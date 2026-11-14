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
        $creatorId = $currentUserId;
        $isBug = isset($_POST['is_bug']) && $_POST['is_bug'] === '1';

        try {
            $taskService->createTask($projectId, $title, $details, !empty($deadline) ? $deadline : null, $creatorId, $isBug);
            $success = "Task '$title' successfully created.";
        } catch (ValidationException $e) {
            $error = $e->getMessage();
        }
    }
}

$projects = $projectService->getAllProjects($currentUserId);

require_once __DIR__ . '/templates/header.php';

// Render single-row sticky-top header
renderPageHeader('Create New Task', '', '', 'index.php');
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

    <?php if (empty($projects)): ?>
        <div class="bg-slate-900/50 border border-slate-800 rounded-xl p-8 text-center text-slate-400 space-y-3">
            <p class="text-base font-semibold text-slate-200">No projects available</p>
            <p class="text-xs text-slate-400">You must create at least one board game project before creating tasks.</p>
            <div class="pt-2">
                <a href="projects.php" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-medium text-xs rounded-lg transition">
                    + Go to Projects
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="bg-slate-900/60 border border-slate-800 p-6 rounded-xl shadow-sm">
            <form action="add_task.php" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-2">
                        <label for="title" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Task Title *</label>
                        <input type="text" id="title" name="title" required
                            placeholder="e.g., Draft card design, Write rulebook section"
                            class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 text-sm outline-none transition">
                    </div>

                    <div>
                        <label for="project_id" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Project *</label>
                        <select id="project_id" name="project_id" required
                            class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 text-sm outline-none transition cursor-pointer">
                            <option value="">Select a project...</option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?php echo $project->getId(); ?>">
                                    <?php echo SecurityHelper::escape(($project->isBoardGame() ? '🎲 ' : '') . $project->getName() . ($project->isPrivate() ? ' (🔒 Private)' : '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label for="details" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Description & Guidelines</label>
                    <textarea id="details" name="details" rows="4"
                        placeholder="Describe the task instructions, prototyping materials required, or rules affected..."
                        class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 text-sm outline-none transition"></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-center">
                    <div>
                        <label for="deadline" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Deadline (Optional)</label>
                        <input type="date" id="deadline" name="deadline"
                            class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 text-sm outline-none transition">
                    </div>

                    <div class="pt-5">
                        <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                            <input type="checkbox" id="is_bug" name="is_bug" value="1"
                                class="w-4 h-4 rounded bg-slate-900 border-slate-700 text-rose-500 focus:ring-rose-500">
                            <span class="text-xs font-semibold text-rose-400">Mark this task as a Bug / Defect</span>
                        </label>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-800/80">
                    <a href="index.php" class="px-4 py-2 text-xs font-medium text-slate-400 hover:text-slate-200 transition">
                        Cancel
                    </a>
                    <button type="submit"
                        class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-lg shadow-sm transition">
                        Create Task
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/templates/footer.php';
?>
