<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_paymentunlockassign\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_userlist;

defined('MOODLE_INTERNAL') || die();

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_payunlock_access',
            [
                'studentid'      => 'privacy:metadata:local_payunlock_access',
                'assignid'       => 'privacy:metadata:local_payunlock_access',
                'status'         => 'privacy:metadata:local_payunlock_access',
                'currentattempt' => 'privacy:metadata:local_payunlock_access',
                'unlockexpiry'   => 'privacy:metadata:local_payunlock_access',
            ],
            'privacy:metadata:local_payunlock_access'
        );

        $collection->add_database_table(
            'local_payunlock_payments',
            [
                'studentid'       => 'privacy:metadata:local_payunlock_payments',
                'assignid'        => 'privacy:metadata:local_payunlock_payments',
                'amount'          => 'privacy:metadata:local_payunlock_payments',
                'currency'        => 'privacy:metadata:local_payunlock_payments',
                'stripesessionid' => 'privacy:metadata:local_payunlock_payments',
                'status'          => 'privacy:metadata:local_payunlock_payments',
            ],
            'privacy:metadata:local_payunlock_payments'
        );

        $collection->add_database_table(
            'local_payunlock_audit',
            [
                'studentid'   => 'privacy:metadata:local_payunlock_audit',
                'assignid'    => 'privacy:metadata:local_payunlock_audit',
                'eventtype'   => 'privacy:metadata:local_payunlock_audit',
                'eventdata'   => 'privacy:metadata:local_payunlock_audit',
                'timecreated' => 'privacy:metadata:local_payunlock_audit',
            ],
            'privacy:metadata:local_payunlock_audit'
        );

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_system_context();
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }
        $userlist->add_from_sql('studentid', 'SELECT DISTINCT studentid FROM {local_payunlock_access}', []);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;

        $access = $DB->get_records('local_payunlock_access', ['studentid' => $userid]);
        if ($access) {
            \core_privacy\local\request\writer::with_context(\context_system::instance())
                ->export_data(['paymentunlockassign', 'access'], (object)['records' => array_values($access)]);
        }

        $payments = $DB->get_records('local_payunlock_payments', ['studentid' => $userid]);
        if ($payments) {
            \core_privacy\local\request\writer::with_context(\context_system::instance())
                ->export_data(['paymentunlockassign', 'payments'], (object)['records' => array_values($payments)]);
        }

        $audit = $DB->get_records('local_payunlock_audit', ['studentid' => $userid]);
        if ($audit) {
            \core_privacy\local\request\writer::with_context(\context_system::instance())
                ->export_data(['paymentunlockassign', 'audit'], (object)['records' => array_values($audit)]);
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if (!$context instanceof \context_system) {
            return;
        }
        $DB->delete_records('local_payunlock_access');
        $DB->delete_records('local_payunlock_payments');
        $DB->delete_records('local_payunlock_audit');
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        $DB->delete_records('local_payunlock_access',   ['studentid' => $userid]);
        $DB->delete_records('local_payunlock_payments', ['studentid' => $userid]);
        $DB->delete_records('local_payunlock_audit',    ['studentid' => $userid]);
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_payunlock_access',   "studentid $insql", $inparams);
        $DB->delete_records_select('local_payunlock_payments', "studentid $insql", $inparams);
        $DB->delete_records_select('local_payunlock_audit',    "studentid $insql", $inparams);
    }
}
