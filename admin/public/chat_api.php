<?php

require_once __DIR__ . '/../app/core/auth.php';
require_once __DIR__ . '/../app/core/live_chat.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$admin = require_auth();
if (!in_array((string)($admin['role'] ?? ''), ['superadmin','reseller','manager'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied'], JSON_UNESCAPED_UNICODE);
    exit;
}

function admin_chat_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function admin_chat_attachments(array &$errors): array
{
    if (empty($_FILES['attachments'])) return [];
    $_FILES['response_attachments'] = $_FILES['attachments'];
    return save_response_attachments($errors);
}

$action = (string)($_GET['action'] ?? $_POST['action'] ?? 'list');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
        $team = null;
        if (($admin['role'] ?? '') !== 'superadmin') {
            $teamThreadId = live_chat_ensure_team_thread($admin);
            if ($teamThreadId) {
                $stmt = db()->prepare('SELECT ct.id thread_id, ct.title, ct.last_message_at, (SELECT message_text FROM chat_messages WHERE thread_id = ct.id ORDER BY id DESC LIMIT 1) last_message, (SELECT COUNT(*) FROM chat_messages cm WHERE cm.thread_id = ct.id AND cm.sender_type <> "system" AND cm.sender_admin_user_id <> :admin_id AND cm.id > COALESCE((SELECT cr.last_message_id FROM chat_reads cr WHERE cr.thread_id = ct.id AND cr.admin_user_id = :reader_id), 0)) unread_count FROM chat_threads ct WHERE ct.id = :thread_id');
                $stmt->execute(['admin_id' => (int)$admin['id'], 'reader_id' => (int)$admin['id'], 'thread_id' => $teamThreadId]);
                $team = $stmt->fetch() ?: null;
            }
        }
        admin_chat_json(['clients' => live_chat_client_threads($admin), 'team' => $team]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'messages') {
        $threadId = max(0, (int)($_GET['thread_id'] ?? 0));
        $endUserId = max(0, (int)($_GET['end_user_id'] ?? 0));
        $kind = (string)($_GET['kind'] ?? 'client');
        if (!$threadId && $kind === 'team') $threadId = (int)(live_chat_ensure_team_thread($admin) ?? 0);
        if (!$threadId && $endUserId) {
            if (!live_chat_admin_can_access_client($admin, $endUserId)) admin_chat_json(['error' => 'Клиент недоступен.'], 403);
            $threadId = live_chat_backfill_client($endUserId);
        }
        $thread = live_chat_thread($threadId);
        if (!$thread || !live_chat_admin_can_access_thread($admin, $thread)) admin_chat_json(['error' => 'Чат недоступен.'], 403);
        $messages = live_chat_message_rows($threadId, max(0, (int)($_GET['after_id'] ?? 0)));
        live_chat_mark_read($threadId, (int)$admin['id']);
        admin_chat_json(['thread' => $thread, 'messages' => $messages]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        if ($action === 'send') {
            $text = trim((string)($_POST['message'] ?? ''));
            $errors = [];
            $attachments = admin_chat_attachments($errors);
            if ($errors) admin_chat_json(['error' => implode(' ', $errors)], 422);
            $kind = (string)($_POST['kind'] ?? 'client');
            $result = $kind === 'team'
                ? live_chat_send_team($admin, $text, $attachments, isset($_POST['include_ai']))
                : live_chat_send_client($admin, max(0, (int)($_POST['end_user_id'] ?? 0)), $text, (string)($_POST['channel'] ?? ''), $attachments);
            admin_chat_json($result, !empty($result['ok']) ? 201 : 422);
        }
        if ($action === 'read') {
            $threadId = max(0, (int)($_POST['thread_id'] ?? 0));
            $thread = live_chat_thread($threadId);
            if (!$thread || !live_chat_admin_can_access_thread($admin, $thread)) admin_chat_json(['error' => 'Чат недоступен.'], 403);
            live_chat_mark_read($threadId, (int)$admin['id']);
            admin_chat_json(['ok' => true]);
        }
    }
} catch (Throwable $error) {
    admin_chat_json(['error' => $error->getMessage()], 500);
}

admin_chat_json(['error' => 'Method not allowed'], 405);
