<?php
// ============================================================
// KCALS - Google Sync helpers
// Phase 1: OAuth connection storage only.
// ============================================================

require_once __DIR__ . '/auth.php';

if (file_exists(__DIR__ . '/../config/google.php')) {
    require_once __DIR__ . '/../config/google.php';
}

function googleSyncConfig(): array {
    return [
        'client_id'      => defined('GOOGLE_CLIENT_ID') ? trim((string) GOOGLE_CLIENT_ID) : '',
        'client_secret'  => defined('GOOGLE_CLIENT_SECRET') ? trim((string) GOOGLE_CLIENT_SECRET) : '',
        'redirect_uri'   => defined('GOOGLE_REDIRECT_URI') ? trim((string) GOOGLE_REDIRECT_URI) : '',
        'encryption_key' => defined('GOOGLE_TOKEN_ENCRYPTION_KEY') ? trim((string) GOOGLE_TOKEN_ENCRYPTION_KEY) : '',
    ];
}

function googleSyncIsConfigured(): bool {
    $cfg = googleSyncConfig();
    return $cfg['client_id'] !== '' && $cfg['client_secret'] !== '' && strlen($cfg['encryption_key']) >= 32;
}

function googleSyncAbsoluteUrl(string $path): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . BASE_URL . $path;
}

function googleSyncRedirectUri(): string {
    $cfg = googleSyncConfig();
    return $cfg['redirect_uri'] !== '' ? $cfg['redirect_uri'] : googleSyncAbsoluteUrl('/google_callback.php');
}

function googleSyncScopes(): array {
    return [
        'openid',
        'email',
        'profile',
        'https://www.googleapis.com/auth/drive.appdata',
    ];
}

function googleSyncBuildAuthUrl(): string {
    $cfg = googleSyncConfig();
    $state = bin2hex(random_bytes(24));
    $_SESSION['google_oauth_state'] = $state;

    $params = [
        'client_id'     => $cfg['client_id'],
        'redirect_uri'  => googleSyncRedirectUri(),
        'response_type' => 'code',
        'scope'         => implode(' ', googleSyncScopes()),
        'access_type'   => 'offline',
        'prompt'        => 'consent',
        'state'         => $state,
    ];

    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

function googleSyncTokenKey(): string {
    $cfg = googleSyncConfig();
    return hash('sha256', $cfg['encryption_key'], true);
}

function googleSyncEncrypt(?string $value): ?string {
    if ($value === null || $value === '') return null;
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($value, 'aes-256-gcm', googleSyncTokenKey(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) {
        throw new RuntimeException('Unable to encrypt Google token.');
    }
    return base64_encode(json_encode([
        'v' => 1,
        'iv' => base64_encode($iv),
        'tag' => base64_encode($tag),
        'data' => base64_encode($cipher),
    ]));
}

function googleSyncDecrypt(?string $payload): ?string {
    if ($payload === null || $payload === '') return null;
    $json = json_decode(base64_decode($payload, true) ?: '', true);
    if (!is_array($json) || ($json['v'] ?? null) !== 1) return null;
    $plain = openssl_decrypt(
        base64_decode((string) $json['data']),
        'aes-256-gcm',
        googleSyncTokenKey(),
        OPENSSL_RAW_DATA,
        base64_decode((string) $json['iv']),
        base64_decode((string) $json['tag'])
    );
    return $plain === false ? null : $plain;
}

function googleSyncHttpPost(string $url, array $fields): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        throw new RuntimeException('Google request failed: ' . $err);
    }
    $data = json_decode($body, true);
    if ($status < 200 || $status >= 300 || !is_array($data)) {
        throw new RuntimeException('Google returned an OAuth error.');
    }
    return $data;
}

function googleSyncHttpGetJson(string $url, string $accessToken): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Authorization: Bearer ' . $accessToken,
        ],
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        throw new RuntimeException('Google request failed: ' . $err);
    }
    $data = json_decode($body, true);
    if ($status < 200 || $status >= 300 || !is_array($data)) {
        throw new RuntimeException('Google profile request failed.');
    }
    return $data;
}

function googleSyncExchangeCode(string $code): array {
    $cfg = googleSyncConfig();
    return googleSyncHttpPost('https://oauth2.googleapis.com/token', [
        'code' => $code,
        'client_id' => $cfg['client_id'],
        'client_secret' => $cfg['client_secret'],
        'redirect_uri' => googleSyncRedirectUri(),
        'grant_type' => 'authorization_code',
    ]);
}

function googleSyncSaveConnection(int $userId, array $token, array $profile): void {
    $db = getDB();
    $existing = googleSyncGetConnection($userId);
    $refreshCipher = array_key_exists('refresh_token', $token)
        ? googleSyncEncrypt((string) $token['refresh_token'])
        : ($existing['refresh_token_cipher'] ?? null);
    if ($refreshCipher === null) {
        throw new RuntimeException('Google did not return a refresh token.');
    }
    $expiresAt = date('Y-m-d H:i:s', time() + max(60, (int) ($token['expires_in'] ?? 3600)));

    $stmt = $db->prepare("
        INSERT INTO google_accounts
            (user_id, google_sub, google_email, google_name, scopes, access_token_cipher, refresh_token_cipher, token_type, expires_at, connected_at, updated_at)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            google_sub = VALUES(google_sub),
            google_email = VALUES(google_email),
            google_name = VALUES(google_name),
            scopes = VALUES(scopes),
            access_token_cipher = VALUES(access_token_cipher),
            refresh_token_cipher = VALUES(refresh_token_cipher),
            token_type = VALUES(token_type),
            expires_at = VALUES(expires_at),
            updated_at = NOW()
    ");
    $stmt->execute([
        $userId,
        (string) ($profile['sub'] ?? ''),
        (string) ($profile['email'] ?? ''),
        (string) ($profile['name'] ?? ''),
        (string) ($token['scope'] ?? implode(' ', googleSyncScopes())),
        googleSyncEncrypt((string) ($token['access_token'] ?? '')),
        $refreshCipher,
        (string) ($token['token_type'] ?? 'Bearer'),
        $expiresAt,
    ]);
}

function googleSyncGetConnection(int $userId): ?array {
    try {
        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM google_accounts WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

function googleSyncDisconnect(int $userId): void {
    $db = getDB();
    $db->prepare('DELETE FROM google_accounts WHERE user_id = ?')->execute([$userId]);
}
