<?php

require_once __DIR__ . '/content_ownership.php';
require_once __DIR__ . '/site_templates.php';
require_once __DIR__ . '/referral_codes.php';

function consultant_owner_from_admin(array $admin): ?array
{
    if ($admin['role'] === 'manager' && !empty($admin['manager_id'])) {
        return ['owner_type' => 'manager', 'owner_id' => (int)$admin['manager_id']];
    }

    if ($admin['role'] === 'reseller' && !empty($admin['reseller_id'])) {
        return ['owner_type' => 'reseller', 'owner_id' => (int)$admin['reseller_id']];
    }

    if ($admin['role'] === 'superadmin') {
        $ownerType = $_GET['owner_type'] ?? $_POST['owner_type'] ?? 'manager';
        $ownerId = (int)($_GET['owner_id'] ?? $_POST['owner_id'] ?? 0);
        if (in_array($ownerType, ['manager', 'reseller'], true) && $ownerId > 0) {
            return ['owner_type' => $ownerType, 'owner_id' => $ownerId];
        }

        $manager = db()->query('SELECT id FROM managers WHERE is_active = 1 ORDER BY id ASC LIMIT 1')->fetch();
        if ($manager) {
            return ['owner_type' => 'manager', 'owner_id' => (int)$manager['id']];
        }
    }

    return null;
}

function consultant_owner_row(string $ownerType, int $ownerId): ?array
{
    $table = $ownerType === 'reseller' ? 'resellers' : 'managers';
    $stmt = db()->prepare("SELECT * FROM $table WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $ownerId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function consultant_parent_owner(string $ownerType, int $ownerId): ?array
{
    if ($ownerType === 'manager') {
        $stmt = db()->prepare('SELECT reseller_id FROM managers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $ownerId]);
        $resellerId = $stmt->fetchColumn();

        return $resellerId ? ['owner_type' => 'reseller', 'owner_id' => (int)$resellerId] : null;
    }

    if ($ownerType === 'reseller') {
        $stmt = db()->prepare('SELECT parent_reseller_id FROM resellers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $ownerId]);
        $parentId = $stmt->fetchColumn();

        return $parentId ? ['owner_type' => 'reseller', 'owner_id' => (int)$parentId] : null;
    }

    return null;
}

function consultant_parent_profile(string $ownerType, int $ownerId): ?array
{
    $parent = consultant_parent_owner($ownerType, $ownerId);
    if (!$parent) {
        return null;
    }

    return ensure_consultant_profile($parent['owner_type'], (int)$parent['owner_id']);
}

function consultant_slug(string $value, string $fallback): string
{
    $value = trim($value);
    if ($value === '') {
        $value = $fallback;
    }

    $map = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch',
        'ы' => 'y', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya', 'ъ' => '', 'ь' => '',
    ];
    $value = strtr(function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value), $map);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?: '';
    $value = trim($value, '-');

    return $value !== '' ? substr($value, 0, 190) : $fallback;
}

function consultant_referral_code(?string $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    if (str_starts_with($value, 'ref_')) {
        $value = substr($value, 4);
    }

    return trim($value) !== '' ? trim($value) : null;
}

function consultant_profile_by_referral_code(?string $referralCode): ?array
{
    $referralCode = consultant_referral_code($referralCode);
    if (!$referralCode) {
        return null;
    }

    $binding = referral_code_binding($referralCode);
    return $binding
        ? ensure_consultant_profile((string)$binding['owner_type'], (int)$binding['owner_id'])
        : null;
}

function ensure_consultant_profile(string $ownerType, int $ownerId, array $seen = []): array
{
    $seenKey = $ownerType . ':' . $ownerId;
    if (isset($seen[$seenKey])) {
        throw new RuntimeException('Обнаружено циклическое наследование мини-сайта.');
    }
    $seen[$seenKey] = true;

    $stmt = db()->prepare('SELECT * FROM consultant_profiles WHERE owner_type = :owner_type AND owner_id = :owner_id LIMIT 1');
    $stmt->execute(['owner_type' => $ownerType, 'owner_id' => $ownerId]);
    $profile = $stmt->fetch();
    if ($profile) {
        ensure_consultant_blocks((int)$profile['id']);
        ensure_consultant_primary_test((int)$profile['id']);
        return $profile;
    }

    $owner = consultant_owner_row($ownerType, $ownerId);
    $name = $owner['name'] ?? ($ownerType . '-' . $ownerId);
    $slug = consultant_unique_slug(consultant_slug($name, $ownerType . '-' . $ownerId));
    $parent = consultant_parent_owner($ownerType, $ownerId);
    $sourceProfileId = null;
    if ($parent) {
        $parentProfile = ensure_consultant_profile($parent['owner_type'], (int)$parent['owner_id'], $seen);
        $sourceProfileId = (int)$parentProfile['id'];
    }

    $insert = db()->prepare(
        'INSERT INTO consultant_profiles
            (owner_type, owner_id, source_profile_id, slug, display_name, title, subtitle, phone, email, theme_key, is_public)
         VALUES
            (:owner_type, :owner_id, :source_profile_id, :slug, :display_name, :title, :subtitle, :phone, :email, :theme_key, 1)'
    );
    $insert->execute([
        'owner_type' => $ownerType,
        'owner_id' => $ownerId,
        'source_profile_id' => $sourceProfileId,
        'slug' => $slug,
        'display_name' => $name,
        'title' => $ownerType === 'reseller' ? app_text('consultant_profile.default_reseller_title') : app_text('consultant_profile.default_manager_title'),
        'subtitle' => app_text('consultant_profile.default_subtitle'),
        'phone' => $owner['phone'] ?? null,
        'email' => $owner['email'] ?? null,
        'theme_key' => 'classic',
    ]);

    $profileId = (int)db()->lastInsertId();
    ensure_consultant_blocks($profileId);
    ensure_consultant_primary_test($profileId);
    $defaultTemplateId = site_template_default_id();
    if (!$sourceProfileId && $defaultTemplateId) {
        site_template_apply_to_profile($profileId, $ownerType, $ownerId, $defaultTemplateId, true);
        ensure_consultant_primary_test($profileId);
    }

    $stmt->execute(['owner_type' => $ownerType, 'owner_id' => $ownerId]);
    return $stmt->fetch();
}

function consultant_profile_inherits(array $profile): bool
{
    return !empty($profile['source_profile_id']) && empty($profile['template_customized_at']);
}

function consultant_profile_source(array $profile): ?array
{
    if (!consultant_profile_inherits($profile)) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM consultant_profiles WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => (int)$profile['source_profile_id']]);
    $source = $stmt->fetch();

    return $source ?: null;
}

