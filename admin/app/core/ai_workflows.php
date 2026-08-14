<?php

require_once __DIR__ . '/ai_center.php';
require_once __DIR__ . '/permissions.php';

function ai_workflow_setting_days(string $key, int $default, int $minimum = 1, int $maximum = 365): int
{
    return max($minimum, min($maximum, (int)(ai_setting($key, (string)$default) ?? $default)));
}

function ai_workflow_ensure_plan(array $user, int $sessionId): ?int
{
    if (!empty($user['staff_preview']) || $sessionId <= 0) {
        return null;
    }
    try {
        $existing = db()->prepare('SELECT id FROM client_action_plans WHERE test_session_id = :session_id LIMIT 1');
        $existing->execute(['session_id' => $sessionId]);
        $existingId = (int)$existing->fetchColumn();
        if ($existingId > 0) {
            return $existingId;
        }

        $owner = ai_owner_for_client($user);
        if ($owner['owner_id'] <= 0) {
            return null;
        }
        $days = ai_workflow_setting_days('ai.default_plan_days', 7, 7, 30);
        if (!in_array($days, [7, 14, 30], true)) {
            $days = 7;
        }
        $sessionStmt = db()->prepare('SELECT test_id, completed_at FROM user_test_sessions WHERE id = :id AND end_user_id = :user_id AND completed_at IS NOT NULL LIMIT 1');
        $sessionStmt->execute(['id' => $sessionId, 'user_id' => (int)$user['id']]);
        $session = $sessionStmt->fetch();
        if (!$session) {
            return null;
        }
        $titleStmt = db()->prepare('SELECT title FROM tests WHERE id = :id LIMIT 1');
        $titleStmt->execute(['id' => (int)$session['test_id']]);
        $testTitle = (string)($titleStmt->fetchColumn() ?: 'чек-апа');
        $startsOn = date('Y-m-d');
        $endsOn = date('Y-m-d', strtotime('+' . ($days - 1) . ' days'));
        $insert = db()->prepare('INSERT INTO client_action_plans (end_user_id, test_session_id, owner_type, owner_id, title, duration_days, starts_on, ends_on, status) VALUES (:user_id, :session_id, :owner_type, :owner_id, :title, :days, :starts_on, :ends_on, "active")');
        $insert->execute([
            'user_id' => (int)$user['id'],
            'session_id' => $sessionId,
            'owner_type' => $owner['owner_type'],
            'owner_id' => $owner['owner_id'],
            'title' => 'План после «' . $testTitle . '»',
            'days' => $days,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
        ]);
        $planId = (int)db()->lastInsertId();

        $sourceStmt = db()->prepare('SELECT ts.id source_id, "test_scale" source_type, ts.title, tsr.advice_text FROM user_test_scale_scores uss INNER JOIN test_scales ts ON ts.id = uss.scale_id LEFT JOIN test_scale_results tsr ON tsr.id = uss.result_id WHERE uss.session_id = :session_id AND COALESCE(tsr.advice_text, "") <> "" ORDER BY FIELD(tsr.severity, "critical", "risk", "good", "excellent"), uss.score DESC LIMIT 5');
        $sourceStmt->execute(['session_id' => $sessionId]);
        $sources = $sourceStmt->fetchAll();
        $productStmt = db()->prepare('SELECT p.id source_id, "product" source_type, p.title, COALESCE(NULLIF(r.reason_text, ""), p.usage_text, p.short_description) advice_text FROM recommendations r INNER JOIN products p ON p.id = r.product_id AND p.is_active = 1 AND p.is_deleted = 0 WHERE r.test_session_id = :session_id ORDER BY r.score DESC, r.id DESC LIMIT 3');
        $productStmt->execute(['session_id' => $sessionId]);
        $sources = array_merge($sources, $productStmt->fetchAll());
        if (!$sources) {
            $sources = [[
                'source_id' => null,
                'source_type' => 'test_result',
                'title' => 'Обсудить результат',
                'advice_text' => 'Посмотрите результат чек-апа и обсудите подходящие дальнейшие шаги со своим консультантом.',
            ]];
        }
        $itemInsert = db()->prepare('INSERT INTO client_action_plan_items (plan_id, day_number, time_of_day, title, instruction, product_id, source_type, source_id, sort_order) VALUES (:plan_id, :day_number, :time_of_day, :title, :instruction, :product_id, :source_type, :source_id, :sort_order)');
        for ($day = 1; $day <= $days; $day++) {
            $source = $sources[($day - 1) % count($sources)];
            $itemInsert->execute([
                'plan_id' => $planId,
                'day_number' => $day,
                'time_of_day' => 'any',
                'title' => (string)$source['title'],
                'instruction' => (string)$source['advice_text'],
                'product_id' => $source['source_type'] === 'product' ? (int)$source['source_id'] : null,
                'source_type' => (string)$source['source_type'],
                'source_id' => $source['source_id'] ? (int)$source['source_id'] : null,
                'sort_order' => $day * 10,
            ]);
        }
        return $planId;
    } catch (Throwable) {
        return null;
    }
}

