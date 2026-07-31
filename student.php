<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Student payment page - students visit this page when their assignment is locked
// to view their status and make a payment to reopen.
// URL: /local/paymentunlockassign/student.php?assignid=<cmid>&courseid=<courseid>

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$assignid = required_param('assignid', PARAM_INT); // course module id
$courseid = optional_param('courseid', 0, PARAM_INT);
$payment  = optional_param('payment', '', PARAM_ALPHA);
$action   = optional_param('action', '', PARAM_ALPHA);

// Verify plugin is globally enabled.
if (!local_paymentunlockassign_is_enabled()) {
    // Redirect silently to the assignment page — globally disabled means no payment UI.
    redirect(
        new moodle_url('/mod/assign/view.php', ['id' => $assignid]),
        get_string('plugin_globally_disabled', 'local_paymentunlockassign'),
        null,
        \core\output\notification::NOTIFY_INFO
    );
}

// Verify plugin is unlocked.
if (!local_paymentunlockassign_check_unlock()) {
    $PAGE->set_context(context_system::instance());
    $PAGE->set_title(get_string('pluginname', 'local_paymentunlockassign'));
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('plugin_not_unlocked', 'local_paymentunlockassign'), 'error');
    echo $OUTPUT->footer();
    die;
}

// Get course module and assignment.
$cm = get_coursemodule_from_id('assign', $assignid, 0, false, MUST_EXIST);
$course = get_course($cm->course);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);

$assign_record = $DB->get_record('assign', ['id' => $cm->instance], '*', MUST_EXIST);

// Get plugin settings for this assignment.
$settings = $DB->get_record('local_payunlock_settings', ['assignid' => $assignid]);

// Redirect if plugin not enabled for this assignment.
if (!$settings || !$settings->enabled) {
    redirect(
        new moodle_url('/mod/assign/view.php', ['id' => $assignid]),
        get_string('not_configured', 'local_paymentunlockassign'),
        null,
        \core\output\notification::NOTIFY_WARNING
    );
}

// Get or create student access record.
$access = local_paymentunlockassign_get_or_create_access($USER->id, $assignid);

// FIX-PUA-OVERRIDES (v1.2.1): Resolve this student's effective dates, honouring
// Moodle's override precedence: user override > group override > base assignment.
// student.php was missed in v1.2.0. Without this, a student on a group-override
// assignment who lands on the payment page would be evaluated against the base dates
// (zero on BSBLDR811-style assignments), derive 'locked', and be stuck in a payment
// loop even though the hooks correctly saw them as 'available'.
$dates    = local_paymentunlockassign_get_effective_dates($assign_record, $USER->id, $cm->course);
$lockdate = local_paymentunlockassign_get_lockdate($dates, $settings);
$now      = time();
$status   = local_paymentunlockassign_derive_status($access, $lockdate, $now);

