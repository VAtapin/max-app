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
    <div class="integration-help-links">
        <section class="panel integration-help-link">
            <div>
                <span class="eyebrow">VK</span>
                <h2>Подключение сообщества VK</h2>
                <p>Ключ доступа и Callback API. SWPro будет принимать ответы клиента и отправлять сообщения от имени сообщества.</p>
            </div>
            <a class="button secondary-button" href="/docs/#/integrations/vk-connect">Инструкция VK</a>
        </section>
        <section class="panel integration-help-link">
            <div>
                <span class="eyebrow">OK</span>
                <h2>Подключение группы OK</h2>
                <p>Нужны ID группы и Bot API token. После сохранения SWPro автоматически подключит webhook для живого чата.</p>
            </div>
            <a class="button secondary-button" href="/docs/#/integrations/ok-connect">Инструкция OK</a>
        </section>
    </div>
    <?php
    return trim((string)ob_get_clean());
}

function render_ok_connection_guide(): string
{
    ob_start();
    ?>
    <section class="panel vk-guide" id="ok-connection-guide">
        <div class="vk-guide-head">
            <div>
                <span class="eyebrow">OK</span>
                <h2>Как подключить группу OK</h2>
                <p>Группа сможет получать сообщения клиентов в живой чат SWPro и отправлять им ответы от своего имени.</p>
            </div>
            <div class="vk-guide-note">
                Токен Bot API — секрет. Не отправляйте его в мессенджерах и не публикуйте на странице группы. Если токен показали постороннему, сразу выпустите новый в настройках OK.
            </div>
        </div>

        <ol class="vk-screenshot-list">
            <li class="vk-screenshot-card"><div class="vk-screenshot-text"><span>1</span><div><h3>Откройте сообщения группы</h3><p>В desktop-версии Одноклассников откройте свою группу и перейдите в настройки сообщений. Включите возможность писать группе.</p></div></div></li>
            <li class="vk-screenshot-card"><div class="vk-screenshot-text"><span>2</span><div><h3>Получите Bot API token и право приложения</h3><p>В настройках группы откройте раздел Bot API и создайте или скопируйте токен доступа. Для системного окна разрешения приложению также нужно право OK BOT_API_INIT; если его ещё не выдали вашему приложению, запросите его у поддержки OK один раз.</p></div></div></li>
            <li class="vk-screenshot-card"><div class="vk-screenshot-text"><span>3</span><div><h3>Найдите ID группы</h3><p>Скопируйте числовой ID группы из её адреса или настроек. В SWPro не нужен короткий текстовый адрес группы.</p></div></div></li>
            <li class="vk-screenshot-card"><div class="vk-screenshot-text"><span>4</span><div><h3>Добавьте подключение в SWPro</h3><p>В разделе «Подключения» создайте запись, выберите платформу OK, укажите название, ID группы и Bot API token, затем сохраните.</p></div></div></li>
            <li class="vk-screenshot-card"><div class="vk-screenshot-text"><span>5</span><div><h3>Проверьте webhook</h3><p>SWPro сам зарегистрирует защищённый webhook. Если всё готово, в карточке подключения появится время «OK webhook подключён». Ответ клиента из OK затем автоматически появится в живом чате.</p></div></div></li>
        </ol>

        <div class="vk-guide-fields">
            <h3>Что заполнить в SWPro</h3>
            <dl>
                <div><dt>Платформа</dt><dd>OK</dd></div>
                <div><dt>ID группы OK</dt><dd>Числовой идентификатор вашей группы.</dd></div>
                <div><dt>Токен Bot API</dt><dd>Токен группы из настроек Bot API OK.</dd></div>
                <div><dt>Webhook</dt><dd>Ничего вручную указывать не нужно: SWPro создаёт и подключает его при сохранении.</dd></div>
                <div><dt>Клиент</dt><dd>В мини-приложении клиент нажимает «Разрешить сообщения OK» и подтверждает системное окно Одноклассников.</dd></div>
            </dl>
        </div>
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
