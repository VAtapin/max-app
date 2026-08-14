<?php

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lead_service.php';
require_once __DIR__ . '/../admin/app/core/live_chat.php';

$data = (input_json() ?: $_POST) + $_GET;
$user = require_platform_user($data);
$onboarding = client_onboarding_status($user);
if (!$onboarding['complete']) json_response(['error' => 'onboarding_required'], 403);
if (!empty($onboarding['web_merge_required'])) json_response(['error' => 'account_merge_required'], 403);

$threadId = live_chat_backfill_client((int)$user['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($data['action'] ?? 'send');
    if ($action === 'read') {
        db()->prepare('UPDATE chat_messages SET status = "read", read_at = NOW() WHERE thread_id = :thread_id AND sender_type = "admin" AND status IN ("sent","delivered")')->execute(['thread_id' => $threadId]);
        json_response(['ok' => true]);
    }
    $message = mb_substr(trim((string)($data['message'] ?? '')), 0, 8000, 'UTF-8');
    if ($message === '') json_response(['error' => 'message is required'], 422);
    $channel = normalize_platform((string)($user['current_platform'] ?? $user['platform'] ?? 'web')) ?: 'web';
    $leadId = create_lead_for_user($user, ['platform' => $channel, 'request_type' => 'consultation', 'message' => $message]);
    $messageId = live_chat_record_client_message((int)$user['id'], $channel, $message, [], 'legacy-lead:' . $leadId);
    json_response(['ok' => true, 'message_id' => $messageId, 'lead_id' => $leadId], 201);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_response(['error' => 'method not allowed'], 405);
$messages = live_chat_message_rows($threadId, max(0, (int)($_GET['after_id'] ?? 0)));
db()->prepare('UPDATE chat_messages SET status = "read", read_at = NOW() WHERE thread_id = :thread_id AND sender_type = "admin" AND status IN ("sent","delivered")')->execute(['thread_id' => $threadId]);
json_response(['thread_id' => $threadId, 'messages' => $messages]);
