<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/permissions.php';
require_once __DIR__ . '/../app/core/client_journey.php';
require_once __DIR__ . '/../app/core/table_ui.php';

$admin = require_auth();
if (!can_manage('results', $admin)) {
    http_response_code(403);
    exit('Access denied');
}

$title = 'Результаты чек-апов';
$where = [
    'uts.completed_at IS NOT NULL',
    'uts.is_preview = 0',
    'eu.merged_into_user_id IS NULL',
    'NOT EXISTS (SELECT 1 FROM resellers rs WHERE rs.source_end_user_id = eu.id)',
    'NOT EXISTS (SELECT 1 FROM managers ms WHERE ms.source_end_user_id = eu.id)',
];
$params = [];
if ($admin['role'] === 'reseller') {
    $where[] = 'eu.reseller_id = :reseller_id';
    $params['reseller_id'] = (int)$admin['reseller_id'];
} elseif ($admin['role'] === 'manager') {
    $where[] = 'eu.manager_id = :manager_id';
    $params['manager_id'] = (int)$admin['manager_id'];
}

$userId = (int)($_GET['user_id'] ?? 0);
$isModalRequest = ($_GET['modal'] ?? '') === '1';
if ($userId > 0) {
    $where[] = 'eu.id = :user_id';
    $params['user_id'] = $userId;
}

$resultSortMap = [
    'id' => '`id`',
    'completed_at' => '`completed_at`',
    'client_name' => '`client_name`',
    'test_title' => '`test_title`',
    'manager_name' => '`manager_name`',
];

