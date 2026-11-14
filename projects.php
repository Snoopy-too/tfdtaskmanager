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
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isPrivate = isset($_POST['is_private']) && $_POST['is_private'] === '1';
        $type = trim($_POST['type'] ?? '');
        $isBoardGame = (isset($_POST['is_boardgame']) && $_POST['is_boardgame'] === '1') || ($type === 'boardgame');
        $classification = $isBoardGame ? 'boardgame' : ($type ?: 'standard');

        try {
            if ($id) {
                $projectService->updateProject($id, $name, $description, $isPrivate, $currentUserId, $isBoardGame, $classification);
                $success = "Project '$name' successfully updated.";
                $editProject = null;
            } else {
                $projectService->createProject($name, $description, $isPrivate, $currentUserId, $isBoardGame, $classification);
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

// Prepare header actions
ob_start();
?>
<button type="button" onclick="toggleCollapsible('project-form-panel')"
    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-xs font-semibold bg-indigo-600 hover:bg-indigo-500 text-white shadow-sm hover:shadow transition duration-150">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
    <span><?php echo $editProject ? 'Edit Project' : 'New Project'; ?></span>
</button>
<?php
$headerActionsHtml = ob_get_clean();

// Render sticky-top header
renderPageHeader('Projects', count($projects) . ' projects', $headerActionsHtml);
?>

<div class="page-container space-y-5">

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

    <!-- Collapsible Form Panel (Hidden until needed) -->
    <?php
    $isEdit = $editProject !== null;
    $formTitle = $isEdit ? 'Edit Project' : 'Add New Project';
    $buttonText = $isEdit ? 'Save Changes' : 'Create Project';
    $nameValue = $isEdit ? $editProject->getName() : '';
    $descValue = $isEdit ? $editProject->getDescription() : '';
    $isPrivateValue = $isEdit ? $editProject->isPrivate() : false;
    $isBoardGameValue = $isEdit ? $editProject->isBoardGame() : false;
    $isFormOpen = $isEdit || !empty($error);
    ?>
    <div id="project-form-panel" class="form-panel-collapsible <?php echo $isFormOpen ? 'is-open' : ''; ?>">
        <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-800">
            <div>
                <h2 class="text-base font-bold text-slate-100"><?php echo $formTitle; ?></h2>
                <p class="text-xs text-slate-400">Configure project details, board game classification, and privacy settings.</p>
            </div>
            <button type="button" onclick="toggleCollapsible('project-form-panel')" class="text-slate-400 hover:text-slate-200 text-sm font-semibold p-1">
                &times; Close
            </button>
        </div>

        <form action="projects.php" method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
            <?php if ($isEdit): ?>
                <input type="hidden" name="id" value="<?php echo $editProject->getId(); ?>">
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="proj_name" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Project Name *</label>
                    <input type="text" id="proj_name" name="name" required
                        placeholder="e.g., Space Strategy, Deep Sea Expedition"
                        value="<?php echo SecurityHelper::escape($nameValue); ?>"
                        class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 text-sm outline-none transition">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Classification</label>
                    <label class="flex items-center gap-2.5 p-2 bg-slate-950/40 border border-slate-800 rounded-lg hover:border-slate-700 transition cursor-pointer select-none">
                        <input type="checkbox" id="proj_is_boardgame" name="is_boardgame" value="1" <?php echo $isBoardGameValue ? 'checked' : ''; ?>
                            class="rounded bg-slate-900 border-slate-700 text-purple-600 focus:ring-purple-500 w-4 h-4">
                        <span class="text-xs font-medium text-slate-200 flex items-center gap-1.5">
                            <span>🎲</span> Board Game (<code class="text-purple-400 font-mono text-[11px]">boardgame</code>)
                        </span>
                    </label>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Privacy Setting</label>
                    <label class="flex items-center gap-2.5 p-2 bg-slate-950/40 border border-slate-800 rounded-lg hover:border-slate-700 transition cursor-pointer select-none">
                        <input type="checkbox" id="proj_is_private" name="is_private" value="1" <?php echo $isPrivateValue ? 'checked' : ''; ?>
                            class="rounded bg-slate-900 border-slate-700 text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                        <span class="text-xs font-medium text-slate-200 flex items-center gap-1.5">
                            <span>🔒</span> Private Project (Only visible to you)
                        </span>
                    </label>
                </div>
            </div>

            <div>
                <label for="proj_description" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Description (Optional)</label>
                <textarea id="proj_description" name="description" rows="2"
                    placeholder="Short summary of game mechanics, concept, or components..."
                    class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 text-sm outline-none transition"><?php echo SecurityHelper::escape($descValue); ?></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800/80">
                <?php if ($isEdit): ?>
                    <a href="projects.php" class="px-4 py-2 rounded-lg text-xs font-medium text-slate-400 hover:text-slate-200 transition">
                        Cancel Edit
                    </a>
                <?php else: ?>
                    <button type="button" onclick="toggleCollapsible('project-form-panel')"
                        class="px-4 py-2 rounded-lg text-xs font-medium text-slate-400 hover:text-slate-200 transition">
                        Cancel
                    </button>
                <?php endif; ?>
                <button type="submit"
                    class="px-5 py-2 rounded-lg text-xs font-semibold bg-indigo-600 hover:bg-indigo-500 text-white shadow-sm transition">
                    <?php echo $buttonText; ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Data Table (Replaces Cards) -->
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Project Name</th>
                    <th>Classification</th>
                    <th>Description</th>
                    <th>Access</th>
                    <th>Created</th>
                    <th class="text-right" style="width: 220px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($projects)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-12 text-slate-400">
                            <div class="max-w-sm mx-auto space-y-2">
                                <p class="text-base font-semibold text-slate-300">No projects yet</p>
                                <p class="text-xs text-slate-500">Create your first board game project to organize tasks, cards, and rulebooks.</p>
                                <div class="pt-2">
                                    <button type="button" onclick="toggleCollapsible('project-form-panel')"
                                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg transition">
                                        + Create Project
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($projects as $project): ?>
                        <tr>
                            <!-- Project Name -->
                            <td data-sort-value="<?php echo SecurityHelper::escape($project->getName()); ?>">
                                <div class="font-semibold text-slate-100 flex items-center gap-2">
                                    <span><?php echo SecurityHelper::escape($project->getName()); ?></span>
                                </div>
                            </td>

                            <!-- Classification -->
                            <td data-sort-value="<?php echo $project->isBoardGame() ? 'boardgame' : 'standard'; ?>">
                                <?php if ($project->isBoardGame()): ?>
                                    <span class="status-pill status-pill--boardgame text-xs">🎲 Board Game</span>
                                <?php else: ?>
                                    <span class="text-xs text-slate-500 font-mono">Standard</span>
                                <?php endif; ?>
                            </td>

                            <!-- Description -->
                            <td class="text-xs text-slate-400 max-w-md">
                                <div class="line-clamp-2">
                                    <?php echo SecurityHelper::escape($project->getDescription() ?: 'No description provided.'); ?>
                                </div>
                            </td>

                            <!-- Access Badge -->
                            <td data-sort-value="<?php echo $project->isPrivate() ? 'Private' : 'Public'; ?>">
                                <?php if ($project->isPrivate()): ?>
                                    <span class="status-pill status-pill--private text-xs">🔒 Private</span>
                                <?php else: ?>
                                    <span class="status-pill status-pill--public text-xs">🌐 Public</span>
                                <?php endif; ?>
                            </td>

                            <!-- Created -->
                            <td class="text-xs text-slate-400 whitespace-nowrap" data-sort-value="<?php echo strtotime($project->getCreatedAt()); ?>">
                                <?php echo date('M d, Y', strtotime($project->getCreatedAt())); ?>
                            </td>

                            <!-- Actions -->
                            <td class="text-right text-xs">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="projects.php?edit=<?php echo $project->getId(); ?>"
                                       class="text-slate-400 hover:text-indigo-400 font-medium transition">
                                        Edit
                                    </a>
                                    <a href="index.php?project_id=<?php echo $project->getId(); ?>"
                                       class="text-indigo-400 hover:text-indigo-300 font-medium transition">
                                        Tasks &rarr;
                                    </a>
                                    <?php if ($project->isBoardGame()): ?>
                                        <a href="board-game-studio/index.php?project_id=<?php echo $project->getId(); ?>"
                                           class="text-purple-400 hover:text-purple-300 font-semibold transition flex items-center gap-1" title="Open in Board Game Studio">
                                            <span>Studio</span> &rarr;
                                        </a>
                                    <?php else: ?>
                                        <a href="board-game-studio/index.php?project_id=<?php echo $project->getId(); ?>"
                                           class="text-slate-500 hover:text-slate-300 transition" title="Open in Board Game Studio">
                                            Studio &rarr;
                                        </a>
                                    <?php endif; ?>
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
