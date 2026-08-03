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
        ],
        [
            'title' => 'Включите сообщения и приветствие',
            'text' => 'В разделе «Сообщения» включите сообщения сообщества и вставьте приветственный текст.',
            'image' => '/admin/uploads/help/vk-messages.png',
            'alt' => 'Настройки сообщений VK-сообщества',
        ],
        [
            'title' => 'Включите настройки для бота',
            'text' => 'Включите возможности ботов и кнопку «Начать».',
            'image' => '/admin/uploads/help/vk-bot-settings.png',
            'alt' => 'Настройки для бота в VK',
        ],
        [
            'title' => 'Создайте ключ доступа',
            'text' => 'Откройте «Дополнительно» → «Работа с API» → «Ключи доступа» и нажмите «Создать ключ».',
            'image' => '/admin/uploads/help/vk-api-keys.png',
            'alt' => 'Раздел ключей доступа VK API',
        ],
        [
            'title' => 'Выберите права ключа',
            'text' => 'Отметьте права для сообщений, фотографий, документов, историй, стены и управления сообществом.',
            'image' => '/admin/uploads/help/vk-api-key-rights.png',
            'alt' => 'Окно выбора прав ключа доступа VK',
        ],
        [
            'title' => 'Отметьте события Callback API',
            'text' => 'На вкладке «Типы событий» отметьте пять событий: входящее сообщение, действие с сообщением, разрешение, запрет и прочитанность.',
            'image' => '/admin/uploads/help/vk-callback-events.png',
            'alt' => 'Типы событий Callback API в VK',
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
                    <a href="<?= h($step['image']) ?>" target="_blank" rel="noopener">
                        <img src="<?= h($step['image']) ?>" alt="<?= h($step['alt']) ?>" loading="lazy">
                    </a>
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
