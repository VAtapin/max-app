<?php

function telegram_oidc_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('swpro_telegram_oidc');
    session_set_cookie_params([
        'lifetime' => 900,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function oidc_base64url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function oidc_base64url_decode(string $value): string
{
    $padding = strlen($value) % 4;
    if ($padding) {
        $value .= str_repeat('=', 4 - $padding);
    }
    return (string)base64_decode(strtr($value, '-_', '+/'), true);
}

function oidc_asn1_length(int $length): string
{
    if ($length < 128) {
        return chr($length);
    }
    $bytes = '';
    while ($length > 0) {
        $bytes = chr($length & 0xff) . $bytes;
        $length >>= 8;
    }
    return chr(0x80 | strlen($bytes)) . $bytes;
}

function oidc_asn1_integer(string $value): string
{
    $value = ltrim($value, "\x00");
    if ($value === '' || (ord($value[0]) & 0x80)) {
        $value = "\x00" . $value;
    }
    return "\x02" . oidc_asn1_length(strlen($value)) . $value;
}

function oidc_rsa_public_key(array $jwk): string
{
    $modulus = oidc_base64url_decode((string)($jwk['n'] ?? ''));
    $exponent = oidc_base64url_decode((string)($jwk['e'] ?? ''));
    if ($modulus === '' || $exponent === '') {
        throw new RuntimeException('Invalid Telegram signing key');
    }

    $rsa = oidc_asn1_integer($modulus) . oidc_asn1_integer($exponent);
    $rsa = "\x30" . oidc_asn1_length(strlen($rsa)) . $rsa;
    $algorithm = hex2bin('300d06092a864886f70d0101010500');
    $bitString = "\x03" . oidc_asn1_length(strlen($rsa) + 1) . "\x00" . $rsa;
    $publicKey = "\x30" . oidc_asn1_length(strlen($algorithm . $bitString)) . $algorithm . $bitString;

    return "-----BEGIN PUBLIC KEY-----\n"
        . chunk_split(base64_encode($publicKey), 64, "\n")
        . "-----END PUBLIC KEY-----\n";
}

function telegram_oidc_http_json(string $url, array $options = []): array
{
    $context = stream_context_create([
        'http' => array_merge([
            'method' => 'GET',
            'timeout' => 10,
            'ignore_errors' => true,
        ], $options),
    ]);
    $raw = @file_get_contents($url, false, $context);
    $decoded = $raw ? json_decode($raw, true) : null;
    if (!is_array($decoded)) {
        throw new RuntimeException('Telegram OpenID Connect is unavailable');
    }
    return $decoded;
}

function telegram_oidc_verify_id_token(string $token, string $clientId, string $nonce): array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        throw new RuntimeException('Invalid Telegram ID token');
    }
    [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
    $header = json_decode(oidc_base64url_decode($encodedHeader), true);
    $claims = json_decode(oidc_base64url_decode($encodedPayload), true);
    if (!is_array($header) || !is_array($claims) || ($header['alg'] ?? '') !== 'RS256') {
        throw new RuntimeException('Unsupported Telegram ID token');
    }

    $jwks = telegram_oidc_http_json('https://oauth.telegram.org/.well-known/jwks.json');
    $key = null;
    foreach ($jwks['keys'] ?? [] as $candidate) {
        if (($candidate['kid'] ?? null) === ($header['kid'] ?? null) && ($candidate['kty'] ?? '') === 'RSA') {
            $key = $candidate;
            break;
        }
    }
    if (!$key) {
        throw new RuntimeException('Telegram signing key is missing');
    }

    $verified = openssl_verify(
        $encodedHeader . '.' . $encodedPayload,
        oidc_base64url_decode($encodedSignature),
        oidc_rsa_public_key($key),
        OPENSSL_ALGO_SHA256
    );
    if ($verified !== 1) {
        throw new RuntimeException('Telegram ID token signature is invalid');
    }

    $audience = $claims['aud'] ?? null;
    $audienceValid = is_array($audience)
        ? in_array($clientId, array_map('strval', $audience), true)
        : hash_equals($clientId, (string)$audience);
    $nonceValid = !isset($claims['nonce']) || hash_equals($nonce, (string)$claims['nonce']);
    if (($claims['iss'] ?? '') !== 'https://oauth.telegram.org'
        || !$audienceValid
        || (int)($claims['exp'] ?? 0) < time()
        || !$nonceValid) {
        throw new RuntimeException('Telegram ID token claims are invalid');
    }

    return $claims;
}

function telegram_oidc_redirect_uri(array $config): string
{
    $configured = trim((string)($config['integrations']['telegram_oidc_redirect_uri'] ?? ''));
    if ($configured !== '') {
        return $configured;
    }
    return rtrim((string)($config['app']['public_url'] ?? 'https://swpro.ru'), '/')
        . '/api/telegram_oidc_callback.php';
}