function ai_workflow_test_comparison(int $endUserId, ?int $testId = null): ?array
{
    $sql = 'SELECT uts.id, uts.test_id, uts.total_score, uts.completed_at, t.title test_title FROM user_test_sessions uts INNER JOIN tests t ON t.id = uts.test_id WHERE uts.end_user_id = :user_id AND uts.completed_at IS NOT NULL AND uts.is_preview = 0';
    $params = ['user_id' => $endUserId];
    if ($testId) {
        $sql .= ' AND uts.test_id = :test_id';
        $params['test_id'] = $testId;
    } else {
        $sql .= ' AND uts.test_id = (SELECT test_id FROM user_test_sessions WHERE end_user_id = :latest_user AND completed_at IS NOT NULL AND is_preview = 0 ORDER BY completed_at DESC, id DESC LIMIT 1)';
        $params['latest_user'] = $endUserId;
    }
    $sql .= ' ORDER BY uts.completed_at DESC, uts.id DESC LIMIT 2';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $sessions = $stmt->fetchAll();
    if (count($sessions) < 2) {
        return null;
    }
    [$current, $previous] = $sessions;
    $scoresStmt = db()->prepare('SELECT uss.session_id, ts.id scale_id, ts.title, uss.score, tsr.title result_title, tsr.severity FROM user_test_scale_scores uss INNER JOIN test_scales ts ON ts.id = uss.scale_id LEFT JOIN test_scale_results tsr ON tsr.id = uss.result_id WHERE uss.session_id IN (:current_id, :previous_id) ORDER BY ts.sort_order, ts.id');
    $scoresStmt->execute(['current_id' => (int)$current['id'], 'previous_id' => (int)$previous['id']]);
    $byScale = [];
    foreach ($scoresStmt->fetchAll() as $row) {
        $scaleId = (int)$row['scale_id'];
        $byScale[$scaleId]['title'] = (string)$row['title'];
        $key = (int)$row['session_id'] === (int)$current['id'] ? 'current' : 'previous';
        $byScale[$scaleId][$key] = ['score' => (int)$row['score'], 'result_title' => $row['result_title'], 'severity' => $row['severity']];
    }
    $items = [];
    foreach ($byScale as $row) {
        if (!isset($row['current'], $row['previous'])) {
            continue;
        }
        $items[] = [
            'title' => $row['title'],
            'previous' => $row['previous'],
            'current' => $row['current'],
            'delta' => $row['current']['score'] - $row['previous']['score'],
        ];
    }
    return [
        'test_id' => (int)$current['test_id'],
        'test_title' => (string)$current['test_title'],
        'previous' => ['session_id' => (int)$previous['id'], 'total_score' => (int)$previous['total_score'], 'completed_at' => $previous['completed_at']],
        'current' => ['session_id' => (int)$current['id'], 'total_score' => (int)$current['total_score'], 'completed_at' => $current['completed_at']],
        'total_delta' => (int)$current['total_score'] - (int)$previous['total_score'],
        'scales' => $items,
        'disclaimer' => 'Сравниваются ответы в анкетах. Изменение баллов не является медицинским заключением.',
    ];
}

