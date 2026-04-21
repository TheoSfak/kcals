<?php
// ============================================================
// KCALS – User Food Preferences Settings Page
// Allows the user to change adventure level, toggle allergies,
// and search/toggle per-food exclusions after the interview.
// ============================================================
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$db     = getDB();
$userId = (int) $_SESSION['user_id'];
$user   = getCurrentUser();

$saveSuccess = isset($_GET['saved']);
$errors      = [];

// ======== HANDLE POST ========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errors[] = __('err_invalid_submit');
    } else {
        $adventure   = max(1, min(3, (int) ($_POST['food_adventure'] ?? 2)));
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

        try {
            $setAllergy = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($allergyVals)));
            $stmt = $db->prepare("
                UPDATE `users`
                SET `food_adventure` = ?, `interview_done` = 1, $setAllergy
                WHERE `id` = ?
            ");
            $stmt->execute(array_merge([$adventure], array_values($allergyVals), [$userId]));

            $db->prepare('DELETE FROM `user_food_exclusions` WHERE `user_id` = ?')->execute([$userId]);

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

            header('Location: ' . BASE_URL . '/settings.php?saved=1');
            exit;

        } catch (PDOException $e) {
            error_log('settings.php save error: ' . $e->getMessage());
            $errors[] = __('pref_err_save');
        }
    }
}

// ======== LOAD DATA ========
$currentAdventure = (int) ($user['food_adventure'] ?? 2);
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
    grid-template-columns: repeat(3,1fr);
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
    <?php foreach ($errors as $e): ?>
    <div class="alert-error"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <form method="POST" id="settings-form">
        <input type="hidden" name="csrf_token"   value="<?= csrfToken() ?>">
        <input type="hidden" name="food_adventure" id="s-input-adventure" value="<?= $currentAdventure ?>">
        <input type="hidden" name="excluded_ids"  id="s-input-excluded"  value="<?= htmlspecialchars(implode(',', array_column($currentExclusions, 'food_id'))) ?>">

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

    <?php foreach ($currentExclusions as $ex): ?>
    excludedMap[<?= (int)$ex['food_id'] ?>] = {name_en: <?= json_encode($ex['name_en']) ?>, name_el: <?= json_encode($ex['name_el']) ?>};
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
                chip.innerHTML = '<span>' + foodName(item) + '</span><button type="button" aria-label="Remove">&times;</button>';
                chip.querySelector('button').addEventListener('click', function () {
                    delete excludedMap[fid];
                    syncInput();
                    renderExclChips();
                });
                container.appendChild(chip);
            });
        }
    }

    function syncInput() {
        document.getElementById('s-input-excluded').value = Object.keys(excludedMap).join(',');
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

    // ---- Food search ----
    var searchInput = document.getElementById('s-food-search');
    var resultsBox  = document.getElementById('s-food-results');
    var timer;

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
                        div.innerHTML = (lang === 'el' ? food.name_el : food.name_en) +
                                        (isExcl ? '<span style="font-size:.7rem;background:#fee2e2;color:#dc2626;border-radius:4px;padding:.1rem .3rem;">excluded</span>' : '');
                        div.addEventListener('click', function () {
                            if (isExcl) {
                                delete excludedMap[food.id];
                            } else {
                                excludedMap[food.id] = {name_en: food.name_en, name_el: food.name_el};
                            }
                            syncInput();
                            renderExclChips();
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
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
