<?php

require_once __DIR__ . '/team_tree.php';

function site_template_prefixed_column(string $column, string $alias = ''): string
{
    return ($alias !== '' ? $alias . '.' : '') . $column;
}

function site_template_append_where(string $where, string $condition): string
{
    return $where !== ''
        ? $where . ' AND ' . $condition
        : 'WHERE ' . $condition;
}

function site_template_admin_scope_condition(array $admin, string $alias = ''): array
{
    $ownerType = site_template_prefixed_column('owner_type', $alias);
    $ownerId = site_template_prefixed_column('owner_id', $alias);

    if (($admin['role'] ?? '') === 'superadmin') {
        return ['WHERE ' . $ownerType . ' IS NULL', []];
    }

    if (($admin['role'] ?? '') === 'reseller' && !empty($admin['reseller_id'])) {
        return [
            "WHERE {$ownerType} = 'reseller' AND {$ownerId} = :site_template_owner_id",
            ['site_template_owner_id' => (int)$admin['reseller_id']],
        ];
    }

    if (($admin['role'] ?? '') === 'manager' && !empty($admin['manager_id'])) {
        return [
            "WHERE {$ownerType} = 'manager' AND {$ownerId} = :site_template_owner_id",
            ['site_template_owner_id' => (int)$admin['manager_id']],
        ];
    }

    return ['WHERE 1=0', []];
}

function site_template_available_scope_condition(array $admin, string $alias = ''): array
{
    $ownerType = site_template_prefixed_column('owner_type', $alias);
    $ownerId = site_template_prefixed_column('owner_id', $alias);
    $conditions = [$ownerType . ' IS NULL'];
    $params = [];

    if (($admin['role'] ?? '') === 'superadmin') {
        return ['WHERE ' . $ownerType . ' IS NULL', []];
    }

    if (($admin['role'] ?? '') === 'reseller' && !empty($admin['reseller_id'])) {
        [$sql, $sqlParams] = team_sql_in_condition(
            $ownerId,
            team_reseller_ancestor_ids((int)$admin['reseller_id'], true),
            'site_tpl_reseller'
        );
        $conditions[] = "({$ownerType} = 'reseller' AND {$sql})";
        $params += $sqlParams;
    }

    if (($admin['role'] ?? '') === 'manager') {
        if (!empty($admin['reseller_id'])) {
            [$sql, $sqlParams] = team_sql_in_condition(
                $ownerId,
                team_reseller_ancestor_ids((int)$admin['reseller_id'], true),
                'site_tpl_manager_reseller'
            );
            $conditions[] = "({$ownerType} = 'reseller' AND {$sql})";
            $params += $sqlParams;
        }

        if (!empty($admin['manager_id'])) {
            $conditions[] = "({$ownerType} = 'manager' AND {$ownerId} = :site_tpl_manager_id)";
            $params['site_tpl_manager_id'] = (int)$admin['manager_id'];
        }
    }

    return ['WHERE (' . implode(' OR ', $conditions) . ')', $params];
}

