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

// Projects list
$projects = $projectService->getAllProjects();
$activeProjectId = null;

if (isset($_GET['project_id'])) {
    if ($_GET['project_id'] !== '') {
        $activeProjectId = (int)$_GET['project_id'];
        $_SESSION['last_project_id'] = $activeProjectId;
    } else {
        unset($_SESSION['last_project_id']);
    }
} else {
    if (isset($_SESSION['last_project_id'])) {
        $activeProjectId = (int)$_SESSION['last_project_id'];
        header("Location: rulebooks.php?project_id=" . $activeProjectId);
        exit;
    }
}

$activeProject = null;
if ($activeProjectId) {
    $activeProject = $projectService->getProjectById($activeProjectId);
}

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
?>

<div class="space-y-8">
    <!-- Top Bar with Project Select -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2">
                <a href="index.php" class="text-xs font-semibold text-slate-400 hover:text-white transition duration-200 inline-flex items-center">
                    <svg class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                    Studio Dashboard
                </a>
            </div>
            <h1 class="text-3xl font-extrabold tracking-tight text-white bg-gradient-to-r from-amber-400 to-orange-400 bg-clip-text text-transparent mt-1">
                Rulebooks & Glossary
            </h1>
            <p class="text-slate-400 mt-1">Design beautiful rulebooks with live sync assets and project-wide terminology control.</p>
        </div>

        <?php if (!empty($projects)): ?>
            <div class="flex items-center space-x-2 bg-slate-900 border border-slate-800 p-2 rounded-xl">
                <label for="project_select" class="text-xs font-semibold text-slate-400 uppercase tracking-wider pl-2">Project:</label>
                <form method="GET" class="m-0">
                    <select id="project_select" name="project_id" onchange="this.form.submit()" class="bg-slate-950 border-0 text-slate-100 text-sm rounded-lg focus:ring-2 focus:ring-amber-505 py-1.5 pl-3 pr-8 font-medium cursor-pointer">
                        <option value="" <?php echo $activeProjectId === null ? 'selected' : ''; ?>>None (Choose Project)</option>
                        <?php foreach ($projects as $proj): ?>
                            <option value="<?php echo $proj->getId(); ?>" <?php echo $proj->getId() === $activeProjectId ? 'selected' : ''; ?>>
                                <?php echo SecurityHelper::escape($proj->getName()); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        <?php endif; ?>
    </div>

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
        <div id="rulebooks-tab" class="tab-content grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 space-y-4">
                <h2 class="text-xl font-bold text-slate-200">Rulebook Documents</h2>
                
                <?php if (empty($rulebooks)): ?>
                    <div class="p-12 text-center bg-slate-900/30 border border-dashed border-slate-800 rounded-2xl">
                        <svg class="mx-auto h-10 w-10 text-slate-650 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <h4 class="text-sm font-semibold text-slate-300">No Rulebooks Created</h4>
                        <p class="text-xs text-slate-550 mt-1">Start by creating a new rulebook document using the panel on the right.</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($rulebooks as $rb): ?>
                            <div class="bg-slate-900 border border-slate-800/80 p-5 rounded-2xl flex flex-col justify-between hover:border-slate-700/80 hover:shadow-lg transition">
                                <div class="space-y-1">
                                    <h3 class="font-bold text-slate-200 truncate"><?php echo SecurityHelper::escape($rb->getName()); ?></h3>
                                    <p class="text-xs text-slate-400">
                                        Blocks: <?php echo count($rb->getContent()); ?>
                                    </p>
                                    <p class="text-[10px] text-slate-500">
                                        Updated: <?php echo date('M d, Y H:i', strtotime($rb->getUpdatedAt())); ?>
                                    </p>
                                </div>
                                <div class="flex items-center justify-between mt-6 pt-3 border-t border-slate-800/60 gap-2">
                                    <a href="rulebook-editor.php?id=<?php echo $rb->getId(); ?>" class="text-xs font-semibold text-amber-400 hover:text-amber-300 flex items-center space-x-1.5 bg-amber-500/5 hover:bg-amber-500/10 px-3 py-1.5 rounded-lg border border-amber-500/20 transition">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                        <span>Open Editor</span>
                                    </a>

                                    <form action="" method="POST" class="m-0" onsubmit="return showCustomConfirm('Are you sure you want to delete this rulebook?', this);">
                                        <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                                        <input type="hidden" name="action" value="delete_rulebook">
                                        <input type="hidden" name="rulebook_id" value="<?php echo $rb->getId(); ?>">
                                        <button type="submit" class="text-xs text-rose-500 hover:text-rose-400 transition p-1 rounded hover:bg-rose-500/10 border border-transparent hover:border-rose-500/10">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Create Rulebook Sidebar -->
            <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl h-fit">
                <h2 class="text-xl font-bold text-slate-200 mb-4">New Rulebook</h2>
                <form action="" method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                    <input type="hidden" name="action" value="create_rulebook">

                    <div>
                        <label for="name" class="block text-sm font-medium text-slate-300 mb-1">Rulebook Title</label>
                        <input type="text" id="name" name="name" required placeholder="e.g. Core Rules" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-sm rounded-xl focus:ring-amber-500 focus:border-amber-500 p-2.5">
                    </div>

                    <button type="submit" class="w-full bg-amber-600 hover:bg-amber-500 text-white font-medium rounded-xl py-2.5 px-4 transition duration-200">
                        Create Rulebook
                    </button>
                </form>
            </div>
        </div>

        <!-- Glossary Tab -->
        <div id="glossary-tab" class="tab-content hidden grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Glossary List -->
            <div class="lg:col-span-2 space-y-4">
                <h2 class="text-xl font-bold text-slate-200">Centralized Glossary</h2>
                <p class="text-xs text-slate-400">Define terminology keys (e.g. <code>discard_pile</code>) that can be inserted anywhere in rulebooks as <code>[[discard_pile]]</code> or bound to card components. Renaming here updates everywhere instantly.</p>

                <?php if (empty($glossary)): ?>
                    <div class="p-12 text-center bg-slate-900/30 border border-dashed border-slate-800 rounded-2xl flex flex-col items-center justify-center space-y-4">
                        <svg class="mx-auto h-12 w-12 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                        <div>
                            <h4 class="text-sm font-semibold text-slate-300">No Glossary Terms Defined</h4>
                            <p class="text-xs text-slate-400 mt-1">Start by adding a term using the manual form or import a glossary CSV file.</p>
                        </div>
                        <div class="pt-2">
                            <button onclick="switchSidebarTab('csv')" class="inline-flex items-center px-4 py-2 border border-slate-700 hover:border-amber-500/50 hover:bg-slate-800 text-xs font-semibold rounded-xl text-slate-350 hover:text-white transition duration-200">
                                <span>Import CSV Glossary</span>
                                <svg class="h-3.5 w-3.5 ml-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($glossary as $term): ?>
                            <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl space-y-2 flex flex-col justify-between md:flex-row md:items-center md:space-y-0 gap-4">
                                <div class="space-y-1">
                                    <div class="flex items-center space-x-2">
                                        <span class="font-bold text-slate-200"><?php echo SecurityHelper::escape($term->getTermName()); ?></span>
                                        <code class="text-[11px] bg-slate-950 text-amber-400 px-2 py-0.5 rounded font-mono">[[<?php echo SecurityHelper::escape($term->getTermKey()); ?>]]</code>
                                    </div>
                                    <p class="text-xs text-slate-400 max-w-xl"><?php echo SecurityHelper::escape($term->getTermDescription()); ?></p>
                                </div>
                                <div class="flex items-center space-x-3 self-end md:self-center">
                                    <button onclick="editGlossary(<?php echo htmlspecialchars(json_encode([
                                        'id' => $term->getId(),
                                        'term_key' => $term->getTermKey(),
                                        'term_name' => $term->getTermName(),
                                        'term_description' => $term->getTermDescription()
                                    ], JSON_HEX_APOS | JSON_HEX_QUOT)); ?>)" class="text-xs text-amber-400 hover:text-amber-300 transition">
                                        Edit
                                    </button>
                                    <form action="" method="POST" class="m-0" onsubmit="return showCustomConfirm('Are you sure you want to delete this glossary term?', this);">
                                        <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                                        <input type="hidden" name="action" value="delete_glossary_term">
                                        <input type="hidden" name="term_id" value="<?php echo $term->getId(); ?>">
                                        <button type="submit" class="text-xs text-rose-500 hover:text-rose-400 transition p-1 rounded">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Combined Create/Edit/Import Glossary Form Sidebar -->
            <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl h-fit space-y-6">
                <!-- Sidebar Tabs -->
                <div class="flex border-b border-slate-850 text-xs font-semibold">
                    <button id="btn-sidebar-manual" onclick="switchSidebarTab('manual')" class="flex-grow pb-2 border-b-2 border-amber-500 text-white transition">
                        Manual Add
                    </button>
                    <button id="btn-sidebar-csv" onclick="switchSidebarTab('csv')" class="flex-grow pb-2 border-b-2 border-transparent text-slate-400 hover:text-white transition">
                        CSV Import
                    </button>
                </div>

                <!-- Manual Add Form -->
                <div id="sidebar-manual-form" class="space-y-4">
                    <h2 id="glossary-form-title" class="text-base font-bold text-slate-200">Add Glossary Term</h2>
                    <form action="" method="POST" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                        <input type="hidden" name="action" value="save_glossary_term">
                        <input type="hidden" name="term_id" id="form_term_id" value="">

                        <div>
                            <label for="term_key" class="block text-xs font-medium text-slate-400 mb-1">Key Shorthand (e.g. banish_zone)</label>
                            <input type="text" id="form_term_key" name="term_key" required placeholder="lowercase, no spaces" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-xl focus:ring-amber-500 focus:border-amber-500 p-2.5">
                        </div>

                        <div>
                            <label for="term_name" class="block text-xs font-medium text-slate-400 mb-1">Term Name (Display Name)</label>
                            <input type="text" id="form_term_name" name="term_name" required placeholder="e.g. Banish Zone" class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-xl focus:ring-amber-500 focus:border-amber-500 p-2.5">
                        </div>

                        <div>
                            <label for="term_description" class="block text-xs font-medium text-slate-400 mb-1">Description</label>
                            <textarea id="form_term_description" name="term_description" rows="3" required placeholder="Definition or gameplay mechanics..." class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-xl focus:ring-amber-500 focus:border-amber-500 p-2.5"></textarea>
                        </div>

                        <div class="flex space-x-2">
                            <button type="submit" class="flex-grow bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold rounded-xl py-2 px-4 transition duration-200">
                                Save Term
                            </button>
                            <button type="button" id="form_cancel_btn" onclick="resetGlossaryForm()" class="hidden bg-slate-800 hover:bg-slate-700 text-slate-350 text-xs font-semibold rounded-xl py-2 px-4 transition duration-200">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>

                <!-- CSV Import Form -->
                <div id="sidebar-csv-form" class="space-y-4 hidden">
                    <h2 class="text-base font-bold text-slate-200">Import CSV Glossary</h2>
                    <form action="api.php?action=import_glossary_csv" method="POST" enctype="multipart/form-data" class="space-y-4" id="csv-import-form" onsubmit="return handleCsvImport(event);">
                        <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                        <input type="hidden" name="project_id" value="<?php echo $activeProjectId; ?>">

                        <div>
                            <label for="csv_file" class="block text-xs font-medium text-slate-400 mb-1">Upload CSV File</label>
                            <input type="file" id="csv_file" name="csv_file" accept=".csv" class="w-full text-xs text-slate-450 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-[10px] file:font-bold file:bg-amber-600 file:text-white hover:file:bg-amber-500 cursor-pointer">
                        </div>

                        <div class="relative flex py-1 items-center">
                            <div class="flex-grow border-t border-slate-800"></div>
                            <span class="flex-shrink mx-2.5 text-slate-500 text-[10px]">Or Paste Raw CSV</span>
                            <div class="flex-grow border-t border-slate-800"></div>
                        </div>

                        <div>
                            <label for="csv_text" class="block text-xs font-medium text-slate-400 mb-1">CSV Text (Comma/Semicolon)</label>
                            <textarea id="csv_text" name="csv_text" rows="4" placeholder="key,name,description&#10;banish,Banish Zone,Exile a card.&#10;exhaust,Exhaust,Tap a card." class="w-full bg-slate-950 border border-slate-800 text-slate-100 text-xs rounded-xl focus:ring-amber-500 p-2.5 font-mono"></textarea>
                        </div>

                        <button type="submit" class="w-full bg-amber-600 hover:bg-amber-500 text-white font-bold py-2 px-4 rounded-xl transition duration-200 text-xs uppercase tracking-wider">
                            Start CSV Import
                        </button>
                    </form>
                </div>
            </div>
        </div>
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

