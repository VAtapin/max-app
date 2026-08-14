<?php

function user_platform_accounts(int $endUserId): array
{
    $stmt = db()->prepare(
        'SELECT platform, platform_user_id, username, first_name, last_name, display_name, created_at
         FROM platform_accounts
         WHERE end_user_id = :end_user_id
         ORDER BY FIELD(platform, "telegram", "VK", "OK", "MAX", "web"), id'
    );
    $stmt->execute(['end_user_id' => $endUserId]);
    return $stmt->fetchAll();
}
