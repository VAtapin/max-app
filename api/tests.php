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

function test_scale_result_for_score(int $scaleId, int $score): ?array
{
    $stmt = db()->prepare(
        'SELECT *
         FROM test_scale_results
         WHERE scale_id = :scale_id
           AND min_score <= :score_min
           AND max_score >= :score_max
         ORDER BY sort_order, min_score DESC, id DESC
         LIMIT 1'
    );
    $stmt->execute([
        'scale_id' => $scaleId,
        'score_min' => $score,
        'score_max' => $score,
    ]);

    $result = $stmt->fetch();
    return $result ?: null;
}

function save_test_scale_scores(int $testId, int $sessionId, array $answerIds): array
{
    $scalesStmt = db()->prepare(
        'SELECT id, slug, title, description, sort_order
         FROM test_scales
         WHERE test_id = :test_id
         ORDER BY sort_order, id'
    );
    $scalesStmt->execute(['test_id' => $testId]);
    $scales = $scalesStmt->fetchAll();
    if (!$scales) {
        return [];
    }

    $scores = [];
    foreach ($scales as $scale) {
        $scores[(int)$scale['id']] = 0;
    }

    $answerIds = array_values(array_unique(array_filter(array_map('intval', $answerIds))));
    if ($answerIds) {
        $placeholders = implode(',', array_fill(0, count($answerIds), '?'));
        $scoreStmt = db()->prepare(
            "SELECT scale_id, SUM(score) AS score
             FROM test_answer_scale_scores
             WHERE answer_id IN ($placeholders)
             GROUP BY scale_id"
        );
        $scoreStmt->execute($answerIds);
        foreach ($scoreStmt->fetchAll() as $item) {
            $scaleId = (int)$item['scale_id'];
            if (array_key_exists($scaleId, $scores)) {
                $scores[$scaleId] = (int)$item['score'];
            }
        }
    }

    $insert = db()->prepare(
        'INSERT INTO user_test_scale_scores (session_id, scale_id, score, result_id)
         VALUES (:session_id, :scale_id, :score, :result_id)'
    );
    $cleanup = db()->prepare('DELETE FROM user_test_scale_scores WHERE session_id = :session_id');
    $cleanup->execute(['session_id' => $sessionId]);

    $items = [];
    foreach ($scales as $scale) {
        $scaleId = (int)$scale['id'];
        $score = $scores[$scaleId] ?? 0;
        $result = test_scale_result_for_score($scaleId, $score);
        $resultId = $result ? (int)$result['id'] : null;
        $insert->execute([
            'session_id' => $sessionId,
            'scale_id' => $scaleId,
            'score' => $score,
            'result_id' => $resultId,
        ]);

        $items[] = [
            'scale_id' => $scaleId,
            'slug' => $scale['slug'],
            'title' => $scale['title'],
            'description' => $scale['description'],
            'score' => $score,
            'result' => $result ? [
                'title' => $result['title'],
                'severity' => $result['severity'],
                'summary_text' => $result['summary_text'],
                'advice_text' => $result['advice_text'],
            ] : null,
        ];
    }

    return $items;
}

function build_scale_result_summary(array $scaleResults): string
{
    if (!$scaleResults) {
        return '';
    }

    $severityWeight = ['critical' => 4, 'risk' => 3, 'good' => 2, 'excellent' => 1];
    usort($scaleResults, static function (array $left, array $right) use ($severityWeight): int {
        $leftSeverity = $left['result']['severity'] ?? 'good';
        $rightSeverity = $right['result']['severity'] ?? 'good';
        $weightDiff = ($severityWeight[$rightSeverity] ?? 0) <=> ($severityWeight[$leftSeverity] ?? 0);
        return $weightDiff !== 0 ? $weightDiff : ($right['score'] <=> $left['score']);
    });

    $parts = ['Ваши результаты по системам организма готовы. Ниже показаны направления, которым стоит уделить внимание в первую очередь.'];
    foreach (array_slice($scaleResults, 0, 3) as $item) {
        $result = $item['result'] ?? null;
        if (!$result) {
            continue;
        }
        $parts[] = $item['title'] . ': ' . $result['title'] . ' (' . $item['score'] . '). ' . trim((string)($result['summary_text'] ?? ''));
    }
    $parts[] = 'Чтобы получить персональный разбор и подобрать программу под ваши цели, отправьте результат консультанту.';
    $parts[] = medical_disclaimer();

    return implode("\n\n", array_filter($parts));
}

