<?php

function user_display_label(array $row): string
{
    $name = trim((string)($row['full_name'] ?? ''));
    if (is_technical_client_name($name)) {
        $name = '';
    }
    if ($name === '') {
        $name = trim((string)($row['username'] ?? ''));
    }
    if ($name === '') {
        $platform = trim((string)($row['platform'] ?? ''));
        $name = ($platform ? platform_label($platform) . ' клиент ' : 'Клиент ') . '#' . (int)$row['id'];
    }

    $platform = trim((string)($row['platform'] ?? ''));
    $platformUserId = trim((string)($row['platform_user_id'] ?? ''));
    return '#' . (int)$row['id'] . ' ' . $name . ($platform ? ' (' . platform_label($platform) . ' ' . $platformUserId . ')' : '');
}

function merge_user_base_select(): string
{
    return "SELECT eu.id, eu.first_name, eu.last_name,
                CONCAT_WS(' ', NULLIF(eu.first_name, ''), NULLIF(eu.last_name, '')) AS full_name,
                eu.username, eu.platform, eu.platform_user_id, eu.gender, eu.birth_date,
                eu.age_years, eu.city, eu.phone, eu.email, eu.reseller_id, eu.manager_id,
                (SELECT GROUP_CONCAT(CONCAT(pa.platform, ':', pa.platform_user_id) ORDER BY FIELD(pa.platform, 'telegram', 'VK', 'OK', 'MAX', 'web'), pa.id SEPARATOR ', ')
                 FROM platform_accounts pa
                 WHERE pa.end_user_id = eu.id) AS platform_accounts_summary
            FROM end_users eu";
}

function merge_user_row(int $userId, array $admin): ?array
{
    [$where, $params] = scoped_where_with_alias(scope_where_for_users($admin), 'eu');
    $where = $where
        ? $where . ' AND eu.id = :user_id AND eu.merged_into_user_id IS NULL'
        : 'WHERE eu.id = :user_id AND eu.merged_into_user_id IS NULL';
    $params['user_id'] = $userId;

    $stmt = db()->prepare(merge_user_base_select() . " $where LIMIT 1");
    $stmt->execute($params);
    $row = $stmt->fetch();

    return $row ?: null;
}

function merge_user_name_variants(array $row): array
{
    $first = trim((string)($row['first_name'] ?? ''));
    $last = trim((string)($row['last_name'] ?? ''));
    $full = trim((string)($row['full_name'] ?? ''));
    $username = trim((string)($row['username'] ?? ''));
    $variants = [
        $full,
        trim($last . ' ' . $first),
        $username,
        trim((string)($row['email'] ?? '')),
        trim((string)($row['phone'] ?? '')),
    ];

    return array_values(array_filter(array_unique(array_map('normalize_merge_text', $variants))));
}

