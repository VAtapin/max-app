<?php

require_once __DIR__ . '/db.php';

function web_user_cleanup_candidates(int $limit = 200): array
{
    $limit = max(1, min(1000, $limit));
    return db()->query(
        'SELECT eu.id, eu.platform_user_id, eu.referral_code_used, eu.onboarding_completed_at, eu.created_at,
                CASE WHEN EXISTS (
                    SELECT 1 FROM user_consents uca
                    WHERE uca.end_user_id = eu.id AND uca.document_type = "user_agreement"
                ) THEN "not_merged" ELSE "without_agreement" END AS cleanup_reason
         FROM end_users eu
         WHERE eu.platform = "web"
           AND eu.merged_into_user_id IS NULL
           AND NOT EXISTS (
               SELECT 1 FROM platform_accounts pa
               WHERE pa.end_user_id = eu.id AND pa.platform <> "web"
           )
           AND NOT EXISTS (SELECT 1 FROM resellers r WHERE r.source_end_user_id = eu.id)
           AND NOT EXISTS (SELECT 1 FROM managers m WHERE m.source_end_user_id = eu.id)
           AND (
               (NOT EXISTS (
                    SELECT 1 FROM user_consents uc3
                    WHERE uc3.end_user_id = eu.id AND uc3.document_type = "user_agreement"
                ) AND eu.created_at < DATE_SUB(NOW(), INTERVAL 3 DAY))
               OR
               ((SELECT MAX(uc5.granted_at) FROM user_consents uc5
                    WHERE uc5.end_user_id = eu.id AND uc5.document_type = "user_agreement")
                    < DATE_SUB(NOW(), INTERVAL 5 DAY))
           )
         ORDER BY eu.id
         LIMIT ' . $limit
    )->fetchAll();
}

function cleanup_expired_web_users(int $limit = 200, bool $dryRun = false): array
{
    $candidates = web_user_cleanup_candidates($limit);
    if ($dryRun || !$candidates) {
        return [
            'dry_run' => $dryRun,
            'candidates' => count($candidates),
            'deleted' => 0,
            'ids' => array_map('intval', array_column($candidates, 'id')),
        ];
    }

    $deleted = 0;
    $deletedIds = [];
    $pdo = db();
    foreach ($candidates as $candidate) {
        $pdo->beginTransaction();
        try {
            $lock = $pdo->prepare(
                'SELECT id FROM end_users
                 WHERE id = :id AND platform = "web" AND merged_into_user_id IS NULL
                 FOR UPDATE'
            );
            $lock->execute(['id' => (int)$candidate['id']]);
            if (!$lock->fetchColumn()) {
                $pdo->rollBack();
                continue;
            }

            $log = $pdo->prepare(
                'INSERT INTO activity_logs (actor_type, actor_id, action, entity_type, entity_id, details)
                 VALUES ("system", NULL, "delete_expired_web_user", "end_users", :entity_id, :details)'
            );
            $log->execute([
                'entity_id' => (int)$candidate['id'],
                'details' => json_encode([
                    'reason' => (string)$candidate['cleanup_reason'],
                    'web_user_id_hash' => hash('sha256', (string)$candidate['platform_user_id']),
                    'referral_code' => $candidate['referral_code_used'],
                ], JSON_UNESCAPED_UNICODE),
            ]);

            $delete = $pdo->prepare('DELETE FROM end_users WHERE id = :id');
            $delete->execute(['id' => (int)$candidate['id']]);
            $pdo->commit();
            if ($delete->rowCount() === 1) {
                $deleted++;
                $deletedIds[] = (int)$candidate['id'];
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Failed to clean Web user #' . (int)$candidate['id'] . ': ' . $e->getMessage());
        }
    }

    return [
        'dry_run' => false,
        'candidates' => count($candidates),
        'deleted' => $deleted,
        'ids' => $deletedIds,
    ];
}
