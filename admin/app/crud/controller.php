<?php

$moduleKey = $_GET['module'] ?? 'users';
if (!isset($modules[$moduleKey]) || !can_manage($moduleKey, $admin)) {
    http_response_code(403);
    exit('Access denied');
}

$module = $modules[$moduleKey];
$title = $module['title'];


















































































































































































$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$errors = [];
$success = $_GET['success'] ?? null;
$editRow = null;
$createLimitErrors = create_limit_block_reasons($moduleKey, $admin);
$canCreate = crud_create_enabled($moduleKey) && !$createLimitErrors;
$canEdit = crud_edit_enabled($moduleKey);
$canDelete = crud_delete_enabled($moduleKey);
$formFields = crud_form_fields($moduleKey, $module['fields']);
if ($moduleKey === 'broadcasts') {
    $formFields = broadcast_form_fields_for_admin($formFields, $admin);
}
if (in_array($moduleKey, owned_modules(), true) && $admin['role'] !== 'superadmin') {
    unset($formFields['owner_type'], $formFields['owner_id']);
}
if ($moduleKey === 'integrations' && $admin['role'] !== 'superadmin') {
    unset($formFields['owner_type'], $formFields['owner_id']);
}
if ($moduleKey === 'resellers' && $admin['role'] === 'reseller') {
    unset($formFields['parent_reseller_id']);
}

