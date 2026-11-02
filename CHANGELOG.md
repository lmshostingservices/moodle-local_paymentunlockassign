# Changelog

All notable changes to Payment Unlock Assignment (`local_paymentunlockassign`) are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [v1.2.8] - 2026-11-02

### Fixed
- **Custom lock date now saves.** On Moodle 4.4 and later, the date picker on the assignment settings form was attached to Moodle's Edit mode switch instead of the settings form, so the date was always saved as empty.
- **Add submission is now hidden for locked students.** Moodle renders this button as a form that carries the action in a hidden field, and the old selectors never matched it. A locked student who clicked it was already redirected to the payment page, but the button stayed visible.

### Security
- The student payment page checks the `mod/assign:submit` capability as well as requiring login.

### Changed
- Includes the full GPL v3 licence text, a complete README and a complete changelog.
- New version number: a v1.2.7 package with different contents had already been submitted for release.

## [v1.2.7] - 2026-10-15

Go-live safety release. No database schema changes.

### Fixed
- **Free-window overrides removed.** The plugin no longer writes a personal Moodle override for every student who opens an enabled assignment between the due date and the cut-off date. Moodle already allows submission in that window. The overrides removed the late flag, stayed in place after the plugin was disabled, and locked students out if a teacher later extended the cut-off.
- **Moodle extensions honoured.** Students with a teacher-granted extension (Grant extension) are no longer redirected to the payment page or have Add submission hidden.
- **Submitted students not charged.** Students whose latest attempt is submitted and awaiting marking no longer see the "Locked – pay" banner and cannot start a checkout.
- **Licence check no longer slows pages.** Cached site-wide for 12 hours (7-day grace if lms-labs.com is unreachable), 5-second timeout, only checked when a paywall is about to be shown, and no longer closes the user's session.
- **Admin Lock preserves teacher overrides.** Only the override the plugin wrote for a paid or admin reopen is removed.
- **Webhook** only unlocks when Stripe reports `payment_status = paid`. The checkout URL is no longer stored as a receipt URL.

### Changed
- Minimum Moodle version is now 4.4, which the output hooks in `db/hooks.php` require.

## [v1.2.6] - 2026-07-23

Includes all changes since v1.0.17. Interim builds v1.0.20 to v1.2.2 were not dated separately; their notes are in `version.php`.

### Added
- A global Enabled setting to pause the plugin site-wide (v1.0.20).
- Lock date source setting: the cut-off date (default) or the due date (v1.1.0).
- The Reopen button is hidden for students who already have a passing grade (v1.1.0).
- Personal override in the window between due date and cut-off (v1.1.1; removed again in v1.2.7).

### Fixed
- `filelib.php` is loaded only when needed, not on every page request (v1.0.21).
- `lib.php` is loaded before the HTTP-headers hook runs (v1.0.22).
- Saving settings no longer stores the assignment's own date as a custom lock date (v1.1.2).
- Assignments with no due date or cut-off date no longer lock every student (v1.1.3).
- Lock decisions use each student's effective dates, honouring user and group overrides (v1.2.0, v1.2.1).
- The editsubmission redirect checks the site licence first (v1.2.0).
- No user override is created on assignments that use group overrides (v1.2.1, v1.2.2).
- A paid reopen never shortens a student's existing deadline (v1.2.2).

## [v1.0.17] - 2026-03-20

### Changed
- Version bump. No functional changes.

## [v1.0.16] - 2026-03-20

### Fixed
- **Lock immediately.**
  - A teacher-set lock now takes priority over the assignment's lock date.
  - Locking removes the student's paid-reopen override.
  - The Add submission buttons are hidden for locked students.

## [v1.0.14] - 2026-03-18

### Fixed
- Moved to the Moodle 4.4+ `before_http_headers` hook. This removed a debugging warning.

## [v1.0.13] - 2026-03-18

### Fixed
- Locked students who open the submission form are redirected to the payment page. Teachers and graders are exempt.

## [v1.0.11] - 2026-03-17

### Added
- A Reopen Assignment banner and button on the assignment page for locked students. Students on a blocked or approval-only third attempt see an information notice instead.

## [v1.0.3] - 2026-03-12

### Fixed
- When the plugin is not active, the Manage page shows setup help instead of a dead-end error.

## [v1.0.2] - 2026-03-06

### Fixed
- Parse error in the privacy provider.
- The Stripe webhook no longer calls the AI Grader API.
- A 5-minute replay window on webhook signatures.
- GDPR export and delete.
- Invoice reference format.

## [v1.0.1] - 2026-03-01

### Fixed
- `install.xml` default value that stopped the tables from being created.

## [v1.0.0] - 2026-02-25

### Added
- Initial release.
