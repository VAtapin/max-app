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

$perPage = 25;
$page = max(1, (int)($_GET['page'] ?? 1));

$countStmt = db()->prepare(
    'SELECT COUNT(*)
     FROM user_test_sessions uts
     INNER JOIN end_users eu ON eu.id = uts.end_user_id
     WHERE ' . implode(' AND ', $where)
);
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

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
     LIMIT ' . $perPage . ' OFFSET ' . $offset
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
    $query = $_GET;
    $query['page'] = $page;
    return 'results.php?' . http_build_query($query);
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

$sessionIds = array_map(static fn(array $session): int => (int)$session['id'], $sessions);
$scalesBySession = result_scale_items_for_sessions($sessionIds);

require __DIR__ . '/../app/views/layouts/header.php';
?>
<div class="toolbar">
    <div>
        <h1>Результаты чек-апов</h1>
        <p class="cell-muted">Найдено записей: <?= (int)$totalRows ?></p>
    </div>
    <?php if ($userId): ?><a class="button secondary-button" href="results.php">Все результаты</a><?php endif; ?>
</div>

<?php if (!$sessions): ?>
    <section class="panel"><div class="empty-state">Завершённых чек-апов пока нет.</div></section>
<?php else: ?>
    <section class="panel results-table-panel">
        <div class="table-summary">
            Страница <?= (int)$page ?> из <?= (int)$totalPages ?> · показано <?= count($sessions) ?> из <?= (int)$totalRows ?>
        </div>
        <div class="table-scroll">
            <table class="data-table results-table">
                <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Клиент</th>
                        <th>Анкета</th>
                        <th>Тест</th>
                        <th>Главный сигнал</th>
                        <th>Консультант</th>
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
                        <td><span class="badge badge-sent"><?= h((string)$session['completed_at']) ?></span></td>
                        <td>
                            <strong><?= h(result_client_name($session)) ?></strong>
                            <div class="cell-muted">ID <?= (int)$session['end_user_id'] ?></div>
                        </td>
                        <td><?= h(result_client_profile_line($session)) ?></td>
                        <td><?= h((string)$session['test_title']) ?></td>
                        <td><?= h(result_top_scale_label($scales)) ?></td>
                        <td><?= h((string)($session['manager_name'] ?: '—')) ?></td>
                        <td>
                            <button type="button" class="button secondary-button compact-button" data-result-modal="<?= h($modalId) ?>">Открыть</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?>
            <nav class="pagination results-pagination" aria-label="Страницы результатов">
                <?php if ($page > 1): ?>
                    <a class="button secondary-button" href="<?= h(result_pagination_url($page - 1)) ?>">Назад</a>
                <?php endif; ?>
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                ?>
                <?php if ($startPage > 1): ?>
                    <a class="button secondary-button" href="<?= h(result_pagination_url(1)) ?>">1</a>
                    <?php if ($startPage > 2): ?><span class="pagination-gap">...</span><?php endif; ?>
                <?php endif; ?>
                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="button pagination-current"><?= (int)$i ?></span>
                    <?php else: ?>
                        <a class="button secondary-button" href="<?= h(result_pagination_url($i)) ?>"><?= (int)$i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?><span class="pagination-gap">...</span><?php endif; ?>
                    <a class="button secondary-button" href="<?= h(result_pagination_url($totalPages)) ?>"><?= (int)$totalPages ?></a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a class="button secondary-button" href="<?= h(result_pagination_url($page + 1)) ?>">Вперёд</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>

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
                </div>
                <div class="modal-actions">
                    <a class="button secondary-button" href="crud.php?module=users&action=edit&id=<?= (int)$session['end_user_id'] ?>">Карточка клиента</a>
                    <form method="dialog"><button type="submit" class="secondary-button">Закрыть</button></form>
                </div>
            </div>
        </dialog>
    <?php endforeach; ?>
<?php endif; ?>

<script>
document.querySelectorAll('[data-result-modal]').forEach((element) => {
    element.addEventListener('click', (event) => {
        if (event.target instanceof HTMLAnchorElement) {
            return;
        }
        if (element instanceof HTMLButtonElement) {
            event.stopPropagation();
        }
        const modalId = element.dataset.resultModal;
        const modal = modalId ? document.getElementById(modalId) : null;
        if (modal && typeof modal.showModal === 'function' && !modal.open) {
            modal.showModal();
        }
    });
});

document.querySelectorAll('.admin-modal').forEach((modal) => {
    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            modal.close();
        }
    });
});
</script>
<?php require __DIR__ . '/../app/views/layouts/footer.php'; ?>