if ($action === 'limit_check') {
    header('Content-Type: application/json; charset=utf-8');

    if (!in_array($moduleKey, ['managers', 'resellers'], true)) {
        echo json_encode(['ok' => true, 'errors' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        verify_csrf();
        $recordId = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
        $payload = collect_payload($formFields);
        $payload = normalize_module_payload($moduleKey, $payload);
        $payload = apply_role_defaults($moduleKey, $payload, $admin, $recordId);
        $limitPayload = $payload;
        if ($moduleKey === 'resellers') {
            $planErrors = subscription_plan_validate_reseller_payload($payload, $admin);
            if (!$planErrors) {
                $limitPayload = subscription_plan_apply_to_reseller_payload($payload, $admin);
            }
        }
        $limitErrors = array_merge(
            $planErrors ?? [],
            validate_child_limit_caps($moduleKey, $limitPayload, $recordId),
            validate_manager_limit_payload($moduleKey, $limitPayload, $recordId),
            validate_leader_limit_payload($moduleKey, $limitPayload, $recordId),
        );

        echo json_encode([
            'ok' => count($limitErrors) === 0,
            'errors' => array_values(array_unique($limitErrors)),
            'field_limits' => child_limit_field_caps($moduleKey, $payload, $recordId),
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'errors' => ['Не удалось проверить лимиты: ' . $e->getMessage()],
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($moduleKey === 'users' && $action === 'merge_search') {
    header('Content-Type: application/json; charset=utf-8');

    $targetUserId = (int)($_GET['id'] ?? 0);
    $query = trim((string)($_GET['q'] ?? ''));

    try {
        if (!$targetUserId) {
            http_response_code(404);
            echo json_encode(['items' => [], 'error' => app_text('user_merge.target_required')], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode(['items' => merge_user_search_results($targetUserId, $query, $admin)], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['items' => [], 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($action === 'create' && $createLimitErrors) {
    $errors[] = 'Лимит закончился. Новые записи этого типа сейчас нельзя добавить.';
    $errors = array_merge($errors, $createLimitErrors);
    $action = 'list';
} elseif ($action === 'create' && !$canCreate) {
    $errors[] = app_text('auto.k_868d1fd837c9');
    $action = 'list';
}

if ($action === 'edit' && !$canEdit) {
    $errors[] = app_text('auto.k_e26ff1144bac');
    $action = 'list';
}

$leadChatOnly = $moduleKey === 'leads'
    && $action === 'edit'
    && ((string)($_GET['chat_only'] ?? '') === '1' || (string)($_POST['chat_only'] ?? '') === '1');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $postAction = $_POST['action'] ?? 'save';
    $postId = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;

    if ($postAction === 'send_lead_response') {
        if ($moduleKey !== 'leads' || !$postId || !scoped_row_exists($moduleKey, $module, $postId, $admin)) {
            http_response_code(404);
            exit('Record not found');
        }

        try {
            $responseId = create_and_send_lead_response($postId, $admin, $errors);
        } catch (Throwable $e) {
            $responseId = null;
            $errors[] = app_text('auto.k_5cececf97899') . $e->getMessage();
        }
        if ($responseId && !$errors) {
            $sentPlatform = lead_response_platform($responseId);
            $sentPlatformQuery = $sentPlatform !== '' ? '&sent_platform=' . rawurlencode($sentPlatform) : '';
            $chatOnlyQuery = (string)($_POST['chat_only'] ?? '') === '1' ? '&chat_only=1' : '';
            redirect('crud.php?module=leads&action=edit&id=' . $postId . $chatOnlyQuery . '&success=response_sent' . $sentPlatformQuery);
        }
        $action = 'edit';
        $id = $postId;
    }

    if ($postAction === 'run_broadcast') {
        if ($moduleKey !== 'broadcasts' || !$postId || !scoped_row_exists($moduleKey, $module, $postId, $admin)) {
            http_response_code(404);
            exit('Record not found');
        }

        try {
            $postId = owned_content_editable_id('broadcasts', $postId, $admin);
            $result = run_broadcast($postId);
            redirect('crud.php?module=broadcasts&success=broadcast_sent&sent=' . (int)$result['sent'] . '&failed=' . (int)$result['failed']);
        } catch (Throwable $e) {
            $errors[] = app_text('broadcasts.run_failed') . $e->getMessage();
            $action = 'list';
        }
    }

    if ($moduleKey === 'tests' && $postId && str_starts_with($postAction, 'test_')) {
        if (!scoped_row_exists($moduleKey, $module, $postId, $admin)) {
            http_response_code(404);
            exit('Record not found');
        }
        $editablePostId = owned_content_editable_id('tests', $postId, $admin);
        if ($editablePostId !== $postId) {
            redirect('crud.php?module=tests&action=edit&id=' . $editablePostId . '&success=personal_copy');
        }
        handle_test_builder_action($postAction, $postId, $admin, $errors);
        if (!$errors) {
            redirect('crud.php?module=tests&action=edit&id=' . $postId . '&success=saved');
        }
        $action = 'edit';
        $id = $postId;
    }

    if ($postAction === 'merge_user') {
        if ($moduleKey !== 'users' || !$postId || !scoped_row_exists($moduleKey, $module, $postId, $admin)) {
            http_response_code(404);
            exit('Record not found');
        }

        $sourceUserId = (int)($_POST['source_user_id'] ?? 0);
        try {
            merge_end_users($postId, $sourceUserId, $admin);
            redirect('crud.php?module=users&action=edit&id=' . $postId . '&success=merged');
        } catch (Throwable $e) {
            $errors[] = app_text('user_merge.failed') . $e->getMessage();
        }
        $action = 'edit';
        $id = $postId;
    }

    if ($postAction === 'promote_user') {
        if ($moduleKey !== 'users' || !$postId || !scoped_row_exists($moduleKey, $module, $postId, $admin)) {
            http_response_code(404);
            exit('Record not found');
        }

        try {
            $promotion = promote_end_user_to_work_account($postId, $_POST, $admin);
            redirect(
                'crud.php?module=users&action=edit&id=' . $postId
                . '&success=promoted&promoted_module=' . rawurlencode((string)$promotion['module'])
                . '&promoted_id=' . (int)$promotion['id']
            );
        } catch (Throwable $e) {
            $errors[] = 'Не удалось сделать клиента рабочим аккаунтом: ' . $e->getMessage();
        }
        $action = 'edit';
        $id = $postId;
    }

    if ($postAction === 'link_staff_user') {
        if ($moduleKey !== 'users' || !$postId || !scoped_row_exists($moduleKey, $module, $postId, $admin)) {
            http_response_code(404);
            exit('Record not found');
        }

        try {
            $linked = link_end_user_to_work_account($postId, $_POST, $admin);
            redirect(
                'crud.php?module=users&action=edit&id=' . $postId
                . '&success=linked_staff&promoted_module=' . rawurlencode((string)$linked['module'])
                . '&promoted_id=' . (int)$linked['id']
            );
        } catch (Throwable $e) {
            $errors[] = 'Не удалось связать клиента с рабочим аккаунтом: ' . $e->getMessage();
        }
        $action = 'edit';
        $id = $postId;
    }

    if ($postAction === 'delete') {
        if (!$canDelete) {
            $errors[] = app_text('auto.k_da5ca3c5fc80');
        } else {
        if (!$postId || !scoped_row_exists($moduleKey, $module, $postId, $admin)) {
            http_response_code(404);
            exit('Record not found');
        }

        try {
            delete_crud_record($moduleKey, $module, $postId, $admin);
            redirect('crud.php?module=' . urlencode($moduleKey) . '&success=deleted');
        } catch (Throwable $e) {
            $errors[] = app_text('auto.k_cdec27146810') . $e->getMessage();
        }
        }
        $action = 'list';
    }

    if ($postAction === 'reset_owned_content') {
        if (!$postId || !scoped_row_exists($moduleKey, $module, $postId, $admin)) {
            http_response_code(404);
            exit('Record not found');
        }
        if (!owned_content_reset_for_admin($moduleKey, $postId, $admin)) {
            $errors[] = 'Не удалось сбросить запись: доступна только ваша личная версия, созданная из версии выше.';
        } else {
            redirect('crud.php?module=' . urlencode($moduleKey) . '&success=content_reset');
        }
        $action = 'list';
    }

    if ($moduleKey === 'site_templates' && $postAction === 'import_global_site_templates') {
        if (!$canCreate) {
            $errors[] = app_text('auto.k_6eaca3d4de92');
        } else {
            try {
                $summary = site_template_import_global_for_admin($admin);
                redirect(
                    'crud.php?module=site_templates&success=templates_imported'
                    . '&imported=' . (int)$summary['imported']
                    . '&restored=' . (int)$summary['restored']
                    . '&skipped=' . (int)$summary['skipped']
                );
            } catch (Throwable $e) {
                $errors[] = 'Не удалось импортировать базовые шаблоны: ' . $e->getMessage();
            }
        }
        $action = 'list';
    }

    if ($postAction === 'save') {
    if (($postId && !$canEdit) || (!$postId && !$canCreate)) {
        if (!$postId && $createLimitErrors) {
            $errors[] = 'Лимит закончился. Новые записи этого типа сейчас нельзя добавить.';
            $errors = array_merge($errors, $createLimitErrors);
        } else {
            $errors[] = $postId
                ? app_text('auto.k_fd8f8d50baa8')
                : app_text('auto.k_6eaca3d4de92');
        }
        $action = 'list';
    } else {
    if ($postId && !scoped_row_exists($moduleKey, $module, $postId, $admin)) {
        http_response_code(404);
        exit('Record not found');
    }

    if ($postId && owned_content_config($moduleKey)) {
        $postId = owned_content_editable_id($moduleKey, $postId, $admin);
        $id = $postId;
    }

    $templateId = nullable_int_value($_POST['template_id'] ?? null);
    if ($templateId && in_array($moduleKey, ['managers', 'resellers'], true) && !site_template_row($templateId, $admin)) {
        $errors[] = 'Шаблон мини-сайта недоступен для вашей ветки.';
    }
    $payload = collect_payload($formFields);
    if ($moduleKey === 'site_templates') {
        $payload = site_template_apply_editor_payload($payload, $_POST);
    }
    $payload = normalize_module_payload($moduleKey, $payload);
    $payload = apply_file_uploads($moduleKey, $formFields, $payload, $errors);
    $errors = array_merge($errors, validate_payload($formFields, $payload));
    $payload = apply_role_defaults($moduleKey, $payload, $admin, $postId);
    $limitPayload = $payload;
    if ($moduleKey === 'resellers') {
        $errors = array_merge($errors, subscription_plan_validate_reseller_payload($payload, $admin));
        if (!$errors) {
            $limitPayload = subscription_plan_apply_to_reseller_payload($payload, $admin);
        }
    }
    $errors = array_merge($errors, validate_unique_payload($moduleKey, $module, $payload, $postId));
    $errors = array_merge($errors, validate_scope_payload($moduleKey, $payload, $admin, $postId));
    $errors = array_merge($errors, validate_child_limit_caps($moduleKey, $limitPayload, $postId));
    $errors = array_merge($errors, validate_manager_limit_payload($moduleKey, $limitPayload, $postId));
    $errors = array_merge($errors, validate_leader_limit_payload($moduleKey, $limitPayload, $postId));
    if (!$errors) {
        try {
            $savedId = save_record($moduleKey, $module, $payload, $postId, $admin);
            if ($moduleKey === 'managers' && can_manage_team_admin_access($moduleKey, $admin, $savedId)) {
                save_manager_admin_access($savedId, $payload, $_POST, $errors);
                if ($errors) {
                    $action = $postId ? 'edit' : 'create';
                    $id = $savedId;
                    $editRow = $payload + ['id' => $savedId, 'template_id' => $templateId];
                }
            }
            if ($moduleKey === 'resellers' && can_manage_team_admin_access($moduleKey, $admin, $savedId)) {
                save_reseller_admin_access($savedId, $payload, $_POST, $errors);
                if ($errors) {
                    $action = $postId ? 'edit' : 'create';
                    $id = $savedId;
                    $editRow = $payload + ['id' => $savedId, 'template_id' => $templateId];
                }
            }
            if (!$errors && in_array($moduleKey, ['managers', 'resellers'], true)) {
                $ownerType = $moduleKey === 'managers' ? 'manager' : 'reseller';
                $profile = ensure_consultant_profile($ownerType, $savedId);
                $profileId = (int)($profile['id'] ?? 0);
                if ($profileId <= 0) {
                    throw new RuntimeException('Не удалось создать профиль мини-сайта.');
                }
                $currentTemplateId = nullable_int_value($profile['template_id'] ?? null);
                $inheritsProfile = consultant_profile_inherits($profile);
                if ($templateId && ($currentTemplateId !== $templateId || $inheritsProfile)) {
                    site_template_apply_to_profile($profileId, $ownerType, $savedId, $templateId);
                } elseif (!$templateId && !$inheritsProfile) {
                    $parentProfile = consultant_parent_profile($ownerType, $savedId);
                    if ($parentProfile) {
                        consultant_profile_reset_to_parent($profileId, (int)$parentProfile['id']);
                    }
                }
            }
            if (!$errors) {
                redirect('crud.php?module=' . urlencode($moduleKey) . '&success=saved');
            }
            $postId = $savedId;
        } catch (Throwable $e) {
            $errors[] = friendly_save_error($e, $payload);
        }
    }

    $editRow = $payload + ['id' => $postId, 'template_id' => $templateId];
    $action = $postId ? 'edit' : 'create';
    }
    }
}

if ($action === 'edit' && $id) {
    if (!scoped_row_exists($moduleKey, $module, $id, $admin)) {
        http_response_code(404);
        exit('Record not found');
    }
    if (owned_content_config($moduleKey) && $admin['role'] !== 'superadmin') {
        $editableId = owned_content_editable_id($moduleKey, $id, $admin);
        if ($editableId !== $id) {
            redirect('crud.php?module=' . urlencode($moduleKey) . '&action=edit&id=' . $editableId . '&success=personal_copy');
        }
    }
    $stmt = db()->prepare("SELECT * FROM {$module['table']} WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $editRow = $stmt->fetch() ?: null;
    if (!$editRow) {
        $errors[] = 'Запись #' . (int)$id . ' не найдена или уже удалена.';
    } elseif (in_array($moduleKey, ['managers', 'resellers'], true)) {
        $editRow['template_id'] = profile_template_id_for_module($moduleKey, (int)$editRow['id']);
    }
}

if ($leadChatOnly && !$editRow) {
    $errors[] = 'Чат не найден. Вернитесь к списку обращений и откройте актуальную карточку клиента.';
}

$rows = [];
$listHtml = '';
$listMeta = [];
$displayColumns = crud_display_columns($moduleKey);
$limitCheckUrl = in_array($moduleKey, ['managers', 'resellers'], true)
    ? 'crud.php?module=' . urlencode($moduleKey) . '&action=limit_check'
    : '';
try {
    if (!$leadChatOnly && $action === 'list') {
        [$listSql, $params] = crud_list_query($moduleKey, $module, $admin);
        if ($moduleKey === 'leads') {
            $stmt = db()->prepare($listSql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
        } else {
            $pageData = crud_paginated_rows($moduleKey, $listSql, $params, $displayColumns);
            $rows = $pageData['rows'];
            $listMeta = $pageData['meta'];
        }
        $listHtml = render_crud_list($moduleKey, $displayColumns, $rows, $canEdit, $canDelete, $admin, $listMeta);
    }
} catch (Throwable $e) {
    $errors[] = app_text('auto.k_49fb23bb29cf') . $e->getMessage();
    $listHtml = app_text('auto.k_fda0c24ca2e9');
}

$adminAccess = null;
$adminAccessRecordId = !empty($editRow['id']) ? (int)$editRow['id'] : null;
$canManageAdminAccess = can_manage_team_admin_access($moduleKey, $admin, $adminAccessRecordId);
if ($canManageAdminAccess && ($action === 'create' || $action === 'edit')) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $adminAccess = [
            'email' => trim((string)($_POST['admin_email'] ?? '')),
            'is_active' => isset($_POST['admin_is_active']) ? 1 : 0,
        ];
    } elseif (!empty($editRow['id'])) {
        $adminAccess = $moduleKey === 'managers'
            ? manager_admin_access((int)$editRow['id'])
            : reseller_admin_access((int)$editRow['id']);
    }
}

$limitFieldCaps = [];
if (in_array($moduleKey, ['managers', 'resellers'], true) && ($action === 'create' || $action === 'edit')) {
    $limitSeed = [];
    foreach ($formFields as $fieldName => $fieldConfig) {
        if (!empty($fieldConfig['virtual']) || !empty($fieldConfig['readonly'])) {
            continue;
        }
        $limitSeed[$fieldName] = $editRow[$fieldName] ?? ($fieldConfig['default'] ?? null);
    }
    $limitSeed = normalize_module_payload($moduleKey, $limitSeed);
    $limitSeed = apply_role_defaults($moduleKey, $limitSeed, $admin, isset($editRow['id']) ? (int)$editRow['id'] : null);
    $limitFieldCaps = child_limit_field_caps($moduleKey, $limitSeed, isset($editRow['id']) ? (int)$editRow['id'] : null);
}

require __DIR__ . '/../views/crud/page.php';
