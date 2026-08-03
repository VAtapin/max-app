<?php

require_once __DIR__ . '/admin/app/core/db.php';
require_once __DIR__ . '/admin/app/core/helpers.php';

$type = (string)($_GET['type'] ?? 'privacy_policy');
$allowed = [
    'privacy_policy',
    'personal_data_consent',
    'health_data_consent',
    'marketing_consent',
    'user_agreement',
    'leader_offer',
];
if (!in_array($type, $allowed, true)) {
    http_response_code(404);
    exit('Документ не найден');
}

$stmt = db()->prepare(
    'SELECT *
     FROM legal_documents
     WHERE document_type = :document_type AND is_active = 1
     ORDER BY id DESC
     LIMIT 1'
);
$stmt->execute(['document_type' => $type]);
$document = $stmt->fetch();
if (!$document) {
    http_response_code(404);
    exit('Документ не опубликован');
}

$settingsStmt = db()->query(
    'SELECT setting_key, setting_value
     FROM settings
     WHERE setting_key IN (
        "legal_operator_name",
        "legal_operator_inn",
        "legal_operator_address",
        "legal_operator_email",
        "leader_monthly_price"
     )'
);
$settings = [];
foreach ($settingsStmt->fetchAll() as $row) {
    $settings[(string)$row['setting_key']] = (string)($row['setting_value'] ?? '');
}

$replacements = [
    '[ОПЕРАТОР]' => $settings['legal_operator_name'] ?: '[ОПЕРАТОР]',
    '[УКАЖИТЕ НАИМЕНОВАНИЕ ИЛИ ФИО ОПЕРАТОРА]' => $settings['legal_operator_name'] ?: '[ОПЕРАТОР]',
    '[ИНН]' => $settings['legal_operator_inn'] ?: '[ИНН]',
    '[АДРЕС]' => $settings['legal_operator_address'] ?: '[АДРЕС]',
    '[EMAIL]' => $settings['legal_operator_email'] ?: '[EMAIL]',
    '[ИСПОЛНИТЕЛЬ]' => $settings['legal_operator_name'] ?: '[ИСПОЛНИТЕЛЬ]',
    '[СТОИМОСТЬ]' => $settings['leader_monthly_price'] ?: '[СТОИМОСТЬ]',
];
$body = strtr((string)$document['body'], $replacements);
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h((string)$document['title']) ?> — SWPro</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#0b4f86">
    <style>
        body { margin: 0; background: #f4f6f8; color: #202739; font: 16px/1.65 system-ui, sans-serif; }
        main { max-width: 820px; margin: 0 auto; padding: 32px 20px 60px; }
        article { background: #fff; border: 1px solid #dbe3ea; padding: 28px; }
        h1 { line-height: 1.2; }
        .meta { color: #667085; }
        a { color: #315c72; }
        @media (max-width: 600px) { main { padding: 12px; } article { padding: 18px; } }
    </style>
</head>
<body>
<main>
    <p><a href="/">← SWPro</a></p>
    <article>
        <h1><?= h((string)$document['title']) ?></h1>
        <p class="meta">Версия: <?= h((string)$document['version']) ?> · Обновлено: <?= h((string)$document['updated_at']) ?></p>
        <div><?= nl2br(h($body)) ?></div>
    </article>
</main>
</body>
</html>
