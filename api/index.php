<?php

require __DIR__ . '/bootstrap.php';

json_response([
    'name' => 'SWPro API',
    'endpoints' => [
        'GET /api/user.php',
        'POST /api/auth.php',
        'POST /api/telegram_auth.php',
        'GET|POST /api/onboarding.php',
        'GET|POST /api/notifications.php',
        'GET /api/messaging_config.php',
        'POST /api/vk_callback.php',
        'GET /api/telegram_oidc_start.php',
        'GET /api/telegram_oidc_callback.php',
        'GET /api/telegram_oidc_session.php',
        'GET /api/profile.php',
        'GET /api/content.php',
        'GET /api/tests.php',
        'POST /api/tests.php?action=start',
        'POST /api/tests.php?action=answer',
        'GET /api/tests.php?action=result',
        'POST /api/contact_manager.php',
    ],
]);
