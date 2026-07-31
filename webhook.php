<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Webhook handler for Stripe payment events.
// This endpoint is called server-to-server by Stripe and must NOT require login.
// URL: /local/paymentunlockassign/webhook.php
// Configure this URL in your Stripe Dashboard > Developers > Webhooks.
// Required events: checkout.session.completed

define('NO_DEBUG_DISPLAY', true);
define('AJAX_SCRIPT', true);
define('NO_MOODLE_COOKIES', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

// Read raw body BEFORE any Moodle processing can consume it.
$payload    = file_get_contents('php://input');
$sig_header = isset($_SERVER['HTTP_STRIPE_SIGNATURE']) ? $_SERVER['HTTP_STRIPE_SIGNATURE'] : '';

header('Content-Type: application/json');

// Verify webhook secret is configured. This is also the indicator the plugin is
// actively set up — we deliberately do NOT make a remote API call here because
// an outage on lms-labs.com would cause all Stripe webhooks to return 403,
// prevent payment unlocks from being granted, and cause Stripe to stop retrying.
// The HMAC signature check below is the authoritative authentication step.
$webhook_secret = get_config('local_paymentunlockassign', 'stripe_webhook_secret');
if (empty($webhook_secret)) {
    http_response_code(400);
    echo json_encode(['error' => 'Webhook secret not configured']);
    exit;
}

if (!local_paymentunlockassign_verify_stripe_signature($payload, $sig_header, $webhook_secret)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

$event = json_decode($payload, true);
if (!$event || empty($event['type'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Only handle checkout.session.completed.
if ($event['type'] !== 'checkout.session.completed') {
    http_response_code(200);
    echo json_encode(['received' => true]);
    exit;
}

$session = $event['data']['object'];
$studentid      = isset($session['metadata']['studentid'])      ? (int)$session['metadata']['studentid']      : 0;
$assignid       = isset($session['metadata']['assignid'])       ? (int)$session['metadata']['assignid']       : 0;
$courseid       = isset($session['metadata']['courseid'])       ? (int)$session['metadata']['courseid']       : 0;
$attempt_number = isset($session['metadata']['attempt_number']) ? (int)$session['metadata']['attempt_number'] : 1;
$stripe_session_id = $session['id'] ?? '';

if (!$studentid || !$assignid || !$stripe_session_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing metadata']);
    exit;
}

// Find the pending payment record.
$payment = $DB->get_record('local_payunlock_payments', ['stripesessionid' => $stripe_session_id]);
if (!$payment) {
    http_response_code(200);
    echo json_encode(['warning' => 'Payment record not found, ignoring']);
    exit;
}

if ($payment->status === 'success') {
    http_response_code(200);
    echo json_encode(['received' => true, 'note' => 'Already processed']);
    exit;
}

// Mark payment as success.
$payment->status             = 'success';
$payment->stripepaymentintentid = $session['payment_intent'] ?? '';
$payment->receipturl         = $session['url'] ?? null;
$payment->invoicereference   = 'INV-' . date('Ymd') . '-' . str_pad((string)$payment->id, 6, '0', STR_PAD_LEFT);
$payment->timepaid           = time();
$DB->update_record('local_payunlock_payments', $payment);

// Get assignment settings for reopen_days.
$settings = $DB->get_record('local_payunlock_settings', ['assignid' => $assignid]);
$reopen_days = $settings ? (int)$settings->reopendays : (int)(get_config('local_paymentunlockassign', 'reopen_days') ?: 7);

$unlock_expiry = time() + ($reopen_days * DAYSECS);

// Update or create student access record.
$access = local_paymentunlockassign_get_or_create_access($studentid, $assignid);
$access->status          = 'reopened';
$access->currentattempt  = $attempt_number;
$access->unlockexpiry    = $unlock_expiry;
$access->approvalgranted = 0;
$access->timemodified    = time();
$DB->update_record('local_payunlock_access', $access);

// Extend Moodle assignment due date for this student.
local_paymentunlockassign_extend_moodle_assignment($assignid, $studentid, $unlock_expiry);

// Log audit event.
local_paymentunlockassign_log_event(
    'PAYMENT_UNLOCK_GRANTED',
    [
        'attempt_number'   => $attempt_number,
        'amount'           => $payment->amount,
        'currency'         => $payment->currency,
        'stripe_session'   => $stripe_session_id,
        'unlock_expiry'    => $unlock_expiry,
        'reopen_days'      => $reopen_days,
    ],
    $studentid,
    $assignid,
    $studentid
);

http_response_code(200);
echo json_encode(['received' => true, 'unlocked' => true]);
exit;
