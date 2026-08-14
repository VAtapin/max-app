<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/permissions.php';
require_once __DIR__ . '/../app/core/crud_views.php';
require_once __DIR__ . '/../app/core/lead_responses.php';
require_once __DIR__ . '/../app/core/test_admin.php';
require_once __DIR__ . '/../app/core/broadcast_runner.php';
require_once __DIR__ . '/../app/core/client_journey.php';
require_once __DIR__ . '/../app/core/content_ownership.php';
require_once __DIR__ . '/../app/core/integration_guides.php';
require_once __DIR__ . '/../app/core/site_templates.php';
require_once __DIR__ . '/../app/core/consultant_profiles.php';
require_once __DIR__ . '/../app/core/subscription_plans.php';

$admin = require_auth();

$crudPublicDir = __DIR__;
require_once __DIR__ . '/../app/crud/integrations.php';
require_once __DIR__ . '/../app/crud/scopes.php';
require_once __DIR__ . '/../app/crud/user_accounts.php';
require_once __DIR__ . '/../app/crud/user_merge.php';
require_once __DIR__ . '/../app/crud/user_promotion.php';
require_once __DIR__ . '/../app/crud/fields.php';
require_once __DIR__ . '/../app/crud/uploads.php';
require_once __DIR__ . '/../app/crud/validation.php';
require_once __DIR__ . '/../app/crud/limits.php';
require_once __DIR__ . '/../app/crud/staff_access.php';
require_once __DIR__ . '/../app/crud/deletion.php';
require_once __DIR__ . '/../app/crud/operations.php';
$modules = require __DIR__ . '/../app/crud/modules.php';
require __DIR__ . '/../app/crud/controller.php';
