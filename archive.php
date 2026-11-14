<?php
declare(strict_types=1);

$container = require_once __DIR__ . '/src/bootstrap.php';

use App\Infrastructure\Security\SecurityHelper;
use App\Application\Services\TaskService;
use App\Application\Services\ProjectService;
use App\Application\Services\UserService;

SecurityHelper::requireLogin();

$taskService = $container->get(TaskService::class);
$projectService = $container->get(ProjectService::class);
$userService = $container->get(UserService::class);

$currentUserId = SecurityHelper::getCurrentUserId() ?? 0;

$selectedProjectId = isset($_GET['project_id']) && $_GET['project_id'] !== '' ? (int)$_GET['project_id'] : null;
$onlyBugs = isset($_GET['only_bugs']) && $_GET['only_bugs'] === '1';
$sortBy = $_GET['sort_by'] ?? '';

$projects = $projectService->getAllProjects($currentUserId);
if ($selectedProjectId !== null) {
    $activeProj = $projectService->getProjectById($selectedProjectId, $currentUserId);
    if (!$activeProj) {
        $selectedProjectId = null;
    }
}

$csrfToken = SecurityHelper::generateCsrfToken();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'unarchive') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (SecurityHelper::verifyCsrfToken($submittedToken)) {
        $taskId = (int)($_POST['task_id'] ?? 0);
        try {
            $taskService->unarchiveTask($taskId, $currentUserId);
            $success = "Task #" . $taskId . " restored to active task board.";
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
    } else {
        $error = 'Security check failed. Please try again.';
    }
}

// Fetch only archived tasks (isArchived = true) scoped to current user
$archivedTasks = $taskService->getTasksFiltered($selectedProjectId, null, $onlyBugs, $sortBy, true, $currentUserId);
$users = $userService->getAllUsers();

$projectMap = [];
$projectObjMap = [];
foreach ($projects as $p) {
    $projectMap[$p->getId()] = $p->getName();
    $projectObjMap[$p->getId()] = $p;
}

$userMap = [];
foreach ($users as $u) {
    $userMap[$u->getId()] = $u->getName();
}

$hasFiltersActive = ($selectedProjectId !== null) || $onlyBugs || ($sortBy !== '');

require_once __DIR__ . '/templates/header.php';

// Render sticky-top header
$subtitle = ($selectedProjectId !== null && $activeProj)
    ? 'Filtered by: "' . SecurityHelper::escape($activeProj->getName()) . '" &bull; <a href="archive.php" class="text-indigo-400 hover:underline font-medium">Show All</a>'
    : count($archivedTasks) . ' archived';

renderPageHeader('Task Archive', $subtitle);
?>

