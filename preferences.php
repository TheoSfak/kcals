<?php
// ============================================================
// KCALS – Food Preference Interview (3-step wizard)
// Step 1: Cuisine adventure level
// Step 2: Allergies
// Step 3: Per-food exclusions
// On completion: saves to DB, redirects to plan (or ?next=)
// ============================================================
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$db     = getDB();
$userId = (int) $_SESSION['user_id'];
$user   = getCurrentUser();

// Where to redirect after saving
$next = ($_GET['next'] ?? '') === 'plan' ? 'plan' : 'plan';

// ======== HANDLE POST ========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        exit('Invalid request.');
    }

    $adventure    = max(0, min(3, (int) ($_POST['food_adventure'] ?? 2)));
    $allergyMap   = ['gluten','dairy','nuts','eggs','shellfish','soy'];
    $allergyVals  = [];
    foreach ($allergyMap as $a) {
        $allergyVals['allergy_' . $a] = isset($_POST['allergy_' . $a]) ? 1 : 0;
    }

    // Excluded food IDs (validated as positive integers)
    $rawIds = $_POST['excluded_ids'] ?? '';
    $excluded = array_unique(
        array_filter(
            array_map('intval', array_filter(explode(',', $rawIds))),
            fn($id) => $id > 0
        )
    );

    try {
        // Update user preference columns
        $setAllergy = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($allergyVals)));
        $stmt = $db->prepare("
            UPDATE `users`
            SET `food_adventure` = ?, `interview_done` = 1, $setAllergy
            WHERE `id` = ?
        ");
        $stmt->execute(array_merge([$adventure], array_values($allergyVals), [$userId]));

        // Replace food exclusions: delete old, insert new
        $db->prepare('DELETE FROM `user_food_exclusions` WHERE `user_id` = ?')->execute([$userId]);

        if (!empty($excluded)) {
            // Verify food IDs exist before inserting (prevent orphan references)
            $ph   = implode(',', array_fill(0, count($excluded), '?'));
            $rows = $db->prepare("SELECT `id` FROM `foods` WHERE `id` IN ($ph)");
            $rows->execute($excluded);
            $validIds = array_column($rows->fetchAll(), 'id');

            if (!empty($validIds)) {
                $insVals = implode(', ', array_fill(0, count($validIds), '(?,?)'));
                $insParams = [];
                foreach ($validIds as $fid) {
                    $insParams[] = $userId;
                    $insParams[] = (int) $fid;
                }
                $db->prepare("INSERT IGNORE INTO `user_food_exclusions` (`user_id`,`food_id`) VALUES $insVals")
                   ->execute($insParams);
            }
        }

        // Redirect to plan (or settings)
        $dest = $next === 'settings' ? BASE_URL . '/settings.php?saved=1' : BASE_URL . '/plan.php';
        header('Location: ' . $dest);
        exit;

    } catch (PDOException $e) {
        error_log('preferences.php save error: ' . $e->getMessage());
        $saveError = __('pref_err_save');
    }
}

