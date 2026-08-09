<?php

require_once __DIR__ . '/team_tree.php';

function subscription_billing_basis_labels(): array
{
    return [
        'branch' => 'Вся ветка',
        'direct' => 'Только 1-й уровень',
    ];
}

function subscription_billing_mode_labels(): array
{
    return [
        'prepaid' => 'Предоплата за места',
        'actual' => 'По факту активных',
    ];
}

function subscription_plan_billing_mode(array $plan): string
{
    $mode = (string)($plan['billing_mode'] ?? 'prepaid');

    return isset(subscription_billing_mode_labels()[$mode]) ? $mode : 'prepaid';
}

function subscription_plan_limit_fields(): array
{
    return [
        'direct_leader_limit',
        'branch_leader_limit',
        'direct_consultant_limit',
        'branch_consultant_limit',
        'per_child_consultant_limit',
    ];
}

function subscription_plan_price_fields(): array
{
    return [
        'price_per_leader',
        'price_per_consultant',
        'fixed_monthly_price',
    ];
}

function subscription_plan_slug(string $title): string
{
    $slug = mb_strtolower(trim($title), 'UTF-8');
    $slug = preg_replace('/\s+/', '-', $slug) ?? '';
    $slug = preg_replace('/[^a-z0-9а-яё_-]/u', '', $slug) ?? '';
    $slug = strtr($slug, [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch',
        'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ]);
    $slug = preg_replace('/[-_]{2,}/', '-', $slug) ?? '';
    $slug = trim($slug, '-_');

    return $slug !== '' ? $slug : 'plan-' . date('YmdHis');
}

function subscription_parse_money(mixed $value): ?float
{
    $value = str_replace(',', '.', trim((string)$value));
    if ($value === '') {
        return null;
    }

    return is_numeric($value) ? round((float)$value, 2) : null;
}

function subscription_parse_limit(mixed $value): ?int
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    return ctype_digit($value) ? (int)$value : -1;
}

function subscription_money_text(?float $value): string
{
    return $value === null ? '—' : number_format($value, 2, ',', ' ') . ' руб.';
}

function subscription_money_value(mixed $value): float
{
    if ($value === null || $value === '') {
        return 0.0;
    }

    return round((float)$value, 2);
}

function subscription_limit_text(?int $limit): string
{
    return $limit === null ? 'без лимита' : (string)$limit;
}

function subscription_plan_global_owner_type(): string
{
    return 'superadmin';
}

function subscription_plan_global_owner_id(): int
{
    return 0;
}

function subscription_plan_owner_for_admin(array $admin): array
{
    if (($admin['role'] ?? '') === 'reseller' && !empty($admin['reseller_id'])) {
        return ['owner_type' => 'reseller', 'owner_id' => (int)$admin['reseller_id']];
    }

    return ['owner_type' => subscription_plan_global_owner_type(), 'owner_id' => subscription_plan_global_owner_id()];
}

function subscription_plan_visibility_sql(?array $admin, string $alias = 'subscription_plans', bool $editableOnly = false): array
{
    $prefix = $alias !== '' ? $alias . '.' : '';
    if (!$admin || ($admin['role'] ?? '') === 'superadmin') {
        return ['', []];
    }

    if (($admin['role'] ?? '') !== 'reseller' || empty($admin['reseller_id'])) {
        return [' AND 1 = 0', []];
    }

    $resellerId = (int)$admin['reseller_id'];
    if ($editableOnly) {
        return [
            " AND {$prefix}owner_type = :plan_owner_type AND {$prefix}owner_id = :plan_owner_id",
            ['plan_owner_type' => 'reseller', 'plan_owner_id' => $resellerId],
        ];
    }

    $ancestorIds = team_reseller_ancestor_ids($resellerId, true);
    [$ownerSql, $ownerParams] = team_sql_in_condition($prefix . 'owner_id', $ancestorIds, 'plan_owner');

    return [
        " AND (({$prefix}owner_type = :plan_global_owner_type AND {$prefix}owner_id = :plan_global_owner_id)"
        . " OR ({$prefix}owner_type = :plan_reseller_owner_type AND {$ownerSql}))",
        [
            'plan_global_owner_type' => subscription_plan_global_owner_type(),
            'plan_global_owner_id' => subscription_plan_global_owner_id(),
            'plan_reseller_owner_type' => 'reseller',
        ] + $ownerParams,
    ];
}

function subscription_plan_can_edit(array $plan, array $admin): bool
{
    if (($admin['role'] ?? '') === 'superadmin') {
        return true;
    }

    return ($admin['role'] ?? '') === 'reseller'
        && (string)($plan['owner_type'] ?? '') === 'reseller'
        && !empty($admin['reseller_id'])
        && (int)($plan['owner_id'] ?? 0) === (int)$admin['reseller_id'];
}

function subscription_plan_owner_label(array $plan): string
{
    $ownerType = (string)($plan['owner_type'] ?? subscription_plan_global_owner_type());
    $ownerId = (int)($plan['owner_id'] ?? 0);
    if ($ownerType !== 'reseller' || $ownerId <= 0) {
        return 'Глобальная';
    }

    $reseller = team_reseller_row($ownerId);
    return 'Лидер: ' . (string)($reseller['name'] ?? ('#' . $ownerId));
}

function subscription_charge_limit(array $plan, string $field): int
{
    if (!array_key_exists($field, $plan) || $plan[$field] === null || $plan[$field] === '') {
        return 0;
    }

    return max(0, (int)$plan[$field]);
}

function subscription_plan_row(?int $planId, bool $activeOnly = false, ?array $admin = null, bool $editableOnly = false): ?array
{
    if (!$planId) {
        return null;
    }

    $sql = 'SELECT * FROM subscription_plans WHERE id = :id';
    $params = ['id' => $planId];
    if ($activeOnly) {
        $sql .= ' AND is_active = 1';
    }
    [$scopeSql, $scopeParams] = subscription_plan_visibility_sql($admin, 'subscription_plans', $editableOnly);
    $sql .= $scopeSql;
    $params += $scopeParams;
    $sql .= ' LIMIT 1';

    try {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
    } catch (Throwable $e) {
        return null;
    }

    return $row ?: null;
}

function subscription_plan_options(bool $activeOnly = true, ?array $admin = null): array
{
    try {
        $sql = 'SELECT id, title AS label, owner_type, owner_id FROM subscription_plans WHERE 1 = 1';
        $params = [];
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        [$scopeSql, $scopeParams] = subscription_plan_visibility_sql($admin, 'subscription_plans', false);
        $sql .= $scopeSql . " ORDER BY owner_type = 'superadmin' DESC, sort_order ASC, title ASC, id ASC";
        $params += $scopeParams;

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function subscription_plan_amount(array $plan): ?float
{
    $base = subscription_money_value($plan['fixed_monthly_price'] ?? null);

    return $base > 0 ? $base : null;
}

function subscription_plan_for_reseller(int $resellerId, bool $activeOnly = false): ?array
{
    if ($resellerId <= 0) {
        return null;
    }

    try {
        $stmt = db()->prepare('SELECT subscription_plan_id FROM resellers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $resellerId]);
        $planId = $stmt->fetchColumn();
    } catch (Throwable) {
        return null;
    }

    return $planId ? subscription_plan_row((int)$planId, $activeOnly) : null;
}

function subscription_plan_billing_usage(int $resellerId, array $plan): array
{
    $summary = team_branch_summary($resellerId);
    $basis = (string)($plan['billing_basis'] ?? 'branch');
    if (!isset(subscription_billing_basis_labels()[$basis])) {
        $basis = 'branch';
    }
    $mode = subscription_plan_billing_mode($plan);
    $activeLeaders = $basis === 'direct'
        ? (int)$summary['direct_leaders']
        : (int)$summary['branch_leaders'];
    $activeConsultants = $basis === 'direct'
        ? (int)$summary['direct_consultants']
        : (int)$summary['branch_consultants'];

    $leaders = $activeLeaders;
    $consultants = $activeConsultants;
    if ($mode === 'prepaid') {
        $leaders = subscription_charge_limit($plan, $basis === 'direct' ? 'direct_leader_limit' : 'branch_leader_limit');
        $consultants = subscription_charge_limit($plan, $basis === 'direct' ? 'direct_consultant_limit' : 'branch_consultant_limit');
    }

    return [
        'basis' => $basis,
        'basis_label' => subscription_billing_basis_labels()[$basis],
        'billing_mode' => $mode,
        'mode_label' => subscription_billing_mode_labels()[$mode],
        'leaders' => $leaders,
        'consultants' => $consultants,
        'active_leaders' => $activeLeaders,
        'active_consultants' => $activeConsultants,
        'summary' => $summary,
    ];
}

function subscription_plan_usage_amount(int $resellerId, ?array $plan = null): ?array
{
    $plan = $plan ?: subscription_plan_for_reseller($resellerId, false);
    if (!$plan) {
        return null;
    }

    $usage = subscription_plan_billing_usage($resellerId, $plan);
    $leaderPrice = subscription_money_value($plan['price_per_leader'] ?? null);
    $consultantPrice = subscription_money_value($plan['price_per_consultant'] ?? null);
    $baseAmount = subscription_money_value($plan['fixed_monthly_price'] ?? null);
    $leaderAmount = round((int)$usage['leaders'] * $leaderPrice, 2);
    $consultantAmount = round((int)$usage['consultants'] * $consultantPrice, 2);

    return [
        'plan' => $plan,
        'basis' => $usage['basis'],
        'basis_label' => $usage['basis_label'],
        'billing_mode' => $usage['billing_mode'],
        'mode_label' => $usage['mode_label'],
        'leaders' => (int)$usage['leaders'],
        'consultants' => (int)$usage['consultants'],
        'active_leaders' => (int)$usage['active_leaders'],
        'active_consultants' => (int)$usage['active_consultants'],
        'summary' => $usage['summary'],
        'price_per_leader' => $leaderPrice,
        'price_per_consultant' => $consultantPrice,
        'base_amount' => $baseAmount,
        'leader_amount' => $leaderAmount,
        'consultant_amount' => $consultantAmount,
        'amount_due' => round($baseAmount + $leaderAmount + $consultantAmount, 2),
    ];
}

function subscription_plan_formula_text(array $plan): string
{
    $parts = [];
    $base = subscription_money_value($plan['fixed_monthly_price'] ?? null);
    $leaderPrice = subscription_money_value($plan['price_per_leader'] ?? null);
    $consultantPrice = subscription_money_value($plan['price_per_consultant'] ?? null);
    $mode = subscription_plan_billing_mode($plan);
    $leaderLabel = $mode === 'prepaid' ? 'места лидеров' : 'активные лидеры';
    $consultantLabel = $mode === 'prepaid' ? 'места консультантов' : 'активные консультанты';

    if ($base > 0) {
        $parts[] = 'база ' . subscription_money_text($base);
    }
    if ($leaderPrice > 0) {
        $parts[] = $leaderLabel . ' x ' . subscription_money_text($leaderPrice);
    }
    if ($consultantPrice > 0) {
        $parts[] = $consultantLabel . ' x ' . subscription_money_text($consultantPrice);
    }

    return $parts ? implode(' + ', $parts) : 'стоимость не задана';
}

function subscription_plan_apply_to_reseller_payload(array $payload, ?array $admin = null): array
{
    $planId = isset($payload['subscription_plan_id']) && $payload['subscription_plan_id'] !== ''
        ? (int)$payload['subscription_plan_id']
        : null;
    $plan = subscription_plan_row($planId, true, $admin);

    if (!$plan) {
        foreach ([
            'manager_limit',
            'direct_leader_limit',
            'branch_leader_limit',
            'direct_manager_limit',
            'branch_manager_limit',
            'per_child_manager_limit',
            'price_per_leader',
            'price_per_consultant',
        ] as $field) {
            $payload[$field] = null;
        }
        $payload['subscription_plan_id'] = null;
        return $payload;
    }

    $payload['subscription_plan_id'] = (int)$plan['id'];
    $payload['direct_leader_limit'] = $plan['direct_leader_limit'] !== null ? (int)$plan['direct_leader_limit'] : null;
    $payload['branch_leader_limit'] = $plan['branch_leader_limit'] !== null ? (int)$plan['branch_leader_limit'] : null;
    $payload['direct_manager_limit'] = $plan['direct_consultant_limit'] !== null ? (int)$plan['direct_consultant_limit'] : null;
    $payload['branch_manager_limit'] = $plan['branch_consultant_limit'] !== null ? (int)$plan['branch_consultant_limit'] : null;
    $payload['manager_limit'] = $payload['direct_manager_limit'];
    $payload['per_child_manager_limit'] = $plan['per_child_consultant_limit'] !== null ? (int)$plan['per_child_consultant_limit'] : null;
    $payload['price_per_leader'] = $plan['price_per_leader'] !== null ? (float)$plan['price_per_leader'] : null;
    $payload['price_per_consultant'] = $plan['price_per_consultant'] !== null ? (float)$plan['price_per_consultant'] : null;

    return $payload;
}

function subscription_plan_validate_reseller_payload(array $payload, ?array $admin = null): array
{
    $planId = isset($payload['subscription_plan_id']) && $payload['subscription_plan_id'] !== ''
        ? (int)$payload['subscription_plan_id']
        : null;
    if (!$planId) {
        return [];
    }

    return subscription_plan_row($planId, true, $admin)
        ? []
        : ['Выбранная подписка не найдена или отключена.'];
}
