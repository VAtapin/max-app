<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/permissions.php';
require_once __DIR__ . '/../app/core/crud_views.php';
require_once __DIR__ . '/../app/core/lead_responses.php';
require_once __DIR__ . '/../app/core/test_admin.php';
require_once __DIR__ . '/../app/core/broadcast_runner.php';
require_once __DIR__ . '/../app/core/client_journey.php';
require_once __DIR__ . '/../app/core/content_ownership.php';
require_once __DIR__ . '/../app/core/integration_guides.php';
require_once __DIR__ . '/../app/core/site_templates.php';
require_once __DIR__ . '/../app/core/consultant_profiles.php';
require_once __DIR__ . '/../app/core/subscription_plans.php';

$admin = require_auth();

function integration_callback_secret(): string
{
    try {
        $random = bin2hex(random_bytes(12));
    } catch (Throwable) {
        $random = str_replace('.', '', uniqid('', true));
    }

    return 'swpro_vk_' . $random;
}

$modules = [
    'resellers' => [
        'title' => app_text('auto.k_32cea47742bf'),
        'table' => 'resellers',
        'columns' => ['id', 'parent_reseller_id', 'subscription_plan_id', 'name', 'email', 'phone', 'billing_name', 'billing_inn', 'billing_email', 'billing_comment', 'referral_code', 'manager_limit', 'direct_leader_limit', 'branch_leader_limit', 'direct_manager_limit', 'branch_manager_limit', 'per_child_manager_limit', 'price_per_leader', 'price_per_consultant', 'is_active'],
        'fields' => [
            'parent_reseller_id' => ['label' => 'Вышестоящий лидер', 'type' => 'select', 'source' => 'resellers', 'nullable' => true],
            'template_id' => [
                'label' => 'Шаблон мини-сайта',
                'type' => 'select',
                'source' => 'site_templates',
                'nullable' => true,
                'nullable_label' => 'Как у вышестоящего лидера',
                'virtual' => true,
                'hint' => 'Этот пункт применяет мини-сайт вышестоящего лидера. Выберите шаблон только если нужна отдельная стартовая страница.',
            ],
            'name' => ['label' => app_text('auto.k_3de49828e86a'), 'required' => true],
            'email' => ['label' => 'Email', 'type' => 'email'],
            'phone' => ['label' => app_text('auto.k_87ec4b495b56')],
            'billing_name' => ['label' => 'Плательщик / юр. лицо'],
            'billing_inn' => ['label' => 'ИНН плательщика'],
            'billing_email' => ['label' => 'Email для счетов', 'type' => 'email'],
            'billing_comment' => ['label' => 'Комментарий для оплаты', 'type' => 'textarea'],
            'subscription_plan_id' => [
                'label' => 'Подписка',
                'type' => 'select',
                'source' => 'subscription_plans',
                'nullable' => true,
                'nullable_label' => 'Нет подписки',
                'hint' => 'Лимиты и стоимость берутся из выбранной подписки. Тарифы настраиваются в разделе «Подписка».',
            ],
            'referral_code' => ['label' => app_text('auto.k_a9d3a61b02f2'), 'required' => true],
            'is_active' => ['label' => app_text('auto.k_667904ef22a4'), 'type' => 'checkbox', 'default' => 1],
        ],
    ],
    'managers' => [
        'title' => app_text('auto.k_6756aa53b5b5'),
        'table' => 'managers',
        'columns' => ['id', 'reseller_id', 'name', 'email', 'phone', 'referral_code', 'is_active'],
        'fields' => [
            'reseller_id' => ['label' => app_text('auto.k_86469fea3a4a'), 'type' => 'select', 'source' => 'resellers', 'nullable' => true],
            'template_id' => [
                'label' => 'Шаблон мини-сайта',
                'type' => 'select',
                'source' => 'site_templates',
                'nullable' => true,
                'nullable_label' => 'Как у лидера',
                'virtual' => true,
                'hint' => 'Этот пункт применяет мини-сайт выбранного лидера. Выберите шаблон только если нужна отдельная стартовая страница.',
            ],
            'name' => ['label' => app_text('auto.k_aee78fe86022'), 'required' => true],
            'email' => ['label' => 'Email', 'type' => 'email'],
            'phone' => ['label' => app_text('auto.k_87ec4b495b56')],
            'telegram_id' => ['label' => 'Telegram ID'],
            'max_id' => ['label' => 'MAX ID'],
            'vk_id' => ['label' => 'VK ID'],
            'referral_code' => ['label' => app_text('auto.k_a9d3a61b02f2'), 'required' => true],
            'is_active' => ['label' => app_text('auto.k_667904ef22a4'), 'type' => 'checkbox', 'default' => 1],
        ],
    ],
    'users' => [
        'title' => 'Клиенты',
        'table' => 'end_users',
        'columns' => ['id', 'platform', 'platform_user_id', 'username', 'reseller_id', 'manager_id', 'status'],
        'fields' => [
            'reseller_id' => ['label' => app_text('auto.k_86469fea3a4a'), 'type' => 'select', 'source' => 'resellers', 'nullable' => true],
            'manager_id' => ['label' => app_text('auto.k_8d98911527e4'), 'type' => 'select', 'source' => 'managers', 'nullable' => true],
            'platform' => ['label' => app_text('auto.k_89009febe5c6'), 'type' => 'select', 'options' => ['telegram', 'VK', 'OK', 'MAX', 'web'], 'required' => true],
            'platform_user_id' => ['label' => app_text('auto.k_c7f40b63aad7'), 'required' => true],
            'username' => ['label' => 'Username'],
            'first_name' => ['label' => app_text('auto.k_aee78fe86022')],
            'last_name' => ['label' => app_text('auto.k_5aa7f892d573')],
            'gender' => ['label' => 'Пол', 'type' => 'select', 'options' => ['female', 'male', 'prefer_not_to_say'], 'nullable' => true],
            'birth_date' => ['label' => 'Дата рождения', 'type' => 'date', 'nullable' => true],
            'age_years' => ['label' => 'Возраст', 'type' => 'number', 'nullable' => true],
            'city' => ['label' => 'Город'],
            'timezone' => ['label' => 'Часовой пояс'],
            'phone' => ['label' => app_text('auto.k_87ec4b495b56')],
            'email' => ['label' => 'Email', 'type' => 'email'],
            'referral_code_used' => ['label' => app_text('auto.k_23f8d055a5d6')],
            'client_stage' => ['label' => 'Этап клиента', 'type' => 'select', 'options' => array_keys(client_stage_labels()), 'required' => true],
            'notifications_enabled' => ['label' => 'Разрешены сообщения', 'type' => 'checkbox', 'default' => 1],
            'status' => ['label' => app_text('auto.k_f7f293b5c58c'), 'type' => 'select', 'options' => ['active', 'blocked', 'unsubscribed'], 'required' => true],
        ],
    ],
    'platform_accounts' => [
        'title' => app_text('auto.k_68a410fd6049'),
        'table' => 'platform_accounts',
        'columns' => ['id', 'end_user_id', 'platform', 'platform_user_id', 'username'],
        'fields' => [
            'end_user_id' => ['label' => app_text('auto.k_51aff1853949'), 'type' => 'select', 'source' => 'end_users', 'required' => true],
            'platform' => ['label' => app_text('auto.k_89009febe5c6'), 'type' => 'select', 'options' => ['telegram', 'VK', 'OK', 'MAX', 'web'], 'required' => true],
            'platform_user_id' => ['label' => app_text('auto.k_c7f40b63aad7'), 'required' => true],
            'username' => ['label' => 'Username'],
        ],
    ],
    'leads' => [
        'title' => app_text('auto.k_be11d71726a6'),
        'table' => 'leads',
        'columns' => ['id', 'end_user_id', 'manager_id', 'reseller_id', 'product_id', 'request_type', 'source_platform', 'status', 'created_at'],
        'fields' => [
            'end_user_id' => ['label' => app_text('auto.k_51aff1853949'), 'type' => 'select', 'source' => 'end_users', 'required' => true],
            'manager_id' => ['label' => app_text('auto.k_8d98911527e4'), 'type' => 'select', 'source' => 'managers', 'nullable' => true],
            'reseller_id' => ['label' => app_text('auto.k_86469fea3a4a'), 'type' => 'select', 'source' => 'resellers', 'nullable' => true],
            'product_id' => ['label' => app_text('auto.k_82a9ca014bb8'), 'type' => 'select', 'source' => 'products', 'nullable' => true],
            'request_type' => [
                'label' => 'Тип обращения',
                'type' => 'select',
                'options' => ['consultation', 'product', 'test_result', 'cashback', 'cooperation', 'other'],
                'required' => true,
            ],
            'source_platform' => ['label' => app_text('auto.k_89009febe5c6'), 'type' => 'select', 'options' => ['telegram', 'VK', 'OK', 'MAX', 'web'], 'required' => true],
            'message' => ['label' => app_text('auto.k_dc72346ac447'), 'type' => 'textarea'],
            'status' => ['label' => app_text('auto.k_f7f293b5c58c'), 'type' => 'select', 'options' => ['new', 'contacted', 'interested', 'closed', 'lost'], 'required' => true],
        ],
    ],
    'categories' => [
        'title' => app_text('auto.k_f7d9b1c868fa'),
        'table' => 'product_categories',
        'columns' => ['id', 'title', 'slug', 'sort_order', 'is_active'],
        'fields' => [
            'title' => ['label' => app_text('auto.k_3de49828e86a'), 'required' => true],
            'slug' => ['label' => 'Slug', 'required' => true],
            'description' => ['label' => app_text('auto.k_f5441f6aee76'), 'type' => 'textarea'],
            'sort_order' => ['label' => app_text('auto.k_ed030118aad8'), 'type' => 'number', 'default' => 100],
            'is_active' => ['label' => app_text('auto.k_c1ae516375c4'), 'type' => 'checkbox', 'default' => 1],
        ],
    ],
    'products' => [
        'title' => app_text('auto.k_c85756a1ae45'),
        'table' => 'products',
        'columns' => ['id', 'category_id', 'title', 'slug', 'price', 'is_active'],
        'fields' => [
            'category_id' => ['label' => app_text('auto.k_1cf49d95b0ed'), 'type' => 'select', 'source' => 'product_categories', 'nullable' => true],
            'title' => ['label' => app_text('auto.k_3de49828e86a'), 'required' => true],
            'slug' => ['label' => 'Slug', 'required' => true],
            'short_description' => ['label' => app_text('auto.k_d1b43352dd0b'), 'type' => 'textarea'],
            'full_description' => ['label' => app_text('auto.k_a6c29f1af453'), 'type' => 'textarea'],
            'composition' => ['label' => app_text('auto.k_c37407200657'), 'type' => 'textarea'],
            'usage_text' => ['label' => app_text('auto.k_1f14ddbb7157'), 'type' => 'textarea'],
            'warning_text' => ['label' => app_text('auto.k_e48b13edc15f'), 'type' => 'textarea'],
            'contraindications' => ['label' => app_text('auto.k_b4307011a15a'), 'type' => 'textarea'],
            'image_path' => ['label' => app_text('auto.k_56a1fd52891d'), 'type' => 'file', 'accept' => 'image/*'],
            'document_path' => ['label' => app_text('auto.k_2f76dff0da9f'), 'type' => 'file', 'accept' => 'application/pdf'],
            'video_url' => ['label' => app_text('auto.k_54fbfaf96a2d')],
            'price' => ['label' => app_text('auto.k_367e2792c179'), 'type' => 'number', 'step' => '0.01', 'nullable' => true],
            'purchase_url' => ['label' => app_text('auto.k_ab281ec27935')],
            'sort_order' => ['label' => app_text('auto.k_ed030118aad8'), 'type' => 'number', 'default' => 100],
            'is_active' => ['label' => app_text('auto.k_667904ef22a4'), 'type' => 'checkbox', 'default' => 1],
        ],
    ],
    'tests' => [
        'title' => app_text('auto.k_663c94d30018'),
        'table' => 'tests',
        'columns' => ['id', 'title', 'category_id', 'scoring_type', 'is_active', 'sort_order'],
        'fields' => [
            'title' => ['label' => app_text('auto.k_3de49828e86a'), 'required' => true],
            'description' => ['label' => app_text('auto.k_f5441f6aee76'), 'type' => 'textarea'],
            'category_id' => ['label' => app_text('auto.k_19c85838e63f'), 'type' => 'select', 'source' => 'product_categories', 'nullable' => true],
            'scoring_type' => ['label' => 'Тип теста', 'type' => 'select', 'options' => ['single', 'multiscale'], 'required' => true],
            'emoji' => ['label' => 'Emoji'],
            'intro_text' => ['label' => 'Текст перед стартом', 'type' => 'textarea'],
            'intro_image_path' => ['label' => 'Картинка intro', 'type' => 'file', 'accept' => 'image/*'],
            'intro_video_url' => ['label' => 'Видео intro'],
            'sort_order' => ['label' => app_text('auto.k_ed030118aad8'), 'type' => 'number', 'default' => 100],
            'is_active' => ['label' => app_text('auto.k_667904ef22a4'), 'type' => 'checkbox', 'default' => 1],
        ],
    ],
    'broadcasts' => [
        'title' => app_text('auto.k_08a679f215bd'),
        'table' => 'broadcasts',
        'columns' => ['id', 'title', 'platform', 'target_type', 'scheduled_at', 'status'],
        'fields' => [
            'title' => ['label' => app_text('auto.k_3de49828e86a'), 'required' => true],
            'message_text' => ['label' => app_text('auto.k_1ba376a71bcf'), 'type' => 'textarea'],
            'audience_type' => ['label' => 'Получатели', 'type' => 'select', 'options' => ['clients', 'consultants'], 'required' => true],
            'image_path' => ['label' => app_text('auto.k_56a1fd52891d'), 'type' => 'file', 'accept' => 'image/*'],
            'video_path' => ['label' => 'Видео MP4', 'type' => 'file', 'accept' => 'video/mp4'],
            'button_text' => ['label' => app_text('auto.k_f9fd27363780')],
            'button_url' => ['label' => app_text('auto.k_668acad1ed4c')],
            'target_type' => ['label' => app_text('auto.k_e9476ab1820b'), 'type' => 'select', 'options' => ['all', 'reseller', 'manager', 'segment', 'own_clients', 'branch_clients', 'direct_consultants', 'branch_consultants', 'direct_leaders', 'branch_leaders', 'whole_branch'], 'required' => true],
            'target_reseller_id' => ['label' => app_text('auto.k_86469fea3a4a'), 'type' => 'select', 'source' => 'resellers', 'nullable' => true],
            'target_manager_id' => ['label' => app_text('auto.k_8d98911527e4'), 'type' => 'select', 'source' => 'managers', 'nullable' => true],
            'segment_stage' => ['label' => 'Сегмент: этап клиента', 'type' => 'select', 'options' => array_keys(client_stage_labels()), 'nullable' => true],
            'segment_checkup' => ['label' => 'Сегмент: чек-ап', 'type' => 'select', 'options' => ['not_started', 'started', 'completed'], 'nullable' => true],
            'segment_activity' => ['label' => 'Сегмент: активность', 'type' => 'select', 'options' => ['active_7', 'active_30', 'inactive_14', 'inactive_30'], 'nullable' => true],
            'platform' => ['label' => app_text('auto.k_89009febe5c6'), 'type' => 'select', 'options' => ['all', 'telegram', 'VK', 'OK', 'MAX'], 'required' => true],
            'schedule_type' => ['label' => app_text('auto.k_f04bd0a06491'), 'type' => 'select', 'options' => ['once', 'daily', 'weekly', 'monthly'], 'required' => true],
            'scheduled_at' => ['label' => app_text('auto.k_854ba1dc86aa'), 'type' => 'datetime-local', 'nullable' => true],
            'status' => ['label' => app_text('auto.k_f7f293b5c58c'), 'type' => 'select', 'options' => ['draft', 'scheduled', 'sent', 'cancelled'], 'required' => true],
        ],
    ],
    'content' => [
        'title' => app_text('auto.k_5e30f01694b5'),
        'table' => 'content_posts',
        'columns' => ['id', 'title', 'owner_type', 'owner_id', 'status', 'publish_at', 'created_by'],
        'fields' => [
            'owner_type' => ['label' => app_text('integrations.owner_type'), 'type' => 'select', 'options' => ['reseller', 'manager'], 'nullable' => true],
            'owner_id' => ['label' => app_text('integrations.owner_id'), 'type' => 'number', 'nullable' => true],
            'content_type' => ['label' => app_text('auto.k_ef19578bced0'), 'type' => 'select', 'options' => ['article', 'image', 'pdf', 'video', 'link'], 'required' => true],
            'section_type' => ['label' => 'Раздел мини-сайта', 'type' => 'select', 'options' => ['general', 'story', 'result', 'promotion', 'giveaway', 'program', 'marathon'], 'required' => true],
            'title' => ['label' => app_text('auto.k_a8504d513adf'), 'required' => true],
            'short_text' => ['label' => app_text('auto.k_45cab8e7b9f1'), 'type' => 'textarea'],
            'full_text' => ['label' => app_text('auto.k_88a3ec931c4d'), 'type' => 'textarea'],
            'image_path' => ['label' => app_text('auto.k_56a1fd52891d'), 'type' => 'file', 'accept' => 'image/*'],
            'attachment_path' => ['label' => app_text('auto.k_1e51e67e49b3'), 'type' => 'file', 'accept' => 'application/pdf,video/mp4,image/*'],
            'video_url' => ['label' => app_text('auto.k_54fbfaf96a2d')],
            'button_text' => ['label' => app_text('auto.k_f9fd27363780')],
            'button_url' => ['label' => app_text('auto.k_668acad1ed4c')],
            'category_id' => ['label' => app_text('auto.k_1cf49d95b0ed'), 'type' => 'select', 'source' => 'product_categories', 'nullable' => true],
            'status' => ['label' => app_text('auto.k_f7f293b5c58c'), 'type' => 'select', 'options' => ['draft', 'published', 'hidden'], 'required' => true],
            'publish_at' => ['label' => app_text('auto.k_8ad0765b3c02'), 'type' => 'datetime-local', 'nullable' => true],
        ],
    ],
    'site_templates' => [
        'title' => 'Шаблоны мини-сайта',
        'table' => 'site_templates',
        'columns' => ['id', 'title', 'slug', 'description', 'owner_type', 'owner_id', 'profile_json', 'blocks_json', 'sort_order', 'is_active'],
        'fields' => [
            'title' => ['label' => 'Название', 'required' => true],
            'slug' => [
                'label' => 'Код шаблона',
                'required' => true,
                'hint' => 'Только латиница, цифры, дефис или подчёркивание.',
            ],
            'description' => ['label' => 'Описание', 'type' => 'textarea'],
            'profile_json' => [
                'label' => 'Первый экран',
                'type' => 'textarea',
                'required' => true,
                'default' => site_template_default_profile_json(),
                'rows' => 14,
                'hint' => 'JSON. Можно использовать {{name}}, {{role_label}}, {{phone}}, {{email}}.',
            ],
            'blocks_json' => [
                'label' => 'Блоки страницы',
                'type' => 'textarea',
                'default' => site_template_default_blocks_json(),
                'rows' => 14,
                'hint' => 'JSON-массив блоков мини-сайта.',
            ],
            'sort_order' => ['label' => app_text('auto.k_ed030118aad8'), 'type' => 'number', 'default' => 100],
            'is_active' => ['label' => app_text('auto.k_667904ef22a4'), 'type' => 'checkbox', 'default' => 1],
        ],
    ],
    'integrations' => [
        'title' => app_text('integrations.title'),
        'table' => 'messaging_integrations',
        'columns' => ['id', 'owner_type', 'owner_id', 'platform', 'title', 'external_id', 'callback_last_event_at', 'is_active'],
        'fields' => [
            'owner_type' => ['label' => app_text('integrations.owner_type'), 'type' => 'select', 'options' => ['reseller', 'manager'], 'required' => true],
            'owner_id' => ['label' => app_text('integrations.owner_id'), 'type' => 'number', 'required' => true],
            'platform' => ['label' => app_text('auto.k_89009febe5c6'), 'type' => 'select', 'options' => ['VK', 'OK', 'telegram', 'MAX'], 'required' => true],
            'title' => ['label' => app_text('auto.k_3de49828e86a'), 'required' => true],
            'external_id' => [
                'label' => app_text('integrations.external_id'),
                'help' => [
                    'title' => 'group_id',
                    'text' => 'В VK откройте Callback API. В сером блоке подтверждения скопируйте число из JSON после group_id.',
                    'image' => '/admin/uploads/help/vk-callback-group-id-marked.png',
                ],
            ],
            'access_token' => [
                'label' => app_text('integrations.access_token'),
                'type' => 'textarea',
                'help' => [
                    'title' => 'Ключ доступа',
                    'text' => 'В VK откройте «Дополнительно» -> «Работа с API» -> «Ключи доступа», создайте ключ и скопируйте строку вида vk1.a... в это поле.',
                    'image' => '/admin/uploads/help/vk-api-access-token-marked.jpg',
                ],
            ],
            'callback_confirmation_code' => [
                'label' => app_text('integrations.callback_confirmation_code'),
                'help' => [
                    'title' => 'Строка, которую должен вернуть сервер',
                    'text' => 'В VK на вкладке Callback API скопируйте строку после слов «Строка, которую должен вернуть сервер».',
                    'image' => '/admin/uploads/help/vk-callback-confirmation-marked.png',
                ],
            ],
            'callback_secret' => [
                'label' => app_text('integrations.callback_secret'),
                'default' => integration_callback_secret(),
                'help' => [
                    'title' => 'Секретный ключ',
                    'text' => 'SWPro генерирует этот ключ сам. Скопируйте его в VK в поле «Секретный ключ» и нажмите «Сохранить».',
                    'image' => '/admin/uploads/help/vk-callback-secret-marked.png',
                ],
            ],
            'callback_last_event_at' => ['label' => app_text('integrations.callback_last_event_at'), 'readonly' => true],
            'callback_last_error' => ['label' => app_text('integrations.callback_last_error'), 'type' => 'textarea', 'readonly' => true],
            'is_active' => ['label' => app_text('auto.k_667904ef22a4'), 'type' => 'checkbox', 'default' => 1],
        ],
    ],
    'legal_documents' => [
        'title' => 'Юридические документы',
        'table' => 'legal_documents',
        'columns' => ['id', 'document_type', 'title', 'version', 'is_required', 'is_active', 'updated_at'],
        'fields' => [
            'document_type' => [
                'label' => 'Тип документа',
                'type' => 'select',
                'options' => ['privacy_policy', 'personal_data_consent', 'health_data_consent', 'marketing_consent', 'user_agreement', 'leader_offer'],
                'required' => true,
            ],
            'title' => ['label' => 'Название', 'required' => true],
            'version' => ['label' => 'Версия', 'required' => true],
            'body' => ['label' => 'Текст документа', 'type' => 'textarea', 'required' => true],
            'is_required' => ['label' => 'Обязательный', 'type' => 'checkbox', 'default' => 1],
            'is_active' => ['label' => 'Активен', 'type' => 'checkbox', 'default' => 1],
        ],
    ],
];

