<?php

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/recommendation_engine.php';

function test_result_for_score(int $testId, int $totalScore): ?array
{
    $stmt = db()->prepare(
        'SELECT *
         FROM test_results
         WHERE test_id = :test_id
           AND min_score <= :min_score
           AND max_score >= :max_score
         ORDER BY sort_order, min_score DESC, id DESC
         LIMIT 1'
    );

    $stmt->execute([
        'test_id' => $testId,
        'min_score' => $totalScore,
        'max_score' => $totalScore,
    ]);

    $result = $stmt->fetch();

    return $result ?: null;
}

function build_result_summary(?array $result): string
{
    if (!$result) {
        return app_text('auto.k_2a2863ee4b8f') . medical_disclaimer();
    }

    $parts = [$result['title']];
    if (!empty($result['summary_text'])) {
        $parts[] = $result['summary_text'];
    }
    if (!empty($result['advice_text'])) {
        $parts[] = $result['advice_text'];
    }
    $parts[] = medical_disclaimer();

    return implode("\n\n", array_filter($parts));
}

function add_result_recommendation(int $endUserId, int $sessionId, ?array $result): void
{
    if (!$result || (empty($result['product_id']) && empty($result['category_id']))) {
        return;
    }

    $productId = $result['product_id'] ? (int)$result['product_id'] : null;
    if (!$productId && !empty($result['category_id'])) {
        $productStmt = db()->prepare(
            'SELECT id FROM products
             WHERE category_id = :category_id AND is_active = 1
             ORDER BY sort_order, id
             LIMIT 1'
        );
        $productStmt->execute(['category_id' => (int)$result['category_id']]);
        $productId = $productStmt->fetchColumn() ?: null;
    }

    $stmt = db()->prepare(
        'INSERT INTO recommendations (end_user_id, test_session_id, product_id, category_id, reason_text, score)
         VALUES (:end_user_id, :test_session_id, :product_id, :category_id, :reason_text, :score)'
    );
    $stmt->execute([
        'end_user_id' => $endUserId,
        'test_session_id' => $sessionId,
        'product_id' => $productId,
        'category_id' => $result['category_id'] ?: null,
        'reason_text' => trim(($result['title'] ?? '') . "\n" . ($result['advice_text'] ?? '')),
        'score' => 1000 + (int)($result['sort_order'] ?? 0),
    ]);
}