// ======== LOAD CURRENT PREFERENCES ========
$currentAdventure  = (int) ($user['food_adventure'] ?? 2);
$currentAllergies  = [
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
$currentExclusions = $exclStmt->fetchAll(); // [{food_id, name_en, name_el}]

// ======== RENDER ========
$pageTitle = __('pref_page_title');
$activeNav = 'preferences';
require_once __DIR__ . '/includes/header.php';
?>

<style>
/* ======== Wizard Wrapper ======== */
.pref-wizard {
    max-width: 680px;
    margin: 2.5rem auto;
    padding: 0 1.25rem;
}
.pref-steps {
    display: flex;
    gap: .5rem;
    margin-bottom: 2rem;
}
.pref-step-dot {
    flex: 1;
    height: 5px;
    border-radius: 99px;
    background: #e2e8f0;
    transition: background .3s;
}
.pref-step-dot.done  { background: #2ecc71; }
.pref-step-dot.active{ background: #23a85e; }

/* ======== Cards ======== */
.pref-card {
    background: #fff;
    border-radius: 14px;
    padding: 2rem;
    box-shadow: 0 2px 16px rgba(0,0,0,.07);
}
.pref-card h2 {
    font-size: 1.25rem;
    font-weight: 700;
    margin: 0 0 .4rem;
    color: #1a1a2e;
}
.pref-card p.sub {
    font-size: .875rem;
    color: #64748b;
    margin: 0 0 1.5rem;
}

/* ======== Adventure option cards ======== */
.adv-grid {
    display: grid;
    grid-template-columns: repeat(4,1fr);
    gap: .875rem;
    margin-bottom: 1.75rem;
}
@media (max-width:560px){ .adv-grid { grid-template-columns:1fr; } }

.adv-option {
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.1rem .875rem;
    cursor: pointer;
    transition: border-color .18s, background .18s, transform .12s;
    text-align: center;
    user-select: none;
}
.adv-option:hover { border-color: #2ecc71; transform: translateY(-1px); }
.adv-option.selected { border-color: #2ecc71; background: #f0fdf4; }
.adv-option .adv-emoji { font-size: 2rem; margin-bottom: .35rem; }
.adv-option input[type=radio] { display: none; }
.adv-option strong { display: block; font-size: .925rem; color: #1e293b; margin-bottom: .2rem; }
.adv-option span   { font-size: .78rem; color: #64748b; line-height: 1.4; }

/* ======== Allergy grid ======== */
.allergy-grid {
    display: grid;
    grid-template-columns: repeat(3,1fr);
    gap: .65rem;
    margin-bottom: 1.75rem;
}
@media (max-width:480px){ .allergy-grid { grid-template-columns:repeat(2,1fr); } }

.allergy-chip {
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    padding: .65rem .5rem;
    display: flex;
    align-items: center;
    gap: .45rem;
    cursor: pointer;
    font-size: .85rem;
    font-weight: 600;
    color: #374151;
    transition: border-color .15s, background .15s;
    user-select: none;
}
.allergy-chip input[type=checkbox] { display: none; }
.allergy-chip .ac-icon { font-size: 1.2rem; }
.allergy-chip.checked { border-color: #f59e0b; background: #fffbeb; color: #92400e; }

/* ======== Food exclusion search ======== */
.food-search-box {
    position: relative;
    margin-bottom: .75rem;
}
.food-search-box input {
    width: 100%;
    box-sizing: border-box;
    padding: .6rem .9rem .6rem 2.4rem;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: .9rem;
    transition: border-color .15s;
}
.food-search-box input:focus {
    outline: none;
    border-color: #2ecc71;
    box-shadow: 0 0 0 3px rgba(46,204,113,.15);
}
.food-search-box .search-icon {
    position: absolute;
    left: .7rem;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
    pointer-events: none;
}
.food-results {
    max-height: 240px;
    overflow-y: auto;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
    box-shadow: 0 4px 12px rgba(0,0,0,.08);
    display: none;
}
.food-results.visible { display: block; }
.food-result-item {
    padding: .55rem .875rem;
    cursor: pointer;
    font-size: .875rem;
    color: #374151;
    transition: background .1s;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.food-result-item:hover { background: #f0fdf4; }
.food-result-item.excluded-item { color: #dc2626; }
.food-result-item span.tag {
    font-size: .7rem;
    background: #fee2e2;
    color: #dc2626;
    border-radius: 4px;
    padding: .1rem .35rem;
}
.excl-chips {
    display: flex;
    flex-wrap: wrap;
    gap: .4rem;
    margin-top: .875rem;
    min-height: 1.5rem;
}
.excl-chip {
    display: flex;
    align-items: center;
    gap: .3rem;
    background: #fee2e2;
    color: #7f1d1d;
    border-radius: 99px;
    padding: .3rem .7rem;
    font-size: .8rem;
    font-weight: 600;
}
.excl-chip button {
    background: none;
    border: none;
    cursor: pointer;
    color: #dc2626;
    font-size: .9rem;
    padding: 0;
    line-height: 1;
}
.no-excl-label {
    font-size: .82rem;
    color: #94a3b8;
    font-style: italic;
}

/* ======== Wizard nav buttons ======== */
.pref-nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 1.5rem;
    gap: 1rem;
}
.btn-pref-next {
    background: #2ecc71;
    color: #fff;
    border: none;
    padding: .7rem 1.75rem;
    border-radius: 8px;
    font-size: .95rem;
    font-weight: 600;
    cursor: pointer;
    transition: background .15s;
}
.btn-pref-next:hover { background: #27ae60; }
.btn-pref-back {
    background: #f1f5f9;
    color: #475569;
    border: none;
    padding: .7rem 1.25rem;
    border-radius: 8px;
    font-size: .9rem;
    font-weight: 600;
    cursor: pointer;
    transition: background .15s;
}
.btn-pref-back:hover { background: #e2e8f0; }
.btn-pref-back[disabled] { visibility: hidden; }
</style>

<div class="pref-wizard">

    <?php if (!empty($saveError)): ?>
    <div style="background:#f8d7da;border:1px solid #dc3545;border-radius:8px;padding:.875rem 1.25rem;margin-bottom:1.25rem;font-size:.9rem;color:#721c24;">
        <?= htmlspecialchars($saveError) ?>
    </div>
    <?php endif; ?>

    <!-- Progress dots -->
    <div class="pref-steps" id="pref-steps">
        <div class="pref-step-dot active" id="dot-1"></div>
        <div class="pref-step-dot"        id="dot-2"></div>
        <div class="pref-step-dot"        id="dot-3"></div>
    </div>

    <form method="POST" id="pref-form">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="food_adventure" id="input-adventure" value="<?= $currentAdventure ?>">
        <input type="hidden" name="excluded_ids"   id="input-excluded"  value="<?= htmlspecialchars(implode(',', array_column($currentExclusions, 'food_id'))) ?>">

        <!-- Allergy checkboxes (submitted as part of form) -->
        <?php foreach (['gluten','dairy','nuts','eggs','shellfish','soy'] as $a): ?>
        <input type="checkbox" name="allergy_<?= $a ?>" id="hid-allergy-<?= $a ?>"
               style="display:none" <?= $currentAllergies[$a] ? 'checked' : '' ?>>
        <?php endforeach; ?>

        <!-- ======== STEP 1: Adventure Level ======== -->
        <div class="pref-card" id="step-1">
            <h2><?= __('pref_step1_title') ?></h2>
            <p class="sub"><?= __('pref_step1_sub') ?></p>

            <div class="adv-grid">
                <?php
                $advLevels = [
                    0 => ['emoji'=>'⚡','title'=>__('pref_adv0_title'),'desc'=>__('pref_adv0_desc')],
                    1 => ['emoji'=>'🇬🇷','title'=>__('pref_adv1_title'),'desc'=>__('pref_adv1_desc')],
                    2 => ['emoji'=>'🌊','title'=>__('pref_adv2_title'),'desc'=>__('pref_adv2_desc')],
                    3 => ['emoji'=>'🌍','title'=>__('pref_adv3_title'),'desc'=>__('pref_adv3_desc')],
                ];
                foreach ($advLevels as $lvl => $info): ?>
                <label class="adv-option <?= $currentAdventure === $lvl ? 'selected' : '' ?>"
                       data-adv="<?= $lvl ?>">
                    <div class="adv-emoji"><?= $info['emoji'] ?></div>
                    <strong><?= htmlspecialchars($info['title']) ?></strong>
                    <span><?= htmlspecialchars($info['desc']) ?></span>
                </label>
                <?php endforeach; ?>
            </div>

            <div class="pref-nav">
                <button type="button" class="btn-pref-back" disabled><?= __('pref_btn_back') ?></button>
                <button type="button" class="btn-pref-next" id="btn-1-next"><?= __('pref_btn_next') ?></button>
            </div>
        </div>

        <!-- ======== STEP 2: Allergies ======== -->
        <div class="pref-card" id="step-2" style="display:none;">
            <h2><?= __('pref_step2_title') ?></h2>
            <p class="sub"><?= __('pref_step2_sub') ?></p>

            <div class="allergy-grid">
                <?php
                $allergyDefs = [
                    'gluten'    => ['icon'=>'🌾','label'=>__('pref_allergy_gluten')],
                    'dairy'     => ['icon'=>'🥛','label'=>__('pref_allergy_dairy')],
                    'nuts'      => ['icon'=>'🥜','label'=>__('pref_allergy_nuts')],
                    'eggs'      => ['icon'=>'🥚','label'=>__('pref_allergy_eggs')],
                    'shellfish' => ['icon'=>'🦐','label'=>__('pref_allergy_shellfish')],
                    'soy'       => ['icon'=>'🌱','label'=>__('pref_allergy_soy')],
                ];
                foreach ($allergyDefs as $key => $def): ?>
                <div class="allergy-chip <?= $currentAllergies[$key] ? 'checked' : '' ?>"
                     data-allergy="<?= $key ?>">
                    <span class="ac-icon"><?= $def['icon'] ?></span>
                    <?= htmlspecialchars($def['label']) ?>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="pref-nav">
                <button type="button" class="btn-pref-back" id="btn-2-back"><?= __('pref_btn_back') ?></button>
                <button type="button" class="btn-pref-next" id="btn-2-next"><?= __('pref_btn_next') ?></button>
            </div>
        </div>

        <!-- ======== STEP 3: Food Exclusions ======== -->
        <div class="pref-card" id="step-3" style="display:none;">
            <h2><?= __('pref_step3_title') ?></h2>
            <p class="sub"><?= __('pref_step3_sub') ?></p>

            <div class="food-search-box">
                <svg class="search-icon" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input type="text" id="food-search-input" placeholder="<?= htmlspecialchars(__('pref_search_ph')) ?>" autocomplete="off">
            </div>
            <div class="food-results" id="food-results"></div>

            <p style="font-size:.82rem;font-weight:600;color:#475569;margin:.875rem 0 .4rem;"><?= __('pref_excluded_label') ?></p>
            <div class="excl-chips" id="excl-chips">
                <?php if (empty($currentExclusions)): ?>
                <span class="no-excl-label" id="no-excl-label"><?= __('pref_none_excluded') ?></span>
                <?php else: ?>
                <?php foreach ($currentExclusions as $ex): ?>
                <div class="excl-chip" data-fid="<?= $ex['food_id'] ?>">
                    <span><?= htmlspecialchars($GLOBALS['_kcals_lang'] === 'el' ? $ex['name_el'] : $ex['name_en']) ?></span>
                    <button type="button" aria-label="Remove">&times;</button>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="pref-nav">
                <button type="button" class="btn-pref-back" id="btn-3-back"><?= __('pref_btn_back') ?></button>
                <button type="submit" class="btn-pref-next"><?= __('pref_btn_finish') ?></button>
            </div>
        </div>
    </form>
</div>

<script>
(function () {
    // ======== State ========
    var currentStep = 1;
    var excludedMap = {}; // foodId -> {name_en, name_el}

    // Pre-populate from PHP
    <?php foreach ($currentExclusions as $ex): ?>
    excludedMap[<?= (int)$ex['food_id'] ?>] = {
        name_en: <?= json_encode($ex['name_en']) ?>,
        name_el: <?= json_encode($ex['name_el']) ?>
    };
    <?php endforeach; ?>

    var lang = <?= json_encode($GLOBALS['_kcals_lang'] ?? 'en') ?>;

    function foodName(item) {
        return lang === 'el' ? item.name_el : item.name_en;
    }

    // ======== Step navigation ========
    function goTo(step) {
        document.getElementById('step-' + currentStep).style.display = 'none';
        document.getElementById('dot-' + currentStep).className = 'pref-step-dot done';
        currentStep = step;
        document.getElementById('step-' + currentStep).style.display = '';
        document.getElementById('dot-' + currentStep).className = 'pref-step-dot active';
    }

    document.getElementById('btn-1-next').addEventListener('click', function () { goTo(2); });
    document.getElementById('btn-2-next').addEventListener('click', function () { goTo(3); });
    document.getElementById('btn-2-back').addEventListener('click', function () {
        document.getElementById('step-2').style.display = 'none';
        document.getElementById('dot-2').className = 'pref-step-dot';
        currentStep = 1;
        document.getElementById('step-1').style.display = '';
        document.getElementById('dot-1').className = 'pref-step-dot active';
    });
    document.getElementById('btn-3-back').addEventListener('click', function () {
        document.getElementById('step-3').style.display = 'none';
        document.getElementById('dot-3').className = 'pref-step-dot';
        currentStep = 2;
        document.getElementById('step-2').style.display = '';
        document.getElementById('dot-2').className = 'pref-step-dot active';
    });

    // ======== Adventure cards ========
    document.querySelectorAll('.adv-option').forEach(function (card) {
        card.addEventListener('click', function () {
            document.querySelectorAll('.adv-option').forEach(function (c) { c.classList.remove('selected'); });
            card.classList.add('selected');
            document.getElementById('input-adventure').value = card.dataset.adv;
        });
    });

    // ======== Allergy chips ========
    document.querySelectorAll('.allergy-chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
            chip.classList.toggle('checked');
            var key = chip.dataset.allergy;
            var cb  = document.getElementById('hid-allergy-' + key);
            if (cb) cb.checked = chip.classList.contains('checked');
        });
    });

    // ======== Exclusion chips helpers ========
    function renderExclChips() {
        var container = document.getElementById('excl-chips');
        container.innerHTML = '';
        var ids = Object.keys(excludedMap);
        if (ids.length === 0) {
            var lbl = document.createElement('span');
            lbl.className = 'no-excl-label';
            lbl.id = 'no-excl-label';
            lbl.textContent = <?= json_encode(__('pref_none_excluded')) ?>;
            container.appendChild(lbl);
        } else {
            ids.forEach(function (fid) {
                var item = excludedMap[fid];
                var chip = document.createElement('div');
                chip.className = 'excl-chip';
                chip.dataset.fid = fid;
                var label = document.createElement('span');
                label.textContent = lang === 'el' ? item.name_el : item.name_en;
                var removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.setAttribute('aria-label', 'Remove');
                removeBtn.textContent = '×';
                removeBtn.addEventListener('click', function () {
                    delete excludedMap[fid];
                    syncExcludedInput();
                    renderExclChips();
                });
                chip.appendChild(label);
                chip.appendChild(removeBtn);
                container.appendChild(chip);
            });
        }
    }

    function syncExcludedInput() {
        document.getElementById('input-excluded').value = Object.keys(excludedMap).join(',');
    }

    // ======== Food search (AJAX) ========
    var searchInput   = document.getElementById('food-search-input');
    var resultsBox    = document.getElementById('food-results');
    var searchTimeout = null;

    searchInput.addEventListener('input', function () {
        clearTimeout(searchTimeout);
        var q = searchInput.value.trim();
        if (q.length < 2) { resultsBox.innerHTML = ''; resultsBox.classList.remove('visible'); return; }
        searchTimeout = setTimeout(function () { doSearch(q); }, 220);
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
                    resultsBox.innerHTML = '<div style="padding:.75rem;font-size:.82rem;color:#94a3b8;">No foods found.</div>';
                } else {
                    data.forEach(function (food) {
                        var isExcl = !!excludedMap[food.id];
                        var div = document.createElement('div');
                        div.className = 'food-result-item' + (isExcl ? ' excluded-item' : '');
                        div.appendChild(document.createTextNode(lang === 'el' ? food.name_el : food.name_en));
                        if (isExcl) {
                            var tag = document.createElement('span');
                            tag.className = 'tag';
                            tag.textContent = 'excluded';
                            div.appendChild(tag);
                        }
                        div.addEventListener('click', function () {
                            if (isExcl) {
                                delete excludedMap[food.id];
                            } else {
                                excludedMap[food.id] = {name_en: food.name_en, name_el: food.name_el};
                            }
                            syncExcludedInput();
                            renderExclChips();
                            searchInput.value = '';
                            resultsBox.innerHTML = '';
                            resultsBox.classList.remove('visible');
                        });
                        resultsBox.appendChild(div);
                    });
                }
                resultsBox.classList.add('visible');
            })
            .catch(function () { resultsBox.innerHTML = ''; resultsBox.classList.remove('visible'); });
    }

    // Initial chip render if no exclusions came from PHP (clean state)
    if (Object.keys(excludedMap).length === 0) {
        // no-op, PHP already rendered the label
    }

})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
