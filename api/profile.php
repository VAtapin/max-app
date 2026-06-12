<?php

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../admin/app/core/consultant_profiles.php';

function profile_by_slug(string $slug): ?array
{
    $stmt = db()->prepare('SELECT * FROM consultant_profiles WHERE slug = :slug AND is_public = 1 LIMIT 1');
    $stmt->execute(['slug' => $slug]);
    $profile = $stmt->fetch();
    return $profile ?: null;
}

function profile_for_user(array $user): ?array
{
    if (!empty($user['manager_id'])) {
        return ensure_consultant_profile('manager', (int)$user['manager_id']);
    }

    if (!empty($user['reseller_id'])) {
        return ensure_consultant_profile('reseller', (int)$user['reseller_id']);
    }

    return null;
}

$slug = trim((string)($_GET['m'] ?? $_GET['slug'] ?? ''));
$profile = $slug !== '' ? profile_by_slug($slug) : null;

if (!$profile) {
    $user = require_platform_user();
    $profile = profile_for_user($user);
}

if (!$profile) {
    json_response(['error' => 'profile not found'], 404);
}

json_response(consultant_profile_payload($profile));
