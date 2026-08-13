<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

/**
 * Returns true if the plugin is globally enabled via site settings.
 * Defaults to enabled (returns true) if the config key has not been saved yet.
 * @package    local_paymentunlockassign
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */
function local_paymentunlockassign_is_enabled() {
    $val = get_config('local_paymentunlockassign', 'enabled');
    return ($val === false) ? true : (bool)$val;
}

/**
 * Get Site ID: Central Config takes priority over local setting.
 */
function local_paymentunlockassign_get_siteid() {
    $aiconfig = get_config('local_aiconfig', 'siteid');
    if (!empty($aiconfig)) {
        return $aiconfig;
    }
    return get_config('local_paymentunlockassign', 'siteid');
}

/**
 * Get API Key: Central Config takes priority over local setting.
 */
function local_paymentunlockassign_get_apikey() {
    $aiconfig = get_config('local_aiconfig', 'apikey');
    if (!empty($aiconfig)) {
        return $aiconfig;
    }
    return get_config('local_paymentunlockassign', 'apikey');
}

/**
 * Verify credit unlock with AI Grader server. Fail-closed: deny if unreachable.
 */
function local_paymentunlockassign_check_unlock() {
    global $CFG;
    require_once($CFG->libdir . '/filelib.php');

    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $siteid = local_paymentunlockassign_get_siteid();
    $apikey = local_paymentunlockassign_get_apikey();

    if (empty($siteid) || empty($apikey)) {
        $cache = false;
        return false;
    }

    $url = 'https://lms-labs.com/api/plugin-unlock/verify?pluginId=paymentunlockassign'
        . '&siteId=' . rawurlencode($siteid)
        . '&apiKey=' . rawurlencode($apikey);

    \core\session\manager::write_close();
    $curl = new \curl();
    $curl->setopt(['CURLOPT_TIMEOUT' => 10, 'CURLOPT_SSL_VERIFYPEER' => true]);
    $response = $curl->get($url);
    $http_code = $curl->info['http_code'];

    if ($http_code !== 200 || !$response) {
        $cache = false;
        return false;
    }

    $data = json_decode($response, true);
    $cache = !empty($data['unlocked']);
    return $cache;
}

/**
 * Resolve a student's effective due/cut-off dates for an assignment, honouring
 * Moodle's override precedence: user override > group override (lowest sortorder)
 * > base assignment.
 *
 * FIX-PUA-OVERRIDES (v1.2.0): The plugin previously read dates from the base
 * {assign} row only, so any assignment whose deadlines are managed via group
 * overrides (a standard RTO pattern) was evaluated against the wrong dates.
 * On such assignments get_lockdate() returned 0 (base dates are zero), and
 * combined with FIX-PUA-NODATES this left students permanently locked.
 *
 * NOTE: an override row may have duedate/cutoffdate as NULL, meaning "inherit
 * from base". Only treat a value as authoritative when it is non-null.
 *
 * @param stdClass $assign_record  Base {assign} record (must include id, duedate, cutoffdate).
 * @param int      $studentid      Moodle user id.
 * @param int      $courseid       Course id (used to resolve student group memberships).
 * @return object  {duedate:int, cutoffdate:int}
 */