function consultant_effective_profile_id(array $profile, array $seen = []): int
{
    $profileId = (int)($profile['id'] ?? 0);
    if (!$profileId || isset($seen[$profileId])) {
        return $profileId;
    }
    $seen[$profileId] = true;

    $source = consultant_profile_source($profile);
    if (!$source) {
        return $profileId;
    }

    return consultant_effective_profile_id($source, $seen);
}

function consultant_effective_profile(array $profile, array $seen = []): array
{
    $profileId = (int)($profile['id'] ?? 0);
    if (!$profileId || isset($seen[$profileId])) {
        return $profile;
    }
    $seen[$profileId] = true;

    $source = consultant_profile_source($profile);
    if (!$source) {
        return $profile;
    }

    $effective = consultant_effective_profile($source, $seen);
    foreach ([
        'id',
        'owner_type',
        'owner_id',
        'source_profile_id',
        'slug',
        'display_name',
        'phone',
        'email',
        'telegram_url',
        'whatsapp_url',
        'vk_url',
        'ok_url',
        'photo_path',
        'is_public',
        'created_at',
        'updated_at',
    ] as $field) {
        if (array_key_exists($field, $profile)) {
            $effective[$field] = $profile[$field];
        }
    }
    $effective['inherited_from_profile_id'] = (int)$source['id'];
    $effective['effective_profile_id'] = consultant_effective_profile_id($source);

    return $effective;
}

function consultant_profile_reset_to_parent(int $profileId, int $sourceProfileId): void
{
    db()->beginTransaction();
    try {
        $stmt = db()->prepare(
            'UPDATE consultant_profiles
             SET source_profile_id = :source_profile_id,
                 template_customized_at = NULL,
                 template_applied_at = NULL
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $profileId,
            'source_profile_id' => $sourceProfileId,
        ]);

        foreach (['profile_blocks', 'profile_products', 'profile_tests', 'profile_materials', 'profile_cashback_cards'] as $table) {
            $delete = db()->prepare("DELETE FROM {$table} WHERE profile_id = :profile_id");
            $delete->execute(['profile_id' => $profileId]);
        }

        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}