function site_template_options(?array $admin = null): array
{
    try {
        $where = 'WHERE st.is_active = 1';
        $params = [];
        if ($admin) {
            [$where, $params] = site_template_available_scope_condition($admin, 'st');
            $where = site_template_append_where($where, 'st.is_active = 1');
        }

        $stmt = db()->prepare(
            "SELECT st.id,
                    CONCAT(
                        st.title,
                        CASE
                            WHEN st.owner_type = 'reseller' THEN ' (лидер)'
                            WHEN st.owner_type = 'manager' THEN ' (личный)'
                            ELSE ' (базовый)'
                        END
                    ) AS label,
                    st.description
             FROM site_templates st
             $where
             ORDER BY st.sort_order, st.id"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function site_template_default_id(?array $admin = null): ?int
{
    $where = 'WHERE st.is_active = 1';
    $params = [];
    if ($admin) {
        [$where, $params] = site_template_available_scope_condition($admin, 'st');
        $where = site_template_append_where($where, 'st.is_active = 1');
    }

    $stmt = db()->prepare(
        "SELECT st.id
         FROM site_templates st
         $where
         ORDER BY st.sort_order, st.id
         LIMIT 1"
    );
    $stmt->execute($params);
    $id = $stmt->fetchColumn();
    return $id !== false ? (int)$id : null;
}

function site_template_row(int $templateId, ?array $admin = null): ?array
{
    if ($templateId <= 0) {
        return null;
    }

    $where = 'WHERE st.id = :id AND st.is_active = 1';
    $params = ['id' => $templateId];
    if ($admin) {
        [$scopeWhere, $scopeParams] = site_template_available_scope_condition($admin, 'st');
        $where .= ' AND ' . preg_replace('/^WHERE\s+/i', '', $scopeWhere);
        $params += $scopeParams;
    }

    $stmt = db()->prepare("SELECT st.* FROM site_templates st $where LIMIT 1");
    $stmt->execute($params);
    $row = $stmt->fetch();

    return $row ?: null;
}

function site_template_default_profile_json(): string
{
    return json_encode([
        'title' => 'Ваш персональный консультант',
        'subtitle' => '{{role_label}} по здоровью и красоте',
        'short_description' => 'Пройдите бесплатный чек-ап и получите персональный разбор.',
        'welcome_text' => 'Добро пожаловать! Здесь можно пройти чек-ап организма, получить результат и отправить его мне для персонального разбора.',
        'cashback_title' => 'Кэшбэк и подарки',
        'cashback_text' => 'Оформите карту клиента, чтобы пользоваться персональными предложениями и подарками.',
        'cooperation_title' => 'Сотрудничество',
        'cooperation_text' => 'Если вам интересно развиваться вместе с командой, напишите мне. Расскажу простыми словами, с чего начать.',
        'theme_key' => 'ocean',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function site_template_default_blocks_json(): string
{
    return json_encode(site_template_editor_default_blocks_payload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function site_template_editor_theme_options(): array
{
    return [
        'classic' => 'Классический',
        'ocean' => 'Свежий синий',
        'berry' => 'Ягодный',
        'graphite' => 'Графитовый',
    ];
}

function site_template_editor_profile_defaults(): array
{
    return site_template_decode_json(site_template_default_profile_json());
}

function site_template_editor_profile(?string $json): array
{
    $profile = site_template_decode_json($json);
    return array_replace(site_template_editor_profile_defaults(), $profile);
}

function site_template_editor_block_defaults(): array
{
    return [
        'about' => [
            'title' => 'Обо мне',
            'is_enabled' => 1,
            'sort_order' => 10,
            'text' => 'Расскажите здесь о себе, опыте и подходе к клиентам.',
        ],
        'cashback' => [
            'title' => 'Кэшбэк и подарки',
            'is_enabled' => 1,
            'sort_order' => 20,
            'text' => 'Объясните, как клиенту оформить карту и получать предложения.',
        ],
        'programs' => [
            'title' => 'Программы и марафоны',
            'is_enabled' => 1,
            'sort_order' => 30,
            'text' => 'Добавьте свои программы, марафоны или подборки материалов.',
        ],
        'materials' => [
            'title' => 'Полезные материалы',
            'is_enabled' => 1,
            'sort_order' => 40,
            'text' => 'Здесь будут материалы, которые консультант показывает клиенту.',
        ],
        'products' => [
            'title' => 'Продукты',
            'is_enabled' => 1,
            'sort_order' => 50,
            'text' => 'Покажите подборки продуктов без автоматического назначения клиенту.',
        ],
        'cooperation' => [
            'title' => 'Сотрудничество',
            'is_enabled' => 1,
            'sort_order' => 60,
            'text' => 'Коротко расскажите о возможности сотрудничества и команде.',
        ],
        'reviews' => [
            'title' => 'Отзывы и результаты',
            'is_enabled' => 0,
            'sort_order' => 70,
            'text' => 'Добавьте истории, отзывы или результаты использования продукции.',
        ],
        'contacts' => [
            'title' => 'Связаться',
            'is_enabled' => 1,
            'sort_order' => 80,
            'text' => 'Блок с контактами консультанта и кнопкой связи.',
        ],
    ];
}

function site_template_editor_default_blocks_payload(): array
{
    $blocks = [];
    foreach (site_template_editor_block_defaults() as $type => $block) {
        $blocks[] = [
            'block_type' => $type,
            'title' => $block['title'],
            'is_enabled' => (int)$block['is_enabled'],
            'sort_order' => (int)$block['sort_order'],
            'settings' => [
                'text' => $block['text'],
            ],
        ];
    }

    return $blocks;
}

function site_template_editor_blocks(?string $json): array
{
    $blocks = site_template_editor_block_defaults();
    $decoded = site_template_decode_json($json);

    foreach ($decoded as $block) {
        if (!is_array($block) || empty($block['block_type'])) {
            continue;
        }

        $type = (string)$block['block_type'];
        $settings = is_array($block['settings'] ?? null) ? $block['settings'] : [];
        $blocks[$type] = array_replace($blocks[$type] ?? [
            'title' => $type,
            'is_enabled' => 0,
            'sort_order' => 100,
            'text' => '',
        ], [
            'title' => trim((string)($block['title'] ?? ($blocks[$type]['title'] ?? $type))),
            'is_enabled' => !empty($block['is_enabled']) || !empty($block['enabled']) ? 1 : 0,
            'sort_order' => (int)($block['sort_order'] ?? ($blocks[$type]['sort_order'] ?? 100)),
            'text' => trim((string)($settings['text'] ?? ($blocks[$type]['text'] ?? ''))),
        ]);
    }

    uasort($blocks, static fn(array $a, array $b): int => ((int)$a['sort_order'] <=> (int)$b['sort_order']));

    return $blocks;
}

function site_template_apply_editor_payload(array $payload, array $post): array
{
    if (!isset($post['template_profile']) && !isset($post['template_blocks'])) {
        return $payload;
    }

    $profileInput = is_array($post['template_profile'] ?? null) ? $post['template_profile'] : [];
    $profile = site_template_editor_profile((string)($payload['profile_json'] ?? ''));
    $profileKeys = [
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
        'phone',
        'email',
        'telegram_url',
        'whatsapp_url',
        'vk_url',
        'ok_url',
        'theme_key',
    ];

    foreach ($profileKeys as $key) {
        if (!array_key_exists($key, $profileInput)) {
            continue;
        }
        $profile[$key] = trim((string)$profileInput[$key]);
    }

    if (!array_key_exists($profile['theme_key'] ?? '', site_template_editor_theme_options())) {
        $profile['theme_key'] = 'classic';
    }

    $blockInput = is_array($post['template_blocks'] ?? null) ? $post['template_blocks'] : [];
    $blocks = [];
    foreach (site_template_editor_blocks((string)($payload['blocks_json'] ?? '')) as $type => $block) {
        $incoming = is_array($blockInput[$type] ?? null) ? $blockInput[$type] : [];
        $title = trim((string)($incoming['title'] ?? $block['title']));
        $text = trim((string)($incoming['text'] ?? $block['text']));
        $sortOrder = (int)($incoming['sort_order'] ?? $block['sort_order']);

        $blocks[] = [
            'block_type' => $type,
            'title' => $title !== '' ? $title : $type,
            'is_enabled' => isset($incoming['is_enabled']) ? 1 : 0,
            'sort_order' => $sortOrder > 0 ? $sortOrder : 100,
            'settings' => [
                'text' => $text,
            ],
        ];
    }

    usort($blocks, static fn(array $a, array $b): int => ((int)$a['sort_order'] <=> (int)$b['sort_order']));

    $profileJson = json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $blocksJson = json_encode($blocks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $payload['profile_json'] = $profileJson !== false ? $profileJson : site_template_default_profile_json();
    $payload['blocks_json'] = $blocksJson !== false ? $blocksJson : site_template_default_blocks_json();

    return $payload;
}

function render_site_template_editor_field(
    string $name,
    string $label,
    mixed $value,
    string $type = 'text',
    string $hint = '',
    array $options = []
): void {
    ?>
    <label class="field">
        <span><?= h($label) ?></span>
        <?php if ($type === 'textarea'): ?>
            <textarea name="<?= h($name) ?>" rows="<?= max(3, (int)($options['rows'] ?? 4)) ?>"><?= h((string)$value) ?></textarea>
        <?php elseif ($type === 'select'): ?>
            <select name="<?= h($name) ?>">
                <?php foreach (($options['choices'] ?? []) as $optionValue => $optionLabel): ?>
                    <option value="<?= h((string)$optionValue) ?>" <?= (string)$value === (string)$optionValue ? 'selected' : '' ?>><?= h((string)$optionLabel) ?></option>
                <?php endforeach; ?>
            </select>
        <?php else: ?>
            <input type="<?= h($type) ?>" name="<?= h($name) ?>" value="<?= h((string)$value) ?>">
        <?php endif; ?>
        <?php if ($hint !== ''): ?>
            <small class="field-hint"><?= h($hint) ?></small>
        <?php endif; ?>
    </label>
    <?php
}

function render_site_template_editor(?array $row = null): void
{
    $profile = site_template_editor_profile((string)($row['profile_json'] ?? ''));
    $blocks = site_template_editor_blocks((string)($row['blocks_json'] ?? ''));
    ?>
    <section class="site-template-editor">
        <div class="site-template-editor-head">
            <div>
                <span class="section-eyebrow">Редактор страницы</span>
                <h3>Содержание шаблона</h3>
            </div>
            <p>Эти поля попадут в мини-сайт при применении шаблона. Можно использовать <code>{{name}}</code>, <code>{{role_label}}</code>, <code>{{phone}}</code>, <code>{{email}}</code>.</p>
        </div>

        <div class="site-template-section">
            <h4>Первый экран</h4>
            <div class="site-template-grid two">
                <?php render_site_template_editor_field('template_profile[title]', 'Заголовок', $profile['title'] ?? ''); ?>
                <?php render_site_template_editor_field('template_profile[subtitle]', 'Подзаголовок', $profile['subtitle'] ?? ''); ?>
                <?php render_site_template_editor_field('template_profile[short_description]', 'Краткое описание', $profile['short_description'] ?? '', 'textarea', '', ['rows' => 3]); ?>
                <?php render_site_template_editor_field('template_profile[welcome_text]', 'Приветственный текст', $profile['welcome_text'] ?? '', 'textarea', '', ['rows' => 3]); ?>
                <?php render_site_template_editor_field('template_profile[welcome_image_path]', 'Картинка первого экрана', $profile['welcome_image_path'] ?? '', 'text', 'Путь или ссылка. Личные фото владельца при применении не затираются.'); ?>
                <?php render_site_template_editor_field('template_profile[welcome_video_url]', 'Видео первого экрана', $profile['welcome_video_url'] ?? '', 'text', 'YouTube/VK/другая ссылка, если нужна.'); ?>
                <?php render_site_template_editor_field('template_profile[theme_key]', 'Оформление', $profile['theme_key'] ?? 'classic', 'select', '', ['choices' => site_template_editor_theme_options()]); ?>
            </div>
        </div>

        <div class="site-template-section">
            <h4>Кэшбэк и сотрудничество</h4>
            <div class="site-template-grid two">
                <?php render_site_template_editor_field('template_profile[cashback_title]', 'Заголовок кэшбэка', $profile['cashback_title'] ?? ''); ?>
                <?php render_site_template_editor_field('template_profile[cashback_url]', 'Ссылка кэшбэка', $profile['cashback_url'] ?? ''); ?>
                <?php render_site_template_editor_field('template_profile[cashback_text]', 'Текст кэшбэка', $profile['cashback_text'] ?? '', 'textarea', '', ['rows' => 3]); ?>
                <?php render_site_template_editor_field('template_profile[cashback_image_path]', 'Картинка кэшбэка', $profile['cashback_image_path'] ?? ''); ?>
                <?php render_site_template_editor_field('template_profile[cooperation_title]', 'Заголовок сотрудничества', $profile['cooperation_title'] ?? ''); ?>
                <?php render_site_template_editor_field('template_profile[cooperation_video_url]', 'Видео сотрудничества', $profile['cooperation_video_url'] ?? ''); ?>
                <?php render_site_template_editor_field('template_profile[cooperation_text]', 'Текст сотрудничества', $profile['cooperation_text'] ?? '', 'textarea', '', ['rows' => 3]); ?>
                <?php render_site_template_editor_field('template_profile[cooperation_image_path]', 'Картинка сотрудничества', $profile['cooperation_image_path'] ?? ''); ?>
            </div>
        </div>

        <div class="site-template-section">
            <h4>Контакты по умолчанию</h4>
            <div class="site-template-grid three">
                <?php render_site_template_editor_field('template_profile[phone]', 'Телефон', $profile['phone'] ?? ''); ?>
                <?php render_site_template_editor_field('template_profile[email]', 'Email', $profile['email'] ?? ''); ?>
                <?php render_site_template_editor_field('template_profile[telegram_url]', 'Telegram', $profile['telegram_url'] ?? ''); ?>
                <?php render_site_template_editor_field('template_profile[whatsapp_url]', 'WhatsApp', $profile['whatsapp_url'] ?? ''); ?>
                <?php render_site_template_editor_field('template_profile[vk_url]', 'VK', $profile['vk_url'] ?? ''); ?>
                <?php render_site_template_editor_field('template_profile[ok_url]', 'OK', $profile['ok_url'] ?? ''); ?>
            </div>
        </div>

        <div class="site-template-section">
            <h4>Блоки мини-сайта</h4>
            <div class="site-template-block-list">
                <?php foreach ($blocks as $type => $block): ?>
                    <div class="site-template-block-row">
                        <label class="checkbox-line">
                            <input type="checkbox" name="template_blocks[<?= h($type) ?>][is_enabled]" value="1" <?= !empty($block['is_enabled']) ? 'checked' : '' ?>>
                            <span><?= h($block['title']) ?></span>
                        </label>
                        <input type="text" name="template_blocks[<?= h($type) ?>][title]" value="<?= h((string)$block['title']) ?>" aria-label="Название блока">
                        <input type="number" name="template_blocks[<?= h($type) ?>][sort_order]" value="<?= h((string)$block['sort_order']) ?>" min="1" aria-label="Сортировка">
                        <textarea name="template_blocks[<?= h($type) ?>][text]" rows="2" aria-label="Текст блока"><?= h((string)$block['text']) ?></textarea>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php
}

function site_template_slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/\s+/', '-', $value) ?? '';
    $value = preg_replace('/[^a-z0-9_-]/', '', $value) ?? '';
    $value = preg_replace('/[-_]{2,}/', '-', $value) ?? '';
    return trim($value, '-_');
}

function site_template_pretty_json(mixed $value, string $fallback): string
{
    if (is_array($value)) {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $fallback;
    }

    $raw = trim((string)$value);
    if ($raw === '') {
        return $fallback;
    }

    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return $raw;
    }

    return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $raw;
}

function site_template_normalize_payload(array $payload): array
{
    if (array_key_exists('slug', $payload)) {
        $payload['slug'] = site_template_slugify((string)$payload['slug']);
    }
    if (($payload['slug'] ?? '') === '' && !empty($payload['title'])) {
        $payload['slug'] = site_template_slugify((string)$payload['title']);
    }
    if (array_key_exists('profile_json', $payload)) {
        $payload['profile_json'] = site_template_pretty_json($payload['profile_json'], site_template_default_profile_json());
    }
    if (array_key_exists('blocks_json', $payload)) {
        $payload['blocks_json'] = site_template_pretty_json($payload['blocks_json'], site_template_default_blocks_json());
    }

    return $payload;
}

function site_template_validate_payload(array $payload): array
{
    $errors = [];
    $slug = (string)($payload['slug'] ?? '');
    if ($slug === '' || !preg_match('/^[a-z0-9_-]{3,100}$/', $slug)) {
        $errors[] = 'Код шаблона должен содержать 3-100 символов: латиница, цифры, дефис или подчёркивание.';
    }

    foreach (['profile_json' => 'Настройки первого экрана', 'blocks_json' => 'Блоки страницы'] as $field => $label) {
        $raw = trim((string)($payload[$field] ?? ''));
        if ($raw === '' && $field === 'blocks_json') {
            continue;
        }
        json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errors[] = $label . ': исправьте JSON. ' . json_last_error_msg();
        }
    }

    return $errors;
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
