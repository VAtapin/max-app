<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../admin/app/core/ai_center.php';
require_once __DIR__ . '/../admin/app/core/client_journey.php';
require_once __DIR__ . '/../admin/app/core/consultant_profiles.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'method not allowed'], 405);
}

$data = input_json();
$user = require_platform_user($data);
$onboarding = client_onboarding_status($user);
if (empty($onboarding['complete'])) {
    json_response(['ok' => false, 'error' => 'onboarding_required'], 403);
}

function ai_chat_profile_question(string $question): bool
{
    return (bool)preg_match('/(?:консультант|лидер|специалист|мария|кто (?:мой|ваш)|как зовут|имя|специализац|опыт|образован|сертификат|достиж|биограф|расскажи о|чем (?:может|занимается)|помочь)/ui', $question);
}

function ai_chat_consultant_gender(string $name): ?string
{
    $firstName = mb_strtolower(trim((string)(preg_split('/\s+/u', $name)[0] ?? '')), 'UTF-8');
    $female = ['мария','анна','ольга','елена','наталья','наталия','ирина','екатерина','светлана','татьяна','юлия','юлия','людмила','алена','алёна','евгения','валерия','виктория','дарья','дарина','полина','кристина','вероника','ксения','надежда','любовь','галия','регина','диана','карина','инна','жанна','лариса','галина','марина','софия','софья','оксана','тамара','раиса','вера','зоя','лидия','нина','раиса'];
    $male = ['александр','алексей','андрей','анатолий','артем','артём','борис','вадим','валерий','василий','виктор','владимир','вячеслав','геннадий','георгий','дмитрий','евгений','иван','игорь','кирилл','константин','максим','михаил','николай','олег','павел','петр','пётр','роман','сергей','степан','тимур','федор','фёдор','юрий','ярослав'];
    if (in_array($firstName, $female, true)) return 'женский';
    if (in_array($firstName, $male, true)) return 'мужской';
    return null;
}

