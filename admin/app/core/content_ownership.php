<?php

require_once __DIR__ . '/team_tree.php';

function owned_content_config(string $moduleKey): ?array
{
    $configs = [
        'categories' => [
            'table' => 'product_categories',
            'source_column' => 'source_category_id',
            'deleted_column' => 'is_deleted',
            'inactive_column' => 'is_active',
            'inactive_value' => 0,
        ],
        'products' => [
            'table' => 'products',
            'source_column' => 'source_product_id',
            'deleted_column' => 'is_deleted',
            'inactive_column' => 'is_active',
            'inactive_value' => 0,
        ],
        'tests' => [
            'table' => 'tests',
            'source_column' => 'source_test_id',
            'deleted_column' => 'is_deleted',
            'inactive_column' => 'is_active',
            'inactive_value' => 0,
        ],
        'content' => [
            'table' => 'content_posts',
            'source_column' => 'source_content_post_id',
            'deleted_column' => 'is_deleted',
            'inactive_column' => 'status',
            'inactive_value' => 'hidden',
        ],
        'broadcasts' => [
            'table' => 'broadcasts',
            'source_column' => 'source_broadcast_id',
            'deleted_column' => 'is_deleted',
            'inactive_column' => 'status',
            'inactive_value' => 'cancelled',
        ],
    ];

    return $configs[$moduleKey] ?? null;
}

function owned_content_admin_owner(array $admin): ?array
{
    if ($admin['role'] === 'reseller' && !empty($admin['reseller_id'])) {
        return ['owner_type' => 'reseller', 'owner_id' => (int)$admin['reseller_id']];
    }

    if ($admin['role'] === 'manager' && !empty($admin['manager_id'])) {
        return ['owner_type' => 'manager', 'owner_id' => (int)$admin['manager_id']];
    }

    return null;
}

function owned_content_owner_clause(string $alias, ?string $ownerType, ?int $ownerId, string $paramPrefix): array
{
    $prefix = $alias !== '' ? $alias . '.' : '';
    if ($ownerType === null) {
        return [$prefix . 'owner_type IS NULL', []];
    }

    return [
        $prefix . 'owner_type = :' . $paramPrefix . '_owner_type AND ' . $prefix . 'owner_id = :' . $paramPrefix . '_owner_id',
        [
            $paramPrefix . '_owner_type' => $ownerType,
            $paramPrefix . '_owner_id' => $ownerId,
        ],
    ];
}

function owned_content_owner_list_clause(string $alias, array $owners, string $paramPrefix): array
{
    $parts = [];
    $params = [];
    foreach ($owners as $index => $owner) {
        [$sql, $ownerParams] = owned_content_owner_clause(
            $alias,
            $owner['owner_type'],
            $owner['owner_id'],
            $paramPrefix . '_' . $index
        );
        $parts[] = '(' . $sql . ')';
        $params += $ownerParams;
    }

    return ['(' . implode(' OR ', $parts) . ')', $params];
}

function owned_content_owner_priority_condition(array $config, string $alias, array $owners, string $paramPrefix): array
{
    if (!$owners) {
        return ['', []];
    }

    $sqlAlias = $alias !== '' ? $alias : $config['table'];
    $prefix = $sqlAlias . '.';
    $sourceColumn = $config['source_column'];
    $deletedColumn = $config['deleted_column'];
    $parts = [];
    $params = [];

    foreach ($owners as $index => $owner) {
        [$ownerSql, $ownerParams] = owned_content_owner_clause($sqlAlias, $owner['owner_type'], $owner['owner_id'], $paramPrefix . '_owner_' . $index);
        $params += $ownerParams;
        $laterOwners = array_slice($owners, $index + 1);
        $notExists = '';
        if ($laterOwners) {
            [$laterSql, $laterParams] = owned_content_owner_list_clause('cow_later', $laterOwners, $paramPrefix . '_later_' . $index);
            $params += $laterParams;
            $notExists = ' AND NOT EXISTS (
                SELECT 1
                FROM ' . $config['table'] . ' cow_later
                WHERE cow_later.id <> ' . $prefix . 'id
                  AND COALESCE(cow_later.' . $sourceColumn . ', cow_later.id) = COALESCE(' . $prefix . $sourceColumn . ', ' . $prefix . 'id)
                  AND ' . $laterSql . '
            )';
        }

        $parts[] = '(' . $ownerSql . ' AND ' . $prefix . $deletedColumn . ' = 0' . $notExists . ')';
    }

    return ['(' . implode(' OR ', $parts) . ')', $params];
}

