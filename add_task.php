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

$error = '';
$success = '';
$csrfToken = SecurityHelper::generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!SecurityHelper::verifyCsrfToken($submittedToken)) {
        $error = 'Security check failed. Please try again.';
    } else {
        $projectId = (int)($_POST['project_id'] ?? 0);
        $title = $_POST['title'] ?? '';
        $details = $_POST['details'] ?? '';
        $deadline = $_POST['deadline'] ?? '';
        $creatorId = SecurityHelper::getCurrentUserId() ?? 0;
        $isBug = isset($_POST['is_bug']) && $_POST['is_bug'] === '1';

        try {
            $taskService->createTask($projectId, $title, $details, !empty($deadline) ? $deadline : null, $creatorId, $isBug);
            $success = "Task '$title' successfully created.";
        } catch (ValidationException $e) {
            $error = $e->getMessage();
        }
    }
}

$projects = $projectService->getAllProjects();

require_once __DIR__ . '/templates/header.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="mb-8">
        <a href="index.php" class="text-sm text-indigo-400 hover:text-indigo-300 font-medium transition">&larr; Back to Dashboard</a>
        <h1 class="text-3xl font-extrabold tracking-tight text-white mt-2">Create New Task</h1>
        <p class="text-slate-400 mt-1">Add a new prototyping task and associate it with a board game project.</p>
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

    <?php if (empty($projects)): ?>
        <div class="bg-slate-900/50 border border-slate-800 rounded-2xl p-8 text-center text-slate-400">
            <p class="text-lg font-medium">No projects available.</p>
            <p class="text-sm mt-1 text-slate-500 mb-4">You must create at least one project before adding tasks.</p>
            <a href="projects.php" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-lg transition duration-200">
                Go to Projects
            </a>
        </div>
    <?php else: ?>
        <div class="bg-slate-900/50 border border-slate-800 p-8 rounded-2xl shadow-xl">
            <form action="add_task.php" method="POST" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">

                <div>
                    <label for="project_id" class="block text-sm font-medium text-slate-300 mb-1.5">Project / Game Title</label>
                    <select id="project_id" name="project_id" required
                        class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-4 py-2.5 text-slate-100 transition outline-none">
                        <option value="">Select a project...</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?php echo $project->getId(); ?>">
                                <?php echo SecurityHelper::escape($project->getName()); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="title" class="block text-sm font-medium text-slate-300 mb-1.5">Task Title</label>
                    <input type="text" id="title" name="title" required
                        placeholder="e.g., Draft card design, Write rulebook section"
                        class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-4 py-2.5 text-slate-100 placeholder-slate-500 transition outline-none">
                </div>

                <div>
                    <label for="details" class="block text-sm font-medium text-slate-300 mb-1.5">Description / Details</label>
                    <textarea id="details" name="details" rows="5"
                        placeholder="Describe the task instructions, prototyping materials required, or specific guidelines..."
                        class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-4 py-2.5 text-slate-100 placeholder-slate-500 transition outline-none"></textarea>
                </div>

                <div>
                    <label for="deadline" class="block text-sm font-medium text-slate-300 mb-1.5">Deadline (Optional)</label>
                    <input type="date" id="deadline" name="deadline"
                        class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-4 py-2.5 text-slate-100 placeholder-slate-500 transition outline-none">
                </div>

                <div class="flex items-center space-x-2.5 py-1">
                    <input type="checkbox" id="is_bug" name="is_bug" value="1"
                        class="w-4 h-4 rounded bg-slate-950/60 border-slate-800 text-indigo-600 focus:ring-indigo-500 focus:ring-offset-slate-900 focus:ring-1">
                    <label for="is_bug" class="text-sm font-medium text-slate-300 select-none cursor-pointer">This task is a bug</label>
                </div>

                <div class="flex space-x-4 pt-2">
                    <button type="submit"
                        class="flex-1 bg-indigo-600 hover:bg-indigo-500 text-white font-medium py-2.5 rounded-lg transition duration-200">
                        Create Task
                    </button>
                    <a href="index.php" class="bg-slate-800 hover:bg-slate-700 text-slate-300 px-6 py-2.5 rounded-lg transition duration-200 flex items-center justify-center">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/templates/footer.php';
?>
