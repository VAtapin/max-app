<?php

require_once __DIR__ . '/../admin/app/core/ai_content_governance.php';

function build_recommendations(int $endUserId, int $testSessionId): array
{
    $userStmt = db()->prepare('SELECT * FROM end_users WHERE id = :id LIMIT 1');
    $userStmt->execute(['id' => $endUserId]);
    $user = $userStmt->fetch() ?: [];

    $scoresStmt = db()->prepare(
        'SELECT
            ta.category_id,
            ta.tag_id,
            ta.product_id,
            SUM(uta.score) AS score
         FROM user_test_answers uta
         JOIN test_answers ta ON ta.id = uta.answer_id
         WHERE uta.session_id = :session_id
         GROUP BY ta.category_id, ta.tag_id, ta.product_id
         ORDER BY score DESC
         LIMIT 5'
    );
    $scoresStmt->execute(['session_id' => $testSessionId]);
    $scores = $scoresStmt->fetchAll();

    $cleanup = db()->prepare('DELETE FROM recommendations WHERE test_session_id = :session_id');
    $cleanup->execute(['session_id' => $testSessionId]);

    $recommendations = [];
    foreach ($scores as $item) {
        $productId = $item['product_id'] ? (int)$item['product_id'] : null;

        if (!$productId && $item['category_id']) {
            [$ownerWhere, $ownerParams] = client_owner_scope($user, 'p', 'products');
            $categoryStmt = db()->prepare('SELECT source_category_id FROM product_categories WHERE id = :id LIMIT 1');
            $categoryStmt->execute(['id' => (int)$item['category_id']]);
            $sourceCategoryId = $categoryStmt->fetchColumn();
            $productStmt = db()->prepare(
                "SELECT p.id
                 FROM products p
                 LEFT JOIN product_categories pc ON pc.id = p.category_id
                 WHERE (p.category_id = :category_id OR pc.source_category_id = :category_id_clone" . ($sourceCategoryId ? " OR p.category_id = :source_category_id" : "") . ")
                   AND p.is_active = 1
                   AND $ownerWhere
                 ORDER BY p.sort_order, p.id
                 LIMIT 1"
            );
            $productParams = [
                'category_id' => (int)$item['category_id'],
                'category_id_clone' => (int)$item['category_id'],
            ] + $ownerParams;
            if ($sourceCategoryId) {
                $productParams['source_category_id'] = (int)$sourceCategoryId;
            }
            $productStmt->execute($productParams);
            $productId = $productStmt->fetchColumn() ?: null;
        }

        $insert = db()->prepare(
            'INSERT INTO recommendations (
                end_user_id, test_session_id, product_id, category_id, tag_id, reason_text, score
             ) VALUES (
                :end_user_id, :test_session_id, :product_id, :category_id, :tag_id, :reason_text, :score
             )'
        );
        $insert->execute([
            'end_user_id' => $endUserId,
            'test_session_id' => $testSessionId,
            'product_id' => $productId,
            'category_id' => $item['category_id'],
            'tag_id' => $item['tag_id'],
            'reason_text' => app_text('auto.k_60697ed57a0b'),
            'score' => (int)$item['score'],
        ]);

        $recommendations[] = [
            'product_id' => $productId,
            'category_id' => $item['category_id'],
            'tag_id' => $item['tag_id'],
            'score' => (int)$item['score'],
        ];
    }

    ai_apply_recommendation_rules($endUserId, $testSessionId);
    $finalStmt = db()->prepare('SELECT product_id, category_id, tag_id, score FROM recommendations WHERE test_session_id = :session_id ORDER BY score DESC, id');
    $finalStmt->execute(['session_id' => $testSessionId]);
    return $finalStmt->fetchAll();
}