function ensure_consultant_primary_test(int $profileId): void
{
    $stmt = db()->prepare(
        'INSERT IGNORE INTO profile_tests (profile_id, test_id, sort_order)
         SELECT :profile_id, id, 10
         FROM tests
         WHERE title = "Диагностика организма" AND is_active = 1
         ORDER BY id
         LIMIT 1'
    );
    $stmt->execute(['profile_id' => $profileId]);
}

function consultant_theme_options(): array
{
    return [
        'classic' => app_text('consultant_profile.theme_classic'),
        'ocean' => app_text('consultant_profile.theme_ocean'),
        'berry' => app_text('consultant_profile.theme_berry'),
        'graphite' => app_text('consultant_profile.theme_graphite'),
    ];
}

function consultant_unique_slug(string $slug, ?int $ignoreProfileId = null): string
{
    $base = $slug;
    $suffix = 2;
    while (true) {
        $sql = 'SELECT id FROM consultant_profiles WHERE slug = :slug';
        $params = ['slug' => $slug];
        if ($ignoreProfileId) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreProfileId;
        }
        $sql .= ' LIMIT 1';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = substr($base, 0, 180) . '-' . $suffix;
        $suffix++;
    }
}

function default_consultant_blocks(): array
{
    return [
        'hero' => [app_text('consultant_profile.block_hero'), 10],
        'video' => [app_text('consultant_profile.block_video'), 20],
        'about' => [app_text('consultant_profile.block_about'), 30],
        'cashback' => ['Кэшбэк и подарки', 40],
        'cooperation' => ['Возможность сотрудничества', 50],
        'tests' => [app_text('consultant_profile.block_tests'), 60],
        'materials' => [app_text('consultant_profile.block_materials'), 70],
        'reviews' => [app_text('consultant_profile.block_reviews'), 80],
        'contacts' => [app_text('consultant_profile.block_contacts'), 90],
        'products' => [app_text('consultant_profile.block_products'), 100],
    ];
}

function ensure_consultant_blocks(int $profileId): void
{
    $stmt = db()->prepare(
        'INSERT INTO profile_blocks (profile_id, block_type, title, is_enabled, sort_order)
         VALUES (:profile_id, :block_type, :title, :is_enabled, :sort_order)
         ON DUPLICATE KEY UPDATE title = COALESCE(title, VALUES(title))'
    );
    foreach (default_consultant_blocks() as $blockType => [$title, $sortOrder]) {
        $stmt->execute([
            'profile_id' => $profileId,
            'block_type' => $blockType,
            'title' => $title,
            'is_enabled' => $blockType === 'products' ? 0 : 1,
            'sort_order' => $sortOrder,
        ]);
    }
}

function consultant_blocks(int $profileId): array
{
    ensure_consultant_blocks($profileId);
    $stmt = db()->prepare('SELECT * FROM profile_blocks WHERE profile_id = :profile_id ORDER BY sort_order, id');
    $stmt->execute(['profile_id' => $profileId]);
    return $stmt->fetchAll();
}

function consultant_selected_ids(int $profileId, string $table, string $column): array
{
    $stmt = db()->prepare("SELECT $column FROM $table WHERE profile_id = :profile_id ORDER BY sort_order, id");
    $stmt->execute(['profile_id' => $profileId]);
    return array_map(static fn($row) => (int)$row[$column], $stmt->fetchAll());
}

function replace_consultant_items(int $profileId, string $table, string $column, array $ids): void
{
    $delete = db()->prepare("DELETE FROM $table WHERE profile_id = :profile_id");
    $delete->execute(['profile_id' => $profileId]);

    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) {
        return;
    }

    $insert = db()->prepare("INSERT INTO $table (profile_id, $column, sort_order) VALUES (:profile_id, :item_id, :sort_order)");
    foreach ($ids as $index => $itemId) {
        $insert->execute([
            'profile_id' => $profileId,
            'item_id' => $itemId,
            'sort_order' => ($index + 1) * 10,
        ]);
    }
}

function consultant_cashback_cards(int $profileId, ?array $legacyProfile = null): array
{
    $stmt = db()->prepare(
        'SELECT id, title, description, image_path, card_url, button_text, sort_order
         FROM profile_cashback_cards
         WHERE profile_id = :profile_id
         ORDER BY sort_order, id'
    );
    $stmt->execute(['profile_id' => $profileId]);
    $cards = $stmt->fetchAll();
    if ($cards) {
        return $cards;
    }

    return [[
        'id' => 0,
        'title' => (string)($legacyProfile['cashback_title'] ?? ''),
        'description' => (string)($legacyProfile['cashback_text'] ?? ''),
        'image_path' => (string)($legacyProfile['cashback_image_path'] ?? ''),
        'card_url' => (string)($legacyProfile['cashback_url'] ?? ''),
        'button_text' => 'Оформить карту клиента',
        'sort_order' => 10,
    ]];
}

