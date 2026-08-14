<?php

require_once __DIR__ . '/admin/app/core/auth.php';
require_once __DIR__ . '/admin/app/core/subscription_plans.php';
require_once __DIR__ . '/admin/app/core/workspace_billing.php';
require_once __DIR__ . '/admin/app/core/consultant_profiles.php';
require_once __DIR__ . '/admin/app/core/referral_codes.php';

function leader_signup_plans(): array
{
    return db()->query(
        "SELECT * FROM subscription_plans
         WHERE owner_type = 'superadmin' AND owner_id = 0 AND is_active = 1 AND is_public = 1
         ORDER BY sort_order, fixed_monthly_price, id"
    )->fetchAll();
}

function leader_signup_discounts(int $planId): array
{
    $stmt = db()->prepare(
        'SELECT months, discount_percent, badge_text FROM subscription_period_discounts
         WHERE subscription_plan_id = :plan_id AND is_active = 1 ORDER BY sort_order, months'
    );
    $stmt->execute(['plan_id' => $planId]);
    return $stmt->fetchAll();
}

function leader_signup_referral(string $name): string
{
    $base = strtoupper(subscription_plan_slug($name));
    $base = preg_replace('/[^A-Z0-9_]+/', '_', str_replace('-', '_', $base)) ?: 'LEADER';
    $base = 'SWPRO_' . trim($base, '_');
    for ($i = 0; $i < 100; $i++) {
        $code = substr($base . ($i ? '_' . ($i + 1) : ''), 0, 64);
        if (!referral_code_binding($code)) return $code;
    }
    return 'SWPRO_' . strtoupper(bin2hex(random_bytes(6)));
}

function leader_money(mixed $value): string
{
    return number_format((float)$value, 0, ',', ' ') . ' ₽';
}

