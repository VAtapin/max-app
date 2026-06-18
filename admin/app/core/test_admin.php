<?php

function test_builder_options(string $table, string $labelColumn): array
{
    if (!in_array($table, ['products', 'product_categories'], true)) {
        return [];
    }

    $stmt = db()->query("SELECT id, {$labelColumn} AS label FROM {$table} ORDER BY id DESC LIMIT 500");
    return $stmt->fetchAll();
}

function test_builder_select(string $name, array $items, ?int $selected = null): string
{
    $html = '<select name="' . h($name) . '"><option value="">' . h(app_text('test_builder.not_selected')) . '</option>';
    foreach ($items as $item) {
        $id = (int)$item['id'];
        $isSelected = $selected !== null && $selected === $id ? ' selected' : '';
        $html .= '<option value="' . $id . '"' . $isSelected . '>#' . $id . ' ' . h($item['label']) . '</option>';
    }
    return $html . '</select>';
}

function test_builder_question_type_label(?string $type): string
{
    return t_choice('question_types', $type);
}

function test_builder_test(int $testId): ?array
{
    $stmt = db()->prepare('SELECT * FROM tests WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $testId]);
    $test = $stmt->fetch();
    return $test ?: null;
}

function test_builder_scale_slug(string $value, string $fallback): string
{
    $value = trim($value) !== '' ? trim($value) : $fallback;
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    $map = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch',
        'ы' => 'y', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya', 'ъ' => '', 'ь' => '',
    ];
    $value = strtr($value, $map);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?: '';
    $value = trim($value, '-');
    return $value !== '' ? substr($value, 0, 100) : $fallback;
}

function test_builder_scales(int $testId): array
{
    $stmt = db()->prepare('SELECT * FROM test_scales WHERE test_id = :test_id ORDER BY sort_order, id');
    $stmt->execute(['test_id' => $testId]);
    $scales = $stmt->fetchAll();
    if (!$scales) {
        return [];
    }

    $scaleIds = array_map(static fn(array $scale): int => (int)$scale['id'], $scales);
    $placeholders = implode(',', array_fill(0, count($scaleIds), '?'));
    $resultsStmt = db()->prepare(
        "SELECT *
         FROM test_scale_results
         WHERE scale_id IN ($placeholders)
         ORDER BY sort_order, min_score, id"
    );
    $resultsStmt->execute($scaleIds);

    $resultsByScale = [];
    foreach ($resultsStmt->fetchAll() as $result) {
        $resultsByScale[(int)$result['scale_id']][] = $result;
    }

    foreach ($scales as &$scale) {
        $scale['results'] = $resultsByScale[(int)$scale['id']] ?? [];
    }
    unset($scale);

    return $scales;
}

function test_builder_save_answer_scales(int $answerId, int $testId, array $scaleIds): void
{
    $delete = db()->prepare('DELETE FROM test_answer_scale_scores WHERE answer_id = :answer_id');
    $delete->execute(['answer_id' => $answerId]);

    $scaleIds = array_values(array_unique(array_filter(array_map('intval', $scaleIds))));
    if (!$scaleIds) {
        return;
    }

    $insert = db()->prepare(
        'INSERT INTO test_answer_scale_scores (answer_id, scale_id, score)
         SELECT :answer_id, id, 1
         FROM test_scales
         WHERE id = :scale_id AND test_id = :test_id'
    );
    foreach ($scaleIds as $scaleId) {
        $insert->execute([
            'answer_id' => $answerId,
            'scale_id' => $scaleId,
            'test_id' => $testId,
        ]);
    }
}

function test_builder_questions(int $testId): array
{
    $stmt = db()->prepare('SELECT * FROM test_questions WHERE test_id = :test_id ORDER BY sort_order, id');
    $stmt->execute(['test_id' => $testId]);
    $questions = $stmt->fetchAll();
    if (!$questions) {
        return [];
    }

    $questionIds = array_map(static fn($question) => (int)$question['id'], $questions);
    $placeholders = implode(',', array_fill(0, count($questionIds), '?'));
    $answersStmt = db()->prepare(
        "SELECT a.*, p.title AS product_title, c.title AS category_title
         FROM test_answers a
         LEFT JOIN products p ON p.id = a.product_id
         LEFT JOIN product_categories c ON c.id = a.category_id
         WHERE a.question_id IN ($placeholders)
         ORDER BY a.sort_order, a.id"
    );
    $answersStmt->execute($questionIds);

    $answersByQuestion = [];
    foreach ($answersStmt->fetchAll() as $answer) {
        $answersByQuestion[(int)$answer['question_id']][] = $answer;
    }

    $answerIds = [];
    foreach ($answersByQuestion as $answers) {
        foreach ($answers as $answer) {
            $answerIds[] = (int)$answer['id'];
        }
    }
    $scaleIdsByAnswer = [];
    if ($answerIds) {
        $answerPlaceholders = implode(',', array_fill(0, count($answerIds), '?'));
        $scaleStmt = db()->prepare(
            "SELECT answer_id, scale_id
             FROM test_answer_scale_scores
             WHERE answer_id IN ($answerPlaceholders)
             ORDER BY scale_id"
        );
        $scaleStmt->execute($answerIds);
        foreach ($scaleStmt->fetchAll() as $item) {
            $scaleIdsByAnswer[(int)$item['answer_id']][] = (int)$item['scale_id'];
        }
    }

    foreach ($answersByQuestion as &$answers) {
        foreach ($answers as &$answer) {
            $answer['scale_ids'] = $scaleIdsByAnswer[(int)$answer['id']] ?? [];
        }
        unset($answer);
    }
    unset($answers);

    foreach ($questions as &$question) {
        $question['answers'] = $answersByQuestion[(int)$question['id']] ?? [];
    }
    unset($question);

    return $questions;
}