// Handle checkout redirect action.
if ($action === 'checkout' && confirm_sesskey()) {
    // Validate student can pay.
    if ($status === 'submitted') {
        redirect(new moodle_url('/local/paymentunlockassign/student.php', ['assignid' => $assignid, 'courseid' => $courseid]));
    }
    if ($status === 'reopened') {
        redirect(new moodle_url('/local/paymentunlockassign/student.php', ['assignid' => $assignid, 'courseid' => $courseid]));
    }
    if ($status !== 'locked') {
        redirect(new moodle_url('/local/paymentunlockassign/student.php', ['assignid' => $assignid, 'courseid' => $courseid]));
    }

    $next_attempt = $access->currentattempt + 1;

    // Check attempt 3 rules.
    if ($next_attempt >= 3) {
        if ($settings->attempt3mode === 'blocked') {
            redirect(
                new moodle_url('/local/paymentunlockassign/student.php', ['assignid' => $assignid, 'courseid' => $courseid]),
                get_string('attempt3_blocked_msg', 'local_paymentunlockassign'),
                null,
                \core\output\notification::NOTIFY_WARNING
            );
        }
        if ($settings->attempt3mode === 'approval' && !$access->approvalgranted) {
            redirect(
                new moodle_url('/local/paymentunlockassign/student.php', ['assignid' => $assignid, 'courseid' => $courseid]),
                get_string('attempt3_approval_msg', 'local_paymentunlockassign'),
                null,
                \core\output\notification::NOTIFY_WARNING
            );
        }
    }

    // Determine fee.
    if ($next_attempt === 1) {
        $fee = (float)$settings->attempt1fee;
    } else {
        $fee = (float)$settings->attempt2fee;
    }
    $currency = $settings->currency ?: 'AUD';

    $success_url = (new moodle_url('/local/paymentunlockassign/student.php', [
        'assignid' => $assignid,
        'courseid' => $courseid,
        'payment'  => 'success',
    ]))->out(false);
    $cancel_url = (new moodle_url('/local/paymentunlockassign/student.php', [
        'assignid' => $assignid,
        'courseid' => $courseid,
        'payment'  => 'cancelled',
    ]))->out(false);

    try {
        $stripe_result = local_paymentunlockassign_create_stripe_session(
            $USER->id,
            $assignid,
            $courseid,
            $next_attempt,
            $fee,
            $currency,
            $assign_record->name,
            $success_url,
            $cancel_url
        );

        // Record pending payment.
        $payment_record = new stdClass();
        $payment_record->studentid       = $USER->id;
        $payment_record->assignid        = $assignid;
        $payment_record->attemptnumber   = $next_attempt;
        $payment_record->amount          = $fee;
        $payment_record->currency        = $currency;
        $payment_record->stripesessionid = $stripe_result['session_id'];
        $payment_record->status          = 'pending';
        $payment_record->timecreated     = time();
        $DB->insert_record('local_payunlock_payments', $payment_record);

        local_paymentunlockassign_log_event(
            'CHECKOUT_SESSION_CREATED',
            ['attempt' => $next_attempt, 'fee' => $fee, 'session_id' => $stripe_result['session_id']],
            $USER->id,
            $assignid
        );

        redirect($stripe_result['url']);

    } catch (Exception $e) {
        redirect(
            new moodle_url('/local/paymentunlockassign/student.php', ['assignid' => $assignid, 'courseid' => $courseid]),
            $e->getMessage(),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

// Set page context.
$PAGE->set_context($context);
$PAGE->set_cm($cm, $course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_url('/local/paymentunlockassign/student.php', ['assignid' => $assignid, 'courseid' => $courseid]);
$PAGE->set_title(get_string('student_page_title', 'local_paymentunlockassign'));
$PAGE->set_heading($course->fullname);
$PAGE->requires->css('/local/paymentunlockassign/styles.css');

// Payment history for this student/assignment.
$payment_records = $DB->get_records('local_payunlock_payments', [
    'studentid' => $USER->id,
    'assignid'  => $assignid,
    'status'    => 'success',
], 'timepaid DESC');

// Next attempt info.
$next_attempt = $access->currentattempt + 1;
if ($next_attempt === 1) {
    $next_fee = (float)$settings->attempt1fee;
} else {
    $next_fee = (float)$settings->attempt2fee;
}
$currency = $settings->currency ?: 'AUD';

echo $OUTPUT->header();
echo '<div class="payunlock-container">';

// Assignment header.
echo '<div class="payunlock-card payunlock-header-card">';
echo '<h2>' . format_string($assign_record->name) . '</h2>';
echo '<p class="payunlock-course">' . format_string($course->fullname) . '</p>';
echo '</div>';

// Status notification from Stripe redirect.
if ($payment === 'success') {
    echo $OUTPUT->notification(get_string('payment_success', 'local_paymentunlockassign'), 'success');
    // Refresh access after successful payment.
    $access = local_paymentunlockassign_get_or_create_access($USER->id, $assignid);
    $status = local_paymentunlockassign_derive_status($access, $lockdate, $now);
} else if ($payment === 'cancelled') {
    echo $OUTPUT->notification(get_string('payment_cancelled', 'local_paymentunlockassign'), 'warning');
}

// Current Status card.
echo '<div class="payunlock-card">';
echo '<h3>' . get_string('assignment_status', 'local_paymentunlockassign') . '</h3>';

$status_labels = [
    'available'  => ['class' => 'success', 'label' => get_string('status_available', 'local_paymentunlockassign')],
    'reopened'   => ['class' => 'info',    'label' => get_string('status_reopened', 'local_paymentunlockassign')],
    'submitted'  => ['class' => 'success', 'label' => get_string('status_submitted', 'local_paymentunlockassign')],
    'locked'     => ['class' => 'danger',  'label' => get_string('status_locked', 'local_paymentunlockassign')],
];
$sl = $status_labels[$status] ?? ['class' => 'secondary', 'label' => ucfirst($status)];
echo '<p><strong>' . get_string('status', 'local_paymentunlockassign') . ':</strong> '
    . '<span class="badge badge-' . $sl['class'] . ' payunlock-status-badge">' . $sl['label'] . '</span></p>';

if ($status === 'reopened' && $access->unlockexpiry) {
    echo '<p>' . get_string('reopen_expires', 'local_paymentunlockassign', userdate($access->unlockexpiry)) . '</p>';
}

echo '<p><strong>' . get_string('current_attempt', 'local_paymentunlockassign') . ':</strong> ' . (int)$access->currentattempt . '</p>';

if ($lockdate) {
    echo '<p><strong>' . get_string('lock_date', 'local_paymentunlockassign') . ':</strong> ' . userdate($lockdate) . '</p>';
}

echo '</div>';

// Payment action card (only show if locked).
if ($status === 'locked') {
    echo '<div class="payunlock-card payunlock-payment-card">';
    echo '<h3>' . get_string('payment_required', 'local_paymentunlockassign') . '</h3>';

    // Check attempt 3 blocks.
    if ($next_attempt >= 3 && $settings->attempt3mode === 'blocked') {
        echo '<div class="alert alert-danger">' . get_string('attempt3_blocked_msg', 'local_paymentunlockassign') . '</div>';
    } else if ($next_attempt >= 3 && $settings->attempt3mode === 'approval' && !$access->approvalgranted) {
        echo '<div class="alert alert-warning">' . get_string('attempt3_approval_msg', 'local_paymentunlockassign') . '</div>';
    } else {
        echo '<p>' . get_string('attempt_label', 'local_paymentunlockassign', $next_attempt) . '</p>';
        echo '<p class="payunlock-fee">' . get_string('fee_label', 'local_paymentunlockassign', $currency . ' $' . number_format($next_fee, 2)) . '</p>';
        echo '<p class="payunlock-duration">Unlocked for ' . (int)$settings->reopendays . ' days after payment.</p>';

        $checkout_url = new moodle_url('/local/paymentunlockassign/student.php', [
            'assignid'  => $assignid,
            'courseid'  => $courseid,
            'action'    => 'checkout',
            'sesskey'   => sesskey(),
        ]);
        echo '<a href="' . $checkout_url->out(false) . '" class="btn btn-primary btn-lg payunlock-pay-btn">'
            . get_string('pay_now', 'local_paymentunlockassign') . '</a>';
    }

    echo '</div>';
}

// Fee schedule card.
echo '<div class="payunlock-card">';
echo '<h3>' . get_string('fee_rules_heading', 'local_paymentunlockassign') . '</h3>';
echo '<table class="table">';
echo '<thead><tr><th>' . get_string('attempt_number', 'local_paymentunlockassign') . '</th><th>' . get_string('fee_amount', 'local_paymentunlockassign') . '</th></tr></thead>';
echo '<tbody>';
echo '<tr><td>1</td><td>' . $currency . ' $' . number_format((float)$settings->attempt1fee, 2) . '</td></tr>';
echo '<tr><td>2</td><td>' . $currency . ' $' . number_format((float)$settings->attempt2fee, 2) . '</td></tr>';
if ($settings->attempt3mode === 'blocked') {
    echo '<tr><td>3+</td><td>Blocked</td></tr>';
} else {
    echo '<tr><td>3+</td><td>Requires admin approval</td></tr>';
}
echo '</tbody></table>';
echo '</div>';

// Payment history.
echo '<div class="payunlock-card">';
echo '<h3>' . get_string('payment_history', 'local_paymentunlockassign') . '</h3>';
if (empty($payment_records)) {
    echo '<p>' . get_string('no_payment_history', 'local_paymentunlockassign') . '</p>';
} else {
    echo '<table class="table">';
    echo '<thead><tr><th>' . get_string('attempt_number', 'local_paymentunlockassign') . '</th><th>' . get_string('amount', 'local_paymentunlockassign') . '</th><th>' . get_string('paid_at', 'local_paymentunlockassign') . '</th></tr></thead>';
    echo '<tbody>';
    foreach ($payment_records as $pr) {
        echo '<tr>';
        echo '<td>' . (int)$pr->attemptnumber . '</td>';
        echo '<td>' . $pr->currency . ' $' . number_format((float)$pr->amount, 2) . '</td>';
        echo '<td>' . ($pr->timepaid ? userdate($pr->timepaid) : '-') . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}
echo '</div>';

// Return to assignment link.
$assign_url = new moodle_url('/mod/assign/view.php', ['id' => $assignid]);
echo '<div class="payunlock-actions">';
echo '<a href="' . $assign_url->out(false) . '" class="btn btn-secondary">' . get_string('return_to_assignment', 'local_paymentunlockassign') . '</a>';
echo '</div>';

echo '</div>'; // .payunlock-container

echo $OUTPUT->footer();