function local_paymentunlockassign_get_effective_dates($assign_record, $studentid, $courseid) {
    global $DB;

    $duedate    = (int)$assign_record->duedate;
    $cutoffdate = (int)$assign_record->cutoffdate;

    // 1. User override wins outright.
    $user_override = $DB->get_record('assign_overrides', [
        'assignid' => $assign_record->id,
        'userid'   => $studentid,
    ]);
    if ($user_override) {
        if ($user_override->duedate !== null) {
            $duedate = (int)$user_override->duedate;
        }
        if ($user_override->cutoffdate !== null) {
            $cutoffdate = (int)$user_override->cutoffdate;
        }
        return (object)['duedate' => $duedate, 'cutoffdate' => $cutoffdate];
    }

    // 2. Group overrides — lowest sortorder wins (Moodle's own rule).
    $sql = "SELECT o.*
              FROM {assign_overrides} o
              JOIN {groups_members} gm ON gm.groupid = o.groupid
             WHERE o.assignid = :assignid
               AND o.userid IS NULL
               AND gm.userid = :userid
          ORDER BY o.sortorder ASC";
    $group_overrides = $DB->get_records_sql($sql, [
        'assignid' => $assign_record->id,
        'userid'   => $studentid,
    ]);
    if ($group_overrides) {
        $winner = reset($group_overrides);
        if ($winner->duedate !== null) {
            $duedate = (int)$winner->duedate;
        }
        if ($winner->cutoffdate !== null) {
            $cutoffdate = (int)$winner->cutoffdate;
        }
    }

    // 3. Base assignment values (already loaded above).
    return (object)['duedate' => $duedate, 'cutoffdate' => $cutoffdate];
}

/**
 * Get the effective lock date for an assignment.
 *
 * FIX-PUA-CUTOFF (v1.1.0): Changed default lock date source from assignment
 * due date to cut-off date. RTOs use due date as a soft deadline and cut-off
 * as the hard deadline — locking on the soft deadline was triggering the
 * payment prompt too early. Now uses cutoffdate by default (falls back to
 * duedate when no cut-off is set). Configurable per assignment via lockdatesource.
 *
 * Priority order:
 *   1. Custom lockdate stored in settings (non-zero) — admin has explicitly
 *      overridden the automatic date.
 *   2. lockdatesource field: 'cutoffdate' (default) or 'duedate'.
 *   3. If source is 'cutoffdate' but assignment has no cutoffdate set,
 *      fall back to duedate.
 *
 * FIX-PUA-OVERRIDES (v1.2.0): Parameter changed from $assign_record (base row)
 * to $dates (resolved effective dates object). Call
 * local_paymentunlockassign_get_effective_dates() first and pass its result here.
 *
 * @param object        $dates     Effective dates object with duedate and cutoffdate fields.
 * @param stdClass|null $settings  The local_payunlock_settings record.
 * @return int  Unix timestamp of the effective lock date (0 if no date set).
 */
function local_paymentunlockassign_get_lockdate($dates, $settings) {
    // Priority 1: explicit custom date set by admin in plugin settings.
    if (!empty($settings) && !empty($settings->lockdate)) {
        return (int)$settings->lockdate;
    }

    // Priority 2: lockdatesource field ('cutoffdate' is the new default).
    $source = ($settings && !empty($settings->lockdatesource))
        ? $settings->lockdatesource
        : 'cutoffdate';

    if ($source === 'duedate') {
        return (int)$dates->duedate;
    }

    // 'cutoffdate' — fall back to duedate if assignment has no cutoff configured.
    if (!empty($dates->cutoffdate)) {
        return (int)$dates->cutoffdate;
    }
    return (int)$dates->duedate;
}

/**
 * Derive the display status of a student access record.
 */
function local_paymentunlockassign_derive_status($access, $lockdate, $now = null) {
    if ($now === null) {
        $now = time();
    }
    // Submitted is always terminal — nothing overrides it.
    if (!empty($access->submittedat)) {
        return 'submitted';
    }
    // Active reopen window — student paid and expiry is still in the future.
    if ($access->status === 'reopened' && !empty($access->unlockexpiry) && $access->unlockexpiry > $now) {
        return 'reopened';
    }
    // BUG-LOCK-INSTANT fix: explicit teacher-set 'locked' always wins.
    // Without this check, if lockdate > now the function returned 'available'
    // regardless of the teacher having clicked "Lock Immediately", causing
    // the before_footer hook to skip injection and leaving "Add Submission"
    // visible to the student.
    if ($access->status === 'locked') {
        return 'locked';
    }
    // FIX-PUA-NODATES (v1.1.3): If no lock date is configured at all (lockdate=0),
    // the assignment has no automatic lock trigger — treat the student as available.
    // Without this check, empty($lockdate) falls through to the comparison below
    // where (0 > time()) is always false, returning 'locked' and redirecting every
    // student to the payment page even though no cutoff/due date has been set.
    if (empty($lockdate)) {
        return 'available';
    }
    if ($lockdate > $now) {
        return 'available';
    }
    return 'locked';
}