function test_builder_results(int $testId): array
{
    $stmt = db()->prepare(
        'SELECT tr.*, p.title AS product_title, c.title AS category_title
         FROM test_results tr
         LEFT JOIN products p ON p.id = tr.product_id
         LEFT JOIN product_categories c ON c.id = tr.category_id
         WHERE tr.test_id = :test_id
         ORDER BY tr.sort_order, tr.min_score, tr.id'
    );
    $stmt->execute(['test_id' => $testId]);
    return $stmt->fetchAll();
}

function handle_test_builder_action(string $postAction, int $testId, array $admin, array &$errors): bool
{
    if (!str_starts_with($postAction, 'test_')) {
        return false;
    }

    try {
        if ($postAction === 'test_add_scale') {
            $title = trim((string)($_POST['scale_title'] ?? ''));
            if ($title === '') {
                $errors[] = 'Введите название шкалы.';
                return true;
            }

            $slug = test_builder_scale_slug((string)($_POST['scale_slug'] ?? ''), 'scale-' . time());
            $stmt = db()->prepare(
                'INSERT INTO test_scales (test_id, slug, title, description, sort_order)
                 VALUES (:test_id, :slug, :title, :description, :sort_order)'
            );
            $stmt->execute([
                'test_id' => $testId,
                'slug' => $slug,
                'title' => $title,
                'description' => trim((string)($_POST['scale_description'] ?? '')) ?: null,
                'sort_order' => (int)($_POST['sort_order'] ?? 100),
            ]);
            log_activity('admin', (int)$admin['id'], 'create_test_scale', 'test_scales', (int)db()->lastInsertId(), ['test_id' => $testId]);
            return true;
        }

        if ($postAction === 'test_update_scale') {
            $scaleId = (int)($_POST['scale_id'] ?? 0);
            $title = trim((string)($_POST['scale_title'] ?? ''));
            if ($scaleId <= 0 || $title === '') {
                $errors[] = 'Введите название шкалы.';
                return true;
            }

            $slug = test_builder_scale_slug((string)($_POST['scale_slug'] ?? ''), 'scale-' . $scaleId);
            $stmt = db()->prepare(
                'UPDATE test_scales
                 SET slug = :slug,
                     title = :title,
                     description = :description,
                     sort_order = :sort_order
                 WHERE id = :id AND test_id = :test_id'
            );
            $stmt->execute([
                'slug' => $slug,
                'title' => $title,
                'description' => trim((string)($_POST['scale_description'] ?? '')) ?: null,
                'sort_order' => (int)($_POST['sort_order'] ?? 100),
                'id' => $scaleId,
                'test_id' => $testId,
            ]);
            log_activity('admin', (int)$admin['id'], 'update_test_scale', 'test_scales', $scaleId, ['test_id' => $testId]);
            return true;
        }

        if ($postAction === 'test_delete_scale') {
            $scaleId = (int)($_POST['scale_id'] ?? 0);
            $stmt = db()->prepare('DELETE FROM test_scales WHERE id = :id AND test_id = :test_id');
            $stmt->execute(['id' => $scaleId, 'test_id' => $testId]);
            log_activity('admin', (int)$admin['id'], 'delete_test_scale', 'test_scales', $scaleId, ['test_id' => $testId]);
            return true;
        }

        if ($postAction === 'test_add_scale_result') {
            $scaleId = (int)($_POST['scale_id'] ?? 0);
            $title = trim((string)($_POST['scale_result_title'] ?? ''));
            if ($scaleId <= 0 || $title === '') {
                $errors[] = 'Введите название результата шкалы.';
                return true;
            }

            $minScore = (int)($_POST['min_score'] ?? 0);
            $maxScore = (int)($_POST['max_score'] ?? $minScore);
            if ($maxScore < $minScore) {
                $errors[] = app_text('test_builder.max_score_error');
                return true;
            }

            $severity = (string)($_POST['severity'] ?? 'good');
            if (!in_array($severity, ['excellent', 'good', 'risk', 'critical'], true)) {
                $severity = 'good';
            }

            $stmt = db()->prepare(
                'INSERT INTO test_scale_results
                    (scale_id, title, min_score, max_score, severity, summary_text, advice_text, sort_order)
                 SELECT :scale_id, :title, :min_score, :max_score, :severity, :summary_text, :advice_text, :sort_order
                 FROM test_scales
                 WHERE id = :scale_id_check AND test_id = :test_id'
            );
            $stmt->execute([
                'scale_id' => $scaleId,
                'title' => $title,
                'min_score' => $minScore,
                'max_score' => $maxScore,
                'severity' => $severity,
                'summary_text' => trim((string)($_POST['summary_text'] ?? '')) ?: null,
                'advice_text' => trim((string)($_POST['advice_text'] ?? '')) ?: null,
                'sort_order' => (int)($_POST['sort_order'] ?? 100),
                'scale_id_check' => $scaleId,
                'test_id' => $testId,
            ]);
            log_activity('admin', (int)$admin['id'], 'create_test_scale_result', 'test_scale_results', (int)db()->lastInsertId(), ['test_id' => $testId]);
            return true;
        }

        if ($postAction === 'test_update_scale_result') {
            $resultId = (int)($_POST['scale_result_id'] ?? 0);
            $title = trim((string)($_POST['scale_result_title'] ?? ''));
            if ($resultId <= 0 || $title === '') {
                $errors[] = 'Введите название результата шкалы.';
                return true;
            }

            $minScore = (int)($_POST['min_score'] ?? 0);
            $maxScore = (int)($_POST['max_score'] ?? $minScore);
            if ($maxScore < $minScore) {
                $errors[] = app_text('test_builder.max_score_error');
                return true;
            }

            $severity = (string)($_POST['severity'] ?? 'good');
            if (!in_array($severity, ['excellent', 'good', 'risk', 'critical'], true)) {
                $severity = 'good';
            }

            $stmt = db()->prepare(
                'UPDATE test_scale_results tr
                 INNER JOIN test_scales ts ON ts.id = tr.scale_id
                 SET tr.title = :title,
                     tr.min_score = :min_score,
                     tr.max_score = :max_score,
                     tr.severity = :severity,
                     tr.summary_text = :summary_text,
                     tr.advice_text = :advice_text,
                     tr.sort_order = :sort_order
                 WHERE tr.id = :id AND ts.test_id = :test_id'
            );
            $stmt->execute([
                'title' => $title,
                'min_score' => $minScore,
                'max_score' => $maxScore,
                'severity' => $severity,
                'summary_text' => trim((string)($_POST['summary_text'] ?? '')) ?: null,
                'advice_text' => trim((string)($_POST['advice_text'] ?? '')) ?: null,
                'sort_order' => (int)($_POST['sort_order'] ?? 100),
                'id' => $resultId,
                'test_id' => $testId,
            ]);
            log_activity('admin', (int)$admin['id'], 'update_test_scale_result', 'test_scale_results', $resultId, ['test_id' => $testId]);
            return true;
        }

        if ($postAction === 'test_delete_scale_result') {
            $resultId = (int)($_POST['scale_result_id'] ?? 0);
            $stmt = db()->prepare(
                'DELETE tr FROM test_scale_results tr
                 INNER JOIN test_scales ts ON ts.id = tr.scale_id
                 WHERE tr.id = :id AND ts.test_id = :test_id'
            );
            $stmt->execute(['id' => $resultId, 'test_id' => $testId]);
            log_activity('admin', (int)$admin['id'], 'delete_test_scale_result', 'test_scale_results', $resultId, ['test_id' => $testId]);
            return true;
        }

        if ($postAction === 'test_add_question') {
            $questionText = trim((string)($_POST['question_text'] ?? ''));
            if ($questionText === '') {
                $errors[] = app_text('test_builder.enter_question_text');
                return true;
            }

            $type = (string)($_POST['question_type'] ?? 'single_choice');
            if (!in_array($type, ['single_choice', 'multiple_choice', 'text'], true)) {
                $type = 'single_choice';
            }

            $stmt = db()->prepare(
                'INSERT INTO test_questions (test_id, question_text, question_type, is_required, sort_order)
                 VALUES (:test_id, :question_text, :question_type, :is_required, :sort_order)'
            );
            $stmt->execute([
                'test_id' => $testId,
                'question_text' => $questionText,
                'question_type' => $type,
                'is_required' => isset($_POST['is_required']) ? 1 : 0,
                'sort_order' => (int)($_POST['sort_order'] ?? 100),
            ]);
            log_activity('admin', (int)$admin['id'], 'create_test_question', 'test_questions', (int)db()->lastInsertId(), ['test_id' => $testId]);
            return true;
        }

        if ($postAction === 'test_delete_question') {
            $questionId = (int)($_POST['question_id'] ?? 0);
            $stmt = db()->prepare('DELETE FROM test_questions WHERE id = :id AND test_id = :test_id');
            $stmt->execute(['id' => $questionId, 'test_id' => $testId]);
            log_activity('admin', (int)$admin['id'], 'delete_test_question', 'test_questions', $questionId, ['test_id' => $testId]);
            return true;
        }

        if ($postAction === 'test_update_question') {
            $questionId = (int)($_POST['question_id'] ?? 0);
            $questionText = trim((string)($_POST['question_text'] ?? ''));
            if ($questionId <= 0 || $questionText === '') {
                $errors[] = app_text('test_builder.enter_question_text');
                return true;
            }

            $type = (string)($_POST['question_type'] ?? 'single_choice');
            if (!in_array($type, ['single_choice', 'multiple_choice', 'text'], true)) {
                $type = 'single_choice';
            }

            $stmt = db()->prepare(
                'UPDATE test_questions
                 SET question_text = :question_text,
                     question_type = :question_type,
                     is_required = :is_required,
                     sort_order = :sort_order
                 WHERE id = :id AND test_id = :test_id'
            );
            $stmt->execute([
                'question_text' => $questionText,
                'question_type' => $type,
                'is_required' => isset($_POST['is_required']) ? 1 : 0,
                'sort_order' => (int)($_POST['sort_order'] ?? 100),
                'id' => $questionId,
                'test_id' => $testId,
            ]);
            log_activity('admin', (int)$admin['id'], 'update_test_question', 'test_questions', $questionId, ['test_id' => $testId]);
            return true;
        }

        if ($postAction === 'test_add_answer') {
            $questionId = (int)($_POST['question_id'] ?? 0);
            $answerText = trim((string)($_POST['answer_text'] ?? ''));
            if ($questionId <= 0 || $answerText === '') {
                $errors[] = app_text('test_builder.enter_answer_option');
                return true;
            }

            $check = db()->prepare('SELECT COUNT(*) FROM test_questions WHERE id = :id AND test_id = :test_id');
            $check->execute(['id' => $questionId, 'test_id' => $testId]);
            if ((int)$check->fetchColumn() === 0) {
                $errors[] = app_text('test_builder.question_not_found');
                return true;
            }

            $stmt = db()->prepare(
                'INSERT INTO test_answers (question_id, answer_text, score, category_id, product_id, sort_order)
                 VALUES (:question_id, :answer_text, :score, :category_id, :product_id, :sort_order)'
            );
            $stmt->execute([
                'question_id' => $questionId,
                'answer_text' => $answerText,
                'score' => (int)($_POST['score'] ?? 0),
                'category_id' => ($_POST['category_id'] ?? '') !== '' ? (int)$_POST['category_id'] : null,
                'product_id' => ($_POST['product_id'] ?? '') !== '' ? (int)$_POST['product_id'] : null,
                'sort_order' => (int)($_POST['sort_order'] ?? 100),
            ]);
            $answerId = (int)db()->lastInsertId();
            if (isset($_POST['scale_ids']) && is_array($_POST['scale_ids'])) {
                test_builder_save_answer_scales($answerId, $testId, $_POST['scale_ids']);
            }
            log_activity('admin', (int)$admin['id'], 'create_test_answer', 'test_answers', $answerId, ['test_id' => $testId]);
            return true;
        }

        if ($postAction === 'test_delete_answer') {
            $answerId = (int)($_POST['answer_id'] ?? 0);
            $stmt = db()->prepare(
                'DELETE a FROM test_answers a
                 INNER JOIN test_questions q ON q.id = a.question_id
                 WHERE a.id = :id AND q.test_id = :test_id'
            );
            $stmt->execute(['id' => $answerId, 'test_id' => $testId]);
            log_activity('admin', (int)$admin['id'], 'delete_test_answer', 'test_answers', $answerId, ['test_id' => $testId]);
            return true;
        }

        if ($postAction === 'test_update_answer') {
            $answerId = (int)($_POST['answer_id'] ?? 0);
            $answerText = trim((string)($_POST['answer_text'] ?? ''));
            if ($answerId <= 0 || $answerText === '') {
                $errors[] = app_text('test_builder.enter_answer_option');
                return true;
            }

            $stmt = db()->prepare(
                'UPDATE test_answers a
                 INNER JOIN test_questions q ON q.id = a.question_id
                 SET a.answer_text = :answer_text,
                     a.score = :score,
                     a.category_id = :category_id,
                     a.product_id = :product_id,
                     a.sort_order = :sort_order
                 WHERE a.id = :id AND q.test_id = :test_id'
            );
            $stmt->execute([
                'answer_text' => $answerText,
                'score' => (int)($_POST['score'] ?? 0),
                'category_id' => ($_POST['category_id'] ?? '') !== '' ? (int)$_POST['category_id'] : null,
                'product_id' => ($_POST['product_id'] ?? '') !== '' ? (int)$_POST['product_id'] : null,
                'sort_order' => (int)($_POST['sort_order'] ?? 100),
                'id' => $answerId,
                'test_id' => $testId,
            ]);
            if (isset($_POST['scale_scores_submitted'])) {
                test_builder_save_answer_scales($answerId, $testId, is_array($_POST['scale_ids'] ?? null) ? $_POST['scale_ids'] : []);
            }
            log_activity('admin', (int)$admin['id'], 'update_test_answer', 'test_answers', $answerId, ['test_id' => $testId]);
            return true;
        }

        if ($postAction === 'test_add_result') {
            $title = trim((string)($_POST['result_title'] ?? ''));
            if ($title === '') {
                $errors[] = app_text('test_builder.enter_result_title');
                return true;
            }

            $minScore = (int)($_POST['min_score'] ?? 0);
            $maxScore = (int)($_POST['max_score'] ?? $minScore);
            if ($maxScore < $minScore) {
                $errors[] = app_text('test_builder.max_score_error');
                return true;
            }

            $stmt = db()->prepare(
                'INSERT INTO test_results
                    (test_id, title, min_score, max_score, summary_text, advice_text, product_id, category_id, sort_order)
                 VALUES
                    (:test_id, :title, :min_score, :max_score, :summary_text, :advice_text, :product_id, :category_id, :sort_order)'
            );
            $stmt->execute([
                'test_id' => $testId,
                'title' => $title,
                'min_score' => $minScore,
                'max_score' => $maxScore,
                'summary_text' => trim((string)($_POST['summary_text'] ?? '')) ?: null,
                'advice_text' => trim((string)($_POST['advice_text'] ?? '')) ?: null,
                'product_id' => ($_POST['product_id'] ?? '') !== '' ? (int)$_POST['product_id'] : null,
                'category_id' => ($_POST['category_id'] ?? '') !== '' ? (int)$_POST['category_id'] : null,
                'sort_order' => (int)($_POST['sort_order'] ?? 100),
            ]);
            log_activity('admin', (int)$admin['id'], 'create_test_result', 'test_results', (int)db()->lastInsertId(), ['test_id' => $testId]);
            return true;
        }

        if ($postAction === 'test_delete_result') {
            $resultId = (int)($_POST['result_id'] ?? 0);
            $stmt = db()->prepare('DELETE FROM test_results WHERE id = :id AND test_id = :test_id');
            $stmt->execute(['id' => $resultId, 'test_id' => $testId]);
            log_activity('admin', (int)$admin['id'], 'delete_test_result', 'test_results', $resultId, ['test_id' => $testId]);
            return true;
        }

        if ($postAction === 'test_update_result') {
            $resultId = (int)($_POST['result_id'] ?? 0);
            $title = trim((string)($_POST['result_title'] ?? ''));
            if ($resultId <= 0 || $title === '') {
                $errors[] = app_text('test_builder.enter_result_title');
                return true;
            }

            $minScore = (int)($_POST['min_score'] ?? 0);
            $maxScore = (int)($_POST['max_score'] ?? $minScore);
            if ($maxScore < $minScore) {
                $errors[] = app_text('test_builder.max_score_error');
                return true;
            }

            $stmt = db()->prepare(
                'UPDATE test_results
                 SET title = :title,
                     min_score = :min_score,
                     max_score = :max_score,
                     summary_text = :summary_text,
                     advice_text = :advice_text,
                     product_id = :product_id,
                     category_id = :category_id,
                     sort_order = :sort_order
                 WHERE id = :id AND test_id = :test_id'
            );
            $stmt->execute([
                'title' => $title,
                'min_score' => $minScore,
                'max_score' => $maxScore,
                'summary_text' => trim((string)($_POST['summary_text'] ?? '')) ?: null,
                'advice_text' => trim((string)($_POST['advice_text'] ?? '')) ?: null,
                'product_id' => ($_POST['product_id'] ?? '') !== '' ? (int)$_POST['product_id'] : null,
                'category_id' => ($_POST['category_id'] ?? '') !== '' ? (int)$_POST['category_id'] : null,
                'sort_order' => (int)($_POST['sort_order'] ?? 100),
                'id' => $resultId,
                'test_id' => $testId,
            ]);
            log_activity('admin', (int)$admin['id'], 'update_test_result', 'test_results', $resultId, ['test_id' => $testId]);
            return true;
        }
    } catch (Throwable $e) {
        $errors[] = app_text('test_builder.update_error', ['error' => $e->getMessage()]);
        return true;
    }

    return false;
}

