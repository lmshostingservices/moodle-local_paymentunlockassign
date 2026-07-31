# Changelog - Payment Unlock Assignment

All notable changes to this plugin will be documented in this file.

## [1.0.17] - 2026-03-20

### Changed
- **VERSION BUMP**: No functional changes. Increments the version number so sites already on v1.0.16 can confirm they have the latest release.

## [1.0.16] - 2026-03-20

### Fixed
- **BUG-LOCK-INSTANT (Layer 1 — derive_status)**: `derive_status()` previously returned `'available'` if the assignment's own lockdate was still in the future, even when a teacher had explicitly set `status='locked'` via "Lock Immediately". Added check `if ($access->status === 'locked') return 'locked';` before the lockdate comparison so teacher-forced locks always take priority.
- **BUG-LOCK-INSTANT (Layer 2 — Moodle override)**: `manage.php` `lock` case now calls `local_paymentunlockassign_remove_moodle_override()` which deletes the student's `assign_overrides` record. When a student paid to reopen, this override kept a future `duedate` in the Moodle table, causing Moodle's core UI to continue rendering the "Add Submission" button regardless of the plugin's locked status.
- **BUG-LOCK-INSTANT (Layer 3 — JS button hide)**: The `before_footer` JS injection now also calls `hideAddSubmissionButtons()` which sets `display:none` on all known "Add submission" / "Edit submission" button selectors across Moodle 4.0–4.5 theme variants (Boost, Classic, child themes), ensuring the button is hidden even if Moodle's markup is loaded before our notice is inserted.

## [1.0.14] - 2026-03-18

### Fixed
- **HOOK-MIGRATION**: Removed deprecated `local_paymentunlockassign_before_http_headers()` from lib.php (triggered Moodle 4.4+ debugging warning via `get_plugins_with_function()`). Migrated to `core\hook\output\before_http_headers` hook registered in `db/hooks.php` and implemented in `classes/hook/listener.php::before_http_headers()`. Behaviour is identical — locked students navigating to `action=editsubmission` are redirected to the payment page.

## [1.0.13] - 2026-03-18

### Fixed
- **BUG-LOCK-INSTANT**: Added  Moodle hook. When a locked student navigates to  they are immediately redirected to the payment page (student.php). Teachers and graders (mod/assign:grade capability) are exempt. Lock status is derived using the same  logic as the existing UI.


## [1.0.11] - 2026-03-17

### Added
- **Reopen Assignment button on the assignment submission page.** When a student's assignment is locked, a prominent banner now appears on the Moodle™ assignment view page (`/mod/assign/view.php`) above the submission status table. The banner includes:
  - A lock icon and a descriptive notice explaining the assignment is locked.
  - A **"Reopen Assignment — [Currency] $[Fee]"** button linking directly to the payment page.
  - For third-attempt blocked/approval cases: an info notice is shown instead of the payment button.
- Uses the Moodle™ 4.3+ hook system (`core\hook\output\before_footer_html_generation`) via `db/hooks.php` and `classes/hook/listener.php`.
- Button is shown to students only (teachers/graders with `mod/assign:grade` are excluded).

## [1.0.3] - 2026-03-12

### Fixed / Improved
- manage.php "not active" error is now an actionable help panel instead of a dead-end message. The panel:
  - Detects whether credentials (Site ID / API Key) are missing and links directly to plugin settings if so.
  - Explains that the plugin requires a one-time 1,000 AI Credits unlock.
  - Links to lms-labs.com/docs/payment-unlock-assignment where the Unlock Plugin button is found.
  - Shows a note if the activation server may be temporarily unreachable.

## [1.0.2] - 2026-03-06

### Fixed
- SECURITY: Fixed PHP parse error (double backslash in privacy provider) that caused a fatal error on all Moodle™ 4.x pages
- Stripe webhook no longer calls the AI Grader API during payment processing
- Added 5-minute Stripe timestamp replay-attack window to signature verification
- GDPR: export_user_data, delete_data_for_user, and delete_data_for_users now correctly export and purge records
- Invoice references now use INV-YYYYMMDD-000XXX format

## [1.0.1] - 2026-03-01

### Fixed
- Removed invalid DEFAULT="null" from XMLDB install.xml that prevented DB tables from being created

## [1.0.0] - 2026-02-25

### Added
- Initial release
