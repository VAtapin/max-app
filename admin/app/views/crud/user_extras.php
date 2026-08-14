<?php if ($moduleKey === 'users' && $action === 'edit' && $editRow): ?>
        <?php $consentStatus = client_onboarding_status($editRow); ?>
        <section class="panel form-panel">
            <h2>Анкета и согласия</h2>
            <p><strong>Этап:</strong> <?= h(user_client_stage_label($editRow)) ?></p>
            <p><strong>Анкета:</strong> <?= $consentStatus['profile_complete'] ? 'заполнена' : 'не завершена' ?></p>
            <p><strong>Обязательные согласия:</strong> <?= $consentStatus['missing_consents'] ? 'не подтверждены полностью' : 'подтверждены' ?></p>
            <p><strong>Информационные рассылки:</strong> <?= $consentStatus['marketing_consent'] ? 'разрешены' : 'не разрешены' ?></p>
            <a
                class="button secondary-button"
                href="results.php?user_id=<?= (int)$editRow['id'] ?>"
                data-admin-modal-url="results.php?user_id=<?= (int)$editRow['id'] ?>&modal=1"
            >Результаты чек-апов</a>
        </section>

        <?php if (user_promotion_allowed($admin)): ?>
            <?php
            $promotionCanPromote = user_promotion_can_promote_row($editRow, $admin);
            $promotionName = user_promotion_full_name($editRow);
            $promotionReferralCode = user_promotion_unique_referral_code($editRow);
            $promotionPlatformIds = user_promotion_platform_identities($editRow);
            $promotionDefaultResellerId = nullable_int_value($editRow['reseller_id'] ?? null);
            if (!$promotionDefaultResellerId && ($admin['role'] ?? '') === 'reseller') {
                $promotionDefaultResellerId = (int)$admin['reseller_id'];
            }
            try {
                $promotionExistingStaff = user_promotion_existing_staff_candidates($editRow, $admin);
            } catch (Throwable $e) {
                $promotionExistingStaff = [];
            }
            try {
                $promotionResellers = team_reseller_options_for_admin($admin, true);
            } catch (Throwable $e) {
                $promotionResellers = [];
            }
            try {
                $promotionTemplates = site_template_options($admin);
            } catch (Throwable $e) {
                $promotionTemplates = [];
            }
            ?>
            <section class="panel form-panel">
                <h2>Сделать рабочим аккаунтом</h2>
                <?php if (!$promotionCanPromote): ?>
                    <div class="empty-state">Этот клиент сейчас недоступен для преобразования.</div>
                <?php else: ?>
                    <p class="cell-muted">
                        Если клиент стал консультантом или лидером, создайте ему рабочий аккаунт или свяжите клиента с уже созданной записью.
                        После этого он будет видеть свою страницу, получит доступ в админку и появится в нужной таблице.
                    </p>

                    <?php if ($promotionExistingStaff): ?>
                        <form
                            method="post"
                            class="crud-form"
                            onsubmit="return confirm('Связать клиента с выбранным рабочим аккаунтом?');"
                        >
                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                            <input type="hidden" name="action" value="link_staff_user">
                            <input type="hidden" name="id" value="<?= h((string)$editRow['id']) ?>">
                            <label class="field">
                                <span>Уже созданный консультант или лидер</span>
                                <select name="existing_staff_ref" required>
                                    <option value="">Выберите запись</option>
                                    <?php foreach ($promotionExistingStaff as $staffCandidate): ?>
                                        <option value="<?= h((string)$staffCandidate['key']) ?>">
                                            <?= h((string)$staffCandidate['label']) ?>
                                            <?php if (!empty($staffCandidate['details'])): ?>
                                                - <?= h((string)$staffCandidate['details']) ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="field">
                                <span>Email для входа, если доступа ещё нет</span>
                                <input type="email" name="link_admin_email" value="<?= h((string)($editRow['email'] ?? '')) ?>">
                            </label>
                            <label class="field">
                                <span>Пароль, если доступа ещё нет</span>
                                <input type="password" name="link_admin_password" autocomplete="new-password">
                            </label>
                            <div class="form-actions">
                                <button type="submit" class="secondary-button">Связать с существующим</button>
                            </div>
                        </form>
                    <?php endif; ?>

                    <form
                        method="post"
                        class="crud-form"
                        onsubmit="return confirm('Создать рабочий аккаунт из этого клиента?');"
                    >
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="action" value="promote_user">
                        <input type="hidden" name="id" value="<?= h((string)$editRow['id']) ?>">
                        <label class="field">
                            <span>Кем сделать клиента</span>
                            <select name="promotion_target" required>
                                <option value="manager">Консультантом</option>
                                <option value="reseller">Лидером</option>
                            </select>
                        </label>

                        <?php if (($admin['role'] ?? '') === 'superadmin'): ?>
                            <label class="field">
                                <span>Лидер для консультанта</span>
                                <select name="promotion_reseller_id">
                                    <option value="">Выберите лидера</option>
                                    <?php foreach ($promotionResellers as $resellerOption): ?>
                                        <option
                                            value="<?= (int)$resellerOption['id'] ?>"
                                            <?= $promotionDefaultResellerId === (int)$resellerOption['id'] ? 'selected' : '' ?>
                                        ><?= h((string)$resellerOption['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="field">
                                <span>Вышестоящий лидер для нового лидера</span>
                                <select name="promotion_parent_reseller_id">
                                    <option value="">Без вышестоящего лидера</option>
                                    <?php foreach ($promotionResellers as $resellerOption): ?>
                                        <option
                                            value="<?= (int)$resellerOption['id'] ?>"
                                            <?= $promotionDefaultResellerId === (int)$resellerOption['id'] ? 'selected' : '' ?>
                                        ><?= h((string)$resellerOption['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        <?php else: ?>
                            <p class="cell-muted">Новый консультант или лидер будет создан в вашей ветке.</p>
                        <?php endif; ?>

                        <label class="field">
                            <span>Шаблон мини-сайта</span>
                            <select name="promotion_template_id">
                                <option value="">Как у вышестоящего лидера</option>
                                <?php foreach ($promotionTemplates as $templateOption): ?>
                                    <option value="<?= (int)$templateOption['id'] ?>"><?= h((string)$templateOption['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="field">
                            <span>Имя в системе</span>
                            <input type="text" name="promotion_name" value="<?= h($promotionName) ?>" required>
                        </label>
                        <label class="field">
                            <span>Email для входа в админку</span>
                            <input type="email" name="admin_email" value="<?= h((string)($editRow['email'] ?? '')) ?>" required>
                        </label>
                        <label class="field">
                            <span>Телефон</span>
                            <input type="text" name="admin_phone" value="<?= h((string)($editRow['phone'] ?? '')) ?>">
                        </label>
                        <label class="field">
                            <span>Реферальный код</span>
                            <input type="text" name="promotion_referral_code" value="<?= h($promotionReferralCode) ?>" required>
                        </label>
                        <label class="field">
                            <span>Пароль для входа в админку</span>
                            <input type="password" name="admin_password" autocomplete="new-password" required>
                        </label>
                        <label class="checkbox-line">
                            <input type="checkbox" name="admin_is_active" value="1" checked>
                            Доступ в админку активен
                        </label>
                        <?php if (array_filter($promotionPlatformIds)): ?>
                            <div class="compact-lines">
                                <strong>Платформы, которые будут перенесены</strong>
                                <?php foreach (['telegram_id' => 'Telegram', 'vk_id' => 'VK', 'max_id' => 'MAX'] as $platformField => $platformLabel): ?>
                                    <?php if (!empty($promotionPlatformIds[$platformField])): ?>
                                        <span><?= h($platformLabel) ?>: <?= h((string)$promotionPlatformIds[$platformField]) ?></span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <div class="form-actions">
                            <button type="submit">Создать рабочий аккаунт</button>
                        </div>
                    </form>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="panel form-panel">
            <h2><?= h(app_text('user_platforms.title')) ?></h2>
            <?php $accounts = user_platform_accounts((int)$editRow['id']); ?>
            <?php if ($accounts): ?>
                <table class="data-table">
                    <thead>
                    <tr>
                        <th><?= h(app_text('auto.k_89009febe5c6')) ?></th>
                        <th>ID</th>
                        <th><?= h(app_text('user_platforms.profile')) ?></th>
                        <th>Username</th>
                        <th><?= h(app_text('user_platforms.created')) ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($accounts as $account): ?>
                        <tr>
                            <td><?= render_platform_badge((string)$account['platform']) ?></td>
                            <td><?= h((string)$account['platform_user_id']) ?></td>
                            <td><?= h(crud_cell_value('platform_accounts', 'platform_profile', $account)) ?></td>
                            <td><?= h((string)($account['username'] ?? '')) ?></td>
                            <td><?= h((string)$account['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state"><?= h(app_text('user_platforms.empty')) ?></div>
            <?php endif; ?>
        </section>

        <section class="panel form-panel">
            <h2><?= h(app_text('user_merge.title')) ?></h2>
            <p class="cell-muted"><?= h(app_text('user_merge.description')) ?></p>
            <form method="post" class="crud-form user-merge-form" data-user-merge-form onsubmit="return this.querySelector('[data-merge-user-id]').value !== '' &amp;&amp; confirm(<?= json_encode(app_text('user_merge.confirm'), JSON_UNESCAPED_UNICODE) ?>);">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="merge_user">
                <input type="hidden" name="id" value="<?= h((string)$editRow['id']) ?>">
                <div
                    class="merge-search"
                    data-user-merge-search
                    data-search-url="crud.php?module=users&amp;action=merge_search&amp;id=<?= (int)$editRow['id'] ?>"
                    data-loading="<?= h(app_text('user_merge.loading_suggestions')) ?>"
                    data-empty="<?= h(app_text('user_merge.empty_suggestions')) ?>"
                    data-selected="<?= h(app_text('user_merge.selected_user')) ?>"
                    data-choose-first="<?= h(app_text('user_merge.choose_first')) ?>"
                >
                    <input type="hidden" name="source_user_id" data-merge-user-id>
                    <label class="field">
                        <span><?= h(app_text('user_merge.source_user')) ?></span>
                        <input
                            type="search"
                            autocomplete="off"
                            placeholder="<?= h(app_text('user_merge.search_placeholder')) ?>"
                            data-merge-search-input
                        >
                    </label>
                    <div class="merge-selected" data-merge-selected hidden></div>
                    <div class="merge-suggestions" data-merge-suggestions>
                        <div class="empty-state"><?= h(app_text('user_merge.loading_suggestions')) ?></div>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="danger-button" data-merge-submit disabled><?= h(app_text('user_merge.submit')) ?></button>
                </div>
            </form>
        </section>
    <?php endif; ?>
