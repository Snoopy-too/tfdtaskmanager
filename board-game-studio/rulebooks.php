<?php
declare(strict_types=1);

$container = require_once __DIR__ . '/../src/bootstrap.php';

use App\Infrastructure\Security\SecurityHelper;
use App\Application\Services\ProjectService;
use App\Application\Services\BgRulebookService;
use App\Application\Exceptions\ValidationException;

SecurityHelper::requireLogin();

$projectService = $container->get(ProjectService::class);
$rulebookService = $container->get(BgRulebookService::class);

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$error = '';
$success = '';
$csrfToken = SecurityHelper::generateCsrfToken();

// Projects list - only board games
$projects = $projectService->getAllProjects($currentUserId);
$boardGameProjects = array_values(array_filter($projects, fn($p) => $p->isBoardGame()));
$activeProjectId = null;

if (isset($_GET['project_id']) && $_GET['project_id'] !== '') {
    $activeProjectId = (int)$_GET['project_id'];
} elseif (isset($_SESSION['last_project_id'])) {
    $candidateId = (int)$_SESSION['last_project_id'];
    $candProject = $projectService->getProjectById($candidateId, $currentUserId);
    if ($candProject && $candProject->isBoardGame()) {
        $activeProjectId = $candidateId;
    }
}

if (!$activeProjectId) {
    header("Location: index.php");
    exit;
}

$activeProject = $projectService->getProjectById($activeProjectId, $currentUserId);
if (!$activeProject || !$activeProject->isBoardGame()) {
    unset($_SESSION['last_project_id']);
    header("Location: index.php");
    exit;
}

$_SESSION['last_project_id'] = $activeProjectId;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $submittedToken = $_POST['csrf_token'] ?? '';
    
    if (!SecurityHelper::verifyCsrfToken($submittedToken)) {
        $error = 'Security check failed. Please try again.';
    } else {
        try {
            if ($action === 'create_rulebook') {
                $name = $_POST['name'] ?? '';
                if (!$activeProjectId) {
                    throw new ValidationException("No active project selected.");
                }
                // Initial content is empty list of blocks
                $newRb = $rulebookService->createRulebook($activeProjectId, $name, [], $currentUserId);
                header("Location: rulebook-editor.php?id=" . $newRb->getId());
                exit;
            } elseif ($action === 'delete_rulebook') {
                $rbId = (int)($_POST['rulebook_id'] ?? 0);
                $rulebookService->deleteRulebook($rbId);
                $success = 'Rulebook deleted successfully.';
            } elseif ($action === 'save_glossary_term') {
                $termId = isset($_POST['term_id']) && $_POST['term_id'] !== '' ? (int)$_POST['term_id'] : null;
                $termKey = $_POST['term_key'] ?? '';
                $termName = $_POST['term_name'] ?? '';
                $termDescription = $_POST['term_description'] ?? '';
                
                if (!$activeProjectId) {
                    throw new ValidationException("No active project selected.");
                }
                $rulebookService->saveGlossaryTerm($activeProjectId, $termId, $termKey, $termName, $termDescription, $currentUserId);
                $success = 'Glossary term saved successfully.';
            } elseif ($action === 'delete_glossary_term') {
                $termId = (int)($_POST['term_id'] ?? 0);
                $rulebookService->deleteGlossaryTerm($termId);
                $success = 'Glossary term deleted successfully.';
            }
        } catch (ValidationException $e) {
            $error = $e->getMessage();
        } catch (\Exception $e) {
            error_log('[BoardGameStudio] rulebooks.php error: ' . $e->getMessage());
            $error = 'An unexpected error occurred. Please try again.';
        }
    }
}

$rulebooks = [];
$glossary = [];
if ($activeProjectId) {
    $rulebooks = $rulebookService->getRulebooksByProject($activeProjectId);
    $glossary = $rulebookService->getGlossaryByProject($activeProjectId);
}

require_once __DIR__ . '/../templates/header.php';

if ($activeProject) {
    renderPageHeader($activeProject->getName(), 'Rulebooks & Glossary', '', 'index.php');
} else {
    renderPageHeader('Rulebooks & Glossary', 'Game Studio', '', 'index.php');
}
?>

