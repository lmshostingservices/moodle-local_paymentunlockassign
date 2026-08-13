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

/**
 * Payment Unlock Assignment - version info
 *
 * v1.2.2: FIX-PUA-GROUPGUARD — extracted local_paymentunlockassign_has_group_overrides()
 *           as a named function (was inlined in ensure_free_window_override()). Now also
 *           emits an audit log entry when the free window override is skipped.
 *           FIX-PUA-NOSHORTEN — extend_moodle_assignment() now resolves the student's
 *           effective dates before writing the override and takes the max of each field.
 *           A paying student can no longer end up with a shorter deadline than their
 *           cohort if $newduedate + DAYSECS is earlier than their existing group cut-off.
 *           §3 (override-tracking table for remove_moodle_override) — explicitly deferred
 *           to v1.3. The indiscriminate delete remains in place; RTOs should not use
 *           "Lock immediately" on assignments that also have teacher-created overrides.
 *           No DB schema changes.
 *
 * v1.2.1: FIX-PUA-OVERRIDES (student.php missed) — student.php:70 was calling
 *           get_lockdate($assign_record) instead of get_lockdate($dates). The two
 *           types are duck-type identical so PHP silently passed — but on a
 *           group-override assignment (base dates = 0) student.php derived 'locked'
 *           while both hooks derived 'available', splitting the plugin's behaviour.
 *           A student redirected to student.php would be stuck in a payment loop.
 *           Fixed: get_effective_dates() resolver now called in student.php before
 *           get_lockdate(), matching the two hook call sites.
 *           FIX-PUA-GROUPCLOBBER — ensure_free_window_override() now checks for
 *           existing group overrides before writing a user override. A user override
 *           permanently outranks group overrides in Moodle. On BSBLDR811-style
 *           assignments (deadlines managed per-group), any user override the plugin
 *           created would pin the student to the group cut-off at time of creation —
 *           if the RTO later extends the group deadline, the stale user override
 *           keeps the student locked out silently. Guard: if ANY group override
 *           exists for the assignment, skip user-override creation entirely.
 *           No DB schema changes.
 *
 * v1.2.0: FIX-PUA-OVERRIDES — Plugin now resolves each student's EFFECTIVE
 *           due/cut-off dates before making any lock decision, honouring Moodle's
 *           override precedence: user override > group override (lowest sortorder)
 *           > base assignment. Previously only the base {assign} row was read, so
 *           any assignment whose deadlines are managed via group overrides (standard
 *           RTO pattern) was evaluated against the wrong dates — on BSBLDR811-style
 *           assignments where base dates are zero, every student was permanently
 *           locked the moment the plugin was enabled. New function:
 *           local_paymentunlockassign_get_effective_dates($assign_record, $userid,
 *           $courseid). get_lockdate() now takes the resolved $dates object. Both
 *           hook call sites (before_http_headers and before_footer) updated.
 *           FIX-PUA-FREEWINDOW override creation now also uses effective cut-off
 *           date so group-deadline students get the correct window.
 *           CONSISTENCY FIX — before_http_headers now calls check_unlock() before
 *           redirecting locked students, matching before_footer behaviour. If the
 *           licensing server is unreachable, students are no longer redirected to
 *           a payment page where the Reopen button will never render.
 *           DROPPED: FIX-PUA-OVERRIDE-TIMING redirect-after-insert (v1.1.3).
 *           before_http_headers fires before mod_assign builds renderer state so
 *           the insert is always visible in the same request — the redirect was
 *           based on a misdiagnosis and risked an infinite redirect loop.
 *           No DB schema changes.
 *
 * v1.1.3: FIX-PUA-NODATES — derive_status() returned 'locked' when lockdate=0
 *           (no dates configured on the assignment) because (0 > time()) is always
 *           false. Any assignment with plugin enabled but no due/cutoff date set
 *           was locking every student. Fix: if empty($lockdate), return 'available'.
 *           No DB schema changes.
 *
 * v1.1.2: FIX-PUA-LOCKDATE-OVERRIDE — manage.php was pre-filling the custom lock
 *           date picker with the assignment's cut-off/due date even when no custom
 *           date was stored. Every save wrote a non-zero lockdate, permanently
 *           overriding the lockdatesource dropdown (Priority 1 in get_lockdate()
 *           fires on any non-zero value). Fixed: date picker now shows empty when
 *           no custom date is stored; JS writes 0 to the hidden field when left
 *           empty so get_lockdate() falls through to lockdatesource. Also cleaned
 *           up the duplicate lockdate_raw hidden field. No DB schema changes.
 *
 * v1.1.1: FIX-PUA-FREEWINDOW — before_http_headers hook auto-creates a personal
 *           assign_overrides row extending the student's due date to the cut-off
 *           date when status='available' and now > duedate < cutoffdate. This
 *           ensures Moodle shows "Add submission" during the free window even when
 *           "Prevent late submissions" is on. No DB schema changes.
 *
 * v1.1.0: FIX-PUA-CUTOFF — Lock trigger changed from assignment due date
 *           to cut-off date by default. RTOs use due date as a soft deadline
 *           and cut-off date as the hard deadline; triggering the payment
 *           prompt on the soft deadline was unexpected. New per-assignment
 *           setting 'lockdatesource' lets admins choose 'Cut-off date'
 *           (default) or 'Due date'. Existing custom lockdate overrides are
 *           unaffected. Falls back to due date when assignment has no
 *           cut-off configured.
 *           FIX-PUA-HIDEPASSING — The "Reopen Assignment" button is now
 *           suppressed for students who have already achieved a passing
 *           grade on the assignment. New per-assignment 'hidepassing'
 *           setting (default: enabled). Prevents confused payment prompts
 *           for students who passed but whose access record is still in the
 *           'locked' state. Also suppresses the before_http_headers redirect
 *           for passing students. Uses Moodle grade_grades + grade_items to
 *           compare finalgrade against gradepass threshold.
 *           DB: two new columns on local_payunlock_settings —
 *           lockdatesource CHAR(20) DEFAULT 'cutoffdate' and
 *           hidepassing INT(1) DEFAULT 1.
 *
 * v1.0.22: FIX-LISTENER-LIBPHP — Added require_once(lib.php) at the top of
 *           before_http_headers() in listener.php. The hook fires before Moodle's
 *           callback scanner has auto-loaded lib.php, so local_paymentunlockassign_is_enabled()
 *           was undefined at the point of call. No DB schema changes.
 *
 * v1.0.21: FIX-FILELIB-SCOPE — Moved require_once($CFG->libdir.'/filelib.php') from
 *           global scope in lib.php INTO the two functions that actually use \curl
 *           (check_unlock, create_stripe_session). Having it at global scope caused Moodle to
 *           load filelib.php on every single page request during callback scanning, which
 *           crashed the site after plugin install. No DB schema changes.
 *
 * v1.0.20: FEATURE — Added global enable/disable toggle (settings_enabled configcheckbox).
 *           Admins can instantly pause the entire plugin site-wide. Checked in both hook
 *           listeners and student.php. No DB schema changes.
 *
 * v1.0.17: VERSION BUMP — No functional changes. Increments the version number so
 *           sites already on v1.0.16 can confirm they have the latest release.
 *
 * v1.0.16: BUG-LOCK-INSTANT (3 layers) — Teacher "Lock Immediately" now fully hides
 *           the Moodle "Add Submission" button for students in real-time.
 *           Layer 1: derive_status() now checks $access->status === 'locked' BEFORE
 *           the lockdate comparison, so a teacher-forced lock wins even when the
 *           assignment's own lockdate is still in the future.
 *           Layer 2: manage.php 'lock' case now calls
 *           local_paymentunlockassign_remove_moodle_override() to delete the
 *           student's assign_overrides record that was created when they paid to
 *           reopen — previously this override kept a future duedate active, so
 *           Moodle's own UI continued showing "Add Submission".
 *           Layer 3: before_footer JS injection now also calls
 *           hideAddSubmissionButtons() which sets display:none on all known
 *           "Add submission" / "Edit submission" button selectors across Moodle
 *           4.0–4.5 theme variants.
 *
 * @package    local_paymentunlockassign
 * @copyright  2026 EssayGraderAI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_paymentunlockassign';
$plugin->version   = 2026072300;
$plugin->release   = '1.2.6';
$plugin->requires  = 2022041900;
$plugin->supported = [400, 500];
$plugin->maturity  = MATURITY_STABLE;
