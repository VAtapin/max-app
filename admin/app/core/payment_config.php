<?php

function payment_config_key(): string
{
    $config = app_config();
    return hash('sha256', (string)($config['db']['password'] ?? '') . '|' . (string)($config['app']['public_url'] ?? ''), true);
}

function payment_config_encode(array $config): ?string
{
    if (!$config) return null;
    if (!function_exists('openssl_encrypt')) {
        throw new RuntimeException('Для безопасного хранения платёжных ключей требуется OpenSSL.');
    }
    $nonce = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt(
        json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'aes-256-gcm', payment_config_key(), OPENSSL_RAW_DATA, $nonce, $tag
    );
    if ($ciphertext === false) throw new RuntimeException('Не удалось зашифровать настройки оплаты.');
    return 'enc:v1:' . base64_encode($nonce . $tag . $ciphertext);
}

function payment_config_decode(?string $stored): array
{
    $stored = (string)$stored;
    if ($stored === '') return [];
    if (!str_starts_with($stored, 'enc:v1:')) {
        $legacy = json_decode($stored, true);
        return is_array($legacy) ? $legacy : [];
    }
    $raw = base64_decode(substr($stored, 7), true);
    if ($raw === false || strlen($raw) < 29) return [];
    $nonce = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', payment_config_key(), OPENSSL_RAW_DATA, $nonce, $tag);
    if ($plain === false) return [];
    $config = json_decode($plain, true);
    return is_array($config) ? $config : [];
}
