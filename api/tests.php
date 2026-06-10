<?php

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/recommendation_engine.php';

if (($_GET['action'] ?? '') === 'submit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = input_json();
    $user = require_platform_user();
    $testId = (int)($data['test_id'] ?? 0);
    $answers = $data['answers'] ?? [];

    if (!$testId || !is_array($answers)) {
        json_response(['error' => 'test_id and answers are required'], 422);
    }

    db()->beginTransaction();
    $sessionStmt = db()->prepare('INSERT INTO user_test_sessions (end_user_id, test_id) VALUES (:end_user_id, :test_id)');
    $sessionStmt->execute(['end_user_id' => $user['id'], 'test_id' => $testId]);
    $sessionId = (int)db()->lastInsertId();

    $total = 0;
    foreach ($answers as $answer) {
        $answerId = isset($answer['answer_id']) ? (int)$answer['answer_id'] : null;
        $questionId = (int)($answer['question_id'] ?? 0);
        $score = 0;
        if ($answerId) {
            $scoreStmt = db()->prepare('SELECT score FROM test_answers WHERE id = :id');
            $scoreStmt->execute(['id' => $answerId]);
            $score = (int)($scoreStmt->fetchColumn() ?: 0);
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

    $summary = 'Подобраны направления поддержки. ' . medical_disclaimer();
    $done = db()->prepare('UPDATE user_test_sessions SET completed_at = NOW(), total_score = :total, result_summary = :summary WHERE id = :id');
    $done->execute(['total' => $total, 'summary' => $summary, 'id' => $sessionId]);
    $recommendations = build_recommendations((int)$user['id'], $sessionId);
    db()->commit();

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
        'recommendations' => $recommendations,
    ]);
}

if (isset($_GET['id'])) {
    $stmt = db()->prepare('SELECT * FROM tests WHERE id = :id AND is_active = 1');
    $stmt->execute(['id' => (int)$_GET['id']]);
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

$stmt = db()->query('SELECT id, title, description FROM tests WHERE is_active = 1 ORDER BY sort_order, title');
json_response(['tests' => $stmt->fetchAll()]);
