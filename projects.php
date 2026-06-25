<?php
declare(strict_types=1);

$container = require_once __DIR__ . '/src/bootstrap.php';

use App\Infrastructure\Security\SecurityHelper;
use App\Application\Services\ProjectService;
use App\Application\Exceptions\ValidationException;

SecurityHelper::requireLogin();

$projectService = $container->get(ProjectService::class);

$error = '';
$success = '';
$csrfToken = SecurityHelper::generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!SecurityHelper::verifyCsrfToken($submittedToken)) {
        $error = 'Security check failed. Please try again.';
    } else {
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';

        try {
            $projectService->createProject($name, $description);
            $success = "Project '$name' successfully created.";
        } catch (ValidationException $e) {
            $error = $e->getMessage();
        }
    }
}

$projects = $projectService->getAllProjects();

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
        
        <div class="bg-slate-900/50 border border-slate-800 p-6 rounded-2xl shadow-xl h-fit">
            <h2 class="text-xl font-bold text-slate-200 mb-6">Add New Project</h2>

            <form action="projects.php" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">

                <div>
                    <label for="name" class="block text-sm font-medium text-slate-300 mb-1">Project Name</label>
                    <input type="text" id="name" name="name" required
                        placeholder="e.g., Space Strategy, Prison Game"
                        class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 placeholder-slate-500 transition outline-none">
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-slate-300 mb-1">Description</label>
                    <textarea id="description" name="description" rows="3"
                        placeholder="Short summary of game mechanics or concepts..."
                        class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 placeholder-slate-500 transition outline-none"></textarea>
                </div>

                <button type="submit"
                    class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-medium py-2 rounded-lg transition duration-200">
                    Add Project
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
                                <h3 class="text-lg font-bold text-indigo-300 mb-2">
                                    <?php echo SecurityHelper::escape($project->getName()); ?>
                                </h3>
                                <p class="text-sm text-slate-400 line-clamp-3">
                                    <?php echo SecurityHelper::escape($project->getDescription() ?: 'No description provided.'); ?>
                                </p>
                            </div>
                            <div class="mt-4 pt-3 border-t border-slate-800/60 text-xs text-slate-500 flex items-center justify-between">
                                <span>Created: <?php echo date('M d, Y', strtotime($project->getCreatedAt())); ?></span>
                                <a href="index.php?project_id=<?php echo $project->getId(); ?>" class="text-indigo-400 hover:text-indigo-300 font-medium transition">View Tasks &rarr;</a>
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
