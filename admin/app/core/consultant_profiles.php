<?php

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

    $manager = db()->prepare('SELECT id FROM managers WHERE referral_code = :code AND is_active = 1 LIMIT 1');
    $manager->execute(['code' => $referralCode]);
    $managerRow = $manager->fetch();
    if ($managerRow) {
        return ensure_consultant_profile('manager', (int)$managerRow['id']);
    }

    $reseller = db()->prepare('SELECT id FROM resellers WHERE referral_code = :code AND is_active = 1 LIMIT 1');
    $reseller->execute(['code' => $referralCode]);
    $resellerRow = $reseller->fetch();
    if ($resellerRow) {
        return ensure_consultant_profile('reseller', (int)$resellerRow['id']);
    }

    return null;
}

function ensure_consultant_profile(string $ownerType, int $ownerId): array
{
    $stmt = db()->prepare('SELECT * FROM consultant_profiles WHERE owner_type = :owner_type AND owner_id = :owner_id LIMIT 1');
    $stmt->execute(['owner_type' => $ownerType, 'owner_id' => $ownerId]);
    $profile = $stmt->fetch();
    if ($profile) {
        ensure_consultant_blocks((int)$profile['id']);
        return $profile;
    }

    $owner = consultant_owner_row($ownerType, $ownerId);
    $name = $owner['name'] ?? ($ownerType . '-' . $ownerId);
    $slug = consultant_unique_slug(consultant_slug($name, $ownerType . '-' . $ownerId));

    $insert = db()->prepare(
        'INSERT INTO consultant_profiles
            (owner_type, owner_id, slug, display_name, title, subtitle, phone, email, is_public)
         VALUES
            (:owner_type, :owner_id, :slug, :display_name, :title, :subtitle, :phone, :email, 1)'
    );
    $insert->execute([
        'owner_type' => $ownerType,
        'owner_id' => $ownerId,
        'slug' => $slug,
        'display_name' => $name,
        'title' => $ownerType === 'reseller' ? app_text('consultant_profile.default_reseller_title') : app_text('consultant_profile.default_manager_title'),
        'subtitle' => app_text('consultant_profile.default_subtitle'),
        'phone' => $owner['phone'] ?? null,
        'email' => $owner['email'] ?? null,
    ]);

    $profileId = (int)db()->lastInsertId();
    ensure_consultant_blocks($profileId);

    $stmt->execute(['owner_type' => $ownerType, 'owner_id' => $ownerId]);
    return $stmt->fetch();
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
        'tests' => [app_text('consultant_profile.block_tests'), 40],
        'products' => [app_text('consultant_profile.block_products'), 50],
        'materials' => [app_text('consultant_profile.block_materials'), 60],
        'reviews' => [app_text('consultant_profile.block_reviews'), 70],
        'contacts' => [app_text('consultant_profile.block_contacts'), 80],
    ];
}

function ensure_consultant_blocks(int $profileId): void
{
    $stmt = db()->prepare(
        'INSERT INTO profile_blocks (profile_id, block_type, title, is_enabled, sort_order)
         VALUES (:profile_id, :block_type, :title, 1, :sort_order)
         ON DUPLICATE KEY UPDATE title = COALESCE(title, VALUES(title))'
    );
    foreach (default_consultant_blocks() as $blockType => [$title, $sortOrder]) {
        $stmt->execute([
            'profile_id' => $profileId,
            'block_type' => $blockType,
            'title' => $title,
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

function consultant_profile_upload_dir(): string
{
    return dirname(__DIR__, 2) . '/uploads/profiles';
}

function consultant_profile_public_path(string $filename): string
{
    return '/admin/uploads/profiles/' . $filename;
}

function consultant_profile_upload(string $field, ?string $currentPath, array &$errors): ?string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $currentPath;
    }

    $file = $_FILES[$field];
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

function consultant_options_for_admin(array $admin): array
{
    if ($admin['role'] !== 'superadmin') {
        return [];
    }

    $options = [];
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
    $profileId = (int)$profile['id'];
    $blocks = consultant_blocks($profileId);

    $products = db()->prepare(
        'SELECT p.id, p.title, p.short_description, p.full_description, p.image_path, p.document_path, p.video_url, p.purchase_url, p.price, pp.sort_order
         FROM profile_products pp
         JOIN products p ON p.id = pp.product_id
         WHERE pp.profile_id = :profile_id AND p.is_active = 1
         ORDER BY pp.sort_order, p.sort_order, p.id'
    );
    $products->execute(['profile_id' => $profileId]);

    $tests = db()->prepare(
        'SELECT t.id, t.title, t.description, pt.sort_order
         FROM profile_tests pt
         JOIN tests t ON t.id = pt.test_id
         WHERE pt.profile_id = :profile_id AND t.is_active = 1
         ORDER BY pt.sort_order, t.sort_order, t.id'
    );
    $tests->execute(['profile_id' => $profileId]);

    $materials = db()->prepare(
        'SELECT c.id, c.title, c.short_text, c.full_text, c.image_path, c.video_url, c.attachment_path, c.content_type, pm.sort_order
         FROM profile_materials pm
         JOIN content_posts c ON c.id = pm.content_post_id
         WHERE pm.profile_id = :profile_id AND c.status = "published"
         ORDER BY pm.sort_order, c.publish_at DESC, c.id DESC'
    );
    $materials->execute(['profile_id' => $profileId]);

    $reviews = db()->prepare(
        'SELECT client_name, client_photo_path, review_text, rating
         FROM profile_reviews
         WHERE profile_id = :profile_id AND is_active = 1
         ORDER BY sort_order, id'
    );
    $reviews->execute(['profile_id' => $profileId]);

    return [
        'profile' => $profile,
        'blocks' => $blocks,
        'products' => $products->fetchAll(),
        'tests' => $tests->fetchAll(),
        'materials' => $materials->fetchAll(),
        'reviews' => $reviews->fetchAll(),
    ];
}
