<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Admin management page for Payment Unlock Assignment.
// URL: /local/paymentunlockassign/manage.php

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$tab        = optional_param('tab', 'assignments', PARAM_ALPHA);
$edit_assign = optional_param('assignid', 0, PARAM_INT);
$action     = optional_param('action', '', PARAM_ALPHA);

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/paymentunlockassign/manage.php', ['tab' => $tab]);
$PAGE->set_title(get_string('manage', 'local_paymentunlockassign'));
$PAGE->set_heading(get_string('manage', 'local_paymentunlockassign'));
$PAGE->set_pagelayout('admin');
$PAGE->requires->css('/local/paymentunlockassign/styles.css');

// Check plugin is unlocked.
if (!local_paymentunlockassign_check_unlock()) {
    $settings_url  = new moodle_url('/admin/settings.php', ['section' => 'local_paymentunlockassign']);
    $docs_url      = 'https://lms-labs.com/docs/payment-unlock-assignment';
    $siteid        = local_paymentunlockassign_get_siteid();
    $apikey        = local_paymentunlockassign_get_apikey();
    $missing_creds = empty($siteid) || empty($apikey);

    echo $OUTPUT->header();
    echo '<div class="alert alert-danger" role="alert" style="max-width:680px;margin:2rem auto;">';
    echo '<h4 class="alert-heading"><strong>Payment Unlock is not active on this site</strong></h4>';
    echo '<hr>';
    echo '<p>This plugin requires a one-time activation via <strong>1,000 AI Credits</strong>. '
       . 'Complete the two steps below to activate it.</p>';
    echo '<ol style="margin-bottom:1rem;">';

    if ($missing_creds) {
        echo '<li><strong>Configure credentials first.</strong> '
           . 'Your Site ID and/or API Key are not yet saved for this plugin. '
           . '<a href="' . $settings_url->out() . '">Go to plugin settings</a> and enter your Site ID and API Key '
           . '(found on your lms-labs.com dashboard).</li>';
    } else {
        echo '<li><strong>Credentials are configured.</strong> '
           . 'Site ID and API Key are set. '
           . '<a href="' . $settings_url->out() . '">Review settings</a> if the error persists.</li>';
    }

    echo '<li><strong>Unlock the plugin with 1,000 AI Credits.</strong> '
       . 'Go to <a href="' . $docs_url . '" target="_blank">lms-labs.com &rarr; Payment Unlock Assignment</a>, '
       . 'click the <strong>Unlock Plugin</strong> button, enter your Site ID and API Key, and confirm the unlock. '
       . 'The Manage page will become available immediately after the unlock is processed.</li>';
    echo '</ol>';
    echo '<p class="mb-0 text-muted" style="font-size:0.875rem;">If you believe the plugin is already unlocked, '
       . 'the activation server may be temporarily unreachable. Please wait a few minutes and try again, '
       . 'or contact support at lms-labs.com.</p>';
    echo '</div>';
    echo $OUTPUT->footer();
    die;
}