$pageData = admin_table_paginated_rows(
    'SELECT uts.*, t.title AS test_title,
            eu.first_name, eu.last_name, eu.username,
            eu.gender, eu.birth_date, eu.age_years, eu.city, eu.client_stage,
            TRIM(CONCAT(COALESCE(eu.first_name, \'\'), \' \', COALESCE(eu.last_name, \'\'))) AS client_name,
            m.name AS manager_name
     FROM user_test_sessions uts
     INNER JOIN tests t ON t.id = uts.test_id
     INNER JOIN end_users eu ON eu.id = uts.end_user_id
     LEFT JOIN managers m ON m.id = eu.manager_id
     WHERE ' . implode(' AND ', $where),
    $params,
    $resultSortMap,
    ['test_title', 'client_name', 'username', 'city', 'client_stage', 'manager_name'],
    'completed_at',
    'desc'
);
$sessions = $pageData['rows'];
$tableMeta = $pageData['meta'];
$totalRows = (int)$tableMeta['total'];
$page = (int)$tableMeta['page'];
$totalPages = (int)$tableMeta['page_count'];

if ($admin['role'] === 'manager') {
    try {
        $mark = db()->prepare(
            'UPDATE consultant_notifications
             SET is_read = 1, read_at = NOW()
             WHERE manager_id = :manager_id AND notification_type = :notification_type AND is_read = 0'
        );
        $mark->execute([
            'manager_id' => (int)$admin['manager_id'],
            'notification_type' => 'test_completed',
        ]);
    } catch (Throwable $e) {
        error_log('Failed to mark test result notifications as read: ' . $e->getMessage());
    }
}

function result_client_name(array $session): string
{
    $name = trim((string)$session['first_name'] . ' ' . (string)$session['last_name']);
    return $name !== '' ? $name : ((string)$session['username'] ?: 'Клиент #' . (int)$session['end_user_id']);
}

function result_client_age(array $session): ?int
{
    $age = !empty($session['age_years']) ? (int)$session['age_years'] : null;
    if (!$age && !empty($session['birth_date'])) {
        $birthDate = date_create((string)$session['birth_date']);
        if ($birthDate) {
            $age = date_diff($birthDate, date_create('today'))->y;
        }
    }
    return $age ?: null;
}

function result_client_profile_line(array $session): string
{
    $parts = [];
    if (!empty($session['city'])) {
        $parts[] = (string)$session['city'];
    }
    $gender = client_gender_labels()[(string)($session['gender'] ?? '')] ?? '';
    if ($gender !== '') {
        $parts[] = $gender;
    }
    $age = result_client_age($session);
    if ($age) {
        $parts[] = $age . ' лет';
    }
    return $parts ? implode(' · ', $parts) : '—';
}

function result_pagination_url(int $page): string
{
    return admin_table_url(['page' => $page], 'results.php');
}

function result_scale_items_for_sessions(array $sessionIds): array
{
    if (!$sessionIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($sessionIds), '?'));
    $stmt = db()->prepare(
        'SELECT uts.session_id, ts.title, uts.score, tsr.title AS result_title, tsr.severity,
                tsr.summary_text, tsr.advice_text
         FROM user_test_scale_scores uts
         INNER JOIN test_scales ts ON ts.id = uts.scale_id
         LEFT JOIN test_scale_results tsr ON tsr.id = uts.result_id
         WHERE uts.session_id IN (' . $placeholders . ')
         ORDER BY uts.session_id, ts.sort_order, ts.id'
    );
    $stmt->execute($sessionIds);

    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $items[(int)$row['session_id']][] = $row;
    }
    return $items;
}

function result_top_scale_label(array $scales): string
{
    if (!$scales) {
        return '—';
    }

    usort($scales, static fn(array $a, array $b): int => (int)$b['score'] <=> (int)$a['score']);
    $top = $scales[0];
    return (string)$top['title'] . ': ' . (int)$top['score'] . ' · ' . ((string)($top['result_title'] ?: 'Результат'));
}

function render_result_detail_body(array $session, array $scales): string
{
    ob_start();
    ?>
    <div class="result-admin-summary"><?= nl2br(h((string)$session['result_summary'])) ?></div>
    <?php if ($scales): ?>
        <div class="result-admin-scales">
            <?php foreach ($scales as $scale): ?>
                <div class="severity-<?= h((string)($scale['severity'] ?: 'neutral')) ?>">
                    <strong><?= h((string)$scale['title']) ?></strong>
                    <span><?= (int)$scale['score'] ?> · <?= h((string)($scale['result_title'] ?: 'Результат')) ?></span>
                    <?php if ($scale['summary_text']): ?><p><?= h((string)$scale['summary_text']) ?></p><?php endif; ?>
                    <?php if ($scale['advice_text']): ?><p><?= h((string)$scale['advice_text']) ?></p><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php
    return trim(ob_get_clean());
}

$sessionIds = array_map(static fn(array $session): int => (int)$session['id'], $sessions);
$scalesBySession = result_scale_items_for_sessions($sessionIds);

if ($isModalRequest) {
    ?>
    <div class="modal-shell">
        <div class="modal-head">
            <div>
                <span class="eyebrow">Результаты чек-апов</span>
                <h2><?= $sessions ? h(result_client_name($sessions[0])) : 'Результаты клиента' ?></h2>
                <p class="cell-muted">Найдено записей: <?= (int)$totalRows ?></p>
            </div>
            <form method="dialog"><button class="icon-button" aria-label="Закрыть">&times;</button></form>
        </div>
        <div class="modal-body">
            <?php if (!$sessions): ?>
                <div class="empty-state">Завершённых чек-апов пока нет.</div>
            <?php else: ?>
                <div class="result-admin-list">
                    <?php foreach ($sessions as $session): ?>
                        <?php
                        $sessionId = (int)$session['id'];
                        $scales = $scalesBySession[$sessionId] ?? [];
                        ?>
                        <article class="result-admin-card">
                            <div class="result-admin-head">
                                <div>
                                    <span class="eyebrow"><?= h((string)$session['test_title']) ?></span>
                                    <h3><?= h((string)$session['completed_at']) ?></h3>
                                    <p class="cell-muted"><?= h(result_client_profile_line($session)) ?></p>
                                </div>
                                <span class="badge badge-sent"><?= h(result_top_scale_label($scales)) ?></span>
                            </div>
                            <?= render_result_detail_body($session, $scales) ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="modal-actions">
            <a class="button secondary-button" href="results.php?user_id=<?= (int)$userId ?>">Открыть отдельной страницей</a>
            <form method="dialog"><button type="submit" class="secondary-button">Закрыть</button></form>
        </div>
    </div>
    <?php
    exit;
}

require __DIR__ . '/../app/views/layouts/header.php';
?>
<div class="toolbar">
    <div>
        <h1>Результаты чек-апов</h1>
        <p class="cell-muted">Найдено записей: <?= (int)$totalRows ?></p>
    </div>
    <?php if ($userId): ?><a class="button secondary-button" href="results.php">Все результаты</a><?php endif; ?>
</div>

<section class="panel results-table-panel">
    <?= render_admin_table_tools($tableMeta, [], [
        'hidden' => $userId > 0 ? ['user_id' => $userId] : [],
        'reset_url' => $userId > 0 ? 'results.php?user_id=' . (int)$userId : 'results.php',
        'search_placeholder' => 'Клиент, город, тест, консультант',
    ]) ?>
    <?php if (!$sessions): ?>
        <div class="empty-state">Завершённых чек-апов пока нет.</div>
    <?php else: ?>
        <div class="table-summary">
            Страница <?= (int)$page ?> из <?= (int)$totalPages ?> · показано <?= count($sessions) ?> из <?= (int)$totalRows ?>
        </div>
        <div class="table-scroll">
            <table class="data-table responsive-table results-table" data-module="results">
                <thead>
                    <tr>
                        <th><?= render_admin_sort_link('completed_at', 'Дата', $tableMeta, $resultSortMap, 'results.php') ?></th>
                        <th><?= render_admin_sort_link('client_name', 'Клиент', $tableMeta, $resultSortMap, 'results.php') ?></th>
                        <th>Анкета</th>
                        <th><?= render_admin_sort_link('test_title', 'Тест', $tableMeta, $resultSortMap, 'results.php') ?></th>
                        <th>Главный сигнал</th>
                        <th><?= render_admin_sort_link('manager_name', 'Консультант', $tableMeta, $resultSortMap, 'results.php') ?></th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($sessions as $session): ?>
                    <?php
                    $sessionId = (int)$session['id'];
                    $scales = $scalesBySession[$sessionId] ?? [];
                    $modalId = 'result-modal-' . $sessionId;
                    ?>
                    <tr class="clickable-row" data-result-modal="<?= h($modalId) ?>">
                        <td data-label="Дата" data-column="completed_at"><span class="badge badge-sent"><?= h((string)$session['completed_at']) ?></span></td>
                        <td data-label="Клиент" data-column="client_name">
                            <strong><?= h(result_client_name($session)) ?></strong>
                            <div class="cell-muted">ID <?= (int)$session['end_user_id'] ?></div>
                        </td>
                        <td data-label="Анкета" data-column="client_profile"><?= h(result_client_profile_line($session)) ?></td>
                        <td data-label="Тест" data-column="test_title"><?= h((string)$session['test_title']) ?></td>
                        <td data-label="Главный сигнал" data-column="top_signal"><?= h(result_top_scale_label($scales)) ?></td>
                        <td data-label="Консультант" data-column="manager_name"><?= h((string)($session['manager_name'] ?: '—')) ?></td>
                        <td data-label="Действия" data-column="actions">
                            <button type="button" class="button secondary-button compact-button" data-result-modal="<?= h($modalId) ?>">Открыть</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= render_admin_pagination($tableMeta, 'results.php') ?>
    <?php endif; ?>
</section>

<?php if ($sessions): ?>
    <?php foreach ($sessions as $session): ?>
        <?php
        $sessionId = (int)$session['id'];
        $scales = $scalesBySession[$sessionId] ?? [];
        $modalId = 'result-modal-' . $sessionId;
        ?>
        <dialog class="admin-modal result-detail-modal" id="<?= h($modalId) ?>">
            <div class="modal-shell">
                <div class="modal-head">
                    <div>
                        <span class="eyebrow"><?= h((string)$session['test_title']) ?></span>
                        <h2><?= h(result_client_name($session)) ?></h2>
                        <p class="cell-muted"><?= h(result_client_profile_line($session)) ?> · <?= h((string)$session['completed_at']) ?></p>
                    </div>
                    <form method="dialog"><button class="icon-button" aria-label="Закрыть">&times;</button></form>
                </div>
                <div class="modal-body">
                    <?= render_result_detail_body($session, $scales) ?>
                </div>
                <div class="modal-actions">
                    <a class="button secondary-button" href="crud.php?module=users&action=edit&id=<?= (int)$session['end_user_id'] ?>">Карточка клиента</a>
                    <form method="dialog"><button type="submit" class="secondary-button">Закрыть</button></form>
                </div>
            </div>
        </dialog>
    <?php endforeach; ?>
<?php endif; ?>
<?php require __DIR__ . '/../app/views/layouts/footer.php'; ?>
