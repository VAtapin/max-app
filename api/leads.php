<?php

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/lead_service.php';

$user = require_platform_user();

function response_attachment_paths(?string $value): array
{
    $value = trim((string)$value);
    if ($value === '') {
        return [];
    }

    $decoded = json_decode($value, true);
    if (is_array($decoded)) {
        return array_values(array_filter(array_map('strval', $decoded), static fn($path) => trim($path) !== ''));
    }

    $paths = preg_split('/\r\n|\r|\n/', $value) ?: [];
    return array_values(array_filter(array_map('trim', $paths), static fn($path) => $path !== ''));
}

if (($_GET['action'] ?? '') === 'mark_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = input_json() ?: $_POST;
    $leadId = (int)($data['lead_id'] ?? 0);

    if (!$leadId) {
        json_response(['error' => 'lead_id is required'], 422);
    }

    $stmt = db()->prepare(
        'UPDATE lead_responses lr
         INNER JOIN leads l ON l.id = lr.lead_id
         SET lr.read_at = NOW()
         WHERE lr.lead_id = :lead_id
           AND l.end_user_id = :end_user_id
           AND lr.read_at IS NULL'
    );

    $stmt->execute([
        'lead_id' => $leadId,
        'end_user_id' => $user['id'],
    ]);

    json_response([
        'ok' => true,
        'updated' => $stmt->rowCount(),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = input_json() ?: $_POST;
    $leadId = create_lead_for_user($user, $data);
    json_response(['lead_id' => $leadId, 'status' => 'new'], 201);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['error' => 'method not allowed'], 405);
}

$stmt = db()->prepare(
    'SELECT l.*, p.title AS product_title
     FROM leads l
     LEFT JOIN products p ON p.id = l.product_id
     WHERE l.end_user_id = :end_user_id
     ORDER BY l.id DESC
     LIMIT 50'
);
$stmt->execute(['end_user_id' => $user['id']]);
$leads = $stmt->fetchAll();

if ($leads) {
    $leadIds = array_map(static fn($lead) => (int)$lead['id'], $leads);
    $placeholders = implode(',', array_fill(0, count($leadIds), '?'));
    $responsesStmt = db()->prepare(
        "SELECT lr.id,
                lr.lead_id,
                lr.message_text,
                lr.attachment_path,
                lr.external_url,
                lr.status,
                lr.sent_at,
                lr.read_at,
                lr.created_at,
                lr.content_post_id,
                lr.test_id,
                cp.title AS content_title,
                cp.short_text AS content_short_text,
                cp.full_text AS content_full_text,
                cp.image_path AS content_image_path,
                cp.attachment_path AS content_attachment_path,
                cp.video_url AS content_video_url,
                cp.button_text AS content_button_text,
                cp.button_url AS content_button_url,
                t.title AS test_title,
                t.description AS test_description
         FROM lead_responses lr
         LEFT JOIN content_posts cp ON cp.id = lr.content_post_id
         LEFT JOIN tests t ON t.id = lr.test_id
         WHERE lr.lead_id IN ($placeholders)
         ORDER BY lr.id ASC"
    );
    $responsesStmt->execute($leadIds);
    $responsesByLead = [];
    foreach ($responsesStmt->fetchAll() as $response) {
        $response['attachments'] = response_attachment_paths($response['attachment_path'] ?? null);
        $response['content'] = !empty($response['content_post_id']) ? [
            'id' => (int)$response['content_post_id'],
            'title' => $response['content_title'],
            'short_text' => $response['content_short_text'],
            'full_text' => $response['content_full_text'],
            'image_path' => $response['content_image_path'],
            'attachment_path' => $response['content_attachment_path'],
            'video_url' => $response['content_video_url'],
            'button_text' => $response['content_button_text'],
            'button_url' => $response['content_button_url'],
        ] : null;
        $response['test'] = !empty($response['test_id']) ? [
            'id' => (int)$response['test_id'],
            'title' => $response['test_title'],
            'description' => $response['test_description'],
        ] : null;
        $responsesByLead[(int)$response['lead_id']][] = $response;
    }

    foreach ($leads as &$lead) {
        $lead['responses'] = $responsesByLead[(int)$lead['id']] ?? [];
    }
    unset($lead);
}

json_response(['leads' => $leads]);
