<?php
// ============================================================
// KCALS - Local Meal Prep Mode
// Shows practical prep tasks derived from the current weekly plan.
// ============================================================
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/engine/meal_prep.php';

requireLogin();

$db = getDB();
$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCsrf($_POST['csrf_token'] ?? '')) {
        $taskId = (int) ($_POST['task_id'] ?? 0);
        $status = (string) ($_POST['status'] ?? 'todo');
        if ($taskId > 0 && in_array($status, ['todo', 'done', 'skipped'], true)) {
            $stmt = $db->prepare('
                UPDATE meal_prep_tasks
                SET status = ?, updated_at = NOW()
                WHERE id = ? AND user_id = ?
            ');
            $stmt->execute([$status, $taskId, $userId]);
        }
    }
    header('Location: ' . BASE_URL . '/meal_prep.php');
    exit;
}

$plan = kcalsMealPrepLatestPlan($db, $userId);
$tasks = [];
$progress = ['total' => 0, 'done' => 0, 'remaining_minutes' => 0];
if ($plan) {
    kcalsMealPrepSyncTasks($db, $userId, $plan);
    $tasks = kcalsMealPrepTasksForPlan($db, $userId, (int) $plan['id']);
    $progress = kcalsMealPrepProgress($tasks);
}

$tasksByDate = [];
foreach ($tasks as $task) {
    $tasksByDate[$task['task_date']][] = $task;
}

