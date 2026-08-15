<?php

require_once __DIR__ . '/ai_center.php';

function ai_private_storage_root(): string
{
    $configured = trim((string)(getenv('SWPRO_PRIVATE_STORAGE_PATH') ?: ''));
    return $configured !== '' ? rtrim($configured, '/\\') : dirname(__DIR__, 3) . '/storage/private';
}

function ai_estimated_audio_seconds(string $text): int
{
    $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    return max(1, (int)ceil(count($words) / 2.35));
}

function ai_openai_speech(string $text): array
{
    if (ai_setting('ai.external_processing_enabled', '0') !== '1'
        || ai_setting('ai.voice_external_enabled', '0') !== '1') {
        throw new RuntimeException('Внешний синтез голоса не разрешён в настройках ИИ.');
    }
    if (!ai_openai_key_configured()) {
        throw new RuntimeException('OPENAI_API_KEY не найден в конфигурации сервера.');
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('На сервере не установлено расширение PHP cURL.');
    }
    $text = trim($text);
    if ($text === '' || mb_strlen($text, 'UTF-8') > 4000) {
        throw new RuntimeException('Сценарий должен содержать от 1 до 4000 символов.');
    }
    $model = ai_openai_model(ai_setting('ai.openai_tts_model', 'gpt-4o-mini-tts'));
    $voice = trim((string)ai_setting('ai.openai_voice', 'marin'));
    if (!in_array($voice, ['alloy','ash','ballad','coral','echo','fable','nova','onyx','sage','shimmer','verse','marin','cedar'], true)) {
        throw new RuntimeException('В настройках указан неподдерживаемый голос OpenAI.');
    }
    $payload = [
        'model' => $model,
        'voice' => $voice,
        'input' => $text,
        'instructions' => trim((string)ai_setting('ai.openai_voice_instructions', 'Говори по-русски как в личном голосовом сообщении знакомому человеку: тепло, живо и естественно. Избегай дикторской, рекламной и торжественной подачи. Используй разговорную интонацию, лёгкие изменения темпа и высоты голоса, короткие естественные паузы между мыслями. Не растягивай окончания и не делай одинаковые паузы после каждого предложения.')),
        // Ogg/Opus is delivered by Telegram as a native voice message.
        'response_format' => 'opus',
    ];
    $curl = curl_init('https://api.openai.com/v1/audio/speech');
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 90,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . ai_openai_api_key(),
            'Content-Type: application/json',
            'Accept: audio/ogg',
            'User-Agent: SWPro-AI/1.0',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
    ]);
    $raw = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);
    if ($raw === false || $curlError !== '') {
        throw new RuntimeException('Не удалось соединиться с OpenAI: ' . ($curlError ?: 'ошибка сети.'));
    }
    if ($status < 200 || $status >= 300) {
        $json = json_decode((string)$raw, true);
        $message = is_array($json) ? (string)($json['error']['message'] ?? 'HTTP ' . $status) : 'HTTP ' . $status;
        throw new RuntimeException('OpenAI не создал аудио: ' . mb_substr($message, 0, 300, 'UTF-8'));
    }
    if (strlen((string)$raw) < 100) {
        throw new RuntimeException('OpenAI вернул пустой аудиофайл.');
    }
    return ['audio' => (string)$raw, 'model' => $model, 'voice' => $voice, 'extension' => 'ogg'];
}

