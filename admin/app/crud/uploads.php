<?php

function public_upload_path(string $moduleKey, string $filename): string
{
    $folder = match ($moduleKey) {
        'products' => 'products',
        'broadcasts' => 'broadcasts',
        'content' => 'content',
        'leads' => 'responses',
        default => 'files',
    };

    return '/admin/uploads/' . $folder . '/' . $filename;
}

function upload_directory(string $moduleKey): string
{
    $folder = match ($moduleKey) {
        'products' => 'products',
        'broadcasts' => 'broadcasts',
        'content' => 'content',
        'leads' => 'responses',
        default => 'files',
    };

    return dirname(__DIR__) . '/uploads/' . $folder;
}

function apply_file_uploads(string $moduleKey, array $fields, array $payload, array &$errors): array
{
    $config = app_config();
    $allowedImageTypes = $config['security']['allowed_image_types'] ?? ['image/jpeg', 'image/png', 'image/webp'];
    $allowedAttachmentTypes = $config['security']['allowed_attachment_types'] ?? [
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/pdf',
        'video/mp4',
    ];
    $maxBytes = (int)($config['security']['upload_max_bytes'] ?? 5242880);

    foreach ($fields as $name => $field) {
        if (($field['type'] ?? 'text') !== 'file') {
            continue;
        }

        $file = $_FILES[$name] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = app_text('auto.k_ad245cc4b64e') . ($field['label'] ?? $name);
            continue;
        }

        if ($maxBytes > 0 && (int)$file['size'] > $maxBytes) {
            $errors[] = app_text('auto.k_016932bbc64e') . round($maxBytes / 1024 / 1024, 1) . app_text('auto.k_e9f54a42c9f8');
            continue;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $accept = (string)($field['accept'] ?? 'image/*');
        $allowedTypes = $accept === 'image/*' ? $allowedImageTypes : $allowedAttachmentTypes;
        if (!in_array($mime, $allowedTypes, true)) {
            $errors[] = $accept === 'image/*'
                ? app_text('auto.k_9b79f0e123f2')
                : app_text('auto.k_56dab6d101ae');
            continue;
        }

        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            'video/mp4' => 'mp4',
            default => null,
        };
        if (!$extension) {
            $errors[] = app_text('auto.k_0d13c589d224');
            continue;
        }

        $directory = upload_directory($moduleKey);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            $errors[] = app_text('auto.k_2365f1af5b59');
            continue;
        }

        $filename = date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
        $target = $directory . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            $errors[] = app_text('auto.k_efb84954029f');
            continue;
        }

        $payload[$name] = public_upload_path($moduleKey, $filename);
    }

    return $payload;
}
