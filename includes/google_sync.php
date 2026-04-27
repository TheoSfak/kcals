<?php
// ============================================================
// KCALS - Google Sync helpers
// Google OAuth connection + private Drive appData backups.
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
        googleSyncCalendarScope(),
    ];
}

function googleSyncCalendarScope(): string {
    return 'https://www.googleapis.com/auth/calendar.events';
}

function googleSyncHasScope(?array $connection, string $scope): bool {
    if (!$connection) return false;
    $scopes = preg_split('/\s+/', trim((string) ($connection['scopes'] ?? ''))) ?: [];
    return in_array($scope, $scopes, true);
}

function googleSyncHasCalendarScope(?array $connection): bool {
    return googleSyncHasScope($connection, googleSyncCalendarScope());
}

function googleSyncCalendarReminderModes(): array {
    return ['previous_evening', 'same_day', 'none'];
}

function googleSyncSaveCalendarSettings(int $userId, string $reminderMode): void {
    if (!in_array($reminderMode, googleSyncCalendarReminderModes(), true)) {
        $reminderMode = 'previous_evening';
    }
    getDB()->prepare('
        UPDATE google_accounts
        SET calendar_reminder_mode = ?, updated_at = NOW()
        WHERE user_id = ?
    ')->execute([$reminderMode, $userId]);
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

function googleSyncHttpJson(string $method, string $url, string $accessToken, array $payload): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
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
        throw new RuntimeException('Google JSON request failed.');
    }
    return $data;
}

function googleSyncHttpMultipart(string $method, string $url, string $accessToken, array $metadata, string $jsonBody): array {
    $boundary = 'kcals_' . bin2hex(random_bytes(12));
    $body = "--$boundary\r\n"
        . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
        . json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\r\n"
        . "--$boundary\r\n"
        . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
        . $jsonBody . "\r\n"
        . "--$boundary--";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: multipart/related; boundary=' . $boundary,
        ],
    ]);
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('Google Drive upload failed: ' . $err);
    }
    $data = json_decode($response, true);
    if ($status === 404) {
        return ['_missing' => true];
    }
    if ($status < 200 || $status >= 300 || !is_array($data)) {
        throw new RuntimeException('Google Drive upload failed.');
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

function googleSyncHttpGetRaw(string $url, string $accessToken): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
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
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException('Google Drive download failed.');
    }
    return (string) $body;
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