if (($_GET['action'] ?? '') === 'submit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = input_json() ?: $_POST;
    $user = require_platform_user($data);
    $testId = (int)($data['test_id'] ?? 0);
    $answers = $data['answers'] ?? [];

    if (!$testId || !is_array($answers)) {
        json_response(['error' => 'test_id and answers are required'], 422);
    }

    [$ownerWhere, $ownerParams] = client_owner_scope($user, 't');
    $testStmt = db()->prepare("SELECT t.id FROM tests t WHERE t.id = :id AND t.is_active = 1 AND $ownerWhere LIMIT 1");
    $testStmt->execute(['id' => $testId] + $ownerParams);
    if (!$testStmt->fetchColumn()) {
        json_response(['error' => 'test not found'], 404);
    }

    $questionStmt = db()->prepare('SELECT id, question_type, is_required FROM test_questions WHERE test_id = :test_id');
    $questionStmt->execute(['test_id' => $testId]);
    $questions = [];
    foreach ($questionStmt->fetchAll() as $question) {
        $questions[(int)$question['id']] = $question;
    }

    if (!$questions) {
        json_response(['error' => 'test has no questions'], 422);
    }

    $answerIds = [];
    foreach ($answers as $answer) {
        if (isset($answer['answer_id'])) {
            $answerIds[] = (int)$answer['answer_id'];
        }
    }
    $answerMap = [];
    if ($answerIds) {
        $placeholders = implode(',', array_fill(0, count($answerIds), '?'));
        $answerStmt = db()->prepare(
            "SELECT a.id, a.question_id, a.score
             FROM test_answers a
             INNER JOIN test_questions q ON q.id = a.question_id
             WHERE q.test_id = ? AND a.id IN ($placeholders)"
        );
        $answerStmt->execute(array_merge([$testId], $answerIds));
        foreach ($answerStmt->fetchAll() as $item) {
            $answerMap[(int)$item['id']] = $item;
        }
    }

    try {
        db()->beginTransaction();
        $sessionStmt = db()->prepare('INSERT INTO user_test_sessions (end_user_id, test_id) VALUES (:end_user_id, :test_id)');
        $sessionStmt->execute(['end_user_id' => $user['id'], 'test_id' => $testId]);
        $sessionId = (int)db()->lastInsertId();

        $total = 0;
        foreach ($answers as $answer) {
            $answerId = isset($answer['answer_id']) ? (int)$answer['answer_id'] : null;
            $questionId = (int)($answer['question_id'] ?? 0);
            if (!$questionId || !isset($questions[$questionId])) {
                throw new RuntimeException('question does not belong to this test');
            }

            $score = 0;
            if ($answerId) {
                if (!isset($answerMap[$answerId]) || (int)$answerMap[$answerId]['question_id'] !== $questionId) {
                    throw new RuntimeException('answer does not belong to this question');
                }
                $score = (int)$answerMap[$answerId]['score'];
            }
            $total += $score;

            $insert = db()->prepare(
                'INSERT INTO user_test_answers (session_id, question_id, answer_id, text_answer, score)
                 VALUES (:session_id, :question_id, :answer_id, :text_answer, :score)'
            );
            $insert->execute([
                'session_id' => $sessionId,
                'question_id' => $questionId,
                'answer_id' => $answerId,
                'text_answer' => $answer['text_answer'] ?? null,
                'score' => $score,
            ]);
        }

        $resultRule = test_result_for_score($testId, $total);
        $summary = build_result_summary($resultRule);
        $done = db()->prepare('UPDATE user_test_sessions SET completed_at = NOW(), total_score = :total, result_summary = :summary WHERE id = :id');
        $done->execute(['total' => $total, 'summary' => $summary, 'id' => $sessionId]);
        $recommendations = build_recommendations((int)$user['id'], $sessionId);
        add_result_recommendation((int)$user['id'], $sessionId, $resultRule);
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        json_response(['error' => 'test submit failed: ' . $e->getMessage()], 500);
    }

    $log = db()->prepare(
        'INSERT INTO activity_logs (actor_type, actor_id, action, entity_type, entity_id, details)
         VALUES ("end_user", :actor_id, "complete_test", "user_test_sessions", :entity_id, :details)'
    );
    $log->execute([
        'actor_id' => $user['id'],
        'entity_id' => $sessionId,
        'details' => json_encode(['recommendations_count' => count($recommendations)], JSON_UNESCAPED_UNICODE),
    ]);

    json_response([
        'session_id' => $sessionId,
        'total_score' => $total,
        'summary' => $summary,
        'result' => $resultRule ? [
            'title' => $resultRule['title'],
            'summary_text' => $resultRule['summary_text'],
            'advice_text' => $resultRule['advice_text'],
        ] : null,
        'recommendations' => $recommendations,
    ]);
}

if (isset($_GET['id'])) {
    $user = null;
    $ownerWhere = 't.owner_type IS NULL';
    $ownerParams = [];
    if (isset($_GET['platform'], $_GET['platform_user_id'])) {
        $user = require_platform_user();
        [$ownerWhere, $ownerParams] = client_owner_scope($user, 't');
    }
    $stmt = db()->prepare("SELECT t.* FROM tests t WHERE t.id = :id AND t.is_active = 1 AND $ownerWhere");
    $stmt->execute(['id' => (int)$_GET['id']] + $ownerParams);
    $test = $stmt->fetch();
    if (!$test) {
        json_response(['error' => 'not found'], 404);
    }

    $questions = db()->prepare('SELECT * FROM test_questions WHERE test_id = :test_id ORDER BY sort_order, id');
    $questions->execute(['test_id' => $test['id']]);
    $items = $questions->fetchAll();

    foreach ($items as &$question) {
        $answers = db()->prepare('SELECT * FROM test_answers WHERE question_id = :question_id ORDER BY sort_order, id');
        $answers->execute(['question_id' => $question['id']]);
        $question['answers'] = $answers->fetchAll();
    }

    json_response(['test' => $test, 'questions' => $items]);
}

$user = null;
$ownerWhere = 'owner_type IS NULL';
$ownerParams = [];
if (isset($_GET['platform'], $_GET['platform_user_id'])) {
    $user = require_platform_user();
    [$ownerWhere, $ownerParams] = client_owner_scope($user);
}
$stmt = db()->prepare("SELECT id, title, description FROM tests WHERE is_active = 1 AND $ownerWhere ORDER BY sort_order, title");
$stmt->execute($ownerParams);
json_response(['tests' => $stmt->fetchAll()]);