function ai_process_voice_job(int $id, array $owner): array
{
    $stmt = db()->prepare('SELECT * FROM ai_voice_jobs WHERE id = :id AND owner_type = :owner_type AND owner_id = :owner_id AND status = "queued" LIMIT 1');
    $stmt->execute(['id' => $id] + $owner);
    $job = $stmt->fetch();
    if (!$job) {
        throw new RuntimeException('Голосовое задание не найдено или уже обработано.');
    }
    if ((string)$job['provider'] !== 'openai' || (string)$job['voice_mode'] !== 'standard') {
        throw new RuntimeException('Сейчас поддерживается только стандартный AI-голос OpenAI.');
    }
    db()->prepare('UPDATE ai_voice_jobs SET status = "processing", error_text = NULL WHERE id = :id')->execute(['id' => $id]);
    try {
        $speech = ai_openai_speech((string)$job['script_text']);
        $relativeDir = 'ai/voice/' . $job['owner_type'] . '/' . (int)$job['owner_id'];
        $absoluteDir = ai_private_storage_root() . '/' . $relativeDir;
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0770, true) && !is_dir($absoluteDir)) {
            throw new RuntimeException('Не удалось создать закрытый каталог для аудио.');
        }
        $extension = in_array((string)($speech['extension'] ?? ''), ['ogg', 'mp3'], true)
            ? (string)$speech['extension']
            : 'ogg';
        $relativePath = $relativeDir . '/job-' . $id . '.' . $extension;
        if (file_put_contents(ai_private_storage_root() . '/' . $relativePath, $speech['audio'], LOCK_EX) === false) {
            throw new RuntimeException('Не удалось сохранить созданное аудио.');
        }
        $seconds = ai_estimated_audio_seconds((string)$job['script_text']);
        db()->prepare('UPDATE ai_voice_jobs SET output_path = :path, duration_seconds = :seconds, model = :model, status = "ready", completed_at = NOW() WHERE id = :id')->execute([
            'path' => $relativePath, 'seconds' => $seconds, 'model' => $speech['model'], 'id' => $id,
        ]);
        db()->prepare('INSERT INTO ai_usage_events (owner_type, owner_id, event_type, quantity, provider, model, metadata_json) VALUES (:owner_type, :owner_id, "voice", :quantity, "openai", :model, :metadata)')->execute([
            'owner_type' => $job['owner_type'], 'owner_id' => (int)$job['owner_id'], 'quantity' => $seconds,
            'model' => $speech['model'], 'metadata' => json_encode(['voice' => $speech['voice'], 'job_id' => $id], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        return ['id' => $id, 'seconds' => $seconds];
    } catch (Throwable $error) {
        db()->prepare('UPDATE ai_voice_jobs SET status = "failed", error_text = :error, completed_at = NOW() WHERE id = :id')->execute([
            'error' => mb_substr($error->getMessage(), 0, 1000, 'UTF-8'), 'id' => $id,
        ]);
        throw $error;
    }
}

function ai_voice_delivery_url(array $job): string
{
    if ((string)($job['status'] ?? '') !== 'ready' || empty($job['end_user_id'])) {
        throw new RuntimeException('Голосовое сообщение не готово или не привязано к клиенту.');
    }
    $token = bin2hex(random_bytes(32));
    db()->prepare('INSERT INTO ai_voice_delivery_links (voice_job_id, end_user_id, token_hash) VALUES (:job_id, :user_id, :token_hash)')->execute([
        'job_id' => (int)$job['id'],
        'user_id' => (int)$job['end_user_id'],
        'token_hash' => hash('sha256', $token),
    ]);
    $base = rtrim((string)(getenv('SWPRO_PUBLIC_URL') ?: 'https://swpro.ru'), '/');
    return $base . '/api/voice_media.php?token=' . rawurlencode($token);
}

function ai_revoke_voice_delivery_url(string $url): void
{
    parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
    $token = trim((string)($query['token'] ?? ''));
    if (preg_match('/^[a-f0-9]{64}$/', $token)) {
        db()->prepare('UPDATE ai_voice_delivery_links SET revoked_at = NOW() WHERE token_hash = :token_hash')->execute([
            'token_hash' => hash('sha256', $token),
        ]);
    }
}

function ai_provider_json(string $method, string $url, string $provider, ?array $payload = null): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('На сервере не установлено расширение PHP cURL.');
    }
    $key = ai_video_provider_key($provider);
    if ($key === '') {
        throw new RuntimeException('Ключ ' . strtoupper($provider) . ' не найден на сервере.');
    }
    $headers = ['Accept: application/json', 'Content-Type: application/json', 'User-Agent: SWPro-AI/1.0'];
    $headers[] = ($provider === 'tavus' ? 'x-api-key: ' : 'X-Api-Key: ') . $key;
    $curl = curl_init($url);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 45, CURLOPT_HTTPHEADER => $headers]);
    if ($payload !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
    $raw = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    if ($raw === false || $error !== '') {
        throw new RuntimeException('Ошибка соединения с ' . $provider . ': ' . ($error ?: 'неизвестная ошибка.'));
    }
    $json = json_decode((string)$raw, true);
    if (!is_array($json)) {
        throw new RuntimeException($provider . ' вернул неизвестный формат ответа.');
    }
    if ($status < 200 || $status >= 300) {
        $message = (string)($json['error']['message'] ?? $json['message'] ?? $json['status_details'] ?? 'HTTP ' . $status);
        throw new RuntimeException($provider . ' отклонил запрос: ' . mb_substr($message, 0, 500, 'UTF-8'));
    }
    return $json;
}

