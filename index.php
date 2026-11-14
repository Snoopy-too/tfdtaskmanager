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
$selectedStatus = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null;
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

// Handle actions (Create task, Archive task, Checkout task)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!SecurityHelper::verifyCsrfToken($submittedToken)) {
        $error = 'Security check failed. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'create_task') {
            $projectId = (int)($_POST['project_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $details = trim($_POST['details'] ?? '');
            $deadline = $_POST['deadline'] ?? '';
            $isBug = isset($_POST['is_bug']) && $_POST['is_bug'] === '1';

            try {
                $taskService->createTask($projectId, $title, $details, !empty($deadline) ? $deadline : null, $currentUserId, $isBug);
                header('Location: index.php?' . http_build_query($_GET));
                exit();
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        } elseif ($action === 'archive') {
            $taskId = (int)($_POST['task_id'] ?? 0);
            try {
                $taskService->archiveTask($taskId, $currentUserId);
                header('Location: index.php?' . http_build_query($_GET));
                exit();
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        } elseif ($action === 'checkout') {
            $taskId = (int)($_POST['task_id'] ?? 0);
            $version = (int)($_POST['version'] ?? 0);
            try {
                $taskService->checkoutTask($taskId, $currentUserId, $version);
                header('Location: index.php?' . http_build_query($_GET));
                exit();
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
}

// Fetch all tasks for stats & counts
$allTasks = $taskService->getTasksFiltered($selectedProjectId, null, false, $sortBy, false, $currentUserId);
$tasks = $taskService->getTasksFiltered($selectedProjectId, $selectedStatus, $onlyBugs, $sortBy, false, $currentUserId);
$users = $userService->getAllUsers();

$projectMap = [];
$projectObjMap = [];
foreach ($projects as $p) {
    $projectMap[$p->getId()] = $p->getName();
    $projectObjMap[$p->getId()] = $p;
}
$userMap = array_column(array_map(fn($u) => ['id' => $u->getId(), 'name' => $u->getName()], $users), 'name', 'id');

$totalCount = count($allTasks);
$todoCount = count(array_filter($allTasks, fn($t) => $t->getStatus() === 'To Do'));
$inProgressCount = count(array_filter($allTasks, fn($t) => $t->getStatus() === 'In Progress'));
$doneCount = count(array_filter($allTasks, fn($t) => $t->getStatus() === 'Done'));
$bugCount = count(array_filter($allTasks, fn($t) => $t->isBug()));

$hasFiltersActive = ($selectedProjectId !== null) || $onlyBugs || ($sortBy !== '');

require_once __DIR__ . '/templates/header.php';

// Prepare Single-Row Sticky Header Actions
ob_start();
?>
<button type="button" onclick="toggleCollapsible('create-task-panel')"
    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-xs font-semibold bg-indigo-600 hover:bg-indigo-500 text-white shadow-sm hover:shadow transition duration-150">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
    <span>New Task</span>
</button>
<?php
$headerActionsHtml = ob_get_clean();

$subtitle = ($selectedProjectId !== null && $activeProj)
    ? 'Filtered by: "' . SecurityHelper::escape($activeProj->getName()) . '" &bull; <a href="index.php" class="text-indigo-400 hover:underline font-medium">Show All</a>'
    : $totalCount . ' total';

// Render the single-row sticky-top header
renderPageHeader('Tasks', $subtitle, $headerActionsHtml);
?>

<div class="page-container space-y-5">

    <?php if (!empty($error)): ?>
        <div class="p-3.5 bg-rose-500/10 border border-rose-500/20 text-rose-300 rounded-xl text-sm flex items-center justify-between">
            <span><?php echo SecurityHelper::escape($error); ?></span>
            <button type="button" onclick="this.parentElement.remove()" class="text-rose-400 hover:text-rose-200 text-lg leading-none">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Hidden-by-default Collapsible Create Task Form Panel -->
    <div id="create-task-panel" class="form-panel-collapsible <?php echo !empty($error) && isset($_POST['action']) && $_POST['action'] === 'create_task' ? 'is-open' : ''; ?>">
        <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-800">
            <div>
                <h2 class="text-base font-bold text-slate-100">Create New Task</h2>
                <p class="text-xs text-slate-400">Quickly add a task or report a bug to your board game project.</p>
            </div>
            <button type="button" onclick="toggleCollapsible('create-task-panel')" class="text-slate-400 hover:text-slate-200 text-sm font-semibold p-1">
                &times; Close
            </button>
        </div>

        <form action="index.php?<?php echo SecurityHelper::escape(http_build_query($_GET)); ?>" method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
            <input type="hidden" name="action" value="create_task">

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-2">
                    <label for="new_task_title" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Task Title *</label>
                    <input type="text" id="new_task_title" name="title" required
                        placeholder="e.g., Design player reference card sheet"
                        class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 text-sm outline-none transition">
                </div>

                <div>
                    <label for="new_task_project" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Project *</label>
                    <select id="new_task_project" name="project_id" required
                        class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 text-sm outline-none transition cursor-pointer">
                        <option value="">Select Project</option>
                        <?php foreach ($projects as $proj): ?>
                            <option value="<?php echo $proj->getId(); ?>" <?php echo $selectedProjectId === $proj->getId() ? 'selected' : ''; ?>>
                                <?php echo SecurityHelper::escape(($proj->isBoardGame() ? '🎲 ' : '') . $proj->getName() . ($proj->isPrivate() ? ' (🔒 Private)' : '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                <div class="md:col-span-2">
                    <label for="new_task_details" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Details (Optional)</label>
                    <textarea id="new_task_details" name="details" rows="2"
                        placeholder="Key requirements, mechanics affected, notes..."
                        class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 text-sm outline-none transition"></textarea>
                </div>

                <div class="space-y-3">
                    <div>
                        <label for="new_task_deadline" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Deadline</label>
                        <input type="date" id="new_task_deadline" name="deadline"
                            class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 text-sm outline-none transition">
                    </div>
                    <div>
                        <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                            <input type="checkbox" id="new_task_is_bug" name="is_bug" value="1"
                                class="rounded bg-slate-900 border-slate-700 text-rose-500 focus:ring-rose-500 w-4 h-4">
                            <span class="text-xs font-semibold text-rose-400">Mark as Bug / Defect</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800/80">
                <button type="button" onclick="toggleCollapsible('create-task-panel')"
                    class="px-4 py-2 rounded-lg text-xs font-medium text-slate-400 hover:text-slate-200 transition">
                    Cancel
                </button>
                <button type="submit"
                    class="px-5 py-2 rounded-lg text-xs font-semibold bg-indigo-600 hover:bg-indigo-500 text-white shadow-sm transition">
                    Create Task
                </button>
            </div>
        </form>
    </div>

    <!-- Quick Status Filter Tab Bar (Similar to Stats app) -->
    <div class="flex items-center justify-between border-b border-slate-800/80 pb-2 flex-wrap gap-2">
        <div class="filter-tabs">
            <?php
            $makeQuery = function($status = null, $bugs = null) use ($selectedProjectId) {
                $params = [];
                if ($selectedProjectId !== null) $params['project_id'] = $selectedProjectId;
                if ($status !== null) $params['status'] = $status;
                if ($bugs) $params['only_bugs'] = '1';
                return 'index.php' . (!empty($params) ? '?' . http_build_query($params) : '');
            };
            ?>
            <a href="<?php echo $makeQuery(null, null); ?>"
                class="filter-tab <?php echo ($selectedStatus === null && !$onlyBugs) ? 'is-active' : ''; ?>">
                <span>All Tasks</span>
                <span class="text-[11px] px-1.5 py-0.2 rounded-full bg-slate-800/60 font-semibold"><?php echo $totalCount; ?></span>
            </a>
            <a href="<?php echo $makeQuery('To Do', null); ?>"
                class="filter-tab <?php echo ($selectedStatus === 'To Do' && !$onlyBugs) ? 'is-active' : ''; ?>">
                <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                <span>To Do</span>
                <span class="text-[11px] px-1.5 py-0.2 rounded-full bg-slate-800/60 font-semibold"><?php echo $todoCount; ?></span>
            </a>
            <a href="<?php echo $makeQuery('In Progress', null); ?>"
                class="filter-tab <?php echo ($selectedStatus === 'In Progress' && !$onlyBugs) ? 'is-active' : ''; ?>">
                <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                <span>In Progress</span>
                <span class="text-[11px] px-1.5 py-0.2 rounded-full bg-slate-800/60 font-semibold"><?php echo $inProgressCount; ?></span>
            </a>
            <a href="<?php echo $makeQuery('Done', null); ?>"
                class="filter-tab <?php echo ($selectedStatus === 'Done' && !$onlyBugs) ? 'is-active' : ''; ?>">
                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                <span>Done</span>
                <span class="text-[11px] px-1.5 py-0.2 rounded-full bg-slate-800/60 font-semibold"><?php echo $doneCount; ?></span>
            </a>
            <a href="<?php echo $makeQuery(null, true); ?>"
                class="filter-tab <?php echo ($onlyBugs) ? 'is-active' : ''; ?>">
                <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                <span>Bugs</span>
                <span class="text-[11px] px-1.5 py-0.2 rounded-full bg-slate-800/60 font-semibold"><?php echo $bugCount; ?></span>
            </a>
        </div>

        <div class="text-xs text-slate-500 font-medium">
            Showing <?php echo count($tasks); ?> item<?php echo count($tasks) === 1 ? '' : 's'; ?>
        </div>
    </div>

    <!-- Data Table (Replaces 3-Column Card Layout) -->
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 90px;"># / Type</th>
                    <th>Task & Details</th>
                    <th>Project</th>
                    <th>Status</th>
                    <th>Assignee</th>
                    <th>Due Date</th>
                    <th class="text-right" style="width: 140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tasks)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-12 text-slate-400">
                            <div class="max-w-sm mx-auto space-y-2">
                                <p class="text-base font-semibold text-slate-300">No tasks found</p>
                                <p class="text-xs text-slate-500">
                                    <?php if ($hasFiltersActive || $selectedStatus): ?>
                                        Try adjusting your filters or status tab.
                                    <?php else: ?>
                                        There are no active tasks in your board right now. Click "New Task" to create one.
                                    <?php endif; ?>
                                </p>
                                <?php if ($hasFiltersActive || $selectedStatus): ?>
                                    <div class="pt-2">
                                        <a href="index.php" class="text-xs text-indigo-400 hover:text-indigo-300 font-medium">&larr; Reset all filters</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tasks as $task): ?>
                        <?php
                        $deadlineTime = $task->getDeadline() ? strtotime($task->getDeadline()) : null;
                        $isOverdue = $deadlineTime && $deadlineTime < strtotime(date('Y-m-d'));
                        $taskProj = $projectObjMap[$task->getProjectId()] ?? null;
                        $isDone = ($task->getStatus() === 'Done');
                        ?>
                        <tr>
                            <!-- # / Type Badge -->
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
                                           class="font-semibold text-slate-100 hover:text-indigo-400 transition <?php echo $isDone ? 'line-through text-slate-400' : ''; ?>">
                                            <?php echo SecurityHelper::escape($task->getTitle()); ?>
                                        </a>
                                        <?php if ($taskProj && $taskProj->isPrivate()): ?>
                                            <span class="status-pill status-pill--private text-[10px]">🔒 Private</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($task->getDetails()): ?>
                                        <div class="text-xs text-slate-400 line-clamp-1">
                                            <?php echo SecurityHelper::escape(strip_tags($task->getDetails())); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Project -->
                            <td class="text-xs" data-sort-value="<?php echo SecurityHelper::escape($projectMap[$task->getProjectId()] ?? ''); ?>">
                                <a href="index.php?project_id=<?php echo $task->getProjectId(); ?>"
                                   class="text-indigo-400 hover:underline font-medium inline-flex items-center gap-1">
                                    <?php if (isset($projectObjMap[$task->getProjectId()]) && $projectObjMap[$task->getProjectId()]->isBoardGame()): ?>
                                        <span title="Board Game">🎲</span>
                                    <?php endif; ?>
                                    <span><?php echo SecurityHelper::escape($projectMap[$task->getProjectId()] ?? 'Unknown Project'); ?></span>
                                </a>
                            </td>

                            <!-- Status -->
                            <td data-sort-value="<?php echo SecurityHelper::escape($task->getStatus()); ?>">
                                <?php if ($task->getStatus() === 'To Do'): ?>
                                    <span class="status-pill status-pill--todo">To Do</span>
                                <?php elseif ($task->getStatus() === 'In Progress'): ?>
                                    <span class="status-pill status-pill--progress">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                                        <span>In Progress</span>
                                    </span>
                                <?php elseif ($task->getStatus() === 'Done'): ?>
                                    <span class="status-pill status-pill--done">
                                        <svg class="w-3.5 h-3.5 inline mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                        <span>Done</span>
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Assignee -->
                            <td class="text-xs" data-sort-value="<?php echo SecurityHelper::escape($userMap[$task->getAssignedTo()] ?? ''); ?>">
                                <?php if ($task->getAssignedTo()): ?>
                                    <span class="font-medium text-slate-200"><?php echo SecurityHelper::escape($userMap[$task->getAssignedTo()] ?? 'Unknown'); ?></span>
                                <?php else: ?>
                                    <span class="text-slate-500 font-mono">—</span>
                                <?php endif; ?>
                            </td>

                            <!-- Due Date -->
                            <td class="text-xs" data-sort-value="<?php echo $deadlineTime ?? ''; ?>">
                                <?php if ($deadlineTime): ?>
                                    <span class="<?php echo ($isOverdue && !$isDone) ? 'text-rose-400 font-semibold' : 'text-slate-400'; ?>">
                                        <?php echo date('M d, Y', $deadlineTime); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-slate-600 font-mono">—</span>
                                <?php endif; ?>
                            </td>

                            <!-- Actions -->
                            <td class="text-right text-xs">
                                <div class="flex items-center justify-end gap-2">
                                    <?php if ($task->getStatus() === 'To Do'): ?>
                                        <form action="index.php?<?php echo SecurityHelper::escape(http_build_query($_GET)); ?>" method="POST" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                                            <input type="hidden" name="action" value="checkout">
                                            <input type="hidden" name="task_id" value="<?php echo $task->getId(); ?>">
                                            <input type="hidden" name="version" value="<?php echo $task->getVersion(); ?>">
                                            <button type="submit" class="text-indigo-400 hover:text-indigo-300 font-medium transition" title="Check out task">
                                                Checkout
                                            </button>
                                        </form>
                                    <?php elseif ($task->getStatus() === 'Done'): ?>
                                        <form action="index.php?<?php echo SecurityHelper::escape(http_build_query($_GET)); ?>" method="POST" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                                            <input type="hidden" name="action" value="archive">
                                            <input type="hidden" name="task_id" value="<?php echo $task->getId(); ?>">
                                            <button type="submit" class="text-slate-400 hover:text-amber-400 font-medium transition"
                                                onclick="return showCustomConfirm('Archive task #<?php echo $task->getId(); ?>?', this.form, 'Archive', 'Archive Task');"
                                                title="Archive completed task">
                                                Archive
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <a href="task_detail.php?id=<?php echo $task->getId(); ?>"
                                       class="text-slate-400 hover:text-slate-200 font-medium transition">
                                        View &rarr;
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