function owned_content_admin_visible_owners(array $admin): array
{
    if ($admin['role'] === 'superadmin') {
        return [];
    }

    return team_owner_chain_for_admin($admin);
}

function owned_content_admin_override_owners(array $admin): array
{
    return array_values(array_filter(
        owned_content_admin_visible_owners($admin),
        static fn(array $owner): bool => $owner['owner_type'] !== null
    ));
}

function owned_content_scope_condition(string $moduleKey, array $admin, string $alias = ''): array
{
    $config = owned_content_config($moduleKey);
    if (!$config || $admin['role'] === 'superadmin') {
        return ['', []];
    }

    [$visibleSql, $params] = owned_content_owner_priority_condition(
        $config,
        $alias !== '' ? $alias : $config['table'],
        owned_content_admin_visible_owners($admin),
        'visible'
    );

    return ['WHERE ' . $visibleSql, $params];
}

function owned_content_client_owners(array $user): array
{
    return team_owner_chain_for_user($user);
}

function owned_content_client_scope_condition(string $moduleKey, array $user, string $alias = ''): array
{
    $config = owned_content_config($moduleKey);
    if (!$config) {
        [$sql, $params] = owned_content_owner_list_clause($alias, owned_content_client_owners($user), 'client_visible');
        return [$sql, $params];
    }

    return owned_content_owner_priority_condition(
        $config,
        $alias !== '' ? $alias : $config['table'],
        owned_content_client_owners($user),
        'client_visible'
    );
}

function owned_content_row(string $moduleKey, int $id): ?array
{
    $config = owned_content_config($moduleKey);
    if (!$config || $id <= 0) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM ' . $config['table'] . ' WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function owned_content_row_matches_owner(array $row, array $owner): bool
{
    return (string)($row['owner_type'] ?? '') === $owner['owner_type']
        && (int)($row['owner_id'] ?? 0) === (int)$owner['owner_id'];
}

function owned_content_unique_slug(string $table, string $sourceSlug, string $ownerType, int $ownerId): string
{
    $base = trim($sourceSlug) !== '' ? trim($sourceSlug) : 'item';
    $base = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $base) ?: 'item';
    $base = trim($base, '-_') ?: 'item';
    $base = substr($base . '-' . $ownerType . '-' . $ownerId, 0, 170);
    $slug = $base;
    $suffix = 2;
    $stmt = db()->prepare("SELECT COUNT(*) FROM {$table} WHERE slug = :slug");

    while (true) {
        $stmt->execute(['slug' => $slug]);
        if ((int)$stmt->fetchColumn() === 0) {
            return $slug;
        }
        $slug = substr($base, 0, 180) . '-' . $suffix;
        $suffix++;
    }
}

function owned_content_root_id(string $moduleKey, int $id): int
{
    $row = owned_content_row($moduleKey, $id);
    if (!$row) {
        return $id;
    }

    $config = owned_content_config($moduleKey);
    if (!$config) {
        return $id;
    }

    $sourceColumn = $config['source_column'];
    return !empty($row[$sourceColumn]) ? (int)$row[$sourceColumn] : (int)$row['id'];
}

function owned_content_source_root(array $source, string $sourceColumn): int
{
    return !empty($source[$sourceColumn]) ? (int)$source[$sourceColumn] : (int)$source['id'];
}

