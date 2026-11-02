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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * English language strings for local_paymentunlockassign.
 *
 * @package    local_paymentunlockassign
 * @copyright  2026 LMS Labs
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['actions'] = 'Actions';
$string['already_reopened'] = 'Your assignment is currently unlocked and active until {$a}. You do not need to pay again.';
$string['already_submitted'] = 'You have already submitted this assessment.';
$string['amount'] = 'Amount';
$string['approval_granted'] = 'Approval';
$string['assignment'] = 'Assignment';
$string['assignment_locked_notice'] = 'This assignment is locked. Pay to reopen it and submit your work.';
$string['assignment_status'] = 'Assignment Status';
$string['attempt1_fee'] = 'Attempt 1 Fee';
$string['attempt2_fee'] = 'Attempt 2 Fee';
$string['attempt3_approval'] = 'Requires admin approval before payment';
$string['attempt3_approval_msg'] = 'Your next attempt requires admin approval. Please contact your teacher.';
$string['attempt3_blocked'] = 'Blocked (no further attempts)';
$string['attempt3_blocked_msg'] = 'Further attempts are blocked. Please contact your teacher.';
$string['attempt_label'] = 'Attempt {$a}';
$string['attempt_number'] = 'Attempt';
$string['configure_assignment'] = 'Configure Assignment';
$string['course'] = 'Course';
$string['currency'] = 'Currency';
$string['current_attempt'] = 'Attempt #';
$string['data'] = 'Data';
$string['edit'] = 'Edit';
$string['enabled'] = 'Enabled';
$string['event'] = 'Event';
$string['extra_days'] = 'Extra Days';
$string['fee_amount'] = 'Fee';
$string['fee_label'] = 'Fee: {$a}';
$string['fee_rules_heading'] = 'Late Submission Fees';
$string['hide_passing'] = 'Hide button for students who have passed';
$string['hide_passing_desc'] = 'When ticked, the "Reopen Assignment" payment button will not be shown to students who have already achieved a passing grade on this assignment. Recommended: keep this ticked to avoid confusing students who have already passed.';
$string['invoice'] = 'Invoice';
$string['lock_date'] = 'Lock Date';
$string['lock_date_custom'] = 'Custom lock date override';
$string['lock_date_custom_desc'] = 'Set a specific date to lock the assignment. When set, this overrides the Lock Date Source above. Leave empty to use the Lock Date Source setting instead.';
$string['lock_date_source'] = 'Lock Date Source';
$string['lock_date_source_cutoffdate'] = 'Cut-off date (recommended)';
$string['lock_date_source_desc'] = 'Which assignment date triggers the lock when no custom lock date is set. Cut-off date is the hard deadline in Moodle; due date is the soft deadline. Falls back to due date if the assignment has no cut-off configured.';
$string['lock_date_source_duedate'] = 'Due date';
$string['manage'] = 'Manage Payment Unlock';
$string['manage_desc'] = 'Configure per-assignment payment unlock settings, view student access, payments, and overrides.';
$string['no_payment_history'] = 'No previous payments.';
$string['noaccess'] = 'No student access records found.';
$string['noassignments'] = 'No assignments found in any course. Create an assignment in Moodle first.';
$string['noaudit'] = 'No audit events yet.';
$string['nopayments'] = 'No successful payments yet.';
$string['not_configured'] = 'Payment unlock is not configured for this assignment.';
$string['override'] = 'Override';
$string['override_approve'] = 'Approve Attempt';
$string['override_extend'] = 'Extend Expiry';
$string['override_lock'] = 'Lock Immediately';
$string['override_reason'] = 'Reason';
$string['override_reopen'] = 'Reopen (Waive Fee)';
$string['override_saved'] = 'Override applied successfully.';
$string['paid_at'] = 'Paid At';
$string['pay_now'] = 'Pay & Unlock Now';
$string['payment_cancelled'] = 'Payment was cancelled. No charge was made.';
$string['payment_history'] = 'Payment History';
$string['payment_required'] = 'Payment Required to Reopen';
$string['payment_success'] = 'Payment successful! Your assignment has been unlocked. You can now submit your work.';
$string['plugin_globally_disabled'] = 'Payment unlock is currently disabled by the site administrator.';
$string['plugin_not_unlocked'] = 'This plugin requires an active AI Grader subscription. Please contact your administrator.';
$string['plugindesc'] = 'Payment Unlock Assignment allows RTOs and training organisations to charge students a fee to reopen a closed or past-due assignment submission window — for example, when a student submits late and needs to pay a resubmission fee.