function googleSyncUpdateAccessToken(int $userId, array $token): void {
    $expiresAt = date('Y-m-d H:i:s', time() + max(60, (int) ($token['expires_in'] ?? 3600)));
    $stmt = getDB()->prepare("
        UPDATE google_accounts
        SET access_token_cipher = ?, token_type = ?, expires_at = ?, updated_at = NOW()
        WHERE user_id = ?
    ");
    $stmt->execute([
        googleSyncEncrypt((string) ($token['access_token'] ?? '')),
        (string) ($token['token_type'] ?? 'Bearer'),
        $expiresAt,
        $userId,
    ]);
}

function googleSyncRefreshAccessToken(int $userId, array $connection): string {
    $refreshToken = googleSyncDecrypt($connection['refresh_token_cipher'] ?? null);
    if (!$refreshToken) {
        throw new RuntimeException('Google refresh token is missing.');
    }

    $cfg = googleSyncConfig();
    $token = googleSyncHttpPost('https://oauth2.googleapis.com/token', [
        'client_id' => $cfg['client_id'],
        'client_secret' => $cfg['client_secret'],
        'refresh_token' => $refreshToken,
        'grant_type' => 'refresh_token',
    ]);
    $accessToken = (string) ($token['access_token'] ?? '');
    if ($accessToken === '') {
        throw new RuntimeException('Google did not return a refreshed access token.');
    }
    googleSyncUpdateAccessToken($userId, $token);
    return $accessToken;
}

function googleSyncAccessToken(int $userId, array $connection): string {
    $expiresAt = strtotime((string) ($connection['expires_at'] ?? '')) ?: 0;
    $accessToken = googleSyncDecrypt($connection['access_token_cipher'] ?? null);
    if ($accessToken && $expiresAt > time() + 120) {
        return $accessToken;
    }
    return googleSyncRefreshAccessToken($userId, $connection);
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

function googleSyncTableExists(string $table): bool {
    try {
        $stmt = getDB()->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return false;
    }
}

function googleSyncRows(string $sql, array $params = []): array {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function googleSyncBuildBackupSnapshot(int $userId): array {
    $db = getDB();

    $userStmt = $db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch() ?: [];
    unset($user['password_hash']);

    $snapshot = [
        'meta' => [
            'app' => 'KCALS',
            'version' => trim((string) @file_get_contents(__DIR__ . '/../VERSION')),
            'exported_at' => gmdate('c'),
            'format' => 'kcals-google-drive-backup-v1',
        ],
        'user' => $user,
        'progress' => googleSyncRows('SELECT * FROM user_progress WHERE user_id = ? ORDER BY entry_date ASC, id ASC', [$userId]),
        'weekly_plans' => googleSyncRows('SELECT * FROM weekly_plans WHERE user_id = ? ORDER BY created_at ASC, id ASC', [$userId]),
        'food_exclusions' => googleSyncRows('
            SELECT ufe.food_id, f.name_en, f.name_el, NULL AS created_at
            FROM user_food_exclusions ufe
            LEFT JOIN foods f ON f.id = ufe.food_id
            WHERE ufe.user_id = ?
            ORDER BY f.name_en, ufe.food_id
        ', [$userId]),
        'food_inclusions' => googleSyncRows('
            SELECT ufi.food_id, f.name_en, f.name_el, ufi.created_at
            FROM user_food_inclusions ufi
            LEFT JOIN foods f ON f.id = ufi.food_id
            WHERE ufi.user_id = ?
            ORDER BY f.name_en, ufi.food_id
        ', [$userId]),
    ];

    if (googleSyncTableExists('user_achievements')) {
        $snapshot['achievements'] = googleSyncRows(
            'SELECT achievement_slug, earned_at FROM user_achievements WHERE user_id = ? ORDER BY earned_at ASC',
            [$userId]
        );
    }

    return $snapshot;
}

function googleSyncUploadDriveBackup(int $userId, string $accessToken, array $snapshot, ?string $fileId = null): array {
    $json = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false) {
        throw new RuntimeException('Unable to encode KCALS backup.');
    }

    $metadata = [
        'name' => 'kcals-backup.json',
        'mimeType' => 'application/json',
    ];

    if ($fileId) {
        $url = 'https://www.googleapis.com/upload/drive/v3/files/' . rawurlencode($fileId)
            . '?uploadType=multipart&fields=id,name,modifiedTime';
        $updated = googleSyncHttpMultipart('PATCH', $url, $accessToken, $metadata, $json);
        if (empty($updated['_missing'])) {
            return $updated;
        }
    }

    $metadata['parents'] = ['appDataFolder'];
    $url = 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,name,modifiedTime';
    return googleSyncHttpMultipart('POST', $url, $accessToken, $metadata, $json);
}

function googleSyncFindDriveBackupFile(string $accessToken): ?array {
    $query = "name = 'kcals-backup.json' and 'appDataFolder' in parents and trashed = false";
    $url = 'https://www.googleapis.com/drive/v3/files?spaces=appDataFolder&fields=files(id,name,modifiedTime,size)&q='
        . rawurlencode($query);
    $result = googleSyncHttpGetJson($url, $accessToken);
    $files = $result['files'] ?? [];
    return is_array($files) && !empty($files[0]) && is_array($files[0]) ? $files[0] : null;
}

function googleSyncDownloadDriveBackup(int $userId): array {
    if (!googleSyncIsConfigured()) {
        throw new RuntimeException('Google Sync is not configured.');
    }
    $connection = googleSyncGetConnection($userId);
    if (!$connection) {
        throw new RuntimeException('Google account is not connected.');
    }

    $accessToken = googleSyncAccessToken($userId, $connection);
    $fileId = (string) ($connection['drive_backup_file_id'] ?? '');
    $file = null;
    if ($fileId === '') {
        $file = googleSyncFindDriveBackupFile($accessToken);
        $fileId = (string) ($file['id'] ?? '');
        if ($fileId !== '') {
            googleSyncMarkBackup($userId, (string) ($connection['sync_status'] ?? 'connected'), $fileId, null);
        }
    }
    if ($fileId === '') {
        throw new RuntimeException('No KCALS Google Drive backup was found.');
    }

    $body = googleSyncHttpGetRaw(
        'https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId) . '?alt=media',
        $accessToken
    );
    $snapshot = json_decode($body, true);
    if (!is_array($snapshot) || ($snapshot['meta']['format'] ?? '') !== 'kcals-google-drive-backup-v1') {
        throw new RuntimeException('The Google Drive backup format is not supported.');
    }

    return [
        'file' => $file ?: ['id' => $fileId],
        'snapshot' => $snapshot,
    ];
}

function googleSyncBackupSummary(array $snapshot): array {
    return [
        'exported_at' => (string) ($snapshot['meta']['exported_at'] ?? ''),
        'version' => (string) ($snapshot['meta']['version'] ?? ''),
        'email' => (string) ($snapshot['user']['email'] ?? ''),
        'progress_count' => count((array) ($snapshot['progress'] ?? [])),
        'plans_count' => count((array) ($snapshot['weekly_plans'] ?? [])),
        'exclusions_count' => count((array) ($snapshot['food_exclusions'] ?? [])),
        'inclusions_count' => count((array) ($snapshot['food_inclusions'] ?? [])),
        'achievements_count' => count((array) ($snapshot['achievements'] ?? [])),
    ];
}

function googleSyncPreviewDriveBackup(int $userId): array {
    $download = googleSyncDownloadDriveBackup($userId);
    return googleSyncBackupSummary($download['snapshot']);
}

function googleSyncValidFoodIds(array $rows): array {
    $ids = array_values(array_unique(array_filter(array_map(
        fn($row) => (int) ($row['food_id'] ?? 0),
        $rows
    ), fn($id) => $id > 0)));
    if (!$ids) return [];
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $stmt = getDB()->prepare("SELECT id FROM foods WHERE id IN ($ph)");
    $stmt->execute($ids);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function googleSyncRestoreBackupSnapshot(int $userId, array $snapshot): array {
    if (($snapshot['meta']['format'] ?? '') !== 'kcals-google-drive-backup-v1') {
        throw new RuntimeException('The Google Drive backup format is not supported.');
    }

    $db = getDB();
    $counts = [
        'profile' => 0,
        'progress' => 0,
        'plans' => 0,
        'exclusions' => 0,
        'inclusions' => 0,
        'achievements' => 0,
    ];

    $db->beginTransaction();
    try {
        $user = (array) ($snapshot['user'] ?? []);
        $profileFields = [
            'full_name', 'gender', 'birth_date', 'height_cm', 'activity_level', 'diet_type',
            'food_adventure', 'interview_done', 'allergy_gluten', 'allergy_dairy', 'allergy_nuts',
            'allergy_eggs', 'allergy_shellfish', 'allergy_soy', 'goal_event_name',
            'goal_event_date', 'goal_weight_kg', 'tdee_override', 'tdee_recalibrated_at',
            'tdee_recalibration_delta', 'recharge_day', 'recovery_mode',
        ];
        $set = [];
        $params = [];
        foreach ($profileFields as $field) {
            if (array_key_exists($field, $user)) {
                $set[] = "`$field` = ?";
                $params[] = $user[$field];
            }
        }
        if ($set) {
            $params[] = $userId;
            $db->prepare('UPDATE users SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($params);
            $counts['profile'] = 1;
        }

        $progressStmt = $db->prepare('
            INSERT INTO user_progress
                (user_id, weight_kg, stress_level, motivation_level, energy_level, notes, entry_date, created_at, sleep_level, workout_type, workout_minutes)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                weight_kg = VALUES(weight_kg),
                stress_level = VALUES(stress_level),
                motivation_level = VALUES(motivation_level),
                energy_level = VALUES(energy_level),
                notes = VALUES(notes),
                sleep_level = VALUES(sleep_level),
                workout_type = VALUES(workout_type),
                workout_minutes = VALUES(workout_minutes)
        ');
        foreach ((array) ($snapshot['progress'] ?? []) as $row) {
            if (empty($row['entry_date']) || !isset($row['weight_kg'])) continue;
            $progressStmt->execute([
                $userId,
                $row['weight_kg'],
                $row['stress_level'] ?? 5,
                $row['motivation_level'] ?? 5,
                $row['energy_level'] ?? 5,
                $row['notes'] ?? null,
                $row['entry_date'],
                $row['created_at'] ?? date('Y-m-d H:i:s'),
                $row['sleep_level'] ?? 5,
                $row['workout_type'] ?? null,
                $row['workout_minutes'] ?? 0,
            ]);
            $counts['progress']++;
        }

        $planFind = $db->prepare('SELECT id FROM weekly_plans WHERE user_id = ? AND start_date = ? AND end_date = ? AND created_at = ? LIMIT 1');
        $planInsert = $db->prepare('
            INSERT INTO weekly_plans (user_id, start_date, end_date, target_calories, zone, plan_data_json, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $planUpdate = $db->prepare('
            UPDATE weekly_plans
            SET target_calories = ?, zone = ?, plan_data_json = ?
            WHERE id = ? AND user_id = ?
        ');
        foreach ((array) ($snapshot['weekly_plans'] ?? []) as $row) {
            if (empty($row['start_date']) || empty($row['end_date']) || empty($row['plan_data_json'])) continue;
            $createdAt = $row['created_at'] ?? date('Y-m-d H:i:s');
            $zone = in_array((string) ($row['zone'] ?? 'yellow'), ['green', 'yellow', 'red'], true) ? $row['zone'] : 'yellow';
            $planFind->execute([$userId, $row['start_date'], $row['end_date'], $createdAt]);
            $existingId = (int) ($planFind->fetchColumn() ?: 0);
            if ($existingId > 0) {
                $planUpdate->execute([
                    (int) ($row['target_calories'] ?? 0),
                    $zone,
                    $row['plan_data_json'],
                    $existingId,
                    $userId,
                ]);
            } else {
                $planInsert->execute([
                    $userId,
                    $row['start_date'],
                    $row['end_date'],
                    (int) ($row['target_calories'] ?? 0),
                    $zone,
                    $row['plan_data_json'],
                    $createdAt,
                ]);
            }
            $counts['plans']++;
        }

        $validExclusions = googleSyncValidFoodIds((array) ($snapshot['food_exclusions'] ?? []));
        if ($validExclusions) {
            $stmt = $db->prepare('INSERT IGNORE INTO user_food_exclusions (user_id, food_id) VALUES (?, ?)');
            foreach ($validExclusions as $foodId) {
                $stmt->execute([$userId, $foodId]);
                $counts['exclusions']++;
            }
        }

        $validInclusions = googleSyncValidFoodIds((array) ($snapshot['food_inclusions'] ?? []));
        if ($validInclusions) {
            $stmt = $db->prepare('INSERT IGNORE INTO user_food_inclusions (user_id, food_id) VALUES (?, ?)');
            foreach ($validInclusions as $foodId) {
                $stmt->execute([$userId, $foodId]);
                $counts['inclusions']++;
            }
        }

        if (googleSyncTableExists('user_achievements')) {
            $stmt = $db->prepare('INSERT IGNORE INTO user_achievements (user_id, achievement_slug, earned_at) VALUES (?, ?, ?)');
            foreach ((array) ($snapshot['achievements'] ?? []) as $row) {
                $slug = (string) ($row['achievement_slug'] ?? '');
                if ($slug === '') continue;
                $stmt->execute([$userId, $slug, $row['earned_at'] ?? date('Y-m-d H:i:s')]);
                $counts['achievements']++;
            }
        }

        $db->commit();
        return $counts;
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function googleSyncRestoreDriveBackup(int $userId): array {
    $download = googleSyncDownloadDriveBackup($userId);
    $counts = googleSyncRestoreBackupSnapshot($userId, $download['snapshot']);
    getDB()->prepare('UPDATE google_accounts SET sync_status = ?, last_sync_error = NULL, updated_at = NOW() WHERE user_id = ?')
        ->execute(['restore_ok', $userId]);
    return $counts;
}

function googleSyncMarkBackup(int $userId, string $status, ?string $fileId = null, ?string $error = null): void {
    $sets = ['sync_status = ?', 'updated_at = NOW()'];
    $params = [$status];
    if ($status === 'backup_ok') {
        $sets[] = 'last_sync_at = NOW()';
        $sets[] = 'last_sync_error = NULL';
    }
    if ($fileId !== null) {
        $sets[] = 'drive_backup_file_id = ?';
        $params[] = $fileId;
    }
    if ($error !== null) {
        $sets[] = 'last_sync_error = ?';
        $params[] = mb_substr($error, 0, 1000);
    }
    $params[] = $userId;
    getDB()->prepare('UPDATE google_accounts SET ' . implode(', ', $sets) . ' WHERE user_id = ?')->execute($params);
}

function googleSyncBackupNow(int $userId): array {
    if (!googleSyncIsConfigured()) {
        throw new RuntimeException('Google Sync is not configured.');
    }
    $connection = googleSyncGetConnection($userId);
    if (!$connection) {
        throw new RuntimeException('Google account is not connected.');
    }

    try {
        $accessToken = googleSyncAccessToken($userId, $connection);
        $snapshot = googleSyncBuildBackupSnapshot($userId);
        $file = googleSyncUploadDriveBackup(
            $userId,
            $accessToken,
            $snapshot,
            $connection['drive_backup_file_id'] ?? null
        );
        googleSyncMarkBackup($userId, 'backup_ok', (string) ($file['id'] ?? ''), null);
        return $file;
    } catch (Throwable $e) {
        googleSyncMarkBackup($userId, 'backup_error', null, $e->getMessage());
        throw $e;
    }
}