function merge_user_similarity_score(array $target, array $candidate, string $query): array
{
    $score = 0.0;
    $queryRank = 0;
    $reasons = [];
    $queryNorm = normalize_merge_text($query);
    $targetNames = merge_user_name_variants($target);
    $candidateNames = merge_user_name_variants($candidate);

    if ($queryNorm !== '') {
        $haystack = implode(' ', $candidateNames) . ' ' . normalize_merge_text((string)($candidate['platform_user_id'] ?? ''))
            . ' ' . normalize_merge_text((string)($candidate['platform_accounts_summary'] ?? ''))
            . ' ' . normalize_merge_text((string)($candidate['city'] ?? ''));
        if (str_contains($haystack, $queryNorm)) {
            $score += 55;
            $queryRank = 2;
            $reasons[] = 'найдено по запросу';
        } elseif (strlen($queryNorm) >= 3) {
            foreach ($candidateNames as $candidateName) {
                similar_text($queryNorm, $candidateName, $percent);
                if ($percent >= 55) {
                    $score += min(40, $percent * 0.45);
                    $queryRank = 1;
                    $reasons[] = 'похожее написание запроса';
                    break;
                }
            }
        }
    }

    foreach ($targetNames as $targetName) {
        if ($targetName === '') {
            continue;
        }
        foreach ($candidateNames as $candidateName) {
            if ($candidateName === '') {
                continue;
            }
            if ($targetName === $candidateName) {
                $score += 90;
                $reasons[] = 'совпадает имя';
                break 2;
            }
            if (strlen($targetName) >= 4 && strlen($candidateName) >= 4
                && (str_contains($targetName, $candidateName) || str_contains($candidateName, $targetName))) {
                $score += 55;
                $reasons[] = 'очень похожее имя';
                break 2;
            }

            similar_text($targetName, $candidateName, $percent);
            if ($percent >= 72) {
                $score += min(50, $percent * 0.55);
                $reasons[] = 'похожее имя';
                break 2;
            }
        }
    }

    if (!empty($target['birth_date']) && !empty($candidate['birth_date']) && $target['birth_date'] === $candidate['birth_date']) {
        $score += 25;
        $reasons[] = 'совпадает дата рождения';
    }
    if (!empty($target['age_years']) && !empty($candidate['age_years']) && (int)$target['age_years'] === (int)$candidate['age_years']) {
        $score += 8;
        $reasons[] = 'совпадает возраст';
    }
    if (!empty($target['city']) && !empty($candidate['city'])
        && normalize_merge_text((string)$target['city']) === normalize_merge_text((string)$candidate['city'])) {
        $score += 18;
        $reasons[] = 'совпадает город';
    }
    if (!empty($target['gender']) && !empty($candidate['gender']) && $target['gender'] === $candidate['gender']) {
        $score += 6;
    }
    if (!empty($target['manager_id']) && !empty($candidate['manager_id']) && (int)$target['manager_id'] === (int)$candidate['manager_id']) {
        $score += 5;
    } elseif (!empty($target['reseller_id']) && !empty($candidate['reseller_id']) && (int)$target['reseller_id'] === (int)$candidate['reseller_id']) {
        $score += 4;
    }
    if (!empty($target['platform']) && !empty($candidate['platform']) && $target['platform'] !== $candidate['platform']) {
        $score += 6;
        $reasons[] = 'другая платформа';
    }

    return [
        'score' => $score,
        'query_rank' => $queryRank,
        'reason' => implode(', ', array_values(array_unique($reasons))) ?: 'возможное совпадение',
    ];
}

function merge_user_search_results(int $targetUserId, string $query, array $admin): array
{
    $target = merge_user_row($targetUserId, $admin);
    if (!$target) {
        throw new RuntimeException('Пользователь недоступен.');
    }

    [$where, $params] = scoped_where_with_alias(scope_where_for_users($admin), 'eu');
    $where = $where
        ? $where . ' AND eu.id <> :target_user_id AND eu.merged_into_user_id IS NULL'
        : 'WHERE eu.id <> :target_user_id AND eu.merged_into_user_id IS NULL';
    $params['target_user_id'] = $targetUserId;

    $stmt = db()->prepare(merge_user_base_select() . " $where ORDER BY eu.id DESC LIMIT 10000");
    $stmt->execute($params);

    $queryNorm = normalize_merge_text($query);
    $minScore = $queryNorm !== '' ? 20 : 45;
    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $match = merge_user_similarity_score($target, $row, $query);
        if ($match['score'] < $minScore) {
            continue;
        }
        $items[] = [
            'id' => (int)$row['id'],
            'label' => user_display_label($row),
            'meta' => merge_user_meta_label($row),
            'reason' => $match['reason'],
            'score' => round($match['score'], 1),
            'query_rank' => $match['query_rank'],
        ];
    }

    usort($items, static fn(array $a, array $b): int => ($b['query_rank'] <=> $a['query_rank'])
        ?: ($b['score'] <=> $a['score'])
        ?: ($b['id'] <=> $a['id']));
    return array_slice($items, 0, 12);
}

function merge_user_meta_label(array $row): string
{
    $parts = [];
    if (!empty($row['city'])) {
        $parts[] = (string)$row['city'];
    }
    if (!empty($row['birth_date'])) {
        $parts[] = 'д.р. ' . $row['birth_date'];
    } elseif (!empty($row['age_years'])) {
        $parts[] = (int)$row['age_years'] . ' лет';
    }
    if (!empty($row['platform_accounts_summary'])) {
        $parts[] = (string)$row['platform_accounts_summary'];
    }

    return implode(' · ', $parts);
}

