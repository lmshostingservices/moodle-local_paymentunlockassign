<?php
// This file is part of Moodle - https://moodle.org/
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
 * Upgrade steps for local_paymentunlockassign.
 *
 * @package    local_paymentunlockassign
 * @copyright  2026 LMS Labs
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Run the upgrade steps for local_paymentunlockassign.
 *
 * @param int $oldversion The version being upgraded from.
 * @return bool
 */
function xmldb_local_paymentunlockassign_upgrade($oldversion) {
    if ($oldversion < 2026072300) {
        upgrade_plugin_savepoint(true, 2026072300, 'local', 'paymentunlockassign');
    }
    if ($oldversion < 2026101500) {
        upgrade_plugin_savepoint(true, 2026101500, 'local', 'paymentunlockassign');
    }
    if ($oldversion < 2026110200) {
        upgrade_plugin_savepoint(true, 2026110200, 'local', 'paymentunlockassign');
    }
    return true;
}
