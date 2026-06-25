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

$taskId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$task = $taskService->getTaskById($taskId);

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
            // Delete task
            $db = $container->get(PDO::class);
            $stmt = $db->prepare("DELETE FROM tasks WHERE id = :id");
            $stmt->execute(['id' => $taskId]);
            
            header('Location: index.php?deleted=1');
            exit();
        } else {
            // Update task fields
            $projectId = (int)($_POST['project_id'] ?? 0);
            $title = $_POST['title'] ?? '';
            $details = $_POST['details'] ?? '';
            $deadline = $_POST['deadline'] ?? '';
            $status = $_POST['status'] ?? 'To Do';

            try {
                if (empty($title)) {
                    throw new ValidationException("Task title is required.");
                }
                if ($projectId <= 0) {
                    throw new ValidationException("Valid project selection is required.");
                }
                if (!empty($deadline) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline)) {
                    throw new ValidationException("Deadline must be in YYYY-MM-DD format.");
                }
                if (!in_array($status, ['To Do', 'In Progress', 'Done'])) {
                    throw new ValidationException("Invalid status selected.");
                }

                // Update entity in DB
                $db = $container->get(PDO::class);
                $stmt = $db->prepare("
                    UPDATE tasks
                    SET project_id = :project_id, title = :title, details = :details,
                        deadline = :deadline, status = :status
                    WHERE id = :id
                ");
                $stmt->execute([
                    'project_id' => $projectId,
                    'title' => $title,
                    'details' => $details,
                    'deadline' => !empty($deadline) ? $deadline : null,
                    'status' => $status,
                    'id' => $taskId
                ]);

                $success = "Task updated successfully.";
                $task = $taskService->getTaskById($taskId); // Reload
            } catch (ValidationException $e) {
                $error = $e->getMessage();
            }
        }
    }
}

$projects = $projectService->getAllProjects();

require_once __DIR__ . '/templates/header.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="mb-8 flex items-center justify-between">
        <div>
            <a href="task_detail.php?id=<?php echo $taskId; ?>" class="text-sm text-indigo-400 hover:text-indigo-300 font-medium transition">&larr; Back to Task Details</a>
            <h1 class="text-3xl font-extrabold tracking-tight text-white mt-2">Edit Task</h1>
        </div>
        
        <!-- Delete Task Form -->
        <form action="edit_task.php?id=<?php echo $taskId; ?>" method="POST" onsubmit="return confirm('Are you sure you want to permanently delete this task?');">
            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
            <input type="hidden" name="action" value="delete">
            <button type="submit" class="px-4 py-2 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 text-sm font-semibold rounded-lg transition duration-200">
                Delete Task
            </button>
        </form>
    </div>

    <?php if (!empty($error)): ?>
        <div class="p-4 bg-rose-500/10 border border-rose-500/20 text-rose-400 rounded-xl text-sm mb-6">
            <?php echo SecurityHelper::escape($error); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-xl text-sm mb-6">
            <?php echo SecurityHelper::escape($success); ?>
        </div>
    <?php endif; ?>

    <div class="bg-slate-900/50 border border-slate-800 p-8 rounded-2xl shadow-xl">
        <form action="edit_task.php?id=<?php echo $taskId; ?>" method="POST" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
            <input type="hidden" name="action" value="update">

            <div>
                <label for="project_id" class="block text-sm font-medium text-slate-300 mb-1.5">Project / Game Title</label>
                <select id="project_id" name="project_id" required
                    class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-4 py-2.5 text-slate-100 transition outline-none">
                    <?php foreach ($projects as $project): ?>
                        <option value="<?php echo $project->getId(); ?>" <?php echo $task->getProjectId() === $project->getId() ? 'selected' : ''; ?>>
                            <?php echo SecurityHelper::escape($project->getName()); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="title" class="block text-sm font-medium text-slate-300 mb-1.5">Task Title</label>
                <input type="text" id="title" name="title" required
                    value="<?php echo SecurityHelper::escape($task->getTitle()); ?>"
                    class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-4 py-2.5 text-slate-100 placeholder-slate-500 transition outline-none">
            </div>

            <div>
                <label for="details" class="block text-sm font-medium text-slate-300 mb-1.5">Description / Details</label>
                <textarea id="details" name="details" rows="5"
                    class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-4 py-2.5 text-slate-100 placeholder-slate-500 transition outline-none"><?php echo SecurityHelper::escape($task->getDetails()); ?></textarea>
            </div>

            <div>
                <label for="deadline" class="block text-sm font-medium text-slate-300 mb-1.5">Deadline</label>
                <input type="date" id="deadline" name="deadline"
                    value="<?php echo $task->getDeadline() ? SecurityHelper::escape($task->getDeadline()) : ''; ?>"
                    class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-4 py-2.5 text-slate-100 placeholder-slate-500 transition outline-none">
            </div>

            <div>
                <label for="status" class="block text-sm font-medium text-slate-300 mb-1.5">Task Status</label>
                <select id="status" name="status" required
                    class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-4 py-2.5 text-slate-100 transition outline-none">
                    <option value="To Do" <?php echo $task->getStatus() === 'To Do' ? 'selected' : ''; ?>>To Do</option>
                    <option value="In Progress" <?php echo $task->getStatus() === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                    <option value="Done" <?php echo $task->getStatus() === 'Done' ? 'selected' : ''; ?>>Done</option>
                </select>
            </div>

            <div class="flex space-x-4 pt-2">
                <button type="submit"
                    class="flex-1 bg-indigo-600 hover:bg-indigo-500 text-white font-medium py-2.5 rounded-lg transition duration-200">
                    Save Changes
                </button>
                <a href="task_detail.php?id=<?php echo $taskId; ?>" class="bg-slate-800 hover:bg-slate-700 text-slate-300 px-6 py-2.5 rounded-lg transition duration-200 flex items-center justify-center">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php
require_once __DIR__ . '/templates/footer.php';
?>