<script>
    let activeConfirmForm = null;

    document.addEventListener('DOMContentLoaded', () => {
        const btnOk = document.getElementById('btn-confirm-ok');
        const btnCancel = document.getElementById('btn-confirm-cancel');
        const modal = document.getElementById('custom-confirm-modal');

        if (btnOk && btnCancel && modal) {
            btnOk.addEventListener('click', () => {
                modal.classList.add('hidden');
                if (activeConfirmForm) {
                    activeConfirmForm.submit();
                }
            });
            btnCancel.addEventListener('click', () => {
                modal.classList.add('hidden');
                activeConfirmForm = null;
            });
        }
    });

    function switchTab(tabId, btn) {
        document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
        document.getElementById(tabId).classList.remove('hidden');
        
        document.querySelectorAll('.tab-btn').forEach(b => {
            b.classList.remove('border-amber-500', 'text-white');
            b.classList.add('border-transparent', 'text-slate-400');
        });
        btn.classList.remove('border-transparent', 'text-slate-400');
        btn.classList.add('border-amber-500', 'text-white');
    }

    function switchSidebarTab(tab) {
        const manualForm = document.getElementById('sidebar-manual-form');
        const csvForm = document.getElementById('sidebar-csv-form');
        const btnManual = document.getElementById('btn-sidebar-manual');
        const btnCsv = document.getElementById('btn-sidebar-csv');
        
        if (tab === 'csv') {
            manualForm.classList.add('hidden');
            csvForm.classList.remove('hidden');
            btnCsv.classList.remove('border-transparent', 'text-slate-400');
            btnCsv.classList.add('border-amber-500', 'text-white');
            btnManual.classList.remove('border-amber-500', 'text-white');
            btnManual.classList.add('border-transparent', 'text-slate-400');
        } else {
            manualForm.classList.remove('hidden');
            csvForm.classList.add('hidden');
            btnManual.classList.remove('border-transparent', 'text-slate-400');
            btnManual.classList.add('border-amber-500', 'text-white');
            btnCsv.classList.remove('border-amber-500', 'text-white');
            btnCsv.classList.add('border-transparent', 'text-slate-400');
        }
    }

    function editGlossary(data) {
        switchSidebarTab('manual');
        document.getElementById('glossary-form-title').textContent = 'Edit Glossary Term';
        document.getElementById('form_term_id').value = data.id;
        document.getElementById('form_term_key').value = data.term_key;
        document.getElementById('form_term_key').readOnly = true; // Key shouldn't change to prevent breaking mappings
        document.getElementById('form_term_key').classList.add('opacity-50');
        document.getElementById('form_term_name').value = data.term_name;
        document.getElementById('form_term_description').value = data.term_description;
        document.getElementById('form_cancel_btn').classList.remove('hidden');
    }

    function resetGlossaryForm() {
        document.getElementById('glossary-form-title').textContent = 'Add Glossary Term';
        document.getElementById('form_term_id').value = '';
        document.getElementById('form_term_key').value = '';
        document.getElementById('form_term_key').readOnly = false;
        document.getElementById('form_term_key').classList.remove('opacity-50');
        document.getElementById('form_term_name').value = '';
        document.getElementById('form_term_description').value = '';
        document.getElementById('form_cancel_btn').classList.add('hidden');
    }

    function showCustomConfirm(message, form) {
        activeConfirmForm = form;
        const modal = document.getElementById('custom-confirm-modal');
        const msgEl = document.getElementById('custom-confirm-message');
        if (modal && msgEl) {
            msgEl.textContent = message;
            modal.classList.remove('hidden');
            return false;
        }
        return confirm(message);
    }

    function handleCsvImport(e) {
        e.preventDefault();
        const form = document.getElementById('csv-import-form');
        const formData = new FormData(form);

        fetch(form.action, {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert(`Successfully imported/updated ${data.count} glossary terms!`);
                window.location.reload();
            } else {
                alert('Error importing CSV: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(err => {
            console.error('CSV import error:', err);
            alert('An unexpected error occurred during CSV import.');
        });
        return false;
    }
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