function ai_workflow_client_today(array $user): array
{
    $planStmt = db()->prepare('SELECT * FROM client_action_plans WHERE end_user_id = :user_id AND status IN ("active", "completed") ORDER BY status = "active" DESC, starts_on DESC, id DESC LIMIT 1');
    $planStmt->execute(['user_id' => (int)$user['id']]);
    $plan = $planStmt->fetch() ?: null;
    if (!$plan) {
        $latestPlanSource = db()->prepare('SELECT id FROM user_test_sessions WHERE end_user_id = :user_id AND completed_at IS NOT NULL AND is_preview = 0 ORDER BY completed_at DESC, id DESC LIMIT 1');
        $latestPlanSource->execute(['user_id' => (int)$user['id']]);
        $latestSessionId = (int)$latestPlanSource->fetchColumn();
        if ($latestSessionId > 0 && ai_workflow_ensure_plan($user, $latestSessionId)) {
            $planStmt->execute(['user_id' => (int)$user['id']]);
            $plan = $planStmt->fetch() ?: null;
        }
    }
    $items = [];
    if ($plan) {
        $itemStmt = db()->prepare('SELECT cpi.*, p.title product_title FROM client_action_plan_items cpi LEFT JOIN products p ON p.id = cpi.product_id WHERE cpi.plan_id = :plan_id ORDER BY cpi.day_number, cpi.sort_order, cpi.id');
        $itemStmt->execute(['plan_id' => (int)$plan['id']]);
        $items = $itemStmt->fetchAll();
        $completed = count(array_filter($items, static fn(array $item): bool => (int)$item['is_completed'] === 1));
        $plan['progress_percent'] = $items ? (int)round($completed * 100 / count($items)) : 0;
        $plan['completed_items'] = $completed;
        $plan['total_items'] = count($items);
        $plan['current_day'] = max(1, min((int)$plan['duration_days'], (int)((new DateTimeImmutable($plan['starts_on']))->diff(new DateTimeImmutable('today'))->days) + 1));
    }
    $latestStmt = db()->prepare('SELECT uts.id, uts.test_id, uts.completed_at, uts.result_summary, t.title test_title FROM user_test_sessions uts INNER JOIN tests t ON t.id = uts.test_id WHERE uts.end_user_id = :user_id AND uts.completed_at IS NOT NULL AND uts.is_preview = 0 ORDER BY uts.completed_at DESC, uts.id DESC LIMIT 1');
    $latestStmt->execute(['user_id' => (int)$user['id']]);
    $latest = $latestStmt->fetch() ?: null;
    $retestDays = ai_workflow_setting_days('ai.retest_after_days', 30, 7, 365);
    $retestDue = $latest ? date('Y-m-d', strtotime((string)$latest['completed_at'] . ' +' . $retestDays . ' days')) : null;
    return [
        'plan' => $plan,
        'plan_items' => $items,
        'latest_result' => $latest,
        'comparison' => ai_workflow_test_comparison((int)$user['id']),
        'retest_due_on' => $retestDue,
        'retest_available' => $retestDue !== null && $retestDue <= date('Y-m-d'),
    ];
}

function ai_workflow_action_message(string $type, array $user, array $context = []): string
{
    $name = trim((string)($user['first_name'] ?? ''));
    $hello = $name !== '' ? $name . ', здравствуйте!' : 'Здравствуйте!';
    return match ($type) {
        'birthday' => $hello . ' Поздравляю вас с днём рождения! Желаю хорошего самочувствия, энергии и радостных событий. Если захотите, я помогу подобрать полезные материалы на ближайшее время.',
        'test_result' => $hello . ' Вижу, что вы завершили чек-ап. Результат уже сохранён. Предлагаю вместе спокойно разобрать основные пункты и определить следующий полезный шаг.',
        'retest' => $hello . ' После прошлого чек-апа прошло около ' . (int)($context['days'] ?? 30) . ' дней. Можно пройти его повторно и сравнить ответы «было → стало». Это займёт несколько минут.',
        'inactive' => $hello . ' Давно не виделись в SWPro. Как вы себя чувствуете? Если появились вопросы по материалам, продуктам или прошлому чек-апу, напишите — помогу разобраться.',
        'plan_ending' => $hello . ' Ваш текущий план подходит к завершению. Давайте коротко подведём итоги: что получилось выполнить и что стоит скорректировать дальше.',
        default => $hello . ' Хотел уточнить, всё ли понятно и нужна ли моя помощь.',
    };
}

