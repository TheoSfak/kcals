<?php
// ============================================================
// KCALS – User Food Preferences Settings Page
// Allows the user to change adventure level, toggle allergies,
// and search/toggle per-food exclusions after the interview.
// ============================================================
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/google_sync.php';

requireLogin();

$db     = getDB();
$userId = (int) $_SESSION['user_id'];
$user   = getCurrentUser();

$saveSuccess = isset($_GET['saved']);
$errors      = [];
$googleStatus = $_GET['google'] ?? '';

// ======== HANDLE POST ========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = __('err_invalid_submit');
    } else {
        $adventure   = max(0, min(3, (int) ($_POST['food_adventure'] ?? 2)));
        $rechargeDay = max(1, min(7, (int) ($_POST['recharge_day']   ?? 3)));
        $allergyKeys = ['gluten','dairy','nuts','eggs','shellfish','soy'];
        $allergyVals = [];
        foreach ($allergyKeys as $a) {
            $allergyVals['allergy_' . $a] = isset($_POST['allergy_' . $a]) ? 1 : 0;
        }

        $rawIds   = $_POST['excluded_ids'] ?? '';
        $excluded = array_unique(
            array_filter(
                array_map('intval', array_filter(explode(',', $rawIds))),
                fn($id) => $id > 0
            )
        );
        $rawIncludeIds = $_POST['included_ids'] ?? '';
        $included = array_values(array_diff(array_unique(
            array_filter(
                array_map('intval', array_filter(explode(',', $rawIncludeIds))),
                fn($id) => $id > 0
            )
        ), $excluded));

        try {
            $setAllergy = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($allergyVals)));
            $stmt = $db->prepare("
                UPDATE `users`
                SET `food_adventure` = ?, `recharge_day` = ?, `interview_done` = 1, $setAllergy
                WHERE `id` = ?
            ");
            $stmt->execute(array_merge([$adventure, $rechargeDay], array_values($allergyVals), [$userId]));

            $db->prepare('DELETE FROM `user_food_exclusions` WHERE `user_id` = ?')->execute([$userId]);
            $db->prepare('DELETE FROM `user_food_inclusions` WHERE `user_id` = ?')->execute([$userId]);

            if (!empty($excluded)) {
                $ph   = implode(',', array_fill(0, count($excluded), '?'));
                $rows = $db->prepare("SELECT `id` FROM `foods` WHERE `id` IN ($ph)");
                $rows->execute($excluded);
                $validIds = array_column($rows->fetchAll(), 'id');

                if (!empty($validIds)) {
                    $insVals   = implode(', ', array_fill(0, count($validIds), '(?,?)'));
                    $insParams = [];
                    foreach ($validIds as $fid) {
                        $insParams[] = $userId;
                        $insParams[] = (int) $fid;
                    }
                    $db->prepare("INSERT IGNORE INTO `user_food_exclusions` (`user_id`,`food_id`) VALUES $insVals")
                       ->execute($insParams);
                }
            }

            if (!empty($included)) {
                $ph   = implode(',', array_fill(0, count($included), '?'));
                $rows = $db->prepare("SELECT `id` FROM `foods` WHERE `id` IN ($ph)");
                $rows->execute($included);
                $validIds = array_column($rows->fetchAll(), 'id');

                if (!empty($validIds)) {
                    $insVals   = implode(', ', array_fill(0, count($validIds), '(?,?)'));
                    $insParams = [];
                    foreach ($validIds as $fid) {
                        $insParams[] = $userId;
                        $insParams[] = (int) $fid;
                    }
                    $db->prepare("INSERT IGNORE INTO `user_food_inclusions` (`user_id`,`food_id`) VALUES $insVals")
                       ->execute($insParams);
                }
            }

            // ---- Event Countdown ----
            if (isset($_POST['action_event'])) {
                if ($_POST['clear_event'] ?? '' === '1') {
                    $db->prepare("UPDATE `users` SET `goal_event_name` = NULL, `goal_event_date` = NULL, `goal_weight_kg` = NULL WHERE `id` = ?")
                       ->execute([$userId]);
                } else {
                    $evName   = trim($_POST['goal_event_name'] ?? '');
                    $evDate   = trim($_POST['goal_event_date'] ?? '');
                    $evWeight = (float) ($_POST['goal_weight_kg'] ?? 0);

                    if ($evDate && $evDate <= date('Y-m-d')) {
                        $errors[] = __('err_event_date');
                    } elseif ($evWeight > 0 && ($evWeight < 30 || $evWeight > 300)) {
                        $errors[] = __('err_event_weight');
                    } else {
                        $db->prepare("UPDATE `users` SET `goal_event_name` = ?, `goal_event_date` = ?, `goal_weight_kg` = ? WHERE `id` = ?")
                           ->execute([
                               $evName ?: null,
                               $evDate ?: null,
                               $evWeight > 0 ? $evWeight : null,
                               $userId,
                           ]);
                    }
                }
            }

            if (empty($errors)) {
                header('Location: ' . BASE_URL . '/settings.php?saved=1');
                exit;
            }

        } catch (PDOException $e) {
            error_log('settings.php save error: ' . $e->getMessage());
            $errors[] = __('pref_err_save');
        }
    }
}