function test_result_materials(): array
{
    $stmt = db()->query(
        "SELECT id, title, short_text, content_type, image_path, video_url, attachment_path, button_text, button_url
         FROM content_posts
         WHERE status = 'published'
           AND title IN (
             'Как читать диагностику организма',
             'Энергия, восстановление и ежедневный ресурс',
             'Пищеварение и комфорт после еды',
             'Кожа, волосы и внешний вид как зеркало привычек',
             'Персональная программа с консультантом'
           )
         ORDER BY FIELD(title,
             'Как читать диагностику организма',
             'Энергия, восстановление и ежедневный ресурс',
             'Пищеварение и комфорт после еды',
             'Кожа, волосы и внешний вид как зеркало привычек',
             'Персональная программа с консультантом'
         )"
    );

    return $stmt->fetchAll();
}

function test_emoji(array $test): string
{
    $emoji = trim((string)($test['emoji'] ?? ''));
    if ($emoji !== '') {
        return $emoji;
    }

    $title = (string)($test['title'] ?? '');
    $title = function_exists('mb_strtolower') ? mb_strtolower($title, 'UTF-8') : strtolower($title);

    return str_contains($title, 'диагност')
        ? '🌿'
        : '✨';
}

function public_test_payload(array $test): array
{
    $intro = trim((string)($test['intro_text'] ?? ''));
    if ($intro === '') {
        $intro = trim((string)($test['description'] ?? ''));
    }

    return [
        'id' => (int)$test['id'],
        'title' => $test['title'],
        'description' => $test['description'],
        'category_id' => $test['category_id'] ?? null,
        'category_title' => $test['category_title'] ?? null,
        'scoring_type' => $test['scoring_type'] ?? 'single',
        'emoji' => test_emoji($test),
        'intro_text' => $intro,
        'intro_image_path' => $test['intro_image_path'] ?? null,
        'intro_video_url' => $test['intro_video_url'] ?? null,
        'questions_count' => isset($test['questions_count']) ? (int)$test['questions_count'] : null,
    ];
}

function test_question_count(int $testId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM test_questions WHERE test_id = :test_id');
    $stmt->execute(['test_id' => $testId]);
    return (int)$stmt->fetchColumn();
}

function test_question_payload(array $question): array
{
    $answers = db()->prepare('SELECT * FROM test_answers WHERE question_id = :question_id ORDER BY sort_order, id');
    $answers->execute(['question_id' => $question['id']]);
    $question['answers'] = $answers->fetchAll();
    return $question;
}

function load_client_test(int $testId, array $user): ?array
{
    [$ownerWhere, $ownerParams] = client_owner_scope($user, 't');
    $stmt = db()->prepare(
        "SELECT t.*, pc.title AS category_title,
                (SELECT COUNT(*) FROM test_questions q WHERE q.test_id = t.id) AS questions_count
         FROM tests t
         LEFT JOIN product_categories pc ON pc.id = t.category_id
         WHERE t.id = :id AND t.is_active = 1 AND $ownerWhere
         LIMIT 1"
    );
    $stmt->execute(['id' => $testId] + $ownerParams);
    $test = $stmt->fetch();
    return $test ?: null;
}

function latest_draft_session(int $endUserId, int $testId): ?array
{
    $stmt = db()->prepare(
        'SELECT *
         FROM user_test_sessions
         WHERE end_user_id = :end_user_id
           AND test_id = :test_id
           AND completed_at IS NULL
         ORDER BY id DESC
         LIMIT 1'
    );
    $stmt->execute([
        'end_user_id' => $endUserId,
        'test_id' => $testId,
    ]);
    $session = $stmt->fetch();
    return $session ?: null;
}

function draft_progress(int $sessionId, int $testId): array
{
    $total = test_question_count($testId);
    $answeredStmt = db()->prepare(
        'SELECT COUNT(DISTINCT uta.question_id)
         FROM user_test_answers uta
         INNER JOIN test_questions tq ON tq.id = uta.question_id
         WHERE uta.session_id = :session_id AND tq.test_id = :test_id'
    );
    $answeredStmt->execute(['session_id' => $sessionId, 'test_id' => $testId]);
    $answered = (int)$answeredStmt->fetchColumn();

    return [
        'answered' => $answered,
        'total' => $total,
        'percent' => $total > 0 ? (int)floor(($answered / $total) * 100) : 0,
    ];
}

