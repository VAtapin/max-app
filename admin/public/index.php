<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/permissions.php';

$admin = require_auth();
$title = 'Dashboard';

function count_table(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

[$userWhere, $userParams] = scope_where_for_users($admin);
[$leadWhere, $leadParams] = scope_where_for_leads($admin);

$stats = [
    'users' => count_table("SELECT COUNT(*) FROM end_users $userWhere", $userParams),
    'new_today' => count_table("SELECT COUNT(*) FROM end_users $userWhere " . ($userWhere ? 'AND' : 'WHERE') . ' DATE(created_at) = CURRENT_DATE', $userParams),
    'managers' => count_table($admin['role'] === 'superadmin'
        ? 'SELECT COUNT(*) FROM managers'
        : 'SELECT COUNT(*) FROM managers WHERE reseller_id = :reseller_id',
        $admin['role'] === 'superadmin' ? [] : ['reseller_id' => $admin['reseller_id']]
    ),
    'resellers' => $admin['role'] === 'superadmin' ? count_table('SELECT COUNT(*) FROM resellers') : 0,
    'tests' => count_table('SELECT COUNT(*) FROM user_test_sessions WHERE completed_at IS NOT NULL'),
    'leads' => count_table("SELECT COUNT(*) FROM leads $leadWhere", $leadParams),
];

$recentStmt = db()->prepare("SELECT id, platform, username, first_name, created_at FROM end_users $userWhere ORDER BY id DESC LIMIT 8");
$recentStmt->execute($userParams);
$recentUsers = $recentStmt->fetchAll();

$platformStmt = db()->prepare("SELECT platform, COUNT(*) AS total FROM end_users $userWhere GROUP BY platform ORDER BY total DESC");
$platformStmt->execute($userParams);
$platforms = $platformStmt->fetchAll();

require __DIR__ . '/../app/views/layouts/header.php';
?>
<div class="grid stats-grid">
    <div class="stat"><span>Пользователи</span><strong><?= $stats['users'] ?></strong></div>
    <div class="stat"><span>Новые сегодня</span><strong><?= $stats['new_today'] ?></strong></div>
    <div class="stat"><span>Менеджеры</span><strong><?= $stats['managers'] ?></strong></div>
    <div class="stat"><span>Реселлеры</span><strong><?= $stats['resellers'] ?></strong></div>
    <div class="stat"><span>Пройденные тесты</span><strong><?= $stats['tests'] ?></strong></div>
    <div class="stat"><span>Заявки</span><strong><?= $stats['leads'] ?></strong></div>
</div>

<div class="two-columns">
    <section class="panel">
        <h2>Последние регистрации</h2>
        <table>
            <thead><tr><th>ID</th><th>Платформа</th><th>Имя</th><th>Дата</th></tr></thead>
            <tbody>
            <?php foreach ($recentUsers as $user): ?>
                <tr>
                    <td><?= (int)$user['id'] ?></td>
                    <td><?= h($user['platform']) ?></td>
                    <td><?= h($user['username'] ?: $user['first_name']) ?></td>
                    <td><?= h($user['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <section class="panel">
        <h2>Активность по платформам</h2>
        <table>
            <thead><tr><th>Платформа</th><th>Пользователи</th></tr></thead>
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
