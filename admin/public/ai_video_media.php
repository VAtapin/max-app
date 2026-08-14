<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/ai_jobs.php';

$admin = require_auth();
$owner = ai_owner_for_admin($admin);
$id = max(0, (int)($_GET['id'] ?? 0));
$stmt = db()->prepare('SELECT j.output_path FROM ai_video_jobs j JOIN ai_avatars a ON a.id = j.avatar_id WHERE j.id = :id AND a.owner_type = :owner_type AND a.owner_id = :owner_id AND j.status = "ready" LIMIT 1');
$stmt->execute(['id' => $id] + $owner);
$relative = trim((string)$stmt->fetchColumn());
$root = realpath(ai_private_storage_root());
$path = $root && $relative !== '' ? realpath($root . '/' . ltrim(str_replace('\\', '/', $relative), '/')) : false;
if (!$root || !$path || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) {
    http_response_code(404);
    exit;
}
header('Content-Type: video/mp4');
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="swpro-ai-video-' . $id . '.mp4"');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
readfile($path);
