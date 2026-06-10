<?php

require __DIR__ . '/bootstrap.php';

json_response([
    'name' => 'Health Sales Support API',
    'endpoints' => [
        'GET /api/user.php',
        'POST /api/auth.php',
        'GET /api/products.php',
        'GET /api/tests.php',
        'POST /api/tests.php?action=submit',
        'GET /api/recommendations.php',
        'GET /api/leads.php',
        'POST /api/leads.php',
        'POST /api/contact_manager.php',
    ],
]);
