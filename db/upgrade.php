<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

function xmldb_local_paymentunlockassign_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026030600101) {
        // v1.0.0 had invalid DEFAULT="null" in install.xml which caused DB install to fail.
        // Create any tables that are missing due to the broken install.

        $tables = [
            'local_payunlock_settings' => function() {
                $table = new xmldb_table('local_payunlock_settings');
                $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
                $table->add_field('assignid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
                $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
                $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
                $table->add_field('lockdate', XMLDB_TYPE_INTEGER, '10', null, null);
                $table->add_field('attempt1fee', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '75.00');
                $table->add_field('attempt2fee', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '125.00');
                $table->add_field('attempt3mode', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'blocked');
                $table->add_field('reopendays', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '7');
                $table->add_field('currency', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'AUD');
                $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
                $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
                $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
                $table->add_key('assignid_unique', XMLDB_KEY_UNIQUE, ['assignid']);
                return $table;
            },
            'local_payunlock_access' => function() {
                $table = new xmldb_table('local_payunlock_access');
                $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
                $table->add_field('studentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
                $table->add_field('assignid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
                $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'available');
                $table->add_field('currentattempt', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
                $table->add_field('unlockexpiry', XMLDB_TYPE_INTEGER, '10', null, null);
                $table->add_field('approvalgranted', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
                $table->add_field('submittedat', XMLDB_TYPE_INTEGER, '10', null, null);
                $table->add_field('lastlockedat', XMLDB_TYPE_INTEGER, '10', null, null);
                $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
                $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
                $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
                $table->add_key('student_assign_unique', XMLDB_KEY_UNIQUE, ['studentid', 'assignid']);
                $table->add_index('studentid', XMLDB_INDEX_NOTUNIQUE, ['studentid']);
                $table->add_index('assignid', XMLDB_INDEX_NOTUNIQUE, ['assignid']);
                return $table;
            },
            'local_payunlock_payments' => function() {
                $table = new xmldb_table('local_payunlock_payments');
                $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
                $table->add_field('studentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
                $table->add_field('assignid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
                $table->add_field('attemptnumber', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '1');
                $table->add_field('amount', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0.00');
                $table->add_field('currency', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'AUD');
                $table->add_field('stripesessionid', XMLDB_TYPE_CHAR, '255', null, null);
                $table->add_field('stripepaymentintentid', XMLDB_TYPE_CHAR, '255', null, null);
                $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
                $table->add_field('receipturl', XMLDB_TYPE_TEXT, null, null, null);
                $table->add_field('invoicereference', XMLDB_TYPE_CHAR, '100', null, null);
                $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
                $table->add_field('timepaid', XMLDB_TYPE_INTEGER, '10', null, null);
                $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
                $table->add_index('studentid', XMLDB_INDEX_NOTUNIQUE, ['studentid']);
                $table->add_index('assignid', XMLDB_INDEX_NOTUNIQUE, ['assignid']);
                $table->add_index('stripesessionid', XMLDB_INDEX_NOTUNIQUE, ['stripesessionid']);
                return $table;
            },
            'local_payunlock_overrides' => function() {
                $table = new xmldb_table('local_payunlock_overrides');
                $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
                $table->add_field('studentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
                $table->add_field('assignid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
                $table->add_field('adminid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
                $table->add_field('overridetype', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL);
                $table->add_field('reason', XMLDB_TYPE_TEXT, null, null, null);
                $table->add_field('newexpiry', XMLDB_TYPE_INTEGER, '10', null, null);
                $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
                $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
                return $table;
            },
            'local_payunlock_audit' => function() {
                $table = new xmldb_table('local_payunlock_audit');
                $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
                $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null);
                $table->add_field('studentid', XMLDB_TYPE_INTEGER, '10', null, null);
                $table->add_field('assignid', XMLDB_TYPE_INTEGER, '10', null, null);
                $table->add_field('eventtype', XMLDB_TYPE_CHAR, '60', null, XMLDB_NOTNULL);
                $table->add_field('eventdata', XMLDB_TYPE_TEXT, null, null, null);
                $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
                $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
                $table->add_index('studentid', XMLDB_INDEX_NOTUNIQUE, ['studentid']);
                $table->add_index('assignid', XMLDB_INDEX_NOTUNIQUE, ['assignid']);
                return $table;
            },
        ];

        foreach ($tables as $tablename => $builder) {
            if (!$dbman->table_exists($tablename)) {
                $table = $builder();
                $dbman->create_table($table);
            }
        }

        upgrade_plugin_savepoint(true, 2026030600101, 'local', 'paymentunlockassign');
    }

    if ($oldversion < 2026030600102) {
        // v1.0.2: Security and GDPR hardening — no DB schema changes required.
        upgrade_plugin_savepoint(true, 2026030600102, 'local', 'paymentunlockassign');
    }

    if ($oldversion < 2026031600111) {
        // v1.0.3 – v1.0.11: UX improvements and "Reopen Assignment" button feature.
        // Hook-based injection via db/hooks.php + classes/hook/listener.php.
        // No DB schema changes required.
        upgrade_plugin_savepoint(true, 2026031600111, 'local', 'paymentunlockassign');
    }

    if ($oldversion < 2026031701112) {
        // v1.0.12: FIX BUG-PAY-WARN — Replaced inline <script> tag in manage.php
        //   with $PAGE->requires->js_init_code() to eliminate Moodle debug warnings.
        //   FIX BUG-PAY-REOPEN — 'Approve Attempt' override now mirrors 'Reopen (Waive
        //   Fee)': sets status='reopened', sets unlockexpiry, and calls
        //   local_paymentunlockassign_extend_moodle_assignment(). No DB schema changes.
        upgrade_plugin_savepoint(true, 2026031701112, 'local', 'paymentunlockassign');
    }

    if ($oldversion < 2026031701113) {
        // v1.0.13: FIX BUG-LOCK-INSTANT — Added before_http_headers() hook that
        //   redirects locked students away from mod/assign editsubmission action
        //   instantly without requiring a page refresh. No DB schema changes.
        upgrade_plugin_savepoint(true, 2026031701113, 'local', 'paymentunlockassign');
    }

    if ($oldversion < 2026031801114) {
        // v1.0.14: HOOK MIGRATION — Removed deprecated legacy lib.php callback
        //   local_paymentunlockassign_before_http_headers() (triggered Moodle 4.4+
        //   debugging warning). Migrated to core\hook\output\before_http_headers
        //   registered in db/hooks.php + classes/hook/listener.php. No DB schema changes.
        upgrade_plugin_savepoint(true, 2026031801114, 'local', 'paymentunlockassign');
    }

    if ($oldversion < 2026032001115) {
        // v1.0.15: VERSION BUMP — Adds the missing savepoints above (v1.0.12–v1.0.14)
        //   so Moodle's upgrade engine correctly stamps the DB version on sites that
        //   installed an intermediate release without a savepoint. No DB schema changes.
        upgrade_plugin_savepoint(true, 2026032001115, 'local', 'paymentunlockassign');
    }

    // v1.0.17: SYNC FIX — version.php was bumped to 2026032001117 (v1.0.16 and v1.0.17)
    //   without corresponding upgrade.php savepoints. Added retroactively to bring the
    //   DB version in sync with version.php. No DB schema changes.
    if ($oldversion < 2026032001117) {
        upgrade_plugin_savepoint(true, 2026032001117, 'local', 'paymentunlockassign');
    }

    // v1.0.18: FIX-CURL-BATCH — lib.php switched from raw curl_init() to Moodle \curl
    //   wrapper + write_close(). No DB schema changes.
    if ($oldversion < 2026051200118) {
        upgrade_plugin_savepoint(true, 2026051200118, 'local', 'paymentunlockassign');
    }

    // v1.0.19: FIX-FILELIB-REQUIRE — Added require_once($CFG->libdir.'/filelib.php') to
    //   lib.php so the \curl class is always available regardless of which Moodle page
    //   triggers lib.php. Fixes "Class curl not found" on manage.php. No DB schema changes.
    if ($oldversion < 2026052700119) {
        upgrade_plugin_savepoint(true, 2026052700119, 'local', 'paymentunlockassign');
    }

    // v1.0.20: FEATURE — Added global enable/disable toggle (settings_enabled configcheckbox).
    //   Admins can instantly pause the entire plugin site-wide. Checked in both hook
    //   listeners and student.php. No DB schema changes.
    if ($oldversion < 2026060500120) {
        upgrade_plugin_savepoint(true, 2026060500120, 'local', 'paymentunlockassign');
    }

    // v1.0.21: FIX-FILELIB-SCOPE — Moved require_once($CFG->libdir.'/filelib.php') from
    //   global scope in lib.php INTO the two functions that actually use \curl
    //   (check_unlock, create_stripe_session). Having it at global scope caused Moodle to
    //   load filelib.php on every single page request during callback scanning, which
    //   crashed the site after plugin install. No DB schema changes.
    if ($oldversion < 2026060500121) {
        upgrade_plugin_savepoint(true, 2026060500121, 'local', 'paymentunlockassign');
    }

    // v1.0.22: FIX-LISTENER-LIBPHP — Added require_once(lib.php) at the top of
    //   before_http_headers() in listener.php. The hook fires before Moodle's callback
    //   scanner has auto-loaded lib.php, so local_paymentunlockassign_is_enabled() was
    //   undefined at the point of call. No DB schema changes.
    if ($oldversion < 2026060500122) {
        upgrade_plugin_savepoint(true, 2026060500122, 'local', 'paymentunlockassign');
    }

    // v1.1.0: FIX-PUA-CUTOFF + FIX-PUA-HIDEPASSING
    //   1. Lock trigger changed from assignment due date to cut-off date by default.
    //      The "Reopen Assignment" button was appearing when the due date passed, but
    //      RTOs use the cut-off date as the hard deadline. New lockdatesource column
    //      lets admins choose 'cutoffdate' (default) or 'duedate' per assignment.
    //   2. Added hidepassing column (default 1). When enabled, the "Reopen Assignment"
    //      button is suppressed for students who have already achieved a passing grade.
    //      Prevents confusion for students who passed but whose assignment record is in
    //      'locked' state because they didn't submit through the plugin's reopen flow.
    //   DB changes: two new columns on local_payunlock_settings.
    if ($oldversion < 2026070700123) {
        $table = new xmldb_table('local_payunlock_settings');

        // lockdatesource: which assignment date triggers the lock ('cutoffdate' or 'duedate').
        $field_source = new xmldb_field('lockdatesource', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'cutoffdate');
        if (!$dbman->field_exists($table, $field_source)) {
            $dbman->add_field($table, $field_source);
        }

        // hidepassing: 1 = suppress Reopen button for students with a passing grade.
        $field_hide = new xmldb_field('hidepassing', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        if (!$dbman->field_exists($table, $field_hide)) {
            $dbman->add_field($table, $field_hide);
        }

        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['lib.php', 'version.php', 'db/upgrade.php', 'classes/hook/listener.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }

        upgrade_plugin_savepoint(true, 2026070700123, 'local', 'paymentunlockassign');
    }

    // v1.1.1: FIX-PUA-FREEWINDOW
    // Problem: Moodle's "Prevent late submissions" setting hides the "Add submission"
    // button after the due date, even when a cut-off date is set and the plugin
    // hasn't locked the student yet (status='available'). Students in the window
    // between due date and cut-off date could not submit without paying.
    // Fix: before_http_headers hook now detects this window and automatically creates
    // a personal assign_overrides row extending the student's due date to the cut-off
    // date, so Moodle shows "Add submission" during the free window. No DB schema changes.
    if ($oldversion < 2026071000001) {
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['lib.php', 'version.php', 'db/upgrade.php', 'classes/hook/listener.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_plugin_savepoint(true, 2026071000001, 'local', 'paymentunlockassign');
    }

    // v1.1.2: FIX-PUA-LOCKDATE-OVERRIDE — manage.php custom lock date picker was
    // pre-filled with the assignment's default dates even when no custom date was
    // stored. Every save silently wrote a non-zero lockdate, overriding the
    // lockdatesource dropdown. Fixed in PHP/JS only. No DB schema changes.
    if ($oldversion < 2026071000002) {
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['lib.php', 'version.php', 'manage.php', 'db/upgrade.php', 'classes/hook/listener.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_plugin_savepoint(true, 2026071000002, 'local', 'paymentunlockassign');
    }

    // v1.1.3: FIX-PUA-NODATES + FIX-PUA-OVERRIDE-TIMING
    //   1. derive_status() returned 'locked' when lockdate=0 (no dates configured on
    //      the assignment) because (0 > time()) is always false, locking every student
    //      on assignments with plugin enabled but no due/cutoff date set.
    //      Fix: if empty($lockdate), return 'available'.
    //   2. ensure_free_window_override() now returns 'new' for a fresh insert vs 'ok'
    //      for an existing override. before_http_headers hook redirects the student
    //      back to the same page when a brand-new override was just created, so Moodle
    //      reads the fresh override at the start of the next request rather than using
    //      state computed before the hook ran.
    //   No DB schema changes.
    if ($oldversion < 2026071100003) {
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['lib.php', 'version.php', 'db/upgrade.php', 'classes/hook/listener.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_plugin_savepoint(true, 2026071100003, 'local', 'paymentunlockassign');
    }

    if ($oldversion < 2026071100004) {
        // v1.2.0: FIX-PUA-OVERRIDES — resolved effective dates via get_effective_dates().
        //         get_lockdate() now accepts $dates object. Both hook call sites updated.
        //         CONSISTENCY FIX: before_http_headers now calls check_unlock().
        //         DROPPED: redirect-after-insert (misdiagnosis, infinite-loop risk).
        //         No DB schema changes.
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['lib.php', 'version.php', 'db/upgrade.php', 'classes/hook/listener.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_plugin_savepoint(true, 2026071100004, 'local', 'paymentunlockassign');
    }

    if ($oldversion < 2026071100005) {
        // v1.2.1: FIX-PUA-OVERRIDES student.php missed — get_effective_dates() now
        //         called in student.php before get_lockdate() to match the hooks.
        //         FIX-PUA-GROUPCLOBBER — ensure_free_window_override() now skips
        //         user-override creation on assignments that have group overrides.
        //         No DB schema changes.
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['lib.php', 'version.php', 'db/upgrade.php', 'classes/hook/listener.php', 'student.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_plugin_savepoint(true, 2026071100005, 'local', 'paymentunlockassign');
    }

    if ($oldversion < 2026071100006) {
        // v1.2.2: FIX-PUA-GROUPGUARD — local_paymentunlockassign_has_group_overrides()
        //         extracted as a named function (was inlined). Also emits an audit log
        //         entry when the free window override is skipped for a group-managed
        //         assignment.
        //         FIX-PUA-NOSHORTEN — extend_moodle_assignment() now resolves the
        //         student's effective dates before writing the override and takes the
        //         max of each field, so a paying student can never end up with a
        //         shorter deadline than their cohort.
        //         §3 (override-tracking table) deferred to v1.3. No DB schema changes.
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['lib.php', 'version.php', 'db/upgrade.php', 'webhook.php', 'manage.php', 'student.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_plugin_savepoint(true, 2026071100006, 'local', 'paymentunlockassign');
    }

    if ($oldversion < 2026072300215) {
        // FIX-API-DOMAIN: Updated all API endpoint URLs from lms-labs.com to lms-labs.com.
        // lms-labs.com has no DNS resolution from Moodle server side; lms-labs.com is the
        // correct working domain. All ajax.php, api_client, unlock_verifier, lib.php calls updated.
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_plugin_savepoint(true, 2026072300215, 'local', 'paymentunlockassign');
    }

    if ($oldversion < 2026072300216) {
        // FIX-API-DOMAIN: Reverted API endpoint to lms-labs.com (correct domain).
        // essaygraderai.app was the original single-plugin domain; lms-labs.com is correct.
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) { opcache_invalidate($_full, true); }
            }
        } elseif (function_exists('opcache_reset')) { opcache_reset(); }
        upgrade_plugin_savepoint(true, 2026072300216, 'local', 'paymentunlockassign');
    }

    if ($oldversion < 2026072300217) {
        // Domain update: lms-labs.com → lms-labs.com
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['version.php', 'lib.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) { opcache_invalidate($_full, true); }
            }
        } elseif (function_exists('opcache_reset')) { opcache_reset(); }
        upgrade_plugin_savepoint(true, 2026072300217, 'local', 'paymentunlockassign');
    }

    return true;
}