function test_builder_scoring_type_label(string $type): string
{
    return $type === 'multiscale' ? 'Многошкальная матрица' : 'Обычный тест';
}

function test_builder_severity_label(string $severity): string
{
    return [
        'excellent' => 'Очень хорошо',
        'good' => 'Хорошо',
        'risk' => 'Зона риска',
        'critical' => 'Требует внимания',
    ][$severity] ?? $severity;
}

function test_builder_scale_checkbox_html(array $scales, array $selectedScaleIds): string
{
    if (!$scales) {
        return '<div class="empty-state">Сначала добавьте шкалы результата.</div>';
    }

    $selected = array_flip(array_map('intval', $selectedScaleIds));
    $html = '<input type="hidden" name="scale_scores_submitted" value="1">';
    $html .= '<fieldset class="scale-picker"><legend>Матрица шкал: этот ответ добавляет +1</legend>';
    foreach ($scales as $scale) {
        $scaleId = (int)$scale['id'];
        $checked = isset($selected[$scaleId]) ? ' checked' : '';
        $html .= '<label><input type="checkbox" name="scale_ids[]" value="' . $scaleId . '"' . $checked . '> ' . h((string)$scale['title']) . '</label>';
    }
    return $html . '</fieldset>';
}

function render_test_scale_builder(int $testId, array $scales): string
{
    ob_start();
    ?>
    <section class="panel form-panel test-builder matrix-builder">
        <div class="builder-intro">
            <div>
                <h2>Шкалы многошкальной матрицы</h2>
                <p>Здесь задаются направления результата. Ответы ниже можно привязать к одной или нескольким шкалам.</p>
            </div>
            <div class="builder-stats">
                <span>Шкалы: <strong><?= count($scales) ?></strong></span>
            </div>
        </div>

        <form method="post" class="inline-grid-form scale-form">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="test_add_scale">
            <input type="hidden" name="id" value="<?= (int)$testId ?>">
            <label class="field"><span>Название шкалы</span><input name="scale_title" required placeholder="Например: Нервная система"></label>
            <label class="field"><span>Код</span><input name="scale_slug" placeholder="nervous"></label>
            <label class="field wide-field"><span>Описание</span><input name="scale_description"></label>
            <label class="field compact-field"><span>Сорт.</span><input type="number" name="sort_order" value="100"></label>
            <div class="form-actions"><button type="submit">Добавить шкалу</button></div>
        </form>

        <?php if (!$scales): ?>
            <div class="empty-state">Шкал пока нет. Добавьте первую шкалу, затем привяжите к ней ответы.</div>
        <?php endif; ?>

        <div class="scale-card-list">
        <?php foreach ($scales as $scale): ?>
            <article class="scale-card">
                <form method="post" class="scale-edit-form">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="test_update_scale">
                    <input type="hidden" name="id" value="<?= (int)$testId ?>">
                    <input type="hidden" name="scale_id" value="<?= (int)$scale['id'] ?>">
                    <label class="field"><span>Название</span><input name="scale_title" value="<?= h((string)$scale['title']) ?>" required></label>
                    <label class="field"><span>Код</span><input name="scale_slug" value="<?= h((string)$scale['slug']) ?>" required></label>
                    <label class="field wide-field"><span>Описание</span><input name="scale_description" value="<?= h((string)($scale['description'] ?? '')) ?>"></label>
                    <label class="field compact-field"><span>Сорт.</span><input type="number" name="sort_order" value="<?= (int)$scale['sort_order'] ?>"></label>
                    <div class="form-actions"><button type="submit" class="secondary-button"><?= h(app_text('auto.k_4864057d626a')) ?></button></div>
                </form>
                <form method="post" class="delete-row-form" onsubmit="return confirm('Удалить шкалу и все её диапазоны результата? Связи ответов с этой шкалой тоже будут удалены.');">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="test_delete_scale">
                    <input type="hidden" name="id" value="<?= (int)$testId ?>">
                    <input type="hidden" name="scale_id" value="<?= (int)$scale['id'] ?>">
                    <button type="submit" class="link-button danger">Удалить шкалу</button>
                </form>

                <div class="scale-results-editor">
                    <h3>Диапазоны результата</h3>
                    <form method="post" class="scale-result-form">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="action" value="test_add_scale_result">
                        <input type="hidden" name="id" value="<?= (int)$testId ?>">
                        <input type="hidden" name="scale_id" value="<?= (int)$scale['id'] ?>">
                        <label class="field"><span>Название</span><input name="scale_result_title" required></label>
                        <label class="field compact-field"><span>От</span><input type="number" name="min_score" value="0"></label>
                        <label class="field compact-field"><span>До</span><input type="number" name="max_score" value="2"></label>
                        <label class="field"><span>Уровень</span><select name="severity">
                            <?php foreach (['excellent', 'good', 'risk', 'critical'] as $severity): ?>
                                <option value="<?= h($severity) ?>"><?= h(test_builder_severity_label($severity)) ?></option>
                            <?php endforeach; ?>
                        </select></label>
                        <label class="field wide-field"><span>Краткий итог</span><textarea name="summary_text" rows="2"></textarea></label>
                        <label class="field wide-field"><span>Совет пользователю</span><textarea name="advice_text" rows="2"></textarea></label>
                        <label class="field compact-field"><span>Сорт.</span><input type="number" name="sort_order" value="100"></label>
                        <div class="form-actions"><button type="submit">Добавить диапазон</button></div>
                    </form>

                    <?php if (!empty($scale['results'])): ?>
                        <div class="scale-result-list">
                        <?php foreach ($scale['results'] as $result): ?>
                            <article class="scale-result-card severity-<?= h((string)$result['severity']) ?>">
                                <form method="post" class="scale-result-edit-form">
                                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="test_update_scale_result">
                                    <input type="hidden" name="id" value="<?= (int)$testId ?>">
                                    <input type="hidden" name="scale_result_id" value="<?= (int)$result['id'] ?>">
                                    <label class="field"><span>Название</span><input name="scale_result_title" value="<?= h((string)$result['title']) ?>" required></label>
                                    <label class="field compact-field"><span>От</span><input type="number" name="min_score" value="<?= (int)$result['min_score'] ?>"></label>
                                    <label class="field compact-field"><span>До</span><input type="number" name="max_score" value="<?= (int)$result['max_score'] ?>"></label>
                                    <label class="field"><span>Уровень</span><select name="severity">
                                        <?php foreach (['excellent', 'good', 'risk', 'critical'] as $severity): ?>
                                            <option value="<?= h($severity) ?>" <?= $result['severity'] === $severity ? 'selected' : '' ?>><?= h(test_builder_severity_label($severity)) ?></option>
                                        <?php endforeach; ?>
                                    </select></label>
                                    <label class="field wide-field"><span>Краткий итог</span><textarea name="summary_text" rows="2"><?= h((string)($result['summary_text'] ?? '')) ?></textarea></label>
                                    <label class="field wide-field"><span>Совет</span><textarea name="advice_text" rows="2"><?= h((string)($result['advice_text'] ?? '')) ?></textarea></label>
                                    <label class="field compact-field"><span>Сорт.</span><input type="number" name="sort_order" value="<?= (int)$result['sort_order'] ?>"></label>
                                    <div class="form-actions"><button type="submit" class="secondary-button"><?= h(app_text('auto.k_4864057d626a')) ?></button></div>
                                </form>
                                <form method="post" onsubmit="return confirm('Удалить диапазон результата?');">
                                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="test_delete_scale_result">
                                    <input type="hidden" name="id" value="<?= (int)$testId ?>">
                                    <input type="hidden" name="scale_result_id" value="<?= (int)$result['id'] ?>">
                                    <button type="submit" class="link-button danger">Удалить диапазон</button>
                                </form>
                            </article>
                        <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">Диапазонов пока нет. Без них пользователь увидит баллы, но не увидит уровень по шкале.</div>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
        </div>
    </section>
    <?php
    return trim(ob_get_clean());
}

