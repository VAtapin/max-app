<?php

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lead_service.php';
require_once __DIR__ . '/../admin/app/core/live_chat.php';
require_once __DIR__ . '/../admin/app/core/ok_callback.php';

ok_callback_handle(input_json(), trim((string)($_GET['key'] ?? '')));