function owned_content_existing_clone_id(string $moduleKey, int $sourceId, string $ownerType, int $ownerId, bool $includeDeleted = true): ?int
{
    $config = owned_content_config($moduleKey);
    if (!$config) {
        return null;
    }
    $sourceId = owned_content_root_id($moduleKey, $sourceId);

    $stmt = db()->prepare(
        'SELECT id
         FROM ' . $config['table'] . '
         WHERE owner_type = :owner_type
           AND owner_id = :owner_id
           AND ' . $config['source_column'] . ' = :source_id' .
           ($includeDeleted ? '' : ' AND ' . $config['deleted_column'] . ' = 0') . '
         LIMIT 1'
    );
    $stmt->execute([
        'owner_type' => $ownerType,
        'owner_id' => $ownerId,
        'source_id' => $sourceId,
    ]);
    $id = $stmt->fetchColumn();

    return $id !== false ? (int)$id : null;
}

function owned_content_mark_deleted(string $moduleKey, int $id): void
{
    $config = owned_content_config($moduleKey);
    if (!$config) {
        return;
    }

    $stmt = db()->prepare(
        'UPDATE ' . $config['table'] . '
         SET ' . $config['deleted_column'] . ' = 1
         WHERE id = :id'
    );
    $stmt->execute(['id' => $id]);
}

function owned_content_map_category_for_owner(?int $categoryId, string $ownerType, int $ownerId): ?int
{
    if (!$categoryId) {
        return null;
    }

    $cloneId = owned_content_existing_clone_id('categories', owned_content_root_id('categories', $categoryId), $ownerType, $ownerId, false);
    return $cloneId ?: $categoryId;
}

function owned_content_map_product_for_owner(?int $productId, string $ownerType, int $ownerId): ?int
{
    if (!$productId) {
        return null;
    }

    $cloneId = owned_content_existing_clone_id('products', owned_content_root_id('products', $productId), $ownerType, $ownerId, false);
    return $cloneId ?: $productId;
}

function owned_content_clone_category(array $source, string $ownerType, int $ownerId, bool $inactive): int
{
    $stmt = db()->prepare(
        'INSERT INTO product_categories
            (title, slug, description, owner_type, owner_id, source_category_id, is_deleted, sort_order, is_active)
         VALUES
            (:title, :slug, :description, :owner_type, :owner_id, :source_category_id, :is_deleted, :sort_order, :is_active)'
    );
    $stmt->execute([
        'title' => $source['title'],
        'slug' => owned_content_unique_slug('product_categories', (string)($source['slug'] ?? ''), $ownerType, $ownerId),
        'description' => $source['description'],
        'owner_type' => $ownerType,
        'owner_id' => $ownerId,
        'source_category_id' => owned_content_source_root($source, 'source_category_id'),
        'is_deleted' => $inactive ? 1 : 0,
        'sort_order' => (int)$source['sort_order'],
        'is_active' => (int)$source['is_active'],
    ]);

    return (int)db()->lastInsertId();
}

function owned_content_clone_product(array $source, string $ownerType, int $ownerId, bool $inactive): int
{
    $stmt = db()->prepare(
        'INSERT INTO products
            (category_id, owner_type, owner_id, source_product_id, is_deleted, title, slug, short_description,
             full_description, composition, usage_text, warning_text, contraindications, image_path,
             document_path, video_url, price, purchase_url, is_active, sort_order)
         VALUES
            (:category_id, :owner_type, :owner_id, :source_product_id, :is_deleted, :title, :slug, :short_description,
             :full_description, :composition, :usage_text, :warning_text, :contraindications, :image_path,
             :document_path, :video_url, :price, :purchase_url, :is_active, :sort_order)'
    );
    $stmt->execute([
        'category_id' => owned_content_map_category_for_owner($source['category_id'] !== null ? (int)$source['category_id'] : null, $ownerType, $ownerId),
        'owner_type' => $ownerType,
        'owner_id' => $ownerId,
        'source_product_id' => owned_content_source_root($source, 'source_product_id'),
        'is_deleted' => $inactive ? 1 : 0,
        'title' => $source['title'],
        'slug' => owned_content_unique_slug('products', (string)($source['slug'] ?? ''), $ownerType, $ownerId),
        'short_description' => $source['short_description'],
        'full_description' => $source['full_description'],
        'composition' => $source['composition'],
        'usage_text' => $source['usage_text'],
        'warning_text' => $source['warning_text'],
        'contraindications' => $source['contraindications'],
        'image_path' => $source['image_path'],
        'document_path' => $source['document_path'],
        'video_url' => $source['video_url'],
        'price' => $source['price'],
        'purchase_url' => $source['purchase_url'],
        'is_active' => (int)$source['is_active'],
        'sort_order' => (int)$source['sort_order'],
    ]);

    return (int)db()->lastInsertId();
}

