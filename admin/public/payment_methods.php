<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/permissions.php';
require_once __DIR__ . '/../app/core/payment_config.php';

$admin = require_auth();
if (($admin['role'] ?? '') !== 'superadmin') {
    http_response_code(403);
    exit('Access denied');
}

$title = 'Методы оплаты';
$errors = [];
$success = $_GET['success'] ?? null;

function payment_method_config_fields(string $code): array
{
    return match ($code) {
        'stripe' => ['secret_key' => 'Секретный ключ', 'webhook_secret' => 'Секрет webhook'],
        'paypal' => ['client_id' => 'Client ID', 'client_secret' => 'Client secret', 'webhook_id' => 'Webhook ID'],
        'yookassa' => ['shop_id' => 'Shop ID', 'secret_key' => 'Секретный ключ'],
        'cloudpayments' => ['public_id' => 'Public ID', 'api_secret' => 'API Secret'],
        default => [],
    };
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $stmt = db()->prepare('SELECT * FROM payment_methods WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $method = $stmt->fetch();
    if (!$method) {
        $errors[] = 'Метод оплаты не найден.';
    } else {
        $config = payment_config_decode($method['config_json'] ?? null);
        foreach (payment_method_config_fields((string)$method['code']) as $field => $_label) {
            $value = trim((string)($_POST['config'][$field] ?? ''));
            if ($value !== '') $config[$field] = $value;
        }
        $update = db()->prepare(
            'UPDATE payment_methods SET title = :title, description = :description,
             instructions = :instructions, config_json = :config_json, is_test = :is_test,
             is_active = :is_active, sort_order = :sort_order WHERE id = :id'
        );
        $update->execute([
            'id' => $id,
            'title' => trim((string)($_POST['title'] ?? '')) ?: $method['title'],
            'description' => trim((string)($_POST['description'] ?? '')) ?: null,
            'instructions' => trim((string)($_POST['instructions'] ?? '')) ?: null,
            'config_json' => payment_config_encode($config),
            'is_test' => isset($_POST['is_test']) ? 1 : 0,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'sort_order' => (int)($_POST['sort_order'] ?? 100),
        ]);
        log_activity('admin', (int)$admin['id'], 'update_payment_method', 'payment_methods', $id);
        redirect('payment_methods.php?success=saved');
    }
}

$methods = db()->query('SELECT * FROM payment_methods ORDER BY sort_order, id')->fetchAll();
require __DIR__ . '/../app/views/layouts/header.php';
?>
<div class="toolbar"><h1>Методы оплаты</h1></div>
<div class="notice">Webhook URL: <code><?= h(rtrim((string)(app_config()['app']['public_url'] ?? ''), '/')) ?>/api/payment_webhook.php?method=КОД</code>. Для каждого сервиса замените КОД на stripe, paypal, yookassa или cloudpayments.</div>
<?php if ($success === 'saved'): ?><div class="notice success">Метод оплаты сохранён.</div><?php endif; ?>
<?php foreach ($errors as $error): ?><div class="alert"><?= h($error) ?></div><?php endforeach; ?>

<div class="payment-method-admin-list">
<?php foreach ($methods as $method): ?>
    <?php $config = payment_config_decode($method['config_json'] ?? null); ?>
    <section class="panel form-panel">
        <form method="post" class="crud-form">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int)$method['id'] ?>">
            <h2><?= h((string)$method['title']) ?></h2>
            <label class="field"><span>Название для пользователя</span><input name="title" value="<?= h((string)$method['title']) ?>"></label>
            <label class="field"><span>Порядок</span><input type="number" name="sort_order" value="<?= (int)$method['sort_order'] ?>"></label>
            <label class="field wide"><span>Описание</span><textarea name="description" rows="2"><?= h((string)$method['description']) ?></textarea></label>
            <?php if ($method['method_type'] === 'manual'): ?>
                <label class="field wide"><span>Реквизиты и инструкция перевода</span><textarea name="instructions" rows="8"><?= h((string)$method['instructions']) ?></textarea></label>
            <?php endif; ?>
            <?php foreach (payment_method_config_fields((string)$method['code']) as $field => $label): ?>
                <label class="field"><span><?= h($label) ?></span><input type="password" name="config[<?= h($field) ?>]" autocomplete="new-password" placeholder="<?= isset($config[$field]) ? 'Сохранено — оставьте пустым, чтобы не менять' : '' ?>"></label>
            <?php endforeach; ?>
            <label class="check-row"><input type="checkbox" name="is_test" value="1" <?= (int)$method['is_test'] === 1 ? 'checked' : '' ?>><span>Тестовый режим</span></label>
            <label class="check-row"><input type="checkbox" name="is_active" value="1" <?= (int)$method['is_active'] === 1 ? 'checked' : '' ?>><span>Показывать этот метод всем плательщикам</span></label>
            <button type="submit">Сохранить</button>
        </form>
    </section>
<?php endforeach; ?>
</div>
<?php require __DIR__ . '/../app/views/layouts/footer.php'; ?>
