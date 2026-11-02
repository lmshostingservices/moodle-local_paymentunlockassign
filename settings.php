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
 * local_paymentunlockassign file.
 *
 * @package    local_paymentunlockassign
 * @copyright  2026 LMS-Labs
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_paymentunlockassign',
        get_string('pluginname', 'local_paymentunlockassign')
    );

    $ADMIN->add('localplugins', $settings);

    // Central Config note.
    $settings->add(new admin_setting_heading(
        'local_paymentunlockassign/heading',
        get_string('settings_heading', 'local_paymentunlockassign'),
        '<div class="alert alert-info">' .
        'If <strong>AI Grader Central Config</strong> (<code>local_aiconfig</code>) is installed, the Site ID and API Key ' .
        'from Central Config will be used automatically. The fields below are only required if Central Config is not installed.' .
        '</div>'
    ));

    // Global enable / disable toggle — shown first so admins can quickly pause the plugin.
    $settings->add(new admin_setting_configcheckbox(
        'local_paymentunlockassign/enabled',
        get_string('settings_enabled', 'local_paymentunlockassign'),
        get_string('settings_enabled_desc', 'local_paymentunlockassign'),
        1  // Default: enabled.
    ));

    // Site ID (fallback from Central Config).
    $settings->add(new admin_setting_configtext(
        'local_paymentunlockassign/siteid',
        get_string('settings_siteid', 'local_paymentunlockassign'),
        get_string('settings_siteid_desc', 'local_paymentunlockassign'),
        '',
        PARAM_TEXT
    ));

    // API Key (fallback from Central Config).
    $settings->add(new admin_setting_configpasswordunmask(
        'local_paymentunlockassign/apikey',
        get_string('settings_apikey', 'local_paymentunlockassign'),
        get_string('settings_apikey_desc', 'local_paymentunlockassign'),
        ''
    ));

    $settings->add(new admin_setting_heading(
        'local_paymentunlockassign/stripe_heading',
        'Stripe Settings',
        'Enter your Stripe keys. These are used to process student payments for late submission reopens.'
    ));

    // Stripe Secret Key.
    $settings->add(new admin_setting_configpasswordunmask(
        'local_paymentunlockassign/stripe_secret_key',
        get_string('settings_stripe_secret', 'local_paymentunlockassign'),
        get_string('settings_stripe_secret_desc', 'local_paymentunlockassign'),
        ''
    ));

    // Stripe Webhook Secret.
    $webhookbase = (isset($CFG) && isset($CFG->wwwroot)) ? $CFG->wwwroot : '[your_moodle_url]';
    $webhookurl = $webhookbase . '/local/paymentunlockassign/webhook.php';
    $settings->add(new admin_setting_configpasswordunmask(
        'local_paymentunlockassign/stripe_webhook_secret',
        get_string('settings_stripe_webhook_secret', 'local_paymentunlockassign'),
        get_string('settings_stripe_webhook_secret_desc', 'local_paymentunlockassign', $webhookurl),
        ''
    ));

    $settings->add(new admin_setting_heading(
        'local_paymentunlockassign/defaults_heading',
        'Default Payment Settings',
        'These defaults apply to new assignments. You can override them per assignment in the Manage page.'
    ));

    // Currency.
    $settings->add(new admin_setting_configtext(
        'local_paymentunlockassign/currency',
        get_string('settings_currency', 'local_paymentunlockassign'),
        get_string('settings_currency_desc', 'local_paymentunlockassign'),
        'AUD',
        PARAM_TEXT
    ));

    // Default reopen duration.
    $settings->add(new admin_setting_configtext(
        'local_paymentunlockassign/reopen_days',
        get_string('settings_reopen_days', 'local_paymentunlockassign'),
        get_string('settings_reopen_days_desc', 'local_paymentunlockassign'),
        '7',
        PARAM_INT
    ));

    // Default Attempt 1 fee.
    $settings->add(new admin_setting_configtext(
        'local_paymentunlockassign/attempt1_fee',
        get_string('settings_attempt1_fee', 'local_paymentunlockassign'),
        get_string('settings_attempt1_fee_desc', 'local_paymentunlockassign'),
        '75',
        PARAM_FLOAT
    ));

    // Default Attempt 2 fee.
    $settings->add(new admin_setting_configtext(
        'local_paymentunlockassign/attempt2_fee',
        get_string('settings_attempt2_fee', 'local_paymentunlockassign'),
        get_string('settings_attempt2_fee_desc', 'local_paymentunlockassign'),
        '125',
        PARAM_FLOAT
    ));

    // Attempt 3 policy.
    $settings->add(new admin_setting_configselect(
        'local_paymentunlockassign/attempt3_mode',
        get_string('settings_attempt3_mode', 'local_paymentunlockassign'),
        get_string('settings_attempt3_mode_desc', 'local_paymentunlockassign'),
        'blocked',
        [
            'blocked'  => get_string('attempt3_blocked', 'local_paymentunlockassign'),
            'approval' => get_string('attempt3_approval', 'local_paymentunlockassign'),
        ]
    ));

    // Notification from email.
    $settings->add(new admin_setting_configtext(
        'local_paymentunlockassign/notify_email',
        get_string('settings_notify_email', 'local_paymentunlockassign'),
        get_string('settings_notify_email_desc', 'local_paymentunlockassign'),
        '',
        PARAM_EMAIL
    ));

    // Manage link.
    $settings->add(new admin_setting_heading(
        'local_paymentunlockassign/manage_link',
        '',
        '<a class="btn btn-primary" href="' . (new moodle_url('/local/paymentunlockassign/manage.php'))->out() . '">' .
        get_string('manage', 'local_paymentunlockassign') . '</a>'
    ));
}