<div class="page-container space-y-6">
    <?php
    $activeTab = 'rulebooks';
    include __DIR__ . '/views/studio-nav.php';
    ?>

    <?php if (empty($projects)): ?>
        <div class="p-8 text-center bg-slate-900/50 border border-slate-800 rounded-2xl max-w-lg mx-auto">
            <svg class="mx-auto h-12 w-12 text-slate-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <h2 class="text-lg font-bold text-slate-200">No Projects Found</h2>
            <p class="text-sm text-slate-400 mt-2 mb-6">Create at least one project in the main task manager before using Rulebooks.</p>
            <a href="../projects.php" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-lg transition duration-200">
                Go to Projects
            </a>
        </div>
    <?php elseif (!$activeProjectId): ?>
        <div class="p-12 text-center bg-slate-900/40 border border-slate-800/80 rounded-2xl max-w-2xl mx-auto my-8 space-y-4">
            <div class="inline-flex p-4 bg-amber-500/10 rounded-2xl text-amber-400 mx-auto">
                <svg class="h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
            </div>
            <h2 class="text-xl font-bold text-slate-200">Welcome to Rulebooks & Glossary</h2>
            <p class="text-slate-400 max-w-md mx-auto text-sm">
                Select a project from the top right menu to manage rulebooks, edit glossary definitions, and link assets.
            </p>
        </div>
    <?php else: ?>
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

        <!-- Tabs Container -->
        <div class="border-b border-slate-800 flex space-x-6 text-sm">
            <button onclick="switchTab('rulebooks-tab', this)" class="tab-btn pb-3 border-b-2 border-amber-500 font-bold text-white transition duration-200">
                Rulebooks (<?php echo count($rulebooks); ?>)
            </button>
            <button onclick="switchTab('glossary-tab', this)" class="tab-btn pb-3 border-b-2 border-transparent font-medium text-slate-400 hover:text-white transition duration-200">
                Project Glossary (<?php echo count($glossary); ?>)
            </button>
        </div>

        <!-- Rulebooks Tab -->
        <div id="rulebooks-tab" class="tab-content space-y-6">
            <!-- Section Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-slate-800/80">
                <div class="flex items-center space-x-3">
                    <div class="p-2 rounded-xl bg-amber-500/10 text-amber-400">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-slate-100 flex items-center gap-2">
                            Rulebook Documents
                            <span class="text-xs px-2 py-0.5 rounded-full bg-slate-800 text-slate-400 font-mono font-normal">
                                <?php echo count($rulebooks); ?>
                            </span>
                        </h2>
                        <p class="text-xs text-slate-400">Create, edit, and organize rulebooks, reference guides, and scenario books</p>
                    </div>
                </div>
                <div>
                    <button type="button" onclick="toggleCollapsible('new-rulebook-panel')" class="inline-flex items-center justify-center space-x-2 px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-amber-600/20 transition-all duration-200">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>New Rulebook</span>
                    </button>
                </div>
            </div>

            <!-- Create Rulebook Collapsible Panel -->
            <div id="new-rulebook-panel" class="form-panel-collapsible mb-6">
                <div class="form-panel-collapsible-inner bg-slate-900 border border-amber-500/20 rounded-2xl p-6 shadow-xl space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                        <div class="flex items-center space-x-3">
                            <div class="p-2 bg-amber-500/10 text-amber-400 rounded-xl">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-slate-200">Create New Rulebook</h3>
                                <p class="text-xs text-slate-400">Initialize a document for rules, reference guides, or scenarios</p>
                            </div>
                        </div>
                        <button type="button" onclick="toggleCollapsible('new-rulebook-panel')" class="text-slate-400 hover:text-slate-200 text-xs px-2 py-1 rounded-lg hover:bg-slate-800 transition">
                            &times; Close
                        </button>
                    </div>

                    <form action="" method="POST" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                        <input type="hidden" name="action" value="create_rulebook">

                        <div class="max-w-md">
                            <label for="name" class="block text-xs font-medium text-slate-300 mb-1.5">Rulebook Title</label>
                            <input type="text" id="name" name="name" required placeholder="e.g. Core Rules, Quick Start Guide" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-xl focus:ring-amber-500 focus:border-amber-500 p-2.5">
                        </div>

                        <div class="flex items-center space-x-3 pt-2">
                            <button type="submit" class="bg-amber-600 hover:bg-amber-500 text-white font-bold rounded-xl py-2 px-5 text-xs transition duration-200 shadow-lg shadow-amber-600/20">
                                Create & Open Editor
                            </button>
                            <button type="button" onclick="toggleCollapsible('new-rulebook-panel')" class="bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-xl py-2 px-4 transition duration-200">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Rulebooks Display Grid -->
            <div>
                <?php if (empty($rulebooks)): ?>
                    <div class="p-12 text-center bg-slate-900/30 border border-dashed border-slate-800 rounded-2xl flex flex-col items-center justify-center space-y-3">
                        <svg class="mx-auto h-12 w-12 text-slate-650" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <h4 class="text-sm font-semibold text-slate-300">No Rulebooks Created</h4>
                        <p class="text-xs text-slate-400 max-w-sm">Start by creating a new rulebook document to write and organize your game's rules.</p>
                        <button type="button" onclick="toggleCollapsible('new-rulebook-panel')" class="mt-2 inline-flex items-center space-x-2 px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold rounded-xl transition duration-200">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            <span>New Rulebook</span>
                        </button>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                        <?php foreach ($rulebooks as $rb): ?>
                            <div class="bg-slate-900/80 border border-slate-800/80 p-4 rounded-xl flex flex-col justify-between hover:border-slate-700 hover:shadow-md transition">
                                <div class="space-y-1">
                                    <h3 class="font-bold text-slate-200 text-sm truncate"><?php echo SecurityHelper::escape($rb->getName()); ?></h3>
                                    <div class="flex items-center gap-2 text-xs text-slate-400">
                                        <span class="px-1.5 py-0.5 rounded bg-slate-800 text-slate-300 font-mono text-[10px]">
                                            <?php echo count($rb->getContent()); ?> blocks
                                        </span>
                                        <span class="text-[10px] text-slate-500">
                                            Updated <?php echo date('M d, Y', strtotime($rb->getUpdatedAt())); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between mt-3.5 pt-2.5 border-t border-slate-800/70 gap-2 text-xs">
                                    <a href="rulebook-editor.php?id=<?php echo $rb->getId(); ?>" class="font-semibold text-amber-400 hover:text-amber-300 flex items-center space-x-1.5 bg-amber-500/10 hover:bg-amber-500/20 px-2.5 py-1 rounded-lg border border-amber-500/20 transition">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                        <span>Open Editor</span>
                                    </a>

                                    <form action="" method="POST" class="m-0" onsubmit="return showCustomConfirm('Are you sure you want to delete this rulebook?', this);">
                                        <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                                        <input type="hidden" name="action" value="delete_rulebook">
                                        <input type="hidden" name="rulebook_id" value="<?php echo $rb->getId(); ?>">
                                        <button type="submit" class="text-xs text-rose-500 hover:text-rose-400 transition py-0.5 px-1.5 rounded hover:bg-rose-500/10" title="Delete Rulebook">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php require_once __DIR__ . '/views/rulebooks-glossary-tab.php'; ?>
    <?php endif; ?>
</div>

<!-- Custom Confirmation Modal -->
<div id="custom-confirm-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 flex items-center justify-center hidden">
    <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl w-full max-w-sm space-y-4 shadow-2xl">
        <div class="flex items-center space-x-3">
            <div class="p-2 rounded-lg bg-rose-500/10 text-rose-500">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
            <h3 class="text-base font-bold text-slate-200">Confirm Action</h3>
        </div>
        <p id="custom-confirm-message" class="text-xs text-slate-400">Are you sure you want to proceed?</p>
        <div class="flex justify-end space-x-2 pt-2">
            <button id="btn-confirm-cancel" class="px-4 py-2 bg-slate-850 hover:bg-slate-800 text-slate-300 text-xs font-semibold rounded-xl transition duration-200">Cancel</button>
            <button id="btn-confirm-ok" class="px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold rounded-xl transition duration-200">Confirm</button>
        </div>
    </div>
</div>

<script src="js/rulebooks-page.js?v=<?php echo filemtime(__DIR__ . '/js/rulebooks-page.js'); ?>"></script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>

