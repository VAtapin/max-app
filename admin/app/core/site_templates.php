<?php

function site_template_options(): array
{
    try {
        $stmt = db()->query(
            'SELECT id, title AS label, description
             FROM site_templates
             WHERE is_active = 1
             ORDER BY sort_order, id'
        );
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function site_template_default_id(): ?int
{
    $stmt = db()->query(
        'SELECT id
         FROM site_templates
         WHERE is_active = 1
         ORDER BY sort_order, id
         LIMIT 1'
    );
    $id = $stmt->fetchColumn();
    return $id !== false ? (int)$id : null;
}

function site_template_row(int $templateId): ?array
{
    if ($templateId <= 0) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM site_templates WHERE id = :id AND is_active = 1 LIMIT 1');
    $stmt->execute(['id' => $templateId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function site_template_owner_vars(string $ownerType, int $ownerId): array
{
    $table = $ownerType === 'reseller' ? 'resellers' : 'managers';
    $stmt = db()->prepare("SELECT name, email, phone FROM {$table} WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $ownerId]);
    $owner = $stmt->fetch() ?: [];
    $name = trim((string)($owner['name'] ?? ''));

    return [
        'name' => $name !== '' ? $name : ($ownerType === 'reseller' ? 'Ваш лидер' : 'Ваш консультант'),
        'role_label' => $ownerType === 'reseller' ? 'Лидер команды' : 'Консультант',
        'email' => trim((string)($owner['email'] ?? '')),
        'phone' => trim((string)($owner['phone'] ?? '')),
    ];
}

function site_template_render_value(mixed $value, array $vars): mixed
{
    if (is_string($value)) {
        foreach ($vars as $key => $replacement) {
            $value = str_replace('{{' . $key . '}}', (string)$replacement, $value);
        }
        return $value;
    }

    if (is_array($value)) {
        foreach ($value as $key => $item) {
            $value[$key] = site_template_render_value($item, $vars);
        }
    }

    return $value;
}

function site_template_decode_json(?string $json): array
{
    $decoded = json_decode((string)$json, true);
    return is_array($decoded) ? $decoded : [];
}

function site_template_apply_to_profile(
    int $profileId,
    string $ownerType,
    int $ownerId,
    int $templateId,
    bool $preserveMedia = true
): bool {
    $template = site_template_row($templateId);
    if (!$template) {
        return false;
    }

    $stmt = db()->prepare('SELECT * FROM consultant_profiles WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $profileId]);
    $profile = $stmt->fetch();
    if (!$profile) {
        return false;
    }

    $vars = site_template_owner_vars($ownerType, $ownerId);
    $profilePayload = site_template_render_value(
        site_template_decode_json($template['profile_json'] ?? null),
        $vars
    );

    $allowedProfileFields = [
        'display_name',
        'title',
        'subtitle',
        'short_description',
        'welcome_text',
        'welcome_image_path',
        'welcome_video_url',
        'cashback_title',
        'cashback_text',
        'cashback_image_path',
        'cashback_url',
        'cooperation_title',
        'cooperation_text',
        'cooperation_image_path',
        'cooperation_video_url',
        'bio',
        'specialization',
        'experience_text',
        'achievements_text',
        'certificates_text',
        'video_url',
        'phone',
        'email',
        'telegram_url',
        'whatsapp_url',
        'vk_url',
        'ok_url',
        'theme_key',
    ];
    $mediaFields = [
        'welcome_image_path',
        'cashback_image_path',
        'cooperation_image_path',
        'photo_path',
        'banner_path',
    ];

    $updates = [];
    $params = [
        'id' => $profileId,
        'template_id' => $templateId,
    ];
    foreach ($allowedProfileFields as $field) {
        if (!array_key_exists($field, $profilePayload)) {
            continue;
        }
        if ($preserveMedia && in_array($field, $mediaFields, true) && trim((string)($profile[$field] ?? '')) !== '') {
            continue;
        }
        $updates[] = $field . ' = :' . $field;
        $params[$field] = $profilePayload[$field] !== '' ? $profilePayload[$field] : null;
    }

    $updates[] = 'template_id = :template_id';
    $updates[] = 'source_profile_id = NULL';
    $updates[] = 'template_applied_at = NOW()';
    $updates[] = 'template_customized_at = NOW()';

    db()->beginTransaction();
    try {
        $update = db()->prepare(
            'UPDATE consultant_profiles
             SET ' . implode(', ', $updates) . '
             WHERE id = :id'
        );
        $update->execute($params);

        $blocks = site_template_render_value(
            site_template_decode_json($template['blocks_json'] ?? null),
            $vars
        );
        if ($blocks) {
            $blockStmt = db()->prepare(
                'INSERT INTO profile_blocks (profile_id, block_type, title, is_enabled, sort_order, settings_json)
                 VALUES (:profile_id, :block_type, :title, :is_enabled, :sort_order, :settings_json)
                 ON DUPLICATE KEY UPDATE
                    title = VALUES(title),
                    is_enabled = VALUES(is_enabled),
                    sort_order = VALUES(sort_order),
                    settings_json = VALUES(settings_json)'
            );
            foreach ($blocks as $block) {
                if (!is_array($block) || empty($block['block_type'])) {
                    continue;
                }
                $settings = $block['settings'] ?? null;
                $blockStmt->execute([
                    'profile_id' => $profileId,
                    'block_type' => (string)$block['block_type'],
                    'title' => trim((string)($block['title'] ?? '')),
                    'is_enabled' => !empty($block['is_enabled']) || !empty($block['enabled']) ? 1 : 0,
                    'sort_order' => (int)($block['sort_order'] ?? 100),
                    'settings_json' => is_array($settings) ? json_encode($settings, JSON_UNESCAPED_UNICODE) : null,
                ]);
            }
        }

        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }

    return true;
}
