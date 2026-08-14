<?php

require_once __DIR__ . '/admin/app/core/db.php';
require_once __DIR__ . '/admin/app/core/helpers.php';
require_once __DIR__ . '/admin/app/core/legal_documents.php';

$type = (string)($_GET['type'] ?? 'privacy_policy');
if (!in_array($type, legal_document_types(), true)) {
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

$referralCode = trim((string)($_GET['ref'] ?? ''));
$rendered = legal_render_document($document);
$body = $rendered['body'];
$backUrl = $referralCode !== '' ? '/?ref=' . rawurlencode($referralCode) : '/';
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
    <p><a href="<?= h($backUrl) ?>">← SWPro</a></p>
    <article>
        <h1><?= h((string)$document['title']) ?></h1>
        <p class="meta">Версия: <?= h((string)$document['version']) ?> · Обновлено: <?= h(legal_date_ru((string)$document['updated_at'])) ?></p>
        <div><?= nl2br(h($body)) ?></div>
    </article>
</main>
</body>
</html>
