<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/permissions.php';
require_once __DIR__ . '/../app/core/client_journey.php';
require_once __DIR__ . '/../app/core/workspace_billing.php';
require_once __DIR__ . '/../app/core/ai_workflows.php';

$admin = require_auth();
$title = app_text('auto.dashboard');

function public_base_url(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host === '') {
        return '';
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . $host;
}

function manager_referral_links(array $manager): array
{
    $code = trim((string)($manager['referral_code'] ?? ''));
    if ($code === '') {
        return [];
    }

    $config = app_config();
    $baseUrl = rtrim(public_base_url(), '/');
    $miniAppUrl = trim((string)($config['integrations']['mini_app_url'] ?? ''));
    $vkAppId = preg_replace('/\D+/', '', (string)($config['integrations']['vk_app_id'] ?? '')) ?: '';
    $okAppId = preg_replace('/\D+/', '', (string)($config['integrations']['ok_app_id'] ?? '')) ?: '';
    if ($miniAppUrl === '' && $baseUrl !== '') {
        $miniAppUrl = $baseUrl . '/vk-mini-app/';
    }

    $telegramBot = trim((string)($config['integrations']['telegram_bot_username'] ?? 'SWProAssistant_bot'));
    $encodedCode = rawurlencode($code);
    $links = [
        'telegram' => [
            'label' => 'Telegram',
            'url' => 'https://t.me/' . rawurlencode($telegramBot) . '?start=' . rawurlencode('ref_' . $code),
        ],
    ];

    if ($vkAppId !== '') {
        $links['VK'] = [
            'label' => 'VK',
            'url' => 'https://vk.ru/app' . $vkAppId . '#ref=' . $encodedCode,
        ];
    }

    if ($okAppId !== '') {
        $links['OK'] = [
            'label' => 'OK',
            'url' => 'https://ok.ru/app/' . $okAppId . '?ref=' . $encodedCode,
        ];
    }

    if ($miniAppUrl !== '') {
        $separator = str_contains($miniAppUrl, '?') ? '&' : '?';
        $links['MAX'] = [
            'label' => 'MAX',
            'url' => $miniAppUrl . $separator . 'ref=' . $encodedCode,
        ];
        $links['web_app'] = [
            'label' => 'Web Mini App',
            'url' => $miniAppUrl . $separator . 'ref=' . $encodedCode,
        ];
        $links['web'] = [
            'label' => 'Web',
            'url' => ($baseUrl !== '' ? $baseUrl : rtrim($miniAppUrl, '/')) . '/?ref=' . $encodedCode,
        ];
    }

    return $links;
}

function dashboard_manager(array $admin): ?array
{
    if ($admin['role'] !== 'manager' || empty($admin['manager_id'])) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT id, name, referral_code
         FROM managers
         WHERE id = :id AND is_active = 1
         LIMIT 1'
    );
    $stmt->execute(['id' => (int)$admin['manager_id']]);
    $manager = $stmt->fetch();

    return $manager ?: null;
}

function dashboard_reseller(array $admin): ?array
{
    if ($admin['role'] !== 'reseller' || empty($admin['reseller_id'])) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT id, name, referral_code
         FROM resellers
         WHERE id = :id AND is_active = 1
         LIMIT 1'
    );
    $stmt->execute(['id' => (int)$admin['reseller_id']]);
    $reseller = $stmt->fetch();

    return $reseller ?: null;
}

function count_table(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function append_where_condition(string $where, string $condition): string
{
    return $where !== '' ? $where . ' AND ' . $condition : 'WHERE ' . $condition;
}

function alias_user_where(string $where): string
{
    return str_replace(
        ['WHERE reseller_id', 'WHERE manager_id', 'AND reseller_id', 'AND manager_id', 'OR reseller_id', 'OR manager_id'],
        ['WHERE eu.reseller_id', 'WHERE eu.manager_id', 'AND eu.reseller_id', 'AND eu.manager_id', 'OR eu.reseller_id', 'OR eu.manager_id'],
        $where
    );
}

[$userWhere, $userParams] = scope_where_for_users($admin);
[$leadWhere, $leadParams] = scope_where_for_leads($admin);
$realClientCondition = 'onboarding_completed_at IS NOT NULL AND (platform <> "web" OR EXISTS (
    SELECT 1 FROM platform_accounts pac WHERE pac.end_user_id = end_users.id AND pac.platform <> "web"
)) AND NOT EXISTS (SELECT 1 FROM resellers rs WHERE rs.source_end_user_id = end_users.id)
   AND NOT EXISTS (SELECT 1 FROM managers ms WHERE ms.source_end_user_id = end_users.id)';