function replace_consultant_cashback_cards(int $profileId, array $cards): void
{
    if (!$cards) {
        $cards = [[
            'title' => '',
            'description' => '',
            'image_path' => null,
            'card_url' => '',
            'button_text' => 'Оформить карту клиента',
        ]];
    }

    db()->beginTransaction();
    try {
        $delete = db()->prepare('DELETE FROM profile_cashback_cards WHERE profile_id = :profile_id');
        $delete->execute(['profile_id' => $profileId]);

        $insert = db()->prepare(
            'INSERT INTO profile_cashback_cards
                (profile_id, title, description, image_path, card_url, button_text, sort_order)
             VALUES
                (:profile_id, :title, :description, :image_path, :card_url, :button_text, :sort_order)'
        );
        foreach (array_values($cards) as $index => $card) {
            $insert->execute([
                'profile_id' => $profileId,
                'title' => trim((string)($card['title'] ?? '')) ?: null,
                'description' => trim((string)($card['description'] ?? '')) ?: null,
                'image_path' => trim((string)($card['image_path'] ?? '')) ?: null,
                'card_url' => trim((string)($card['card_url'] ?? '')) ?: null,
                'button_text' => trim((string)($card['button_text'] ?? '')) ?: 'Оформить карту клиента',
                'sort_order' => ($index + 1) * 10,
            ]);
        }
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}

function consultant_profile_upload_dir(): string
{
    return dirname(__DIR__, 2) . '/uploads/profiles';
}

function consultant_profile_public_path(string $filename): string
{
    return '/admin/uploads/profiles/' . $filename;
}

function consultant_profile_store_upload(array $file, ?string $currentPath, array &$errors): ?string
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $currentPath;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $errors[] = app_text('consultant_profile.upload_failed');
        return $currentPath;
    }

    $mime = mime_content_type($file['tmp_name']) ?: '';
    $extension = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => null,
    };
    if (!$extension) {
        $errors[] = app_text('consultant_profile.upload_image_type');
        return $currentPath;
    }

    $directory = consultant_profile_upload_dir();
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        $errors[] = app_text('consultant_profile.upload_dir_failed');
        return $currentPath;
    }

    $filename = date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) {
        $errors[] = app_text('consultant_profile.upload_failed');
        return $currentPath;
    }

    return consultant_profile_public_path($filename);
}

function consultant_profile_upload(string $field, ?string $currentPath, array &$errors): ?string
{
    return consultant_profile_store_upload($_FILES[$field] ?? [], $currentPath, $errors);
}

function consultant_profile_indexed_upload(string $field, string|int $index, ?string $currentPath, array &$errors): ?string
{
    $source = $_FILES[$field] ?? [];
    $file = [];
    foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $key) {
        if (isset($source[$key]) && is_array($source[$key]) && array_key_exists($index, $source[$key])) {
            $file[$key] = $source[$key][$index];
        }
    }

    return consultant_profile_store_upload($file, $currentPath, $errors);
}

function consultant_options_for_admin(array $admin): array
{
    if ($admin['role'] === 'manager') {
        return [];
    }

    $options = [];
    if ($admin['role'] === 'reseller') {
        $resellerIds = team_reseller_branch_ids((int)$admin['reseller_id'], true);
        [$resellerWhere, $resellerParams] = team_sql_in_condition('id', $resellerIds, 'profile_reseller');
        $stmt = db()->prepare('SELECT id, name FROM resellers WHERE ' . $resellerWhere . ' ORDER BY name, id');
        $stmt->execute($resellerParams);
        foreach ($stmt->fetchAll() as $row) {
            $options[] = ['owner_type' => 'reseller', 'owner_id' => (int)$row['id'], 'label' => 'Лидер: ' . team_reseller_label((int)$row['id'])];
        }

        $managerIds = team_manager_ids_for_resellers($resellerIds);
        if ($managerIds) {
            [$managerWhere, $managerParams] = team_sql_in_condition('id', $managerIds, 'profile_manager');
            $stmt = db()->prepare('SELECT id, name FROM managers WHERE ' . $managerWhere . ' ORDER BY name, id');
            $stmt->execute($managerParams);
            foreach ($stmt->fetchAll() as $row) {
                $options[] = ['owner_type' => 'manager', 'owner_id' => (int)$row['id'], 'label' => app_text('auto.k_8d98911527e4') . ': ' . $row['name']];
            }
        }

        return $options;
    }

    foreach (db()->query('SELECT id, name FROM managers ORDER BY id ASC')->fetchAll() as $row) {
        $options[] = ['owner_type' => 'manager', 'owner_id' => (int)$row['id'], 'label' => app_text('auto.k_8d98911527e4') . ': ' . $row['name']];
    }
    foreach (db()->query('SELECT id, name FROM resellers ORDER BY id ASC')->fetchAll() as $row) {
        $options[] = ['owner_type' => 'reseller', 'owner_id' => (int)$row['id'], 'label' => app_text('auto.k_86469fea3a4a') . ': ' . $row['name']];
    }

    return $options;
}

