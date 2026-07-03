<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/permissions.php';
require_once __DIR__ . '/../app/core/client_journey.php';

$admin = require_auth();
if (!can_manage('results', $admin)) {
    http_response_code(403);
    exit('Access denied');
}

$title = 'Результаты чек-апов';
$where = ['uts.completed_at IS NOT NULL', 'eu.merged_into_user_id IS NULL'];
$params = [];
if ($admin['role'] === 'reseller') {
    $where[] = 'eu.reseller_id = :reseller_id';
    $params['reseller_id'] = (int)$admin['reseller_id'];
} elseif ($admin['role'] === 'manager') {
    $where[] = 'eu.manager_id = :manager_id';
    $params['manager_id'] = (int)$admin['manager_id'];
}

$userId = (int)($_GET['user_id'] ?? 0);
if ($userId > 0) {
    $where[] = 'eu.id = :user_id';
    $params['user_id'] = $userId;
}

$stmt = db()->prepare(
    'SELECT uts.*, t.title AS test_title,
            eu.id AS end_user_id, eu.first_name, eu.last_name, eu.username,
            eu.gender, eu.birth_date, eu.age_years, eu.city, eu.client_stage,
            m.name AS manager_name
     FROM user_test_sessions uts
     INNER JOIN tests t ON t.id = uts.test_id
     INNER JOIN end_users eu ON eu.id = uts.end_user_id
     LEFT JOIN managers m ON m.id = eu.manager_id
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY uts.completed_at DESC, uts.id DESC
     LIMIT 100'
);
$stmt->execute($params);
$sessions = $stmt->fetchAll();

if ($admin['role'] === 'manager') {
    $mark = db()->prepare(
        'UPDATE consultant_notifications
         SET is_read = 1, read_at = NOW()
         WHERE manager_id = :manager_id AND notification_type = "test_completed" AND is_read = 0'
    );
    $mark->execute(['manager_id' => $admin['manager_id']]);
}

function result_scale_items(int $sessionId): array
{
    $stmt = db()->prepare(
        'SELECT ts.title, uts.score, tsr.title AS result_title, tsr.severity,
                tsr.summary_text, tsr.advice_text
         FROM user_test_scale_scores uts
         INNER JOIN test_scales ts ON ts.id = uts.scale_id
         LEFT JOIN test_scale_results tsr ON tsr.id = uts.result_id
         WHERE uts.session_id = :session_id
         ORDER BY ts.sort_order, ts.id'
    );
    $stmt->execute(['session_id' => $sessionId]);
    return $stmt->fetchAll();
}

require __DIR__ . '/../app/views/layouts/header.php';
?>
<div class="toolbar">
    <h1>Результаты чек-апов</h1>
    <?php if ($userId): ?><a class="button secondary-button" href="results.php">Все результаты</a><?php endif; ?>
</div>

<?php if (!$sessions): ?>
    <section class="panel"><div class="empty-state">Завершённых чек-апов пока нет.</div></section>
<?php endif; ?>

<div class="result-admin-list">
    <?php foreach ($sessions as $session): ?>
        <?php
        $name = trim((string)$session['first_name'] . ' ' . (string)$session['last_name']);
        $name = $name !== '' ? $name : ((string)$session['username'] ?: 'Клиент #' . (int)$session['end_user_id']);
        $age = $session['age_years'];
        if (!$age && $session['birth_date']) {
            $age = date_diff(date_create((string)$session['birth_date']), date_create('today'))->y;
        }
        $gender = client_gender_labels()[(string)($session['gender'] ?? '')] ?? '—';
        $scales = result_scale_items((int)$session['id']);
        ?>
        <article class="panel result-admin-card">
            <div class="result-admin-head">
                <div>
                    <span class="eyebrow"><?= h((string)$session['test_title']) ?></span>
                    <h2><?= h($name) ?></h2>
                    <p class="cell-muted"><?= h(trim((string)$session['city'] . ' · ' . $gender . ($age ? ' · ' . $age . ' лет' : ''))) ?></p>
                </div>
                <div>
                    <span class="badge badge-sent"><?= h((string)$session['completed_at']) ?></span>
                    <a class="button secondary-button" href="crud.php?module=users&action=edit&id=<?= (int)$session['end_user_id'] ?>">Карточка клиента</a>
                </div>
            </div>
            <div class="result-admin-summary"><?= nl2br(h((string)$session['result_summary'])) ?></div>
            <?php if ($scales): ?>
                <div class="result-admin-scales">
                    <?php foreach ($scales as $scale): ?>
                        <div>
                            <strong><?= h((string)$scale['title']) ?></strong>
                            <span><?= (int)$scale['score'] ?> · <?= h((string)($scale['result_title'] ?: 'Результат')) ?></span>
                            <?php if ($scale['summary_text']): ?><p><?= h((string)$scale['summary_text']) ?></p><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/../app/views/layouts/footer.php'; ?>
