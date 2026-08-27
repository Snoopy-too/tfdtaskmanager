<?php
declare(strict_types=1);

$container = require_once __DIR__ . '/src/bootstrap.php';

use App\Infrastructure\Security\SecurityHelper;
use App\Application\Services\ProjectService;
use App\Application\Exceptions\ValidationException;

SecurityHelper::requireLogin();

$projectService = $container->get(ProjectService::class);

$currentUserId = SecurityHelper::getCurrentUserId() ?? 0;
$error = '';
$success = '';
$csrfToken = SecurityHelper::generateCsrfToken();

$editProject = null;
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
if ($editId) {
    $editProject = $projectService->getProjectById($editId, $currentUserId);
    if (!$editProject) {
        $error = 'Project not found or access denied.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!SecurityHelper::verifyCsrfToken($submittedToken)) {
        $error = 'Security check failed. Please try again.';
    } else {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : null;
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $isPrivate = isset($_POST['is_private']) && $_POST['is_private'] === '1';

        try {
            if ($id) {
                $projectService->updateProject($id, $name, $description, $isPrivate, $currentUserId);
                $success = "Project '$name' successfully updated.";
                $editProject = null;
            } else {
                $projectService->createProject($name, $description, $isPrivate, $currentUserId);
                $success = "Project '$name' successfully created.";
            }
        } catch (ValidationException $e) {
            $error = $e->getMessage();
            if ($id) {
                $editProject = $projectService->getProjectById($id, $currentUserId);
            }
        }
    }
}

$projects = $projectService->getAllProjects($currentUserId);

require_once __DIR__ . '/templates/header.php';
?>

<div class="space-y-8">
    <div>
        <h1 class="text-3xl font-extrabold tracking-tight text-white">Board Game Projects</h1>
        <p class="text-slate-400 mt-1">Manage game titles and categorize prototyping tasks.</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="p-4 bg-rose-500/10 border border-rose-500/20 text-rose-400 rounded-xl text-sm">
            <?php echo SecurityHelper::escape($error); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-xl text-sm">
            <?php echo SecurityHelper::escape($success); ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <?php
        $isEdit = $editProject !== null;
        $formTitle = $isEdit ? 'Edit Project' : 'Add New Project';
        $buttonText = $isEdit ? 'Save Changes' : 'Add Project';
        $nameValue = $isEdit ? $editProject->getName() : '';
        $descValue = $isEdit ? $editProject->getDescription() : '';
        $isPrivateValue = $isEdit ? $editProject->isPrivate() : false;
        ?>
        <div class="bg-slate-900/50 border border-slate-800 p-6 rounded-2xl shadow-xl h-fit">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-slate-200"><?php echo $formTitle; ?></h2>
                <?php if ($isEdit): ?>
                    <a href="projects.php" class="text-xs text-indigo-400 hover:text-indigo-300 font-medium transition">Cancel</a>
                <?php endif; ?>
            </div>

            <form action="projects.php" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                <?php if ($isEdit): ?>
                    <input type="hidden" name="id" value="<?php echo $editProject->getId(); ?>">
                <?php endif; ?>

                <div>
                    <label for="name" class="block text-sm font-medium text-slate-300 mb-1">Project Name</label>
                    <input type="text" id="name" name="name" required
                        placeholder="e.g., Space Strategy, Prison Game"
                        value="<?php echo SecurityHelper::escape($nameValue); ?>"
                        class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 placeholder-slate-500 transition outline-none">
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-slate-300 mb-1">Description</label>
                    <textarea id="description" name="description" rows="3"
                        placeholder="Short summary of game mechanics or concepts..."
                        class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 placeholder-slate-500 transition outline-none"><?php echo SecurityHelper::escape($descValue); ?></textarea>
                </div>

                <div class="pt-1">
                    <label class="flex items-start space-x-3 cursor-pointer select-none bg-slate-950/40 border border-slate-800/80 p-3 rounded-xl hover:border-slate-700 transition">
                        <input type="checkbox" id="is_private" name="is_private" value="1" <?php echo $isPrivateValue ? 'checked' : ''; ?>
                            class="mt-1 h-4 w-4 rounded bg-slate-900 border-slate-700 text-indigo-600 focus:ring-indigo-500 focus:ring-offset-slate-900">
                        <div>
                            <span class="text-sm font-semibold text-slate-200 flex items-center gap-1.5">
                                <span>🔒</span> Private Project
                            </span>
                            <p class="text-xs text-slate-400 mt-0.5">Only you can view and access this project, its tasks, and its studio assets. Default is public.</p>
                        </div>
                    </label>
                </div>

                <button type="submit"
                    class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-medium py-2 rounded-lg transition duration-200">
                    <?php echo $buttonText; ?>
                </button>
            </form>
        </div>

        <div class="lg:col-span-2 space-y-4">
            <h2 class="text-xl font-bold text-slate-200 mb-6 font-semibold">Active Board Games</h2>
            
            <?php if (empty($projects)): ?>
                <div class="bg-slate-900/30 border border-slate-800/80 rounded-2xl p-12 text-center text-slate-400">
                    <p class="text-lg font-medium">No projects created yet.</p>
                    <p class="text-sm mt-1 text-slate-500">Create one on the left to start categorizing prototype tasks.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($projects as $project): ?>
                        <div class="bg-slate-900/50 border border-slate-800/80 p-5 rounded-2xl hover:border-slate-700 transition duration-300 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <h3 class="text-lg font-bold text-indigo-300">
                                        <?php echo SecurityHelper::escape($project->getName()); ?>
                                    </h3>
                                    <?php if ($project->isPrivate()): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                            🔒 Private
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-slate-800 text-slate-400 border border-slate-700/60">
                                            🌐 Public
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-sm text-slate-400 line-clamp-3">
                                    <?php echo SecurityHelper::escape($project->getDescription() ?: 'No description provided.'); ?>
                                </p>
                            </div>
                            <div class="mt-4 pt-3 border-t border-slate-800/60 text-xs text-slate-500 flex items-center justify-between">
                                <span>Created: <?php echo date('M d, Y', strtotime($project->getCreatedAt())); ?></span>
                                <div class="flex items-center space-x-3">
                                    <a href="projects.php?edit=<?php echo $project->getId(); ?>" class="text-slate-400 hover:text-indigo-300 font-medium transition">Edit</a>
                                    <span class="text-slate-700">|</span>
                                    <a href="index.php?project_id=<?php echo $project->getId(); ?>" class="text-indigo-400 hover:text-indigo-300 font-medium transition">View Tasks &rarr;</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php
require_once __DIR__ . '/templates/footer.php';
?>