function ai_submit_video_job(int $id, array $owner): string
{
    $stmt = db()->prepare('SELECT j.*, a.provider_avatar_id, a.voice_id, a.owner_type, a.owner_id FROM ai_video_jobs j JOIN ai_avatars a ON a.id = j.avatar_id WHERE j.id = :id AND a.owner_type = :owner_type AND a.owner_id = :owner_id AND j.status = "queued" LIMIT 1');
    $stmt->execute(['id' => $id] + $owner);
    $job = $stmt->fetch();
    if (!$job) {
        throw new RuntimeException('Видео-задание не найдено или уже отправлено.');
    }
    $provider = (string)$job['provider'];
    if (!ai_video_provider_configured($provider)) {
        throw new RuntimeException('Видеопровайдер ' . $provider . ' не настроен.');
    }
    if (trim((string)$job['provider_avatar_id']) === '') {
        throw new RuntimeException('У аватара не указан внешний ID провайдера.');
    }
    $script = trim((string)$job['script_text']);
    if ($script === '' || mb_strlen($script, 'UTF-8') > 4900) {
        throw new RuntimeException('Сценарий видео должен содержать от 1 до 4900 символов.');
    }
    if ($provider === 'tavus') {
        $json = ai_provider_json('POST', 'https://tavusapi.com/v2/videos', 'tavus', [
            'replica_id' => (string)$job['provider_avatar_id'], 'script' => $script, 'video_name' => 'SWPro #' . $id,
        ]);
        $providerId = (string)($json['video_id'] ?? '');
    } else {
        if (trim((string)$job['voice_id']) === '') {
            throw new RuntimeException('Для HeyGen укажите ID голоса в настройках аватара.');
        }
        $json = ai_provider_json('POST', 'https://api.heygen.com/v2/video/generate', 'heygen', [
            'video_inputs' => [[
                'character' => ['type' => 'avatar', 'avatar_id' => (string)$job['provider_avatar_id'], 'avatar_style' => 'normal'],
                'voice' => ['type' => 'text', 'input_text' => $script, 'voice_id' => (string)$job['voice_id']],
            ]],
            'dimension' => ['width' => 1280, 'height' => 720],
        ]);
        $providerId = (string)($json['data']['video_id'] ?? '');
    }
    if ($providerId === '') {
        throw new RuntimeException($provider . ' не вернул ID видео.');
    }
    db()->prepare('UPDATE ai_video_jobs SET provider_job_id = :provider_id, status = "processing", error_text = NULL WHERE id = :id')->execute(['provider_id' => $providerId, 'id' => $id]);
    return $providerId;
}

function ai_download_generated_video(string $url, string $target): void
{
    $parts = parse_url($url);
    $host = strtolower((string)($parts['host'] ?? ''));
    $allowed = $url !== '' && ($parts['scheme'] ?? '') === 'https' && (
        $host === 'heygen.com' || str_ends_with($host, '.heygen.com') || $host === 'heygen.ai' || str_ends_with($host, '.heygen.ai')
        || $host === 'tavus.io' || str_ends_with($host, '.tavus.io') || $host === 'mux.com' || str_ends_with($host, '.mux.com')
        || $host === 'cloudfront.net' || str_ends_with($host, '.cloudfront.net') || $host === 'amazonaws.com' || str_ends_with($host, '.amazonaws.com')
    );
    if (!$allowed) {
        throw new RuntimeException('Провайдер вернул недопустимый адрес видео.');
    }
    $directory = dirname($target);
    if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
        throw new RuntimeException('Не удалось создать закрытый каталог видео.');
    }
    $temporary = $target . '.part';
    $file = fopen($temporary, 'wb');
    if (!$file) {
        throw new RuntimeException('Не удалось открыть временный файл видео.');
    }
    $written = 0;
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 180,
        CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use ($file, &$written): int {
            $written += strlen($chunk);
            if ($written > 157286400) {
                return 0;
            }
            return (int)fwrite($file, $chunk);
        },
    ]);
    $ok = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    fclose($file);
    if (!$ok || $status < 200 || $status >= 300 || $written < 1000) {
        @unlink($temporary);
        throw new RuntimeException('Не удалось скачать готовое видео: ' . ($error ?: 'HTTP ' . $status));
    }
    if (!rename($temporary, $target)) {
        @unlink($temporary);
        throw new RuntimeException('Не удалось сохранить готовое видео.');
    }
}