function next_question_for_session(int $sessionId, int $testId): ?array
{
    $stmt = db()->prepare(
        'SELECT q.*
         FROM test_questions q
         LEFT JOIN user_test_answers uta ON uta.question_id = q.id AND uta.session_id = :session_id
         WHERE q.test_id = :test_id
           AND uta.id IS NULL
         ORDER BY q.sort_order, q.id
         LIMIT 1'
    );
    $stmt->execute(['session_id' => $sessionId, 'test_id' => $testId]);
    $question = $stmt->fetch();
    return $question ? test_question_payload($question) : null;
}

function session_answer_ids(int $sessionId): array
{
    $stmt = db()->prepare('SELECT answer_id FROM user_test_answers WHERE session_id = :session_id AND answer_id IS NOT NULL');
    $stmt->execute(['session_id' => $sessionId]);
    return array_map(static fn(array $row): int => (int)$row['answer_id'], $stmt->fetchAll());
}

function complete_test_session(array $user, int $testId, int $sessionId): array
{
    $scoreStmt = db()->prepare('SELECT COALESCE(SUM(score), 0) FROM user_test_answers WHERE session_id = :session_id');
    $scoreStmt->execute(['session_id' => $sessionId]);
    $total = (int)$scoreStmt->fetchColumn();

    $resultRule = test_result_for_score($testId, $total);
    $scaleResults = save_test_scale_scores($testId, $sessionId, session_answer_ids($sessionId));
    $summary = $scaleResults ? build_scale_result_summary($scaleResults) : build_result_summary($resultRule);

    $done = db()->prepare('UPDATE user_test_sessions SET completed_at = NOW(), total_score = :total, result_summary = :summary WHERE id = :id');
    $done->execute(['total' => $total, 'summary' => $summary, 'id' => $sessionId]);
    $recommendations = build_recommendations((int)$user['id'], $sessionId);
    add_result_recommendation((int)$user['id'], $sessionId, $resultRule);

    $log = db()->prepare(
        'INSERT INTO activity_logs (actor_type, actor_id, action, entity_type, entity_id, details)
         VALUES ("end_user", :actor_id, "complete_test", "user_test_sessions", :entity_id, :details)'
    );
    $log->execute([
        'actor_id' => $user['id'],
        'entity_id' => $sessionId,
        'details' => json_encode(['recommendations_count' => count($recommendations)], JSON_UNESCAPED_UNICODE),
    ]);

    return [
        'done' => true,
        'session_id' => $sessionId,
        'total_score' => $total,
        'summary' => $summary,
        'result' => $resultRule ? [
            'title' => $resultRule['title'],
            'summary_text' => $resultRule['summary_text'],
            'advice_text' => $resultRule['advice_text'],
        ] : null,
        'scale_results' => $scaleResults,
        'materials' => test_result_materials(),
        'recommendations' => $recommendations,
    ];
}

function session_response(array $test, array $session): array
{
    $progress = draft_progress((int)$session['id'], (int)$test['id']);
    $question = next_question_for_session((int)$session['id'], (int)$test['id']);

    return [
        'session' => [
            'id' => (int)$session['id'],
            'started_at' => $session['started_at'],
        ],
        'test' => public_test_payload($test),
        'progress' => $progress,
        'question' => $question,
        'done' => $question === null,
    ];
}

if (($_GET['action'] ?? '') === 'resume') {
    $user = require_platform_user();
    $testId = (int)($_GET['test_id'] ?? 0);
    $test = $testId ? load_client_test($testId, $user) : null;
    if (!$test) {
        json_response(['error' => 'test not found'], 404);
    }

    $session = latest_draft_session((int)$user['id'], $testId);
    json_response([
        'test' => public_test_payload($test),
        'session' => $session ? session_response($test, $session)['session'] : null,
        'progress' => $session ? draft_progress((int)$session['id'], $testId) : null,
        'question' => $session ? next_question_for_session((int)$session['id'], $testId) : null,
    ]);
}