// Handle settings save for an assignment.
if ($action === 'save' && confirm_sesskey()) {
    $rec = new stdClass();
    $rec->assignid        = required_param('assignid', PARAM_INT);
    $rec->courseid        = required_param('courseid', PARAM_INT);
    $rec->enabled         = optional_param('enabled', 0, PARAM_INT);
    $rec->lockdate        = optional_param('lockdate_raw', 0, PARAM_INT);
    $rec->lockdatesource  = optional_param('lockdatesource', 'cutoffdate', PARAM_ALPHA);
    // Sanitise: only allow known values.
    if (!in_array($rec->lockdatesource, ['cutoffdate', 'duedate'], true)) {
        $rec->lockdatesource = 'cutoffdate';
    }
    $rec->hidepassing     = optional_param('hidepassing', 0, PARAM_INT);
    $rec->attempt1fee     = optional_param('attempt1fee', 75, PARAM_FLOAT);
    $rec->attempt2fee     = optional_param('attempt2fee', 125, PARAM_FLOAT);
    $rec->attempt3mode    = optional_param('attempt3mode', 'blocked', PARAM_ALPHA);
    $rec->reopendays      = optional_param('reopendays', 7, PARAM_INT);
    $rec->currency        = optional_param('currency', 'AUD', PARAM_TEXT);
    $rec->timemodified    = time();

    $existing = $DB->get_record('local_payunlock_settings', ['assignid' => $rec->assignid]);
    if ($existing) {
        $rec->id = $existing->id;
        $DB->update_record('local_payunlock_settings', $rec);
    } else {
        $rec->timecreated = time();
        $DB->insert_record('local_payunlock_settings', $rec);
    }

    redirect(
        new moodle_url('/local/paymentunlockassign/manage.php', ['tab' => 'assignments']),
        get_string('saved', 'local_paymentunlockassign'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Handle admin overrides.
if ($action === 'override' && confirm_sesskey()) {
    $override_type = required_param('override_type', PARAM_ALPHA);
    $studentid     = required_param('studentid', PARAM_INT);
    $assignid_ov   = required_param('assignid_ov', PARAM_INT);
    $reason        = optional_param('reason', '', PARAM_TEXT);
    $extra_days    = optional_param('extra_days', 7, PARAM_INT);

    $access = local_paymentunlockassign_get_or_create_access($studentid, $assignid_ov);
    $settings_ov = $DB->get_record('local_payunlock_settings', ['assignid' => $assignid_ov]);
    $reopen_days = $settings_ov ? (int)$settings_ov->reopendays : 7;

    switch ($override_type) {
        case 'reopen':
            $expiry = time() + ($reopen_days * DAYSECS);
            $access->status        = 'reopened';
            $access->unlockexpiry  = $expiry;
            $access->timemodified  = time();
            $DB->update_record('local_payunlock_access', $access);
            local_paymentunlockassign_extend_moodle_assignment($assignid_ov, $studentid, $expiry);
            local_paymentunlockassign_log_event('ADMIN_REOPEN_OVERRIDE', ['reason' => $reason], $studentid, $assignid_ov);
            break;

        case 'extend':
            $new_expiry = ($access->unlockexpiry ?: time()) + ($extra_days * DAYSECS);
            $access->unlockexpiry  = $new_expiry;
            $access->timemodified  = time();
            $DB->update_record('local_payunlock_access', $access);
            local_paymentunlockassign_extend_moodle_assignment($assignid_ov, $studentid, $new_expiry);
            local_paymentunlockassign_log_event('ADMIN_EXTEND_OVERRIDE', ['reason' => $reason, 'extra_days' => $extra_days], $studentid, $assignid_ov);
            break;

        case 'lock':
            $access->status       = 'locked';
            $access->lastlockedat = time();
            $access->timemodified = time();
            $DB->update_record('local_payunlock_access', $access);
            // BUG-LOCK-INSTANT fix: remove the student's Moodle assign_override so
            // Moodle's own UI immediately stops showing "Add Submission". Without this,
            // the override created when the student paid keeps a future duedate active
            // and Moodle renders the button regardless of our plugin's locked status.
            local_paymentunlockassign_remove_moodle_override($assignid_ov, $studentid);
            local_paymentunlockassign_log_event('ADMIN_LOCK_OVERRIDE', ['reason' => $reason], $studentid, $assignid_ov);
            break;

        case 'approve':
            // BUG-PAY-REOPEN fix: previously only set approvalgranted=1 but did NOT
            // set status='reopened', did NOT set unlockexpiry, and did NOT call
            // extend_moodle_assignment(). This meant Moodle still blocked submission
            // (due date unchanged) and derive_status() still returned 'locked', so
            // the hook-injected "Reopen Assignment" button on the assignment page
            // stayed permanently blocked. Mirror the 'reopen' case: set status,
            // set expiry, and extend the Moodle assignment due date so the student
            // can actually submit once they land on the assignment page.
            $expiry = time() + ($reopen_days * DAYSECS);
            $access->approvalgranted = 1;
            $access->status          = 'reopened';
            $access->unlockexpiry    = $expiry;
            $access->timemodified    = time();
            $DB->update_record('local_payunlock_access', $access);
            local_paymentunlockassign_extend_moodle_assignment($assignid_ov, $studentid, $expiry);
            local_paymentunlockassign_log_event('ADMIN_APPROVE_OVERRIDE', ['reason' => $reason], $studentid, $assignid_ov);
            break;
    }

    // Log override record.
    $ov_record = new stdClass();
    $ov_record->studentid    = $studentid;
    $ov_record->assignid     = $assignid_ov;
    $ov_record->adminid      = $USER->id;
    $ov_record->overridetype = $override_type;
    $ov_record->reason       = $reason;
    $ov_record->newexpiry    = $access->unlockexpiry ?? null;
    $ov_record->timecreated  = time();
    $DB->insert_record('local_payunlock_overrides', $ov_record);

    redirect(
        new moodle_url('/local/paymentunlockassign/manage.php', ['tab' => 'access']),
        get_string('override_saved', 'local_paymentunlockassign'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();

// Tabs.
$tabs = [
    new tabobject('assignments', new moodle_url('/local/paymentunlockassign/manage.php', ['tab' => 'assignments']), get_string('tab_assignments', 'local_paymentunlockassign')),
    new tabobject('access',      new moodle_url('/local/paymentunlockassign/manage.php', ['tab' => 'access']),      get_string('tab_access', 'local_paymentunlockassign')),
    new tabobject('payments',    new moodle_url('/local/paymentunlockassign/manage.php', ['tab' => 'payments']),    get_string('tab_payments', 'local_paymentunlockassign')),
    new tabobject('audit',       new moodle_url('/local/paymentunlockassign/manage.php', ['tab' => 'audit']),       get_string('tab_audit', 'local_paymentunlockassign')),
];
echo $OUTPUT->tabtree($tabs, $tab);

// ---- ASSIGNMENTS TAB ----
if ($tab === 'assignments') {

    // If editing a specific assignment.
    if ($edit_assign) {
        $cm = get_coursemodule_from_id('assign', $edit_assign, 0, false, MUST_EXIST);
        $assign_rec = $DB->get_record('assign', ['id' => $cm->instance], '*', MUST_EXIST);
        $settings = $DB->get_record('local_payunlock_settings', ['assignid' => $edit_assign]);
        $def_curr = get_config('local_paymentunlockassign', 'currency') ?: 'AUD';
        $def_days = get_config('local_paymentunlockassign', 'reopen_days') ?: 7;
        $def_fee1 = get_config('local_paymentunlockassign', 'attempt1_fee') ?: 75;
        $def_fee2 = get_config('local_paymentunlockassign', 'attempt2_fee') ?: 125;
        $def_mode = get_config('local_paymentunlockassign', 'attempt3_mode') ?: 'blocked';

        echo '<div class="payunlock-card">';
        echo '<h3>' . get_string('configure_assignment', 'local_paymentunlockassign') . ': ' . format_string($assign_rec->name) . '</h3>';
        echo '<form method="post" action="' . (new moodle_url('/local/paymentunlockassign/manage.php'))->out(false) . '">';
        echo '<input type="hidden" name="action" value="save">';
        echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
        echo '<input type="hidden" name="assignid" value="' . $edit_assign . '">';
        echo '<input type="hidden" name="courseid" value="' . $cm->course . '">';

        $enabled         = $settings ? (int)$settings->enabled : 0;
        $lockdatesource  = ($settings && !empty($settings->lockdatesource)) ? $settings->lockdatesource : 'cutoffdate';
        $hidepassing     = isset($settings->hidepassing) ? (int)$settings->hidepassing : 1;
        // Custom lock date: only pre-fill if an explicit override is stored.
        // Showing the assignment's default dates when no override is set was causing
        // the custom date to be silently saved on every form submit, overriding the
        // lockdatesource dropdown (Priority 1 in get_lockdate() fires on any non-zero value).
        // Leave blank when no custom date is set so get_lockdate() uses lockdatesource.
        $lockdate_display = ($settings && !empty($settings->lockdate))
            ? date('Y-m-d', (int)$settings->lockdate)
            : '';
        $fee1      = $settings ? (float)$settings->attempt1fee : $def_fee1;
        $fee2      = $settings ? (float)$settings->attempt2fee : $def_fee2;
        $mode      = $settings ? $settings->attempt3mode : $def_mode;
        $days      = $settings ? (int)$settings->reopendays : $def_days;
        $curr      = $settings ? $settings->currency : $def_curr;

        echo '<table class="table payunlock-form-table"><tbody>';
        echo '<tr><td><label>Enabled</label></td><td><input type="checkbox" name="enabled" value="1" ' . ($enabled ? 'checked' : '') . '></td></tr>';

        // Lock date source (new in v1.1.0).
        echo '<tr><td><label>' . get_string('lock_date_source', 'local_paymentunlockassign') . '</label></td><td>';
        echo '<select name="lockdatesource" class="form-control" style="width:auto">';
        echo '<option value="cutoffdate" ' . ($lockdatesource === 'cutoffdate' ? 'selected' : '') . '>'
            . get_string('lock_date_source_cutoffdate', 'local_paymentunlockassign') . '</option>';
        echo '<option value="duedate" ' . ($lockdatesource === 'duedate' ? 'selected' : '') . '>'
            . get_string('lock_date_source_duedate', 'local_paymentunlockassign') . '</option>';
        echo '</select>';
        echo '<br><small>' . get_string('lock_date_source_desc', 'local_paymentunlockassign') . '</small>';
        echo '</td></tr>';

        // Custom lock date override.
        // The hidden field carries the Unix timestamp on submit (JS converts date → timestamp).
        // Leave the date input empty when no custom date is stored — empty means "use lockdatesource".
        echo '<tr><td><label>' . get_string('lock_date_custom', 'local_paymentunlockassign') . '</label></td><td>';
        echo '<input type="date" id="lockdate_picker" value="' . htmlspecialchars($lockdate_display) . '" class="form-control">';
        echo '<input type="hidden" name="lockdate_raw" id="lockdate_unix" value="0">';
        echo '<br><small>' . get_string('lock_date_custom_desc', 'local_paymentunlockassign') . '</small>';
        echo '</td></tr>';

        // Hide button for passing students (new in v1.1.0).
        echo '<tr><td><label>' . get_string('hide_passing', 'local_paymentunlockassign') . '</label></td><td>';
        echo '<input type="checkbox" name="hidepassing" value="1" ' . ($hidepassing ? 'checked' : '') . '>';
        echo '<br><small>' . get_string('hide_passing_desc', 'local_paymentunlockassign') . '</small>';
        echo '</td></tr>';
        echo '<tr><td><label>Attempt 1 Fee (' . htmlspecialchars($curr) . ')</label></td><td><input type="number" step="0.01" name="attempt1fee" value="' . $fee1 . '" class="form-control" style="width:120px"></td></tr>';
        echo '<tr><td><label>Attempt 2 Fee (' . htmlspecialchars($curr) . ')</label></td><td><input type="number" step="0.01" name="attempt2fee" value="' . $fee2 . '" class="form-control" style="width:120px"></td></tr>';
        echo '<tr><td><label>Attempt 3+ Policy</label></td><td>';
        echo '<select name="attempt3mode" class="form-control" style="width:auto">';
        echo '<option value="blocked" ' . ($mode === 'blocked' ? 'selected' : '') . '>Blocked</option>';
        echo '<option value="approval" ' . ($mode === 'approval' ? 'selected' : '') . '>Requires admin approval</option>';
        echo '</select></td></tr>';
        echo '<tr><td><label>Reopen Duration (days)</label></td><td><input type="number" name="reopendays" value="' . $days . '" class="form-control" style="width:100px"></td></tr>';
        echo '<tr><td><label>Currency</label></td><td><input type="text" name="currency" value="' . htmlspecialchars($curr) . '" class="form-control" style="width:100px"></td></tr>';
        echo '</tbody></table>';

        echo '<button type="submit" class="btn btn-primary">' . get_string('save', 'local_paymentunlockassign') . '</button> ';
        echo '<a href="' . (new moodle_url('/local/paymentunlockassign/manage.php', ['tab' => 'assignments']))->out(false) . '" class="btn btn-secondary">Cancel</a>';
        echo '</form>';
        echo '</div>';

        // BUG-PAY-WARN fix: inline <script> tags echoed directly from PHP trigger
        // a Moodle debug warning ("Scripts must use $PAGE->requires->js_init_code").
        // Replace with the correct Moodle API so the page passes strict mode checks.
        $js_lockdate = <<<'JSCODE'
require([], function() {
    var f = document.querySelector("form");
    if (!f) { return; }
    f.addEventListener("submit", function() {
        var picker = document.getElementById("lockdate_picker");
        var hidden = document.getElementById("lockdate_unix");
        if (picker && hidden) {
            if (picker.value) {
                // Convert the chosen date to a midnight UTC Unix timestamp.
                hidden.value = Math.floor(new Date(picker.value).getTime() / 1000);
            } else {
                // Empty date = no custom override; send 0 so get_lockdate() uses lockdatesource.
                hidden.value = 0;
            }
        }
    });
});
JSCODE;
        $PAGE->requires->js_init_code($js_lockdate, false);

    } else {
        // List all assignments across all courses.
        $sql = 'SELECT cm.id AS cmid, a.name, c.fullname AS coursename, c.id AS courseid
                FROM {course_modules} cm
                JOIN {assign} a ON a.id = cm.instance
                JOIN {course} c ON c.id = cm.course
                JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                WHERE cm.deletioninprogress = 0
                ORDER BY c.fullname, a.name';
        $assignments = $DB->get_records_sql($sql, ['modname' => 'assign']);

        echo '<div class="payunlock-card">';
        echo '<h3>' . get_string('tab_assignments', 'local_paymentunlockassign') . '</h3>';

        if (empty($assignments)) {
            echo '<p>' . get_string('noassignments', 'local_paymentunlockassign') . '</p>';
        } else {
            echo '<table class="table">';
            echo '<thead><tr><th>' . get_string('course') . '</th><th>' . get_string('assignment', 'local_paymentunlockassign') . '</th>'
                . '<th>' . get_string('enabled', 'local_paymentunlockassign') . '</th>'
                . '<th>' . get_string('attempt1_fee', 'local_paymentunlockassign') . '</th>'
                . '<th>' . get_string('attempt2_fee', 'local_paymentunlockassign') . '</th>'
                . '<th>' . get_string('reopen_days', 'local_paymentunlockassign') . '</th>'
                . '<th>' . get_string('actions', 'local_paymentunlockassign') . '</th></tr></thead>';
            echo '<tbody>';
            foreach ($assignments as $a) {
                $s = $DB->get_record('local_payunlock_settings', ['assignid' => $a->cmid]);
                $edit_url = new moodle_url('/local/paymentunlockassign/manage.php', ['tab' => 'assignments', 'assignid' => $a->cmid]);
                echo '<tr>';
                echo '<td>' . format_string($a->coursename) . '</td>';
                echo '<td>' . format_string($a->name) . '</td>';
                echo '<td>' . ($s && $s->enabled ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-secondary">No</span>') . '</td>';
                echo '<td>' . ($s ? $s->currency . ' $' . number_format((float)$s->attempt1fee, 2) : '-') . '</td>';
                echo '<td>' . ($s ? $s->currency . ' $' . number_format((float)$s->attempt2fee, 2) : '-') . '</td>';
                echo '<td>' . ($s ? (int)$s->reopendays : '-') . '</td>';
                echo '<td><a href="' . $edit_url->out(false) . '" class="btn btn-sm btn-primary">' . get_string('edit', 'local_paymentunlockassign') . '</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';
    }
}

// ---- ACCESS TAB ----
if ($tab === 'access') {
    $sql = 'SELECT pua.*, u.firstname, u.lastname, u.email, a.name AS assignname, c.fullname AS coursename, cm.id AS cmid
            FROM {local_payunlock_access} pua
            JOIN {user} u ON u.id = pua.studentid
            JOIN {course_modules} cm ON cm.id = pua.assignid
            JOIN {assign} a ON a.id = cm.instance
            JOIN {course} c ON c.id = cm.course
            ORDER BY pua.timemodified DESC';
    $accesses = $DB->get_records_sql($sql);

    echo '<div class="payunlock-card">';
    echo '<h3>' . get_string('tab_access', 'local_paymentunlockassign') . '</h3>';

    if (empty($accesses)) {
        echo '<p>' . get_string('noaccess', 'local_paymentunlockassign') . '</p>';
    } else {
        echo '<table class="table payunlock-access-table">';
        echo '<thead><tr><th>' . get_string('student', 'local_paymentunlockassign') . '</th>'
            . '<th>' . get_string('assignment', 'local_paymentunlockassign') . '</th>'
            . '<th>' . get_string('course') . '</th>'
            . '<th>' . get_string('status', 'local_paymentunlockassign') . '</th>'
            . '<th>' . get_string('current_attempt', 'local_paymentunlockassign') . '</th>'
            . '<th>' . get_string('unlock_expiry', 'local_paymentunlockassign') . '</th>'
            . '<th>' . get_string('approval_granted', 'local_paymentunlockassign') . '</th>'
            . '<th>' . get_string('override', 'local_paymentunlockassign') . '</th></tr></thead>';
        echo '<tbody>';
        foreach ($accesses as $acc) {
            echo '<tr>';
            echo '<td>' . fullname($acc) . '<br><small>' . htmlspecialchars($acc->email) . '</small></td>';
            echo '<td>' . format_string($acc->assignname) . '</td>';
            echo '<td>' . format_string($acc->coursename) . '</td>';
            echo '<td><span class="badge badge-' . ($acc->status === 'reopened' ? 'info' : ($acc->status === 'locked' ? 'danger' : ($acc->status === 'submitted' ? 'success' : 'secondary'))) . '">' . htmlspecialchars($acc->status) . '</span></td>';
            echo '<td>' . (int)$acc->currentattempt . '</td>';
            echo '<td>' . ($acc->unlockexpiry ? userdate($acc->unlockexpiry) : '-') . '</td>';
            echo '<td>' . ($acc->approvalgranted ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-secondary">No</span>') . '</td>';
            echo '<td>';
            // Override buttons.
            $base_params = ['action' => 'override', 'sesskey' => sesskey(), 'studentid' => $acc->studentid, 'assignid_ov' => $acc->assignid, 'tab' => 'access'];
            foreach ([
                'reopen'  => get_string('override_reopen', 'local_paymentunlockassign'),
                'extend'  => get_string('override_extend', 'local_paymentunlockassign'),
                'lock'    => get_string('override_lock', 'local_paymentunlockassign'),
                'approve' => get_string('override_approve', 'local_paymentunlockassign'),
            ] as $ov_type => $ov_label) {
                $p = $base_params;
                $p['override_type'] = $ov_type;
                $p['reason'] = 'Admin override';
                if ($ov_type === 'extend') {
                    $p['extra_days'] = 7;
                }
                $url = new moodle_url('/local/paymentunlockassign/manage.php', $p);
                $btn_class = $ov_type === 'lock' ? 'btn-danger' : 'btn-secondary';
                echo '<a href="' . $url->out(false) . '" class="btn btn-sm ' . $btn_class . ' payunlock-ov-btn" onclick="return confirm(\'Apply override: ' . $ov_type . '?\')">' . $ov_label . '</a> ';
            }
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }
    echo '</div>';
}

// ---- PAYMENTS TAB ----
if ($tab === 'payments') {
    $sql = 'SELECT pup.*, u.firstname, u.lastname, a.name AS assignname, c.fullname AS coursename
            FROM {local_payunlock_payments} pup
            JOIN {user} u ON u.id = pup.studentid
            JOIN {course_modules} cm ON cm.id = pup.assignid
            JOIN {assign} a ON a.id = cm.instance
            JOIN {course} c ON c.id = cm.course
            WHERE pup.status = :status
            ORDER BY pup.timepaid DESC';
    $payments_list = $DB->get_records_sql($sql, ['status' => 'success']);

    // Revenue totals by currency.
    $revenue_sql = 'SELECT currency, SUM(amount) AS total, COUNT(*) AS cnt
                    FROM {local_payunlock_payments}
                    WHERE status = :status
                    GROUP BY currency';
    $revenue = $DB->get_records_sql($revenue_sql, ['status' => 'success']);

    echo '<div class="payunlock-card">';
    echo '<h3>' . get_string('tab_payments', 'local_paymentunlockassign') . '</h3>';

    if (!empty($revenue)) {
        echo '<div class="payunlock-revenue-summary">';
        foreach ($revenue as $r) {
            echo '<div class="payunlock-revenue-item">';
            echo '<strong>' . htmlspecialchars($r->currency) . ' $' . number_format((float)$r->total, 2) . '</strong>';
            echo '<span>' . (int)$r->cnt . ' payments</span>';
            echo '</div>';
        }
        echo '</div>';
    }

    if (empty($payments_list)) {
        echo '<p>' . get_string('nopayments', 'local_paymentunlockassign') . '</p>';
    } else {
        echo '<table class="table">';
        echo '<thead><tr><th>' . get_string('student', 'local_paymentunlockassign') . '</th>'
            . '<th>' . get_string('assignment', 'local_paymentunlockassign') . '</th>'
            . '<th>' . get_string('course') . '</th>'
            . '<th>' . get_string('attempt_number', 'local_paymentunlockassign') . '</th>'
            . '<th>' . get_string('amount', 'local_paymentunlockassign') . '</th>'
            . '<th>' . get_string('paid_at', 'local_paymentunlockassign') . '</th>'
            . '<th>' . get_string('invoice', 'local_paymentunlockassign') . '</th></tr></thead>';
        echo '<tbody>';
        foreach ($payments_list as $p) {
            echo '<tr>';
            echo '<td>' . fullname($p) . '</td>';
            echo '<td>' . format_string($p->assignname) . '</td>';
            echo '<td>' . format_string($p->coursename) . '</td>';
            echo '<td>' . (int)$p->attemptnumber . '</td>';
            echo '<td>' . htmlspecialchars($p->currency) . ' $' . number_format((float)$p->amount, 2) . '</td>';
            echo '<td>' . ($p->timepaid ? userdate($p->timepaid) : '-') . '</td>';
            echo '<td>' . htmlspecialchars($p->invoicereference ?: '-') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }
    echo '</div>';
}

// ---- AUDIT TAB ----
if ($tab === 'audit') {
    $audit_records = $DB->get_records('local_payunlock_audit', null, 'timecreated DESC', '*', 0, 100);

    echo '<div class="payunlock-card">';
    echo '<h3>' . get_string('tab_audit', 'local_paymentunlockassign') . '</h3>';

    if (empty($audit_records)) {
        echo '<p>' . get_string('noaudit', 'local_paymentunlockassign') . '</p>';
    } else {
        echo '<table class="table">';
        echo '<thead><tr><th>' . get_string('time', 'local_paymentunlockassign') . '</th>'
            . '<th>' . get_string('event', 'local_paymentunlockassign') . '</th>'
            . '<th>Student ID</th>'
            . '<th>Assign ID</th>'
            . '<th>' . get_string('data', 'local_paymentunlockassign') . '</th></tr></thead>';
        echo '<tbody>';
        foreach ($audit_records as $log) {
            echo '<tr>';
            echo '<td>' . userdate($log->timecreated) . '</td>';
            echo '<td><code>' . htmlspecialchars($log->eventtype) . '</code></td>';
            echo '<td>' . (int)$log->studentid . '</td>';
            echo '<td>' . (int)$log->assignid . '</td>';
            echo '<td><small><code>' . htmlspecialchars(substr($log->eventdata ?? '', 0, 120)) . '</code></small></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }
    echo '</div>';
}

echo $OUTPUT->footer();
