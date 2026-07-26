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

$selectedProjectId = isset($_GET['project_id']) && $_GET['project_id'] !== '' ? (int)$_GET['project_id'] : null;
$onlyBugs = isset($_GET['only_bugs']) && $_GET['only_bugs'] === '1';
$sortBy = $_GET['sort_by'] ?? '';

$csrfToken = SecurityHelper::generateCsrfToken();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'unarchive') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (SecurityHelper::verifyCsrfToken($submittedToken)) {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $currentUserId = SecurityHelper::getCurrentUserId() ?? 0;
        try {
            $taskService->unarchiveTask($taskId, $currentUserId);
            $success = "Task #" . $taskId . " has been unarchived and restored to active board.";
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
    } else {
        $error = 'Security check failed. Please try again.';
    }
}

// Fetch only archived tasks (isArchived = true)
$archivedTasks = $taskService->getTasksFiltered($selectedProjectId, null, $onlyBugs, $sortBy, true);

$projects = $projectService->getAllProjects();
$users = $userService->getAllUsers();

$projectMap = [];
foreach ($projects as $p) {
    $projectMap[$p->getId()] = $p->getName();
}

$userMap = [];
foreach ($users as $u) {
    $userMap[$u->getId()] = $u->getName();
}

require_once __DIR__ . '/templates/header.php';
?>

<div class="space-y-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-white flex items-center gap-3">
                <span>Task Archive</span>
                <span class="text-sm font-semibold px-3 py-1 bg-amber-500/10 text-amber-400 border border-amber-500/20 rounded-full">
                    <?php echo count($archivedTasks); ?> Archived
                </span>
            </h1>
            <p class="text-slate-400 mt-1">Completed tasks stored away for reference. Restore them anytime.</p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="index.php" class="inline-flex items-center px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 font-medium text-sm rounded-lg border border-slate-700 transition duration-200">
                &larr; Back to Dashboard
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="bg-rose-500/10 border border-rose-500/20 text-rose-300 px-4 py-3 rounded-xl text-sm">
            <?php echo SecurityHelper::escape($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 px-4 py-3 rounded-xl text-sm">
            <?php echo SecurityHelper::escape($success); ?>
        </div>
    <?php endif; ?>

    <!-- Filter Bar -->
    <div class="bg-slate-900/50 border border-slate-800 p-4 rounded-xl shadow-md">
        <form action="archive.php" method="GET" class="flex flex-col md:flex-row items-end gap-4">
            <div class="flex-grow w-full md:max-w-xs">
                <label for="project_filter" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Filter by Project</label>
                <select id="project_filter" name="project_id" onchange="this.form.submit()"
                    class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 text-sm transition outline-none">
                    <option value="">All Projects</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?php echo $project->getId(); ?>" <?php echo $selectedProjectId === $project->getId() ? 'selected' : ''; ?>>
                            <?php echo SecurityHelper::escape($project->getName()); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex-grow w-full md:max-w-xs">
                <label for="bug_filter" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Filter by Type</label>
                <select id="bug_filter" name="only_bugs" onchange="this.form.submit()"
                    class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 text-sm transition outline-none">
                    <option value="0" <?php echo !$onlyBugs ? 'selected' : ''; ?>>All Tasks</option>
                    <option value="1" <?php echo $onlyBugs ? 'selected' : ''; ?>>Bugs Only</option>
                </select>
            </div>

            <div class="flex-grow w-full md:max-w-xs">
                <label for="sort_by" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Sort By</label>
                <select id="sort_by" name="sort_by" onchange="this.form.submit()"
                    class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 text-sm transition outline-none">
                    <option value="" <?php echo $sortBy === '' ? 'selected' : ''; ?>>Default (Newest First)</option>
                    <option value="deadline" <?php echo $sortBy === 'deadline' ? 'selected' : ''; ?>>Due Date</option>
                    <option value="alphabetical" <?php echo $sortBy === 'alphabetical' ? 'selected' : ''; ?>>Alphabetical</option>
                    <option value="task_number" <?php echo $sortBy === 'task_number' ? 'selected' : ''; ?>>Task Number</option>
                </select>
            </div>

            <?php if ($selectedProjectId !== null || $onlyBugs || $sortBy !== ''): ?>
                <a href="archive.php" class="px-3 py-2 text-xs font-semibold text-slate-400 hover:text-slate-200 transition">Clear Filters</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Archived Task List Grid -->
    <?php if (empty($archivedTasks)): ?>
        <div class="bg-slate-900/40 border border-slate-800/80 rounded-2xl p-12 text-center space-y-3">
            <p class="text-slate-400 text-base font-medium">No archived tasks found.</p>
            <p class="text-slate-500 text-xs">Completed tasks can be archived from the main dashboard or task detail page.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($archivedTasks as $task): ?>
                <div class="bg-slate-900 border border-slate-800/80 p-5 rounded-2xl flex flex-col justify-between space-y-4 shadow-lg hover:border-slate-700 transition">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-indigo-400 uppercase tracking-wider">
                                <?php echo SecurityHelper::escape($projectMap[$task->getProjectId()] ?? 'Unknown Project'); ?>
                            </span>
                            <?php if ($task->isBug()): ?>
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-500/20 text-rose-400 border border-rose-500/30">BUG</span>
                            <?php endif; ?>
                        </div>
                        <h3 class="text-base font-bold text-slate-200 line-through">
                            <a href="task_detail.php?id=<?php echo $task->getId(); ?>" class="hover:text-indigo-400 transition">
                                <span class="text-slate-500 font-medium mr-1 no-underline">#<?php echo $task->getId(); ?>:</span><?php echo SecurityHelper::escape($task->getTitle()); ?>
                            </a>
                        </h3>
                        <p class="text-xs text-slate-400 line-clamp-2">
                            <?php echo SecurityHelper::escape($task->getDetails() ?: 'No details'); ?>
                        </p>
                    </div>

                    <div class="pt-4 border-t border-slate-800/60 flex items-center justify-between text-xs">
                        <span class="text-slate-500">
                            Created by <?php echo SecurityHelper::escape($userMap[$task->getCreatedBy()] ?? 'Unknown'); ?>
                        </span>

                        <div class="flex items-center space-x-3">
                            <form action="archive.php?<?php echo SecurityHelper::escape(http_build_query($_GET)); ?>" method="POST" class="inline">
                                <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                                <input type="hidden" name="action" value="unarchive">
                                <input type="hidden" name="task_id" value="<?php echo $task->getId(); ?>">
                                <button type="submit" class="px-3 py-1.5 bg-indigo-600/20 hover:bg-indigo-600/30 text-indigo-300 border border-indigo-500/30 text-xs font-medium rounded-lg transition" onclick="return confirm('Restore task #<?php echo $task->getId(); ?> to active board?');">
                                    Unarchive
                                </button>
                            </form>
                            <a href="task_detail.php?id=<?php echo $task->getId(); ?>" class="text-slate-400 hover:text-slate-200 font-medium">Details &rarr;</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/templates/footer.php';
?>