$plans = leader_signup_plans();
$planDiscounts = [];
foreach ($plans as $plan) $planDiscounts[(int)$plan['id']] = leader_signup_discounts((int)$plan['id']);
$errors = [];
$values = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string)($_POST['name'] ?? ''));
    $email = mb_strtolower(trim((string)($_POST['email'] ?? '')), 'UTF-8');
    $phone = trim((string)($_POST['phone'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $planId = (int)($_POST['plan_id'] ?? 0);
    $months = (int)($_POST['months'] ?? 1);
    $plan = null;
    foreach ($plans as $candidate) if ((int)$candidate['id'] === $planId) $plan = $candidate;

    if (trim((string)($_POST['company'] ?? '')) !== '') $errors[] = 'Не удалось отправить форму.';
    if ($name === '') $errors[] = 'Укажите имя и фамилию.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Укажите корректный email.';
    if (mb_strlen($password) < 10) $errors[] = 'Пароль должен содержать не менее 10 символов.';
    if (!hash_equals($password, (string)($_POST['password_confirm'] ?? ''))) $errors[] = 'Пароли не совпадают.';
    if (!$plan) $errors[] = 'Выберите доступный тариф.';
    if (empty($_POST['accept_documents'])) $errors[] = 'Нужно принять документы сервиса.';

    if ($plan && subscription_plan_billing_mode($plan) === 'prepaid') {
        $allowed = array_map(static fn(array $row): int => (int)$row['months'], $planDiscounts[$planId]);
        if (!in_array($months, $allowed, true)) $errors[] = 'Выберите доступный период оплаты.';
    } else {
        $months = 1;
    }

    if (!$errors) {
        $stmt = db()->prepare('SELECT id FROM admin_users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        if ($stmt->fetchColumn()) $errors[] = 'Пользователь с таким email уже зарегистрирован.';
    }

    if (!$errors) {
        try {
            db()->beginTransaction();
            $referral = leader_signup_referral($name);
            $stmt = db()->prepare(
                'INSERT INTO resellers
                    (parent_reseller_id, subscription_plan_id, name, email, phone, billing_name,
                     billing_email, legal_name, legal_email, legal_phone, referral_code, is_active)
                 VALUES (NULL, :plan_id, :name, :email, :phone, :name, :email, :name, :email, :phone, :referral, 1)'
            );
            $stmt->execute(['plan_id' => $planId, 'name' => $name, 'email' => $email, 'phone' => $phone ?: null, 'referral' => $referral]);
            $resellerId = (int)db()->lastInsertId();

            $stmt = db()->prepare(
                "INSERT INTO admin_users
                    (role, reseller_id, name, email, password_hash, phone, referral_code, is_active)
                 VALUES ('reseller', :reseller_id, :name, :email, :password_hash, :phone, :referral, 1)"
            );
            $stmt->execute([
                'reseller_id' => $resellerId, 'name' => $name, 'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'phone' => $phone ?: null,
                'referral' => $referral,
            ]);
            $adminId = (int)db()->lastInsertId();
            ensure_consultant_profile('reseller', $resellerId);
            $workspace = billing_sync_workspace('reseller', $resellerId);
            $invoice = null;
            if ($workspace && $workspace['billing_mode'] === 'prepaid' && (float)$workspace['monthly_price'] > 0) {
                $invoice = billing_create_prepaid_invoice($workspace, $months);
            }
            log_activity('admin', $adminId, 'self_register_leader', 'resellers', $resellerId, [
                'plan_id' => $planId, 'months' => $months, 'invoice_id' => $invoice['id'] ?? null,
                'documents_accepted' => true,
                'ip_hash' => hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? '')),
            ]);
            db()->commit();
            $stmt = db()->prepare('SELECT * FROM admin_users WHERE id = :id');
            $stmt->execute(['id' => $adminId]);
            complete_admin_login($stmt->fetch());
            if ($invoice) redirect('/admin/public/payment_checkout.php?invoice_id=' . (int)$invoice['id']);
            redirect('/admin/public/index.php?welcome=leader');
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            $errors[] = 'Регистрацию не удалось завершить. Попробуйте ещё раз или обратитесь в поддержку.';
        }
    }
}

$plansJson = [];
foreach ($plans as $plan) {
    $plansJson[(int)$plan['id']] = [
        'mode' => subscription_plan_billing_mode($plan),
        'price' => (float)($plan['fixed_monthly_price'] ?? 0),
        'periods' => $planDiscounts[(int)$plan['id']],
    ];
}
?>
<!doctype html><html lang="ru"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Стать лидером SWPro</title><meta name="description" content="Создайте свою команду в SWPro: мини-сайт, клиенты, чек-апы, рассылки и AI-инструменты.">
<link rel="stylesheet" href="/public.css"><link rel="stylesheet" href="/leaders.css">
</head><body><main class="leader-page">
<header class="leader-nav"><a class="brand-link" href="/">SWPro</a><nav><a href="#advantages">Возможности</a><a href="#tariffs">Тарифы</a><a href="#signup">Регистрация</a></nav><a class="leader-nav-button" href="#signup">Стать лидером</a></header>

<section class="leader-hero">
<div class="hero-copy"><span class="eyebrow">Рабочее место лидера SWPro</span><h1>Ведите клиентов, команду и контент в одной системе</h1><p>Персональный сайт, единая клиентская база, чек-апы, живые чаты, рассылки и AI-инструменты — всё связано и уже готово к ежедневной работе.</p>
<div class="hero-points"><span>Web · VK · Telegram · MAX</span><span>Одна история клиента</span><span>Безопасные роли и доступы</span></div>
<div class="leader-actions"><a class="primary" href="#signup">Создать кабинет</a><a class="secondary" href="#tariffs">Сравнить тарифы</a></div></div>
<figure class="hero-visual"><img src="/public/assets/img/leaders/leader-workspace.webp" alt="Лидер работает с клиентами в SWPro" width="1440" height="960" decoding="async" fetchpriority="high"><figcaption><strong>Вместо десятка сервисов</strong><span>Клиенты, сообщения, задачи, контент и AI — в одном кабинете.</span></figcaption></figure>
</section>

<section class="proof-strip" aria-label="Основные преимущества"><div><strong>Одна база</strong><span>для всех каналов</span></div><div><strong>Вся ветка</strong><span>с разделением прав</span></div><div><strong>Каждый день</strong><span>готовый план действий</span></div><div><strong>AI внутри</strong><span>по проверенной базе</span></div></section>

<section id="advantages" class="leader-section feature-section"><div class="section-heading"><div><span class="eyebrow">Возможности</span><h2>Не просто мини-сайт, а полноценное рабочее место</h2></div><p>SWPro сопровождает весь путь: от первой реферальной ссылки до повторного чек-апа, личного сообщения и развития собственной команды.</p></div>
<div class="advantage-grid">
<?php foreach ([
 ['01','Клиентская база','Анкета, контакты, согласия, история активности и закрепление за нужным специалистом — в одной карточке.'],
 ['02','Команда и структура','Лидеры и консультанты вашей ветки, понятная иерархия, лимиты тарифа и безопасное разграничение доступа.'],
 ['03','Мини-сайт и Mini App','Персональная страница с вашим фото, материалами, товарами, тестами и кнопками связи через Web, VK, Telegram и MAX.'],
 ['04','Чек-апы и динамика','Информационные опросы, понятные результаты, персональный план на 7/14/30 дней, повторное прохождение и сравнение «было → стало».'],
 ['05','Живые чаты','Личные диалоги с клиентами и отдельный чат команды. Новые сообщения поднимаются вверх и не теряются среди записей.'],
 ['06','Рассылки и сегменты','Сообщения только доступной аудитории: своим клиентам, своей команде и выбранным сегментам по разрешённым каналам.'],
 ['07','Что сделать сегодня','Дни рождения, новые результаты, повторный чек-ап, неактивность и завершение плана превращаются в готовый список действий.'],
 ['08','AI-консультант','Отвечает клиентам по продуктам, материалам и работе SWPro на основе утверждённой базы знаний, а не случайных сведений.'],
 ['09','AI-студия','Создаёт проверяемые черновики постов, поздравлений, кампаний и персональных сообщений. Вы редактируете и решаете, что отправлять.'],
 ['10','Карточки, голос и видео','Персональные PNG-визитки с QR-кодом, голосовые сценарии и AI-видео — с лимитами и возможностями выбранного тарифа.'],
 ['11','Подписка и оплата','Начисления по выбранной модели, счета, скидки за 6 и 12 месяцев, история оплат и подключаемые способы оплаты.'],
 ['12','Безопасность и документы','Двухфакторная защита, журнал согласий, юридические документы, изоляция веток и контроль доступа к данным клиентов.'],
] as [$number,$title,$text]): ?><article class="advantage-card"><span class="feature-number"><?= h($number) ?></span><h3><?= h($title) ?></h3><p><?= h($text) ?></p></article><?php endforeach; ?>
</div></section>

<section class="leader-section journey-section"><figure><img src="/public/assets/img/leaders/client-journey.webp" alt="Консультант сопровождает клиента с помощью SWPro" width="1440" height="960" loading="lazy" decoding="async"></figure><div><span class="eyebrow">Путь клиента</span><h2>От знакомства до следующего результата</h2><ol class="journey-steps"><li><strong>Персональная ссылка</strong><span>Человек открывает ваш сайт или Mini App и знакомится с вами.</span></li><li><strong>Анкета и чек-ап</strong><span>Данные, согласия и ответы сохраняются в единой клиентской истории.</span></li><li><strong>План и общение</strong><span>Клиент видит материалы и прогресс, а вы получаете повод вовремя написать.</span></li><li><strong>Повторный контакт</strong><span>SWPro напомнит о следующем шаге и поможет подготовить сообщение.</span></li></ol><a class="secondary" href="#signup">Посмотреть тарифы и подключиться</a></div></section>

<section class="leader-section ai-highlight"><div><span class="eyebrow">AI без потери контроля</span><h2>Помощник готовит — решение остаётся за вами</h2><p>AI находит нужное в проверенной базе SWPro, объясняет результаты понятным языком, предлагает тексты и помогает не забыть о клиенте. Ничего не публикуется и не рассылается автоматически без вашего решения.</p></div><ul><li>Ответы по утверждённым материалам</li><li>Персональные черновики сообщений</li><li>Идеи для публикаций и кампаний</li><li>Голосовые и видеосценарии</li></ul></section>

<section id="tariffs" class="leader-section"><span class="eyebrow">Тарифы</span><h2>Выберите подходящий масштаб</h2>
<?php if (!$plans): ?><p class="leader-empty">Активные тарифы сейчас уточняются. Оставьте заявку через поддержку.</p><?php else: ?>
<div class="tariff-grid"><?php foreach ($plans as $plan): ?><article class="tariff-card">
<h3><?= h((string)$plan['title']) ?></h3><p><?= nl2br(h((string)$plan['description'])) ?></p>
<div class="tariff-price"><?= leader_money($plan['fixed_monthly_price'] ?? 0) ?><small>/ месяц за рабочее место лидера</small></div>
<ul><li><?= subscription_plan_billing_mode($plan) === 'prepaid' ? 'Предоплата за выбранный период' : 'Оплата по факту за прошедший месяц' ?></li>
<li>Лидеры 1-го уровня: <?= $plan['direct_leader_limit'] === null ? 'без лимита' : (int)$plan['direct_leader_limit'] ?></li>
<li>Консультанты в ветке: <?= $plan['branch_consultant_limit'] === null ? 'без лимита' : (int)$plan['branch_consultant_limit'] ?></li>
<li>AI-консультант: <?= (int)($plan['ai_text_enabled'] ?? 0) ? 'включён' : 'не включён' ?></li></ul>
<?php if ($planDiscounts[(int)$plan['id']]): ?><div class="discounts"><?php foreach ($planDiscounts[(int)$plan['id']] as $discount): ?><?php if ((float)$discount['discount_percent'] > 0): ?><span><?= (int)$discount['months'] ?> мес. — выгода <?= h((string)$discount['discount_percent']) ?>%</span><?php endif; ?><?php endforeach; ?></div><?php endif; ?>
<button type="button" data-choose-plan="<?= (int)$plan['id'] ?>">Выбрать тариф</button></article><?php endforeach; ?></div>

<div class="comparison"><table><thead><tr><th>Тариф</th><th>База</th><th>Доп. лидер</th><th>Консультант</th><th>AI</th></tr></thead><tbody><?php foreach ($plans as $plan): ?><tr><th><?= h((string)$plan['title']) ?></th><td><?= leader_money($plan['fixed_monthly_price'] ?? 0) ?></td><td><?= leader_money($plan['price_per_leader'] ?? 0) ?></td><td><?= leader_money($plan['price_per_consultant'] ?? 0) ?></td><td><?= (int)($plan['ai_text_enabled'] ?? 0) ? 'Да' : 'Нет' ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php endif; ?></section>

<section id="signup" class="leader-section signup-section"><div><span class="eyebrow">Самостоятельное подключение</span><h2>Создайте кабинет лидера</h2><p>После регистрации тариф с предоплатой сразу откроет безопасную страницу оплаты. До оплаты клиентские приложения будут недоступны, но кабинет останется открыт.</p></div>
<form method="post" class="signup-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input class="honeypot" name="company" tabindex="-1" autocomplete="off">
<?php foreach ($errors as $error): ?><div class="signup-error" role="alert"><?= h($error) ?></div><?php endforeach; ?>
<label><span>Имя и фамилия *</span><input name="name" required value="<?= h((string)($values['name'] ?? '')) ?>" autocomplete="name"></label>
<div class="form-row"><label><span>Email для входа *</span><input type="email" name="email" required value="<?= h((string)($values['email'] ?? '')) ?>" autocomplete="email"></label><label><span>Телефон</span><input name="phone" value="<?= h((string)($values['phone'] ?? '')) ?>" autocomplete="tel"></label></div>
<label><span>Тариф *</span><select name="plan_id" id="plan-select" required><?php foreach ($plans as $plan): ?><option value="<?= (int)$plan['id'] ?>" <?= (int)($values['plan_id'] ?? ($plans[0]['id'] ?? 0)) === (int)$plan['id'] ? 'selected' : '' ?>><?= h((string)$plan['title']) ?> — <?= leader_money($plan['fixed_monthly_price'] ?? 0) ?>/мес.</option><?php endforeach; ?></select></label>
<label id="period-field"><span>Период оплаты *</span><select name="months" id="period-select"></select><small id="period-summary"></small></label>
<div class="form-row"><label><span>Пароль *</span><input type="password" name="password" minlength="10" required autocomplete="new-password"></label><label><span>Повторите пароль *</span><input type="password" name="password_confirm" minlength="10" required autocomplete="new-password"></label></div>
<label class="accept"><input type="checkbox" name="accept_documents" value="1" required><span>Принимаю <a href="/legal.php?type=user_agreement" target="_blank">Пользовательское соглашение</a>, <a href="/legal.php?type=privacy_policy" target="_blank">Политику данных</a> и <a href="/legal.php?type=leader_offer" target="_blank">Публичную оферту</a>.</span></label>
<button type="submit" class="signup-submit">Зарегистрироваться и продолжить</button></form></section>
</main><script>
const plans=<?= json_encode($plansJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const planSelect=document.querySelector('#plan-select'),periodField=document.querySelector('#period-field'),periodSelect=document.querySelector('#period-select'),summary=document.querySelector('#period-summary');
function money(v){return new Intl.NumberFormat('ru-RU',{maximumFractionDigits:0}).format(v)+' ₽'}
function updatePeriods(){const p=plans[planSelect.value]; if(!p)return; periodSelect.innerHTML=''; const periods=p.periods?.length?p.periods:[{months:1,discount_percent:0,badge_text:''}]; periods.forEach(x=>{const o=document.createElement('option');o.value=x.months;o.textContent=x.months+' мес.'+(Number(x.discount_percent)?' — скидка '+x.discount_percent+'%':'');periodSelect.append(o)});periodField.hidden=p.mode!=='prepaid';updateSummary()}
function updateSummary(){const p=plans[planSelect.value],x=p?.periods?.find(v=>String(v.months)===periodSelect.value);if(!p||p.mode!=='prepaid'){summary.textContent='Счёт формируется за завершённый календарный месяц.';return}const months=Number(x?.months||1),discount=Number(x?.discount_percent||0);summary.textContent='К оплате сейчас: '+money(p.price*months*(1-discount/100))+(discount?' · экономия '+money(p.price*months*discount/100):'')}
planSelect?.addEventListener('change',updatePeriods);periodSelect?.addEventListener('change',updateSummary);updatePeriods();
document.querySelectorAll('[data-choose-plan]').forEach(b=>b.addEventListener('click',()=>{planSelect.value=b.dataset.choosePlan;updatePeriods();document.querySelector('#signup').scrollIntoView({behavior:'smooth'})}));
</script></body></html>