function consultant_profile_payload(array $profile): array
{
    $itemsProfileId = consultant_effective_profile_id($profile);
    $profile = consultant_effective_profile($profile);
    $profileId = $itemsProfileId;
    $blocks = consultant_blocks($itemsProfileId);
    $ownerAdmin = $profile['owner_type'] === 'reseller'
        ? [
            'role' => 'reseller',
            'reseller_id' => (int)$profile['owner_id'],
            'manager_id' => null,
        ]
        : [
            'role' => 'manager',
            'manager_id' => (int)$profile['owner_id'],
            'reseller_id' => owned_content_manager_reseller_id((int)$profile['owner_id']),
        ];

    [$productWhere, $productParams] = owned_content_scope_condition('products', $ownerAdmin, 'p');
    $productWhere = $productWhere ? ' AND ' . preg_replace('/^WHERE\s+/i', '', $productWhere) : '';
    [$testWhere, $testParams] = owned_content_scope_condition('tests', $ownerAdmin, 't');
    $testWhere = $testWhere ? ' AND ' . preg_replace('/^WHERE\s+/i', '', $testWhere) : '';
    [$materialWhere, $materialParams] = owned_content_scope_condition('content', $ownerAdmin, 'c');
    $materialWhere = $materialWhere ? ' AND ' . preg_replace('/^WHERE\s+/i', '', $materialWhere) : '';

    $products = db()->prepare(
        'SELECT p.id, p.title, p.short_description, p.full_description, p.image_path, p.document_path, p.video_url, p.purchase_url, p.price, pp.sort_order
         FROM profile_products pp
         JOIN products p ON p.id = pp.product_id
         WHERE pp.profile_id = :profile_id AND p.is_active = 1' . $productWhere . '
         ORDER BY pp.sort_order, p.sort_order, p.id'
    );
    $products->execute(['profile_id' => $profileId] + $productParams);

    $tests = db()->prepare(
        'SELECT t.id, t.title, t.description, pt.sort_order
         FROM profile_tests pt
         JOIN tests t ON t.id = pt.test_id
         WHERE pt.profile_id = :profile_id AND t.is_active = 1' . $testWhere . '
         ORDER BY pt.sort_order, t.sort_order, t.id'
    );
    $tests->execute(['profile_id' => $profileId] + $testParams);

    $materials = db()->prepare(
        'SELECT c.id, c.title, c.short_text, c.full_text, c.image_path, c.video_url, c.attachment_path,
                c.content_type, c.section_type, c.button_text, c.button_url, pm.sort_order
         FROM profile_materials pm
         JOIN content_posts c ON c.id = pm.content_post_id
         WHERE pm.profile_id = :profile_id
           AND c.status = "published"
           ' . $materialWhere . '
         ORDER BY pm.sort_order, c.publish_at DESC, c.id DESC'
    );
    $materials->execute(['profile_id' => $profileId] + $materialParams);

    $reviews = db()->prepare(
        'SELECT client_name, client_photo_path, review_text, rating
         FROM profile_reviews
         WHERE profile_id = :profile_id AND is_active = 1
         ORDER BY sort_order, id'
    );
    $reviews->execute(['profile_id' => $profileId]);

    $cashbackCards = consultant_cashback_cards($profileId, $profile);
    $profile['cashback_cards'] = $cashbackCards;

    return [
        'profile' => $profile,
        'cashback_cards' => $cashbackCards,
        'blocks' => $blocks,
        'products' => $products->fetchAll(),
        'tests' => $tests->fetchAll(),
        'materials' => $materials->fetchAll(),
        'reviews' => $reviews->fetchAll(),
    ];
}
