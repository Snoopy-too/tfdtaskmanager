<?php
declare(strict_types=1);

$container = require_once __DIR__ . '/src/bootstrap.php';

use App\Infrastructure\Security\SecurityHelper;
use App\Application\Services\TaskService;
use App\Application\Services\ProjectService;
use App\Application\Exceptions\ValidationException;

SecurityHelper::requireLogin();

$taskService = $container->get(TaskService::class);
$projectService = $container->get(ProjectService::class);

$currentUserId = SecurityHelper::getCurrentUserId() ?? 0;
$taskId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$task = $taskService->getTaskById($taskId, $currentUserId);

if (!$task) {
    http_response_code(404);
    echo "Task not found.";
    exit();
}

$error = '';
$success = '';
$csrfToken = SecurityHelper::generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!SecurityHelper::verifyCsrfToken($submittedToken)) {
        $error = 'Security check failed. Please try again.';
    } else {
        $action = $_POST['action'] ?? 'update';

        if ($action === 'delete') {
            try {
                $taskService->deleteTask($taskId, $currentUserId);
                header('Location: index.php?deleted=1');
                exit();
            } catch (ValidationException $e) {
                $error = $e->getMessage();
            }
        } else {
            // Update task fields
            $projectId = (int)($_POST['project_id'] ?? 0);
            $title = $_POST['title'] ?? '';
            $details = $_POST['details'] ?? '';
            $deadline = $_POST['deadline'] ?? '';
            $status = $_POST['status'] ?? 'To Do';
            $isBug = isset($_POST['is_bug']) && $_POST['is_bug'] === '1';
            $version = (int)($_POST['version'] ?? 0);

            try {
                $taskService->updateTask(
                    $taskId,
                    $projectId,
                    $title,
                    $details,
                    !empty($deadline) ? $deadline : null,
                    $status,
                    $isBug,
                    $currentUserId,
                    $version
                );

                $success = "Task updated successfully.";
                $task = $taskService->getTaskById($taskId, $currentUserId); // Reload
            } catch (ValidationException $e) {
                $error = $e->getMessage();
            }
        }
    }
}

$projects = $projectService->getAllProjects($currentUserId);

require_once __DIR__ . '/templates/header.php';

// Prepare header actions (Delete button in header)
ob_start();
?>
<form action="edit_task.php?id=<?php echo $taskId; ?>" method="POST" class="inline"
      onsubmit="return showCustomConfirm('Are you sure you want to permanently delete this task?', this, 'Delete', 'Delete Task');">
    <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
    <input type="hidden" name="action" value="delete">
    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 text-xs font-semibold rounded-lg transition duration-200">
        Delete Task
    </button>
</form>
<?php
$headerActionsHtml = ob_get_clean();

$title = 'Edit ' . ($task->isBug() ? 'Bug #' : 'Task #') . $task->getId();
renderPageHeader($title, $task->getStatus(), $headerActionsHtml, 'task_detail.php?id=' . $taskId);
?>

<div class="page-container max-w-3xl space-y-5">

    <?php if (!empty($error)): ?>
        <div class="p-3.5 bg-rose-500/10 border border-rose-500/20 text-rose-300 rounded-xl text-sm flex items-center justify-between">
            <span><?php echo SecurityHelper::escape($error); ?></span>
            <button type="button" onclick="this.parentElement.remove()" class="text-rose-400 hover:text-rose-200 text-lg leading-none">&times;</button>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="p-3.5 bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 rounded-xl text-sm flex items-center justify-between">
            <span><?php echo SecurityHelper::escape($success); ?></span>
            <button type="button" onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-emerald-200 text-lg leading-none">&times;</button>
        </div>
    <?php endif; ?>

    <div class="bg-slate-900/60 border border-slate-800 p-6 rounded-xl shadow-sm">
        <form action="edit_task.php?id=<?php echo $taskId; ?>" method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="version" value="<?php echo $task->getVersion(); ?>">

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-2">
                    <label for="title" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Task Title *</label>
                    <input type="text" id="title" name="title" required
                        value="<?php echo SecurityHelper::escape($task->getTitle()); ?>"
                        class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 text-sm outline-none transition">
                </div>

                <div>
                    <label for="project_id" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Project *</label>
                    <select id="project_id" name="project_id" required
                        class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 text-sm outline-none transition cursor-pointer">
                        <?php foreach ($projects as $project): ?>
                            <option value="<?php echo $project->getId(); ?>" <?php echo $task->getProjectId() === $project->getId() ? 'selected' : ''; ?>>
                                <?php echo SecurityHelper::escape(($project->isBoardGame() ? '🎲 ' : '') . $project->getName() . ($project->isPrivate() ? ' (🔒 Private)' : '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label for="details" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Description & Guidelines</label>
                <textarea id="details" name="details" rows="5"
                    class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 text-sm outline-none transition"><?php echo SecurityHelper::escape($task->getDetails()); ?></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                <div>
                    <label for="status" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Status *</label>
                    <select id="status" name="status" required
                        class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 text-sm outline-none transition cursor-pointer">
                        <option value="To Do" <?php echo $task->getStatus() === 'To Do' ? 'selected' : ''; ?>>To Do</option>
                        <option value="In Progress" <?php echo $task->getStatus() === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="Done" <?php echo $task->getStatus() === 'Done' ? 'selected' : ''; ?>>Done</option>
                    </select>
                </div>

                <div>
                    <label for="deadline" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Deadline</label>
                    <input type="date" id="deadline" name="deadline"
                        value="<?php echo $task->getDeadline() ? SecurityHelper::escape($task->getDeadline()) : ''; ?>"
                        class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 text-sm outline-none transition">
                </div>

                <div class="pt-5">
                    <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" id="is_bug" name="is_bug" value="1"
                            <?php echo $task->isBug() ? 'checked' : ''; ?>
                            class="w-4 h-4 rounded bg-slate-900 border-slate-700 text-rose-500 focus:ring-rose-500">
                        <span class="text-xs font-semibold text-rose-400">Mark as Bug / Defect</span>
                    </label>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-800/80">
                <a href="task_detail.php?id=<?php echo $taskId; ?>" class="px-4 py-2 text-xs font-medium text-slate-400 hover:text-slate-200 transition">
                    Cancel
                </a>
                <button type="submit"
                    class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-lg shadow-sm transition">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<?php
require_once __DIR__ . '/templates/footer.php';
?>