function owned_content_clone_content(array $source, string $ownerType, int $ownerId, bool $inactive): int
{
    $stmt = db()->prepare(
        'INSERT INTO content_posts
            (content_type, section_type, title, short_text, full_text, image_path, attachment_path,
             video_url, button_text, button_url, category_id, owner_type, owner_id, status, publish_at,
             created_by, source_content_post_id, is_deleted)
         VALUES
            (:content_type, :section_type, :title, :short_text, :full_text, :image_path, :attachment_path,
             :video_url, :button_text, :button_url, :category_id, :owner_type, :owner_id, :status, :publish_at,
             :created_by, :source_content_post_id, :is_deleted)'
    );
    $stmt->execute([
        'content_type' => $source['content_type'],
        'section_type' => $source['section_type'],
        'title' => $source['title'],
        'short_text' => $source['short_text'],
        'full_text' => $source['full_text'],
        'image_path' => $source['image_path'],
        'attachment_path' => $source['attachment_path'],
        'video_url' => $source['video_url'],
        'button_text' => $source['button_text'],
        'button_url' => $source['button_url'],
        'category_id' => owned_content_map_category_for_owner($source['category_id'] !== null ? (int)$source['category_id'] : null, $ownerType, $ownerId),
        'owner_type' => $ownerType,
        'owner_id' => $ownerId,
        'status' => $source['status'],
        'publish_at' => $source['publish_at'],
        'created_by' => $source['created_by'],
        'source_content_post_id' => owned_content_source_root($source, 'source_content_post_id'),
        'is_deleted' => $inactive ? 1 : 0,
    ]);

    return (int)db()->lastInsertId();
}

