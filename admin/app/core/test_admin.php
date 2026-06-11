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
    $html = '<select name="' . h($name) . '"><option value="">Not selected</option>';
    foreach ($items as $item) {
        $id = (int)$item['id'];
        $isSelected = $selected !== null && $selected === $id ? ' selected' : '';
        $html .= '<option value="' . $id . '"' . $isSelected . '>#' . $id . ' ' . h($item['label']) . '</option>';
    }
    return $html . '</select>';
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
                $errors[] = 'Enter question text.';
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

        if ($postAction === 'test_add_answer') {
            $questionId = (int)($_POST['question_id'] ?? 0);
            $answerText = trim((string)($_POST['answer_text'] ?? ''));
            if ($questionId <= 0 || $answerText === '') {
                $errors[] = 'Enter answer option.';
                return true;
            }

            $check = db()->prepare('SELECT COUNT(*) FROM test_questions WHERE id = :id AND test_id = :test_id');
            $check->execute(['id' => $questionId, 'test_id' => $testId]);
            if ((int)$check->fetchColumn() === 0) {
                $errors[] = 'Question not found.';
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

        if ($postAction === 'test_add_result') {
            $title = trim((string)($_POST['result_title'] ?? ''));
            if ($title === '') {
                $errors[] = 'Enter result title.';
                return true;
            }

            $minScore = (int)($_POST['min_score'] ?? 0);
            $maxScore = (int)($_POST['max_score'] ?? $minScore);
            if ($maxScore < $minScore) {
                $errors[] = 'Max score cannot be lower than min score.';
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
    } catch (Throwable $e) {
        $errors[] = 'Could not update test builder: ' . $e->getMessage();
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

    ob_start();
    ?>
    <section class="panel form-panel test-builder">
        <h2>Questions and answer options</h2>
        <form method="post" class="inline-grid-form">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="test_add_question">
            <input type="hidden" name="id" value="<?= (int)$testId ?>">
            <label class="field wide-field"><span>Question text</span><input name="question_text" required></label>
            <label class="field"><span>Type</span><select name="question_type"><option value="single_choice">Single choice</option><option value="multiple_choice">Multiple choice</option><option value="text">Text answer</option></select></label>
            <label class="field compact-field"><span>Sort</span><input type="number" name="sort_order" value="100"></label>
            <label class="field checkbox-field"><span>Required</span><input type="checkbox" name="is_required" value="1" checked></label>
            <div class="form-actions"><button type="submit">Add question</button></div>
        </form>

        <?php if (!$questions): ?><div class="empty-state">No questions yet. Add the first question, then add answer options with scores.</div><?php endif; ?>

        <div class="builder-list">
        <?php foreach ($questions as $question): ?>
            <article class="builder-card">
                <div class="builder-card-head"><div><strong>#<?= (int)$question['id'] ?> <?= h($question['question_text']) ?></strong><span><?= h($question['question_type']) ?>, sort <?= (int)$question['sort_order'] ?></span></div><form method="post" onsubmit="return confirm('Delete question and all answers?');"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="test_delete_question"><input type="hidden" name="id" value="<?= (int)$testId ?>"><input type="hidden" name="question_id" value="<?= (int)$question['id'] ?>"><button type="submit" class="link-button danger">Delete</button></form></div>
                <?php if ($question['answers']): ?>
                    <table class="builder-table"><thead><tr><th>Answer</th><th>Score</th><th>Reaction</th><th></th></tr></thead><tbody>
                    <?php foreach ($question['answers'] as $answer): ?><tr><td><?= h($answer['answer_text']) ?></td><td><?= (int)$answer['score'] ?></td><td><?= h($answer['product_title'] ?: $answer['category_title'] ?: '-') ?></td><td><form method="post" onsubmit="return confirm('Delete answer option?');"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="test_delete_answer"><input type="hidden" name="id" value="<?= (int)$testId ?>"><input type="hidden" name="answer_id" value="<?= (int)$answer['id'] ?>"><button type="submit" class="link-button danger">Delete</button></form></td></tr><?php endforeach; ?>
                    </tbody></table>
                <?php endif; ?>
                <?php if ($question['question_type'] !== 'text'): ?>
                    <form method="post" class="inline-grid-form answer-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="test_add_answer"><input type="hidden" name="id" value="<?= (int)$testId ?>"><input type="hidden" name="question_id" value="<?= (int)$question['id'] ?>"><label class="field wide-field"><span>Answer option</span><input name="answer_text" required></label><label class="field compact-field"><span>Score</span><input type="number" name="score" value="0"></label><label class="field"><span>Category</span><?= test_builder_select('category_id', $categories) ?></label><label class="field"><span>Product</span><?= test_builder_select('product_id', $products) ?></label><label class="field compact-field"><span>Sort</span><input type="number" name="sort_order" value="100"></label><div class="form-actions"><button type="submit">Add answer</button></div></form>
                <?php else: ?>
                    <div class="empty-state">Text answers are saved in history, but scores are added only by answer options.</div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
        </div>
    </section>

    <section class="panel form-panel test-builder">
        <h2>Score results</h2>
        <form method="post" class="inline-grid-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="test_add_result"><input type="hidden" name="id" value="<?= (int)$testId ?>"><label class="field"><span>Result title</span><input name="result_title" required></label><label class="field compact-field"><span>From</span><input type="number" name="min_score" value="0"></label><label class="field compact-field"><span>To</span><input type="number" name="max_score" value="10"></label><label class="field wide-field"><span>Short summary</span><textarea name="summary_text" rows="2"></textarea></label><label class="field wide-field"><span>Advice for user</span><textarea name="advice_text" rows="3"></textarea></label><label class="field"><span>Category</span><?= test_builder_select('category_id', $categories) ?></label><label class="field"><span>Product</span><?= test_builder_select('product_id', $products) ?></label><label class="field compact-field"><span>Sort</span><input type="number" name="sort_order" value="100"></label><div class="form-actions"><button type="submit">Add result</button></div></form>
        <?php if ($results): ?>
            <table class="builder-table"><thead><tr><th>Score</th><th>Result</th><th>Advice/reaction</th><th></th></tr></thead><tbody>
            <?php foreach ($results as $result): ?><tr><td><?= (int)$result['min_score'] ?>-<?= (int)$result['max_score'] ?></td><td><strong><?= h($result['title']) ?></strong><br><?= nl2br(h($result['summary_text'] ?? '')) ?></td><td><?= nl2br(h($result['advice_text'] ?? '')) ?><br><?= h($result['product_title'] ?: $result['category_title'] ?: '') ?></td><td><form method="post" onsubmit="return confirm('Delete result?');"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="test_delete_result"><input type="hidden" name="id" value="<?= (int)$testId ?>"><input type="hidden" name="result_id" value="<?= (int)$result['id'] ?>"><button type="submit" class="link-button danger">Delete</button></form></td></tr><?php endforeach; ?>
            </tbody></table>
        <?php else: ?><div class="empty-state">No results yet. Without ranges, users receive a general summary and recommendations from selected answers.</div><?php endif; ?>
    </section>
    <?php
    return trim(ob_get_clean());
}