function ai_workflow_refresh_actions(array $admin): array
{
    $owner = ai_owner_for_admin($admin);
    [$where, $params] = scope_where_for_users($admin);
    $where = $where !== '' ? $where . ' AND ' : 'WHERE ';
    $sql = 'SELECT eu.*, (SELECT MAX(uts.completed_at) FROM user_test_sessions uts WHERE uts.end_user_id = eu.id AND uts.completed_at IS NOT NULL AND uts.is_preview = 0) latest_test_at, (SELECT MAX(cap.ends_on) FROM client_action_plans cap WHERE cap.end_user_id = eu.id AND cap.status = "active") plan_ends_on FROM end_users eu ' . str_replace(['WHERE reseller_id', 'WHERE manager_id', 'AND reseller_id', 'AND manager_id'], ['WHERE eu.reseller_id', 'WHERE eu.manager_id', 'AND eu.reseller_id', 'AND eu.manager_id'], $where) . ' eu.onboarding_completed_at IS NOT NULL AND eu.merged_into_user_id IS NULL ORDER BY eu.last_activity_at ASC LIMIT 500';
    try {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();
    } catch (Throwable) {
        return [];
    }
    $inactiveDays = ai_workflow_setting_days('ai.inactive_after_days', 14, 3, 365);
    $retestDays = ai_workflow_setting_days('ai.retest_after_days', 30, 7, 365);
    $insert = db()->prepare('INSERT INTO ai_action_suggestions (owner_type, owner_id, end_user_id, action_type, event_key, due_on, priority, title, reason_text, draft_text, preferred_channel) VALUES (:owner_type, :owner_id, :user_id, :action_type, :event_key, :due_on, :priority, :title, :reason, :draft_text, :channel) ON DUPLICATE KEY UPDATE due_on = VALUES(due_on), priority = VALUES(priority), title = VALUES(title), reason_text = VALUES(reason_text), draft_text = IF(status = "pending", VALUES(draft_text), draft_text), updated_at = NOW()');
    foreach ($users as $user) {
        $events = [];
        if (!empty($user['birth_date']) && date('m-d', strtotime((string)$user['birth_date'])) === date('m-d')) {
            $events[] = ['birthday', 100, 'Поздравить с днём рождения', 'Сегодня день рождения клиента', date('Y-m-d')];
        }
        if (!empty($user['latest_test_at'])) {
            $testDate = date('Y-m-d', strtotime((string)$user['latest_test_at']));
            if ($testDate >= date('Y-m-d', strtotime('-2 days'))) {
                $events[] = ['test_result', 90, 'Разобрать новый результат', 'Клиент недавно завершил чек-ап', date('Y-m-d')];
            }
            $retestDue = date('Y-m-d', strtotime($testDate . ' +' . $retestDays . ' days'));
            if ($retestDue <= date('Y-m-d')) {
                $events[] = ['retest', 70, 'Предложить повторный чек-ап', 'После прошлого прохождения прошло не менее ' . $retestDays . ' дней', date('Y-m-d')];
            }
        }
        if (!empty($user['last_activity_at']) && (string)$user['last_activity_at'] <= date('Y-m-d H:i:s', strtotime('-' . $inactiveDays . ' days'))) {
            $events[] = ['inactive', 50, 'Связаться после перерыва', 'Клиент не проявлял активности не менее ' . $inactiveDays . ' дней', date('Y-m-d')];
        }
        if (!empty($user['plan_ends_on']) && (string)$user['plan_ends_on'] <= date('Y-m-d', strtotime('+2 days'))) {
            $events[] = ['plan_ending', 80, 'Подвести итоги плана', 'Персональный план завершается', date('Y-m-d')];
        }
        foreach ($events as [$type, $priority, $title, $reason, $dueOn]) {
            $insert->execute([
                'owner_type' => $owner['owner_type'], 'owner_id' => $owner['owner_id'], 'user_id' => (int)$user['id'],
                'action_type' => $type, 'event_key' => $type . ':' . $user['id'] . ':' . ($type === 'birthday' ? date('Y') : date('Y-m')),
                'due_on' => $dueOn, 'priority' => $priority, 'title' => $title, 'reason' => $reason,
                'draft_text' => ai_workflow_action_message($type, $user, ['days' => $retestDays]),
                'channel' => in_array((string)$user['platform'], ['telegram','VK','OK','MAX','web'], true) ? $user['platform'] : 'any',
            ]);
        }
    }
    $list = db()->prepare('SELECT aas.*, eu.first_name, eu.last_name, eu.platform FROM ai_action_suggestions aas LEFT JOIN end_users eu ON eu.id = aas.end_user_id WHERE aas.owner_type = :owner_type AND aas.owner_id = :owner_id AND aas.status = "pending" AND aas.due_on <= CURRENT_DATE ORDER BY aas.priority DESC, aas.id DESC LIMIT 50');
    $list->execute($owner);
    return $list->fetchAll();
}

function ai_workflow_refresh_all_actions(): array
{
    $result = ['workspaces' => 0, 'suggestions' => 0, 'errors' => 0];
    $seen = [];
    $rows = db()->query('SELECT id, role, reseller_id, manager_id FROM admin_users WHERE is_active = 1 AND role IN ("reseller", "manager") ORDER BY id')->fetchAll();
    foreach ($rows as $admin) {
        $owner = ai_owner_for_admin($admin);
        $key = $owner['owner_type'] . ':' . $owner['owner_id'];
        if ($owner['owner_id'] <= 0 || isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        try {
            $items = ai_workflow_refresh_actions($admin);
            $result['workspaces']++;
            $result['suggestions'] += count($items);
        } catch (Throwable) {
            $result['errors']++;
        }
    }
    return $result;
}
