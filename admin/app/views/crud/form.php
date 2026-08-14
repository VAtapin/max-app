<?php if (($action === 'create' || $action === 'edit') && !$leadChatOnly): ?>
    <section class="panel form-panel">
        <h2><?= h(crud_form_title($moduleKey, $action)) ?></h2>
        <form method="post" class="crud-form" enctype="multipart/form-data" <?= $limitCheckUrl !== '' ? 'data-limit-check-url="' . h($limitCheckUrl) . '"' : '' ?>>
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= h((string)($editRow['id'] ?? '')) ?>">
            <?php if ($limitCheckUrl !== ''): ?>
                <div class="limit-check-message" data-limit-check-message hidden></div>
            <?php endif; ?>
            <?php foreach ($formFields as $name => $field): ?>
                <?php
                $type = $field['type'] ?? 'text';
                $value = $editRow[$name] ?? ($field['default'] ?? '');
                if ($moduleKey === 'leads' && $action === 'edit' && $name === 'message') {
                    $leadAttachments = render_lead_attachments(
                        $editRow['attachments_json'] ?? null,
                        (string)($editRow['message'] ?? ''),
                        'lead-edit-attachments-list'
                    );
                    if ($leadAttachments !== '') {
                        echo '<div class="field lead-edit-attachments"><span>Вложения клиента</span>' . $leadAttachments . '</div>';
                    }
                    if (!empty($editRow['attachments_json'])) {
                        $value = lead_display_message((string)$value);
                    }
                }
                if ($moduleKey === 'site_templates' && in_array($name, ['profile_json', 'blocks_json'], true)) {
                    echo '<textarea name="' . h($name) . '" hidden>' . h((string)$value) . '</textarea>';
                    continue;
                }
                ?>
                <label class="field">
                    <span class="field-label-line">
                        <span><?= h($field['label'] ?? $name) ?><?= ($field['required'] ?? false) ? ' *' : '' ?></span>
                        <?php if (!empty($field['help']) && is_array($field['help'])): ?>
                            <button
                                type="button"
                                class="field-info-button"
                                aria-label="Показать подсказку"
                                data-image-preview
                                data-image-src="<?= h((string)($field['help']['image'] ?? '')) ?>"
                                data-image-title="<?= h((string)($field['help']['title'] ?? ($field['label'] ?? $name))) ?>"
                                data-image-caption="<?= h((string)($field['help']['text'] ?? '')) ?>"
                            >i</button>
                        <?php endif; ?>
                    </span>
                    <?php if ($type === 'textarea'): ?>
                        <textarea name="<?= h($name) ?>" rows="<?= max(3, (int)($field['rows'] ?? 4)) ?>" <?= !empty($field['readonly']) ? 'readonly' : '' ?>><?= h((string)$value) ?></textarea>
                    <?php elseif ($type === 'select'): ?>
                        <select name="<?= h($name) ?>">
                            <?php if ($field['nullable'] ?? false): ?>
                                <option value=""><?= h((string)($field['nullable_label'] ?? app_text('auto.k_24da5932344a'))) ?></option>
                            <?php endif; ?>
                            <?php if (isset($field['options'])): ?>
                                <?php foreach ($field['options'] as $option): ?>
                                    <option value="<?= h($option) ?>" <?= (string)$value === (string)$option ? 'selected' : '' ?>><?= h(form_option_label($name, $option)) ?></option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php foreach (safe_select_options($field['source'], $admin, $errors) as $option): ?>
                                    <option value="<?= (int)$option['id'] ?>" <?= (string)$value === (string)$option['id'] ? 'selected' : '' ?>>
                                        #<?= (int)$option['id'] ?> <?= h($option['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    <?php elseif ($type === 'file'): ?>
                        <?php if ($value): ?>
                            <div class="file-control">
                                <span class="cell-muted"><?= h(app_text('media.current_file')) ?></span>
                                <a class="file-link" href="<?= h((string)$value) ?>" target="_blank" rel="noopener"><?= h(app_text('media.open_file')) ?></a>
                            <?php if (($field['accept'] ?? '') === 'image/*'): ?>
                                <img class="file-preview" src="<?= h((string)$value) ?>" alt="">
                            <?php endif; ?>
                                <label class="checkbox-line file-remove">
                                    <input type="checkbox" name="remove_file[<?= h($name) ?>]" value="1">
                                    <?= h(app_text('media.remove_current_file')) ?>
                                </label>
                            </div>
                        <?php endif; ?>
                        <input type="hidden" name="<?= h($name) ?>_current" value="<?= h((string)$value) ?>">
                        <input type="file" name="<?= h($name) ?>" <?= isset($field['accept']) ? 'accept="' . h($field['accept']) . '"' : '' ?>>
                    <?php elseif ($type === 'checkbox'): ?>
                        <input type="checkbox" name="<?= h($name) ?>" value="1" <?= (int)$value === 1 ? 'checked' : '' ?>>
                    <?php else: ?>
                        <?php $inputValue = $type === 'datetime-local' ? datetime_for_input($value ? (string)$value : null) : (string)$value; ?>
                        <?php
                        $isLimitField = $moduleKey === 'resellers' && in_array($name, [
                            'direct_leader_limit',
                            'branch_leader_limit',
                            'direct_manager_limit',
                            'branch_manager_limit',
                            'per_child_manager_limit',
                        ], true);
                        $limitCap = $isLimitField ? ($limitFieldCaps[$name] ?? null) : null;
                        ?>
                        <input
                            type="<?= h($type) ?>"
                            name="<?= h($name) ?>"
                            value="<?= h($inputValue) ?>"
                            <?= isset($field['step']) ? 'step="' . h($field['step']) . '"' : '' ?>
                            <?= isset($field['min']) ? 'min="' . h((string)$field['min']) . '"' : '' ?>
                            <?= $limitCap ? 'max="' . h((string)$limitCap['max']) . '"' : '' ?>
                            <?= $isLimitField ? 'data-limit-field="' . h($name) . '"' : '' ?>
                            <?= $limitCap ? 'data-limit-max="' . h((string)$limitCap['max']) . '" data-limit-source="' . h((string)$limitCap['source']) . '"' : '' ?>
                            <?= !empty($field['readonly']) ? 'readonly' : '' ?>
                        >
                        <?php if ($isLimitField): ?>
                            <small class="limit-field-message" data-limit-field-message="<?= h($name) ?>" hidden></small>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if (!empty($field['hint'])): ?>
                        <small class="field-hint"><?= h((string)$field['hint']) ?></small>
                    <?php endif; ?>
                </label>
                <?php if ($moduleKey === 'site_templates' && $name === 'description'): ?>
                    <?php render_site_template_editor($editRow ?: null); ?>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if ($moduleKey === 'broadcasts'): ?>
                <section class="field wide broadcast-preview" id="broadcast-preview">
                    <span>Предварительный просмотр</span>
                    <strong id="broadcast-preview-title"><?= h((string)($editRow['title'] ?? 'Заголовок рассылки')) ?></strong>
                    <p id="broadcast-preview-text"><?= nl2br(h((string)($editRow['message_text'] ?? 'Текст сообщения'))) ?></p>
                    <div id="broadcast-preview-media"></div>
                </section>
                <script src="assets/js/crud.js?v=<?= (int)filemtime(__DIR__ . '/../../../public/assets/js/crud.js') ?>"></script>
            <?php endif; ?>
            <?php if ($canManageAdminAccess): ?>
                <fieldset class="field admin-access-group">
                    <legend><?= h(app_text('admin_access.title')) ?></legend>
                    <label class="field">
                        <span><?= h(app_text('admin_access.email')) ?></span>
                        <input type="email" name="admin_email" value="<?= h((string)($adminAccess['email'] ?? '')) ?>">
                    </label>
                    <label class="field">
                        <span><?= h(app_text('admin_access.password')) ?></span>
                        <input type="password" name="admin_password" autocomplete="new-password">
                    </label>
                    <?php if (!empty($adminAccess['id'])): ?>
                        <p class="cell-muted"><?= h(app_text('admin_access.password_hint')) ?></p>
                    <?php endif; ?>
                    <label class="checkbox-line">
                        <input type="checkbox" name="admin_is_active" value="1" <?= (int)($adminAccess['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                        <?= h(app_text('admin_access.active')) ?>
                    </label>
                </fieldset>
            <?php endif; ?>
            <div class="form-actions">
                <button type="submit"><?= h(app_text('auto.k_4864057d626a')) ?></button>
                <a class="button secondary-button" href="crud.php?module=<?= h($moduleKey) ?>"><?= h(app_text('auto.k_0ec753be8df9')) ?></a>
            </div>
        </form>
    </section>
<?php endif; ?>
