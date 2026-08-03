<?php

function integration_guide_callback_url(): string
{
    try {
        $publicUrl = rtrim((string)(app_config()['app']['public_url'] ?? ''), '/');
    } catch (Throwable) {
        $publicUrl = '';
    }

    return ($publicUrl !== '' ? $publicUrl : 'https://swpro.ru') . '/api/vk_callback.php';
}

function render_vk_connection_help_link(): string
{
    ob_start();
    ?>
    <section class="panel integration-help-link">
        <div>
            <span class="eyebrow">VK</span>
            <h2>Инструкция по подключению сообщества</h2>
            <p>Пошаговая настройка сообщений, ключа доступа и Callback API вынесена в раздел помощи.</p>
        </div>
        <a class="button secondary-button" href="help.php#vk-connection-guide">Открыть инструкцию</a>
    </section>
    <?php
    return trim((string)ob_get_clean());
}

function render_vk_connection_guide(): string
{
    $callbackUrl = integration_guide_callback_url();
    $greetingText = 'Здравствуйте! Здесь можно пройти чек-ап и получить ответ консультанта. Нажмите «Начать» или откройте приложение SWPro';
    $steps = [
        [
            'title' => 'Откройте управление сообщества',
            'text' => 'На странице сообщества нажмите «Управление».',
            'image' => '/admin/uploads/help/vk-community-page.png',
            'alt' => 'Страница VK-сообщества с пунктом Управление',
            'markers' => [
                ['label' => 'Управление', 'x' => 72, 'y' => 45],
            ],
        ],
        [
            'title' => 'Включите сообщения и приветствие',
            'text' => 'В управлении откройте пункт «Сообщения». Проверьте, что сообщения сообщества включены, и вставьте приветственный текст.',
            'image' => '/admin/uploads/help/vk-messages.png',
            'alt' => 'Настройки сообщений VK-сообщества',
            'markers' => [
                ['label' => 'Сообщения', 'x' => 79, 'y' => 61],
                ['label' => 'Приветствие', 'x' => 47, 'y' => 28],
            ],
        ],
        [
            'title' => 'Включите настройки для бота',
            'text' => 'В этом же разделе «Сообщения» откройте «Настройки для бота». Включите возможности ботов, поставьте галочку «Добавить кнопку „Начать“» и сохраните.',
            'image' => '/admin/uploads/help/vk-bot-settings.png',
            'alt' => 'Настройки для бота в VK',
            'markers' => [
                ['label' => 'Настройки для бота', 'x' => 78, 'y' => 56],
                ['label' => 'Кнопка «Начать»', 'x' => 48, 'y' => 31],
            ],
        ],
        [
            'title' => 'Создайте ключ доступа',
            'text' => 'Откройте «Дополнительно» → «Работа с API» → «Ключи доступа» и нажмите «Создать ключ».',
            'image' => '/admin/uploads/help/vk-api-keys.png',
            'alt' => 'Раздел ключей доступа VK API',
            'markers' => [
                ['label' => 'Работа с API', 'x' => 78, 'y' => 46],
                ['label' => 'Создать ключ', 'x' => 59, 'y' => 11],
            ],
        ],
        [
            'title' => 'Выберите права ключа',
            'text' => 'В окне создания ключа отметьте доступ к управлению сообществом, сообщениям, фотографиям, документам, историям и стене. Затем нажмите «Создать».',
            'image' => '/admin/uploads/help/vk-api-key-rights.png',
            'alt' => 'Окно выбора прав ключа доступа VK',
            'markers' => [
                ['label' => 'Права доступа', 'x' => 33, 'y' => 42],
                ['label' => 'Создать', 'x' => 76, 'y' => 89],
            ],
        ],
        [
            'title' => 'Отметьте события Callback API',
            'text' => 'В Callback API укажите адрес сервера и секретный ключ, подтвердите сервер. Затем во вкладке «Типы событий» отметьте входящее сообщение, действие с сообщением, разрешение, запрет и прочитанность.',
            'image' => '/admin/uploads/help/vk-callback-events.png',
            'alt' => 'Типы событий Callback API в VK',
            'markers' => [
                ['label' => 'Типы событий', 'x' => 35, 'y' => 19],
                ['label' => '5 галочек', 'x' => 37, 'y' => 38],
            ],
        ],
    ];

    ob_start();
    ?>
    <section class="panel vk-guide" id="vk-connection-guide">
        <div class="vk-guide-head">
            <div>
                <span class="eyebrow">VK</span>
                <h2>Как подключить сообщество VK</h2>
                <p>Настройка нужна, чтобы SWPro получал сообщения из VK и отправлял ответы клиентам от имени сообщества.</p>
            </div>
            <div class="vk-guide-note">
                Не публикуйте ключ доступа и секретный ключ. Если ключ уже показывали посторонним, удалите его в VK и создайте новый.
            </div>
        </div>

        <div class="vk-guide-inline">
            <strong>Текст приветствия VK:</strong>
            <span><?= h($greetingText) ?></span>
        </div>

        <ol class="vk-screenshot-list">
            <?php foreach ($steps as $index => $step): ?>
                <li class="vk-screenshot-card">
                    <div class="vk-screenshot-text">
                        <span><?= $index + 1 ?></span>
                        <div>
                            <h3><?= h($step['title']) ?></h3>
                            <p><?= h($step['text']) ?></p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="vk-screenshot-preview"
                        data-image-preview
                        data-image-src="<?= h($step['image']) ?>"
                        data-image-title="<?= h($step['title']) ?>"
                    >
                        <img src="<?= h($step['image']) ?>" alt="<?= h($step['alt']) ?>" loading="lazy">
                        <?php foreach ($step['markers'] ?? [] as $marker): ?>
                            <span
                                class="vk-shot-marker"
                                style="--marker-x: <?= (float)$marker['x'] ?>%; --marker-y: <?= (float)$marker['y'] ?>%;"
                            ><?= h($marker['label']) ?></span>
                        <?php endforeach; ?>
                    </button>
                </li>
            <?php endforeach; ?>
        </ol>

        <div class="vk-guide-fields">
            <h3>Что потом заполнить в SWPro</h3>
            <dl>
                <div><dt>Платформа</dt><dd>VK</dd></div>
                <div><dt>ID группы/канала</dt><dd>Числовой <code>group_id</code> из Callback API.</dd></div>
                <div><dt>Ключ доступа</dt><dd>Ключ из вкладки «Ключи доступа».</dd></div>
                <div><dt>Callback: строка подтверждения</dt><dd>Строка, которую VK просит вернуть серверу.</dd></div>
                <div><dt>Callback: секретный ключ</dt><dd>Ваш секретный ключ. Он должен совпадать в VK и SWPro.</dd></div>
                <div><dt>Адрес Callback API</dt><dd><code><?= h($callbackUrl) ?></code></dd></div>
            </dl>
        </div>
    </section>
    <?php
    return trim((string)ob_get_clean());
}