if (($_GET['action'] ?? '') === 'start' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = input_json() ?: $_POST;
    $user = require_platform_user($data);
    $testId = (int)($data['test_id'] ?? 0);
    $reset = !empty($data['reset']);
    $test = $testId ? load_client_test($testId, $user) : null;
    if (!$test) {
        json_response(['error' => 'test not found'], 404);
    }

    if ($reset) {
        $delete = db()->prepare(
            'DELETE FROM user_test_sessions
             WHERE end_user_id = :end_user_id
               AND test_id = :test_id
               AND completed_at IS NULL'
        );
        $delete->execute(['end_user_id' => $user['id'], 'test_id' => $testId]);
    }

    $session = latest_draft_session((int)$user['id'], $testId);
    if (!$session) {
        $insert = db()->prepare('INSERT INTO user_test_sessions (end_user_id, test_id) VALUES (:end_user_id, :test_id)');
        $insert->execute(['end_user_id' => $user['id'], 'test_id' => $testId]);
        $session = [
            'id' => (int)db()->lastInsertId(),
            'started_at' => date('Y-m-d H:i:s'),
        ];
    }

    json_response(session_response($test, $session));
}

if (($_GET['action'] ?? '') === 'reset' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = input_json() ?: $_POST;
    $user = require_platform_user($data);
    $testId = (int)($data['test_id'] ?? 0);
    $delete = db()->prepare(
        'DELETE FROM user_test_sessions
         WHERE end_user_id = :end_user_id
           AND test_id = :test_id
           AND completed_at IS NULL'
    );
    $delete->execute(['end_user_id' => $user['id'], 'test_id' => $testId]);
    json_response(['reset' => true]);
}

