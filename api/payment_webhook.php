<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../admin/app/core/payment_providers.php';

$code = strtolower(trim((string)($_GET['method'] ?? '')));
$stmt = db()->prepare('SELECT * FROM payment_methods WHERE code = :code LIMIT 1');
$stmt->execute(['code' => $code]);
$method = $stmt->fetch();
if (!$method) json_response(['error' => 'unknown method'], 404);
$config = payment_method_config($method);
$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) $data = $_POST;
$eventId = '';
$providerPaymentId = '';
$valid = false;
$succeeded = false;

try {
    if ($code === 'stripe') {
        $signature = (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
        preg_match('/(?:^|,)t=(\d+)/', $signature, $timeMatch);
        preg_match('/(?:^|,)v1=([a-f0-9]+)/', $signature, $sigMatch);
        $expected = hash_hmac('sha256', ($timeMatch[1] ?? '') . '.' . $raw, (string)($config['webhook_secret'] ?? ''));
        $valid = !empty($timeMatch[1]) && abs(time() - (int)$timeMatch[1]) <= 300 && hash_equals($expected, (string)($sigMatch[1] ?? ''));
        $eventId = (string)($data['id'] ?? '');
        $object = $data['data']['object'] ?? [];
        $providerPaymentId = (string)($object['id'] ?? '');
        $succeeded = ($data['type'] ?? '') === 'checkout.session.completed' && ($object['payment_status'] ?? '') === 'paid';
    } elseif ($code === 'cloudpayments') {
        $signature = (string)($_SERVER['HTTP_CONTENT_HMAC'] ?? $_SERVER['HTTP_X_CONTENT_HMAC'] ?? '');
        $expected = base64_encode(hash_hmac('sha256', $raw, (string)($config['api_secret'] ?? ''), true));
        $valid = $signature !== '' && hash_equals($expected, $signature);
        $eventId = (string)($data['TransactionId'] ?? hash('sha256', $raw));
        $providerPaymentId = 'cp-' . (string)($data['AccountId'] ?? ($data['Data']['transaction_id'] ?? ''));
        $succeeded = (string)($data['Status'] ?? 'Completed') === 'Completed';
    } elseif ($code === 'yookassa') {
        $eventId = (string)($data['object']['id'] ?? '');
        $providerPaymentId = $eventId;
        if ($eventId && !empty($config['shop_id']) && !empty($config['secret_key'])) {
            $payment = payment_http('https://api.yookassa.ru/v3/payments/' . rawurlencode($eventId), 'GET', null, ['Authorization: Basic ' . base64_encode($config['shop_id'] . ':' . $config['secret_key'])]);
            $valid = ($payment['id'] ?? '') === $eventId;
            $succeeded = ($payment['status'] ?? '') === 'succeeded';
        }
    } elseif ($code === 'paypal') {
        $eventId = (string)($data['id'] ?? '');
        $providerPaymentId = (string)($data['resource']['supplementary_data']['related_ids']['order_id'] ?? '');
        $host = !empty($method['is_test']) ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
        if (!empty($config['client_id']) && !empty($config['client_secret']) && !empty($config['webhook_id'])) {
            $token = payment_http($host . '/v1/oauth2/token', 'POST', 'grant_type=client_credentials', ['Authorization: Basic ' . base64_encode($config['client_id'] . ':' . $config['client_secret']), 'Content-Type: application/x-www-form-urlencoded']);
            $verification = payment_http($host . '/v1/notifications/verify-webhook-signature', 'POST', json_encode([
                'auth_algo' => $_SERVER['HTTP_PAYPAL_AUTH_ALGO'] ?? '',
                'cert_url' => $_SERVER['HTTP_PAYPAL_CERT_URL'] ?? '',
                'transmission_id' => $_SERVER['HTTP_PAYPAL_TRANSMISSION_ID'] ?? '',
                'transmission_sig' => $_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG'] ?? '',
                'transmission_time' => $_SERVER['HTTP_PAYPAL_TRANSMISSION_TIME'] ?? '',
                'webhook_id' => $config['webhook_id'],
                'webhook_event' => $data,
            ], JSON_UNESCAPED_SLASHES), ['Authorization: Bearer ' . $token['access_token'], 'Content-Type: application/json']);
            $valid = ($verification['verification_status'] ?? '') === 'SUCCESS';
            $succeeded = ($data['event_type'] ?? '') === 'PAYMENT.CAPTURE.COMPLETED';
        }
    }
} catch (Throwable) {
    $valid = false;
}

if ($eventId === '') $eventId = hash('sha256', $raw);
$insert = db()->prepare('INSERT IGNORE INTO payment_webhook_events (payment_method_code, provider_event_id, signature_valid, payload) VALUES (:code, :event_id, :valid, :payload)');
$insert->execute(['code' => $code, 'event_id' => $eventId, 'valid' => $valid ? 1 : 0, 'payload' => $raw]);
if (!$valid) json_response(['error' => 'invalid webhook'], 400);

if ($succeeded && $providerPaymentId !== '') {
    $transactionStmt = db()->prepare('SELECT id, billing_invoice_id, amount FROM payment_transactions WHERE provider_payment_id = :provider_id LIMIT 1');
    $transactionStmt->execute(['provider_id' => $providerPaymentId]);
    $transaction = $transactionStmt->fetch();
    if ($transaction) {
        $eventAmount = null;
        $eventCurrency = null;
        if ($code === 'stripe') {
            $eventAmount = isset($object['amount_total']) ? (float)$object['amount_total'] / 100 : null;
            $eventCurrency = strtoupper((string)($object['currency'] ?? ''));
        } elseif ($code === 'cloudpayments') {
            $eventAmount = isset($data['Amount']) ? (float)$data['Amount'] : null;
            $eventCurrency = strtoupper((string)($data['Currency'] ?? 'RUB'));
        } elseif ($code === 'yookassa') {
            $eventAmount = isset($payment['amount']['value']) ? (float)$payment['amount']['value'] : null;
            $eventCurrency = strtoupper((string)($payment['amount']['currency'] ?? ''));
        } elseif ($code === 'paypal') {
            $eventAmount = isset($data['resource']['amount']['value']) ? (float)$data['resource']['amount']['value'] : null;
            $eventCurrency = strtoupper((string)($data['resource']['amount']['currency_code'] ?? ''));
        }
        if ($eventAmount !== null && $eventCurrency === 'RUB' && abs($eventAmount - (float)$transaction['amount']) < 0.01) {
            billing_complete_invoice((int)$transaction['billing_invoice_id'], (int)$transaction['id']);
        }
    }
}
$update = db()->prepare('UPDATE payment_webhook_events SET processed_at = NOW() WHERE payment_method_code = :code AND provider_event_id = :event_id');
$update->execute(['code' => $code, 'event_id' => $eventId]);
json_response($code === 'cloudpayments' ? ['code' => 0] : ['ok' => true]);
