<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/payment_providers.php';

$admin = require_auth();
$transactionId = (int)($_GET['transaction_id'] ?? 0);
$stmt = db()->prepare(
    'SELECT pt.*, bi.invoice_number, bi.amount_due, pm.config_json
     FROM payment_transactions pt JOIN billing_invoices bi ON bi.id = pt.billing_invoice_id
     JOIN payment_methods pm ON pm.id = pt.payment_method_id WHERE pt.id = :id LIMIT 1'
);
$stmt->execute(['id' => $transactionId]);
$transaction = $stmt->fetch();
if (!$transaction || !payment_invoice_for_admin((int)$transaction['billing_invoice_id'], $admin)) { http_response_code(404); exit('Платёж не найден.'); }
$config = payment_config_decode($transaction['config_json'] ?? null);
$title = 'CloudPayments';
require __DIR__ . '/../app/views/layouts/header.php';
?>
<section class="panel payment-widget-card">
    <h1>Оплата через CloudPayments</h1>
    <p>Счёт <?= h((string)$transaction['invoice_number']) ?></p>
    <strong><?= h(subscription_money_text((float)$transaction['amount_due'])) ?></strong>
    <button id="cloud-pay-button">Перейти к оплате</button>
</section>
<script src="https://widget.cloudpayments.ru/bundles/cloudpayments.js"></script>
<script>
document.getElementById('cloud-pay-button').addEventListener('click', () => {
    const widget = new cp.CloudPayments();
    widget.pay('charge', {
        publicId: <?= json_encode((string)($config['public_id'] ?? '')) ?>,
        description: <?= json_encode('SWPro, счёт ' . $transaction['invoice_number'], JSON_UNESCAPED_UNICODE) ?>,
        amount: <?= json_encode((float)$transaction['amount_due']) ?>,
        currency: 'RUB',
        invoiceId: <?= json_encode((string)$transaction['invoice_number']) ?>,
        accountId: <?= json_encode((string)$transaction['id']) ?>,
        data: {transaction_id: <?= (int)$transaction['id'] ?>}
    }, {onSuccess: () => location.href = 'payment_return.php?transaction_id=<?= (int)$transaction['id'] ?>'});
});
</script>
<?php require __DIR__ . '/../app/views/layouts/footer.php'; ?>
