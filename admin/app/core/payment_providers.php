<?php

require_once __DIR__ . '/workspace_billing.php';
require_once __DIR__ . '/payment_config.php';

function payment_method(int $methodId): ?array
{
    $stmt = db()->prepare('SELECT * FROM payment_methods WHERE id = :id AND is_active = 1 LIMIT 1');
    $stmt->execute(['id' => $methodId]);
    return $stmt->fetch() ?: null;
}

function payment_method_config(array $method): array
{
    return payment_config_decode($method['config_json'] ?? null);
}

function payment_invoice_for_admin(int $invoiceId, array $admin): ?array
{
    $sql = 'SELECT bi.*, ws.subject_type, ws.subject_id
            FROM billing_invoices bi JOIN workspace_subscriptions ws ON ws.id = bi.workspace_subscription_id
            WHERE bi.id = :id';
    $params = ['id' => $invoiceId];
    if (($admin['role'] ?? '') === 'reseller') {
        $resellerId = (int)$admin['reseller_id'];
        if (billing_root_reseller_id($resellerId) === $resellerId) {
            // The main leader remains financially responsible for the branch
            // and may pay any individual workplace invoice in it.
            $sql .= ' AND ws.root_reseller_id = :subject_id';
        } else {
            $sql .= ' AND ws.subject_type = "reseller" AND ws.subject_id = :subject_id';
        }
        $params['subject_id'] = $resellerId;
    } elseif (($admin['role'] ?? '') === 'manager') {
        $sql .= ' AND ws.subject_type = "manager" AND ws.subject_id = :subject_id';
        $params['subject_id'] = (int)$admin['manager_id'];
    } elseif (($admin['role'] ?? '') !== 'superadmin') {
        return null;
    }
    $stmt = db()->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);
    return $stmt->fetch() ?: null;
}

function payment_create_transaction(array $invoice, array $method): array
{
    $key = bin2hex(random_bytes(16));
    $stmt = db()->prepare(
        'INSERT INTO payment_transactions
            (billing_invoice_id, payment_method_id, idempotency_key, amount, status)
         VALUES (:invoice_id, :method_id, :key, :amount, "created")'
    );
    $stmt->execute([
        'invoice_id' => (int)$invoice['id'],
        'method_id' => (int)$method['id'],
        'key' => $key,
        'amount' => (float)$invoice['amount_due'],
    ]);
    $id = (int)db()->lastInsertId();
    $get = db()->prepare('SELECT * FROM payment_transactions WHERE id = :id');
    $get->execute(['id' => $id]);
    return $get->fetch();
}

function payment_http(string $url, string $method = 'POST', array|string|null $body = null, array $headers = []): array
{
    if (!function_exists('curl_init')) throw new RuntimeException('На сервере не установлено расширение cURL.');
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($body !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, is_array($body) ? http_build_query($body) : $body);
    $raw = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    $json = json_decode((string)$raw, true);
    if ($error !== '' || $status < 200 || $status >= 300 || !is_array($json)) {
        throw new RuntimeException('Платёжный сервис не принял запрос' . ($error ? ': ' . $error : '.'));
    }
    return $json;
}

function payment_public_base(): string
{
    return rtrim((string)(app_config()['app']['public_url'] ?? ''), '/');
}

function payment_start_gateway(array $invoice, array $method, array $transaction): string
{
    $config = payment_method_config($method);
    $base = payment_public_base();
    $success = $base . '/admin/public/payment_return.php?transaction_id=' . (int)$transaction['id'];
    $cancel = $base . '/admin/public/payment_checkout.php?invoice_id=' . (int)$invoice['id'];
    $amount = number_format((float)$invoice['amount_due'], 2, '.', '');
    $description = 'SWPro, счёт ' . $invoice['invoice_number'];
    $providerId = null;
    $redirect = null;

    if ($method['code'] === 'stripe') {
        if (empty($config['secret_key'])) throw new RuntimeException('Stripe ещё не настроен.');
        $result = payment_http('https://api.stripe.com/v1/checkout/sessions', 'POST', [
            'mode' => 'payment',
            'success_url' => $success,
            'cancel_url' => $cancel,
            'client_reference_id' => (string)$transaction['id'],
            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]' => 'rub',
            'line_items[0][price_data][unit_amount]' => (int)round((float)$invoice['amount_due'] * 100),
            'line_items[0][price_data][product_data][name]' => $description,
            'metadata[transaction_id]' => (string)$transaction['id'],
        ], ['Authorization: Bearer ' . $config['secret_key'], 'Content-Type: application/x-www-form-urlencoded']);
        $providerId = $result['id'] ?? null;
        $redirect = $result['url'] ?? null;
    } elseif ($method['code'] === 'paypal') {
        if (empty($config['client_id']) || empty($config['client_secret'])) throw new RuntimeException('PayPal ещё не настроен.');
        $host = !empty($method['is_test']) ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
        $token = payment_http($host . '/v1/oauth2/token', 'POST', 'grant_type=client_credentials', [
            'Authorization: Basic ' . base64_encode($config['client_id'] . ':' . $config['client_secret']),
            'Content-Type: application/x-www-form-urlencoded',
        ]);
        $result = payment_http($host . '/v2/checkout/orders', 'POST', json_encode([
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => (string)$transaction['id'],
                'description' => $description,
                'amount' => ['currency_code' => 'RUB', 'value' => $amount],
            ]],
            'application_context' => ['return_url' => $success, 'cancel_url' => $cancel],
        ], JSON_UNESCAPED_SLASHES), [
            'Authorization: Bearer ' . $token['access_token'], 'Content-Type: application/json',
            'PayPal-Request-Id: ' . $transaction['idempotency_key'],
        ]);
        $providerId = $result['id'] ?? null;
        foreach ($result['links'] ?? [] as $link) if (($link['rel'] ?? '') === 'approve') $redirect = $link['href'];
    } elseif ($method['code'] === 'yookassa') {
        if (empty($config['shop_id']) || empty($config['secret_key'])) throw new RuntimeException('ЮKassa ещё не настроена.');
        $result = payment_http('https://api.yookassa.ru/v3/payments', 'POST', json_encode([
            'amount' => ['value' => $amount, 'currency' => 'RUB'],
            'capture' => true,
            'confirmation' => ['type' => 'redirect', 'return_url' => $success],
            'description' => $description,
            'metadata' => ['transaction_id' => (string)$transaction['id']],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), [
            'Authorization: Basic ' . base64_encode($config['shop_id'] . ':' . $config['secret_key']),
            'Idempotence-Key: ' . $transaction['idempotency_key'], 'Content-Type: application/json',
        ]);
        $providerId = $result['id'] ?? null;
        $redirect = $result['confirmation']['confirmation_url'] ?? null;
    } elseif ($method['code'] === 'cloudpayments') {
        if (empty($config['public_id'])) throw new RuntimeException('CloudPayments ещё не настроен.');
        $providerId = 'cp-' . $transaction['id'];
        $redirect = $base . '/admin/public/cloudpayments_widget.php?transaction_id=' . (int)$transaction['id'];
    }

    if (!$redirect) throw new RuntimeException('Платёжный сервис не вернул ссылку оплаты.');
    $update = db()->prepare('UPDATE payment_transactions SET provider_payment_id = :provider_id, status = "pending", provider_payload = :payload WHERE id = :id');
    $update->execute(['provider_id' => $providerId, 'payload' => json_encode(['redirect' => $redirect]), 'id' => $transaction['id']]);
    return $redirect;
}