/**
 * Check whether a student has achieved a passing grade for an assignment.
 *
 * FIX-PUA-HIDEPASSING (v1.1.0): Used to suppress the "Reopen Assignment"
 * payment prompt for students who have already achieved a passing grade.
 * Students who pass do not need to reopen or pay — showing the button
 * confuses them. Returns false (not passed) when no passing grade threshold
 * is configured on the assignment so the prompt is always shown in that case.
 *
 * @param int $studentid   Moodle user id of the student.
 * @param int $assignid    Course module id (cmid) of the assignment.
 * @return bool  True if the student has a final grade >= the grade item's gradepass.
 */
function local_paymentunlockassign_student_has_passed($studentid, $assignid) {
    global $DB;

    $cm = get_coursemodule_from_id('assign', $assignid);
    if (!$cm) {
        return false;
    }

    // Look up the grade item for this assignment.
    $gradeitem = $DB->get_record_sql(
        "SELECT gi.id, gi.gradepass, gi.grademax
           FROM {grade_items} gi
          WHERE gi.itemtype = 'mod'
            AND gi.itemmodule = 'assign'
            AND gi.iteminstance = :instance
            AND gi.courseid = :courseid",
        ['instance' => (int)$cm->instance, 'courseid' => (int)$cm->course]
    );

    // No passing grade configured on this assignment — cannot determine pass.
    if (!$gradeitem || (float)$gradeitem->gradepass <= 0) {
        return false;
    }

    $gradegrade = $DB->get_record('grade_grades', [
        'itemid' => $gradeitem->id,
        'userid' => $studentid,
    ]);

    if (!$gradegrade || $gradegrade->finalgrade === null) {
        return false;
    }

    return (float)$gradegrade->finalgrade >= (float)$gradeitem->gradepass;
}

/**
 * Determine whether the paywall (locked notice / Reopen button) should be
 * shown to the current student for this assignment.
 *
 * Returns false (suppress paywall) when:
 *   - The status is not 'locked'.
 *   - hidepassing=1 is configured AND the student has a passing grade.
 *
 * @param stdClass      $access    Student access record.
 * @param stdClass      $settings  Plugin settings record for this assignment.
 * @param int           $lockdate  Effective lock timestamp.
 * @param int           $studentid Moodle user id.
 * @param int           $assignid  Course module id.
 * @param int|null      $now       Current timestamp (defaults to time()).
 * @return bool  True = show paywall. False = suppress it.
 */
function local_paymentunlockassign_should_show_paywall($access, $settings, $lockdate, $studentid, $assignid, $now = null) {
    if ($now === null) {
        $now = time();
    }

    $status = local_paymentunlockassign_derive_status($access, $lockdate, $now);
    if ($status !== 'locked') {
        return false;
    }

    // FIX-PUA-HIDEPASSING (v1.1.0): Suppress paywall for students who have a
    // passing grade. hidepassing defaults to 1 when the column is absent
    // (pre-v1.1.0 settings row) so the safer behaviour (hide for passing
    // students) applies automatically on upgrade without admin action.
    $hidepassing = isset($settings->hidepassing) ? (int)$settings->hidepassing : 1;
    if ($hidepassing && local_paymentunlockassign_student_has_passed($studentid, $assignid)) {
        return false;
    }

    return true;
}