$pageTitle = __('prep_title');
$activeNav = 'prep';
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width:980px; margin:2rem auto; padding:0 1.25rem;">
    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
        <div>
            <h1 style="font-size:1.5rem; margin-bottom:.25rem;"><?= __('prep_h1') ?></h1>
            <p class="text-small" style="color:var(--slate-mid);"><?= __('prep_sub') ?></p>
        </div>
        <div style="display:flex; gap:.75rem; flex-wrap:wrap;">
            <a href="<?= BASE_URL ?>/plan.php" class="btn btn-outline btn-sm"><?= __('prep_back_plan') ?></a>
            <?php if ($plan): ?>
            <a href="<?= BASE_URL ?>/shopping.php" class="btn btn-outline btn-sm">
                <i data-lucide="shopping-cart" style="width:14px;height:14px;"></i>
                <?= __('prep_shopping') ?>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$plan): ?>
    <div class="card" style="text-align:center; padding:3rem;">
        <i data-lucide="chef-hat" style="width:48px;height:48px; color:var(--slate-light); display:block; margin:0 auto 1rem;"></i>
        <h3><?= __('prep_no_plan') ?></h3>
        <p style="margin:.5rem 0 1.5rem;"><?= __('prep_no_plan_desc') ?></p>
        <a href="<?= BASE_URL ?>/plan.php" class="btn btn-primary"><?= __('prep_gen_plan') ?></a>
    </div>
    <?php else: ?>

    <div class="prep-summary">
        <div>
            <span><?= __('prep_week') ?></span>
            <strong><?= htmlspecialchars($plan['start_date']) ?> → <?= htmlspecialchars($plan['end_date']) ?></strong>
        </div>
        <div>
            <span><?= __('prep_done') ?></span>
            <strong><?= (int) $progress['done'] ?> / <?= (int) $progress['total'] ?></strong>
        </div>
        <div>
            <span><?= __('prep_remaining') ?></span>
            <strong><?= (int) $progress['remaining_minutes'] ?> <?= __('prep_min') ?></strong>
        </div>
    </div>

    <?php if (empty($tasks)): ?>
    <div class="alert alert-warning"><?= __('prep_no_tasks') ?></div>
    <?php else: ?>
        <?php foreach ($tasksByDate as $date => $dayTasks): ?>
        <?php
            $first = $dayTasks[0];
            $dayLabel = __('day_' . strtolower((string) $first['day_name']));
            $dayDone = count(array_filter($dayTasks, fn($task) => ($task['status'] ?? '') === 'done'));
        ?>
        <section class="prep-day">
            <div class="prep-day-head">
                <div>
                    <h2><?= htmlspecialchars($dayLabel) ?></h2>
                    <span><?= htmlspecialchars(date('d/m', strtotime($date))) ?></span>
                </div>
                <strong><?= $dayDone ?> / <?= count($dayTasks) ?></strong>
            </div>

            <?php foreach ($dayTasks as $task): ?>
            <?php
                $details = json_decode((string) ($task['details_json'] ?? ''), true) ?: [];
                $isDone = ($task['status'] ?? '') === 'done';
                $isSkipped = ($task['status'] ?? '') === 'skipped';
                $title = ($GLOBALS['_kcals_lang'] === 'el') ? $task['title_el'] : $task['title_en'];
            ?>
            <article class="prep-task <?= $isDone ? 'is-done' : '' ?> <?= $isSkipped ? 'is-skipped' : '' ?>">
                <div class="prep-task-main">
                    <form method="POST" class="prep-check-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                        <input type="hidden" name="task_id" value="<?= (int) $task['id'] ?>">
                        <input type="hidden" name="status" value="<?= $isDone ? 'todo' : 'done' ?>">
                        <button type="submit" class="prep-check" title="<?= htmlspecialchars($isDone ? __('prep_mark_todo') : __('prep_mark_done')) ?>">
                            <i data-lucide="<?= $isDone ? 'check' : 'circle' ?>"></i>
                        </button>
                    </form>
                    <div>
                        <div class="prep-task-title"><?= htmlspecialchars($title) ?></div>
                        <div class="prep-task-meta">
                            <span><i data-lucide="clock"></i><?= (int) $task['estimated_minutes'] ?> <?= __('prep_min') ?></span>
                            <span><?= __('prep_type_' . $task['task_type']) ?></span>
                        </div>
                        <?php if (($task['task_type'] ?? '') === 'main_cook' && !empty($details['details']['components'])): ?>
                        <p class="prep-components">
                            <?php
                                $bits = [];
                                foreach ($details['details']['components'] as $component) {
                                    $name = kcalsMealPrepComponentName($component, $GLOBALS['_kcals_lang']);
                                    if ($name === '') continue;
                                    $bits[] = htmlspecialchars($name) . ' ' . (int) $component['grams'] . 'g';
                                }
                                echo implode(' · ', $bits);
                            ?>
                        </p>
                        <?php elseif (($task['task_type'] ?? '') === 'quick_pack' && !empty($details['meals'])): ?>
                        <p class="prep-components">
                            <?php
                                $bits = [];
                                foreach ($details['meals'] as $meal) {
                                    $bits[] = htmlspecialchars(($GLOBALS['_kcals_lang'] === 'el') ? $meal['meal_name_el'] : $meal['meal_name_en']);
                                }
                                echo implode(' · ', $bits);
                            ?>
                        </p>
                        <?php elseif (($task['task_type'] ?? '') === 'fresh_prep' && !empty($details['components'])): ?>
                        <p class="prep-components">
                            <?php
                                $bits = [];
                                foreach ($details['components'] as $component) {
                                    $name = kcalsMealPrepComponentName($component, $GLOBALS['_kcals_lang']);
                                    if ($name === '') continue;
                                    $bits[] = htmlspecialchars($name) . ' ' . (int) $component['grams'] . 'g';
                                }
                                echo implode(' · ', $bits);
                            ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                    <input type="hidden" name="task_id" value="<?= (int) $task['id'] ?>">
                    <input type="hidden" name="status" value="<?= $isSkipped ? 'todo' : 'skipped' ?>">
                    <button type="submit" class="prep-skip"><?= $isSkipped ? __('prep_unskip') : __('prep_skip') ?></button>
                </form>
            </article>
            <?php endforeach; ?>
        </section>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.prep-summary {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: .8rem;
    margin-bottom: 1.25rem;
}
.prep-summary > div {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: .9rem 1rem;
}
.prep-summary span {
    display: block;
    color: var(--slate-mid);
    font-size: .78rem;
    margin-bottom: .25rem;
}
.prep-summary strong {
    color: var(--slate);
    font-size: 1rem;
}
.prep-day {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 8px;
    margin-bottom: 1rem;
    overflow: hidden;
}
.prep-day-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    background: #f8fafc;
    border-bottom: 1px solid var(--border);
    padding: .85rem 1rem;
}
.prep-day-head h2 {
    margin: 0;
    font-size: 1rem;
}
.prep-day-head span {
    color: var(--slate-mid);
    font-size: .8rem;
}
.prep-task {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: .95rem 1rem;
    border-bottom: 1px solid #f1f5f9;
}
.prep-task:last-child { border-bottom: 0; }
.prep-task.is-done { background: #f0fdf4; }
.prep-task.is-skipped { opacity: .62; }
.prep-task-main {
    display: flex;
    gap: .75rem;
    min-width: 0;
}
.prep-check-form { margin: 0; }
.prep-check {
    width: 32px;
    height: 32px;
    border: 2px solid var(--green);
    background: #fff;
    color: var(--green-dark);
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}
.prep-check svg { width: 16px; height: 16px; }
.prep-task-title {
    font-weight: 800;
    color: var(--slate);
    line-height: 1.35;
}
.prep-task.is-done .prep-task-title { text-decoration: line-through; color: #15803d; }
.prep-task-meta {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: .55rem;
    margin-top: .25rem;
    color: var(--slate-mid);
    font-size: .78rem;
}
.prep-task-meta span {
    display: inline-flex;
    align-items: center;
    gap: .25rem;
}
.prep-task-meta svg { width: 13px; height: 13px; }
.prep-components {
    color: var(--slate-mid);
    font-size: .8rem;
    margin: .45rem 0 0;
    line-height: 1.45;
}
.prep-skip {
    border: 0;
    background: transparent;
    color: var(--slate-mid);
    font-size: .78rem;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
}
@media (max-width: 680px) {
    .prep-summary { grid-template-columns: 1fr; }
    .prep-task { flex-direction: column; }
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
