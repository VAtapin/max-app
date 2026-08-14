<?php

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../admin/app/core/ai_jobs.php';

$token = trim((string)($_GET['token'] ?? ''));
if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    http_response_code(404);
    exit;
}
$stmt = db()->prepare(
    'SELECT link.id link_id, job.output_path
     FROM ai_voice_delivery_links link
     INNER JOIN ai_voice_jobs job ON job.id = link.voice_job_id
     WHERE link.token_hash = :token_hash
       AND link.revoked_at IS NULL
       AND job.status = "ready"
     LIMIT 1'
);
$stmt->execute(['token_hash' => hash('sha256', $token)]);
$row = $stmt->fetch();
$relative = trim((string)($row['output_path'] ?? ''));
$root = realpath(ai_private_storage_root());
$path = $root && $relative !== '' ? realpath($root . '/' . ltrim(str_replace('\\', '/', $relative), '/')) : false;
if (!$row || !$root || !$path || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) {
    http_response_code(404);
    exit;
}
db()->prepare('UPDATE ai_voice_delivery_links SET last_accessed_at = NOW() WHERE id = :id')->execute(['id' => (int)$row['link_id']]);
$extension = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));
$mime = match ($extension) {
    'ogg', 'oga' => 'audio/ogg',
    'm4a' => 'audio/mp4',
    default => 'audio/mpeg',
};
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="swpro-voice.' . ($extension ?: 'mp3') . '"');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
readfile($path);