$webVisitorCondition = 'platform = "web" AND NOT EXISTS (
    SELECT 1 FROM platform_accounts pav WHERE pav.end_user_id = end_users.id AND pav.platform <> "web"
) AND NOT EXISTS (SELECT 1 FROM resellers rs WHERE rs.source_end_user_id = end_users.id)
   AND NOT EXISTS (SELECT 1 FROM managers ms WHERE ms.source_end_user_id = end_users.id)';
$realClientAliasCondition = 'eu.onboarding_completed_at IS NOT NULL AND (eu.platform <> "web" OR EXISTS (
    SELECT 1 FROM platform_accounts pac WHERE pac.end_user_id = eu.id AND pac.platform <> "web"
)) AND NOT EXISTS (SELECT 1 FROM resellers rs WHERE rs.source_end_user_id = eu.id)
   AND NOT EXISTS (SELECT 1 FROM managers ms WHERE ms.source_end_user_id = eu.id)';
$assignedUserWhere = append_where_condition($userWhere, $realClientCondition);
$visitorUserWhere = append_where_condition($userWhere, $webVisitorCondition);
$assignedUserAliasWhere = append_where_condition(alias_user_where($userWhere), $realClientAliasCondition);

$stats = [
    'users' => count_table("SELECT COUNT(*) FROM end_users $assignedUserWhere", $userParams),
    'new_today' => count_table("SELECT COUNT(*) FROM end_users $assignedUserWhere AND DATE(created_at) = CURRENT_DATE", $userParams),
    'visitors' => count_table("SELECT COUNT(*) FROM end_users $visitorUserWhere", $userParams),
    'managers' => count_table($admin['role'] === 'superadmin'
        ? 'SELECT COUNT(*) FROM managers'
        : 'SELECT COUNT(*) FROM managers WHERE reseller_id = :reseller_id',
        $admin['role'] === 'superadmin' ? [] : ['reseller_id' => $admin['reseller_id']]
    ),
    'resellers' => $admin['role'] === 'superadmin' ? count_table('SELECT COUNT(*) FROM resellers') : 0,
    'tests' => count_table(
        "SELECT COUNT(*) FROM user_test_sessions uts INNER JOIN end_users eu ON eu.id = uts.end_user_id $assignedUserAliasWhere
         AND uts.completed_at IS NOT NULL AND uts.is_preview = 0",
        $userParams
    ),
    'leads' => count_table("SELECT COUNT(*) FROM leads $leadWhere", $leadParams),
];

$stageStmt = db()->prepare(
    "SELECT client_stage, COUNT(*) AS total
     FROM end_users
     $assignedUserWhere
     GROUP BY client_stage"
);
$stageStmt->execute($userParams);
$stageStats = [];
foreach ($stageStmt->fetchAll() as $row) {
    $stageStats[(string)$row['client_stage']] = (int)$row['total'];
}

$subscription = null;
$subscriptionResellerId = (int)($admin['reseller_id'] ?? 0);
if ($subscriptionResellerId > 0) {
    $subscriptionStmt = db()->prepare(
        'SELECT * FROM leader_subscriptions
         WHERE reseller_id = :reseller_id
         ORDER BY id DESC
         LIMIT 1'
    );
    $subscriptionStmt->execute(['reseller_id' => $subscriptionResellerId]);
    $subscription = $subscriptionStmt->fetch() ?: null;
}

$recentStmt = db()->prepare("SELECT id, platform, username, first_name, created_at FROM end_users $assignedUserWhere ORDER BY id DESC LIMIT 8");
$recentStmt->execute($userParams);
$recentUsers = $recentStmt->fetchAll();

$platformStmt = db()->prepare("SELECT platform, COUNT(*) AS total FROM end_users $assignedUserWhere GROUP BY platform ORDER BY total DESC");
$platformStmt->execute($userParams);
$platforms = $platformStmt->fetchAll();
$dashboardManager = dashboard_manager($admin);
$dashboardReseller = dashboard_reseller($admin);
$referralOwner = $dashboardManager ?: $dashboardReseller;
$referralTitle = $dashboardManager ? app_text('referrals.dashboard_title') : 'Реферальная ссылка лидера';
$referralLinks = $referralOwner ? manager_referral_links($referralOwner) : [];
$billingWorkspace = in_array($admin['role'] ?? '', ['reseller', 'manager'], true)
    ? billing_workspace_for_admin($admin) : null;
$todayActions = in_array($admin['role'] ?? '', ['reseller', 'manager'], true)
    ? array_slice(ai_workflow_refresh_actions($admin), 0, 5) : [];
if ($billingWorkspace) {
    billing_refresh_statuses();
    $billingWorkspace = billing_workspace_for_admin($admin);
}

