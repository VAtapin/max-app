<?php

function admin_table_per_page_options(): array
{
    return [10, 25, 50, 100];
}

function admin_table_request(array $sortMap = [], string $defaultSort = 'id', string $defaultDir = 'desc'): array
{
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = (int)($_GET['per_page'] ?? 25);
    if (!in_array($perPage, admin_table_per_page_options(), true)) {
        $perPage = 25;
    }

    $dir = strtolower((string)($_GET['dir'] ?? $defaultDir));
    if (!in_array($dir, ['asc', 'desc'], true)) {
        $dir = $defaultDir === 'asc' ? 'asc' : 'desc';
    }

    $sort = trim((string)($_GET['sort'] ?? $defaultSort));
    if ($sortMap && !isset($sortMap[$sort])) {
        $sort = isset($sortMap[$defaultSort]) ? $defaultSort : (string)array_key_first($sortMap);
    }

    return [
        'page' => $page,
        'per_page' => $perPage,
        'q' => trim((string)($_GET['q'] ?? '')),
        'sort' => $sort,
        'dir' => $dir,
    ];
}

function admin_table_url(array $overrides = [], ?string $basePath = null): string
{
    $params = array_merge($_GET, $overrides);
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        }
    }

    $path = $basePath ?: basename((string)($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
    $query = http_build_query($params);

    return $path . ($query !== '' ? '?' . $query : '');
}

function admin_table_strip_order_limit(string $sql): string
{
    $sql = rtrim(trim($sql), ';');
    $sql = preg_replace('/\s+ORDER BY\s+[^\r\n]*(?:\r?\n\s*LIMIT\s+\d+(?:\s+OFFSET\s+\d+)?)?\s*$/i', '', $sql) ?? $sql;
    $sql = preg_replace('/\s+LIMIT\s+\d+(?:\s+OFFSET\s+\d+)?\s*$/i', '', $sql) ?? $sql;

    return trim($sql);
}

function admin_table_search_where(array $columns, string $paramName): string
{
    $clauses = [];
    foreach (array_unique($columns) as $column) {
        $column = trim((string)$column, '` ');
        if ($column === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            continue;
        }
        $clauses[] = 'CAST(`' . $column . '` AS CHAR) LIKE :' . $paramName;
    }

    return $clauses ? ' WHERE ' . implode(' OR ', $clauses) : '';
}

function admin_table_paginated_rows(
    string $sql,
    array $params,
    array $sortMap,
    array $searchColumns,
    string $defaultSort = 'id',
    string $defaultDir = 'desc'
): array {
    $request = admin_table_request($sortMap, $defaultSort, $defaultDir);
    $baseSql = admin_table_strip_order_limit($sql);
    $queryParams = $params;
    $searchParam = '__admin_table_search';
    $where = '';

    if ($request['q'] !== '' && $searchColumns) {
        while (array_key_exists($searchParam, $queryParams)) {
            $searchParam = '_' . $searchParam;
        }
        $where = admin_table_search_where($searchColumns, $searchParam);
        if ($where !== '') {
            $queryParams[$searchParam] = '%' . $request['q'] . '%';
        }
    }

    $countStmt = db()->prepare('SELECT COUNT(*) FROM (' . $baseSql . ') admin_table_page' . $where);
    $countStmt->execute($queryParams);
    $total = (int)$countStmt->fetchColumn();

    $sort = isset($sortMap[$request['sort']]) ? $request['sort'] : $defaultSort;
    if (!isset($sortMap[$sort])) {
        $sort = (string)array_key_first($sortMap);
    }
    $dir = strtoupper($request['dir']);
    $pageCount = max(1, (int)ceil($total / $request['per_page']));
    $page = min($request['page'], $pageCount);
    $offset = ($page - 1) * $request['per_page'];
    $sortSql = $sortMap[$sort] ?? '`id`';
    $tieBreaker = $sort === 'id' || !isset($sortMap['id']) ? '' : ', `id` DESC';

    $rowsStmt = db()->prepare(
        'SELECT * FROM (' . $baseSql . ') admin_table_page'
        . $where
        . ' ORDER BY ' . $sortSql . ' ' . $dir . $tieBreaker
        . ' LIMIT ' . (int)$request['per_page'] . ' OFFSET ' . (int)$offset
    );
    $rowsStmt->execute($queryParams);

    $request['page'] = $page;
    $request['page_count'] = $pageCount;
    $request['total'] = $total;
    $request['sort'] = $sort;

    return [
        'rows' => $rowsStmt->fetchAll(),
        'meta' => $request,
    ];
}

function render_admin_table_tools(array $meta, array $filters = [], array $options = []): string
{
    if (!$meta) {
        return '';
    }

    $hidden = $options['hidden'] ?? [];
    $filterNames = array_map(static fn(array $filter): string => (string)($filter['name'] ?? ''), $filters);
    $skip = array_merge(['q', 'per_page', 'page'], $filterNames, array_keys($hidden), $options['skip'] ?? []);
    $resetUrl = (string)($options['reset_url'] ?? basename((string)($_SERVER['SCRIPT_NAME'] ?? 'index.php')));
    $searchPlaceholder = (string)($options['search_placeholder'] ?? 'Имя, email, код, статус');

    ob_start();
    ?>
    <form method="get" class="filters table-tools">
        <?php foreach ($hidden as $key => $value): ?>
            <input type="hidden" name="<?= h((string)$key) ?>" value="<?= h((string)$value) ?>">
        <?php endforeach; ?>
        <?php foreach ($_GET as $key => $value): ?>
            <?php if (!in_array((string)$key, $skip, true) && !is_array($value)): ?>
                <input type="hidden" name="<?= h((string)$key) ?>" value="<?= h((string)$value) ?>">
            <?php endif; ?>
        <?php endforeach; ?>
        <label class="table-search">
            <span>Поиск</span>
            <input type="search" name="q" value="<?= h((string)($meta['q'] ?? '')) ?>" placeholder="<?= h($searchPlaceholder) ?>">
        </label>
        <?php foreach ($filters as $filter): ?>
            <?php
            $name = (string)($filter['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $value = array_key_exists('value', $filter) ? (string)$filter['value'] : (string)($_GET[$name] ?? '');
            $choices = $filter['options'] ?? [];
            ?>
            <label>
                <span><?= h((string)($filter['label'] ?? $name)) ?></span>
                <select name="<?= h($name) ?>">
                    <?php foreach ($choices as $choiceValue => $choiceLabel): ?>
                        <option value="<?= h((string)$choiceValue) ?>" <?= $value === (string)$choiceValue ? 'selected' : '' ?>><?= h((string)$choiceLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endforeach; ?>
        <label>
            <span>На странице</span>
            <select name="per_page">
                <?php foreach (admin_table_per_page_options() as $perPage): ?>
                    <option value="<?= $perPage ?>" <?= (int)($meta['per_page'] ?? 25) === $perPage ? 'selected' : '' ?>><?= $perPage ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit">Показать</button>
        <a class="button secondary-button" href="<?= h($resetUrl) ?>">Сбросить</a>
    </form>
    <?php
    return trim(ob_get_clean());
}

function render_admin_sort_link(string $key, string $label, array $meta, array $sortMap, ?string $basePath = null): string
{
    if (!$meta || !isset($sortMap[$key])) {
        return h($label);
    }

    $active = ($meta['sort'] ?? 'id') === $key;
    $nextDir = $active && ($meta['dir'] ?? 'desc') === 'asc' ? 'desc' : 'asc';
    $symbol = $active ? (($meta['dir'] ?? 'desc') === 'asc' ? ' ↑' : ' ↓') : '';
    $href = admin_table_url(['sort' => $key, 'dir' => $nextDir, 'page' => 1], $basePath);

    return '<a class="sort-link' . ($active ? ' active' : '') . '" href="' . h($href) . '">' . h($label . $symbol) . '</a>';
}

function render_admin_pagination(array $meta, ?string $basePath = null): string
{
    if (!$meta || (int)($meta['page_count'] ?? 1) <= 1) {
        return '';
    }

    $page = (int)$meta['page'];
    $pageCount = (int)$meta['page_count'];
    $pages = array_values(array_unique(array_filter([
        1,
        $page - 1,
        $page,
        $page + 1,
        $pageCount,
    ], static fn(int $item): bool => $item >= 1 && $item <= $pageCount)));
    sort($pages);

    ob_start();
    ?>
    <nav class="pagination table-pagination" aria-label="Страницы">
        <?php if ($page > 1): ?>
            <a class="button secondary-button" href="<?= h(admin_table_url(['page' => $page - 1], $basePath)) ?>">Назад</a>
        <?php endif; ?>
        <?php $previous = 0; ?>
        <?php foreach ($pages as $number): ?>
            <?php if ($previous && $number > $previous + 1): ?>
                <span class="pagination-gap">...</span>
            <?php endif; ?>
            <?php if ($number === $page): ?>
                <span class="pagination-current"><?= $number ?></span>
            <?php else: ?>
                <a class="button secondary-button" href="<?= h(admin_table_url(['page' => $number], $basePath)) ?>"><?= $number ?></a>
            <?php endif; ?>
            <?php $previous = $number; ?>
        <?php endforeach; ?>
        <?php if ($page < $pageCount): ?>
            <a class="button secondary-button" href="<?= h(admin_table_url(['page' => $page + 1], $basePath)) ?>">Вперёд</a>
        <?php endif; ?>
    </nav>
    <?php
    return trim(ob_get_clean());
}