function ai_poll_video_jobs(int $limit = 5): array
{
    $result = ['checked' => 0, 'ready' => 0, 'failed' => 0];
    $jobs = db()->query('SELECT j.*, a.owner_type, a.owner_id FROM ai_video_jobs j JOIN ai_avatars a ON a.id = j.avatar_id WHERE j.status = "processing" AND j.provider_job_id IS NOT NULL ORDER BY j.id LIMIT ' . max(1, min(20, $limit)))->fetchAll();
    foreach ($jobs as $job) {
        $result['checked']++;
        try {
            if ($job['provider'] === 'tavus') {
                $json = ai_provider_json('GET', 'https://tavusapi.com/v2/videos/' . rawurlencode((string)$job['provider_job_id']), 'tavus');
                $status = (string)($json['status'] ?? '');
                $url = (string)($json['download_url'] ?? '');
                $error = (string)($json['status_details'] ?? '');
                $done = $status === 'ready';
                $failed = in_array($status, ['error', 'failed'], true);
            } else {
                $json = ai_provider_json('GET', 'https://api.heygen.com/v1/video_status.get?video_id=' . rawurlencode((string)$job['provider_job_id']), 'heygen');
                $data = (array)($json['data'] ?? []);
                $status = (string)($data['status'] ?? '');
                $url = (string)($data['video_url'] ?? '');
                $error = (string)($data['error'] ?? '');
                $done = $status === 'completed';
                $failed = $status === 'failed';
            }
            if ($failed) {
                db()->prepare('UPDATE ai_video_jobs SET status = "failed", error_text = :error, completed_at = NOW() WHERE id = :id')->execute([
                    'error' => mb_substr($error !== '' ? $error : 'Провайдер не смог создать видео.', 0, 1000, 'UTF-8'),
                    'id' => (int)$job['id'],
                ]);
                $result['failed']++;
                continue;
            }
            if (!$done || $url === '') {
                continue;
            }
            $relative = 'ai/video/' . $job['owner_type'] . '/' . (int)$job['owner_id'] . '/job-' . (int)$job['id'] . '.mp4';
            ai_download_generated_video($url, ai_private_storage_root() . '/' . $relative);
            $seconds = ai_estimated_audio_seconds((string)$job['script_text']);
            db()->prepare('UPDATE ai_video_jobs SET output_path = :path, duration_seconds = :seconds, status = "ready", completed_at = NOW() WHERE id = :id')->execute(['path' => $relative, 'seconds' => $seconds, 'id' => (int)$job['id']]);
            $eventType = $job['personalization_level'] === 'personal' ? 'personal_video' : 'video';
            db()->prepare('INSERT INTO ai_usage_events (owner_type, owner_id, event_type, quantity, provider, metadata_json) VALUES (:owner_type, :owner_id, :event_type, :quantity, :provider, :metadata)')->execute([
                'owner_type' => $job['owner_type'], 'owner_id' => (int)$job['owner_id'], 'event_type' => $eventType, 'quantity' => $seconds,
                'provider' => $job['provider'], 'metadata' => json_encode(['job_id' => (int)$job['id'], 'provider_job_id' => $job['provider_job_id']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            $result['ready']++;
        } catch (Throwable $error) {
            $expired = strtotime((string)$job['created_at']) < strtotime('-3 days');
            db()->prepare('UPDATE ai_video_jobs SET status = :status, error_text = :error, completed_at = IF(:status2 = "failed", NOW(), completed_at) WHERE id = :id')->execute([
                'status' => $expired ? 'failed' : 'processing',
                'status2' => $expired ? 'failed' : 'processing',
                'error' => mb_substr($error->getMessage(), 0, 1000, 'UTF-8'),
                'id' => (int)$job['id'],
            ]);
            if ($expired) {
                $result['failed']++;
            }
        }
    }
    return $result;
}
