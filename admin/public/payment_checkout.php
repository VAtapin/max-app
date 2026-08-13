<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/payment_providers.php';

$admin = require_auth();
$invoiceId = (int)($_GET['invoice_id'] ?? $_POST['invoice_id'] ?? 0);
$invoice = payment_invoice_for_admin($invoiceId, $admin);
if (!$invoice) {
    http_response_code(404);
    exit('Счёт не найден.');
}
if ($invoice['status'] === 'paid') redirect('billing.php?success=paid');
$title = 'Оплата счёта';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $method = payment_method((int)($_POST['method_id'] ?? 0));
        if (!$method) throw new RuntimeException('Выберите активный метод оплаты.');
        $transaction = payment_create_transaction($invoice, $method);
        if ($method['method_type'] === 'manual') {
            $comment = trim((string)($_POST['payer_comment'] ?? ''));
            $receiptPath = null;
            if (!empty($_FILES['receipt']['tmp_name']) && ($_FILES['receipt']['error'] ?? UPLOAD_ERR_OK) === UPLOAD_ERR_OK) {
                if ((int)($_FILES['receipt']['size'] ?? 0) > 10 * 1024 * 1024) throw new RuntimeException('Размер квитанции не должен превышать 10 МБ.');
                $mime = mime_content_type($_FILES['receipt']['tmp_name']) ?: '';
                $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'][$mime] ?? null;
                if (!$extension) throw new RuntimeException('Квитанция должна быть изображением или PDF.');
                $dir = dirname(__DIR__) . '/uploads/payments';
                if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException('Не удалось сохранить квитанцию.');
                $name = date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
                if (!move_uploaded_file($_FILES['receipt']['tmp_name'], $dir . '/' . $name)) throw new RuntimeException('Не удалось сохранить квитанцию.');
                $receiptPath = '/admin/uploads/payments/' . $name;
            }
            $stmt = db()->prepare('UPDATE payment_transactions SET status = "pending", payer_comment = :comment, receipt_path = :receipt WHERE id = :id');
            $stmt->execute(['comment' => $comment ?: null, 'receipt' => $receiptPath, 'id' => $transaction['id']]);
            $stmt = db()->prepare('UPDATE billing_invoices SET status = "awaiting_confirmation" WHERE id = :id');
            $stmt->execute(['id' => $invoiceId]);
            redirect('billing.php?success=manual_sent');
        }
        $redirectUrl = payment_start_gateway($invoice, $method, $transaction);
        header('Location: ' . $redirectUrl);
        exit;
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

$methods = billing_active_payment_methods();
require __DIR__ . '/../app/views/layouts/header.php';
?>
<div class="toolbar"><h1>Оплата счёта</h1><a class="button secondary-button" href="billing.php">Назад</a></div>
<?php foreach ($errors as $error): ?><div class="alert"><?= h($error) ?></div><?php endforeach; ?>
<section class="panel payment-invoice-summary">
    <h2><?= h((string)$invoice['invoice_number']) ?></h2>
    <p>Период: <?= h((string)$invoice['period_start']) ?> — <?= h((string)$invoice['period_end']) ?></p>
    <?php if ((float)$invoice['discount_amount'] > 0): ?><p class="notice success">Ваша скидка <?= h((string)$invoice['discount_percent']) ?>%: экономия <?= h(subscription_money_text((float)$invoice['discount_amount'])) ?></p><?php endif; ?>
    <strong class="payment-total">К оплате: <?= h(subscription_money_text((float)$invoice['amount_due'])) ?></strong>
</section>

<div class="payment-method-list">
<?php foreach ($methods as $method): ?>
    <section class="panel payment-method-card">
        <h2><?= h((string)$method['title']) ?></h2>
        <?php if ($method['description']): ?><p><?= h((string)$method['description']) ?></p><?php endif; ?>
        <?php if ($method['method_type'] === 'manual'): ?>
            <div class="manual-payment-instructions"><?= nl2br(h((string)($method['instructions'] ?: 'Реквизиты будут предоставлены администратором.'))) ?></div>
            <form method="post" enctype="multipart/form-data" class="crud-form">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="invoice_id" value="<?= $invoiceId ?>">
                <input type="hidden" name="method_id" value="<?= (int)$method['id'] ?>">
                <label class="field wide"><span>Комментарий или номер перевода</span><textarea name="payer_comment" rows="3"></textarea></label>
                <label class="field"><span>Квитанция</span><input type="file" name="receipt" accept="image/*,.pdf"></label>
                <button type="submit">Я оплатил</button>
            </form>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="invoice_id" value="<?= $invoiceId ?>">
                <input type="hidden" name="method_id" value="<?= (int)$method['id'] ?>">
                <button type="submit">Оплатить через <?= h((string)$method['title']) ?></button>
            </form>
        <?php endif; ?>
    </section>
<?php endforeach; ?>
<?php if (!$methods): ?><div class="empty-state">Активные методы оплаты ещё не настроены. Обратитесь к администратору.</div><?php endif; ?>
</div>
<?php require __DIR__ . '/../app/views/layouts/footer.php'; ?>