/**
 * Remove a student's Moodle assign_override for a given assignment.
 * Called when a teacher locks a student immediately — removes the extended
 * due-date override that was created when the student paid to reopen,
 * so that Moodle's own UI immediately stops showing "Add Submission".
 *
 * @param int $assignid  local_payunlock_access.assignid (= course-module id).
 * @param int $studentid User ID of the student.
 */
function local_paymentunlockassign_remove_moodle_override($assignid, $studentid) {
    global $DB;
    $cm = get_coursemodule_from_id('assign', $assignid);
    if (!$cm) {
        return;
    }
    $DB->delete_records('assign_overrides', [
        'assignid' => $cm->instance,
        'userid'   => $studentid,
    ]);
}

/**
 * Get or create a student access record.
 */
function local_paymentunlockassign_get_or_create_access($studentid, $assignid) {
    global $DB;
    $access = $DB->get_record('local_payunlock_access', ['studentid' => $studentid, 'assignid' => $assignid]);
    if (!$access) {
        $access = new stdClass();
        $access->studentid      = $studentid;
        $access->assignid       = $assignid;
        $access->status         = 'available';
        $access->currentattempt = 0;
        $access->unlockexpiry   = null;
        $access->approvalgranted = 0;
        $access->submittedat    = null;
        $access->lastlockedat   = null;
        $access->timecreated    = time();
        $access->timemodified   = time();
        $access->id = $DB->insert_record('local_payunlock_access', $access);
    }
    return $access;
}

/**
 * Log an audit event.
 */
function local_paymentunlockassign_log_event($eventtype, $data = [], $studentid = null, $assignid = null, $userid = null) {
    global $DB, $USER;
    $record = new stdClass();
    $record->userid      = $userid ?? ($USER->id ?? null);
    $record->studentid   = $studentid;
    $record->assignid    = $assignid;
    $record->eventtype   = $eventtype;
    $record->eventdata   = json_encode($data);
    $record->timecreated = time();
    $DB->insert_record('local_payunlock_audit', $record);
}

/**
 * Returns true if the assignment has any GROUP overrides in assign_overrides.
 *
 * FIX-PUA-GROUPGUARD (v1.2.2): In Moodle, a user override permanently outranks
 * ALL group overrides — once a user override exists for a student, any later edit
 * to the group override is silently ignored for that student. RTOs commonly manage
 * cohort deadlines via group overrides (e.g. GD1–GD9 on BSBLDR811). Writing a user
 * override on such an assignment pins the student to whatever dates the group had at
 * the moment the plugin wrote the override. If the RTO later extends the group
 * deadline, the stale user override keeps the student locked out silently.
 *
 * This function is the single check point — call it before writing any user override.
 *
 * @param int $assigninstance The {assign}.id (NOT the cmid).
 * @return bool
 */
function local_paymentunlockassign_has_group_overrides($assigninstance) {
    global $DB;
    return $DB->record_exists_select(
        'assign_overrides',
        'assignid = :assignid AND userid IS NULL AND groupid IS NOT NULL',
        ['assignid' => (int)$assigninstance]
    );
}

/**
 * Ensure a student has a Moodle assignment override covering the "free window"
 * between the assignment's due date and cut-off date.
 *
 * FIX-PUA-FREEWINDOW (v1.1.1): Moodle's "Prevent late submissions" setting hides
 * the "Add submission" button after the due date — even when a cut-off date is set
 * and the plugin hasn't locked the student yet. This function creates (or updates)
 * an assign_overrides row that extends the student's personal due date to the
 * cut-off date, so Moodle shows the button during the free window.
 *
 * Only called when: status='available' AND now > duedate AND now < cutoffdate.
 * The override is idempotent — if one already exists that covers until cut-off,
 * it is left unchanged.
 *
 * @param int $assignid   Course module id of the assignment.
 * @param int $studentid  Moodle user id of the student.
 * @param int $cutoffdate Unix timestamp of the assignment's cut-off date.
 * @return bool
 */
