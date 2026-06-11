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
        "SELECT id, lead_id, message_text, attachment_path, external_url, status, sent_at, read_at, created_at
         FROM lead_responses
         WHERE lead_id IN ($placeholders)
         ORDER BY id ASC"
    );
    $responsesStmt->execute($leadIds);
    $responsesByLead = [];
    foreach ($responsesStmt->fetchAll() as $response) {
        $response['attachments'] = response_attachment_paths($response['attachment_path'] ?? null);
        $responsesByLead[(int)$response['lead_id']][] = $response;
    }

    foreach ($leads as &$lead) {
        $lead['responses'] = $responsesByLead[(int)$lead['id']] ?? [];
    }
    unset($lead);
}

json_response(['leads' => $leads]);