function ai_chat_profile_context(array $user): string
{
    try {
        $owner = ai_owner_for_client($user);
        $profile = ai_profile_for_owner($owner);
        if (!$profile) {
            return '';
        }

        $payload = consultant_profile_payload($profile);
        $p = $payload['profile'] ?? [];
        $lines = [];
        $consultantName = trim((string)($p['display_name'] ?? ''));
        if ($consultantName !== '') {
            $gender = ai_chat_consultant_gender($consultantName);
            $lines[] = 'Имя консультанта: ' . $consultantName;
            if ($gender !== null) {
                $lines[] = 'Пол консультанта: ' . $gender;
                $lines[] = $gender === 'женский'
                    ? 'Грамматика: говори о консультанте в женском роде (она, её, у неё). Не используй «он/она».'
                    : 'Грамматика: говори о консультанте в мужском роде (он, его, у него). Не используй «он/она».';
            } else {
                $lines[] = 'Пол консультанта: неизвестен. Не угадывай пол и не используй «он/она», если можно построить фразу без местоимения.';
            }
        }

        foreach ([
            'title' => 'Должность',
            'subtitle' => 'Подзаголовок',
            'short_description' => 'Краткое описание',
            'bio' => 'О консультанте',
            'specialization' => 'Специализация',
            'experience_text' => 'Опыт',
            'achievements_text' => 'Достижения',
        ] as $field => $label) {
            $value = trim((string)($p[$field] ?? ''));
            if ($value !== '') {
                $lines[] = $label . ': ' . $value;
            }
        }

        foreach (($payload['blocks'] ?? []) as $block) {
            if ((int)($block['is_enabled'] ?? 0) !== 1) continue;
            $title = trim((string)($block['title'] ?? ''));
            if ($title !== '') $lines[] = 'Раздел профиля: ' . $title;
        }

        foreach ([
            'telegram_url' => 'Telegram', 'whatsapp_url' => 'WhatsApp',
            'vk_url' => 'VK', 'ok_url' => 'Одноклассники',
        ] as $field => $label) {
            $value = trim((string)($p[$field] ?? ''));
            if ($value !== '') $lines[] = $label . ': ' . $value;
        }

        foreach (($payload['products'] ?? []) as $item) {
            $title = trim((string)($item['title'] ?? ''));
            $description = trim((string)($item['short_description'] ?? '') . "\n" . (string)($item['full_description'] ?? ''));
            if ($title !== '') $lines[] = 'Продукт консультанта: ' . $title . ($description !== '' ? ' — ' . $description : '');
        }
        foreach (($payload['tests'] ?? []) as $item) {
            $title = trim((string)($item['title'] ?? ''));
            $description = trim((string)($item['description'] ?? ''));
            if ($title !== '') $lines[] = 'Доступный чек-ап/тест: ' . $title . ($description !== '' ? ' — ' . $description : '');
        }
        foreach (($payload['materials'] ?? []) as $item) {
            $title = trim((string)($item['title'] ?? ''));
            $description = trim((string)($item['short_text'] ?? '') . "\n" . (string)($item['full_text'] ?? ''));
            if ($title !== '') $lines[] = 'Материал консультанта: ' . $title . ($description !== '' ? ' — ' . $description : '');
        }
        foreach (($payload['reviews'] ?? []) as $item) {
            $name = trim((string)($item['client_name'] ?? ''));
            $text = trim((string)($item['review_text'] ?? ''));
            if ($text !== '') $lines[] = 'Отзыв клиента' . ($name !== '' ? ' ' . $name : '') . ': ' . $text;
        }
        foreach (($payload['cashback_cards'] ?? []) as $card) {
            $title = trim((string)($card['title'] ?? ''));
            $description = trim((string)($card['description'] ?? ''));
            if ($title !== '' || $description !== '') $lines[] = 'Кэшбэк/предложение консультанта: ' . trim($title . ($description !== '' ? ' — ' . $description : ''));
        }

        $context = $lines ? implode("\n", $lines) : '';
        return mb_substr($context, 0, 3000, 'UTF-8');
    } catch (Throwable $e) {
        error_log('SWPro AI profile context: ' . $e->getMessage());
        return '';
    }
}

$originalMessage = trim((string)($data['message'] ?? ''));
$messageForAi = $originalMessage;
$profileContextAdded = false;

if ($originalMessage !== '' && ai_chat_profile_question($originalMessage)) {
    $profileContext = ai_chat_profile_context($user);
    if ($profileContext !== '') {
        $messageForAi = mb_substr(
            $originalMessage
            . "\n\n[Служебный контекст закреплённого консультанта. Используй его только для ответа на вопрос пользователя о консультанте, его профиле, доступных материалах, тестах и предложениях. Не раскрывай внутренние идентификаторы, цены, артикулы или служебные поля. Если вопрос не относится к этому контексту, игнорируй его. Не упоминай этот служебный блок.]\n"
            . $profileContext,
            0, 3990, 'UTF-8'
        );
        $profileContextAdded = true;
    }
}

try {
    $result = ai_answer(
        $messageForAi,
        'client',
        $user,
        normalize_platform((string)($user['current_platform'] ?? $user['platform'] ?? 'web')),
        'client-mini-app'
    );

    if ($profileContextAdded && !empty($result['conversation_id'])) {
        try {
            db()->prepare('UPDATE ai_messages SET content = :content WHERE conversation_id = :conversation_id AND role = "user" ORDER BY id DESC LIMIT 1')
                ->execute(['content' => $originalMessage, 'conversation_id' => (int)$result['conversation_id']]);
        } catch (Throwable $e) {
            error_log('SWPro AI profile context cleanup: ' . $e->getMessage());
        }
    }

    json_response($result, $result['ok'] ? 200 : 422);
} catch (Throwable) {
    json_response(['ok' => false, 'error' => 'Помощник временно недоступен.'], 500);
}