// ======== LOAD DATA ========
$currentAdventure  = (int) ($user['food_adventure'] ?? 2);
$currentRechargeDay = max(1, min(7, (int) ($user['recharge_day'] ?? 3)));
$currentAllergies = [
    'gluten'    => (int) ($user['allergy_gluten']    ?? 0),
    'dairy'     => (int) ($user['allergy_dairy']     ?? 0),
    'nuts'      => (int) ($user['allergy_nuts']      ?? 0),
    'eggs'      => (int) ($user['allergy_eggs']      ?? 0),
    'shellfish' => (int) ($user['allergy_shellfish'] ?? 0),
    'soy'       => (int) ($user['allergy_soy']       ?? 0),
];

$exclStmt = $db->prepare('
    SELECT ufe.food_id, f.name_en, f.name_el
    FROM user_food_exclusions ufe
    JOIN foods f ON f.id = ufe.food_id
    WHERE ufe.user_id = ?
    ORDER BY f.name_en
');
$exclStmt->execute([$userId]);
$currentExclusions = $exclStmt->fetchAll();

$inclStmt = $db->prepare('
    SELECT ufi.food_id, f.name_en, f.name_el
    FROM user_food_inclusions ufi
    JOIN foods f ON f.id = ufi.food_id
    WHERE ufi.user_id = ?
    ORDER BY f.name_en
');
$inclStmt->execute([$userId]);
$currentInclusions = $inclStmt->fetchAll();

$googleSyncConfigured = googleSyncIsConfigured();
$googleConnection = googleSyncGetConnection($userId);
$googleRedirectUri = googleSyncRedirectUri();
$googleRestorePreview = $_SESSION['google_restore_preview'] ?? null;
$googleRestoreCounts = $_SESSION['google_restore_counts'] ?? null;
$googleCalendarSyncCounts = $_SESSION['google_calendar_sync_counts'] ?? null;
$googleCalendarReady = googleSyncHasCalendarScope($googleConnection);
$googleCalendarReminderMode = (string) ($googleConnection['calendar_reminder_mode'] ?? 'previous_evening');
if (!in_array($googleCalendarReminderMode, googleSyncCalendarReminderModes(), true)) {
    $googleCalendarReminderMode = 'previous_evening';
}
unset($_SESSION['google_restore_counts']);
unset($_SESSION['google_calendar_sync_counts']);

$pageTitle = __('settings_title');
$activeNav = 'preferences';
require_once __DIR__ . '/includes/header.php';
?>

<style>
.settings-wrap {
    max-width: 700px;
    margin: 2.5rem auto;
    padding: 0 1.25rem;
}
.settings-card {
    background: #fff;
    border-radius: 14px;
    padding: 1.75rem 2rem;
    box-shadow: 0 2px 16px rgba(0,0,0,.07);
    margin-bottom: 1.5rem;
}
.settings-card h3 {
    font-size: 1rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 1rem;
    display: flex;
    align-items: center;
    gap: .5rem;
}
.adv-grid-sm {
    display: grid;
    grid-template-columns: repeat(4,1fr);
    gap: .75rem;
}
@media (max-width:520px){ .adv-grid-sm { grid-template-columns:1fr; } }
.adv-opt-sm {
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    padding: .875rem .75rem;
    cursor: pointer;
    text-align: center;
    transition: border-color .15s, background .15s;
    user-select: none;
}
.adv-opt-sm:hover { border-color: #2ecc71; }
.adv-opt-sm.selected { border-color: #2ecc71; background: #f0fdf4; }
.adv-opt-sm .emoji { font-size:1.5rem; display:block; margin-bottom:.3rem; }
.adv-opt-sm strong { font-size:.875rem; display:block; color:#1e293b; }

.allergy-row {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
}
.al-chip {
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    padding: .45rem .875rem;
    cursor: pointer;
    font-size: .85rem;
    font-weight: 600;
    color: #374151;
    display: flex;
    align-items: center;
    gap: .35rem;
    transition: border-color .12s, background .12s;
    user-select: none;
}
.al-chip.checked { border-color: #f59e0b; background: #fffbeb; color: #92400e; }

.food-search-box {
    position: relative;
    margin-bottom: .6rem;
}
.food-search-box input {
    width: 100%;
    box-sizing: border-box;
    padding: .55rem .85rem .55rem 2.25rem;
    border: 1px solid #d1d5db;
    border-radius: 7px;
    font-size: .875rem;
    transition: border-color .15s;
}
.food-search-box input:focus { outline:none; border-color:#2ecc71; box-shadow:0 0 0 3px rgba(46,204,113,.15); }
.food-search-box .si { position:absolute;left:.7rem;top:50%;transform:translateY(-50%);color:#9ca3af;pointer-events:none; }
.food-results { max-height:220px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:7px;background:#fff;box-shadow:0 4px 12px rgba(0,0,0,.08);display:none; }
.food-results.visible { display:block; }
.fri { padding:.5rem .875rem;cursor:pointer;font-size:.85rem;color:#374151;transition:background .1s;display:flex;justify-content:space-between;align-items:center; }
.fri:hover { background:#f0fdf4; }
.excl-chips { display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.75rem;min-height:1.5rem; }
.excl-chip { display:flex;align-items:center;gap:.3rem;background:#fee2e2;color:#7f1d1d;border-radius:99px;padding:.28rem .7rem;font-size:.8rem;font-weight:600; }
.excl-chip button { background:none;border:none;cursor:pointer;color:#dc2626;font-size:.9rem;padding:0;line-height:1; }
.incl-chip { background:#dcfce7;color:#14532d; }
.incl-chip button { color:#15803d; }
.no-excl { font-size:.8rem;color:#94a3b8;font-style:italic; }
.btn-save-settings {
    background: #2ecc71;
    color: #fff;
    border: none;
    padding: .7rem 2rem;
    border-radius: 8px;
    font-size: .95rem;
    font-weight: 600;
    cursor: pointer;
    transition: background .15s;
}
.btn-save-settings:hover { background: #27ae60; }
.alert-success { background:#d1fae5;border:1px solid #6ee7b7;border-radius:8px;padding:.75rem 1rem;margin-bottom:1.25rem;font-size:.9rem;color:#065f46; }
.alert-error   { background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;padding:.75rem 1rem;margin-bottom:1.25rem;font-size:.9rem;color:#7f1d1d; }
.google-sync-status { display:flex;align-items:center;gap:.65rem;flex-wrap:wrap;margin-top:1rem; }
.google-sync-pill { display:inline-flex;align-items:center;gap:.35rem;border-radius:99px;padding:.32rem .75rem;font-size:.8rem;font-weight:700; }
.google-sync-pill.ok { background:#dcfce7;color:#166534; }
.google-sync-pill.warn { background:#fef3c7;color:#92400e; }
.google-sync-meta { font-size:.78rem;color:#64748b;margin-top:.85rem;word-break:break-all; }
</style>

<div class="settings-wrap">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;margin-bottom:1.5rem;">
        <div>
            <h1 style="font-size:1.4rem;font-weight:800;color:#1e293b;margin:0 0 .2rem;"><?= __('settings_h1') ?></h1>
            <p style="font-size:.875rem;color:#64748b;margin:0;"><?= __('settings_sub') ?></p>
        </div>
        <a href="<?= BASE_URL ?>/plan.php" style="font-size:.85rem;color:#2ecc71;text-decoration:none;font-weight:600;">
            <?= __('settings_back_plan') ?>
        </a>
    </div>

    <?php if ($saveSuccess): ?>
    <div class="alert-success"><?= __('settings_saved') ?></div>
    <?php endif; ?>
<?php if ($googleStatus === 'connected'): ?>
    <div class="alert-success"><?= __('google_sync_connected') ?></div>
    <?php elseif ($googleStatus === 'disconnected'): ?>
    <div class="alert-success"><?= __('google_sync_disconnected') ?></div>
    <?php elseif ($googleStatus === 'backup_ok'): ?>
    <div class="alert-success"><?= __('google_sync_backup_ok') ?></div>
    <?php elseif ($googleStatus === 'preview_ok'): ?>
    <div class="alert-success"><?= __('google_sync_preview_ok') ?></div>
    <?php elseif ($googleStatus === 'restore_ok'): ?>
    <div class="alert-success"><?= __('google_sync_restore_ok') ?></div>
    <?php elseif ($googleStatus === 'calendar_saved'): ?>
    <div class="alert-success"><?= __('google_calendar_saved') ?></div>
    <?php elseif ($googleStatus === 'calendar_sync_ok'): ?>
    <div class="alert-success"><?= __('google_calendar_sync_ok') ?></div>
    <?php elseif ($googleStatus === 'config'): ?>
    <div class="alert-error"><?= __('google_sync_config_missing') ?></div>
    <?php elseif ($googleStatus === 'not_connected'): ?>
    <div class="alert-error"><?= __('google_sync_not_connected') ?></div>
    <?php elseif ($googleStatus === 'backup_error'): ?>
    <div class="alert-error"><?= __('google_sync_backup_error') ?></div>
    <?php elseif ($googleStatus === 'preview_error'): ?>
    <div class="alert-error"><?= __('google_sync_preview_error') ?></div>
    <?php elseif ($googleStatus === 'restore_error'): ?>
    <div class="alert-error"><?= __('google_sync_restore_error') ?></div>
    <?php elseif ($googleStatus === 'calendar_reconnect'): ?>
    <div class="alert-error"><?= __('google_calendar_reconnect_needed') ?></div>
    <?php elseif ($googleStatus === 'calendar_sync_error'): ?>
    <div class="alert-error"><?= __('google_calendar_sync_error') ?></div>
    <?php elseif ($googleStatus === 'error'): ?>
    <div class="alert-error"><?= __('google_sync_error') ?></div>
    <?php endif; ?>
    <?php foreach ($errors as $e): ?>
    <div class="alert-error"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <div class="settings-card">
        <h3>🔄 <?= __('google_sync_h') ?></h3>
        <p style="font-size:.82rem;color:#64748b;margin:0;">
            <?= __('google_sync_intro') ?>
        </p>
        <div class="google-sync-status">
            <?php if ($googleConnection): ?>
                <span class="google-sync-pill ok">
                    <i data-lucide="check-circle" style="width:14px;height:14px;"></i>
                    <?= __('google_sync_status_connected') ?>
                </span>
                <span style="font-size:.85rem;color:#374151;">
                    <?= htmlspecialchars($googleConnection['google_email'] ?: $googleConnection['google_name'] ?: '') ?>
                </span>
                <form method="POST" action="<?= BASE_URL ?>/google_backup.php" style="margin:0;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i data-lucide="cloud-upload" style="width:13px;height:13px;vertical-align:-2px;margin-right:3px;"></i>
                        <?= __('google_sync_backup_now') ?>
                    </button>
                </form>
                <form method="POST" action="<?= BASE_URL ?>/google_restore_preview.php" style="margin:0;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                    <button type="submit" class="btn btn-outline btn-sm">
                        <i data-lucide="search-check" style="width:13px;height:13px;vertical-align:-2px;margin-right:3px;"></i>
                        <?= __('google_sync_check_backup') ?>
                    </button>
                </form>
                <form method="POST" action="<?= BASE_URL ?>/google_disconnect.php" style="margin:0;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                    <button type="submit" class="btn btn-outline btn-sm" style="color:#dc2626;border-color:#dc2626;">
                        <i data-lucide="unlink" style="width:13px;height:13px;vertical-align:-2px;margin-right:3px;"></i>
                        <?= __('google_sync_disconnect') ?>
                    </button>
                </form>
            <?php elseif ($googleSyncConfigured): ?>
                <span class="google-sync-pill warn">
                    <i data-lucide="plug" style="width:14px;height:14px;"></i>
                    <?= __('google_sync_status_ready') ?>
                </span>
                <a href="<?= BASE_URL ?>/google_connect.php" class="btn btn-primary btn-sm">
                    <i data-lucide="link" style="width:13px;height:13px;vertical-align:-2px;margin-right:3px;"></i>
                    <?= __('google_sync_connect') ?>
                </a>
            <?php else: ?>
                <span class="google-sync-pill warn">
                    <i data-lucide="settings" style="width:14px;height:14px;"></i>
                    <?= __('google_sync_status_config') ?>
                </span>
                <a href="<?= BASE_URL ?>/google_connect.php" class="btn btn-outline btn-sm">
                    <i data-lucide="settings" style="width:13px;height:13px;vertical-align:-2px;margin-right:3px;"></i>
                    <?= __('google_sync_connect') ?>
                </a>
            <?php endif; ?>
        </div>
        <div class="google-sync-meta">
            <?= __('google_sync_phase_note') ?><br>
            <?php if (!$googleSyncConfigured): ?>
                <?= __('google_sync_config_help') ?><br>
            <?php endif; ?>
            <?php if ($googleConnection && !empty($googleConnection['last_sync_at'])): ?>
                <?= __('google_sync_last_backup') ?>: <?= htmlspecialchars(date('d/m/Y H:i', strtotime($googleConnection['last_sync_at']))) ?><br>
            <?php endif; ?>
            <?= __('google_sync_redirect_uri') ?>: <code><?= htmlspecialchars($googleRedirectUri) ?></code>
        </div>
        <div class="google-sync-meta" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:.75rem;margin-top:1rem;">
            <strong style="display:block;color:#1e293b;margin-bottom:.35rem;"><?= __('google_calendar_title') ?></strong>
            <?php if ($googleConnection && $googleCalendarReady): ?>
                <span class="google-sync-pill ok">
                    <i data-lucide="calendar-check" style="width:14px;height:14px;"></i>
                    <?= __('google_calendar_status_ready') ?>
                </span>
                <form method="POST" action="<?= BASE_URL ?>/google_calendar_sync.php" style="display:inline-flex;margin-left:.5rem;"
                      onsubmit="return confirm(<?= htmlspecialchars(json_encode(__('google_calendar_sync_confirm')), ENT_QUOTES) ?>)">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i data-lucide="calendar-plus" style="width:13px;height:13px;vertical-align:-2px;margin-right:3px;"></i>
                        <?= __('google_calendar_sync_now') ?>
                    </button>
                </form>
            <?php elseif ($googleConnection): ?>
                <span class="google-sync-pill warn">
                    <i data-lucide="calendar-plus" style="width:14px;height:14px;"></i>
                    <?= __('google_calendar_status_reconnect') ?>
                </span>
                <a href="<?= BASE_URL ?>/google_connect.php" class="btn btn-primary btn-sm" style="margin-left:.5rem;">
                    <i data-lucide="refresh-cw" style="width:13px;height:13px;vertical-align:-2px;margin-right:3px;"></i>
                    <?= __('google_calendar_reconnect') ?>
                </a>
            <?php else: ?>
                <span class="google-sync-pill warn">
                    <i data-lucide="calendar-plus" style="width:14px;height:14px;"></i>
                    <?= __('google_calendar_status_connect') ?>
                </span>
            <?php endif; ?>
            <?php if ($googleConnection && !empty($googleConnection['calendar_last_sync_at'])): ?>
            <p style="font-size:.78rem;color:#64748b;margin:.7rem 0 0;">
                <?= __('google_calendar_last_sync') ?>: <?= htmlspecialchars(date('d/m/Y H:i', strtotime($googleConnection['calendar_last_sync_at']))) ?>
            </p>
            <?php endif; ?>
            <?php if ($googleConnection && is_array($googleCalendarSyncCounts)): ?>
            <p style="font-size:.78rem;color:#166534;margin:.7rem 0 0;">
                <?= sprintf(
                    __('google_calendar_sync_counts'),
                    (int) $googleCalendarSyncCounts['created'],
                    (int) $googleCalendarSyncCounts['updated'],
                    (int) $googleCalendarSyncCounts['total']
                ) ?>
            </p>
            <?php endif; ?>
            <p style="font-size:.78rem;color:#64748b;margin:.7rem 0 0;">
                <?= __('google_calendar_phase_note') ?>
            </p>
            <?php if ($googleConnection): ?>
            <form method="POST" action="<?= BASE_URL ?>/google_calendar_settings.php" style="margin-top:.75rem;display:flex;align-items:center;gap:.65rem;flex-wrap:wrap;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                <label style="font-size:.78rem;font-weight:700;color:#374151;" for="calendar-reminder-mode">
                    <?= __('google_calendar_reminder_label') ?>
                </label>
                <select id="calendar-reminder-mode" name="calendar_reminder_mode" class="form-control" style="max-width:220px;">
                    <?php foreach (googleSyncCalendarReminderModes() as $mode): ?>
                    <option value="<?= htmlspecialchars($mode) ?>" <?= $googleCalendarReminderMode === $mode ? 'selected' : '' ?>>
                        <?= htmlspecialchars(__('google_calendar_reminder_' . $mode)) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-outline btn-sm">
                    <i data-lucide="save" style="width:13px;height:13px;vertical-align:-2px;margin-right:3px;"></i>
                    <?= __('google_calendar_save') ?>
                </button>
            </form>
            <?php endif; ?>
        </div>
        <?php if ($googleConnection && is_array($googleRestorePreview)): ?>
        <div class="google-sync-meta" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:.75rem;margin-top:1rem;">
            <strong style="display:block;color:#1e293b;margin-bottom:.35rem;"><?= __('google_sync_preview_title') ?></strong>
            <?= sprintf(
                __('google_sync_preview_counts'),
                htmlspecialchars($googleRestorePreview['version'] ?: '-'),
                htmlspecialchars($googleRestorePreview['exported_at'] ?: '-'),
                (int) $googleRestorePreview['progress_count'],
                (int) $googleRestorePreview['plans_count'],
                (int) $googleRestorePreview['exclusions_count'],
                (int) $googleRestorePreview['inclusions_count'],
                (int) $googleRestorePreview['achievements_count']
            ) ?>
            <form method="POST" action="<?= BASE_URL ?>/google_restore.php" style="margin-top:.75rem;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                <input type="hidden" name="confirm_restore" value="1">
                <button type="submit" class="btn btn-outline btn-sm" style="color:#b45309;border-color:#f59e0b;"
                        onclick="return confirm(<?= htmlspecialchars(json_encode(__('google_sync_restore_confirm')), ENT_QUOTES) ?>)">
                    <i data-lucide="rotate-ccw" style="width:13px;height:13px;vertical-align:-2px;margin-right:3px;"></i>
                    <?= __('google_sync_restore_btn') ?>
                </button>
            </form>
        </div>
        <?php endif; ?>
        <?php if ($googleConnection && is_array($googleRestoreCounts)): ?>
        <div class="google-sync-meta" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:.75rem;margin-top:1rem;color:#166534;">
            <?= sprintf(
                __('google_sync_restore_counts'),
                (int) $googleRestoreCounts['progress'],
                (int) $googleRestoreCounts['plans'],
                (int) $googleRestoreCounts['exclusions'],
                (int) $googleRestoreCounts['inclusions'],
                (int) $googleRestoreCounts['achievements']
            ) ?>
        </div>
        <?php endif; ?>
    </div>

    <form method="POST" id="settings-form">
        <input type="hidden" name="csrf_token"   value="<?= csrfToken() ?>">
        <input type="hidden" name="food_adventure" id="s-input-adventure" value="<?= $currentAdventure ?>">
        <input type="hidden" name="excluded_ids"  id="s-input-excluded"  value="<?= htmlspecialchars(implode(',', array_column($currentExclusions, 'food_id'))) ?>">
        <input type="hidden" name="included_ids"  id="s-input-included"  value="<?= htmlspecialchars(implode(',', array_column($currentInclusions, 'food_id'))) ?>">

        <!-- Hidden allergy checkboxes -->
        <?php foreach (['gluten','dairy','nuts','eggs','shellfish','soy'] as $a): ?>
        <input type="checkbox" name="allergy_<?= $a ?>" id="s-hid-allergy-<?= $a ?>"
               style="display:none" <?= $currentAllergies[$a] ? 'checked' : '' ?>>
        <?php endforeach; ?>

        <!-- ===== Cuisine Style ===== -->
        <div class="settings-card">
            <h3>🍽️ <?= __('settings_adventure_h') ?></h3>
            <div class="adv-grid-sm">
                <?php
                $advDefs = [
                    0 => ['emoji'=>'⚡','title'=>__('pref_adv0_title')],
                    1 => ['emoji'=>'🇬🇷','title'=>__('pref_adv1_title')],
                    2 => ['emoji'=>'🌊','title'=>__('pref_adv2_title')],
                    3 => ['emoji'=>'🌍','title'=>__('pref_adv3_title')],
                ];
                foreach ($advDefs as $lvl => $d): ?>
                <div class="adv-opt-sm <?= $currentAdventure === $lvl ? 'selected':'' ?>" data-adv="<?= $lvl ?>">
                    <span class="emoji"><?= $d['emoji'] ?></span>
                    <strong><?= htmlspecialchars($d['title']) ?></strong>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ===== Allergies ===== -->
        <div class="settings-card">
            <h3>⚠️ <?= __('settings_allergies_h') ?></h3>
            <div class="allergy-row">
                <?php
                $alDefs = [
                    'gluten'    => ['icon'=>'🌾','label'=>__('pref_allergy_gluten')],
                    'dairy'     => ['icon'=>'🥛','label'=>__('pref_allergy_dairy')],
                    'nuts'      => ['icon'=>'🥜','label'=>__('pref_allergy_nuts')],
                    'eggs'      => ['icon'=>'🥚','label'=>__('pref_allergy_eggs')],
                    'shellfish' => ['icon'=>'🦐','label'=>__('pref_allergy_shellfish')],
                    'soy'       => ['icon'=>'🌱','label'=>__('pref_allergy_soy')],
                ];
                foreach ($alDefs as $key => $def): ?>
                <div class="al-chip <?= $currentAllergies[$key] ? 'checked':'' ?>" data-allergy="<?= $key ?>">
                    <?= $def['icon'] ?> <?= htmlspecialchars($def['label']) ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ===== Excluded Foods ===== -->
        <div class="settings-card">
            <h3>🚫 <?= __('settings_exclude_h') ?></h3>
            <p style="font-size:.82rem;color:#64748b;margin:0 0 .875rem;"><?= __('settings_exclude_tip') ?></p>

            <div class="food-search-box">
                <svg class="si" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input type="text" id="s-food-search" placeholder="<?= htmlspecialchars(__('pref_search_ph')) ?>" autocomplete="off">
            </div>
            <div class="food-results" id="s-food-results"></div>

            <div class="excl-chips" id="s-excl-chips">
                <?php if (empty($currentExclusions)): ?>
                <span class="no-excl"><?= __('pref_none_excluded') ?></span>
                <?php else: ?>
                <?php foreach ($currentExclusions as $ex): ?>
                <div class="excl-chip" data-fid="<?= $ex['food_id'] ?>">
                    <span><?= htmlspecialchars($GLOBALS['_kcals_lang'] === 'el' ? $ex['name_el'] : $ex['name_en']) ?></span>
                    <button type="button" aria-label="Remove">&times;</button>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===== Included Foods ===== -->
        <div class="settings-card">
            <h3>✅ <?= __('settings_include_h') ?></h3>
            <p style="font-size:.82rem;color:#64748b;margin:0 0 .875rem;"><?= __('settings_include_tip') ?></p>

            <div class="food-search-box">
                <svg class="si" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input type="text" id="s-food-include-search" placeholder="<?= htmlspecialchars(__('pref_search_ph')) ?>" autocomplete="off">
            </div>
            <div class="food-results" id="s-food-include-results"></div>

            <div class="excl-chips" id="s-incl-chips">
                <?php if (empty($currentInclusions)): ?>
                <span class="no-excl"><?= __('settings_include_none') ?></span>
                <?php else: ?>
                <?php foreach ($currentInclusions as $inc): ?>
                <div class="excl-chip incl-chip" data-fid="<?= $inc['food_id'] ?>">
                    <span><?= htmlspecialchars($GLOBALS['_kcals_lang'] === 'el' ? $inc['name_el'] : $inc['name_en']) ?></span>
                    <button type="button" aria-label="Remove">&times;</button>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===== Goal Event ===== -->
        <div class="settings-card">
            <h3>🎯 <?= __('event_h') ?></h3>
            <p style="font-size:.82rem;color:#64748b;margin:0 0 .875rem;"><?= __('event_sub') ?></p>
            <input type="hidden" name="action_event" value="1">
            <input type="hidden" name="clear_event"  id="s-clear-event" value="0">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:.75rem;">
                <div class="form-group" style="margin:0;">
                    <label style="font-size:.82rem;font-weight:600;color:#374151;display:block;margin-bottom:.4rem;"><?= __('event_name_label') ?></label>
                    <input type="text" name="goal_event_name" class="form-control"
                           value="<?= htmlspecialchars($user['goal_event_name'] ?? '') ?>"
                           placeholder="<?= htmlspecialchars(__('event_name_ph')) ?>"
                           maxlength="120">
                </div>
                <div class="form-group" style="margin:0;">
                    <label style="font-size:.82rem;font-weight:600;color:#374151;display:block;margin-bottom:.4rem;"><?= __('event_date_label') ?></label>
                    <input type="date" name="goal_event_date" class="form-control"
                           value="<?= htmlspecialchars($user['goal_event_date'] ?? '') ?>"
                           min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                </div>
                <div class="form-group" style="margin:0;">
                    <label style="font-size:.82rem;font-weight:600;color:#374151;display:block;margin-bottom:.4rem;"><?= __('event_weight_label') ?></label>
                    <input type="number" name="goal_weight_kg" class="form-control"
                           value="<?= htmlspecialchars($user['goal_weight_kg'] ?? '') ?>"
                           step="0.1" min="30" max="300"
                           placeholder="<?= htmlspecialchars(__('event_weight_ph')) ?>">
                </div>
                <?php if (!empty($user['goal_event_name']) || !empty($user['goal_event_date'])): ?>
                <div style="display:flex;align-items:flex-end;">
                    <button type="submit" class="btn btn-outline btn-sm"
                            onclick="document.getElementById('s-clear-event').value='1';"
                            style="color:#dc2626;border-color:#dc2626;">
                        <i data-lucide="trash-2" style="width:13px;height:13px;vertical-align:-2px;margin-right:3px;"></i>
                        <?= __('event_clear') ?>
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===== Hormetic Recharge Day (v0.9.5) ===== -->
        <div class="settings-card">
            <h3>⚡ <?= __('settings_recharge_h') ?></h3>
            <p style="font-size:.82rem;color:#64748b;margin:0 0 .875rem;"><?= __('settings_recharge_tip') ?></p>
            <select name="recharge_day" class="form-control" style="max-width:220px;">
                <?php
                $dayMap = [
                    1 => __('day_monday'),    2 => __('day_tuesday'),   3 => __('day_wednesday'),
                    4 => __('day_thursday'),  5 => __('day_friday'),     6 => __('day_saturday'),
                    7 => __('day_sunday'),
                ];
                foreach ($dayMap as $num => $label): ?>
                <option value="<?= $num ?>" <?= $currentRechargeDay === $num ? 'selected' : '' ?>>
                    <?= htmlspecialchars($label) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
            <button type="submit" class="btn-save-settings">
                <i data-lucide="save" style="width:15px;height:15px;vertical-align:-2px;margin-right:4px;"></i>
                <?= __('pref_btn_save') ?>
            </button>
        </div>
    </form>
</div>

<script>
(function () {
    var lang = <?= json_encode($GLOBALS['_kcals_lang'] ?? 'en') ?>;
    var excludedMap = {};
    var includedMap = {};

    <?php foreach ($currentExclusions as $ex): ?>
    excludedMap[<?= (int)$ex['food_id'] ?>] = {name_en: <?= json_encode($ex['name_en']) ?>, name_el: <?= json_encode($ex['name_el']) ?>};
    <?php endforeach; ?>
    <?php foreach ($currentInclusions as $inc): ?>
    includedMap[<?= (int)$inc['food_id'] ?>] = {name_en: <?= json_encode($inc['name_en']) ?>, name_el: <?= json_encode($inc['name_el']) ?>};
    <?php endforeach; ?>

    function foodName(item) { return lang === 'el' ? item.name_el : item.name_en; }

    // ---- Adventure cards ----
    document.querySelectorAll('.adv-opt-sm').forEach(function (card) {
        card.addEventListener('click', function () {
            document.querySelectorAll('.adv-opt-sm').forEach(function (c) { c.classList.remove('selected'); });
            card.classList.add('selected');
            document.getElementById('s-input-adventure').value = card.dataset.adv;
        });
    });

    // ---- Allergy chips ----
    document.querySelectorAll('.al-chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
            chip.classList.toggle('checked');
            var cb = document.getElementById('s-hid-allergy-' + chip.dataset.allergy);
            if (cb) cb.checked = chip.classList.contains('checked');
        });
    });

    // ---- Exclusion chips ----
    function renderExclChips() {
        var container = document.getElementById('s-excl-chips');
        container.innerHTML = '';
        var ids = Object.keys(excludedMap);
        if (!ids.length) {
            var lbl = document.createElement('span');
            lbl.className = 'no-excl';
            lbl.textContent = <?= json_encode(__('pref_none_excluded')) ?>;
            container.appendChild(lbl);
        } else {
            ids.forEach(function (fid) {
                var item = excludedMap[fid];
                var chip = document.createElement('div');
                chip.className = 'excl-chip';
                chip.dataset.fid = fid;
                var label = document.createElement('span');
                label.textContent = foodName(item);
                var removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.setAttribute('aria-label', 'Remove');
                removeBtn.textContent = '×';
                removeBtn.addEventListener('click', function () {
                    delete excludedMap[fid];
                    syncInput();
                    renderExclChips();
                });
                chip.appendChild(label);
                chip.appendChild(removeBtn);
                container.appendChild(chip);
            });
        }
    }

    function syncInput() {
        document.getElementById('s-input-excluded').value = Object.keys(excludedMap).join(',');
        document.getElementById('s-input-included').value = Object.keys(includedMap).join(',');
    }

    // Wire up existing chip remove buttons
    document.querySelectorAll('#s-excl-chips .excl-chip button').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var fid = btn.closest('.excl-chip').dataset.fid;
            delete excludedMap[fid];
            syncInput();
            renderExclChips();
        });
    });

    // ---- Inclusion chips ----
    function renderInclChips() {
        var container = document.getElementById('s-incl-chips');
        container.innerHTML = '';
        var ids = Object.keys(includedMap);
        if (!ids.length) {
            var lbl = document.createElement('span');
            lbl.className = 'no-excl';
            lbl.textContent = <?= json_encode(__('settings_include_none')) ?>;
            container.appendChild(lbl);
        } else {
            ids.forEach(function (fid) {
                var item = includedMap[fid];
                var chip = document.createElement('div');
                chip.className = 'excl-chip incl-chip';
                chip.dataset.fid = fid;
                var label = document.createElement('span');
                label.textContent = foodName(item);
                var removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.setAttribute('aria-label', 'Remove');
                removeBtn.textContent = '×';
                removeBtn.addEventListener('click', function () {
                    delete includedMap[fid];
                    syncInput();
                    renderInclChips();
                });
                chip.appendChild(label);
                chip.appendChild(removeBtn);
                container.appendChild(chip);
            });
        }
    }

    document.querySelectorAll('#s-incl-chips .excl-chip button').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var fid = btn.closest('.excl-chip').dataset.fid;
            delete includedMap[fid];
            syncInput();
            renderInclChips();
        });
    });

    // ---- Food search ----
    var searchInput = document.getElementById('s-food-search');
    var resultsBox  = document.getElementById('s-food-results');
    var includeSearchInput = document.getElementById('s-food-include-search');
    var includeResultsBox  = document.getElementById('s-food-include-results');
    var timer;
    var includeTimer;

    searchInput.addEventListener('input', function () {
        clearTimeout(timer);
        var q = searchInput.value.trim();
        if (q.length < 2) { resultsBox.innerHTML = ''; resultsBox.classList.remove('visible'); return; }
        timer = setTimeout(function () { doSearch(q); }, 220);
    });
    searchInput.addEventListener('blur', function () {
        setTimeout(function () { resultsBox.classList.remove('visible'); }, 200);
    });
    searchInput.addEventListener('focus', function () {
        if (resultsBox.innerHTML) resultsBox.classList.add('visible');
    });
    includeSearchInput.addEventListener('input', function () {
        clearTimeout(includeTimer);
        var q = includeSearchInput.value.trim();
        if (q.length < 2) { includeResultsBox.innerHTML = ''; includeResultsBox.classList.remove('visible'); return; }
        includeTimer = setTimeout(function () { doIncludeSearch(q); }, 220);
    });
    includeSearchInput.addEventListener('blur', function () {
        setTimeout(function () { includeResultsBox.classList.remove('visible'); }, 200);
    });
    includeSearchInput.addEventListener('focus', function () {
        if (includeResultsBox.innerHTML) includeResultsBox.classList.add('visible');
    });

    function doSearch(q) {
        fetch(<?= json_encode(BASE_URL . '/ajax/food_search.php') ?> + '?q=' + encodeURIComponent(q))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                resultsBox.innerHTML = '';
                if (!data.length) {
                    resultsBox.innerHTML = '<div style="padding:.65rem;font-size:.8rem;color:#94a3b8;">No foods found.</div>';
                } else {
                    data.forEach(function (food) {
                        var isExcl = !!excludedMap[food.id];
                        var div = document.createElement('div');
                        div.className = 'fri';
                        div.appendChild(document.createTextNode(lang === 'el' ? food.name_el : food.name_en));
                        if (isExcl) {
                            var tag = document.createElement('span');
                            tag.style.cssText = 'font-size:.7rem;background:#fee2e2;color:#dc2626;border-radius:4px;padding:.1rem .3rem;';
                            tag.textContent = 'excluded';
                            div.appendChild(tag);
                        }
                        div.addEventListener('click', function () {
                            if (isExcl) {
                                delete excludedMap[food.id];
                            } else {
                                excludedMap[food.id] = {name_en: food.name_en, name_el: food.name_el};
                                delete includedMap[food.id];
                            }
                            syncInput();
                            renderExclChips();
                            renderInclChips();
                            searchInput.value = '';
                            resultsBox.innerHTML = '';
                            resultsBox.classList.remove('visible');
                        });
                        resultsBox.appendChild(div);
                    });
                }
                resultsBox.classList.add('visible');
            });
    }

    function doIncludeSearch(q) {
        fetch(<?= json_encode(BASE_URL . '/ajax/food_search.php') ?> + '?q=' + encodeURIComponent(q))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                includeResultsBox.innerHTML = '';
                if (!data.length) {
                    includeResultsBox.innerHTML = '<div style="padding:.65rem;font-size:.8rem;color:#94a3b8;">No foods found.</div>';
                } else {
                    data.forEach(function (food) {
                        var isIncl = !!includedMap[food.id];
                        var isExcl = !!excludedMap[food.id];
                        var div = document.createElement('div');
                        div.className = 'fri';
                        div.appendChild(document.createTextNode(lang === 'el' ? food.name_el : food.name_en));
                        if (isExcl) {
                            var blockedTag = document.createElement('span');
                            blockedTag.style.cssText = 'font-size:.7rem;background:#fee2e2;color:#dc2626;border-radius:4px;padding:.1rem .3rem;';
                            blockedTag.textContent = 'excluded';
                            div.appendChild(blockedTag);
                        } else if (isIncl) {
                            var tag = document.createElement('span');
                            tag.style.cssText = 'font-size:.7rem;background:#dcfce7;color:#15803d;border-radius:4px;padding:.1rem .3rem;';
                            tag.textContent = 'included';
                            div.appendChild(tag);
                        }
                        div.addEventListener('click', function () {
                            if (isExcl) return;
                            if (isIncl) {
                                delete includedMap[food.id];
                            } else {
                                includedMap[food.id] = {name_en: food.name_en, name_el: food.name_el};
                            }
                            syncInput();
                            renderInclChips();
                            includeSearchInput.value = '';
                            includeResultsBox.innerHTML = '';
                            includeResultsBox.classList.remove('visible');
                        });
                        includeResultsBox.appendChild(div);
                    });
                }
                includeResultsBox.classList.add('visible');
            });
    }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
