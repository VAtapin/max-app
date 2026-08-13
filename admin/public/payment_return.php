<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/payment_providers.php';

$admin = require_auth();
$transactionId = (int)($_GET['transaction_id'] ?? 0);
$stmt = db()->prepare('SELECT pt.billing_invoice_id, pm.code FROM payment_transactions pt JOIN payment_methods pm ON pm.id = pt.payment_method_id WHERE pt.id = :id LIMIT 1');
$stmt->execute(['id' => $transactionId]);
$transaction = $stmt->fetch();
$invoiceId = (int)($transaction['billing_invoice_id'] ?? 0);
if (!$invoiceId || !payment_invoice_for_admin($invoiceId, $admin)) {
    http_response_code(404);
    exit('Платёж не найден.');
}
if (($transaction['code'] ?? '') === 'cloudpayments') {
    redirect('billing.php?success=processing');
}
$paid = false;
try { $paid = payment_verify_transaction($transactionId); } catch (Throwable) { $paid = false; }
redirect($paid ? 'billing.php?success=paid' : 'payment_checkout.php?invoice_id=' . $invoiceId);
