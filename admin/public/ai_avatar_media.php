<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/ai_center.php';

$admin = require_auth();
$owner = ai_owner_for_admin($admin);
$id = max(0, (int)($_GET['id'] ?? 0));
$type = (string)($_GET['type'] ?? 'preview');
$columns = [
    'photo' => 'source_photo_path',
    'video' => 'source_video_path',
    'preview' => 'preview_video_path',
];
if ($id <= 0 || !isset($columns[$type])) {
    http_response_code(404);
    exit;
}
$stmt = db()->prepare('SELECT ' . $columns[$type] . ' AS media_path FROM ai_avatars WHERE id = :id AND owner_type = :owner_type AND owner_id = :owner_id LIMIT 1');
$stmt->execute(['id' => $id] + $owner);
$relative = trim((string)$stmt->fetchColumn());
$configuredRoot = trim((string)(getenv('SWPRO_PRIVATE_STORAGE_PATH') ?: ''));
$root = realpath($configuredRoot !== '' ? $configuredRoot : dirname(__DIR__, 2) . '/storage/private');
$path = $root && $relative !== '' ? realpath($root . '/' . ltrim(str_replace('\\', '/', $relative), '/')) : false;
if (!$root || !$path || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) {
    http_response_code(404);
    exit;
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
readfile($path);