function local_paymentunlockassign_ensure_free_window_override($assignid, $studentid, $cutoffdate) {
    global $DB;
    $cm = get_coursemodule_from_id('assign', $assignid);
    if (!$cm) {
        return false;
    }

    // FIX-PUA-GROUPCLOBBER (v1.2.1) / FIX-PUA-GROUPGUARD (v1.2.2):
    // A user override permanently outranks all group overrides in Moodle. On
    // assignments managed via group overrides, skip user-override creation
    // entirely. The free-window button will not auto-appear on such assignments
    // until a non-invasive approach is implemented (v1.3.x).
    if (local_paymentunlockassign_has_group_overrides($cm->instance)) {
        local_paymentunlockassign_log_event(
            'freewindow_override_skipped_group_managed',
            ['assignid' => $assignid, 'assigninstance' => $cm->instance],
            $studentid, $assignid
        );
        return 'skipped';
    }

    $existing = $DB->get_record('assign_overrides', [
        'assignid' => $cm->instance,
        'userid'   => $studentid,
    ]);
    if ($existing) {
        // Only update if the current override doesn't already cover until cut-off.
        if ((int)$existing->duedate >= (int)$cutoffdate) {
            return 'ok'; // Already covers until cut-off — nothing to do.
        }
        $override              = clone $existing;
        $override->duedate    = (int)$cutoffdate;
        $override->cutoffdate = (int)$cutoffdate;
        $DB->update_record('assign_overrides', $override);
        return 'ok'; // Updated existing override.
    } else {
        $override                           = new stdClass();
        $override->assignid                 = $cm->instance;
        $override->userid                   = $studentid;
        $override->duedate                  = (int)$cutoffdate;
        $override->cutoffdate               = (int)$cutoffdate;
        $override->allowsubmissionsfromdate = null;
        $override->timelimit                = null;
        $DB->insert_record('assign_overrides', $override);
        // FIX-PUA-OVERRIDE-TIMING (v1.1.3): Return 'new' so the caller knows a fresh
        // override was just inserted. The before_http_headers hook uses this to redirect
        // the student to the same page, ensuring Moodle's assign module reads the
        // freshly-written override from the DB at the start of the next request rather
        // than potentially serving a state that was computed before the override existed.
        return 'new';
    }
}

/**
 * Extend Moodle assignment due date for a specific student via assign_overrides.
 * @param int $assignid   Assignment cmid (course module id)
 * @param int $studentid  Moodle user id
 * @param int $newduedate Unix timestamp for new due date
 */
function local_paymentunlockassign_extend_moodle_assignment($assignid, $studentid, $newduedate) {
    global $DB;
    $cm = get_coursemodule_from_id('assign', $assignid);
    if (!$cm) {
        return false;
    }

    $assign_record = $DB->get_record('assign', ['id' => $cm->instance]);

    // FIX-PUA-NOSHORTEN (v1.2.2): Never write an override that gives the student
    // less time than they currently have from their group or base dates. Resolve the
    // student's effective dates first and take the max of each field. This prevents
    // the scenario where a student pays $250 to reopen and ends up with a shorter
    // deadline than classmates who paid nothing — which happens when
    // $newduedate + DAYSECS is earlier than the student's existing group cut-off.
    $effective  = local_paymentunlockassign_get_effective_dates($assign_record, $studentid, $cm->course);
    $newcutoff  = (int)$newduedate + DAYSECS;
    $newduedate = max((int)$newduedate, (int)$effective->duedate);
    $newcutoff  = max($newcutoff, (int)$effective->cutoffdate);

    $existing = $DB->get_record('assign_overrides', ['assignid' => $cm->instance, 'userid' => $studentid]);
    if ($existing) {
        $override = clone $existing;
        $override->duedate    = $newduedate;
        $override->cutoffdate = $newcutoff;
        $DB->update_record('assign_overrides', $override);
    } else {
        $override = new stdClass();
        $override->assignid               = $cm->instance;
        $override->userid                 = $studentid;
        $override->duedate                = $newduedate;
        $override->cutoffdate             = $newcutoff;
        $override->allowsubmissionsfromdate = null;
        $override->timelimit              = null;
        $DB->insert_record('assign_overrides', $override);
    }
    return true;
}

