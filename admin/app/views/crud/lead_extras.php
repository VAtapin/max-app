<?php if ($moduleKey === 'leads' && $action === 'edit' && $editRow): ?>
        <section class="panel">
            <h2>Чат с клиентом</h2>
            <?php
            try {
                echo render_lead_conversation((int)($editRow['end_user_id'] ?? 0));
            } catch (Throwable $e) {
                echo '<div class="alert">Не удалось загрузить чат: ' . h($e->getMessage()) . '</div>';
            }
            ?>
        </section>
    <?php endif; ?>
    <?php if ($moduleKey === 'leads' && $action === 'edit' && $editRow): ?>
        <section class="panel form-panel">
            <h2><?= h(app_text('auto.k_e33268c4b97d')) ?></h2>
            <form method="post" class="crud-form" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="send_lead_response">
                <input type="hidden" name="id" value="<?= h((string)$editRow['id']) ?>">
                <?php if ($leadChatOnly): ?>
                    <input type="hidden" name="chat_only" value="1">
                <?php endif; ?>

                <label class="field">
                    <span><?= h(app_text('auto.k_a76a99a18c25')) ?></span>
                    <textarea name="response_text" rows="4" placeholder="<?= h(app_text('auto.response_placeholder')) ?>"></textarea>
                </label>

                <label class="field">
                    <span><?= h(app_text('auto.k_19114f713f60')) ?></span>
                    <select name="response_content_id">
                        <option value=""><?= h(app_text('auto.k_92250813ceb7')) ?></option>
                        <?php foreach (safe_select_options('content_posts', $admin, $errors) as $option): ?>
                            <option value="<?= (int)$option['id'] ?>">#<?= (int)$option['id'] ?> <?= h($option['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="field">
                    <span><?= h(app_text('auto.k_3e644b83e4f3')) ?></span>
                    <select name="response_test_id">
                        <option value=""><?= h(app_text('auto.k_92250813ceb7')) ?></option>
                        <?php foreach (safe_select_options('tests', $admin, $errors) as $option): ?>
                            <option value="<?= (int)$option['id'] ?>">#<?= (int)$option['id'] ?> <?= h($option['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="field">
                    <span><?= h(app_text('auto.k_4012dea6eccf')) ?></span>
                    <input type="file" name="response_attachments[]" accept="image/*,application/pdf,video/mp4,audio/*" multiple>
                </label>

                <label class="field">
                    <span><?= h(app_text('auto.k_e6877b1b589a')) ?></span>
                    <input type="url" name="response_external_url" placeholder="https://...">
                </label>

                <div class="form-actions">
                    <button type="submit"><?= h(app_text('auto.k_18523c1df9fa')) ?></button>
                </div>
            </form>
        </section>

        <?php if (!$leadChatOnly): ?>
        <section class="panel">
            <h2><?= h(app_text('auto.k_238615f19976')) ?></h2>
            <?php
            try {
                $responses = lead_response_history((int)$editRow['id']);
            } catch (Throwable $e) {
                $responses = [];
                echo app_text('auto.k_8646540328ff') . h($e->getMessage()) . '</div>';
            }
            ?>
            <?php if ($responses): ?>
                <div class="lead-response-timeline">
                    <?php foreach ($responses as $response): ?>
                        <?php
                        $attachments = lead_response_attachment_paths($response['attachment_path'] ?? null);
                        $status = status_label($response['status'] ?? 'pending');
                        $contentUrl = !empty($response['content_post_id']) ? 'crud.php?module=content&action=edit&id=' . (int)$response['content_post_id'] : '#';
                        $testUrl = !empty($response['test_id']) ? 'crud.php?module=tests&action=edit&id=' . (int)$response['test_id'] : '#';
                        ?>
                        <article class="lead-response-card">
                            <div class="lead-response-head">
                                <div>
                                    <strong><?= h(
                                        ($response['response_source'] ?? 'admin') === 'telegram'
                                            ? 'Ответ из Telegram'
                                            : ($response['admin_name'] ?? app_text('auto.k_1b93795b9768'))
                                    ) ?></strong>
                                    <span><?= h($response['created_at']) ?></span>
                                </div>
                                <span class="<?= h(status_badge_class($status)) ?>"><?= h($status) ?></span>
                            </div>

                            <?php if (trim((string)($response['message_text'] ?? '')) !== ''): ?>
                                <div class="lead-response-message"><?= nl2br(h($response['message_text'])) ?></div>
                            <?php endif; ?>

                            <?php if (($response['content_title'] ?? '') || ($response['test_title'] ?? '') || $attachments || ($response['external_url'] ?? '')): ?>
                                <div class="lead-response-resources">
                                    <?php if ($response['content_title'] ?? ''): ?>
                                        <a href="<?= h($contentUrl) ?>">
                                            <?= h(app_text('lead_response.open_material')) ?>: <?= h($response['content_title']) ?>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($response['test_title'] ?? ''): ?>
                                        <a href="<?= h($testUrl) ?>">
                                            <?= h(app_text('lead_response.pass_test')) ?>: <?= h($response['test_title']) ?>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($attachments): ?>
                                        <?= render_lead_file_attachments($attachments, 'lead-response-attachments') ?>
                                    <?php endif; ?>
                                    <?php if ($response['external_url'] ?? ''): ?>
                                        <a href="<?= h($response['external_url']) ?>" target="_blank" rel="noopener">
                                            <?= h(app_text('lead_response.open_link')) ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($response['error_message'] ?? ''): ?>
                                <div class="alert error"><?= h($response['error_message']) ?></div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state"><?= h(app_text('auto.k_06fe678de6fe')) ?></div>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    <?php endif; ?>
