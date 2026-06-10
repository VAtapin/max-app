<?php

require __DIR__ . '/bootstrap.php';

$user = require_platform_user();
json_response(['user' => $user, 'disclaimer' => medical_disclaimer()]);
