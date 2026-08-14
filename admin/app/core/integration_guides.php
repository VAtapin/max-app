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
        <a class="button secondary-button" href="/docs/#/integrations/vk-connect">Открыть инструкцию</a>
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
            'text' => 'Зайдите в свое VK-сообщество. В правом блоке меню нажмите «Управление». Если правого блока нет, откройте кнопку «Еще» и найдите управление там.',
            'image' => '/admin/uploads/help/vk-community-page-marked.png',
            'alt' => 'Страница VK-сообщества с пунктом Управление',
        ],
        [
            'title' => 'Включите сообщения и приветствие',
            'text' => 'В правом меню управления откройте «Сообщения». Проверьте строку «Сообщения сообщества»: должно быть «Включены». В поле «Приветствие» вставьте текст из блока выше и сохраните.',
            'image' => '/admin/uploads/help/vk-messages-marked.png',
            'alt' => 'Настройки сообщений VK-сообщества',
        ],
        [
            'title' => 'Включите настройки для бота',
            'text' => 'В том же правом меню, внутри раздела «Сообщения», нажмите «Настройки для бота». Включите возможности ботов, отметьте «Добавить кнопку „Начать“» и нажмите «Сохранить».',
            'image' => '/admin/uploads/help/vk-bot-settings-marked.png',
            'alt' => 'Настройки для бота в VK',
        ],
        [
            'title' => 'Создайте ключ доступа',
            'text' => 'В правом меню VK откройте «Дополнительно», затем «Работа с API». На вкладке «Ключи доступа» нажмите «Создать ключ».',
            'image' => '/admin/uploads/help/vk-api-access-token-marked.jpg',
            'alt' => 'Раздел ключей доступа VK API',
        ],
        [
            'title' => 'Выберите права ключа',
            'text' => 'В окне создания ключа отметьте права: управление сообществом, сообщения сообщества, фотографии, документы, истории и стена. После подтверждения VK покажет ключ вида vk1.a...: скопируйте его в SWPro в поле «Ключ доступа».',
            'image' => '/admin/uploads/help/vk-api-access-token-marked.jpg',
            'alt' => 'Окно выбора прав ключа доступа VK',
        ],
        [
            'title' => 'Скопируйте group_id',
            'text' => 'Откройте вкладку Callback API. В сером блоке подтверждения VK показывает JSON с group_id. Скопируйте только число и вставьте его в поле «group_id» в SWPro.',
            'image' => '/admin/uploads/help/vk-callback-group-id-marked.png',
            'alt' => 'Настройки сервера Callback API в VK',
        ],
        [
            'title' => 'Скопируйте строку, которую должен вернуть сервер',
            'text' => 'В этом же блоке VK показывает «Строка, которую должен вернуть сервер». Скопируйте ее в одноименное поле SWPro.',
            'image' => '/admin/uploads/help/vk-callback-confirmation-marked.png',
            'alt' => 'Строка подтверждения сервера Callback API в VK',
        ],
        [
            'title' => 'Укажите секретный ключ',
            'text' => 'SWPro генерирует «Секретный ключ» автоматически при создании подключения. Скопируйте его из SWPro, вставьте в VK в поле «Секретный ключ» и нажмите «Сохранить».',
            'image' => '/admin/uploads/help/vk-callback-secret-marked.png',
            'alt' => 'Секретный ключ Callback API в VK',
        ],
        [
            'title' => 'Отметьте события Callback API',
            'text' => 'В той же вкладке Callback API откройте «Типы событий». В блоке «Сообщения» отметьте: входящее сообщение, действие с сообщением, разрешение на получение, запрет на получение и прочитанность сообщений. Эти события нужны, чтобы SWPro видел входящие сообщения и статус диалога.',
            'image' => '/admin/uploads/help/vk-callback-events-marked.png',
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
                    <button
                        type="button"
                        class="vk-screenshot-preview"
                        data-image-preview
                        data-image-src="<?= h($step['image']) ?>"
                        data-image-title="<?= h($step['title']) ?>"
                    >
                        <img src="<?= h($step['image']) ?>" alt="<?= h($step['alt']) ?>" loading="lazy">
                    </button>
                </li>
            <?php endforeach; ?>
        </ol>

        <div class="vk-guide-fields">
            <h3>Что потом заполнить в SWPro</h3>
            <dl>
                <div><dt>Платформа</dt><dd>VK</dd></div>
                <div><dt>group_id</dt><dd>Число из JSON в блоке подтверждения Callback API.</dd></div>
                <div><dt>Ключ доступа</dt><dd>Ключ из вкладки «Ключи доступа».</dd></div>
                <div><dt>Строка, которую должен вернуть сервер</dt><dd>Строка из блока подтверждения Callback API.</dd></div>
                <div><dt>Секретный ключ</dt><dd>Ключ, который SWPro сгенерировал в подключении. Он должен совпадать в VK и SWPro.</dd></div>
                <div><dt>Адрес Callback API</dt><dd><code><?= h($callbackUrl) ?></code></dd></div>
            </dl>
        </div>
    </section>
    <?php
    return trim((string)ob_get_clean());
}