if (($_GET['action'] ?? '') === 'answer' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = input_json() ?: $_POST;
    $user = require_platform_user($data);
    $sessionId = (int)($data['session_id'] ?? 0);
    $questionId = (int)($data['question_id'] ?? 0);
    $answerIds = $data['answer_ids'] ?? [];
    if (isset($data['answer_id'])) {
        $answerIds = [(int)$data['answer_id']];
    }
    if (!is_array($answerIds)) {
        $answerIds = [];
    }
    $textAnswer = trim((string)($data['text_answer'] ?? ''));

    $sessionStmt = db()->prepare(
        'SELECT *
         FROM user_test_sessions
         WHERE id = :id AND end_user_id = :end_user_id AND completed_at IS NULL
         LIMIT 1'
    );
    $sessionStmt->execute(['id' => $sessionId, 'end_user_id' => $user['id']]);
    $session = $sessionStmt->fetch();
    if (!$session) {
        json_response(['error' => 'session not found'], 404);
    }

    $testId = (int)$session['test_id'];
    $test = load_client_test($testId, $user);
    if (!$test) {
        json_response(['error' => 'test not found'], 404);
    }

    $questionStmt = db()->prepare('SELECT * FROM test_questions WHERE id = :id AND test_id = :test_id LIMIT 1');
    $questionStmt->execute(['id' => $questionId, 'test_id' => $testId]);
    $question = $questionStmt->fetch();
    if (!$question) {
        json_response(['error' => 'question not found'], 404);
    }

    $answerRows = [];
    $answerIds = array_values(array_unique(array_filter(array_map('intval', $answerIds))));
    if ($answerIds) {
        $placeholders = implode(',', array_fill(0, count($answerIds), '?'));
        $answerStmt = db()->prepare(
            "SELECT id, question_id, score
             FROM test_answers
             WHERE question_id = ? AND id IN ($placeholders)"
        );
        $answerStmt->execute(array_merge([$questionId], $answerIds));
        $answerRows = $answerStmt->fetchAll();
        if (count($answerRows) !== count($answerIds)) {
            json_response(['error' => 'answer does not belong to this question'], 422);
        }
    }

    if (!$answerRows && $textAnswer === '') {
        json_response(['error' => 'answer is required'], 422);
    }

    try {
        db()->beginTransaction();
        $delete = db()->prepare('DELETE FROM user_test_answers WHERE session_id = :session_id AND question_id = :question_id');
        $delete->execute(['session_id' => $sessionId, 'question_id' => $questionId]);

        $insert = db()->prepare(
            'INSERT INTO user_test_answers (session_id, question_id, answer_id, text_answer, score)
             VALUES (:session_id, :question_id, :answer_id, :text_answer, :score)'
        );

        if ($answerRows) {
            foreach ($answerRows as $answer) {
                $insert->execute([
                    'session_id' => $sessionId,
                    'question_id' => $questionId,
                    'answer_id' => (int)$answer['id'],
                    'text_answer' => null,
                    'score' => (int)$answer['score'],
                ]);
            }
        } else {
            $insert->execute([
                'session_id' => $sessionId,
                'question_id' => $questionId,
                'answer_id' => null,
                'text_answer' => $textAnswer,
                'score' => 0,
            ]);
        }

        $next = next_question_for_session($sessionId, $testId);
        if (!$next) {
            $payload = complete_test_session($user, $testId, $sessionId);
            db()->commit();
            json_response($payload);
        }

        db()->commit();
        json_response(session_response($test, $session));
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        json_response(['error' => 'answer save failed: ' . $e->getMessage()], 500);
    }
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

    $answeredQuestions = [];
    foreach ($answers as $answer) {
        $questionId = (int)($answer['question_id'] ?? 0);
        if (!$questionId || !isset($questions[$questionId])) {
            continue;
        }

        $answerId = isset($answer['answer_id']) ? (int)$answer['answer_id'] : null;
        $textAnswer = trim((string)($answer['text_answer'] ?? ''));
        if ($answerId || $textAnswer !== '') {
            $answeredQuestions[$questionId] = true;
        }
    }

    foreach ($questions as $questionId => $question) {
        if ((int)$question['is_required'] === 1 && empty($answeredQuestions[$questionId])) {
            json_response(['error' => 'required questions are not answered'], 422);
        }
    }

    try {
        db()->beginTransaction();
        $sessionStmt = db()->prepare('INSERT INTO user_test_sessions (end_user_id, test_id) VALUES (:end_user_id, :test_id)');
        $sessionStmt->execute(['end_user_id' => $user['id'], 'test_id' => $testId]);
        $sessionId = (int)db()->lastInsertId();

        $total = 0;
        $selectedAnswerIds = [];
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
                $selectedAnswerIds[] = $answerId;
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
        $scaleResults = save_test_scale_scores($testId, $sessionId, $selectedAnswerIds);
        $summary = $scaleResults ? build_scale_result_summary($scaleResults) : build_result_summary($resultRule);
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
        'scale_results' => $scaleResults ?? [],
        'materials' => test_result_materials(),
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

    $draft = $user ? latest_draft_session((int)$user['id'], (int)$test['id']) : null;
    $payload = [
        'test' => public_test_payload($test),
        'questions' => $items,
        'session' => null,
        'progress' => [
            'answered' => 0,
            'total' => count($items),
            'percent' => 0,
        ],
        'question' => null,
    ];
    if ($draft) {
        $sessionPayload = session_response($test, $draft);
        $payload['session'] = $sessionPayload['session'];
        $payload['progress'] = $sessionPayload['progress'];
        $payload['question'] = $sessionPayload['question'];
    }

    json_response($payload);
}

$user = null;
$ownerWhere = 't.owner_type IS NULL';
$ownerParams = [];
if (isset($_GET['platform'], $_GET['platform_user_id'])) {
    $user = require_platform_user();
    [$ownerWhere, $ownerParams] = client_owner_scope($user, 't');
}
$stmt = db()->prepare(
    "SELECT t.id, t.title, t.description, t.scoring_type, t.emoji, t.intro_text,
            t.intro_image_path, t.intro_video_url, pc.title AS category_title,
            COUNT(DISTINCT q.id) AS questions_count
     FROM tests t
     LEFT JOIN product_categories pc ON pc.id = t.category_id
     LEFT JOIN test_questions q ON q.test_id = t.id
     WHERE t.is_active = 1 AND $ownerWhere
     GROUP BY t.id, t.title, t.description, t.scoring_type, t.emoji, t.intro_text,
              t.intro_image_path, t.intro_video_url, pc.title, t.sort_order
     ORDER BY t.sort_order, t.title"
);
$stmt->execute($ownerParams);
json_response(['tests' => $stmt->fetchAll()]);