function merge_end_users(int $targetUserId, int $sourceUserId, array $admin): void
{
    if ($targetUserId <= 0 || $sourceUserId <= 0 || $targetUserId === $sourceUserId) {
        throw new RuntimeException('Выберите двух разных пользователей.');
    }
    if (!scoped_end_user_exists($targetUserId, $admin) || !scoped_end_user_exists($sourceUserId, $admin)) {
        throw new RuntimeException('Пользователь недоступен.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $targetStmt = $pdo->prepare('SELECT * FROM end_users WHERE id = :id AND merged_into_user_id IS NULL FOR UPDATE');
        $targetStmt->execute(['id' => $targetUserId]);
        $target = $targetStmt->fetch();

        $sourceStmt = $pdo->prepare('SELECT * FROM end_users WHERE id = :id AND merged_into_user_id IS NULL FOR UPDATE');
        $sourceStmt->execute(['id' => $sourceUserId]);
        $source = $sourceStmt->fetch();

        if (!$target || !$source) {
            throw new RuntimeException('Один из пользователей уже объединён.');
        }

        $mergeFields = [
            'username',
            'first_name',
            'last_name',
            'gender',
            'birth_date',
            'age_years',
            'city',
            'phone',
            'email',
            'referral_code_used',
            'onboarding_completed_at',
            'referral_registered_at',
        ];
        $assignments = [];
        $mergeParams = ['target_id' => $targetUserId];
        foreach ($mergeFields as $field) {
            if (($target[$field] ?? null) === null || trim((string)$target[$field]) === '') {
                if (($source[$field] ?? null) !== null && trim((string)$source[$field]) !== '') {
                    $assignments[] = "$field = :$field";
                    $mergeParams[$field] = $source[$field];
                }
            }
        }
        if (empty($target['reseller_id']) && empty($target['manager_id'])
            && (!empty($source['reseller_id']) || !empty($source['manager_id']))) {
            $assignments[] = 'reseller_id = :reseller_id';
            $assignments[] = 'manager_id = :manager_id';
            $mergeParams['reseller_id'] = $source['reseller_id'];
            $mergeParams['manager_id'] = $source['manager_id'];
        }
        if ($assignments) {
            $mergeData = $pdo->prepare('UPDATE end_users SET ' . implode(', ', $assignments) . ' WHERE id = :target_id');
            $mergeData->execute($mergeParams);
        }

        $moveResellerSource = $pdo->prepare('UPDATE resellers SET source_end_user_id = :target_id WHERE source_end_user_id = :source_id');
        $moveResellerSource->execute(['target_id' => $targetUserId, 'source_id' => $sourceUserId]);
        $moveManagerSource = $pdo->prepare('UPDATE managers SET source_end_user_id = :target_id WHERE source_end_user_id = :source_id');
        $moveManagerSource->execute(['target_id' => $targetUserId, 'source_id' => $sourceUserId]);

        $dedupeAutomation = $pdo->prepare(
            'DELETE source_log
             FROM automation_logs source_log
             INNER JOIN automation_logs target_log
               ON target_log.end_user_id = :target_id
              AND target_log.automation_type = source_log.automation_type
              AND target_log.context_key = source_log.context_key
              AND target_log.platform = source_log.platform
             WHERE source_log.end_user_id = :source_id'
        );
        $dedupeAutomation->execute(['target_id' => $targetUserId, 'source_id' => $sourceUserId]);

        $updates = [
            'platform_accounts' => 'end_user_id',
            'leads' => 'end_user_id',
            'user_test_sessions' => 'end_user_id',
            'recommendations' => 'end_user_id',
            'broadcast_logs' => 'end_user_id',
            'user_consents' => 'end_user_id',
            'client_stage_history' => 'end_user_id',
            'user_notifications' => 'end_user_id',
            'automation_logs' => 'end_user_id',
            'consultant_notifications' => 'end_user_id',
        ];
        foreach ($updates as $table => $column) {
            $stmt = $pdo->prepare("UPDATE $table SET $column = :target_id WHERE $column = :source_id");
            $stmt->execute(['target_id' => $targetUserId, 'source_id' => $sourceUserId]);
        }

        $mark = $pdo->prepare(
            'UPDATE end_users
             SET merged_into_user_id = :target_id, status = "unsubscribed"
             WHERE id = :source_id'
        );
        $mark->execute(['target_id' => $targetUserId, 'source_id' => $sourceUserId]);

        log_activity('admin', (int)$admin['id'], 'merge_end_users', 'end_users', $targetUserId, [
            'source_user_id' => $sourceUserId,
            'target_user_id' => $targetUserId,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