<div class="page-container space-y-5">

    <?php if ($error): ?>
        <div class="p-3.5 bg-rose-500/10 border border-rose-500/20 text-rose-300 rounded-xl text-sm flex items-center justify-between">
            <span><?php echo SecurityHelper::escape($error); ?></span>
            <button type="button" onclick="this.parentElement.remove()" class="text-rose-400 hover:text-rose-200 text-lg leading-none">&times;</button>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="p-3.5 bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 rounded-xl text-sm flex items-center justify-between">
            <span><?php echo SecurityHelper::escape($success); ?></span>
            <button type="button" onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-emerald-200 text-lg leading-none">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Data Table (Replaces Cards) -->
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 90px;"># / Type</th>
                    <th>Archived Task & Details</th>
                    <th>Project</th>
                    <th>Created By</th>
                    <th>Created Date</th>
                    <th class="text-right" style="width: 170px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($archivedTasks)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-12 text-slate-400">
                            <div class="max-w-sm mx-auto space-y-2">
                                <p class="text-base font-semibold text-slate-300">No archived tasks found</p>
                                <p class="text-xs text-slate-500">
                                    <?php if ($hasFiltersActive): ?>
                                        Try adjusting your filter settings.
                                    <?php else: ?>
                                        Tasks marked as Done can be archived from the dashboard to keep your workspace tidy.
                                    <?php endif; ?>
                                </p>
                                <div class="pt-2">
                                    <a href="index.php" class="text-xs text-indigo-400 hover:text-indigo-300 font-medium">&larr; Return to Active Board</a>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($archivedTasks as $task): ?>
                        <?php $taskProj = $projectObjMap[$task->getProjectId()] ?? null; ?>
                        <tr>
                            <!-- # / Type -->
                            <td class="font-mono text-xs" data-sort-value="<?php echo $task->getId(); ?>">
                                <?php if ($task->isBug()): ?>
                                    <span class="status-pill status-pill--bug">BUG #<?php echo $task->getId(); ?></span>
                                <?php else: ?>
                                    <span class="status-pill status-pill--todo">#<?php echo $task->getId(); ?></span>
                                <?php endif; ?>
                            </td>

                            <!-- Title & Details -->
                            <td data-sort-value="<?php echo SecurityHelper::escape($task->getTitle()); ?>">
                                <div class="space-y-0.5">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <a href="task_detail.php?id=<?php echo $task->getId(); ?>"
                                           class="font-semibold text-slate-300 line-through hover:text-indigo-400 transition">
                                            <?php echo SecurityHelper::escape($task->getTitle()); ?>
                                        </a>
                                        <?php if ($taskProj && $taskProj->isPrivate()): ?>
                                            <span class="status-pill status-pill--private text-[10px]">🔒 Private</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($task->getDetails()): ?>
                                        <div class="text-xs text-slate-500 line-clamp-1">
                                            <?php echo SecurityHelper::escape(strip_tags($task->getDetails())); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Project -->
                            <td class="text-xs" data-sort-value="<?php echo SecurityHelper::escape($projectMap[$task->getProjectId()] ?? ''); ?>">
                                <span class="text-slate-400 inline-flex items-center gap-1">
                                    <?php if ($taskProj && $taskProj->isBoardGame()): ?>
                                        <span title="Board Game">🎲</span>
                                    <?php endif; ?>
                                    <span><?php echo SecurityHelper::escape($projectMap[$task->getProjectId()] ?? 'Unknown Project'); ?></span>
                                </span>
                            </td>

                            <!-- Created By -->
                            <td class="text-xs text-slate-400" data-sort-value="<?php echo SecurityHelper::escape($userMap[$task->getCreatedBy()] ?? ''); ?>">
                                <?php echo SecurityHelper::escape($userMap[$task->getCreatedBy()] ?? 'Unknown User'); ?>
                            </td>

                            <!-- Created Date -->
                            <td class="text-xs text-slate-400 whitespace-nowrap" data-sort-value="<?php echo strtotime($task->getCreatedAt()); ?>">
                                <?php echo date('M d, Y', strtotime($task->getCreatedAt())); ?>
                            </td>

                            <!-- Actions -->
                            <td class="text-right text-xs">
                                <div class="flex items-center justify-end gap-3">
                                    <form action="archive.php?<?php echo SecurityHelper::escape(http_build_query($_GET)); ?>" method="POST" class="inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                                        <input type="hidden" name="action" value="unarchive">
                                        <input type="hidden" name="task_id" value="<?php echo $task->getId(); ?>">
                                        <button type="submit" class="text-indigo-400 hover:text-indigo-300 font-medium transition"
                                            onclick="return showCustomConfirm('Restore task #<?php echo $task->getId(); ?> to active board?', this.form, 'Unarchive', 'Restore Task');">
                                            Unarchive
                                        </button>
                                    </form>
                                    <a href="task_detail.php?id=<?php echo $task->getId(); ?>"
                                       class="text-slate-400 hover:text-slate-200 font-medium transition">
                                        Details &rarr;
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<?php
require_once __DIR__ . '/templates/footer.php';
?>