function owned_content_manager_reseller_id(int $managerId): ?int
{
    $stmt = db()->prepare('SELECT reseller_id FROM managers WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $managerId]);
    $resellerId = $stmt->fetchColumn();

    return $resellerId !== false && $resellerId !== null ? (int)$resellerId : null;
}

function owned_content_clone_broadcast(array $source, string $ownerType, int $ownerId, bool $inactive): int
{
    $targetType = $ownerType;
    $targetResellerId = $ownerType === 'reseller' ? $ownerId : owned_content_manager_reseller_id($ownerId);
    $targetManagerId = $ownerType === 'manager' ? $ownerId : null;

    $stmt = db()->prepare(
        'INSERT INTO broadcasts
            (owner_type, owner_id, source_broadcast_id, title, message_text, audience_type, image_path,
             video_path, button_text, button_url, target_type, target_reseller_id, target_manager_id,
             segment_stage, segment_checkup, segment_activity,
             platform, schedule_type, scheduled_at, status, created_by, is_deleted)
         VALUES
            (:owner_type, :owner_id, :source_broadcast_id, :title, :message_text, :audience_type, :image_path,
             :video_path, :button_text, :button_url, :target_type, :target_reseller_id, :target_manager_id,
             :segment_stage, :segment_checkup, :segment_activity,
             :platform, :schedule_type, :scheduled_at, :status, :created_by, :is_deleted)'
    );
    $stmt->execute([
        'owner_type' => $ownerType,
        'owner_id' => $ownerId,
        'source_broadcast_id' => owned_content_source_root($source, 'source_broadcast_id'),
        'title' => $source['title'],
        'message_text' => $source['message_text'],
        'audience_type' => $source['audience_type'],
        'image_path' => $source['image_path'],
        'video_path' => $source['video_path'],
        'button_text' => $source['button_text'],
        'button_url' => $source['button_url'],
        'target_type' => $targetType,
        'target_reseller_id' => $targetResellerId,
        'target_manager_id' => $targetManagerId,
        'segment_stage' => $source['segment_stage'] ?? null,
        'segment_checkup' => $source['segment_checkup'] ?? null,
        'segment_activity' => $source['segment_activity'] ?? null,
        'platform' => $source['platform'],
        'schedule_type' => $source['schedule_type'],
        'scheduled_at' => $inactive ? null : $source['scheduled_at'],
        'status' => $inactive ? 'cancelled' : 'draft',
        'created_by' => $source['created_by'],
        'is_deleted' => $inactive ? 1 : 0,
    ]);

    return (int)db()->lastInsertId();
}

function owned_content_clone_test_children(int $sourceTestId, int $targetTestId, string $ownerType, int $ownerId): void
{
    $scaleMap = [];
    $scales = db()->prepare('SELECT * FROM test_scales WHERE test_id = :test_id ORDER BY id');
    $scales->execute(['test_id' => $sourceTestId]);
    $insertScale = db()->prepare(
        'INSERT INTO test_scales (test_id, slug, title, description, sort_order)
         VALUES (:test_id, :slug, :title, :description, :sort_order)'
    );
    foreach ($scales->fetchAll() as $scale) {
        $insertScale->execute([
            'test_id' => $targetTestId,
            'slug' => $scale['slug'],
            'title' => $scale['title'],
            'description' => $scale['description'],
            'sort_order' => (int)$scale['sort_order'],
        ]);
        $scaleMap[(int)$scale['id']] = (int)db()->lastInsertId();
    }

    if ($scaleMap) {
        $results = db()->prepare('SELECT * FROM test_scale_results WHERE scale_id = :scale_id ORDER BY id');
        $insertResult = db()->prepare(
            'INSERT INTO test_scale_results
                (scale_id, title, min_score, max_score, severity, summary_text, advice_text, sort_order)
             VALUES
                (:scale_id, :title, :min_score, :max_score, :severity, :summary_text, :advice_text, :sort_order)'
        );
        foreach ($scaleMap as $sourceScaleId => $targetScaleId) {
            $results->execute(['scale_id' => $sourceScaleId]);
            foreach ($results->fetchAll() as $result) {
                $insertResult->execute([
                    'scale_id' => $targetScaleId,
                    'title' => $result['title'],
                    'min_score' => (int)$result['min_score'],
                    'max_score' => (int)$result['max_score'],
                    'severity' => $result['severity'],
                    'summary_text' => $result['summary_text'],
                    'advice_text' => $result['advice_text'],
                    'sort_order' => (int)$result['sort_order'],
                ]);
            }
        }
    }

    $questionMap = [];
    $answerMap = [];
    $questions = db()->prepare('SELECT * FROM test_questions WHERE test_id = :test_id ORDER BY id');
    $questions->execute(['test_id' => $sourceTestId]);
    $insertQuestion = db()->prepare(
        'INSERT INTO test_questions (test_id, question_text, question_type, gender_scope, is_required, sort_order)
         VALUES (:test_id, :question_text, :question_type, :gender_scope, :is_required, :sort_order)'
    );
    $answers = db()->prepare('SELECT * FROM test_answers WHERE question_id = :question_id ORDER BY id');
    $insertAnswer = db()->prepare(
        'INSERT INTO test_answers
            (question_id, answer_text, score, tag_id, category_id, product_id, sort_order)
         VALUES
            (:question_id, :answer_text, :score, :tag_id, :category_id, :product_id, :sort_order)'
    );
    foreach ($questions->fetchAll() as $question) {
        $insertQuestion->execute([
            'test_id' => $targetTestId,
            'question_text' => $question['question_text'],
            'question_type' => $question['question_type'],
            'gender_scope' => $question['gender_scope'],
            'is_required' => (int)$question['is_required'],
            'sort_order' => (int)$question['sort_order'],
        ]);
        $targetQuestionId = (int)db()->lastInsertId();
        $questionMap[(int)$question['id']] = $targetQuestionId;

        $answers->execute(['question_id' => (int)$question['id']]);
        foreach ($answers->fetchAll() as $answer) {
            $insertAnswer->execute([
                'question_id' => $targetQuestionId,
                'answer_text' => $answer['answer_text'],
                'score' => (int)$answer['score'],
                'tag_id' => $answer['tag_id'],
                'category_id' => owned_content_map_category_for_owner($answer['category_id'] !== null ? (int)$answer['category_id'] : null, $ownerType, $ownerId),
                'product_id' => owned_content_map_product_for_owner($answer['product_id'] !== null ? (int)$answer['product_id'] : null, $ownerType, $ownerId),
                'sort_order' => (int)$answer['sort_order'],
            ]);
            $answerMap[(int)$answer['id']] = (int)db()->lastInsertId();
        }
    }

    if ($answerMap && $scaleMap) {
        $scaleScores = db()->prepare('SELECT * FROM test_answer_scale_scores WHERE answer_id = :answer_id ORDER BY id');
        $insertScaleScore = db()->prepare(
            'INSERT IGNORE INTO test_answer_scale_scores (answer_id, scale_id, score)
             VALUES (:answer_id, :scale_id, :score)'
        );
        foreach ($answerMap as $sourceAnswerId => $targetAnswerId) {
            $scaleScores->execute(['answer_id' => $sourceAnswerId]);
            foreach ($scaleScores->fetchAll() as $score) {
                $sourceScaleId = (int)$score['scale_id'];
                if (!isset($scaleMap[$sourceScaleId])) {
                    continue;
                }
                $insertScaleScore->execute([
                    'answer_id' => $targetAnswerId,
                    'scale_id' => $scaleMap[$sourceScaleId],
                    'score' => (int)$score['score'],
                ]);
            }
        }
    }

    $testResults = db()->prepare('SELECT * FROM test_results WHERE test_id = :test_id ORDER BY id');
    $testResults->execute(['test_id' => $sourceTestId]);
    $insertTestResult = db()->prepare(
        'INSERT INTO test_results
            (test_id, title, min_score, max_score, summary_text, advice_text, product_id, category_id, sort_order)
         VALUES
            (:test_id, :title, :min_score, :max_score, :summary_text, :advice_text, :product_id, :category_id, :sort_order)'
    );
    foreach ($testResults->fetchAll() as $result) {
        $insertTestResult->execute([
            'test_id' => $targetTestId,
            'title' => $result['title'],
            'min_score' => (int)$result['min_score'],
            'max_score' => (int)$result['max_score'],
            'summary_text' => $result['summary_text'],
            'advice_text' => $result['advice_text'],
            'product_id' => owned_content_map_product_for_owner($result['product_id'] !== null ? (int)$result['product_id'] : null, $ownerType, $ownerId),
            'category_id' => owned_content_map_category_for_owner($result['category_id'] !== null ? (int)$result['category_id'] : null, $ownerType, $ownerId),
            'sort_order' => (int)$result['sort_order'],
        ]);
    }
}

function owned_content_clone_test(array $source, string $ownerType, int $ownerId, bool $inactive): int
{
    $stmt = db()->prepare(
        'INSERT INTO tests
            (title, description, category_id, scoring_type, emoji, intro_text, intro_image_path,
             intro_video_url, owner_type, owner_id, source_test_id, is_deleted, is_active, sort_order)
         VALUES
            (:title, :description, :category_id, :scoring_type, :emoji, :intro_text, :intro_image_path,
             :intro_video_url, :owner_type, :owner_id, :source_test_id, :is_deleted, :is_active, :sort_order)'
    );
    $stmt->execute([
        'title' => $source['title'],
        'description' => $source['description'],
        'category_id' => owned_content_map_category_for_owner($source['category_id'] !== null ? (int)$source['category_id'] : null, $ownerType, $ownerId),
        'scoring_type' => $source['scoring_type'],
        'emoji' => $source['emoji'],
        'intro_text' => $source['intro_text'],
        'intro_image_path' => $source['intro_image_path'],
        'intro_video_url' => $source['intro_video_url'],
        'owner_type' => $ownerType,
        'owner_id' => $ownerId,
        'source_test_id' => owned_content_source_root($source, 'source_test_id'),
        'is_deleted' => $inactive ? 1 : 0,
        'is_active' => (int)$source['is_active'],
        'sort_order' => (int)$source['sort_order'],
    ]);
    $targetTestId = (int)db()->lastInsertId();
    if (!$inactive) {
        owned_content_clone_test_children((int)$source['id'], $targetTestId, $ownerType, $ownerId);
    }

    return $targetTestId;
}

function owned_content_clone_for_owner(string $moduleKey, int $sourceId, string $ownerType, int $ownerId, bool $inactive = false): int
{
    $source = owned_content_row($moduleKey, $sourceId);
    if (!$source) {
        throw new RuntimeException('Source content not found.');
    }

    if (owned_content_row_matches_owner($source, ['owner_type' => $ownerType, 'owner_id' => $ownerId])) {
        if ($inactive) {
            owned_content_mark_deleted($moduleKey, $sourceId);
        }
        return $sourceId;
    }

    $existingId = owned_content_existing_clone_id($moduleKey, $sourceId, $ownerType, $ownerId);
    if ($existingId) {
        if ($inactive) {
            owned_content_mark_deleted($moduleKey, $existingId);
        }
        owned_content_sync_profile_selection($moduleKey, $sourceId, $existingId, $ownerType, $ownerId);
        return $existingId;
    }

    $cloneId = match ($moduleKey) {
        'categories' => owned_content_clone_category($source, $ownerType, $ownerId, $inactive),
        'products' => owned_content_clone_product($source, $ownerType, $ownerId, $inactive),
        'tests' => owned_content_clone_test($source, $ownerType, $ownerId, $inactive),
        'content' => owned_content_clone_content($source, $ownerType, $ownerId, $inactive),
        'broadcasts' => owned_content_clone_broadcast($source, $ownerType, $ownerId, $inactive),
        default => throw new RuntimeException('Unsupported content module.'),
    };

    owned_content_sync_profile_selection($moduleKey, $sourceId, $cloneId, $ownerType, $ownerId);
    return $cloneId;
}

function owned_content_sync_profile_selection(string $moduleKey, int $sourceId, int $cloneId, string $ownerType, int $ownerId): void
{
    $map = [
        'products' => ['table' => 'profile_products', 'column' => 'product_id'],
        'tests' => ['table' => 'profile_tests', 'column' => 'test_id'],
        'content' => ['table' => 'profile_materials', 'column' => 'content_post_id'],
    ];
    if (!isset($map[$moduleKey])) {
        return;
    }

    $profile = db()->prepare(
        'SELECT id
         FROM consultant_profiles
         WHERE owner_type = :owner_type AND owner_id = :owner_id
         LIMIT 1'
    );
    $profile->execute([
        'owner_type' => $ownerType,
        'owner_id' => $ownerId,
    ]);
    $profileId = $profile->fetchColumn();
    if ($profileId === false) {
        return;
    }

    $table = $map[$moduleKey]['table'];
    $column = $map[$moduleKey]['column'];
    $update = db()->prepare(
        "UPDATE IGNORE {$table}
         SET {$column} = :clone_id
         WHERE profile_id = :profile_id AND {$column} = :source_id"
    );
    $update->execute([
        'clone_id' => $cloneId,
        'profile_id' => (int)$profileId,
        'source_id' => $sourceId,
    ]);

    $cleanup = db()->prepare(
        "DELETE FROM {$table}
         WHERE profile_id = :profile_id AND {$column} = :source_id"
    );
    $cleanup->execute([
        'profile_id' => (int)$profileId,
        'source_id' => $sourceId,
    ]);
}

function owned_content_editable_id(string $moduleKey, int $id, array $admin): int
{
    if ($admin['role'] === 'superadmin') {
        return $id;
    }

    $owner = owned_content_admin_owner($admin);
    if (!$owner) {
        return $id;
    }

    $row = owned_content_row($moduleKey, $id);
    if (!$row || owned_content_row_matches_owner($row, $owner)) {
        return $id;
    }

    return owned_content_clone_for_owner($moduleKey, $id, $owner['owner_type'], (int)$owner['owner_id']);
}

function owned_content_delete_for_admin(string $moduleKey, int $id, array $admin): bool
{
    if ($admin['role'] === 'superadmin' || !owned_content_config($moduleKey)) {
        return false;
    }

    $owner = owned_content_admin_owner($admin);
    if (!$owner) {
        return false;
    }

    $row = owned_content_row($moduleKey, $id);
    if (!$row) {
        return false;
    }

    $sourceColumn = owned_content_config($moduleKey)['source_column'];
    if (owned_content_row_matches_owner($row, $owner) && empty($row[$sourceColumn])) {
        return false;
    }

    owned_content_clone_for_owner($moduleKey, $id, $owner['owner_type'], (int)$owner['owner_id'], true);
    return true;
}

function owned_content_owner_label(array $row, array $admin): string
{
    $ownerType = $row['owner_type'] ?? null;
    $ownerId = !empty($row['owner_id']) ? (int)$row['owner_id'] : null;
    if ($ownerType === null || $ownerType === '') {
        return 'Базовый контент';
    }

    $owner = owned_content_admin_owner($admin);
    $sourceColumn = null;
    foreach (owned_content_config_keys() as $moduleKey) {
        $config = owned_content_config($moduleKey);
        if ($config && array_key_exists($config['source_column'], $row)) {
            $sourceColumn = $config['source_column'];
            break;
        }
    }
    $isClone = $sourceColumn !== null && !empty($row[$sourceColumn]);

    if ($owner && $owner['owner_type'] === $ownerType && (int)$owner['owner_id'] === (int)$ownerId) {
        return $isClone ? 'Моя версия' : 'Мой оригинал';
    }

    if ($ownerType === 'reseller' && $ownerId) {
        return 'Версия лидера: ' . team_reseller_label($ownerId);
    }

    if ($ownerType === 'manager' && $ownerId) {
        $stmt = db()->prepare('SELECT name FROM managers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $ownerId]);
        $name = $stmt->fetchColumn();
        return 'Версия консультанта: ' . ($name ?: ('#' . $ownerId));
    }

    return 'Персональная версия';
}

function owned_content_config_keys(): array
{
    return ['categories', 'products', 'tests', 'content', 'broadcasts'];
}

function owned_content_can_reset(string $moduleKey, array $row, array $admin): bool
{
    if (($admin['role'] ?? 'superadmin') === 'superadmin') {
        return false;
    }

    $config = owned_content_config($moduleKey);
    $owner = owned_content_admin_owner($admin);
    if (!$config || !$owner) {
        return false;
    }

    return owned_content_row_matches_owner($row, $owner)
        && !empty($row[$config['source_column']]);
}

function owned_content_reset_for_admin(string $moduleKey, int $id, array $admin): bool
{
    $row = owned_content_row($moduleKey, $id);
    if (!$row || !owned_content_can_reset($moduleKey, $row, $admin)) {
        return false;
    }

    $config = owned_content_config($moduleKey);
    $stmt = db()->prepare('DELETE FROM ' . $config['table'] . ' WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);

    log_activity('admin', (int)$admin['id'], 'reset_owned_content', $config['table'], $id, [
        'module' => $moduleKey,
        'source_id' => (int)$row[$config['source_column']],
    ]);

    return true;
}