require __DIR__ . '/../app/views/layouts/header.php';
?>
<?php if ($billingWorkspace): ?>
    <section class="panel dashboard-billing-card <?= in_array($billingWorkspace['status'], ['overdue','suspended'], true) ? 'billing-overdue' : '' ?>">
        <div><span class="eyebrow">Подписка рабочего места</span><h2><?= h(subscription_money_text((float)$billingWorkspace['monthly_price'])) ?> в месяц</h2>
        <?php if ($billingWorkspace['billing_mode'] === 'prepaid'): ?><p>Оплачено до: <strong><?= h(!empty($billingWorkspace['paid_until']) ? app_date_ru($billingWorkspace['paid_until']) : 'нет оплаты') ?></strong></p>
        <?php else: ?><p>Расчёт по факту за календарный месяц. Просрочка учитывается только за завершённый месяц.</p><?php endif; ?></div>
        <div><strong><?= $billingWorkspace['status'] === 'overdue' ? 'Есть задолженность' : ($billingWorkspace['status'] === 'due' ? 'Ожидается оплата' : 'Доступ активен') ?></strong><br><a class="button" href="billing.php">Открыть и оплатить</a></div>
    </section>
<?php endif; ?>
<?php if ($referralOwner && $referralLinks): ?>
    <section class="panel referral-panel">
        <div>
            <h2><?= h($referralTitle) ?></h2>
            <p class="cell-muted"><?= h(app_text('referrals.code')) ?>: <strong><?= h((string)$referralOwner['referral_code']) ?></strong></p>
        </div>
        <div class="referral-controls">
            <label class="field">
                <span><?= h(app_text('referrals.platform')) ?></span>
                <select id="referral-platform">
                    <?php foreach ($referralLinks as $platform => $item): ?>
                        <option value="<?= h($platform) ?>"><?= h($item['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field referral-link-field">
                <span><?= h(app_text('referrals.link')) ?></span>
                <input id="referral-link" readonly value="<?= h((string)reset($referralLinks)['url']) ?>">
            </label>
            <button type="button" id="copy-referral-link"><?= h(app_text('referrals.copy')) ?></button>
            <?php if (isset($referralLinks['web_app'])): ?>
                <button type="button" class="secondary-button" id="open-owner-mini-site">Открыть мой мини-сайт</button>
                <span class="cell-muted" id="owner-mini-site-status"></span>
            <?php endif; ?>
        </div>
    </section>
    <script>
        window.swproReferralLinks = <?= json_encode($referralLinks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.swproReferralTexts = <?= json_encode([
            'copy' => app_text('referrals.copy'),
            'copied' => app_text('referrals.copied'),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.swproOwnerMiniSite = <?= json_encode([
            'url' => $referralLinks['web_app']['url'] ?? '',
            'csrf' => csrf_token(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        document.addEventListener('DOMContentLoaded', () => {
            const select = document.querySelector('#referral-platform');
            const input = document.querySelector('#referral-link');
            const button = document.querySelector('#copy-referral-link');
            if (!select || !input || !button) return;
            select.addEventListener('change', () => {
                input.value = window.swproReferralLinks[select.value]?.url || '';
            });
            button.addEventListener('click', async () => {
                input.select();
                try {
                    await navigator.clipboard.writeText(input.value);
                    button.textContent = window.swproReferralTexts.copied;
                    setTimeout(() => button.textContent = window.swproReferralTexts.copy, 1600);
                } catch (_) {
                    document.execCommand('copy');
                }
            });

            const ownerButton = document.querySelector('#open-owner-mini-site');
            const ownerStatus = document.querySelector('#owner-mini-site-status');
            ownerButton?.addEventListener('click', async () => {
                ownerButton.disabled = true;
                if (ownerStatus) ownerStatus.textContent = 'Подключаем этот браузер…';
                try {
                    let webUserId = localStorage.getItem('swpro_web_user_id');
                    if (!webUserId) {
                        webUserId = `web-${crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random().toString(16).slice(2)}`}`;
                        localStorage.setItem('swpro_web_user_id', webUserId);
                    }
                    const body = new URLSearchParams({
                        csrf_token: window.swproOwnerMiniSite.csrf,
                        web_user_id: webUserId,
                    });
                    const response = await fetch('bind_web_preview.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
                        body,
                        credentials: 'same-origin',
                    });
                    const result = await response.json();
                    if (!response.ok) throw new Error(result.error || 'Не удалось открыть мини-сайт.');
                    if (ownerStatus) ownerStatus.textContent = '';
                    window.open(window.swproOwnerMiniSite.url, '_blank', 'noopener');
                } catch (error) {
                    if (ownerStatus) ownerStatus.textContent = error instanceof Error ? error.message : 'Не удалось открыть мини-сайт.';
                } finally {
                    ownerButton.disabled = false;
                }
            });
        });
    </script>
<?php endif; ?>

<?php if ($subscription): ?>
    <section class="panel">
        <h2>Доступ лидера</h2>
        <p>Статус: <strong><?= h((string)$subscription['status']) ?></strong></p>
        <p class="cell-muted">Действует до: <?= h((string)($subscription['ends_at'] ?: 'без ограничения')) ?></p>
    </section>
<?php endif; ?>

<?php if (in_array($admin['role'] ?? '', ['reseller', 'manager'], true)): ?>
    <section class="panel dashboard-today-panel">
        <div class="page-title-row"><div><span class="eyebrow">Ежедневный план</span><h2>Что сделать сегодня</h2><p class="cell-muted">Клиенты, которым сейчас особенно полезно ваше внимание.</p></div><a class="button secondary-button" href="ai_actions.php">Открыть весь список</a></div>
        <?php if ($todayActions): ?><div class="dashboard-action-list">
            <?php foreach ($todayActions as $action): ?><a href="ai_actions.php" class="dashboard-action-item"><span class="badge"><?= (int)$action['priority'] ?></span><span><strong><?= h(trim((string)$action['first_name'] . ' ' . (string)$action['last_name']) ?: 'Клиент') ?> — <?= h((string)$action['title']) ?></strong><small><?= h((string)$action['reason_text']) ?></small></span></a><?php endforeach; ?>
        </div><?php else: ?><p class="empty-state">На сегодня срочных действий нет.</p><?php endif; ?>
    </section>
<?php endif; ?>

<div class="grid stats-grid">
    <a class="stat" href="crud.php?module=users&user_scope=clients"><span>Клиенты</span><strong><?= $stats['users'] ?></strong></a>
    <a class="stat" href="crud.php?module=users&user_scope=clients&client_stage=new"><span>Присоединились сегодня</span><strong><?= $stats['new_today'] ?></strong></a>
    <?php if ($admin['role'] === 'superadmin'): ?><a class="stat" href="crud.php?module=users&user_scope=visitors"><span>Без консультанта</span><strong><?= $stats['visitors'] ?></strong></a><?php endif; ?>
    <?php if (can_manage('managers', $admin)): ?><a class="stat" href="crud.php?module=managers"><span>Консультанты</span><strong><?= $stats['managers'] ?></strong></a><?php endif; ?>
    <?php if (can_manage('resellers', $admin)): ?><a class="stat" href="crud.php?module=resellers"><span>Лидеры</span><strong><?= $stats['resellers'] ?></strong></a><?php endif; ?>
    <a class="stat" href="results.php"><span>Завершили чек-ап</span><strong><?= $stats['tests'] ?></strong></a>
    <a class="stat" href="crud.php?module=leads"><span>Обращения</span><strong><?= $stats['leads'] ?></strong></a>
    <a class="stat" href="crud.php?module=users&user_scope=clients&client_stage=test_started"><span>Чек-ап начат</span><strong><?= $stageStats['test_started'] ?? 0 ?></strong></a>
    <a class="stat" href="crud.php?module=users&user_scope=clients&client_stage=consultation_requested"><span>Ждут связи</span><strong><?= $stageStats['consultation_requested'] ?? 0 ?></strong></a>
</div>

<div class="two-columns">
    <section class="panel">
        <h2><?= h(app_text('auto.k_be171d445786')) ?></h2>
        <table>
            <thead><tr><th>ID</th><th><?= h(app_text('auto.k_89009febe5c6')) ?></th><th><?= h(app_text('auto.k_aee78fe86022')) ?></th><th><?= h(app_text('auto.k_a5b49d2ebad2')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($recentUsers as $user): ?>
                <tr>
                    <td><?= (int)$user['id'] ?></td>
                    <td><?= h($user['platform']) ?></td>
                    <td><?= h($user['username'] ?: $user['first_name']) ?></td>
                    <td><?= h(app_date_ru($user['created_at'], true)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <section class="panel">
        <h2><?= h(app_text('auto.k_ac1bdba05b4e')) ?></h2>
        <table>
            <thead><tr><th><?= h(app_text('auto.k_89009febe5c6')) ?></th><th><?= h(app_text('auto.k_0f0b8f55edcc')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($platforms as $platform): ?>
                <tr>
                    <td><?= h($platform['platform']) ?></td>
                    <td><?= (int)$platform['total'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</div>
<?php require __DIR__ . '/../app/views/layouts/footer.php'; ?>