function render_test_matrix_preview(array $questions, array $scales): string
{
    if (!$scales || !$questions) {
        return '';
    }

    ob_start();
    ?>
    <section class="panel form-panel matrix-preview">
        <div class="builder-intro">
            <div>
                <h2>Визуальная матрица</h2>
                <p>Галочка показывает, что выбранный ответ добавляет +1 в соответствующую шкалу.</p>
            </div>
        </div>
        <table class="matrix-table">
            <thead>
                <tr>
                    <th>Вопрос / ответ</th>
                    <?php foreach ($scales as $scale): ?>
                        <th title="<?= h((string)$scale['title']) ?>"><?= h((string)$scale['title']) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($questions as $question): ?>
                    <?php foreach (($question['answers'] ?? []) as $answerIndex => $answer): ?>
                        <?php $selected = array_flip(array_map('intval', $answer['scale_ids'] ?? [])); ?>
                        <tr>
                            <td>
                                <?php if ($answerIndex === 0): ?><strong><?= h((string)$question['question_text']) ?></strong><?php endif; ?>
                                <span><?= h((string)$answer['answer_text']) ?></span>
                            </td>
                            <?php foreach ($scales as $scale): ?>
                                <td class="<?= isset($selected[(int)$scale['id']]) ? 'matrix-on' : 'matrix-off' ?>"><?= isset($selected[(int)$scale['id']]) ? '✓' : '' ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <?php
    return trim(ob_get_clean());
}

