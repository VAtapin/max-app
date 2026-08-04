<?php

function subscription_billing_basis_labels(): array
{
    return [
        'branch' => 'Вся ветка',
        'direct' => 'Только прямой уровень',
    ];
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

function subscription_limit_text(?int $limit): string
{
    return $limit === null ? 'без лимита' : (string)$limit;
}

function subscription_plan_row(?int $planId, bool $activeOnly = false): ?array
{
    if (!$planId) {
        return null;
    }

    $sql = 'SELECT * FROM subscription_plans WHERE id = :id';
    if ($activeOnly) {
        $sql .= ' AND is_active = 1';
    }
    $sql .= ' LIMIT 1';

    try {
        $stmt = db()->prepare($sql);
        $stmt->execute(['id' => $planId]);
        $row = $stmt->fetch();
    } catch (Throwable $e) {
        return null;
    }

    return $row ?: null;
}

function subscription_plan_options(bool $activeOnly = true): array
{
    try {
        $where = $activeOnly ? 'WHERE is_active = 1' : '';
        $stmt = db()->query("SELECT id, title AS label FROM subscription_plans $where ORDER BY sort_order ASC, title ASC, id ASC");
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function subscription_plan_amount(array $plan): ?float
{
    if (($plan['fixed_monthly_price'] ?? null) !== null && $plan['fixed_monthly_price'] !== '') {
        return round((float)$plan['fixed_monthly_price'], 2);
    }

    $basis = (string)($plan['billing_basis'] ?? 'branch');
    $leaderLimit = $basis === 'direct'
        ? ($plan['direct_leader_limit'] ?? null)
        : ($plan['branch_leader_limit'] ?? null);
    $consultantLimit = $basis === 'direct'
        ? ($plan['direct_consultant_limit'] ?? null)
        : ($plan['branch_consultant_limit'] ?? null);

    $amount = 0.0;
    $hasAmount = false;
    if ($leaderLimit !== null && $leaderLimit !== '' && ($plan['price_per_leader'] ?? null) !== null && $plan['price_per_leader'] !== '') {
        $amount += (int)$leaderLimit * (float)$plan['price_per_leader'];
        $hasAmount = true;
    }
    if ($consultantLimit !== null && $consultantLimit !== '' && ($plan['price_per_consultant'] ?? null) !== null && $plan['price_per_consultant'] !== '') {
        $amount += (int)$consultantLimit * (float)$plan['price_per_consultant'];
        $hasAmount = true;
    }

    return $hasAmount ? round($amount, 2) : null;
}

function subscription_plan_apply_to_reseller_payload(array $payload): array
{
    $planId = isset($payload['subscription_plan_id']) && $payload['subscription_plan_id'] !== ''
        ? (int)$payload['subscription_plan_id']
        : null;
    $plan = subscription_plan_row($planId, true);

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

function subscription_plan_validate_reseller_payload(array $payload): array
{
    $planId = isset($payload['subscription_plan_id']) && $payload['subscription_plan_id'] !== ''
        ? (int)$payload['subscription_plan_id']
        : null;
    if (!$planId) {
        return [];
    }

    return subscription_plan_row($planId, true)
        ? []
        : ['Выбранная подписка не найдена или отключена.'];
}