Teachers configure a per-assignment fee schedule (Attempt 1 fee and Attempt 2 fee), a reopen duration in days, and the currency. When a student\'s submission window has closed, they see a payment button on the assignment page. Clicking it initiates a Stripe checkout session; on successful payment, the assignment is automatically reopened for the configured number of days and the student can submit immediately.

Stripe webhook integration confirms payments in real time so reopening is instant. Administrators configure the Stripe secret key, webhook endpoint secret, default fees, currency, and reopen duration at the site level, with per-assignment overrides available. The plugin reads AI Grader API credentials from Central Config if installed.';
$string['pluginname'] = 'Payment Unlock Assignment';
$string['privacy:metadata:local_payunlock_access'] = 'Stores student assignment access status and attempt information.';
$string['privacy:metadata:local_payunlock_audit'] = 'Stores audit log events related to student assignment access.';
$string['privacy:metadata:local_payunlock_payments'] = 'Stores payment records for late submission unlock fees.';
$string['reopen_assignment'] = 'Reopen Assignment';
$string['reopen_days'] = 'Reopen Days';
$string['reopen_expires'] = 'Your access expires: {$a}';
$string['return_to_assignment'] = 'Return to Assignment';
$string['save'] = 'Save Settings';
$string['saved'] = 'Settings saved.';
$string['settings_apikey'] = 'AI Grader API Key';
$string['settings_apikey_desc'] = 'Your AI Grader API Key (used if Central Config is not installed).';
$string['settings_attempt1_fee'] = 'Default Attempt 1 Fee';
$string['settings_attempt1_fee_desc'] = 'Default fee in dollars for the first late-submission reopen attempt.';
$string['settings_attempt2_fee'] = 'Default Attempt 2 Fee';
$string['settings_attempt2_fee_desc'] = 'Default fee in dollars for the second late-submission reopen attempt.';
$string['settings_attempt3_mode'] = 'Attempt 3 Policy';
$string['settings_attempt3_mode_desc'] = 'What happens on a third late-submission attempt.';
$string['settings_currency'] = 'Default Currency';
$string['settings_currency_desc'] = 'Default currency for all payments (e.g. AUD, USD, NZD).';
$string['settings_enabled'] = 'Enable Payment Unlock (Global)';
$string['settings_enabled_desc'] = 'When ticked, the payment unlock feature is active across all assignments where it has been enabled in the Manage page. <strong>Untick to instantly pause the entire plugin site-wide</strong> — no payment prompts will appear to students, and all assignments will behave as standard Moodle™ assignments. Individual assignment settings are preserved and will resume when you re-enable this toggle.';
$string['settings_heading'] = 'Payment Unlock Assignment Settings';
$string['settings_notify_email'] = 'Notification From Email';
$string['settings_notify_email_desc'] = 'Email address used as the sender for student reminder emails.';
$string['settings_reopen_days'] = 'Default Reopen Duration (Days)';
$string['settings_reopen_days_desc'] = 'How many days the assignment is reopened after a successful payment.';
$string['settings_siteid'] = 'AI Grader Site ID';
$string['settings_siteid_desc'] = 'Your AI Grader Site ID (used if Central Config is not installed).';
$string['settings_stripe_secret'] = 'Stripe Secret Key';
$string['settings_stripe_secret_desc'] = 'Your Stripe secret key (sk_live_... or sk_test_...).';
$string['settings_stripe_webhook_secret'] = 'Stripe Webhook Secret';
$string['settings_stripe_webhook_secret_desc'] = 'Stripe webhook endpoint signing secret (whsec_...). Create a webhook in your Stripe dashboard pointing to: {$a}';
$string['status'] = 'Status';
$string['status_available'] = 'Open for submission';
$string['status_locked'] = 'Locked';
$string['status_reopened'] = 'Unlocked (active)';
$string['status_submitted'] = 'Submitted';
$string['stripe_session'] = 'Stripe Session';
$string['student'] = 'Student';
$string['student_page_title'] = 'Assignment Late Submission Unlock';
$string['tab_access'] = 'Student Access';
$string['tab_assignments'] = 'Assignments';
$string['tab_audit'] = 'Audit Log';
$string['tab_payments'] = 'Payments';
$string['time'] = 'Time';
$string['unlock_expiry'] = 'Unlock Expiry';
$string['unlock_not_active'] = 'Payment Unlock is not active on this site. Please contact your administrator.';