/**
 * Create a Stripe checkout session via HTTP.
 * Returns ['url' => '...', 'session_id' => '...'] or throws on error.
 */
function local_paymentunlockassign_create_stripe_session($studentid, $assignid, $courseid, $attempt_number, $amount, $currency, $assign_name, $success_url, $cancel_url) {
    global $CFG;
    require_once($CFG->libdir . '/filelib.php');

    $stripe_secret = get_config('local_paymentunlockassign', 'stripe_secret_key');
    if (empty($stripe_secret)) {
        throw new moodle_exception('Stripe is not configured. Contact your administrator.');
    }

    $postdata = http_build_query([
        'mode'                                            => 'payment',
        'success_url'                                     => $success_url,
        'cancel_url'                                      => $cancel_url,
        'payment_method_types[]'                          => 'card',
        'line_items[0][price_data][currency]'             => strtolower($currency),
        'line_items[0][price_data][unit_amount]'          => (int)round($amount * 100),
        'line_items[0][price_data][product_data][name]'   => 'Late Submission Unlock - Attempt ' . $attempt_number,
        'line_items[0][price_data][product_data][description]' => $assign_name,
        'line_items[0][quantity]'                         => '1',
        'metadata[studentid]'                             => $studentid,
        'metadata[assignid]'                              => $assignid,
        'metadata[courseid]'                              => $courseid,
        'metadata[attempt_number]'                        => $attempt_number,
    ]);

    \core\session\manager::write_close();
    $curl2 = new \curl();
    $curl2->setopt(['CURLOPT_TIMEOUT' => 20, 'CURLOPT_SSL_VERIFYPEER' => true, 'CURLOPT_USERPWD' => $stripe_secret . ':', 'CURLOPT_HTTPAUTH' => CURLAUTH_BASIC]);
    $response = $curl2->post('https://api.stripe.com/v1/checkout/sessions', $postdata);
    $http_code = $curl2->info['http_code'];

    if (!$response) {
        throw new moodle_exception('Failed to connect to Stripe. Please try again later.');
    }

    $data = json_decode($response, true);
    if ($http_code !== 200 || empty($data['url'])) {
        $msg = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown Stripe error';
        throw new moodle_exception('Stripe error: ' . $msg);
    }

    return ['url' => $data['url'], 'session_id' => $data['id']];
}

/**
 * Verify Stripe webhook signature.
 * @param string $payload    Raw POST body
 * @param string $sig_header HTTP_STRIPE_SIGNATURE header value
 * @param string $secret     Webhook signing secret
 * @return bool
 */
function local_paymentunlockassign_verify_stripe_signature($payload, $sig_header, $secret) {
    $timestamp   = null;
    $signatures  = [];
    foreach (explode(',', $sig_header) as $part) {
        $kv = explode('=', trim($part), 2);
        if (count($kv) !== 2) {
            continue;
        }
        if ($kv[0] === 't') {
            $timestamp = $kv[1];
        }
        if ($kv[0] === 'v1') {
            $signatures[] = $kv[1];
        }
    }
    if (!$timestamp || empty($signatures)) {
        return false;
    }
    // Reject webhooks older than 5 minutes to prevent replay attacks.
    // Stripe recommends a 300-second tolerance window.
    if (abs(time() - (int)$timestamp) > 300) {
        return false;
    }
    $signed_payload  = $timestamp . '.' . $payload;
    $expected        = hash_hmac('sha256', $signed_payload, $secret);
    return in_array($expected, $signatures, true);
}
