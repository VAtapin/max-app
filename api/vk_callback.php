<?php

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lead_service.php';
require_once __DIR__ . '/../admin/app/core/vk_callback.php';

vk_callback_handle(input_json());
