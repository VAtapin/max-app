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

function render_vk_connection_guide(bool $compact = false): string
{
    $callbackUrl = integration_guide_callback_url();
    $greetingText = 'Здравствуйте! Здесь можно пройти чек-ап и получить ответ консультанта. Нажмите «Начать» или откройте приложение SWPro';
    $className = 'panel vk-guide' . ($compact ? ' vk-guide-compact' : '');

    ob_start();
    ?>
    <section class="<?= h($className) ?>">
        <div class="vk-guide-head">
            <div>
                <span class="eyebrow">VK</span>
                <h2>Как подключить сообщество VK</h2>
                <p>Подключение нужно, чтобы SWPro принимал входящие сообщения из сообщества и отправлял ответы клиентам от имени этого сообщества.</p>
            </div>
            <div class="vk-guide-note">
                Ключ доступа и секретный ключ не публикуйте в чатах и инструкциях. Если ключ уже показали посторонним, удалите его в VK и создайте новый.
            </div>
        </div>

        <div class="vk-guide-steps">
            <article class="vk-guide-step">
                <div class="vk-guide-step-title">
                    <span>1</span>
                    <h3>Откройте управление</h3>
                </div>
                <p>Войдите в свое VK-сообщество и нажмите <strong>Управление</strong> в правом меню.</p>
                <div class="vk-guide-shot">
                    <div class="vk-shot-top">Страница сообщества</div>
                    <div class="vk-shot-cover">Обложка сообщества</div>
                    <div class="vk-shot-row is-highlight">Управление</div>
                    <div class="vk-shot-row">Сообщения</div>
                    <div class="vk-shot-row">Статистика</div>
                </div>
            </article>

            <article class="vk-guide-step">
                <div class="vk-guide-step-title">
                    <span>2</span>
                    <h3>Включите сообщения</h3>
                </div>
                <p>Перейдите в <strong>Сообщения</strong>, включите сообщения сообщества и поставьте приветствие.</p>
                <div class="vk-guide-copy"><?= h($greetingText) ?></div>
                <div class="vk-guide-shot">
                    <div class="vk-shot-top">Управление · Сообщения</div>
                    <div class="vk-shot-field"><b>Сообщения сообщества</b><strong>Включены</strong></div>
                    <div class="vk-shot-field is-highlight"><b>Приветствие</b><span>Текст приветствия SWPro</span></div>
                    <div class="vk-shot-button">Сохранить</div>
                </div>
            </article>

            <article class="vk-guide-step">
                <div class="vk-guide-step-title">
                    <span>3</span>
                    <h3>Настройте бота</h3>
                </div>
                <p>В разделе <strong>Настройки для бота</strong> включите возможности ботов и кнопку <strong>Начать</strong>.</p>
                <div class="vk-guide-shot">
                    <div class="vk-shot-top">Сообщения · Настройки для бота</div>
                    <div class="vk-shot-field"><b>Возможности ботов</b><strong>Включены</strong></div>
                    <div class="vk-shot-check is-highlight">Добавить кнопку «Начать»</div>
                    <div class="vk-shot-check">Разрешить добавлять сообщество в чаты</div>
                </div>
            </article>

            <article class="vk-guide-step">
                <div class="vk-guide-step-title">
                    <span>4</span>
                    <h3>Создайте ключ доступа</h3>
                </div>
                <p>Откройте <strong>Дополнительно → Работа с API → Ключи доступа</strong> и нажмите <strong>Создать ключ</strong>.</p>
                <div class="vk-guide-shot">
                    <div class="vk-shot-tabs">
                        <span class="is-active">Ключи доступа</span>
                        <span>Callback API</span>
                        <span>Long Poll API</span>
                    </div>
                    <div class="vk-shot-row is-highlight">Создать ключ</div>
                    <div class="vk-shot-check">Управление сообществом</div>
                    <div class="vk-shot-check">Сообщения сообщества</div>
                    <div class="vk-shot-check">Фотографии, документы, истории, стена</div>
                </div>
            </article>

            <article class="vk-guide-step">
                <div class="vk-guide-step-title">
                    <span>5</span>
                    <h3>Заполните Callback API</h3>
                </div>
                <p>На вкладке <strong>Callback API</strong> укажите адрес сервера, задайте секретный ключ, сохраните и нажмите <strong>Подтвердить</strong>.</p>
                <div class="vk-guide-shot">
                    <div class="vk-shot-tabs">
                        <span>Ключи доступа</span>
                        <span class="is-active">Callback API</span>
                        <span>Long Poll API</span>
                    </div>
                    <div class="vk-shot-field"><b>Версия API</b><span>5.199</span></div>
                    <div class="vk-shot-field is-highlight"><b>Адрес</b><code><?= h($callbackUrl) ?></code></div>
                    <div class="vk-shot-field"><b>group_id</b><code>скопируйте из VK</code></div>
                    <div class="vk-shot-field"><b>Строка подтверждения</b><code>скопируйте из VK</code></div>
                    <div class="vk-shot-field is-highlight"><b>Секретный ключ</b><code>одинаковый в VK и SWPro</code></div>
                </div>
            </article>

            <article class="vk-guide-step">
                <div class="vk-guide-step-title">
                    <span>6</span>
                    <h3>Отметьте типы событий</h3>
                </div>
                <p>В <strong>Callback API → Типы событий</strong> включите пять событий для сообщений.</p>
                <div class="vk-guide-shot">
                    <div class="vk-shot-top">Типы событий</div>
                    <div class="vk-shot-check is-highlight">Входящее сообщение</div>
                    <div class="vk-shot-check is-muted">Исходящее сообщение</div>
                    <div class="vk-shot-check is-muted">Редактирование сообщения</div>
                    <div class="vk-shot-check is-highlight">Действие с сообщением</div>
                    <div class="vk-shot-check is-highlight">Разрешение на получение</div>
                    <div class="vk-shot-check is-highlight">Запрет на получение</div>
                    <div class="vk-shot-check is-muted">Статус набора текста</div>
                    <div class="vk-shot-check is-highlight">Прочитанность сообщений</div>
                </div>
            </article>
        </div>

        <div class="vk-guide-map">
            <h3>Что вставить в SWPro</h3>
            <div class="vk-guide-map-grid">
                <div><b>Платформа</b><span>VK</span></div>
                <div><b>Название</b><span>Понятное имя подключения, например «Сообщество Марии VK»</span></div>
                <div><b>ID группы/канала</b><span>Числовой <code>group_id</code> из блока подтверждения Callback API</span></div>
                <div><b>Ключ доступа</b><span>Ключ из вкладки «Ключи доступа»</span></div>
                <div><b>Callback: строка подтверждения</b><span>Строка, которую должен вернуть сервер</span></div>
                <div><b>Callback: секретный ключ</b><span>Секрет, который вы сами придумали и указали в VK</span></div>
            </div>
        </div>
    </section>
    <?php
    return trim((string)ob_get_clean());
}