function render_test_builder(int $testId): string
{
    $test = test_builder_test($testId);
    $isMultiscale = ($test['scoring_type'] ?? 'single') === 'multiscale';
    $questions = test_builder_questions($testId);
    $results = test_builder_results($testId);
    $scales = $isMultiscale ? test_builder_scales($testId) : [];
    $products = test_builder_options('products', 'title');
    $categories = test_builder_options('product_categories', 'title');
    $answersCount = array_sum(array_map(static fn(array $question): int => count($question['answers'] ?? []), $questions));

    ob_start();
    ?>
    <section class="panel form-panel test-builder test-type-panel">
        <div class="builder-intro">
            <div>
                <h2><?= h(test_builder_scoring_type_label((string)($test['scoring_type'] ?? 'single'))) ?></h2>
                <p><?= $isMultiscale
                    ? 'Этот тест считает результат по нескольким шкалам. Редактируйте шкалы и отмечайте, какие ответы добавляют баллы в каждую шкалу.'
                    : 'Этот тест считает один общий балл и подбирает результат по диапазону общей суммы.' ?></p>
            </div>
            <div class="builder-stats">
                <span>Тип: <strong><?= h(test_builder_scoring_type_label((string)($test['scoring_type'] ?? 'single'))) ?></strong></span>
                <?php if ($isMultiscale): ?><span>Шкалы: <strong><?= count($scales) ?></strong></span><?php endif; ?>
            </div>
        </div>
    </section>

    <?php if ($isMultiscale): ?>
        <?= render_test_scale_builder($testId, $scales) ?>
        <?= render_test_matrix_preview($questions, $scales) ?>
    <?php endif; ?>

    <section class="panel form-panel test-builder">
        <div class="builder-intro">
            <div>
                <h2><?= h(app_text('test_builder.questions_title')) ?></h2>
                <p><?= h(app_text('test_builder.constructor_hint')) ?></p>
            </div>
            <div class="builder-stats">
                <span><?= h(app_text('test_builder.questions_count')) ?>: <strong><?= count($questions) ?></strong></span>
                <span><?= h(app_text('test_builder.answers_count')) ?>: <strong><?= $answersCount ?></strong></span>
                <span><?= h(app_text('test_builder.results_count')) ?>: <strong><?= count($results) ?></strong></span>
                <?php if ($isMultiscale): ?><span>Матрица: <strong><?= count($scales) ?></strong></span><?php endif; ?>
            </div>
        </div>
        <form method="post" class="inline-grid-form">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="test_add_question">
            <input type="hidden" name="id" value="<?= (int)$testId ?>">
            <label class="field wide-field"><span><?= h(app_text('test_builder.question_text')) ?></span><input name="question_text" required placeholder="<?= h(app_text('test_builder.question_placeholder')) ?>"></label>
            <label class="field"><span><?= h(app_text('test_builder.type')) ?></span><select name="question_type"><option value="single_choice"><?= h(test_builder_question_type_label('single_choice')) ?></option><option value="multiple_choice"><?= h(test_builder_question_type_label('multiple_choice')) ?></option><option value="text"><?= h(test_builder_question_type_label('text')) ?></option></select></label>
            <label class="field compact-field"><span><?= h(app_text('test_builder.sort')) ?></span><input type="number" name="sort_order" value="100"></label>
            <label class="field checkbox-field"><span><?= h(app_text('test_builder.required')) ?></span><input type="checkbox" name="is_required" value="1" checked></label>
            <div class="form-actions"><button type="submit"><?= h(app_text('test_builder.add_question')) ?></button></div>
        </form>

        <?php if (!$questions): ?><div class="empty-state"><?= h(app_text('test_builder.no_questions')) ?></div><?php endif; ?>

        <div class="builder-list">
        <?php foreach ($questions as $question): ?>
            <article class="builder-card">
                <div class="builder-card-head">
                    <div>
                        <strong>#<?= (int)$question['id'] ?> <?= h($question['question_text']) ?></strong>
                        <span><?= h(test_builder_question_type_label($question['question_type'])) ?>, <?= h(app_text('test_builder.sort')) ?> <?= (int)$question['sort_order'] ?><?= (int)$question['is_required'] === 1 ? ', ' . h(app_text('test_builder.required')) : '' ?></span>
                    </div>
                    <form method="post" onsubmit="return confirm('<?= h(app_text('test_builder.delete_question_confirm')) ?>');">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="action" value="test_delete_question">
                        <input type="hidden" name="id" value="<?= (int)$testId ?>">
                        <input type="hidden" name="question_id" value="<?= (int)$question['id'] ?>">
                        <button type="submit" class="link-button danger"><?= h(app_text('test_builder.delete')) ?></button>
                    </form>
                </div>

                <form method="post" class="inline-grid-form question-edit-form">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="test_update_question">
                    <input type="hidden" name="id" value="<?= (int)$testId ?>">
                    <input type="hidden" name="question_id" value="<?= (int)$question['id'] ?>">
                    <label class="field wide-field"><span><?= h(app_text('test_builder.question_text')) ?></span><input name="question_text" value="<?= h((string)$question['question_text']) ?>" required></label>
                    <label class="field"><span><?= h(app_text('test_builder.type')) ?></span><select name="question_type">
                        <?php foreach (['single_choice', 'multiple_choice', 'text'] as $type): ?>
                            <option value="<?= h($type) ?>" <?= $question['question_type'] === $type ? 'selected' : '' ?>><?= h(test_builder_question_type_label($type)) ?></option>
                        <?php endforeach; ?>
                    </select></label>
                    <label class="field compact-field"><span><?= h(app_text('test_builder.sort')) ?></span><input type="number" name="sort_order" value="<?= (int)$question['sort_order'] ?>"></label>
                    <label class="field checkbox-field"><span><?= h(app_text('test_builder.required')) ?></span><input type="checkbox" name="is_required" value="1" <?= (int)$question['is_required'] === 1 ? 'checked' : '' ?>></label>
                    <div class="form-actions"><button type="submit" class="secondary-button"><?= h(app_text('auto.k_4864057d626a')) ?></button></div>
                </form>

                <?php if ($question['answers']): ?>
                    <div class="answer-editor-list">
                    <?php foreach ($question['answers'] as $answer): ?>
                        <form method="post" class="answer-editor-row">
                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                            <input type="hidden" name="action" value="test_update_answer">
                            <input type="hidden" name="id" value="<?= (int)$testId ?>">
                            <input type="hidden" name="answer_id" value="<?= (int)$answer['id'] ?>">
                            <label class="field"><span><?= h(app_text('test_builder.answer')) ?></span><input name="answer_text" value="<?= h((string)$answer['answer_text']) ?>" required></label>
                            <label class="field compact-field"><span><?= h(app_text('test_builder.score')) ?></span><input type="number" name="score" value="<?= (int)$answer['score'] ?>"></label>
                            <label class="field"><span><?= h(app_text('test_builder.category')) ?></span><?= test_builder_select('category_id', $categories, $answer['category_id'] ? (int)$answer['category_id'] : null) ?></label>
                            <label class="field"><span><?= h(app_text('test_builder.product')) ?></span><?= test_builder_select('product_id', $products, $answer['product_id'] ? (int)$answer['product_id'] : null) ?></label>
                            <label class="field compact-field"><span><?= h(app_text('test_builder.sort')) ?></span><input type="number" name="sort_order" value="<?= (int)$answer['sort_order'] ?>"></label>
                            <?php if ($isMultiscale): ?>
                                <?= test_builder_scale_checkbox_html($scales, $answer['scale_ids'] ?? []) ?>
                            <?php endif; ?>
                            <div class="row-actions">
                                <button type="submit" class="secondary-button"><?= h(app_text('auto.k_4864057d626a')) ?></button>
                            </div>
                        </form>
                        <form method="post" class="delete-row-form" onsubmit="return confirm('<?= h(app_text('test_builder.delete_answer_confirm')) ?>');">
                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                            <input type="hidden" name="action" value="test_delete_answer">
                            <input type="hidden" name="id" value="<?= (int)$testId ?>">
                            <input type="hidden" name="answer_id" value="<?= (int)$answer['id'] ?>">
                            <button type="submit" class="link-button danger"><?= h(app_text('test_builder.delete')) ?></button>
                        </form>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ($question['question_type'] !== 'text'): ?>
                    <form method="post" class="inline-grid-form answer-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="test_add_answer"><input type="hidden" name="id" value="<?= (int)$testId ?>"><input type="hidden" name="question_id" value="<?= (int)$question['id'] ?>"><label class="field wide-field"><span><?= h(app_text('test_builder.answer_option')) ?></span><input name="answer_text" required></label><label class="field compact-field"><span><?= h(app_text('test_builder.score')) ?></span><input type="number" name="score" value="0"></label><label class="field"><span><?= h(app_text('test_builder.category')) ?></span><?= test_builder_select('category_id', $categories) ?></label><label class="field"><span><?= h(app_text('test_builder.product')) ?></span><?= test_builder_select('product_id', $products) ?></label><label class="field compact-field"><span><?= h(app_text('test_builder.sort')) ?></span><input type="number" name="sort_order" value="100"></label><?php if ($isMultiscale): ?><?= test_builder_scale_checkbox_html($scales, []) ?><?php endif; ?><div class="form-actions"><button type="submit"><?= h(app_text('test_builder.add_answer')) ?></button></div></form>
                <?php else: ?>
                    <div class="empty-state"><?= h(app_text('test_builder.text_answer_note')) ?></div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
        </div>
    </section>

    <section class="panel form-panel test-builder">
        <h2><?= h(app_text('test_builder.score_results_title')) ?></h2>
        <form method="post" class="inline-grid-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="test_add_result"><input type="hidden" name="id" value="<?= (int)$testId ?>"><label class="field"><span><?= h(app_text('test_builder.result_title')) ?></span><input name="result_title" required></label><label class="field compact-field"><span><?= h(app_text('test_builder.from')) ?></span><input type="number" name="min_score" value="0"></label><label class="field compact-field"><span><?= h(app_text('test_builder.to')) ?></span><input type="number" name="max_score" value="10"></label><label class="field wide-field"><span><?= h(app_text('test_builder.short_summary')) ?></span><textarea name="summary_text" rows="2"></textarea></label><label class="field wide-field"><span><?= h(app_text('test_builder.advice_for_user')) ?></span><textarea name="advice_text" rows="3"></textarea></label><label class="field"><span><?= h(app_text('test_builder.category')) ?></span><?= test_builder_select('category_id', $categories) ?></label><label class="field"><span><?= h(app_text('test_builder.product')) ?></span><?= test_builder_select('product_id', $products) ?></label><label class="field compact-field"><span><?= h(app_text('test_builder.sort')) ?></span><input type="number" name="sort_order" value="100"></label><div class="form-actions"><button type="submit"><?= h(app_text('test_builder.add_result')) ?></button></div></form>
        <?php if ($results): ?>
            <div class="result-editor-list">
            <?php foreach ($results as $result): ?>
                <article class="result-editor-card">
                    <form method="post" class="result-edit-form">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="action" value="test_update_result">
                        <input type="hidden" name="id" value="<?= (int)$testId ?>">
                        <input type="hidden" name="result_id" value="<?= (int)$result['id'] ?>">
                        <label class="field"><span><?= h(app_text('test_builder.result_title')) ?></span><input name="result_title" value="<?= h((string)$result['title']) ?>" required></label>
                        <label class="field compact-field"><span><?= h(app_text('test_builder.from')) ?></span><input type="number" name="min_score" value="<?= (int)$result['min_score'] ?>"></label>
                        <label class="field compact-field"><span><?= h(app_text('test_builder.to')) ?></span><input type="number" name="max_score" value="<?= (int)$result['max_score'] ?>"></label>
                        <label class="field wide-field"><span><?= h(app_text('test_builder.short_summary')) ?></span><textarea name="summary_text" rows="2"><?= h((string)($result['summary_text'] ?? '')) ?></textarea></label>
                        <label class="field wide-field"><span><?= h(app_text('test_builder.advice_for_user')) ?></span><textarea name="advice_text" rows="3"><?= h((string)($result['advice_text'] ?? '')) ?></textarea></label>
                        <label class="field"><span><?= h(app_text('test_builder.category')) ?></span><?= test_builder_select('category_id', $categories, $result['category_id'] ? (int)$result['category_id'] : null) ?></label>
                        <label class="field"><span><?= h(app_text('test_builder.product')) ?></span><?= test_builder_select('product_id', $products, $result['product_id'] ? (int)$result['product_id'] : null) ?></label>
                        <label class="field compact-field"><span><?= h(app_text('test_builder.sort')) ?></span><input type="number" name="sort_order" value="<?= (int)$result['sort_order'] ?>"></label>
                        <div class="form-actions"><button type="submit" class="secondary-button"><?= h(app_text('auto.k_4864057d626a')) ?></button></div>
                    </form>
                    <form method="post" onsubmit="return confirm('<?= h(app_text('test_builder.delete_result_confirm')) ?>');">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="action" value="test_delete_result">
                        <input type="hidden" name="id" value="<?= (int)$testId ?>">
                        <input type="hidden" name="result_id" value="<?= (int)$result['id'] ?>">
                        <button type="submit" class="link-button danger"><?= h(app_text('test_builder.delete')) ?></button>
                    </form>
                </article>
            <?php endforeach; ?>
            </div>
        <?php else: ?><div class="empty-state"><?= h(app_text('test_builder.no_results')) ?></div><?php endif; ?>
    </section>
    <?php
    return trim(ob_get_clean());
}
