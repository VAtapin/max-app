<?php

function build_recommendations(int $endUserId, int $testSessionId): array
{
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
            $productStmt = db()->prepare(
                'SELECT id
                 FROM products
                 WHERE category_id = :category_id AND is_active = 1
                 ORDER BY sort_order, id
                 LIMIT 1'
            );
            $productStmt->execute(['category_id' => (int)$item['category_id']]);
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
            'reason_text' => app_text('auto.k_60697ed57a0b') . medical_disclaimer(),
            'score' => (int)$item['score'],
        ]);

        $recommendations[] = [
            'product_id' => $productId,
            'category_id' => $item['category_id'],
            'tag_id' => $item['tag_id'],
            'score' => (int)$item['score'],
        ];
    }

    return $recommendations;
}
