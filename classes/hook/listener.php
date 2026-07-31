<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_paymentunlockassign\hook;

/**
 * Hook listener for local_paymentunlockassign.
 *
 * Injects the "Reopen Assignment" button on the Moodle assignment submission
 * page when the assignment is locked for the current student.
 *
 * Uses core\hook\output\before_footer_html_generation (Moodle 4.3+).
 *
 * @package   local_paymentunlockassign
 * @copyright 2026 Essay Grader AI
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class listener {

    /**
     * Redirect locked students away from the assignment submission form.
     *
     * Called by core\hook\output\before_http_headers (Moodle 4.3+).
     * Fires before any HTTP headers are sent, so redirect() works correctly.
     *
     * BUG-LOCK-INSTANT (v1.0.13): When an admin applied a lock override the DB
     * was updated immediately but students who still had the page open could still
     * click "Add Submission". This hook redirects them on the very next request.
     *
     * FIX-PUA-HIDEPASSING (v1.1.0): Redirect is suppressed for students who have
     * a passing grade and hidepassing=1 is set for the assignment — passing students
     * should never see the payment prompt.
     *
     * @param \core\hook\output\before_http_headers $hook
     */
    public static function before_http_headers(\core\hook\output\before_http_headers $hook): void {
        global $DB, $USER, $CFG;

        require_once($CFG->dirroot . '/local/paymentunlockassign/lib.php');

        if (!isloggedin() || isguestuser()) {
            return;
        }

        // Bail out immediately if the plugin is globally disabled.
        if (!\local_paymentunlockassign_is_enabled()) {
            return;
        }

        // Only intercept mod/assign/view.php requests.
        $script = (string)($_SERVER['SCRIPT_FILENAME'] ?? '');
        if (!preg_match('#/mod/assign/view\.php$#', $script)) {
            return;
        }

        $cmid = optional_param('id', 0, PARAM_INT);
        if (!$cmid) {
            return;
        }

        $settings = $DB->get_record('local_payunlock_settings', ['assignid' => $cmid]);
        if (!$settings || !$settings->enabled) {
            return;
        }

        // Teachers / graders are never affected.
        $context = \context_module::instance($cmid);
        if (has_capability('mod/assign:grade', $context)) {
            return;
        }

        $cm = get_coursemodule_from_id('assign', $cmid);
        if (!$cm) {
            return;
        }
        $assign_record = $DB->get_record('assign', ['id' => $cm->instance]);
        if (!$assign_record) {
            return;
        }

        // FIX-PUA-OVERRIDES (v1.2.0): Resolve this student's effective dates, honouring
        // Moodle's override precedence: user override > group override > base assignment.
        // Previously only the base {assign} row was read, so group-override-driven
        // assignments (base dates = 0) were evaluated against zero dates — combined with
        // FIX-PUA-NODATES this permanently locked every student on such assignments.
        $dates = local_paymentunlockassign_get_effective_dates($assign_record, $USER->id, $cm->course);

        $access   = local_paymentunlockassign_get_or_create_access($USER->id, $cmid);
        $lockdate = local_paymentunlockassign_get_lockdate($dates, $settings);
        $now      = time();
        $action   = optional_param('action', '', PARAM_ALPHA);

        // FIX-PUA-FREEWINDOW (v1.1.1): Student is in the window between the assignment
        // due date and the cut-off date. The plugin hasn't locked them yet (status =
        // 'available'), but Moodle's "Prevent late submissions" setting hides the
        // "Add submission" button after the due date. Fix: create a personal Moodle
        // override extending the student's due date to the cut-off date so Moodle
        // shows the button. This fires on EVERY view request (not just editsubmission)
        // so the button is visible as soon as the student opens the assignment page.
        // Uses $dates (effective dates) so group-override deadlines are respected.
        $status = local_paymentunlockassign_derive_status($access, $lockdate, $now);
        if ($status === 'available'
            && !empty($dates->duedate)
            && $now > (int)$dates->duedate
            && !empty($dates->cutoffdate)
            && $now < (int)$dates->cutoffdate
        ) {
            local_paymentunlockassign_ensure_free_window_override(
                $cmid,
                $USER->id,
                (int)$dates->cutoffdate
            );
            // Override is in place (existing or just created). Let the request through.
            // NOTE: No redirect here — before_http_headers fires before mod_assign builds
            // its renderer state, so the override written above is visible to mod_assign
            // in the same request. A redirect would risk an infinite loop.
            return;
        }

        // LOCKED path: only redirect on editsubmission attempts.
        if ($action !== 'editsubmission') {
            return;
        }

        // FIX-PUA-HIDEPASSING (v1.1.0): Use should_show_paywall() which also checks
        // whether the student has a passing grade (and hidepassing is enabled).
        if (!local_paymentunlockassign_should_show_paywall($access, $settings, $lockdate, $USER->id, $cmid, $now)) {
            return;
        }

        // Consistency fix (v1.2.0): before_footer only shows the Reopen button when
        // the site licence is active. Do not redirect to a payment page the student
        // cannot act on if the licensing server is currently unreachable.
        if (!local_paymentunlockassign_check_unlock()) {
            return;
        }

        $courseid = optional_param('course', 0, PARAM_INT);
        if (!$courseid && !empty($cm->course)) {
            $courseid = $cm->course;
        }
        redirect(new \moodle_url('/local/paymentunlockassign/student.php', [
            'assignid' => $cmid,
            'courseid' => $courseid,
        ]));
    }

    /**
     * Injects the "Reopen Assignment" button into the assignment view page.
     *
     * Called by core\hook\output\before_footer_html_generation.
     *
     * FIX-PUA-HIDEPASSING (v1.1.0): Button is suppressed for students who have
     * a passing grade when hidepassing=1 is set for the assignment.
     *
     * @param \core\hook\output\before_footer_html_generation $hook
     */
    public static function before_footer(\core\hook\output\before_footer_html_generation $hook): void {
        global $PAGE, $USER, $DB, $CFG;

        // Ensure plugin lib functions are available — the hook manager does not
        // auto-include lib.php before dispatching.
        require_once($CFG->dirroot . '/local/paymentunlockassign/lib.php');

        // Only run on the assignment view page.
        if ($PAGE->pagetype !== 'mod-assign-view') {
            return;
        }

        if (!isloggedin() || isguestuser()) {
            return;
        }

        // Bail out immediately if the plugin is globally disabled.
        if (!\local_paymentunlockassign_is_enabled()) {
            return;
        }

        $cm = $PAGE->cm;
        if (!$cm || $cm->modname !== 'assign') {
            return;
        }

        // Students only — teachers and graders must not see the payment notice.
        $context = \context_module::instance($cm->id);
        if (has_capability('mod/assign:grade', $context)) {
            return;
        }

        // Plugin must be enabled for this specific assignment.
        $settings = $DB->get_record('local_payunlock_settings', [
            'assignid' => $cm->id,
            'enabled'  => 1,
        ]);
        if (!$settings) {
            return;
        }

        // Plugin must be credit-unlocked on this site (result is cached per-request).
        if (!local_paymentunlockassign_check_unlock()) {
            return;
        }

        // Load the assignment record.
        $assign_record = $DB->get_record('assign', ['id' => $cm->instance]);
        if (!$assign_record) {
            return;
        }

        // FIX-PUA-OVERRIDES (v1.2.0): Resolve this student's effective dates.
        $dates = local_paymentunlockassign_get_effective_dates($assign_record, $USER->id, $cm->course);

        // Get or create the student's access record.
        $access = local_paymentunlockassign_get_or_create_access($USER->id, $cm->id);

        // Derive the effective lock date and current status.
        $lockdate = local_paymentunlockassign_get_lockdate($dates, $settings);
        $now      = time();

        // FIX-PUA-HIDEPASSING (v1.1.0): Use should_show_paywall() which checks both
        // the locked status and whether the student has a passing grade.
        if (!local_paymentunlockassign_should_show_paywall($access, $settings, $lockdate, $USER->id, $cm->id, $now)) {
            return;
        }

        // Determine next attempt number and fee.
        $next_attempt = (int)$access->currentattempt + 1;
        $fee      = ($next_attempt === 1) ? (float)$settings->attempt1fee : (float)$settings->attempt2fee;
        $currency = !empty($settings->currency) ? $settings->currency : 'AUD';

        // Check third-attempt policy.
        $blocked     = false;
        $blocked_msg = '';
        if ($next_attempt >= 3) {
            if ($settings->attempt3mode === 'blocked') {
                $blocked     = true;
                $blocked_msg = get_string('attempt3_blocked_msg', 'local_paymentunlockassign');
            } elseif ($settings->attempt3mode === 'approval' && !$access->approvalgranted) {
                $blocked     = true;
                $blocked_msg = get_string('attempt3_approval_msg', 'local_paymentunlockassign');
            }
        }

        // Build the student payment page URL.
        $student_url = (new \moodle_url('/local/paymentunlockassign/student.php', [
            'assignid' => $cm->id,
            'courseid' => $cm->course,
        ]))->out(false);

        // Build the notice HTML.
        if ($blocked) {
            // Third-attempt blocked or awaiting approval — show info notice only.
            $icon = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"'
                  . ' fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-right:10px;">'
                  . '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/>'
                  . '<line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
            $html = '<div class="payunlock-assign-notice alert alert-warning" role="alert"'
                  . ' style="display:flex;align-items:center;gap:10px;margin-bottom:1rem;">'
                  . $icon
                  . '<span><strong>' . get_string('status_locked', 'local_paymentunlockassign') . '</strong>'
                  . ' &mdash; ' . htmlspecialchars($blocked_msg) . '</span>'
                  . '</div>';
        } else {
            // Show the "Reopen Assignment" button with the fee.
            $fee_str  = htmlspecialchars($currency) . ' $' . number_format($fee, 2);
            $btn_text = htmlspecialchars(get_string('reopen_assignment', 'local_paymentunlockassign'))
                      . ' &mdash; ' . $fee_str;
            $notice   = htmlspecialchars(get_string('assignment_locked_notice', 'local_paymentunlockassign'));
            $lock_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"'
                  . ' fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;">'
                  . '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>'
                  . '<path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>';
            $html = '<div class="payunlock-assign-notice alert alert-warning" role="alert"'
                  . ' style="display:flex;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:1rem;">'
                  . '<span style="display:flex;align-items:center;gap:8px;flex:1;min-width:200px;">'
                  . $lock_icon . '<span>' . $notice . '</span></span>'
                  . '<a href="' . $student_url . '" class="btn btn-warning payunlock-reopen-btn"'
                  . ' style="white-space:nowrap;">' . $btn_text . '</a>'
                  . '</div>';
        }

        // Inject via JS so the notice appears before the submission status table
        // (more prominent than footer injection). Guards against double-insertion.
        // Also hides the native Moodle "Add Submission" button so the student cannot
        // bypass the lock by clicking it directly — BUG-LOCK-INSTANT fix layer 3.
        $encoded = json_encode($html);
        $js = <<<JS
require([], function() {
    function hideAddSubmissionButtons() {
        // Hide all native Moodle "Add submission" / "Edit submission" buttons.
        // Covers Moodle 4.0-4.5 theme variants (Boost, Classic, child themes).
        var selectors = [
            'a[href*="action=editsubmission"]',
            'button[data-action="add-submission"]',
            '.submissionlinks a.btn',
            '.submissionlinks button',
            'form[action*="editsubmission"] button[type="submit"]',
        ];
        selectors.forEach(function(sel) {
            document.querySelectorAll(sel).forEach(function(el) {
                el.style.display = 'none';
            });
        });
    }

    function injectPayUnlockNotice() {
        if (document.querySelector(".payunlock-assign-notice")) { return; }
        var wrap = document.createElement("div");
        wrap.innerHTML = {$encoded};
        var anchor = document.querySelector(".submissionstatustable") ||
                     document.querySelector(".grading-summary-container") ||
                     document.querySelector("[data-region='assignment-submission-status']") ||
                     document.querySelector("#assign-intro") ||
                     document.querySelector("#region-main .box");
        if (anchor) {
            anchor.parentNode.insertBefore(wrap, anchor);
        } else {
            var main = document.getElementById("region-main");
            if (main) { main.insertBefore(wrap, main.firstChild); }
        }
        hideAddSubmissionButtons();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", injectPayUnlockNotice);
    } else {
        injectPayUnlockNotice();
    }
});
JS;
        $PAGE->requires->js_init_code($js, false);
    }
}
