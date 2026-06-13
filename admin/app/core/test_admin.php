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
            log_activity('admin', (int)$admin['id'], 'create_test_answer', 'test_answers', (int)db()->lastInsertId(), ['test_id' => $testId]);
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

function render_test_builder(int $testId): string
{
    $questions = test_builder_questions($testId);
    $results = test_builder_results($testId);
    $products = test_builder_options('products', 'title');
    $categories = test_builder_options('product_categories', 'title');
    $answersCount = array_sum(array_map(static fn(array $question): int => count($question['answers'] ?? []), $questions));

    ob_start();
    ?>
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
                    <form method="post" class="inline-grid-form answer-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="test_add_answer"><input type="hidden" name="id" value="<?= (int)$testId ?>"><input type="hidden" name="question_id" value="<?= (int)$question['id'] ?>"><label class="field wide-field"><span><?= h(app_text('test_builder.answer_option')) ?></span><input name="answer_text" required></label><label class="field compact-field"><span><?= h(app_text('test_builder.score')) ?></span><input type="number" name="score" value="0"></label><label class="field"><span><?= h(app_text('test_builder.category')) ?></span><?= test_builder_select('category_id', $categories) ?></label><label class="field"><span><?= h(app_text('test_builder.product')) ?></span><?= test_builder_select('product_id', $products) ?></label><label class="field compact-field"><span><?= h(app_text('test_builder.sort')) ?></span><input type="number" name="sort_order" value="100"></label><div class="form-actions"><button type="submit"><?= h(app_text('test_builder.add_answer')) ?></button></div></form>
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