$moduleKey = $_GET['module'] ?? 'users';
if (!isset($modules[$moduleKey]) || !can_manage($moduleKey, $admin)) {
    http_response_code(403);
    exit('Access denied');
}

$module = $modules[$moduleKey];
$title = $module['title'];

function scope_where_for_module(string $moduleKey, array $admin): array
{
    if ($moduleKey === 'users') {
        return scope_where_for_users($admin);
    }

    if ($moduleKey === 'platform_accounts') {
        [$userWhere, $userParams] = scope_where_for_users($admin);
        if (!$userWhere) {
            return ['', []];
        }
        return [
            'WHERE end_user_id IN (SELECT id FROM end_users ' . $userWhere . ')',
            $userParams,
        ];
    }

    if ($moduleKey === 'leads') {
        return scope_where_for_leads($admin);
    }

    if ($moduleKey === 'resellers' && $admin['role'] === 'reseller') {
        [$where, $params] = team_sql_in_condition('id', team_reseller_branch_ids((int)$admin['reseller_id'], true), 'scope_reseller_branch');
        return ['WHERE ' . $where, $params];
    }

    if ($moduleKey === 'managers' && $admin['role'] === 'reseller') {
        [$where, $params] = team_sql_in_condition('reseller_id', team_reseller_branch_ids((int)$admin['reseller_id'], true), 'scope_manager_branch');
        return ['WHERE ' . $where, $params];
    }

    if ($moduleKey === 'site_templates') {
        return site_template_admin_scope_condition($admin);
    }

    if (in_array($moduleKey, owned_modules(), true)) {
        return owner_scope_condition($admin, '', $moduleKey);
    }

    if ($moduleKey === 'broadcasts') {
        return owned_content_scope_condition('broadcasts', $admin);
    }

    if ($moduleKey === 'integrations') {
        return integration_scope_condition($admin);
    }

    return ['', []];
}

function scoped_row_exists(string $moduleKey, array $module, int $id, array $admin): bool
{
    [$where, $params] = scope_where_for_module($moduleKey, $admin);
    $sql = "SELECT COUNT(*) FROM {$module['table']} WHERE id = :id";
    if ($where) {
        $sql .= ' AND ' . preg_replace('/^WHERE\s+/i', '', $where);
    }
    $params['id'] = $id;
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn() > 0;
}


function owned_modules(): array
{
    return owned_content_config_keys();
}

function owner_scope_condition(array $admin, string $alias = '', string $moduleKey = ''): array
{
    if ($moduleKey !== '' && owned_content_config($moduleKey)) {
        return owned_content_scope_condition($moduleKey, $admin, $alias);
    }

    $prefix = $alias !== '' ? $alias . '.' : '';
    if ($admin['role'] === 'superadmin') {
        return ['', []];
    }

    if ($admin['role'] === 'reseller') {
        return [
            'WHERE (' . $prefix . 'owner_type IS NULL OR (' . $prefix . 'owner_type = "reseller" AND ' . $prefix . 'owner_id = :owner_reseller_id))',
            ['owner_reseller_id' => $admin['reseller_id']],
        ];
    }

    $parts = [$prefix . 'owner_type IS NULL'];
    $params = [];
    if (!empty($admin['reseller_id'])) {
        $parts[] = '(' . $prefix . 'owner_type = "reseller" AND ' . $prefix . 'owner_id = :owner_reseller_id)';
        $params['owner_reseller_id'] = $admin['reseller_id'];
    }
    $parts[] = '(' . $prefix . 'owner_type = "manager" AND ' . $prefix . 'owner_id = :owner_manager_id)';
    $params['owner_manager_id'] = $admin['manager_id'];

    return [
        'WHERE (' . implode(' OR ', $parts) . ')',
        $params,
    ];
}

function integration_scope_condition(array $admin): array
{
    if ($admin['role'] === 'superadmin') {
        return ['', []];
    }

    if ($admin['role'] === 'reseller') {
        return [
            'WHERE ((owner_type = "reseller" AND owner_id = :scope_reseller_id) OR (owner_type = "manager" AND owner_id IN (SELECT id FROM managers WHERE reseller_id = :scope_reseller_id_sub)))',
            ['scope_reseller_id' => $admin['reseller_id'], 'scope_reseller_id_sub' => $admin['reseller_id']],
        ];
    }

    return ['WHERE owner_type = "manager" AND owner_id = :scope_manager_id', ['scope_manager_id' => $admin['manager_id']]];
}

function scoped_end_user_exists(int $endUserId, array $admin): bool
{
    [$where, $params] = scope_where_for_users($admin);
    $sql = 'SELECT COUNT(*) FROM end_users WHERE id = :id';
    if ($where) {
        $sql .= ' AND ' . preg_replace('/^WHERE\s+/i', '', $where);
    }
    $params['id'] = $endUserId;
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn() > 0;
}

function user_display_label(array $row): string
{
    $name = trim((string)($row['full_name'] ?? ''));
    if (is_technical_client_name($name)) {
        $name = '';
    }
    if ($name === '') {
        $name = trim((string)($row['username'] ?? ''));
    }
    if ($name === '') {
        $platform = trim((string)($row['platform'] ?? ''));
        $name = ($platform ? platform_label($platform) . ' клиент ' : 'Клиент ') . '#' . (int)$row['id'];
    }

    $platform = trim((string)($row['platform'] ?? ''));
    $platformUserId = trim((string)($row['platform_user_id'] ?? ''));
    return '#' . (int)$row['id'] . ' ' . $name . ($platform ? ' (' . platform_label($platform) . ' ' . $platformUserId . ')' : '');
}

function merge_user_base_select(): string
{
    return "SELECT eu.id, eu.first_name, eu.last_name,
                CONCAT_WS(' ', NULLIF(eu.first_name, ''), NULLIF(eu.last_name, '')) AS full_name,
                eu.username, eu.platform, eu.platform_user_id, eu.gender, eu.birth_date,
                eu.age_years, eu.city, eu.phone, eu.email, eu.reseller_id, eu.manager_id,
                (SELECT GROUP_CONCAT(CONCAT(pa.platform, ':', pa.platform_user_id) ORDER BY FIELD(pa.platform, 'telegram', 'VK', 'OK', 'MAX', 'web'), pa.id SEPARATOR ', ')
                 FROM platform_accounts pa
                 WHERE pa.end_user_id = eu.id) AS platform_accounts_summary
            FROM end_users eu";
}

function merge_user_row(int $userId, array $admin): ?array
{
    [$where, $params] = scoped_where_with_alias(scope_where_for_users($admin), 'eu');
    $where = $where
        ? $where . ' AND eu.id = :user_id AND eu.merged_into_user_id IS NULL'
        : 'WHERE eu.id = :user_id AND eu.merged_into_user_id IS NULL';
    $params['user_id'] = $userId;

    $stmt = db()->prepare(merge_user_base_select() . " $where LIMIT 1");
    $stmt->execute($params);
    $row = $stmt->fetch();

    return $row ?: null;
}

function normalize_merge_text(?string $value): string
{
    $value = trim((string)$value);
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    $value = strtr($value, [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'i', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch',
        'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        'А' => 'a', 'Б' => 'b', 'В' => 'v', 'Г' => 'g', 'Д' => 'd', 'Е' => 'e', 'Ё' => 'e',
        'Ж' => 'zh', 'З' => 'z', 'И' => 'i', 'Й' => 'i', 'К' => 'k', 'Л' => 'l', 'М' => 'm',
        'Н' => 'n', 'О' => 'o', 'П' => 'p', 'Р' => 'r', 'С' => 's', 'Т' => 't', 'У' => 'u',
        'Ф' => 'f', 'Х' => 'h', 'Ц' => 'c', 'Ч' => 'ch', 'Ш' => 'sh', 'Щ' => 'sch',
        'Ъ' => '', 'Ы' => 'y', 'Ь' => '', 'Э' => 'e', 'Ю' => 'yu', 'Я' => 'ya',
    ]);

    return preg_replace('/[^a-z0-9]+/u', '', $value) ?? '';
}

function merge_user_name_variants(array $row): array
{
    $first = trim((string)($row['first_name'] ?? ''));
    $last = trim((string)($row['last_name'] ?? ''));
    $full = trim((string)($row['full_name'] ?? ''));
    $username = trim((string)($row['username'] ?? ''));
    $variants = [
        $full,
        trim($last . ' ' . $first),
        $username,
        trim((string)($row['email'] ?? '')),
        trim((string)($row['phone'] ?? '')),
    ];

    return array_values(array_filter(array_unique(array_map('normalize_merge_text', $variants))));
}

function merge_user_similarity_score(array $target, array $candidate, string $query): array
{
    $score = 0.0;
    $queryRank = 0;
    $reasons = [];
    $queryNorm = normalize_merge_text($query);
    $targetNames = merge_user_name_variants($target);
    $candidateNames = merge_user_name_variants($candidate);

    if ($queryNorm !== '') {
        $haystack = implode(' ', $candidateNames) . ' ' . normalize_merge_text((string)($candidate['platform_user_id'] ?? ''))
            . ' ' . normalize_merge_text((string)($candidate['platform_accounts_summary'] ?? ''))
            . ' ' . normalize_merge_text((string)($candidate['city'] ?? ''));
        if (str_contains($haystack, $queryNorm)) {
            $score += 55;
            $queryRank = 2;
            $reasons[] = 'найдено по запросу';
        } elseif (strlen($queryNorm) >= 3) {
            foreach ($candidateNames as $candidateName) {
                similar_text($queryNorm, $candidateName, $percent);
                if ($percent >= 55) {
                    $score += min(40, $percent * 0.45);
                    $queryRank = 1;
                    $reasons[] = 'похожее написание запроса';
                    break;
                }
            }
        }
    }

    foreach ($targetNames as $targetName) {
        if ($targetName === '') {
            continue;
        }
        foreach ($candidateNames as $candidateName) {
            if ($candidateName === '') {
                continue;
            }
            if ($targetName === $candidateName) {
                $score += 90;
                $reasons[] = 'совпадает имя';
                break 2;
            }
            if (strlen($targetName) >= 4 && strlen($candidateName) >= 4
                && (str_contains($targetName, $candidateName) || str_contains($candidateName, $targetName))) {
                $score += 55;
                $reasons[] = 'очень похожее имя';
                break 2;
            }

            similar_text($targetName, $candidateName, $percent);
            if ($percent >= 72) {
                $score += min(50, $percent * 0.55);
                $reasons[] = 'похожее имя';
                break 2;
            }
        }
    }

    if (!empty($target['birth_date']) && !empty($candidate['birth_date']) && $target['birth_date'] === $candidate['birth_date']) {
        $score += 25;
        $reasons[] = 'совпадает дата рождения';
    }
    if (!empty($target['age_years']) && !empty($candidate['age_years']) && (int)$target['age_years'] === (int)$candidate['age_years']) {
        $score += 8;
        $reasons[] = 'совпадает возраст';
    }
    if (!empty($target['city']) && !empty($candidate['city'])
        && normalize_merge_text((string)$target['city']) === normalize_merge_text((string)$candidate['city'])) {
        $score += 18;
        $reasons[] = 'совпадает город';
    }
    if (!empty($target['gender']) && !empty($candidate['gender']) && $target['gender'] === $candidate['gender']) {
        $score += 6;
    }
    if (!empty($target['manager_id']) && !empty($candidate['manager_id']) && (int)$target['manager_id'] === (int)$candidate['manager_id']) {
        $score += 5;
    } elseif (!empty($target['reseller_id']) && !empty($candidate['reseller_id']) && (int)$target['reseller_id'] === (int)$candidate['reseller_id']) {
        $score += 4;
    }
    if (!empty($target['platform']) && !empty($candidate['platform']) && $target['platform'] !== $candidate['platform']) {
        $score += 6;
        $reasons[] = 'другая платформа';
    }

    return [
        'score' => $score,
        'query_rank' => $queryRank,
        'reason' => implode(', ', array_values(array_unique($reasons))) ?: 'возможное совпадение',
    ];
}

function merge_user_search_results(int $targetUserId, string $query, array $admin): array
{
    $target = merge_user_row($targetUserId, $admin);
    if (!$target) {
        throw new RuntimeException('Пользователь недоступен.');
    }

    [$where, $params] = scoped_where_with_alias(scope_where_for_users($admin), 'eu');
    $where = $where
        ? $where . ' AND eu.id <> :target_user_id AND eu.merged_into_user_id IS NULL'
        : 'WHERE eu.id <> :target_user_id AND eu.merged_into_user_id IS NULL';
    $params['target_user_id'] = $targetUserId;

    $stmt = db()->prepare(merge_user_base_select() . " $where ORDER BY eu.id DESC LIMIT 10000");
    $stmt->execute($params);

    $queryNorm = normalize_merge_text($query);
    $minScore = $queryNorm !== '' ? 20 : 45;
    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $match = merge_user_similarity_score($target, $row, $query);
        if ($match['score'] < $minScore) {
            continue;
        }
        $items[] = [
            'id' => (int)$row['id'],
            'label' => user_display_label($row),
            'meta' => merge_user_meta_label($row),
            'reason' => $match['reason'],
            'score' => round($match['score'], 1),
            'query_rank' => $match['query_rank'],
        ];
    }

    usort($items, static fn(array $a, array $b): int => ($b['query_rank'] <=> $a['query_rank'])
        ?: ($b['score'] <=> $a['score'])
        ?: ($b['id'] <=> $a['id']));
    return array_slice($items, 0, 12);
}

function merge_user_meta_label(array $row): string
{
    $parts = [];
    if (!empty($row['city'])) {
        $parts[] = (string)$row['city'];
    }
    if (!empty($row['birth_date'])) {
        $parts[] = 'д.р. ' . $row['birth_date'];
    } elseif (!empty($row['age_years'])) {
        $parts[] = (int)$row['age_years'] . ' лет';
    }
    if (!empty($row['platform_accounts_summary'])) {
        $parts[] = (string)$row['platform_accounts_summary'];
    }

    return implode(' · ', $parts);
}

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

function user_promotion_allowed(array $admin): bool
{
    return in_array((string)$admin['role'], ['superadmin', 'reseller'], true);
}

function user_promotion_can_promote_row(array $row, array $admin): bool
{
    if (!user_promotion_allowed($admin)) {
        return false;
    }
    if (!empty($row['merged_into_user_id'])) {
        return false;
    }

    return scoped_end_user_exists((int)($row['id'] ?? 0), $admin);
}

function user_promotion_full_name(array $user): string
{
    $name = trim(implode(' ', array_filter([
        trim((string)($user['first_name'] ?? '')),
        trim((string)($user['last_name'] ?? '')),
    ])));
    if ($name !== '') {
        return $name;
    }

    $username = trim((string)($user['username'] ?? ''));
    return $username !== '' ? $username : ('Клиент #' . (int)($user['id'] ?? 0));
}

function user_promotion_platform_field(string $platform): ?string
{
    return match (strtolower(trim($platform))) {
        'telegram', 'tg' => 'telegram_id',
        'max' => 'max_id',
        'vk', 'vkontakte' => 'vk_id',
        default => null,
    };
}

function user_promotion_platform_identities(array $user): array
{
    $result = [];
    $add = static function (?string $platform, mixed $platformUserId) use (&$result): void {
        $field = $platform !== null ? user_promotion_platform_field($platform) : null;
        $value = trim((string)$platformUserId);
        if ($field && $value !== '' && empty($result[$field])) {
            $result[$field] = $value;
        }
    };

    $add((string)($user['platform'] ?? ''), $user['platform_user_id'] ?? null);
    foreach (user_platform_accounts((int)($user['id'] ?? 0)) as $account) {
        $add((string)($account['platform'] ?? ''), $account['platform_user_id'] ?? null);
    }

    return $result;
}

function user_promotion_referral_code_exists(string $code): bool
{
    if ($code === '') {
        return false;
    }

    foreach ([
        'SELECT id FROM resellers WHERE referral_code = :code LIMIT 1',
        'SELECT id FROM managers WHERE referral_code = :code LIMIT 1',
        'SELECT id FROM admin_users WHERE referral_code = :code LIMIT 1',
    ] as $sql) {
        $stmt = db()->prepare($sql);
        $stmt->execute(['code' => $code]);
        if ($stmt->fetchColumn()) {
            return true;
        }
    }

    return false;
}

function user_promotion_unique_referral_code(array $user): string
{
    $name = normalize_merge_text(user_promotion_full_name($user));
    $base = normalize_referral_slug('SWPRO_' . ($name !== '' ? $name : ('USER_' . (int)$user['id'])));
    if ($base === '' || $base === 'SWPRO') {
        $base = 'SWPRO_USER_' . (int)$user['id'];
    }

    $candidate = $base;
    for ($i = 2; user_promotion_referral_code_exists($candidate); $i++) {
        $candidate = $base . '_' . $i;
    }

    return $candidate;
}