function payment_verify_transaction(int $transactionId): bool
{
    $stmt = db()->prepare(
        'SELECT pt.*, pm.code, pm.config_json, pm.is_test, bi.amount_due
         FROM payment_transactions pt
         JOIN payment_methods pm ON pm.id = pt.payment_method_id
         JOIN billing_invoices bi ON bi.id = pt.billing_invoice_id
         WHERE pt.id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $transactionId]);
    $transaction = $stmt->fetch();
    if (!$transaction) return false;
    if ($transaction['status'] === 'succeeded') return true;
    $config = payment_config_decode($transaction['config_json'] ?? null);
    $paid = false;
    if ($transaction['code'] === 'stripe' && !empty($config['secret_key'])) {
        $result = payment_http('https://api.stripe.com/v1/checkout/sessions/' . rawurlencode((string)$transaction['provider_payment_id']), 'GET', null, ['Authorization: Bearer ' . $config['secret_key']]);
        $paid = ($result['payment_status'] ?? '') === 'paid' && (int)($result['amount_total'] ?? 0) === (int)round((float)$transaction['amount_due'] * 100);
    } elseif ($transaction['code'] === 'paypal' && !empty($config['client_id']) && !empty($config['client_secret'])) {
        $host = !empty($transaction['is_test']) ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
        $token = payment_http($host . '/v1/oauth2/token', 'POST', 'grant_type=client_credentials', ['Authorization: Basic ' . base64_encode($config['client_id'] . ':' . $config['client_secret']), 'Content-Type: application/x-www-form-urlencoded']);
        try {
            $result = payment_http($host . '/v2/checkout/orders/' . rawurlencode((string)$transaction['provider_payment_id']) . '/capture', 'POST', '{}', ['Authorization: Bearer ' . $token['access_token'], 'Content-Type: application/json', 'PayPal-Request-Id: capture-' . $transaction['idempotency_key']]);
        } catch (Throwable) {
            $result = payment_http($host . '/v2/checkout/orders/' . rawurlencode((string)$transaction['provider_payment_id']), 'GET', null, ['Authorization: Bearer ' . $token['access_token']]);
        }
        $captured = $result['purchase_units'][0]['payments']['captures'][0]['amount']['value']
            ?? $result['purchase_units'][0]['amount']['value'] ?? null;
        $currency = $result['purchase_units'][0]['payments']['captures'][0]['amount']['currency_code']
            ?? $result['purchase_units'][0]['amount']['currency_code'] ?? null;
        $paid = ($result['status'] ?? '') === 'COMPLETED'
            && $currency === 'RUB'
            && $captured !== null
            && abs((float)$captured - (float)$transaction['amount_due']) < 0.01;
    } elseif ($transaction['code'] === 'yookassa' && !empty($config['shop_id']) && !empty($config['secret_key'])) {
        $result = payment_http('https://api.yookassa.ru/v3/payments/' . rawurlencode((string)$transaction['provider_payment_id']), 'GET', null, ['Authorization: Basic ' . base64_encode($config['shop_id'] . ':' . $config['secret_key'])]);
        $paid = ($result['status'] ?? '') === 'succeeded' && abs((float)($result['amount']['value'] ?? 0) - (float)$transaction['amount_due']) < 0.01;
    }
    if ($paid) billing_complete_invoice((int)$transaction['billing_invoice_id'], (int)$transaction['id']);
    return $paid;
}