function user_promotion_staff_conflict(array $platformIds, string $email, string $referralCode): ?string
{
    if ($email !== '') {
        foreach ([
            'managers' => 'консультант',
            'resellers' => 'лидер',
            'admin_users' => 'пользователь админки',
        ] as $table => $label) {
            $stmt = db()->prepare("SELECT id FROM {$table} WHERE email = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            $existingId = $stmt->fetchColumn();
            if ($existingId) {
                return 'Email уже используется: ' . $label . ' #' . (int)$existingId . '.';
            }
        }
    }

    if ($referralCode !== '' && user_promotion_referral_code_exists($referralCode)) {
        return 'Реферальный код уже используется.';
    }

    foreach (['telegram_id', 'max_id', 'vk_id'] as $field) {
        $value = trim((string)($platformIds[$field] ?? ''));
        if ($value === '') {
            continue;
        }

        foreach ([
            'managers' => 'консультант',
            'admin_users' => 'пользователь админки',
        ] as $table => $label) {
            $stmt = db()->prepare("SELECT id FROM {$table} WHERE {$field} = :value LIMIT 1");
            $stmt->execute(['value' => $value]);
            $existingId = $stmt->fetchColumn();
            if ($existingId) {
                return strtoupper(str_replace('_id', '', $field)) . ' уже используется: ' . $label . ' #' . (int)$existingId . '.';
            }
        }
    }

    return null;
}

function user_promotion_staff_module(string $type): ?string
{
    return match ($type) {
        'manager' => 'managers',
        'reseller' => 'resellers',
        default => null,
    };
}

function user_promotion_staff_scope_ok(string $type, int $id, array $admin): bool
{
    global $modules;

    $moduleKey = user_promotion_staff_module($type);
    if (!$moduleKey || !isset($modules[$moduleKey])) {
        return false;
    }

    return scoped_row_exists($moduleKey, $modules[$moduleKey], $id, $admin);
}

function user_promotion_staff_row(string $type, int $id, array $admin): array
{
    if (!user_promotion_staff_scope_ok($type, $id, $admin)) {
        throw new RuntimeException('Рабочий аккаунт не найден или недоступен.');
    }

    $table = $type === 'manager' ? 'managers' : 'resellers';
    $stmt = db()->prepare("SELECT * FROM {$table} WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Рабочий аккаунт не найден.');
    }

    return $row;
}

function user_promotion_staff_label(string $type, array $row): string
{
    $label = $type === 'manager' ? 'Консультант' : 'Лидер';
    return $label . ' #' . (int)$row['id'] . ' ' . trim((string)($row['name'] ?? ''));
}

function user_promotion_existing_access(string $type, int $id): ?array
{
    return $type === 'manager' ? manager_admin_access($id) : reseller_admin_access($id);
}

function user_promotion_add_search_condition(array &$conditions, array &$params, string $expression, string $param, mixed $value): void
{
    $value = trim((string)$value);
    if ($value === '') {
        return;
    }

    $conditions[] = $expression;
    $params[$param] = $value;
}

function user_promotion_existing_staff_candidates(array $user, array $admin): array
{
    if (!user_promotion_allowed($admin)) {
        return [];
    }

    $platformIds = user_promotion_platform_identities($user);
    $fullName = user_promotion_full_name($user);
    $firstName = trim((string)($user['first_name'] ?? ''));
    $lastName = trim((string)($user['last_name'] ?? ''));
    $email = trim((string)($user['email'] ?? ''));
    $phone = trim((string)($user['phone'] ?? ''));
    $candidates = [];

    $addCandidate = static function (string $type, array $row, string $reason = '') use (&$candidates, $admin): void {
        $id = (int)($row['id'] ?? 0);
        if ($id <= 0 || !user_promotion_staff_scope_ok($type, $id, $admin)) {
            return;
        }

        $key = $type . ':' . $id;
        if (isset($candidates[$key])) {
            return;
        }

        $details = [];
        if (!empty($row['email'])) {
            $details[] = (string)$row['email'];
        }
        if (!empty($row['phone'])) {
            $details[] = (string)$row['phone'];
        }
        if ($type === 'manager' && !empty($row['reseller_id'])) {
            $details[] = 'лидер: ' . team_reseller_label((int)$row['reseller_id']);
        }
        foreach (['telegram_id' => 'TG', 'vk_id' => 'VK', 'max_id' => 'MAX'] as $field => $label) {
            if (!empty($row[$field])) {
                $details[] = $label . ': ' . (string)$row[$field];
            }
        }
        if ($reason !== '') {
            $details[] = $reason;
        }

        $candidates[$key] = [
            'key' => $key,
            'type' => $type,
            'id' => $id,
            'label' => user_promotion_staff_label($type, $row),
            'details' => implode(' · ', array_values(array_unique(array_filter($details)))),
        ];
    };

    $managerConditions = [];
    $managerParams = [];
    user_promotion_add_search_condition($managerConditions, $managerParams, 'm.email = :manager_email', 'manager_email', $email);
    user_promotion_add_search_condition($managerConditions, $managerParams, 'm.phone = :manager_phone', 'manager_phone', $phone);
    user_promotion_add_search_condition($managerConditions, $managerParams, 'm.telegram_id = :manager_telegram_id', 'manager_telegram_id', $platformIds['telegram_id'] ?? '');
    user_promotion_add_search_condition($managerConditions, $managerParams, 'm.vk_id = :manager_vk_id', 'manager_vk_id', $platformIds['vk_id'] ?? '');
    user_promotion_add_search_condition($managerConditions, $managerParams, 'm.max_id = :manager_max_id', 'manager_max_id', $platformIds['max_id'] ?? '');
    user_promotion_add_search_condition($managerConditions, $managerParams, 'm.name = :manager_full_name', 'manager_full_name', $fullName);
    if ($lastName !== '') {
        $managerConditions[] = 'm.name LIKE :manager_last_name';
        $managerParams['manager_last_name'] = '%' . $lastName . '%';
    }
    if ($firstName !== '' && $lastName !== '') {
        $managerConditions[] = '(m.name LIKE :manager_first_name AND m.name LIKE :manager_last_name_pair)';
        $managerParams['manager_first_name'] = '%' . $firstName . '%';
        $managerParams['manager_last_name_pair'] = '%' . $lastName . '%';
    }
    if ($managerConditions) {
        $stmt = db()->prepare(
            'SELECT m.*
             FROM managers m
             WHERE (' . implode(' OR ', $managerConditions) . ')
             ORDER BY m.id DESC
             LIMIT 30'
        );
        $stmt->execute($managerParams);
        foreach ($stmt->fetchAll() as $row) {
            $addCandidate('manager', $row, 'похожий консультант');
        }
    }

    $resellerConditions = [];
    $resellerParams = [];
    user_promotion_add_search_condition($resellerConditions, $resellerParams, 'r.email = :reseller_email', 'reseller_email', $email);
    user_promotion_add_search_condition($resellerConditions, $resellerParams, 'r.phone = :reseller_phone', 'reseller_phone', $phone);
    user_promotion_add_search_condition($resellerConditions, $resellerParams, 'r.name = :reseller_full_name', 'reseller_full_name', $fullName);
    if ($lastName !== '') {
        $resellerConditions[] = 'r.name LIKE :reseller_last_name';
        $resellerParams['reseller_last_name'] = '%' . $lastName . '%';
    }
    if ($firstName !== '' && $lastName !== '') {
        $resellerConditions[] = '(r.name LIKE :reseller_first_name AND r.name LIKE :reseller_last_name_pair)';
        $resellerParams['reseller_first_name'] = '%' . $firstName . '%';
        $resellerParams['reseller_last_name_pair'] = '%' . $lastName . '%';
    }
    if ($resellerConditions) {
        $stmt = db()->prepare(
            'SELECT r.*
             FROM resellers r
             WHERE (' . implode(' OR ', $resellerConditions) . ')
             ORDER BY r.id DESC
             LIMIT 30'
        );
        $stmt->execute($resellerParams);
        foreach ($stmt->fetchAll() as $row) {
            $addCandidate('reseller', $row, 'похожий лидер');
        }
    }

    $adminConditions = [];
    $adminParams = [];
    user_promotion_add_search_condition($adminConditions, $adminParams, 'au.email = :admin_email', 'admin_email', $email);
    user_promotion_add_search_condition($adminConditions, $adminParams, 'au.phone = :admin_phone', 'admin_phone', $phone);
    user_promotion_add_search_condition($adminConditions, $adminParams, 'au.telegram_id = :admin_telegram_id', 'admin_telegram_id', $platformIds['telegram_id'] ?? '');
    user_promotion_add_search_condition($adminConditions, $adminParams, 'au.vk_id = :admin_vk_id', 'admin_vk_id', $platformIds['vk_id'] ?? '');
    user_promotion_add_search_condition($adminConditions, $adminParams, 'au.max_id = :admin_max_id', 'admin_max_id', $platformIds['max_id'] ?? '');
    if ($adminConditions) {
        $stmt = db()->prepare(
            'SELECT au.*
             FROM admin_users au
             WHERE au.role IN ("manager", "reseller")
               AND (' . implode(' OR ', $adminConditions) . ')
             ORDER BY au.id DESC
             LIMIT 30'
        );
        $stmt->execute($adminParams);
        foreach ($stmt->fetchAll() as $row) {
            if ((string)$row['role'] === 'manager' && !empty($row['manager_id'])) {
                try {
                    $staff = user_promotion_staff_row('manager', (int)$row['manager_id'], $admin);
                } catch (Throwable) {
                    continue;
                }
                $addCandidate('manager', $staff + [
                    'telegram_id' => $row['telegram_id'] ?? null,
                    'vk_id' => $row['vk_id'] ?? null,
                    'max_id' => $row['max_id'] ?? null,
                ], 'найден по доступу в админку');
            }
            if ((string)$row['role'] === 'reseller' && !empty($row['reseller_id'])) {
                try {
                    $staff = user_promotion_staff_row('reseller', (int)$row['reseller_id'], $admin);
                } catch (Throwable) {
                    continue;
                }
                $addCandidate('reseller', $staff + [
                    'telegram_id' => $row['telegram_id'] ?? null,
                    'vk_id' => $row['vk_id'] ?? null,
                    'max_id' => $row['max_id'] ?? null,
                ], 'найден по доступу в админку');
            }
        }
    }

    return array_slice(array_values($candidates), 0, 20);
}

function user_promotion_platform_conflict(string $type, int $id, string $field, string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    if (in_array($field, ['telegram_id', 'max_id', 'vk_id'], true)) {
        $stmt = db()->prepare("SELECT id FROM managers WHERE {$field} = :value LIMIT 1");
        $stmt->execute(['value' => $value]);
        $managerId = (int)$stmt->fetchColumn();
        if ($managerId > 0 && ($type !== 'manager' || $managerId !== $id)) {
            return strtoupper(str_replace('_id', '', $field)) . ' уже привязан к консультанту #' . $managerId . '.';
        }
    }

    $stmt = db()->prepare("SELECT id, role, manager_id, reseller_id FROM admin_users WHERE {$field} = :value LIMIT 1");
    $stmt->execute(['value' => $value]);
    $adminUser = $stmt->fetch();
    if (!$adminUser) {
        return null;
    }

    $sameManager = $type === 'manager'
        && (string)$adminUser['role'] === 'manager'
        && (int)($adminUser['manager_id'] ?? 0) === $id;
    $sameReseller = $type === 'reseller'
        && (string)$adminUser['role'] === 'reseller'
        && (int)($adminUser['reseller_id'] ?? 0) === $id;
    if (!$sameManager && !$sameReseller) {
        return strtoupper(str_replace('_id', '', $field)) . ' уже привязан к другому рабочему аккаунту.';
    }

    return null;
}

function user_promotion_sync_platform_ids(string $type, int $id, array $platformIds): void
{
    $fields = [];
    foreach (['telegram_id', 'max_id', 'vk_id'] as $field) {
        $value = trim((string)($platformIds[$field] ?? ''));
        if ($value === '') {
            continue;
        }
        $conflict = user_promotion_platform_conflict($type, $id, $field, $value);
        if ($conflict) {
            throw new RuntimeException($conflict);
        }
        $fields[$field] = $value;
    }
    if (!$fields) {
        return;
    }

    $sets = [];
    $params = ['id' => $id];
    foreach ($fields as $field => $value) {
        $sets[] = "{$field} = CASE WHEN {$field} IS NULL OR {$field} = '' THEN :{$field} ELSE {$field} END";
        $params[$field] = $value;
    }

    if ($type === 'manager') {
        $stmt = db()->prepare('UPDATE managers SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $stmt->execute($params);
    }

    $role = $type === 'manager' ? 'manager' : 'reseller';
    $ownerColumn = $type === 'manager' ? 'manager_id' : 'reseller_id';
    $stmt = db()->prepare(
        'UPDATE admin_users
         SET ' . implode(', ', $sets) . '
         WHERE role = :role AND ' . $ownerColumn . ' = :id'
    );
    $params['role'] = $role;
    $stmt->execute($params);
}

function user_promotion_admin_post_for_link(string $type, array $staff, array $platformIds, array $post, ?array $existing): array
{
    $existing ??= [];
    $value = static function (string $postKey, string $staffKey = '') use ($post, $staff, $existing): string {
        $staffKey = $staffKey !== '' ? $staffKey : $postKey;
        return trim((string)($post[$postKey] ?? ($existing[$staffKey] ?? ($staff[$staffKey] ?? ''))));
    };

    return [
        'admin_email' => $value('link_admin_email', 'email'),
        'admin_password' => (string)($post['link_admin_password'] ?? ''),
        'admin_is_active' => '1',
        'admin_phone' => $value('link_admin_phone', 'phone'),
        'admin_telegram_id' => trim((string)($platformIds['telegram_id'] ?? ($existing['telegram_id'] ?? ($staff['telegram_id'] ?? '')))),
        'admin_max_id' => trim((string)($platformIds['max_id'] ?? ($existing['max_id'] ?? ($staff['max_id'] ?? '')))),
        'admin_vk_id' => trim((string)($platformIds['vk_id'] ?? ($existing['vk_id'] ?? ($staff['vk_id'] ?? '')))),
        'admin_referral_code' => trim((string)($existing['referral_code'] ?? ($staff['referral_code'] ?? ''))),
    ];
}

function user_promotion_ensure_staff_access_for_link(string $type, int $id, array $staff, array $platformIds, array $post): void
{
    $existing = user_promotion_existing_access($type, $id);
    $adminPost = user_promotion_admin_post_for_link($type, $staff, $platformIds, $post, $existing);
    $errors = [];

    if ($type === 'manager') {
        save_manager_admin_access($id, $staff, $adminPost, $errors);
    } else {
        save_reseller_admin_access($id, $staff, $adminPost, $errors);
    }

    if ($errors) {
        throw new RuntimeException(implode(' ', $errors));
    }
}

function link_end_user_to_work_account(int $endUserId, array $post, array $admin): array
{
    if (!user_promotion_allowed($admin)) {
        throw new RuntimeException('Недостаточно прав для связывания клиента.');
    }
    if (!scoped_end_user_exists($endUserId, $admin)) {
        throw new RuntimeException('Клиент не найден или недоступен.');
    }

    $ref = trim((string)($post['existing_staff_ref'] ?? ''));
    if (!preg_match('/^(manager|reseller):(\d+)$/', $ref, $matches)) {
        throw new RuntimeException('Выберите рабочий аккаунт для связи.');
    }

    $type = $matches[1];
    $staffId = (int)$matches[2];
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare('SELECT * FROM end_users WHERE id = :id AND merged_into_user_id IS NULL LIMIT 1 FOR UPDATE');
        $stmt->execute(['id' => $endUserId]);
        $user = $stmt->fetch();
        if (!$user || !user_promotion_can_promote_row($user, $admin)) {
            throw new RuntimeException('Клиент не найден или недоступен.');
        }

        $staff = user_promotion_staff_row($type, $staffId, $admin);
        $platformIds = user_promotion_platform_identities($user);
        user_promotion_ensure_staff_access_for_link($type, $staffId, $staff, $platformIds, $post);
        user_promotion_sync_platform_ids($type, $staffId, $platformIds);

        $oldResellerId = nullable_int_value($user['reseller_id'] ?? null);
        $oldManagerId = nullable_int_value($user['manager_id'] ?? null);
        $oldStage = (string)($user['client_stage'] ?? 'new');
        $module = $type === 'manager' ? 'managers' : 'resellers';
        if ($type === 'manager') {
            $newResellerId = nullable_int_value($staff['reseller_id'] ?? null);
            $newManagerId = $staffId;
            if (!$newResellerId) {
                throw new RuntimeException('У выбранного консультанта не указан лидер.');
            }
            $update = $pdo->prepare(
                'UPDATE end_users
                 SET reseller_id = :reseller_id, manager_id = :manager_id, client_stage = "partner",
                     stage_updated_at = NOW(), status = "active"
                 WHERE id = :id'
            );
            $update->execute(['id' => $endUserId, 'reseller_id' => $newResellerId, 'manager_id' => $newManagerId]);
        } else {
            $newResellerId = $staffId;
            $newManagerId = null;
            $update = $pdo->prepare(
                'UPDATE end_users
                 SET reseller_id = :reseller_id, manager_id = NULL, client_stage = "partner",
                     stage_updated_at = NOW(), status = "active"
                 WHERE id = :id'
            );
            $update->execute(['id' => $endUserId, 'reseller_id' => $newResellerId]);
        }

        sync_active_leads_assignment($endUserId, $newResellerId, $newManagerId);
        sync_consultant_notifications_assignment($endUserId, $newResellerId, $newManagerId);
        log_end_user_transfer($endUserId, $oldResellerId, $oldManagerId, $newResellerId, $newManagerId, $admin, 'end_user_linked_to_staff', [
            'staff_type' => $type,
            'staff_id' => $staffId,
        ]);

        if ($oldStage !== 'partner') {
            $history = $pdo->prepare(
                'INSERT INTO client_stage_history (end_user_id, previous_stage, new_stage, source, actor_id)
                 VALUES (:end_user_id, :previous_stage, "partner", :source, :actor_id)'
            );
            $history->execute([
                'end_user_id' => $endUserId,
                'previous_stage' => $oldStage,
                'source' => user_promotion_stage_source($admin),
                'actor_id' => (int)$admin['id'],
            ]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return ['module' => $module, 'id' => $staffId, 'label' => user_promotion_staff_label($type, $staff)];
}

function user_promotion_template_id(array $post, array $admin): ?int
{
    $templateId = nullable_int_value($post['promotion_template_id'] ?? null);
    return $templateId && site_template_row($templateId, $admin) ? $templateId : null;
}

function user_promotion_scoped_reseller(int $resellerId, array $admin): ?array
{
    if ($resellerId <= 0) {
        return null;
    }

    global $modules;
    if (!scoped_row_exists('resellers', $modules['resellers'], $resellerId, $admin)) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM resellers WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $resellerId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function user_promotion_reseller_id_for_consultant(array $user, array $post, array $admin): int
{
    if ($admin['role'] === 'reseller') {
        return (int)$admin['reseller_id'];
    }

    $selectedId = nullable_int_value($post['promotion_reseller_id'] ?? null)
        ?? nullable_int_value($user['reseller_id'] ?? null);
    if (!$selectedId || !user_promotion_scoped_reseller($selectedId, $admin)) {
        throw new RuntimeException('Выберите лидера для нового консультанта.');
    }

    return $selectedId;
}

function user_promotion_parent_reseller_id_for_leader(array $user, array $post, array $admin): ?int
{
    if ($admin['role'] === 'reseller') {
        return (int)$admin['reseller_id'];
    }

    $selectedId = nullable_int_value($post['promotion_parent_reseller_id'] ?? null);
    if (!$selectedId) {
        return null;
    }
    if (!user_promotion_scoped_reseller($selectedId, $admin)) {
        throw new RuntimeException('Выбранный вышестоящий лидер недоступен.');
    }

    return $selectedId;
}

function user_promotion_apply_profile(string $ownerType, int $ownerId, ?int $templateId): void
{
    $profile = ensure_consultant_profile($ownerType, $ownerId);
    $profileId = (int)($profile['id'] ?? 0);
    if ($profileId <= 0) {
        return;
    }
    if ($templateId) {
        site_template_apply_to_profile($profileId, $ownerType, $ownerId, $templateId);
        return;
    }
    if (!consultant_profile_inherits($profile)) {
        $parentProfile = consultant_parent_profile($ownerType, $ownerId);
        if ($parentProfile) {
            consultant_profile_reset_to_parent($profileId, (int)$parentProfile['id']);
        }
    }
}

function user_promotion_stage_source(array $admin): string
{
    return $admin['role'] === 'reseller' ? 'leader' : 'admin';
}

function promote_end_user_to_work_account(int $endUserId, array $post, array $admin): array
{
    if (!user_promotion_allowed($admin)) {
        throw new RuntimeException('Недостаточно прав для преобразования клиента.');
    }
    if (!scoped_end_user_exists($endUserId, $admin)) {
        throw new RuntimeException('Клиент не найден или недоступен.');
    }

    $target = (string)($post['promotion_target'] ?? '');
    if (!in_array($target, ['manager', 'reseller'], true)) {
        throw new RuntimeException('Выберите, кого создать: консультанта или лидера.');
    }

    $templateId = user_promotion_template_id($post, $admin);
    $pdo = db();
    $pdo->beginTransaction();
    $createdOwnerType = null;
    $createdOwnerId = 0;
    $module = $target === 'manager' ? 'managers' : 'resellers';
    $label = '';

    try {
        $stmt = $pdo->prepare('SELECT * FROM end_users WHERE id = :id AND merged_into_user_id IS NULL LIMIT 1 FOR UPDATE');
        $stmt->execute(['id' => $endUserId]);
        $user = $stmt->fetch();
        if (!$user || !user_promotion_can_promote_row($user, $admin)) {
            throw new RuntimeException('Клиент не найден или недоступен.');
        }

        $name = trim((string)($post['promotion_name'] ?? ''));
        $name = $name !== '' ? $name : user_promotion_full_name($user);
        $email = trim((string)($post['admin_email'] ?? ($user['email'] ?? '')));
        $password = (string)($post['admin_password'] ?? '');
        if ($email === '') {
            throw new RuntimeException('Укажите email для входа в админку.');
        }
        if ($password === '') {
            throw new RuntimeException('Укажите временный пароль для входа в админку.');
        }

        $phone = trim((string)($post['admin_phone'] ?? ($user['phone'] ?? '')));
        $platformIds = user_promotion_platform_identities($user);
        $referralCode = trim((string)($post['promotion_referral_code'] ?? ''));
        $referralCode = $referralCode !== '' ? normalize_referral_slug($referralCode) : user_promotion_unique_referral_code($user);
        if ($referralCode === '') {
            throw new RuntimeException('Не удалось сформировать реферальный код.');
        }

        $conflict = user_promotion_staff_conflict($platformIds, $email, $referralCode);
        if ($conflict) {
            throw new RuntimeException($conflict);
        }

        $oldResellerId = nullable_int_value($user['reseller_id'] ?? null);
        $oldManagerId = nullable_int_value($user['manager_id'] ?? null);
        $oldStage = (string)($user['client_stage'] ?? 'new');
        $adminPost = [
            'admin_email' => $email,
            'admin_password' => $password,
            'admin_is_active' => '1',
            'admin_phone' => $phone,
            'admin_telegram_id' => $platformIds['telegram_id'] ?? '',
            'admin_max_id' => $platformIds['max_id'] ?? '',
            'admin_vk_id' => $platformIds['vk_id'] ?? '',
            'admin_referral_code' => $referralCode,
        ];
        $accessErrors = [];

        if ($target === 'manager') {
            $resellerId = user_promotion_reseller_id_for_consultant($user, $post, $admin);
            $payload = [
                'reseller_id' => $resellerId,
                'name' => $name,
                'email' => $email,
                'phone' => $phone !== '' ? $phone : null,
                'telegram_id' => $platformIds['telegram_id'] ?? null,
                'max_id' => $platformIds['max_id'] ?? null,
                'vk_id' => $platformIds['vk_id'] ?? null,
                'referral_code' => $referralCode,
                'is_active' => 1,
            ];
            $limitErrors = validate_manager_limit_payload('managers', $payload, null);
            if ($limitErrors) {
                throw new RuntimeException(implode(' ', $limitErrors));
            }

            $insert = $pdo->prepare(
                'INSERT INTO managers (reseller_id, name, email, phone, telegram_id, max_id, vk_id, referral_code, is_active)
                 VALUES (:reseller_id, :name, :email, :phone, :telegram_id, :max_id, :vk_id, :referral_code, :is_active)'
            );
            $insert->execute($payload);
            $managerId = (int)$pdo->lastInsertId();
            save_manager_admin_access($managerId, $payload, $adminPost, $accessErrors);
            if ($accessErrors) {
                throw new RuntimeException(implode(' ', $accessErrors));
            }

            $update = $pdo->prepare(
                'UPDATE end_users
                 SET reseller_id = :reseller_id, manager_id = :manager_id, client_stage = "partner",
                     stage_updated_at = NOW(), status = "active"
                 WHERE id = :id'
            );
            $update->execute(['id' => $endUserId, 'reseller_id' => $resellerId, 'manager_id' => $managerId]);
            $newResellerId = $resellerId;
            $newManagerId = $managerId;
            $createdOwnerType = 'manager';
            $createdOwnerId = $managerId;
            $label = 'консультант #' . $managerId;
        } else {
            $parentResellerId = user_promotion_parent_reseller_id_for_leader($user, $post, $admin);
            $payload = [
                'parent_reseller_id' => $parentResellerId,
                'name' => $name,
                'email' => $email,
                'phone' => $phone !== '' ? $phone : null,
                'billing_email' => $email,
                'referral_code' => $referralCode,
                'is_active' => 1,
            ];
            $limitErrors = validate_leader_limit_payload('resellers', $payload, null);
            if ($limitErrors) {
                throw new RuntimeException(implode(' ', $limitErrors));
            }

            $insert = $pdo->prepare(
                'INSERT INTO resellers (parent_reseller_id, name, email, phone, billing_email, referral_code, is_active)
                 VALUES (:parent_reseller_id, :name, :email, :phone, :billing_email, :referral_code, :is_active)'
            );
            $insert->execute($payload);
            $resellerId = (int)$pdo->lastInsertId();
            save_reseller_admin_access($resellerId, $payload, $adminPost, $accessErrors);
            if ($accessErrors) {
                throw new RuntimeException(implode(' ', $accessErrors));
            }

            $update = $pdo->prepare(
                'UPDATE end_users
                 SET reseller_id = :reseller_id, manager_id = NULL, client_stage = "partner",
                     stage_updated_at = NOW(), status = "active"
                 WHERE id = :id'
            );
            $update->execute(['id' => $endUserId, 'reseller_id' => $resellerId]);
            $newResellerId = $resellerId;
            $newManagerId = null;
            $createdOwnerType = 'reseller';
            $createdOwnerId = $resellerId;
            $label = 'лидер #' . $resellerId;
        }

        sync_active_leads_assignment($endUserId, $newResellerId, $newManagerId);
        sync_consultant_notifications_assignment($endUserId, $newResellerId, $newManagerId);
        log_end_user_transfer($endUserId, $oldResellerId, $oldManagerId, $newResellerId, $newManagerId, $admin, 'end_user_promoted', [
            'target' => $target,
            'created_owner_id' => $createdOwnerId,
        ]);

        if ($oldStage !== 'partner') {
            $history = $pdo->prepare(
                'INSERT INTO client_stage_history (end_user_id, previous_stage, new_stage, source, actor_id)
                 VALUES (:end_user_id, :previous_stage, "partner", :source, :actor_id)'
            );
            $history->execute([
                'end_user_id' => $endUserId,
                'previous_stage' => $oldStage,
                'source' => user_promotion_stage_source($admin),
                'actor_id' => (int)$admin['id'],
            ]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    if ($createdOwnerType && $createdOwnerId > 0) {
        try {
            user_promotion_apply_profile($createdOwnerType, $createdOwnerId, $templateId);
        } catch (Throwable) {
            // Рабочий аккаунт создан; мини-сайт можно восстановить вручную в админке.
        }
    }

    return ['module' => $module, 'id' => $createdOwnerId, 'label' => $label];
}

function merge_end_users(int $targetUserId, int $sourceUserId, array $admin): void
{
    if ($targetUserId <= 0 || $sourceUserId <= 0 || $targetUserId === $sourceUserId) {
        throw new RuntimeException('Выберите двух разных пользователей.');
    }
    if (!scoped_end_user_exists($targetUserId, $admin) || !scoped_end_user_exists($sourceUserId, $admin)) {
        throw new RuntimeException('Пользователь недоступен.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $targetStmt = $pdo->prepare('SELECT * FROM end_users WHERE id = :id AND merged_into_user_id IS NULL FOR UPDATE');
        $targetStmt->execute(['id' => $targetUserId]);
        $target = $targetStmt->fetch();

        $sourceStmt = $pdo->prepare('SELECT * FROM end_users WHERE id = :id AND merged_into_user_id IS NULL FOR UPDATE');
        $sourceStmt->execute(['id' => $sourceUserId]);
        $source = $sourceStmt->fetch();

        if (!$target || !$source) {
            throw new RuntimeException('Один из пользователей уже объединён.');
        }

        $mergeFields = [
            'username',
            'first_name',
            'last_name',
            'gender',
            'birth_date',
            'age_years',
            'city',
            'phone',
            'email',
            'referral_code_used',
            'onboarding_completed_at',
        ];
        $assignments = [];
        $mergeParams = ['target_id' => $targetUserId];
        foreach ($mergeFields as $field) {
            if (($target[$field] ?? null) === null || trim((string)$target[$field]) === '') {
                if (($source[$field] ?? null) !== null && trim((string)$source[$field]) !== '') {
                    $assignments[] = "$field = :$field";
                    $mergeParams[$field] = $source[$field];
                }
            }
        }
        if (empty($target['reseller_id']) && empty($target['manager_id'])
            && (!empty($source['reseller_id']) || !empty($source['manager_id']))) {
            $assignments[] = 'reseller_id = :reseller_id';
            $assignments[] = 'manager_id = :manager_id';
            $mergeParams['reseller_id'] = $source['reseller_id'];
            $mergeParams['manager_id'] = $source['manager_id'];
        }
        if ($assignments) {
            $mergeData = $pdo->prepare('UPDATE end_users SET ' . implode(', ', $assignments) . ' WHERE id = :target_id');
            $mergeData->execute($mergeParams);
        }

        $dedupeAutomation = $pdo->prepare(
            'DELETE source_log
             FROM automation_logs source_log
             INNER JOIN automation_logs target_log
               ON target_log.end_user_id = :target_id
              AND target_log.automation_type = source_log.automation_type
              AND target_log.context_key = source_log.context_key
              AND target_log.platform = source_log.platform
             WHERE source_log.end_user_id = :source_id'
        );
        $dedupeAutomation->execute(['target_id' => $targetUserId, 'source_id' => $sourceUserId]);

        $updates = [
            'platform_accounts' => 'end_user_id',
            'leads' => 'end_user_id',
            'user_test_sessions' => 'end_user_id',
            'recommendations' => 'end_user_id',
            'broadcast_logs' => 'end_user_id',
            'user_consents' => 'end_user_id',
            'client_stage_history' => 'end_user_id',
            'user_notifications' => 'end_user_id',
            'automation_logs' => 'end_user_id',
            'consultant_notifications' => 'end_user_id',
        ];
        foreach ($updates as $table => $column) {
            $stmt = $pdo->prepare("UPDATE $table SET $column = :target_id WHERE $column = :source_id");
            $stmt->execute(['target_id' => $targetUserId, 'source_id' => $sourceUserId]);
        }

        $mark = $pdo->prepare(
            'UPDATE end_users
             SET merged_into_user_id = :target_id, status = "unsubscribed"
             WHERE id = :source_id'
        );
        $mark->execute(['target_id' => $targetUserId, 'source_id' => $sourceUserId]);

        log_activity('admin', (int)$admin['id'], 'merge_end_users', 'end_users', $targetUserId, [
            'source_user_id' => $sourceUserId,
            'target_user_id' => $targetUserId,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function select_options(string $source, array $admin): array
{
    $allowed = [
        'resellers' => ['table' => 'resellers', 'label' => 'name'],
        'managers' => ['table' => 'managers', 'label' => 'name'],
        'end_users' => ['table' => 'end_users', 'label' => 'platform_user_id'],
        'products' => ['table' => 'products', 'label' => 'title'],
        'product_categories' => ['table' => 'product_categories', 'label' => 'title'],
        'content_posts' => ['table' => 'content_posts', 'label' => 'title'],
        'tests' => ['table' => 'tests', 'label' => 'title'],
        'site_templates' => ['table' => 'site_templates', 'label' => 'title'],
        'subscription_plans' => ['table' => 'subscription_plans', 'label' => 'title'],
    ];
    if (!isset($allowed[$source])) {
        return [];
    }

    if ($source === 'site_templates') {
        return site_template_options($admin);
    }

    $item = $allowed[$source];
    $where = '';
    $params = [];
    if ($source === 'resellers') {
        $options = team_reseller_options_for_admin($admin, true);
        if ($options) {
            return array_map(static fn(array $option): array => [
                'id' => (int)$option['id'],
                'label' => (string)$option['label'],
            ], $options);
        }
    }
    if ($source === 'managers' && $admin['role'] === 'reseller') {
        $branchIds = team_reseller_branch_ids((int)$admin['reseller_id'], true);
        [$whereSql, $params] = team_sql_in_condition('reseller_id', $branchIds, 'select_manager_branch');
        $where = 'WHERE ' . $whereSql;
    }
    if ($source === 'end_users') {
        [$where, $params] = scope_where_for_users($admin);
    }
    if (in_array($source, ['products', 'product_categories', 'content_posts', 'tests'], true)) {
        $moduleForSource = match ($source) {
            'products' => 'products',
            'product_categories' => 'categories',
            'content_posts' => 'content',
            'tests' => 'tests',
            default => '',
        };
        $alias = match ($source) {
            'products' => 'p',
            'product_categories' => 'pc',
            'content_posts' => 'cp',
            'tests' => 't',
            default => 'item',
        };
        [$where, $params] = owned_content_scope_condition($moduleForSource, $admin, $alias);
        $stmt = db()->prepare("SELECT {$alias}.id, {$alias}.{$item['label']} AS label FROM {$item['table']} {$alias} $where ORDER BY {$alias}.id DESC LIMIT 500");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    if ($source === 'subscription_plans') {
        return subscription_plan_options(true, $admin);
    }

    $stmt = db()->prepare("SELECT id, {$item['label']} AS label FROM {$item['table']} $where ORDER BY id DESC LIMIT 500");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function safe_select_options(string $source, array $admin, array &$errors): array
{
    try {
        return select_options($source, $admin);
    } catch (Throwable $e) {
        $errors[] = app_text('auto.k_e81a65169eba') . $source . '. ' . $e->getMessage();
        return [];
    }
}

function form_option_label(string $fieldName, string $option): string
{
    if ($fieldName === 'status') {
        return status_label($option);
    }

    if (in_array($fieldName, ['platform', 'source_platform'], true)) {
        return platform_label($option);
    }

    if ($fieldName === 'target_type') {
        return target_label($option);
    }

    if ($fieldName === 'scoring_type') {
        return $option === 'multiscale' ? 'Многошкальная матрица' : 'Обычный тест';
    }

    if ($fieldName === 'client_stage') {
        return client_stage_labels()[$option] ?? $option;
    }

    if ($fieldName === 'segment_stage') {
        return client_stage_labels()[$option] ?? $option;
    }

    if ($fieldName === 'gender') {
        return client_gender_labels()[$option] ?? $option;
    }

    if ($fieldName === 'audience_type') {
        return $option === 'consultants' ? 'Консультанты команды' : 'Клиенты';
    }

    if ($fieldName === 'segment_checkup') {
        return [
            'not_started' => 'Чек-ап не начат',
            'started' => 'Чек-ап начат',
            'completed' => 'Чек-ап завершен',
        ][$option] ?? $option;
    }

    if ($fieldName === 'segment_activity') {
        return [
            'active_7' => 'Активны за 7 дней',
            'active_30' => 'Активны за 30 дней',
            'inactive_14' => 'Неактивны 14 дней',
            'inactive_30' => 'Неактивны 30 дней',
        ][$option] ?? $option;
    }

    if ($fieldName === 'section_type') {
        return [
            'general' => 'Общее',
            'story' => 'История',
            'result' => 'Результаты',
            'promotion' => 'Акция',
            'giveaway' => 'Розыгрыш',
            'program' => 'Программа',
            'marathon' => 'Марафон',
        ][$option] ?? $option;
    }

    return $option;
}

function format_cell_value(mixed $value): string
{
    if ($value === null || $value === '') {
        return app_text('auto.k_1b93795b9768');
    }

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    return (string)$value;
}

function normalize_datetime(?string $value): ?string
{
    if (!$value) {
        return null;
    }

    return str_replace('T', ' ', $value);
}

function datetime_for_input(?string $value): string
{
    if (!$value) {
        return '';
    }

    return str_replace(' ', 'T', substr($value, 0, 16));
}

function collect_payload(array $fields): array
{
    $payload = [];
    foreach ($fields as $name => $field) {
        if (!empty($field['virtual'])) {
            continue;
        }
        if (!empty($field['readonly'])) {
            continue;
        }
        $type = $field['type'] ?? 'text';
        if ($type === 'file') {
            $current = trim((string)($_POST[$name . '_current'] ?? ''));
            $removeFiles = $_POST['remove_file'] ?? [];
            $removeCurrent = is_array($removeFiles) && isset($removeFiles[$name]);
            $payload[$name] = (!$removeCurrent && $current !== '') ? $current : null;
            continue;
        }

        if ($type === 'checkbox') {
            $payload[$name] = isset($_POST[$name]) ? 1 : 0;
            continue;
        }

        $value = trim((string)($_POST[$name] ?? ''));
        if ($type === 'datetime-local') {
            $value = normalize_datetime($value);
        }
        if (($field['nullable'] ?? false) && $value === '') {
            $value = null;
        }
        if ($type === 'number' && $value !== null && $value !== '') {
            $value = str_contains($value, '.') ? (float)$value : (int)$value;
        }
        $payload[$name] = $value;
    }

    return $payload;
}

function normalize_referral_slug(?string $value): string
{
    $value = strtoupper(trim((string)$value));
    $value = preg_replace('/\s+/', '-', $value) ?? '';
    $value = preg_replace('/[^A-Z0-9_-]/', '', $value) ?? '';
    $value = preg_replace('/[-_]{2,}/', '-', $value) ?? '';
    return trim($value, '-_');
}

function normalize_module_payload(string $moduleKey, array $payload): array
{
    if (in_array($moduleKey, ['managers', 'resellers'], true) && array_key_exists('referral_code', $payload)) {
        $payload['referral_code'] = normalize_referral_slug((string)$payload['referral_code']);
    }

    if ($moduleKey === 'integrations' && trim((string)($payload['callback_secret'] ?? '')) === '') {
        $payload['callback_secret'] = integration_callback_secret();
    }

    if ($moduleKey === 'site_templates') {
        $payload = site_template_normalize_payload($payload);
    }

    return $payload;
}

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

function validate_payload(array $fields, array $payload): array
{
    $errors = [];
    foreach ($fields as $name => $field) {
        if (!empty($field['virtual'])) {
            continue;
        }
        if (($field['required'] ?? false) && (($payload[$name] ?? null) === null || $payload[$name] === '')) {
            $errors[] = app_text('auto.k_2dc144adf452') . ($field['label'] ?? $name);
        }
        if (isset($field['options']) && ($payload[$name] ?? '') !== '' && !in_array($payload[$name], $field['options'], true)) {
            $errors[] = app_text('auto.k_337d46ded7e2') . ($field['label'] ?? $name);
        }
    }

    return $errors;
}

function validate_unique_payload(string $moduleKey, array $module, array $payload, ?int $recordId = null): array
{
    $uniqueFields = match ($moduleKey) {
        'resellers', 'managers' => [
            'referral_code' => 'Реферальный код',
        ],
        'site_templates' => [
            'slug' => 'Код шаблона',
        ],
        default => [],
    };

    $errors = [];
    foreach ($uniqueFields as $field => $label) {
        $value = trim((string)($payload[$field] ?? ''));
        if ($value === '') {
            continue;
        }

        if ($field === 'referral_code' && in_array($moduleKey, ['resellers', 'managers'], true)) {
            $conflict = staff_referral_code_conflict($value, $module['table'], $recordId);
            if ($conflict) {
                $errors[] = $label . ' "' . $value . '" уже используется: '
                    . $conflict['label'] . ' #' . (int)$conflict['id'] . '. Укажите другой код.';
            }
            continue;
        }

        $sql = "SELECT id FROM {$module['table']} WHERE `$field` = :value";
        $params = ['value' => $value];
        if ($recordId) {
            $sql .= ' AND id <> :id';
            $params['id'] = $recordId;
        }
        $sql .= ' LIMIT 1';

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $existingId = $stmt->fetchColumn();
        if ($existingId !== false) {
            $errors[] = $label . ' "' . $value . '" уже используется. Укажите другой код.';
        }
    }

    return $errors;
}

function staff_referral_code_conflict(string $code, ?string $exceptTable = null, ?int $exceptId = null): ?array
{
    $code = trim($code);
    if ($code === '') {
        return null;
    }

    foreach ([
        'resellers' => 'лидер',
        'managers' => 'консультант',
    ] as $table => $label) {
        $sql = "SELECT id FROM {$table} WHERE referral_code = :code";
        $params = ['code' => $code];
        if ($table === $exceptTable && $exceptId !== null) {
            $sql .= ' AND id <> :except_id';
            $params['except_id'] = $exceptId;
        }
        $sql .= ' LIMIT 1';

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $id = $stmt->fetchColumn();
        if ($id !== false) {
            return ['id' => (int)$id, 'label' => $label];
        }
    }

    return null;
}

function friendly_save_error(Throwable $e, array $payload): string
{
    $message = $e->getMessage();
    $isDuplicate = ($e instanceof PDOException && (int)($e->errorInfo[1] ?? 0) === 1062)
        || stripos($message, 'Duplicate entry') !== false;

    if ($isDuplicate) {
        if (stripos($message, 'referral_code') !== false) {
            $code = trim((string)($payload['referral_code'] ?? ''));
            return $code !== ''
                ? 'Реферальный код "' . $code . '" уже используется. Укажите другой код.'
                : 'Такой реферальный код уже используется. Укажите другой код.';
        }

        if (stripos($message, 'slug') !== false) {
            $code = trim((string)($payload['slug'] ?? ''));
            return $code !== ''
                ? 'Код шаблона "' . $code . '" уже используется. Укажите другой код.'
                : 'Такой код шаблона уже используется. Укажите другой код.';
        }

        return 'Такая запись уже существует. Проверьте уникальные поля.';
    }

    return app_text('auto.k_02613f541f5f') . ' ' . $message;
}

function validate_scope_payload(string $moduleKey, array $payload, array $admin, ?int $recordId = null): array
{
    $errors = [];
    if (in_array($moduleKey, ['managers', 'resellers'], true)) {
        $code = (string)($payload['referral_code'] ?? '');
        if ($code === '' || !preg_match('/^[A-Z0-9_-]{3,64}$/', $code)) {
            $errors[] = app_text('referrals.invalid_code');
        }
    }

    if ($moduleKey === 'site_templates') {
        $errors = array_merge($errors, site_template_validate_payload($payload));
    }

    if ($moduleKey === 'resellers') {
        foreach (['manager_limit', 'direct_leader_limit', 'branch_leader_limit', 'direct_manager_limit', 'branch_manager_limit', 'per_child_manager_limit'] as $field) {
            if (($payload[$field] ?? null) !== null && (int)$payload[$field] < 0) {
                $errors[] = 'Лимиты лидеров и консультантов не могут быть отрицательными.';
                break;
            }
        }
        foreach (['price_per_leader', 'price_per_consultant'] as $field) {
            if (($payload[$field] ?? null) !== null && (float)$payload[$field] < 0) {
                $errors[] = 'Цена не может быть отрицательной.';
                break;
            }
        }

        $parentId = nullable_int_value($payload['parent_reseller_id'] ?? null);
        if ($recordId && $parentId === $recordId) {
            $errors[] = 'Лидер не может быть вышестоящим сам для себя.';
        }
        if ($recordId && $parentId && team_is_reseller_in_branch($recordId, $parentId, false)) {
            $errors[] = 'Нельзя выбрать дочернего лидера как вышестоящего.';
        }
        if ($admin['role'] === 'reseller') {
            $rootId = (int)$admin['reseller_id'];
            if ($recordId) {
                if (!team_is_reseller_in_branch($rootId, $recordId, true)) {
                    $errors[] = 'Этот лидер не входит в вашу ветку.';
                }
                if ($parentId !== null && !team_is_reseller_in_branch($rootId, $parentId, true)) {
                    $errors[] = 'Вышестоящий лидер должен быть внутри вашей ветки.';
                }
            } elseif ($parentId === null) {
                $errors[] = 'Для лидера в вашей ветке нужно выбрать вышестоящего лидера.';
            } elseif (!team_is_reseller_in_branch($rootId, $parentId, true)) {
                $errors[] = 'Вышестоящий лидер должен быть внутри вашей ветки.';
            }
        }
    }

    if ($moduleKey === 'managers' && $admin['role'] === 'reseller') {
        $resellerId = nullable_int_value($payload['reseller_id'] ?? null);
        if ($resellerId && !team_is_reseller_in_branch((int)$admin['reseller_id'], $resellerId, true)) {
            $errors[] = 'Консультанта можно закрепить только за лидером внутри вашей ветки.';
        }
    }

    if (in_array($moduleKey, ['users', 'leads'], true) && !empty($payload['manager_id']) && !empty($payload['reseller_id'])) {
        $managerResellerId = team_manager_reseller_id((int)$payload['manager_id']);
        if ($managerResellerId === null) {
            $errors[] = app_text('auto.k_34b1bedb5064');
        } elseif ($managerResellerId !== (int)$payload['reseller_id']) {
            $errors[] = app_text('auto.k_34b1bedb5064');
        }
    }

    if (in_array($moduleKey, ['users', 'leads', 'broadcasts'], true) && $admin['role'] === 'reseller') {
        $rootId = (int)$admin['reseller_id'];
        $resellerId = nullable_int_value($payload['reseller_id'] ?? $payload['target_reseller_id'] ?? null);
        $managerId = nullable_int_value($payload['manager_id'] ?? $payload['target_manager_id'] ?? null);
        if ($resellerId && !team_is_reseller_in_branch($rootId, $resellerId, true)) {
            $errors[] = 'Выбранный лидер не входит в вашу ветку.';
        }
        if ($managerId && !team_is_manager_in_branch($rootId, $managerId)) {
            $errors[] = 'Выбранный консультант не входит в вашу ветку.';
        }
    }

    if (in_array($moduleKey, ['leads', 'platform_accounts'], true)) {
        $endUserId = (int)($payload['end_user_id'] ?? 0);
        if ($endUserId && !scoped_end_user_exists($endUserId, $admin)) {
            $errors[] = app_text('auto.k_34b1bedb5064');
        }
    }

    if (in_array($moduleKey, owned_modules(), true) && $admin['role'] === 'superadmin') {
        $ownerType = (string)($payload['owner_type'] ?? '');
        $ownerId = (int)($payload['owner_id'] ?? 0);
        if ($ownerType === '' && $ownerId > 0) {
            $errors[] = 'Выберите тип владельца материала.';
        } elseif ($ownerType !== '') {
            if ($ownerId <= 0) {
                $errors[] = 'Укажите ID владельца материала.';
            } elseif ($ownerType === 'reseller') {
                $stmt = db()->prepare('SELECT COUNT(*) FROM resellers WHERE id = :id');
                $stmt->execute(['id' => $ownerId]);
                if ((int)$stmt->fetchColumn() === 0) {
                    $errors[] = 'Лидер для материала не найден.';
                }
            } elseif ($ownerType === 'manager') {
                $stmt = db()->prepare('SELECT COUNT(*) FROM managers WHERE id = :id');
                $stmt->execute(['id' => $ownerId]);
                if ((int)$stmt->fetchColumn() === 0) {
                    $errors[] = 'Консультант для материала не найден.';
                }
            }
        }
    }

    if ($moduleKey === 'integrations' && $admin['role'] !== 'superadmin') {
        $ownerType = (string)($payload['owner_type'] ?? '');
        $ownerId = (int)($payload['owner_id'] ?? 0);
        if ($admin['role'] === 'reseller') {
            $allowed = ($ownerType === 'reseller' && $ownerId === (int)$admin['reseller_id']);
            if (!$allowed && $ownerType === 'manager') {
                $stmt = db()->prepare('SELECT COUNT(*) FROM managers WHERE id = :id AND reseller_id = :reseller_id');
                $stmt->execute(['id' => $ownerId, 'reseller_id' => $admin['reseller_id']]);
                $allowed = (int)$stmt->fetchColumn() > 0;
            }
            if (!$allowed) {
                $errors[] = app_text('integrations.owner_forbidden');
            }
        } elseif ($admin['role'] === 'manager' && !($ownerType === 'manager' && $ownerId === (int)$admin['manager_id'])) {
            $errors[] = app_text('integrations.owner_forbidden');
        }
    }

    if ($moduleKey === 'broadcasts'
        && trim((string)($payload['message_text'] ?? '')) === ''
        && empty($payload['image_path'])
        && empty($payload['video_path'])) {
        $errors[] = 'Добавьте текст, фотографию или видео.';
    }

    return $errors;
}

function manager_reseller_id(int $managerId): ?int
{
    return team_manager_reseller_id($managerId);
}

function leader_manager_limit_state(?int $resellerId, ?int $excludeManagerId = null): ?array
{
    if (!$resellerId) {
        return null;
    }

    $leaderStmt = db()->prepare('SELECT manager_limit FROM resellers WHERE id = :id LIMIT 1');
    $leaderStmt->execute(['id' => $resellerId]);
    $leader = $leaderStmt->fetch();
    if (!$leader) {
        return null;
    }

    $params = ['reseller_id' => $resellerId];
    $sql = 'SELECT COUNT(*) FROM managers WHERE reseller_id = :reseller_id AND is_active = 1';
    if ($excludeManagerId) {
        $sql .= ' AND id <> :exclude_manager_id';
        $params['exclude_manager_id'] = $excludeManagerId;
    }

    $countStmt = db()->prepare($sql);
    $countStmt->execute($params);

    return [
        'limit' => $leader['manager_limit'] !== null && $leader['manager_limit'] !== '' ? (int)$leader['manager_limit'] : null,
        'active' => (int)$countStmt->fetchColumn(),
    ];
}

function validate_manager_limit_payload(string $moduleKey, array $payload, ?int $recordId = null): array
{
    if ($moduleKey !== 'managers' || (int)($payload['is_active'] ?? 0) !== 1) {
        return [];
    }

    $resellerId = nullable_int_value($payload['reseller_id'] ?? null);
    if (!$resellerId) {
        return [];
    }

    $errors = [];
    $leader = team_reseller_row($resellerId);
    if ($leader) {
        $directLimit = team_limit_value($leader, 'direct_manager_limit', 'manager_limit');
        if ($directLimit !== null) {
            $directActive = team_direct_manager_count($resellerId, $recordId);
            if ($directActive + 1 > $directLimit) {
                $errors[] = 'У лидера "' . team_reseller_label($resellerId) . '" заполнен лимит прямых консультантов: ' . $directActive . ' из ' . $directLimit . '.';
            }
        }
    }

    foreach (team_reseller_ancestor_ids($resellerId, true) as $ancestorId) {
        $ancestor = team_reseller_row($ancestorId);
        if (!$ancestor) {
            continue;
        }

        $branchLimit = team_limit_value($ancestor, 'branch_manager_limit', 'manager_limit');
        if ($branchLimit !== null) {
            $branchActive = team_branch_manager_count($ancestorId, $recordId);
            if ($branchActive + 1 > $branchLimit) {
                $errors[] = 'У лидера "' . team_reseller_label($ancestorId) . '" заполнен лимит консультантов всей ветки: ' . $branchActive . ' из ' . $branchLimit . '.';
            }
        }

        $parent = team_reseller_row($ancestorId);
        $parentId = !empty($parent['parent_reseller_id']) ? (int)$parent['parent_reseller_id'] : null;
        if ($parentId) {
            $parentRow = team_reseller_row($parentId);
            $perChildLimit = $parentRow ? team_limit_value($parentRow, 'per_child_manager_limit') : null;
            if ($perChildLimit !== null) {
                $childBranchActive = team_branch_manager_count($ancestorId, $recordId);
                if ($childBranchActive + 1 > $perChildLimit) {
                    $errors[] = 'У лидера "' . team_reseller_label($parentId) . '" заполнен лимит консультантов на дочернего лидера "' . team_reseller_label($ancestorId) . '": ' . $childBranchActive . ' из ' . $perChildLimit . '.';
                }
            }
        }
    }

    return array_values(array_unique($errors));
}

function validate_leader_limit_payload(string $moduleKey, array $payload, ?int $recordId = null): array
{
    if ($moduleKey !== 'resellers') {
        return [];
    }

    $errors = [];
    $isActive = (int)($payload['is_active'] ?? 0) === 1;
    $parentId = nullable_int_value($payload['parent_reseller_id'] ?? null);

    if ($isActive && $parentId) {
        $parent = team_reseller_row($parentId);
        if ($parent) {
            $directLimit = team_limit_value($parent, 'direct_leader_limit');
            if ($directLimit !== null) {
                $directActive = team_direct_leader_count($parentId, $recordId);
                if ($directActive + 1 > $directLimit) {
                    $errors[] = 'У вышестоящего лидера "' . team_reseller_label($parentId) . '" заполнен лимит прямых лидеров: ' . $directActive . ' из ' . $directLimit . '.';
                }
            }
        }

        foreach (team_reseller_ancestor_ids($parentId, true) as $ancestorId) {
            $ancestor = team_reseller_row($ancestorId);
            if (!$ancestor) {
                continue;
            }

            $branchLimit = team_limit_value($ancestor, 'branch_leader_limit');
            if ($branchLimit !== null) {
                $branchActive = team_branch_leader_count($ancestorId, $recordId);
                if ($branchActive + 1 > $branchLimit) {
                    $errors[] = 'У лидера "' . team_reseller_label($ancestorId) . '" заполнен лимит лидеров всей ветки: ' . $branchActive . ' из ' . $branchLimit . '.';
                }
            }
        }
    }

    if ($recordId) {
        $checks = [
            'direct_leader_limit' => ['count' => team_direct_leader_count($recordId), 'label' => 'прямых лидеров'],
            'branch_leader_limit' => ['count' => team_branch_leader_count($recordId), 'label' => 'лидеров всей ветки'],
            'direct_manager_limit' => ['count' => team_direct_manager_count($recordId), 'label' => 'прямых консультантов'],
            'branch_manager_limit' => ['count' => team_branch_manager_count($recordId), 'label' => 'консультантов всей ветки'],
            'manager_limit' => ['count' => team_direct_manager_count($recordId), 'label' => 'прямых консультантов'],
        ];

        foreach ($checks as $field => $check) {
            $limit = nullable_int_value($payload[$field] ?? null);
            if ($limit !== null && $check['count'] > $limit) {
                $errors[] = 'Нельзя поставить лимит ' . $limit . ' для ' . $check['label'] . ': сейчас уже ' . $check['count'] . '.';
            }
        }
    }

    return array_values(array_unique($errors));
}

function reseller_parent_id_for_limits(array $payload, ?int $recordId = null): ?int
{
    $parentId = nullable_int_value($payload['parent_reseller_id'] ?? null);
    if ($parentId || !$recordId) {
        return $parentId;
    }

    $stmt = db()->prepare('SELECT parent_reseller_id FROM resellers WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $recordId]);
    $storedParentId = $stmt->fetchColumn();

    return $storedParentId ? (int)$storedParentId : null;
}

function limit_field_value(array $payload, string $field): ?int
{
    $value = nullable_int_value($payload[$field] ?? null);
    return $value !== null && $value >= 0 ? $value : null;
}

function validate_child_limit_caps(string $moduleKey, array $payload, ?int $recordId = null): array
{
    if ($moduleKey !== 'resellers') {
        return [];
    }

    $parentId = reseller_parent_id_for_limits($payload, $recordId);
    if (!$parentId) {
        return [];
    }

    $parent = team_reseller_row($parentId);
    if (!$parent) {
        return [];
    }

    $errors = [];
    $childLimits = [
        'direct_leader_limit' => limit_field_value($payload, 'direct_leader_limit'),
        'branch_leader_limit' => limit_field_value($payload, 'branch_leader_limit'),
        'direct_manager_limit' => limit_field_value($payload, 'direct_manager_limit'),
        'branch_manager_limit' => limit_field_value($payload, 'branch_manager_limit'),
        'per_child_manager_limit' => limit_field_value($payload, 'per_child_manager_limit'),
    ];

    $directLeaderLimit = $childLimits['direct_leader_limit'];
    $branchLeaderLimit = $childLimits['branch_leader_limit'];
    if ($directLeaderLimit !== null && $branchLeaderLimit !== null && $directLeaderLimit > $branchLeaderLimit) {
        $errors[] = 'Лимит прямых лидеров не может быть больше лимита лидеров во всей ветке этого лидера.';
    }

    $directManagerLimit = $childLimits['direct_manager_limit'];
    $branchManagerLimit = $childLimits['branch_manager_limit'];
    if ($directManagerLimit !== null && $branchManagerLimit !== null && $directManagerLimit > $branchManagerLimit) {
        $errors[] = 'Лимит прямых консультантов не может быть больше лимита консультантов во всей ветке этого лидера.';
    }

    $parentDirectLeaderLimit = team_limit_value($parent, 'direct_leader_limit');
    if ($parentDirectLeaderLimit !== null && $directLeaderLimit !== null && $directLeaderLimit > $parentDirectLeaderLimit) {
        $errors[] = 'Лимит прямых лидеров нельзя поставить больше, чем у вышестоящего лидера "' . team_reseller_label($parentId) . '": максимум ' . $parentDirectLeaderLimit . '.';
    }

    $parentBranchLeaderLimit = team_limit_value($parent, 'branch_leader_limit');
    if ($parentBranchLeaderLimit !== null) {
        foreach (['direct_leader_limit' => 'Лимит прямых лидеров', 'branch_leader_limit' => 'Лимит лидеров во всей ветке'] as $field => $label) {
            $value = $childLimits[$field];
            if ($value !== null && $value > $parentBranchLeaderLimit) {
                $errors[] = $label . ' нельзя поставить больше лимита лидеров всей ветки вышестоящего лидера "' . team_reseller_label($parentId) . '": максимум ' . $parentBranchLeaderLimit . '.';
            }
        }
    }

    $parentBranchManagerLimit = team_limit_value($parent, 'branch_manager_limit', 'manager_limit');
    if ($parentBranchManagerLimit !== null) {
        foreach ([
            'direct_manager_limit' => 'Лимит прямых консультантов',
            'branch_manager_limit' => 'Лимит консультантов во всей ветке',
            'per_child_manager_limit' => 'Лимит консультантов на одного дочернего лидера',
        ] as $field => $label) {
            $value = $childLimits[$field];
            if ($value !== null && $value > $parentBranchManagerLimit) {
                $errors[] = $label . ' нельзя поставить больше лимита консультантов всей ветки вышестоящего лидера "' . team_reseller_label($parentId) . '": максимум ' . $parentBranchManagerLimit . '.';
            }
        }
    }

    $parentPerChildManagerLimit = team_limit_value($parent, 'per_child_manager_limit');
    if ($parentPerChildManagerLimit !== null) {
        foreach (['direct_manager_limit' => 'Лимит прямых консультантов', 'branch_manager_limit' => 'Лимит консультантов во всей ветке'] as $field => $label) {
            $value = $childLimits[$field];
            if ($value !== null && $value > $parentPerChildManagerLimit) {
                $errors[] = $label . ' для дочернего лидера нельзя поставить больше правила вышестоящего лидера "' . team_reseller_label($parentId) . '": максимум ' . $parentPerChildManagerLimit . '.';
            }
        }
    }

    return array_values(array_unique($errors));
}

function add_limit_field_cap(array &$caps, string $field, ?int $max, string $source): void
{
    if ($max === null) {
        return;
    }

    if (!isset($caps[$field]) || $max < (int)$caps[$field]['max']) {
        $caps[$field] = [
            'max' => $max,
            'source' => $source,
        ];
    }
}

function child_limit_field_caps(string $moduleKey, array $payload, ?int $recordId = null): array
{
    if ($moduleKey !== 'resellers') {
        return [];
    }

    $parentId = reseller_parent_id_for_limits($payload, $recordId);
    if (!$parentId) {
        return [];
    }

    $parent = team_reseller_row($parentId);
    if (!$parent) {
        return [];
    }

    $parentLabel = team_reseller_label($parentId);
    $caps = [];

    $parentDirectLeaderLimit = team_limit_value($parent, 'direct_leader_limit');
    add_limit_field_cap(
        $caps,
        'direct_leader_limit',
        $parentDirectLeaderLimit,
        'У вышестоящего лидера "' . $parentLabel . '" лимит прямых лидеров: ' . $parentDirectLeaderLimit . '.'
    );

    $parentBranchLeaderLimit = team_limit_value($parent, 'branch_leader_limit');
    foreach (['direct_leader_limit', 'branch_leader_limit'] as $field) {
        add_limit_field_cap(
            $caps,
            $field,
            $parentBranchLeaderLimit,
            'У вышестоящего лидера "' . $parentLabel . '" лимит лидеров во всей ветке: ' . $parentBranchLeaderLimit . '.'
        );
    }

    $parentBranchManagerLimit = team_limit_value($parent, 'branch_manager_limit', 'manager_limit');
    foreach (['direct_manager_limit', 'branch_manager_limit', 'per_child_manager_limit'] as $field) {
        add_limit_field_cap(
            $caps,
            $field,
            $parentBranchManagerLimit,
            'У вышестоящего лидера "' . $parentLabel . '" лимит консультантов во всей ветке: ' . $parentBranchManagerLimit . '.'
        );
    }

    $parentPerChildManagerLimit = team_limit_value($parent, 'per_child_manager_limit');
    foreach (['direct_manager_limit', 'branch_manager_limit'] as $field) {
        add_limit_field_cap(
            $caps,
            $field,
            $parentPerChildManagerLimit,
            'У вышестоящего лидера "' . $parentLabel . '" лимит консультантов на дочернего лидера: ' . $parentPerChildManagerLimit . '.'
        );
    }

    return $caps;
}

function create_limit_block_reasons(string $moduleKey, array $admin): array
{
    if (($admin['role'] ?? '') !== 'reseller' || empty($admin['reseller_id'])) {
        return [];
    }

    $resellerId = (int)$admin['reseller_id'];
    if ($moduleKey === 'resellers') {
        return validate_leader_limit_payload('resellers', [
            'parent_reseller_id' => $resellerId,
            'is_active' => 1,
        ]);
    }

    if ($moduleKey === 'managers') {
        return validate_manager_limit_payload('managers', [
            'reseller_id' => $resellerId,
            'is_active' => 1,
        ]);
    }

    return [];
}

function apply_role_defaults(string $moduleKey, array $payload, array $admin, ?int $recordId = null): array
{
    if (in_array($moduleKey, ['users', 'leads'], true) && !empty($payload['manager_id'])) {
        $payload['reseller_id'] = manager_reseller_id((int)$payload['manager_id']);
    }

    if ($admin['role'] === 'reseller' && $moduleKey === 'resellers') {
        if ($recordId) {
            unset($payload['parent_reseller_id']);
        } else {
            $payload['parent_reseller_id'] = $admin['reseller_id'];
        }
    }
    if ($admin['role'] === 'reseller' && in_array($moduleKey, ['managers', 'users', 'leads'], true)) {
        $resellerId = nullable_int_value($payload['reseller_id'] ?? null);
        if (!$resellerId || !team_is_reseller_in_branch((int)$admin['reseller_id'], $resellerId, true)) {
            $payload['reseller_id'] = $admin['reseller_id'];
        }
    }
    if ($admin['role'] === 'manager' && in_array($moduleKey, ['users', 'leads'], true)) {
        $payload['manager_id'] = $admin['manager_id'];
        $payload['reseller_id'] = $admin['reseller_id'];
    }
    if (in_array($moduleKey, owned_modules(), true) && $admin['role'] !== 'superadmin') {
        $payload['owner_type'] = $admin['role'];
        $payload['owner_id'] = $admin['role'] === 'reseller' ? $admin['reseller_id'] : $admin['manager_id'];
    }
    if ($moduleKey === 'site_templates') {
        if ($recordId) {
            unset($payload['owner_type'], $payload['owner_id'], $payload['source_template_id']);
        } elseif ($admin['role'] === 'reseller') {
            $payload['owner_type'] = 'reseller';
            $payload['owner_id'] = $admin['reseller_id'];
            $payload['source_template_id'] = null;
        } elseif ($admin['role'] === 'manager') {
            $payload['owner_type'] = 'manager';
            $payload['owner_id'] = $admin['manager_id'];
            $payload['source_template_id'] = null;
        } else {
            $payload['owner_type'] = null;
            $payload['owner_id'] = null;
            $payload['source_template_id'] = null;
        }
    }
    if ($moduleKey === 'integrations' && $admin['role'] === 'manager') {
        $payload['owner_type'] = 'manager';
        $payload['owner_id'] = $admin['manager_id'];
    }
    if ($moduleKey === 'integrations' && $admin['role'] === 'reseller' && empty($payload['owner_type'])) {
        $payload['owner_type'] = 'reseller';
        $payload['owner_id'] = $admin['reseller_id'];
    }
    if ($moduleKey === 'broadcasts') {
        $payload['created_by'] = $admin['id'];
        if ($admin['role'] === 'manager') {
            $payload['owner_type'] = 'manager';
            $payload['owner_id'] = $admin['manager_id'];
            $payload['audience_type'] = 'clients';
            $payload['target_type'] = 'manager';
            $payload['target_manager_id'] = $admin['manager_id'];
            $payload['target_reseller_id'] = $admin['reseller_id'];
        } elseif ($admin['role'] === 'reseller') {
            $payload['owner_type'] = 'reseller';
            $payload['owner_id'] = $admin['reseller_id'];
            $allowedTargets = [
                'own_clients',
                'branch_clients',
                'direct_consultants',
                'branch_consultants',
                'direct_leaders',
                'branch_leaders',
            ];
            $targetType = (string)($payload['target_type'] ?? '');
            $payload['target_type'] = in_array($targetType, $allowedTargets, true)
                ? $targetType
                : 'own_clients';
            $payload['audience_type'] = in_array($payload['target_type'], ['own_clients', 'branch_clients'], true)
                ? 'clients'
                : 'consultants';
            $payload['target_reseller_id'] = $admin['reseller_id'];
            $payload['target_manager_id'] = null;
        }
    }
    if ($moduleKey === 'content') {
        $payload['created_by'] = $admin['id'];
    }

    return $payload;
}

function broadcast_form_fields_for_admin(array $fields, array $admin): array
{
    if (($admin['role'] ?? '') === 'manager') {
        unset(
            $fields['audience_type'],
            $fields['target_type'],
            $fields['target_reseller_id'],
            $fields['target_manager_id']
        );
        return $fields;
    }

    if (($admin['role'] ?? '') === 'reseller') {
        $fields['target_type']['options'] = [
            'own_clients',
            'branch_clients',
            'direct_consultants',
            'branch_consultants',
            'direct_leaders',
            'branch_leaders',
        ];
        $fields['target_type']['default'] = 'own_clients';
        $fields['target_type']['hint'] = 'Доступны только получатели из вашей ветки.';
        unset(
            $fields['audience_type'],
            $fields['target_reseller_id'],
            $fields['target_manager_id']
        );
    }

    return $fields;
}

function nullable_int_value(mixed $value): ?int
{
    return $value === null || $value === '' ? null : (int)$value;
}

function profile_template_id_for_module(string $moduleKey, int $recordId): ?int
{
    if (!in_array($moduleKey, ['managers', 'resellers'], true) || $recordId <= 0) {
        return null;
    }

    $ownerType = $moduleKey === 'managers' ? 'manager' : 'reseller';
    try {
        $profile = ensure_consultant_profile($ownerType, $recordId);
    } catch (Throwable $e) {
        return null;
    }

    $templateId = nullable_int_value($profile['template_id'] ?? null);
    if ($templateId) {
        return $templateId;
    }

    return null;
}

function sync_active_leads_assignment(int $endUserId, ?int $resellerId, ?int $managerId): int
{
    $stmt = db()->prepare(
        'UPDATE leads
         SET reseller_id = :reseller_id,
             manager_id = :manager_id
         WHERE end_user_id = :end_user_id
           AND status IN ("new", "contacted", "interested")'
    );
    $stmt->execute([
        'reseller_id' => $resellerId,
        'manager_id' => $managerId,
        'end_user_id' => $endUserId,
    ]);

    return $stmt->rowCount();
}

function sync_consultant_notifications_assignment(int $endUserId, ?int $resellerId, ?int $managerId): int
{
    $stmt = db()->prepare(
        'UPDATE IGNORE consultant_notifications
         SET reseller_id = :reseller_id,
             manager_id = :manager_id
         WHERE end_user_id = :end_user_id
           AND is_read = 0'
    );
    $stmt->execute([
        'reseller_id' => $resellerId,
        'manager_id' => $managerId,
        'end_user_id' => $endUserId,
    ]);

    return $stmt->rowCount();
}

function log_end_user_transfer(
    int $endUserId,
    ?int $oldResellerId,
    ?int $oldManagerId,
    ?int $newResellerId,
    ?int $newManagerId,
    array $admin,
    string $action,
    array $extra = []
): void {
    if ($oldResellerId === $newResellerId && $oldManagerId === $newManagerId) {
        return;
    }

    log_activity('admin', (int)$admin['id'], $action, 'end_users', $endUserId, $extra + [
        'old_reseller_id' => $oldResellerId,
        'old_manager_id' => $oldManagerId,
        'new_reseller_id' => $newResellerId,
        'new_manager_id' => $newManagerId,
    ]);
}

function sync_user_assignment_from_lead(int $leadId, array $leadBefore, array $payload, array $admin): void
{
    $endUserId = nullable_int_value($payload['end_user_id'] ?? $leadBefore['end_user_id'] ?? null);
    if (!$endUserId) {
        return;
    }

    $oldLeadResellerId = nullable_int_value($leadBefore['reseller_id'] ?? null);
    $oldLeadManagerId = nullable_int_value($leadBefore['manager_id'] ?? null);
    $newResellerId = nullable_int_value($payload['reseller_id'] ?? null);
    $newManagerId = nullable_int_value($payload['manager_id'] ?? null);
    if ($newResellerId === null && $newManagerId === null) {
        return;
    }
    if ($oldLeadResellerId === $newResellerId && $oldLeadManagerId === $newManagerId) {
        return;
    }

    $stmt = db()->prepare('SELECT reseller_id, manager_id FROM end_users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $endUserId]);
    $userBefore = $stmt->fetch();
    if (!$userBefore) {
        return;
    }

    $oldUserResellerId = nullable_int_value($userBefore['reseller_id'] ?? null);
    $oldUserManagerId = nullable_int_value($userBefore['manager_id'] ?? null);

    $update = db()->prepare(
        'UPDATE end_users
         SET reseller_id = :reseller_id,
             manager_id = :manager_id
         WHERE id = :id'
    );
    $update->execute([
        'reseller_id' => $newResellerId,
        'manager_id' => $newManagerId,
        'id' => $endUserId,
    ]);

    $updatedLeads = sync_active_leads_assignment($endUserId, $newResellerId, $newManagerId);
    $updatedNotifications = sync_consultant_notifications_assignment($endUserId, $newResellerId, $newManagerId);

    log_end_user_transfer(
        $endUserId,
        $oldUserResellerId,
        $oldUserManagerId,
        $newResellerId,
        $newManagerId,
        $admin,
        'transfer_end_user_from_lead',
        [
            'lead_id' => $leadId,
            'updated_active_leads' => $updatedLeads,
            'updated_unread_notifications' => $updatedNotifications,
        ]
    );
}

function detach_owner_content(string $ownerType, int $ownerId): void
{
    $updates = [
        'product_categories' => ['column' => 'is_active', 'value' => 0],
        'products' => ['column' => 'is_active', 'value' => 0],
        'tests' => ['column' => 'is_active', 'value' => 0],
        'site_templates' => ['column' => 'is_active', 'value' => 0],
        'content_posts' => ['column' => 'status', 'value' => 'hidden'],
        'broadcasts' => ['column' => 'status', 'value' => 'cancelled'],
    ];

    foreach ($updates as $table => $update) {
        $stmt = db()->prepare(
            "UPDATE {$table}
             SET {$update['column']} = :inactive_value
             WHERE owner_type = :owner_type AND owner_id = :owner_id"
        );
        $stmt->execute([
            'inactive_value' => $update['value'],
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
        ]);
    }
}

function delete_owner_service_records(string $ownerType, int $ownerId): void
{
    $stmt = db()->prepare('DELETE FROM referral_links WHERE owner_type = :owner_type AND owner_id = :owner_id');
    $stmt->execute(['owner_type' => $ownerType, 'owner_id' => $ownerId]);

    $stmt = db()->prepare('DELETE FROM messaging_integrations WHERE owner_type = :owner_type AND owner_id = :owner_id');
    $stmt->execute(['owner_type' => $ownerType, 'owner_id' => $ownerId]);

    $stmt = db()->prepare('DELETE FROM consultant_profiles WHERE owner_type = :owner_type AND owner_id = :owner_id');
    $stmt->execute(['owner_type' => $ownerType, 'owner_id' => $ownerId]);
}

function delete_crud_record(string $moduleKey, array $module, int $id, array $admin): void
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        if ($moduleKey === 'users') {
            $stmt = $pdo->prepare('DELETE FROM end_users WHERE id = :id');
            $stmt->execute(['id' => $id]);
            log_activity('admin', (int)$admin['id'], 'delete_end_users', 'end_users', $id);
            $pdo->commit();
            return;
        }

        if (owned_content_delete_for_admin($moduleKey, $id, $admin)) {
            log_activity('admin', (int)$admin['id'], 'hide_owned_' . $module['table'], $module['table'], $id);
            $pdo->commit();
            return;
        }

        if ($moduleKey === 'content') {
            $stmt = $pdo->prepare('UPDATE content_posts SET status = "hidden" WHERE id = :id');
            $stmt->execute(['id' => $id]);
            log_activity('admin', (int)$admin['id'], 'hide_content_posts', 'content_posts', $id);
            $pdo->commit();
            return;
        }

        if ($moduleKey === 'site_templates') {
            $stmt = $pdo->prepare('UPDATE site_templates SET is_active = 0 WHERE id = :id');
            $stmt->execute(['id' => $id]);
            log_activity('admin', (int)$admin['id'], 'hide_site_templates', 'site_templates', $id);
            $pdo->commit();
            return;
        }

        if ($moduleKey === 'managers') {
            detach_owner_content('manager', $id);
            delete_owner_service_records('manager', $id);

            $stmt = $pdo->prepare('DELETE FROM admin_users WHERE role = "manager" AND manager_id = :manager_id');
            $stmt->execute(['manager_id' => $id]);

            $stmt = $pdo->prepare('DELETE FROM managers WHERE id = :id');
            $stmt->execute(['id' => $id]);
            log_activity('admin', (int)$admin['id'], 'delete_managers', 'managers', $id);
            $pdo->commit();
            return;
        }

        if ($moduleKey === 'resellers') {
            detach_owner_content('reseller', $id);
            delete_owner_service_records('reseller', $id);

            $stmt = $pdo->prepare('DELETE FROM admin_users WHERE role = "reseller" AND reseller_id = :reseller_id AND manager_id IS NULL');
            $stmt->execute(['reseller_id' => $id]);

            $stmt = $pdo->prepare('DELETE FROM resellers WHERE id = :id');
            $stmt->execute(['id' => $id]);
            log_activity('admin', (int)$admin['id'], 'delete_resellers', 'resellers', $id);
            $pdo->commit();
            return;
        }

        $stmt = $pdo->prepare("DELETE FROM {$module['table']} WHERE id = :id");
        $stmt->execute(['id' => $id]);
        log_activity('admin', (int)$admin['id'], 'delete_' . $module['table'], $module['table'], $id);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function manager_admin_access(int $managerId): ?array
{
    if ($managerId <= 0) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT id, name, email, phone, telegram_id, max_id, vk_id, referral_code, is_active
         FROM admin_users
         WHERE role = "manager" AND manager_id = :manager_id
         ORDER BY id DESC
         LIMIT 1'
    );
    $stmt->execute(['manager_id' => $managerId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function reseller_admin_access(int $resellerId): ?array
{
    if ($resellerId <= 0) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT id, name, email, phone, telegram_id, max_id, vk_id, referral_code, is_active
         FROM admin_users
         WHERE role = "reseller" AND reseller_id = :reseller_id AND manager_id IS NULL
         ORDER BY id DESC
         LIMIT 1'
    );
    $stmt->execute(['reseller_id' => $resellerId]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function can_manage_team_admin_access(string $moduleKey, array $admin, ?int $recordId = null): bool
{
    if (!in_array($moduleKey, ['managers', 'resellers'], true)) {
        return false;
    }

    if ($admin['role'] === 'superadmin') {
        return true;
    }

    if ($admin['role'] !== 'reseller') {
        return false;
    }

    if ($moduleKey === 'managers') {
        return true;
    }

    if (!$recordId) {
        return true;
    }

    return (int)($admin['reseller_id'] ?? 0) !== $recordId;
}

function admin_access_extra_payload(array $entityPayload, array $post, ?array $existing = null): array
{
    $existing ??= [];
    $referralCode = trim((string)($post['admin_referral_code'] ?? ($entityPayload['referral_code'] ?? ($existing['referral_code'] ?? ''))));
    $referralCode = $referralCode !== '' ? normalize_referral_slug($referralCode) : null;

    $stringOrNull = static function (mixed $value): ?string {
        $value = trim((string)$value);
        return $value !== '' ? $value : null;
    };

    return [
        'phone' => $stringOrNull($post['admin_phone'] ?? ($entityPayload['phone'] ?? ($existing['phone'] ?? ''))),
        'telegram_id' => $stringOrNull($post['admin_telegram_id'] ?? ($entityPayload['telegram_id'] ?? ($existing['telegram_id'] ?? ''))),
        'max_id' => $stringOrNull($post['admin_max_id'] ?? ($entityPayload['max_id'] ?? ($existing['max_id'] ?? ''))),
        'vk_id' => $stringOrNull($post['admin_vk_id'] ?? ($entityPayload['vk_id'] ?? ($existing['vk_id'] ?? ''))),
        'referral_code' => $referralCode,
    ];
}

function save_reseller_admin_access(int $resellerId, array $resellerPayload, array $post, array &$errors): void
{
    $email = trim((string)($post['admin_email'] ?? ''));
    $password = (string)($post['admin_password'] ?? '');
    $isActive = isset($post['admin_is_active']) ? 1 : 0;
    $existing = reseller_admin_access($resellerId);

    if ($email === '' && $password === '' && !$existing) {
        return;
    }

    if ($email === '') {
        $errors[] = app_text('admin_access.email_required');
        return;
    }

    if (!$existing && $password === '') {
        $errors[] = app_text('admin_access.password_required');
        return;
    }

    if ($existing) {
        $extra = admin_access_extra_payload($resellerPayload, $post, $existing);
        $params = [
            'id' => (int)$existing['id'],
            'name' => $resellerPayload['name'] ?? $email,
            'email' => $email,
            'phone' => $extra['phone'],
            'telegram_id' => $extra['telegram_id'],
            'max_id' => $extra['max_id'],
            'vk_id' => $extra['vk_id'],
            'referral_code' => $extra['referral_code'],
            'reseller_id' => $resellerId,
            'is_active' => $isActive,
        ];
        $passwordSql = '';
        if ($password !== '') {
            $passwordSql = ', password_hash = :password_hash';
            $params['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $stmt = db()->prepare(
            'UPDATE admin_users
             SET name = :name, email = :email, phone = :phone, telegram_id = :telegram_id, max_id = :max_id,
                 vk_id = :vk_id, referral_code = :referral_code, reseller_id = :reseller_id, manager_id = NULL,
                 is_active = :is_active' . $passwordSql . '
             WHERE id = :id'
        );
        $stmt->execute($params);
        return;
    }

    $extra = admin_access_extra_payload($resellerPayload, $post);
    $stmt = db()->prepare(
        'INSERT INTO admin_users (
            role, reseller_id, manager_id, name, email, password_hash, phone, telegram_id, max_id, vk_id, referral_code, is_active
         ) VALUES (
            "reseller", :reseller_id, NULL, :name, :email, :password_hash, :phone, :telegram_id, :max_id, :vk_id, :referral_code, :is_active
         )'
    );
    $stmt->execute([
        'reseller_id' => $resellerId,
        'name' => $resellerPayload['name'] ?? $email,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'phone' => $extra['phone'],
        'telegram_id' => $extra['telegram_id'],
        'max_id' => $extra['max_id'],
        'vk_id' => $extra['vk_id'],
        'referral_code' => $extra['referral_code'],
        'is_active' => $isActive,
    ]);
}

function save_manager_admin_access(int $managerId, array $managerPayload, array $post, array &$errors): void
{
    $email = trim((string)($post['admin_email'] ?? ''));
    $password = (string)($post['admin_password'] ?? '');
    $isActive = isset($post['admin_is_active']) ? 1 : 0;
    $existing = manager_admin_access($managerId);

    if ($email === '' && $password === '' && !$existing) {
        return;
    }

    if ($email === '') {
        $errors[] = app_text('admin_access.email_required');
        return;
    }

    if (!$existing && $password === '') {
        $errors[] = app_text('admin_access.password_required');
        return;
    }

    $resellerId = $managerPayload['reseller_id'] !== null && $managerPayload['reseller_id'] !== ''
        ? (int)$managerPayload['reseller_id']
        : null;

    if ($existing) {
        $extra = admin_access_extra_payload($managerPayload, $post, $existing);
        $params = [
            'id' => (int)$existing['id'],
            'name' => $managerPayload['name'] ?? $email,
            'email' => $email,
            'phone' => $extra['phone'],
            'telegram_id' => $extra['telegram_id'],
            'max_id' => $extra['max_id'],
            'vk_id' => $extra['vk_id'],
            'referral_code' => $extra['referral_code'],
            'reseller_id' => $resellerId,
            'manager_id' => $managerId,
            'is_active' => $isActive,
        ];
        $passwordSql = '';
        if ($password !== '') {
            $passwordSql = ', password_hash = :password_hash';
            $params['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $stmt = db()->prepare(
            'UPDATE admin_users
             SET name = :name, email = :email, phone = :phone, telegram_id = :telegram_id, max_id = :max_id,
                 vk_id = :vk_id, referral_code = :referral_code, reseller_id = :reseller_id, manager_id = :manager_id,
                 is_active = :is_active' . $passwordSql . '
             WHERE id = :id'
        );
        $stmt->execute($params);
        return;
    }

    $extra = admin_access_extra_payload($managerPayload, $post);
    $stmt = db()->prepare(
        'INSERT INTO admin_users (
            role, reseller_id, manager_id, name, email, password_hash, phone, telegram_id, max_id, vk_id, referral_code, is_active
         ) VALUES (
            "manager", :reseller_id, :manager_id, :name, :email, :password_hash, :phone, :telegram_id, :max_id, :vk_id, :referral_code, :is_active
         )'
    );
    $stmt->execute([
        'reseller_id' => $resellerId,
        'manager_id' => $managerId,
        'name' => $managerPayload['name'] ?? $email,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'phone' => $extra['phone'],
        'telegram_id' => $extra['telegram_id'],
        'max_id' => $extra['max_id'],
        'vk_id' => $extra['vk_id'],
        'referral_code' => $extra['referral_code'],
        'is_active' => $isActive,
    ]);
}

function save_record(string $moduleKey, array $module, array $payload, ?int $id, array $admin): int
{
    $payload = apply_role_defaults($moduleKey, $payload, $admin, $id);
    $columns = array_keys($payload);

    if ($id) {
        $before = null;
        if ($moduleKey === 'users') {
            $beforeStmt = db()->prepare('SELECT reseller_id, manager_id, client_stage FROM end_users WHERE id = :id LIMIT 1');
            $beforeStmt->execute(['id' => $id]);
            $before = $beforeStmt->fetch();
        } elseif ($moduleKey === 'leads') {
            $beforeStmt = db()->prepare('SELECT end_user_id, reseller_id, manager_id FROM leads WHERE id = :id LIMIT 1');
            $beforeStmt->execute(['id' => $id]);
            $before = $beforeStmt->fetch();
        }

        $assignments = implode(', ', array_map(static fn($column) => "`$column` = :$column", $columns));
        $payload['id'] = $id;
        $stmt = db()->prepare("UPDATE {$module['table']} SET $assignments WHERE id = :id");
        $stmt->execute($payload);
        log_activity('admin', (int)$admin['id'], 'update_' . $module['table'], $module['table'], $id);

        if ($moduleKey === 'users' && $before) {
            $oldResellerId = $before['reseller_id'] !== null ? (int)$before['reseller_id'] : null;
            $oldManagerId = $before['manager_id'] !== null ? (int)$before['manager_id'] : null;
            $newResellerId = $payload['reseller_id'] !== null ? (int)$payload['reseller_id'] : null;
            $newManagerId = $payload['manager_id'] !== null ? (int)$payload['manager_id'] : null;
            if ($oldResellerId !== $newResellerId || $oldManagerId !== $newManagerId) {
                $updatedLeads = sync_active_leads_assignment($id, $newResellerId, $newManagerId);
                $updatedNotifications = sync_consultant_notifications_assignment($id, $newResellerId, $newManagerId);
                log_end_user_transfer(
                    $id,
                    $oldResellerId,
                    $oldManagerId,
                    $newResellerId,
                    $newManagerId,
                    $admin,
                    'transfer_end_user',
                    [
                        'updated_active_leads' => $updatedLeads,
                        'updated_unread_notifications' => $updatedNotifications,
                    ]
                );
            }
            $oldStage = (string)($before['client_stage'] ?? 'new');
            $newStage = (string)($payload['client_stage'] ?? $oldStage);
            if ($oldStage !== $newStage) {
                $touchStage = db()->prepare('UPDATE end_users SET stage_updated_at = NOW() WHERE id = :id');
                $touchStage->execute(['id' => $id]);
                $history = db()->prepare(
                    'INSERT INTO client_stage_history (
                        end_user_id, previous_stage, new_stage, source, actor_id
                     ) VALUES (
                        :end_user_id, :previous_stage, :new_stage, :source, :actor_id
                     )'
                );
                $history->execute([
                    'end_user_id' => $id,
                    'previous_stage' => $oldStage,
                    'new_stage' => $newStage,
                    'source' => $admin['role'] === 'manager' ? 'consultant' : ($admin['role'] === 'reseller' ? 'leader' : 'admin'),
                    'actor_id' => $admin['id'],
                ]);
            }
        }
        if ($moduleKey === 'leads' && $before) {
            sync_user_assignment_from_lead($id, $before, $payload, $admin);
        }

        return $id;
    }

    $columnSql = implode(', ', array_map(static fn($column) => "`$column`", $columns));
    $placeholderSql = implode(', ', array_map(static fn($column) => ":$column", $columns));
    $stmt = db()->prepare("INSERT INTO {$module['table']} ($columnSql) VALUES ($placeholderSql)");
    $stmt->execute($payload);
    $newId = (int)db()->lastInsertId();
    log_activity('admin', (int)$admin['id'], 'create_' . $module['table'], $module['table'], $newId);
    return $newId;
}

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$errors = [];
$success = $_GET['success'] ?? null;
$editRow = null;
$createLimitErrors = create_limit_block_reasons($moduleKey, $admin);
$canCreate = crud_create_enabled($moduleKey) && !$createLimitErrors;
$canEdit = crud_edit_enabled($moduleKey);
$canDelete = crud_delete_enabled($moduleKey);
$formFields = crud_form_fields($moduleKey, $module['fields']);
if ($moduleKey === 'broadcasts') {
    $formFields = broadcast_form_fields_for_admin($formFields, $admin);
}
if (in_array($moduleKey, owned_modules(), true) && $admin['role'] !== 'superadmin') {
    unset($formFields['owner_type'], $formFields['owner_id']);
}
if ($moduleKey === 'integrations' && $admin['role'] !== 'superadmin') {
    unset($formFields['owner_type'], $formFields['owner_id']);
}
if ($moduleKey === 'resellers' && $admin['role'] === 'reseller') {
    unset($formFields['parent_reseller_id']);
}

if ($action === 'limit_check') {
    header('Content-Type: application/json; charset=utf-8');

    if (!in_array($moduleKey, ['managers', 'resellers'], true)) {
        echo json_encode(['ok' => true, 'errors' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        verify_csrf();
        $recordId = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
        $payload = collect_payload($formFields);
        $payload = normalize_module_payload($moduleKey, $payload);
        $payload = apply_role_defaults($moduleKey, $payload, $admin, $recordId);
        $limitPayload = $payload;
        if ($moduleKey === 'resellers') {
            $planErrors = subscription_plan_validate_reseller_payload($payload, $admin);
            if (!$planErrors) {
                $limitPayload = subscription_plan_apply_to_reseller_payload($payload, $admin);
            }
        }
        $limitErrors = array_merge(
            $planErrors ?? [],
            validate_child_limit_caps($moduleKey, $limitPayload, $recordId),
            validate_manager_limit_payload($moduleKey, $limitPayload, $recordId),
            validate_leader_limit_payload($moduleKey, $limitPayload, $recordId),
        );

        echo json_encode([
            'ok' => count($limitErrors) === 0,
            'errors' => array_values(array_unique($limitErrors)),
            'field_limits' => child_limit_field_caps($moduleKey, $payload, $recordId),
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'errors' => ['Не удалось проверить лимиты: ' . $e->getMessage()],
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($moduleKey === 'users' && $action === 'merge_search') {
    header('Content-Type: application/json; charset=utf-8');

    $targetUserId = (int)($_GET['id'] ?? 0);
    $query = trim((string)($_GET['q'] ?? ''));

    try {
        if (!$targetUserId) {
            http_response_code(404);
            echo json_encode(['items' => [], 'error' => app_text('user_merge.target_required')], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode(['items' => merge_user_search_results($targetUserId, $query, $admin)], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['items' => [], 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($action === 'create' && $createLimitErrors) {
    $errors[] = 'Лимит закончился. Новые записи этого типа сейчас нельзя добавить.';
    $errors = array_merge($errors, $createLimitErrors);
    $action = 'list';
} elseif ($action === 'create' && !$canCreate) {
    $errors[] = app_text('auto.k_868d1fd837c9');
    $action = 'list';
}

if ($action === 'edit' && !$canEdit) {
    $errors[] = app_text('auto.k_e26ff1144bac');
    $action = 'list';
}

$leadChatOnly = $moduleKey === 'leads'
    && $action === 'edit'
    && ((string)($_GET['chat_only'] ?? '') === '1' || (string)($_POST['chat_only'] ?? '') === '1');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $postAction = $_POST['action'] ?? 'save';
    $postId = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;

    if ($postAction === 'send_lead_response') {
        if ($moduleKey !== 'leads' || !$postId || !scoped_row_exists($moduleKey, $module, $postId, $admin)) {
            http_response_code(404);
            exit('Record not found');
        }

        try {
            $responseId = create_and_send_lead_response($postId, $admin, $errors);
        } catch (Throwable $e) {
            $responseId = null;
            $errors[] = app_text('auto.k_5cececf97899') . $e->getMessage();
        }
        if ($responseId && !$errors) {
            $sentPlatform = lead_response_platform($responseId);
            $sentPlatformQuery = $sentPlatform !== '' ? '&sent_platform=' . rawurlencode($sentPlatform) : '';
            $chatOnlyQuery = (string)($_POST['chat_only'] ?? '') === '1' ? '&chat_only=1' : '';
            redirect('crud.php?module=leads&action=edit&id=' . $postId . $chatOnlyQuery . '&success=response_sent' . $sentPlatformQuery);
        }
        $action = 'edit';
        $id = $postId;
    }

    if ($postAction === 'run_broadcast') {
        if ($moduleKey !== 'broadcasts' || !$postId || !scoped_row_exists($moduleKey, $module, $postId, $admin)) {
            http_response_code(404);
            exit('Record not found');
        }

        try {
            $postId = owned_content_editable_id('broadcasts', $postId, $admin);
            $result = run_broadcast($postId);
            redirect('crud.php?module=broadcasts&success=broadcast_sent&sent=' . (int)$result['sent'] . '&failed=' . (int)$result['failed']);
        } catch (Throwable $e) {
            $errors[] = app_text('broadcasts.run_failed') . $e->getMessage();
            $action = 'list';
        }
    }

    if ($moduleKey === 'tests' && $postId && str_starts_with($postAction, 'test_')) {
        if (!scoped_row_exists($moduleKey, $module, $postId, $admin)) {
            http_response_code(404);
            exit('Record not found');
        }
        $editablePostId = owned_content_editable_id('tests', $postId, $admin);
        if ($editablePostId !== $postId) {
            redirect('crud.php?module=tests&action=edit&id=' . $editablePostId . '&success=personal_copy');
        }
        handle_test_builder_action($postAction, $postId, $admin, $errors);
        if (!$errors) {
            redirect('crud.php?module=tests&action=edit&id=' . $postId . '&success=saved');
        }
        $action = 'edit';
        $id = $postId;
    }

    if ($postAction === 'merge_user') {
        if ($moduleKey !== 'users' || !$postId || !scoped_row_exists($moduleKey, $module, $postId, $admin)) {
            http_response_code(404);
            exit('Record not found');
        }

        $sourceUserId = (int)($_POST['source_user_id'] ?? 0);
        try {
            merge_end_users($postId, $sourceUserId, $admin);
            redirect('crud.php?module=users&action=edit&id=' . $postId . '&success=merged');
        } catch (Throwable $e) {
            $errors[] = app_text('user_merge.failed') . $e->getMessage();
        }
        $action = 'edit';
        $id = $postId;
    }

    if ($postAction === 'promote_user') {
        if ($moduleKey !== 'users' || !$postId || !scoped_row_exists($moduleKey, $module, $postId, $admin)) {
            http_response_code(404);
            exit('Record not found');
        }

        try {
            $promotion = promote_end_user_to_work_account($postId, $_POST, $admin);
            redirect(
                'crud.php?module=users&action=edit&id=' . $postId
                . '&success=promoted&promoted_module=' . rawurlencode((string)$promotion['module'])
                . '&promoted_id=' . (int)$promotion['id']
            );
        } catch (Throwable $e) {
            $errors[] = 'Не удалось сделать клиента рабочим аккаунтом: ' . $e->getMessage();
        }
        $action = 'edit';
        $id = $postId;
    }

    if ($postAction === 'link_staff_user') {
        if ($moduleKey !== 'users' || !$postId || !scoped_row_exists($moduleKey, $module, $postId, $admin)) {
            http_response_code(404);
            exit('Record not found');
        }

        try {
            $linked = link_end_user_to_work_account($postId, $_POST, $admin);
            redirect(
                'crud.php?module=users&action=edit&id=' . $postId
                . '&success=linked_staff&promoted_module=' . rawurlencode((string)$linked['module'])
                . '&promoted_id=' . (int)$linked['id']
            );
        } catch (Throwable $e) {
            $errors[] = 'Не удалось связать клиента с рабочим аккаунтом: ' . $e->getMessage();
        }
        $action = 'edit';
        $id = $postId;
    }

    if ($postAction === 'delete') {
        if (!$canDelete) {
            $errors[] = app_text('auto.k_da5ca3c5fc80');
        } else {
        if (!$postId || !scoped_row_exists($moduleKey, $module, $postId, $admin)) {
            http_response_code(404);
            exit('Record not found');
        }

        try {
            delete_crud_record($moduleKey, $module, $postId, $admin);
            redirect('crud.php?module=' . urlencode($moduleKey) . '&success=deleted');
        } catch (Throwable $e) {
            $errors[] = app_text('auto.k_cdec27146810') . $e->getMessage();
        }
        }
        $action = 'list';
    }

    if ($postAction === 'reset_owned_content') {
        if (!$postId || !scoped_row_exists($moduleKey, $module, $postId, $admin)) {
            http_response_code(404);
            exit('Record not found');
        }
        if (!owned_content_reset_for_admin($moduleKey, $postId, $admin)) {
            $errors[] = 'Не удалось сбросить запись: доступна только ваша личная версия, созданная из версии выше.';
        } else {
            redirect('crud.php?module=' . urlencode($moduleKey) . '&success=content_reset');
        }
        $action = 'list';
    }

    if ($moduleKey === 'site_templates' && $postAction === 'import_global_site_templates') {
        if (!$canCreate) {
            $errors[] = app_text('auto.k_6eaca3d4de92');
        } else {
            try {
                $summary = site_template_import_global_for_admin($admin);
                redirect(
                    'crud.php?module=site_templates&success=templates_imported'
                    . '&imported=' . (int)$summary['imported']
                    . '&restored=' . (int)$summary['restored']
                    . '&skipped=' . (int)$summary['skipped']
                );
            } catch (Throwable $e) {
                $errors[] = 'Не удалось импортировать базовые шаблоны: ' . $e->getMessage();
            }
        }
        $action = 'list';
    }

    if ($postAction === 'save') {
    if (($postId && !$canEdit) || (!$postId && !$canCreate)) {
        if (!$postId && $createLimitErrors) {
            $errors[] = 'Лимит закончился. Новые записи этого типа сейчас нельзя добавить.';
            $errors = array_merge($errors, $createLimitErrors);
        } else {
            $errors[] = $postId
                ? app_text('auto.k_fd8f8d50baa8')
                : app_text('auto.k_6eaca3d4de92');
        }
        $action = 'list';
    } else {
    if ($postId && !scoped_row_exists($moduleKey, $module, $postId, $admin)) {
        http_response_code(404);
        exit('Record not found');
    }

    if ($postId && owned_content_config($moduleKey)) {
        $postId = owned_content_editable_id($moduleKey, $postId, $admin);
        $id = $postId;
    }

    $templateId = nullable_int_value($_POST['template_id'] ?? null);
    if ($templateId && in_array($moduleKey, ['managers', 'resellers'], true) && !site_template_row($templateId, $admin)) {
        $errors[] = 'Шаблон мини-сайта недоступен для вашей ветки.';
    }
    $payload = collect_payload($formFields);
    if ($moduleKey === 'site_templates') {
        $payload = site_template_apply_editor_payload($payload, $_POST);
    }
    $payload = normalize_module_payload($moduleKey, $payload);
    $payload = apply_file_uploads($moduleKey, $formFields, $payload, $errors);
    $errors = array_merge($errors, validate_payload($formFields, $payload));
    $payload = apply_role_defaults($moduleKey, $payload, $admin, $postId);
    $limitPayload = $payload;
    if ($moduleKey === 'resellers') {
        $errors = array_merge($errors, subscription_plan_validate_reseller_payload($payload, $admin));
        if (!$errors) {
            $limitPayload = subscription_plan_apply_to_reseller_payload($payload, $admin);
        }
    }
    $errors = array_merge($errors, validate_unique_payload($moduleKey, $module, $payload, $postId));
    $errors = array_merge($errors, validate_scope_payload($moduleKey, $payload, $admin, $postId));
    $errors = array_merge($errors, validate_child_limit_caps($moduleKey, $limitPayload, $postId));
    $errors = array_merge($errors, validate_manager_limit_payload($moduleKey, $limitPayload, $postId));
    $errors = array_merge($errors, validate_leader_limit_payload($moduleKey, $limitPayload, $postId));
    if (!$errors) {
        try {
            $savedId = save_record($moduleKey, $module, $payload, $postId, $admin);
            if ($moduleKey === 'managers' && can_manage_team_admin_access($moduleKey, $admin, $savedId)) {
                save_manager_admin_access($savedId, $payload, $_POST, $errors);
                if ($errors) {
                    $action = $postId ? 'edit' : 'create';
                    $id = $savedId;
                    $editRow = $payload + ['id' => $savedId, 'template_id' => $templateId];
                }
            }
            if ($moduleKey === 'resellers' && can_manage_team_admin_access($moduleKey, $admin, $savedId)) {
                save_reseller_admin_access($savedId, $payload, $_POST, $errors);
                if ($errors) {
                    $action = $postId ? 'edit' : 'create';
                    $id = $savedId;
                    $editRow = $payload + ['id' => $savedId, 'template_id' => $templateId];
                }
            }
            if (!$errors && in_array($moduleKey, ['managers', 'resellers'], true)) {
                $ownerType = $moduleKey === 'managers' ? 'manager' : 'reseller';
                $profile = ensure_consultant_profile($ownerType, $savedId);
                $profileId = (int)($profile['id'] ?? 0);
                if ($profileId <= 0) {
                    throw new RuntimeException('Не удалось создать профиль мини-сайта.');
                }
                $currentTemplateId = nullable_int_value($profile['template_id'] ?? null);
                $inheritsProfile = consultant_profile_inherits($profile);
                if ($templateId && ($currentTemplateId !== $templateId || $inheritsProfile)) {
                    site_template_apply_to_profile($profileId, $ownerType, $savedId, $templateId);
                } elseif (!$templateId && !$inheritsProfile) {
                    $parentProfile = consultant_parent_profile($ownerType, $savedId);
                    if ($parentProfile) {
                        consultant_profile_reset_to_parent($profileId, (int)$parentProfile['id']);
                    }
                }
            }
            if (!$errors) {
                redirect('crud.php?module=' . urlencode($moduleKey) . '&success=saved');
            }
            $postId = $savedId;
        } catch (Throwable $e) {
            $errors[] = friendly_save_error($e, $payload);
        }
    }

    $editRow = $payload + ['id' => $postId, 'template_id' => $templateId];
    $action = $postId ? 'edit' : 'create';
    }
    }
}

if ($action === 'edit' && $id) {
    if (!scoped_row_exists($moduleKey, $module, $id, $admin)) {
        http_response_code(404);
        exit('Record not found');
    }
    if (owned_content_config($moduleKey) && $admin['role'] !== 'superadmin') {
        $editableId = owned_content_editable_id($moduleKey, $id, $admin);
        if ($editableId !== $id) {
            redirect('crud.php?module=' . urlencode($moduleKey) . '&action=edit&id=' . $editableId . '&success=personal_copy');
        }
    }
    $stmt = db()->prepare("SELECT * FROM {$module['table']} WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $editRow = $stmt->fetch() ?: null;
    if (!$editRow) {
        $errors[] = 'Запись #' . (int)$id . ' не найдена или уже удалена.';
    } elseif (in_array($moduleKey, ['managers', 'resellers'], true)) {
        $editRow['template_id'] = profile_template_id_for_module($moduleKey, (int)$editRow['id']);
    }
}

if ($leadChatOnly && !$editRow) {
    $errors[] = 'Чат не найден. Вернитесь к списку обращений и откройте актуальную карточку клиента.';
}

$rows = [];
$listHtml = '';
$listMeta = [];
$displayColumns = crud_display_columns($moduleKey);
$limitCheckUrl = in_array($moduleKey, ['managers', 'resellers'], true)
    ? 'crud.php?module=' . urlencode($moduleKey) . '&action=limit_check'
    : '';
try {
    if (!$leadChatOnly && $action === 'list') {
        [$listSql, $params] = crud_list_query($moduleKey, $module, $admin);
        if ($moduleKey === 'leads') {
            $stmt = db()->prepare($listSql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
        } else {
            $pageData = crud_paginated_rows($moduleKey, $listSql, $params, $displayColumns);
            $rows = $pageData['rows'];
            $listMeta = $pageData['meta'];
        }
        $listHtml = render_crud_list($moduleKey, $displayColumns, $rows, $canEdit, $canDelete, $admin, $listMeta);
    }
} catch (Throwable $e) {
    $errors[] = app_text('auto.k_49fb23bb29cf') . $e->getMessage();
    $listHtml = app_text('auto.k_fda0c24ca2e9');
}

$adminAccess = null;
$adminAccessRecordId = !empty($editRow['id']) ? (int)$editRow['id'] : null;
$canManageAdminAccess = can_manage_team_admin_access($moduleKey, $admin, $adminAccessRecordId);
if ($canManageAdminAccess && ($action === 'create' || $action === 'edit')) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $adminAccess = [
            'email' => trim((string)($_POST['admin_email'] ?? '')),
            'is_active' => isset($_POST['admin_is_active']) ? 1 : 0,
        ];
    } elseif (!empty($editRow['id'])) {
        $adminAccess = $moduleKey === 'managers'
            ? manager_admin_access((int)$editRow['id'])
            : reseller_admin_access((int)$editRow['id']);
    }
}

$limitFieldCaps = [];
if (in_array($moduleKey, ['managers', 'resellers'], true) && ($action === 'create' || $action === 'edit')) {
    $limitSeed = [];
    foreach ($formFields as $fieldName => $fieldConfig) {
        if (!empty($fieldConfig['virtual']) || !empty($fieldConfig['readonly'])) {
            continue;
        }
        $limitSeed[$fieldName] = $editRow[$fieldName] ?? ($fieldConfig['default'] ?? null);
    }
    $limitSeed = normalize_module_payload($moduleKey, $limitSeed);
    $limitSeed = apply_role_defaults($moduleKey, $limitSeed, $admin, isset($editRow['id']) ? (int)$editRow['id'] : null);
    $limitFieldCaps = child_limit_field_caps($moduleKey, $limitSeed, isset($editRow['id']) ? (int)$editRow['id'] : null);
}

require __DIR__ . '/../app/views/layouts/header.php';
?>
<div class="toolbar">
    <h1><?= h($title) ?></h1>
    <?php if ($leadChatOnly): ?>
        <a class="button secondary-button" href="crud.php?module=leads">К списку обращений</a>
    <?php elseif ($canCreate): ?>
        <div class="toolbar-actions">
            <?php if ($moduleKey === 'site_templates' && site_template_current_owner($admin)): ?>
                <form method="post" class="inline-form toolbar-inline-form">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="import_global_site_templates">
                    <button type="submit" class="secondary-button">Импортировать базовые</button>
                </form>
            <?php endif; ?>
            <a class="button" href="crud.php?module=<?= h($moduleKey) ?>&action=create"><?= h(app_text('auto.k_559a87f7cc13')) ?></a>
        </div>
    <?php endif; ?>
</div>
<?php if ($success === 'saved'): ?>
    <div class="notice success"><?= h(app_text('auto.k_ead4c298eba3')) ?></div>
<?php elseif ($success === 'deleted'): ?>
    <div class="notice success"><?= h(app_text('auto.k_5db71cdc4927')) ?></div>
<?php elseif ($success === 'response_sent'): ?>
    <?php $sentPlatformLabel = platform_label((string)($_GET['sent_platform'] ?? '')); ?>
    <div class="notice success"><?= h(app_text('auto.k_0184f257cbfc', ['platform' => $sentPlatformLabel !== '' ? $sentPlatformLabel : 'платформу заявки'])) ?></div>
<?php elseif ($success === 'merged'): ?>
    <div class="notice success"><?= h(app_text('user_merge.success')) ?></div>
<?php elseif ($success === 'promoted' || $success === 'linked_staff'): ?>
    <?php
    $promotedModule = (string)($_GET['promoted_module'] ?? '');
    $promotedId = (int)($_GET['promoted_id'] ?? 0);
    $promotedLabel = $promotedModule === 'resellers' ? 'лидеров' : 'консультантов';
    $promotedText = $success === 'promoted'
        ? 'Клиент преобразован в рабочий аккаунт и теперь отображается в разделе ' . $promotedLabel . '.'
        : 'Клиент связан с рабочим аккаунтом и теперь отображается в разделе ' . $promotedLabel . '.';
    $promotedUrl = in_array($promotedModule, ['managers', 'resellers'], true) && $promotedId > 0
        ? 'crud.php?module=' . rawurlencode($promotedModule) . '&action=edit&id=' . $promotedId
        : '';
    ?>
    <div class="notice success">
        <?= h($promotedText) ?>
        <?php if ($promotedUrl !== ''): ?>
            <a href="<?= h($promotedUrl) ?>">Открыть запись</a>
        <?php endif; ?>
    </div>
<?php elseif ($success === 'personal_copy'): ?>
    <div class="notice success"><?= h(app_text('content_ownership.personal_copy')) ?></div>
<?php elseif ($success === 'content_reset'): ?>
    <div class="notice success">Личная версия сброшена. Теперь снова используется версия выше.</div>
<?php elseif ($success === 'templates_imported'): ?>
    <?php
    $importedTemplates = (int)($_GET['imported'] ?? 0);
    $restoredTemplates = (int)($_GET['restored'] ?? 0);
    $skippedTemplates = (int)($_GET['skipped'] ?? 0);
    ?>
    <div class="notice success">
        Базовые шаблоны импортированы.
        Новых: <?= $importedTemplates ?>,
        восстановлено: <?= $restoredTemplates ?>,
        уже были: <?= $skippedTemplates ?>.
    </div>
<?php elseif ($success === 'broadcast_sent'): ?>
    <div class="notice success"><?= h(app_text('broadcasts.run_success', [
        'sent' => (int)($_GET['sent'] ?? 0),
        'failed' => (int)($_GET['failed'] ?? 0),
    ])) ?></div>
<?php endif; ?>
<?php foreach ($errors as $error): ?>
    <div class="alert"><?= h($error) ?></div>
<?php endforeach; ?>
<?php if ($createLimitErrors && $action === 'list' && in_array($moduleKey, ['managers', 'resellers'], true)): ?>
    <div class="notice warning">
        <strong>Лимит закончился.</strong>
        Чтобы добавить новых участников, увеличьте лимит в подписке.
        <?php foreach ($createLimitErrors as $limitError): ?>
            <br><?= h($limitError) ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php if ($moduleKey === 'integrations'): ?>
    <?= render_vk_connection_help_link() ?>
<?php endif; ?>
<?php if (($action === 'create' || $action === 'edit') && !$leadChatOnly): ?>
    <section class="panel form-panel">
        <h2><?= h(crud_form_title($moduleKey, $action)) ?></h2>
        <form method="post" class="crud-form" enctype="multipart/form-data" <?= $limitCheckUrl !== '' ? 'data-limit-check-url="' . h($limitCheckUrl) . '"' : '' ?>>
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= h((string)($editRow['id'] ?? '')) ?>">
            <?php if ($limitCheckUrl !== ''): ?>
                <div class="limit-check-message" data-limit-check-message hidden></div>
            <?php endif; ?>
            <?php foreach ($formFields as $name => $field): ?>
                <?php
                $type = $field['type'] ?? 'text';
                $value = $editRow[$name] ?? ($field['default'] ?? '');
                if ($moduleKey === 'leads' && $action === 'edit' && $name === 'message') {
                    $leadAttachments = render_lead_attachments(
                        $editRow['attachments_json'] ?? null,
                        (string)($editRow['message'] ?? ''),
                        'lead-edit-attachments-list'
                    );
                    if ($leadAttachments !== '') {
                        echo '<div class="field lead-edit-attachments"><span>Вложения клиента</span>' . $leadAttachments . '</div>';
                    }
                    if (!empty($editRow['attachments_json'])) {
                        $value = lead_display_message((string)$value);
                    }
                }
                if ($moduleKey === 'site_templates' && in_array($name, ['profile_json', 'blocks_json'], true)) {
                    echo '<textarea name="' . h($name) . '" hidden>' . h((string)$value) . '</textarea>';
                    continue;
                }
                ?>
                <label class="field">
                    <span class="field-label-line">
                        <span><?= h($field['label'] ?? $name) ?><?= ($field['required'] ?? false) ? ' *' : '' ?></span>
                        <?php if (!empty($field['help']) && is_array($field['help'])): ?>
                            <button
                                type="button"
                                class="field-info-button"
                                aria-label="Показать подсказку"
                                data-image-preview
                                data-image-src="<?= h((string)($field['help']['image'] ?? '')) ?>"
                                data-image-title="<?= h((string)($field['help']['title'] ?? ($field['label'] ?? $name))) ?>"
                                data-image-caption="<?= h((string)($field['help']['text'] ?? '')) ?>"
                            >i</button>
                        <?php endif; ?>
                    </span>
                    <?php if ($type === 'textarea'): ?>
                        <textarea name="<?= h($name) ?>" rows="<?= max(3, (int)($field['rows'] ?? 4)) ?>" <?= !empty($field['readonly']) ? 'readonly' : '' ?>><?= h((string)$value) ?></textarea>
                    <?php elseif ($type === 'select'): ?>
                        <select name="<?= h($name) ?>">
                            <?php if ($field['nullable'] ?? false): ?>
                                <option value=""><?= h((string)($field['nullable_label'] ?? app_text('auto.k_24da5932344a'))) ?></option>
                            <?php endif; ?>
                            <?php if (isset($field['options'])): ?>
                                <?php foreach ($field['options'] as $option): ?>
                                    <option value="<?= h($option) ?>" <?= (string)$value === (string)$option ? 'selected' : '' ?>><?= h(form_option_label($name, $option)) ?></option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php foreach (safe_select_options($field['source'], $admin, $errors) as $option): ?>
                                    <option value="<?= (int)$option['id'] ?>" <?= (string)$value === (string)$option['id'] ? 'selected' : '' ?>>
                                        #<?= (int)$option['id'] ?> <?= h($option['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    <?php elseif ($type === 'file'): ?>
                        <?php if ($value): ?>
                            <div class="file-control">
                                <span class="cell-muted"><?= h(app_text('media.current_file')) ?></span>
                                <a class="file-link" href="<?= h((string)$value) ?>" target="_blank" rel="noopener"><?= h(app_text('media.open_file')) ?></a>
                            <?php if (($field['accept'] ?? '') === 'image/*'): ?>
                                <img class="file-preview" src="<?= h((string)$value) ?>" alt="">
                            <?php endif; ?>
                                <label class="checkbox-line file-remove">
                                    <input type="checkbox" name="remove_file[<?= h($name) ?>]" value="1">
                                    <?= h(app_text('media.remove_current_file')) ?>
                                </label>
                            </div>
                        <?php endif; ?>
                        <input type="hidden" name="<?= h($name) ?>_current" value="<?= h((string)$value) ?>">
                        <input type="file" name="<?= h($name) ?>" <?= isset($field['accept']) ? 'accept="' . h($field['accept']) . '"' : '' ?>>
                    <?php elseif ($type === 'checkbox'): ?>
                        <input type="checkbox" name="<?= h($name) ?>" value="1" <?= (int)$value === 1 ? 'checked' : '' ?>>
                    <?php else: ?>
                        <?php $inputValue = $type === 'datetime-local' ? datetime_for_input($value ? (string)$value : null) : (string)$value; ?>
                        <?php
                        $isLimitField = $moduleKey === 'resellers' && in_array($name, [
                            'direct_leader_limit',
                            'branch_leader_limit',
                            'direct_manager_limit',
                            'branch_manager_limit',
                            'per_child_manager_limit',
                        ], true);
                        $limitCap = $isLimitField ? ($limitFieldCaps[$name] ?? null) : null;
                        ?>
                        <input
                            type="<?= h($type) ?>"
                            name="<?= h($name) ?>"
                            value="<?= h($inputValue) ?>"
                            <?= isset($field['step']) ? 'step="' . h($field['step']) . '"' : '' ?>
                            <?= isset($field['min']) ? 'min="' . h((string)$field['min']) . '"' : '' ?>
                            <?= $limitCap ? 'max="' . h((string)$limitCap['max']) . '"' : '' ?>
                            <?= $isLimitField ? 'data-limit-field="' . h($name) . '"' : '' ?>
                            <?= $limitCap ? 'data-limit-max="' . h((string)$limitCap['max']) . '" data-limit-source="' . h((string)$limitCap['source']) . '"' : '' ?>
                            <?= !empty($field['readonly']) ? 'readonly' : '' ?>
                        >
                        <?php if ($isLimitField): ?>
                            <small class="limit-field-message" data-limit-field-message="<?= h($name) ?>" hidden></small>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if (!empty($field['hint'])): ?>
                        <small class="field-hint"><?= h((string)$field['hint']) ?></small>
                    <?php endif; ?>
                </label>
                <?php if ($moduleKey === 'site_templates' && $name === 'description'): ?>
                    <?php render_site_template_editor($editRow ?: null); ?>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if ($moduleKey === 'broadcasts'): ?>
                <section class="field wide broadcast-preview" id="broadcast-preview">
                    <span>Предварительный просмотр</span>
                    <strong id="broadcast-preview-title"><?= h((string)($editRow['title'] ?? 'Заголовок рассылки')) ?></strong>
                    <p id="broadcast-preview-text"><?= nl2br(h((string)($editRow['message_text'] ?? 'Текст сообщения'))) ?></p>
                    <div id="broadcast-preview-media"></div>
                </section>
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        const form = document.querySelector('.crud-form');
                        const title = document.querySelector('#broadcast-preview-title');
                        const text = document.querySelector('#broadcast-preview-text');
                        const media = document.querySelector('#broadcast-preview-media');
                        if (!form || !title || !text || !media) return;
                        const update = () => {
                            title.textContent = form.elements.title?.value || 'Заголовок рассылки';
                            text.textContent = form.elements.message_text?.value || 'Текст сообщения';
                        };
                        form.elements.title?.addEventListener('input', update);
                        form.elements.message_text?.addEventListener('input', update);
                        ['image_path', 'video_path'].forEach((name) => {
                            form.elements[name]?.addEventListener('change', (event) => {
                                const file = event.target.files?.[0];
                                if (!file) return;
                                const url = URL.createObjectURL(file);
                                media.innerHTML = name === 'video_path'
                                    ? `<video controls src="${url}"></video>`
                                    : `<img src="${url}" alt="">`;
                            });
                        });
                    });
                </script>
            <?php endif; ?>
            <?php if ($canManageAdminAccess): ?>
                <fieldset class="field admin-access-group">
                    <legend><?= h(app_text('admin_access.title')) ?></legend>
                    <label class="field">
                        <span><?= h(app_text('admin_access.email')) ?></span>
                        <input type="email" name="admin_email" value="<?= h((string)($adminAccess['email'] ?? '')) ?>">
                    </label>
                    <label class="field">
                        <span><?= h(app_text('admin_access.password')) ?></span>
                        <input type="password" name="admin_password" autocomplete="new-password">
                    </label>
                    <?php if (!empty($adminAccess['id'])): ?>
                        <p class="cell-muted"><?= h(app_text('admin_access.password_hint')) ?></p>
                    <?php endif; ?>
                    <label class="checkbox-line">
                        <input type="checkbox" name="admin_is_active" value="1" <?= (int)($adminAccess['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                        <?= h(app_text('admin_access.active')) ?>
                    </label>
                </fieldset>
            <?php endif; ?>
            <div class="form-actions">
                <button type="submit"><?= h(app_text('auto.k_4864057d626a')) ?></button>
                <a class="button secondary-button" href="crud.php?module=<?= h($moduleKey) ?>"><?= h(app_text('auto.k_0ec753be8df9')) ?></a>
            </div>
        </form>
    </section>
    <?php if ($moduleKey === 'tests' && $action === 'edit' && $editRow): ?>
        <?= render_test_builder((int)$editRow['id'], $admin) ?>
    <?php endif; ?>
    <?php if ($moduleKey === 'users' && $action === 'edit' && $editRow): ?>
        <?php $consentStatus = client_onboarding_status($editRow); ?>
        <section class="panel form-panel">
            <h2>Анкета и согласия</h2>
            <p><strong>Этап:</strong> <?= h(user_client_stage_label($editRow)) ?></p>
            <p><strong>Анкета:</strong> <?= $consentStatus['profile_complete'] ? 'заполнена' : 'не завершена' ?></p>
            <p><strong>Обязательные согласия:</strong> <?= $consentStatus['missing_consents'] ? 'не подтверждены полностью' : 'подтверждены' ?></p>
            <p><strong>Информационные рассылки:</strong> <?= $consentStatus['marketing_consent'] ? 'разрешены' : 'не разрешены' ?></p>
            <a
                class="button secondary-button"
                href="results.php?user_id=<?= (int)$editRow['id'] ?>"
                data-admin-modal-url="results.php?user_id=<?= (int)$editRow['id'] ?>&modal=1"
            >Результаты чек-апов</a>
        </section>

        <?php if (user_promotion_allowed($admin)): ?>
            <?php
            $promotionCanPromote = user_promotion_can_promote_row($editRow, $admin);
            $promotionName = user_promotion_full_name($editRow);
            $promotionReferralCode = user_promotion_unique_referral_code($editRow);
            $promotionPlatformIds = user_promotion_platform_identities($editRow);
            $promotionDefaultResellerId = nullable_int_value($editRow['reseller_id'] ?? null);
            if (!$promotionDefaultResellerId && ($admin['role'] ?? '') === 'reseller') {
                $promotionDefaultResellerId = (int)$admin['reseller_id'];
            }
            try {
                $promotionExistingStaff = user_promotion_existing_staff_candidates($editRow, $admin);
            } catch (Throwable $e) {
                $promotionExistingStaff = [];
            }
            try {
                $promotionResellers = team_reseller_options_for_admin($admin, true);
            } catch (Throwable $e) {
                $promotionResellers = [];
            }
            try {
                $promotionTemplates = site_template_options($admin);
            } catch (Throwable $e) {
                $promotionTemplates = [];
            }
            ?>
            <section class="panel form-panel">
                <h2>Сделать рабочим аккаунтом</h2>
                <?php if (!$promotionCanPromote): ?>
                    <div class="empty-state">Этот клиент сейчас недоступен для преобразования.</div>
                <?php else: ?>
                    <p class="cell-muted">
                        Если клиент стал консультантом или лидером, создайте ему рабочий аккаунт или свяжите клиента с уже созданной записью.
                        После этого он будет видеть свою страницу, получит доступ в админку и появится в нужной таблице.
                    </p>

                    <?php if ($promotionExistingStaff): ?>
                        <form
                            method="post"
                            class="crud-form"
                            onsubmit="return confirm('Связать клиента с выбранным рабочим аккаунтом?');"
                        >
                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                            <input type="hidden" name="action" value="link_staff_user">
                            <input type="hidden" name="id" value="<?= h((string)$editRow['id']) ?>">
                            <label class="field">
                                <span>Уже созданный консультант или лидер</span>
                                <select name="existing_staff_ref" required>
                                    <option value="">Выберите запись</option>
                                    <?php foreach ($promotionExistingStaff as $staffCandidate): ?>
                                        <option value="<?= h((string)$staffCandidate['key']) ?>">
                                            <?= h((string)$staffCandidate['label']) ?>
                                            <?php if (!empty($staffCandidate['details'])): ?>
                                                - <?= h((string)$staffCandidate['details']) ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="field">
                                <span>Email для входа, если доступа ещё нет</span>
                                <input type="email" name="link_admin_email" value="<?= h((string)($editRow['email'] ?? '')) ?>">
                            </label>
                            <label class="field">
                                <span>Пароль, если доступа ещё нет</span>
                                <input type="password" name="link_admin_password" autocomplete="new-password">
                            </label>
                            <div class="form-actions">
                                <button type="submit" class="secondary-button">Связать с существующим</button>
                            </div>
                        </form>
                    <?php endif; ?>

                    <form
                        method="post"
                        class="crud-form"
                        onsubmit="return confirm('Создать рабочий аккаунт из этого клиента?');"
                    >
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="action" value="promote_user">
                        <input type="hidden" name="id" value="<?= h((string)$editRow['id']) ?>">
                        <label class="field">
                            <span>Кем сделать клиента</span>
                            <select name="promotion_target" required>
                                <option value="manager">Консультантом</option>
                                <option value="reseller">Лидером</option>
                            </select>
                        </label>

                        <?php if (($admin['role'] ?? '') === 'superadmin'): ?>
                            <label class="field">
                                <span>Лидер для консультанта</span>
                                <select name="promotion_reseller_id">
                                    <option value="">Выберите лидера</option>
                                    <?php foreach ($promotionResellers as $resellerOption): ?>
                                        <option
                                            value="<?= (int)$resellerOption['id'] ?>"
                                            <?= $promotionDefaultResellerId === (int)$resellerOption['id'] ? 'selected' : '' ?>
                                        ><?= h((string)$resellerOption['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="field">
                                <span>Вышестоящий лидер для нового лидера</span>
                                <select name="promotion_parent_reseller_id">
                                    <option value="">Без вышестоящего лидера</option>
                                    <?php foreach ($promotionResellers as $resellerOption): ?>
                                        <option
                                            value="<?= (int)$resellerOption['id'] ?>"
                                            <?= $promotionDefaultResellerId === (int)$resellerOption['id'] ? 'selected' : '' ?>
                                        ><?= h((string)$resellerOption['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        <?php else: ?>
                            <p class="cell-muted">Новый консультант или лидер будет создан в вашей ветке.</p>
                        <?php endif; ?>

                        <label class="field">
                            <span>Шаблон мини-сайта</span>
                            <select name="promotion_template_id">
                                <option value="">Как у вышестоящего лидера</option>
                                <?php foreach ($promotionTemplates as $templateOption): ?>
                                    <option value="<?= (int)$templateOption['id'] ?>"><?= h((string)$templateOption['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="field">
                            <span>Имя в системе</span>
                            <input type="text" name="promotion_name" value="<?= h($promotionName) ?>" required>
                        </label>
                        <label class="field">
                            <span>Email для входа в админку</span>
                            <input type="email" name="admin_email" value="<?= h((string)($editRow['email'] ?? '')) ?>" required>
                        </label>
                        <label class="field">
                            <span>Телефон</span>
                            <input type="text" name="admin_phone" value="<?= h((string)($editRow['phone'] ?? '')) ?>">
                        </label>
                        <label class="field">
                            <span>Реферальный код</span>
                            <input type="text" name="promotion_referral_code" value="<?= h($promotionReferralCode) ?>" required>
                        </label>
                        <label class="field">
                            <span>Пароль для входа в админку</span>
                            <input type="password" name="admin_password" autocomplete="new-password" required>
                        </label>
                        <label class="checkbox-line">
                            <input type="checkbox" name="admin_is_active" value="1" checked>
                            Доступ в админку активен
                        </label>
                        <?php if (array_filter($promotionPlatformIds)): ?>
                            <div class="compact-lines">
                                <strong>Платформы, которые будут перенесены</strong>
                                <?php foreach (['telegram_id' => 'Telegram', 'vk_id' => 'VK', 'max_id' => 'MAX'] as $platformField => $platformLabel): ?>
                                    <?php if (!empty($promotionPlatformIds[$platformField])): ?>
                                        <span><?= h($platformLabel) ?>: <?= h((string)$promotionPlatformIds[$platformField]) ?></span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <div class="form-actions">
                            <button type="submit">Создать рабочий аккаунт</button>
                        </div>
                    </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="panel form-panel">
            <h2><?= h(app_text('user_platforms.title')) ?></h2>
            <?php $accounts = user_platform_accounts((int)$editRow['id']); ?>
            <?php if ($accounts): ?>
                <table class="data-table">
                    <thead>
                    <tr>
                        <th><?= h(app_text('auto.k_89009febe5c6')) ?></th>
                        <th>ID</th>
                        <th><?= h(app_text('user_platforms.profile')) ?></th>
                        <th>Username</th>
                        <th><?= h(app_text('user_platforms.created')) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($accounts as $account): ?>
                        <tr>
                            <td><?= render_platform_badge((string)$account['platform']) ?></td>
                            <td><?= h((string)$account['platform_user_id']) ?></td>
                            <td><?= h(crud_cell_value('platform_accounts', 'platform_profile', $account)) ?></td>
                            <td><?= h((string)($account['username'] ?? '')) ?></td>
                            <td><?= h((string)$account['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state"><?= h(app_text('user_platforms.empty')) ?></div>
            <?php endif; ?>
        </section>

        <section class="panel form-panel">
            <h2><?= h(app_text('user_merge.title')) ?></h2>
            <p class="cell-muted"><?= h(app_text('user_merge.description')) ?></p>
            <form method="post" class="crud-form user-merge-form" data-user-merge-form onsubmit="return this.querySelector('[data-merge-user-id]').value !== '' &amp;&amp; confirm(<?= json_encode(app_text('user_merge.confirm'), JSON_UNESCAPED_UNICODE) ?>);">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="merge_user">
                <input type="hidden" name="id" value="<?= h((string)$editRow['id']) ?>">
                <div
                    class="merge-search"
                    data-user-merge-search
                    data-search-url="crud.php?module=users&amp;action=merge_search&amp;id=<?= (int)$editRow['id'] ?>"
                    data-loading="<?= h(app_text('user_merge.loading_suggestions')) ?>"
                    data-empty="<?= h(app_text('user_merge.empty_suggestions')) ?>"
                    data-selected="<?= h(app_text('user_merge.selected_user')) ?>"
                    data-choose-first="<?= h(app_text('user_merge.choose_first')) ?>"
                >
                    <input type="hidden" name="source_user_id" data-merge-user-id>
                    <label class="field">
                        <span><?= h(app_text('user_merge.source_user')) ?></span>
                        <input
                            type="search"
                            autocomplete="off"
                            placeholder="<?= h(app_text('user_merge.search_placeholder')) ?>"
                            data-merge-search-input
                        >
                    </label>
                    <div class="merge-selected" data-merge-selected hidden></div>
                    <div class="merge-suggestions" data-merge-suggestions>
                        <div class="empty-state"><?= h(app_text('user_merge.loading_suggestions')) ?></div>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="danger-button" data-merge-submit disabled><?= h(app_text('user_merge.submit')) ?></button>
                </div>
            </form>
        </section>
    <?php endif; ?>
<?php endif; ?>
<?php if ($moduleKey === 'leads' && $action === 'edit' && $editRow): ?>
        <section class="panel">
            <h2>Чат с клиентом</h2>
            <?php
            try {
                echo render_lead_conversation((int)($editRow['end_user_id'] ?? 0));
            } catch (Throwable $e) {
                echo '<div class="alert">Не удалось загрузить чат: ' . h($e->getMessage()) . '</div>';
            }
            ?>
        </section>
    <?php endif; ?>
    <?php if ($moduleKey === 'leads' && $action === 'edit' && $editRow): ?>
        <section class="panel form-panel">
            <h2><?= h(app_text('auto.k_e33268c4b97d')) ?></h2>
            <form method="post" class="crud-form" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="send_lead_response">
                <input type="hidden" name="id" value="<?= h((string)$editRow['id']) ?>">
                <?php if ($leadChatOnly): ?>
                    <input type="hidden" name="chat_only" value="1">
                <?php endif; ?>

                <label class="field">
                    <span><?= h(app_text('auto.k_a76a99a18c25')) ?></span>
                    <textarea name="response_text" rows="4" placeholder="<?= h(app_text('auto.response_placeholder')) ?>"></textarea>
                </label>

                <label class="field">
                    <span><?= h(app_text('auto.k_19114f713f60')) ?></span>
                    <select name="response_content_id">
                        <option value=""><?= h(app_text('auto.k_92250813ceb7')) ?></option>
                        <?php foreach (safe_select_options('content_posts', $admin, $errors) as $option): ?>
                            <option value="<?= (int)$option['id'] ?>">#<?= (int)$option['id'] ?> <?= h($option['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="field">
                    <span><?= h(app_text('auto.k_3e644b83e4f3')) ?></span>
                    <select name="response_test_id">
                        <option value=""><?= h(app_text('auto.k_92250813ceb7')) ?></option>
                        <?php foreach (safe_select_options('tests', $admin, $errors) as $option): ?>
                            <option value="<?= (int)$option['id'] ?>">#<?= (int)$option['id'] ?> <?= h($option['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="field">
                    <span><?= h(app_text('auto.k_4012dea6eccf')) ?></span>
                    <input type="file" name="response_attachments[]" accept="image/*,application/pdf,video/mp4,audio/*" multiple>
                </label>

                <label class="field">
                    <span><?= h(app_text('auto.k_e6877b1b589a')) ?></span>
                    <input type="url" name="response_external_url" placeholder="https://...">
                </label>

                <div class="form-actions">
                    <button type="submit"><?= h(app_text('auto.k_18523c1df9fa')) ?></button>
                </div>
            </form>
        </section>

        <?php if (!$leadChatOnly): ?>
        <section class="panel">
            <h2><?= h(app_text('auto.k_238615f19976')) ?></h2>
            <?php
            try {
                $responses = lead_response_history((int)$editRow['id']);
            } catch (Throwable $e) {
                $responses = [];
                echo app_text('auto.k_8646540328ff') . h($e->getMessage()) . '</div>';
            }
            ?>
            <?php if ($responses): ?>
                <div class="lead-response-timeline">
                    <?php foreach ($responses as $response): ?>
                        <?php
                        $attachments = lead_response_attachment_paths($response['attachment_path'] ?? null);
                        $status = status_label($response['status'] ?? 'pending');
                        $contentUrl = !empty($response['content_post_id']) ? 'crud.php?module=content&action=edit&id=' . (int)$response['content_post_id'] : '#';
                        $testUrl = !empty($response['test_id']) ? 'crud.php?module=tests&action=edit&id=' . (int)$response['test_id'] : '#';
                        ?>
                        <article class="lead-response-card">
                            <div class="lead-response-head">
                                <div>
                                    <strong><?= h(
                                        ($response['response_source'] ?? 'admin') === 'telegram'
                                            ? 'Ответ из Telegram'
                                            : ($response['admin_name'] ?? app_text('auto.k_1b93795b9768'))
                                    ) ?></strong>
                                    <span><?= h($response['created_at']) ?></span>
                                </div>
                                <span class="<?= h(status_badge_class($status)) ?>"><?= h($status) ?></span>
                            </div>

                            <?php if (trim((string)($response['message_text'] ?? '')) !== ''): ?>
                                <div class="lead-response-message"><?= nl2br(h($response['message_text'])) ?></div>
                            <?php endif; ?>

                            <?php if (($response['content_title'] ?? '') || ($response['test_title'] ?? '') || $attachments || ($response['external_url'] ?? '')): ?>
                                <div class="lead-response-resources">
                                    <?php if ($response['content_title'] ?? ''): ?>
                                        <a href="<?= h($contentUrl) ?>">
                                            <?= h(app_text('lead_response.open_material')) ?>: <?= h($response['content_title']) ?>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($response['test_title'] ?? ''): ?>
                                        <a href="<?= h($testUrl) ?>">
                                            <?= h(app_text('lead_response.pass_test')) ?>: <?= h($response['test_title']) ?>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($attachments): ?>
                                        <?= render_lead_file_attachments($attachments, 'lead-response-attachments') ?>
                                    <?php endif; ?>
                                    <?php if ($response['external_url'] ?? ''): ?>
                                        <a href="<?= h($response['external_url']) ?>" target="_blank" rel="noopener">
                                            <?= h(app_text('lead_response.open_link')) ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($response['error_message'] ?? ''): ?>
                                <div class="alert error"><?= h($response['error_message']) ?></div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state"><?= h(app_text('auto.k_06fe678de6fe')) ?></div>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    <?php endif; ?>
<?php if ($moduleKey === 'leads'): ?>
    <?= render_lead_media_modal() ?>
<?php endif; ?>
<?php $showList = $action === 'list' && !$leadChatOnly; ?>
<?php if ($showList): ?>
    <section class="panel">
        <?= $listHtml ?>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/../app/views/layouts/footer.php'; ?>
