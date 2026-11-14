<?php
declare(strict_types=1);

/**
 * Sandbox Manager View (spec §2): list, create, open, and delete a project's playtest sandboxes.
 */

$container = require_once __DIR__ . '/../src/bootstrap.php';

use App\Application\Exceptions\ResourceLockedException;
use App\Application\Exceptions\ValidationException;
use App\Application\Services\BgSandboxService;
use App\Application\Services\ProjectService;
use App\Domain\Entities\BgSandbox;
use App\Infrastructure\Security\SecurityHelper;

SecurityHelper::requireLogin();

$projectService = $container->get(ProjectService::class);
$sandboxService = $container->get(BgSandboxService::class);

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$csrfToken = SecurityHelper::generateCsrfToken();
$error = '';
$success = '';

// --- Active project resolution (same convention as the other studio pages) ---
$projects = $projectService->getAllProjects($currentUserId);
$activeProjectId = null;
if (isset($_GET['project_id'])) {
    if ($_GET['project_id'] !== '') {
        $activeProjectId = (int)$_GET['project_id'];
        $_SESSION['last_project_id'] = $activeProjectId;
    } else {
        unset($_SESSION['last_project_id']);
    }
} elseif (isset($_SESSION['last_project_id']) && $projectService->getProjectById((int)$_SESSION['last_project_id'], $currentUserId)) {
    header('Location: sandboxes.php?project_id=' . (int)$_SESSION['last_project_id']);
    exit;
}

$activeProject = $activeProjectId ? $projectService->getProjectById($activeProjectId, $currentUserId) : null;
if ($activeProject === null) {
    $activeProjectId = null;
}

// --- Actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!SecurityHelper::verifyCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
        $error = 'Security check failed. Please try again.';
    } elseif ($activeProjectId === null) {
        $error = 'No active project selected.';
    } else {
        try {
            $action = (string)($_POST['action'] ?? '');
            if ($action === 'create_sandbox') {
                $sandbox = $sandboxService->createSandbox(
                    $activeProjectId,
                    (string)($_POST['sandbox_name'] ?? ''),
                    (int)($_POST['default_player_count'] ?? 0),
                    $currentUserId
                );
                header('Location: sandbox.php?id=' . (int)$sandbox->id);
                exit;
            }
            if ($action === 'delete_sandbox') {
                $sandbox = $sandboxService->getSandboxById((int)($_POST['sandbox_id'] ?? 0));
                if ($sandbox === null || $sandbox->projectId !== $activeProjectId) {
                    throw new ValidationException('Sandbox not found.');
                }
                $sandboxService->deleteSandbox($sandbox, $currentUserId);
                $success = 'Sandbox deleted.';
            }
        } catch (ValidationException | ResourceLockedException $e) {
            $error = $e->getMessage();
        } catch (\Throwable $e) {
            error_log('[BoardGameStudio] sandboxes.php error: ' . $e->getMessage());
            $error = 'An unexpected error occurred. Please try again.';
        }
    }
}

$sandboxes = $activeProjectId !== null ? $sandboxService->getSandboxesByProject($activeProjectId) : [];
$activeRooms = $activeProjectId !== null ? $sandboxService->getActiveRoomsForProject($activeProjectId) : [];
$publications = $activeProjectId !== null ? $sandboxService->getPublicationsForProject($activeProjectId) : [];
$lobbyPublicUrl = $sandboxService->getLobbyPublicUrl();

require_once __DIR__ . '/../templates/header.php';

if ($activeProject) {
    renderPageHeader($activeProject->getName(), 'Playtest Sandbox', '', 'index.php');
} else {
    renderPageHeader('Playtest Sandbox', 'Game Studio', '', 'index.php');
}
?>

<div class="page-container space-y-6">
    <?php if ($activeProject): ?>
        <?php
        $activeTab = 'sandbox';
        include __DIR__ . '/views/studio-nav.php';
        ?>
    <?php endif; ?>

    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-extrabold tracking-tight bg-gradient-to-r from-sky-400 to-indigo-400 bg-clip-text text-transparent">Playtest Sandbox</h2>
            <p class="text-slate-400 mt-1 text-sm">Stage a 2D table with your project components, then playtest solo or with your team.</p>
        </div>

        <?php if (!empty($projects)): ?>
            <div class="flex items-center space-x-2 bg-slate-900 border border-slate-800 p-2 rounded-xl">
                <label for="project_select" class="text-xs font-semibold text-slate-400 uppercase tracking-wider pl-2">Project:</label>
                <form method="GET" class="m-0">
                    <select id="project_select" name="project_id" onchange="this.form.submit()" class="bg-slate-950 border-0 text-slate-100 text-sm rounded-lg focus:ring-2 focus:ring-sky-500 py-1.5 pl-3 pr-8 font-medium cursor-pointer">
                        <option value="" <?php echo $activeProjectId === null ? 'selected' : ''; ?>>None (Choose Project)</option>
                        <?php foreach ($projects as $proj): ?>
                            <option value="<?php echo (int)$proj->getId(); ?>" <?php echo $proj->getId() === $activeProjectId ? 'selected' : ''; ?>>
                                <?php echo SecurityHelper::escape($proj->getName() . ($proj->isPrivate() ? ' (🔒 Private)' : '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($error !== ''): ?>
        <div class="p-4 bg-rose-500/10 border border-rose-500/20 text-rose-400 rounded-xl text-sm"><?php echo SecurityHelper::escape($error); ?></div>
    <?php endif; ?>
    <?php if ($success !== ''): ?>
        <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-xl text-sm"><?php echo SecurityHelper::escape($success); ?></div>
    <?php endif; ?>

    <?php if ($activeProjectId === null): ?>
        <div class="p-12 text-center bg-slate-900/40 border border-slate-800/80 rounded-2xl max-w-2xl mx-auto space-y-3">
            <h2 class="text-xl font-bold text-slate-200">Select a project</h2>
            <p class="text-slate-400 text-sm">Choose a project from the menu at the top right to manage its playtest sandboxes.</p>
        </div>
    <?php else: ?>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-bold text-slate-200">Sandboxes (<?php echo count($sandboxes); ?>)</h2>
            <div class="flex flex-wrap items-center gap-2">
                <form method="GET" action="sandbox.php" class="flex items-center gap-1.5 m-0">
                    <input type="text" name="room" maxlength="16" placeholder="Room code (e.g. 99FD3943)" required class="bg-slate-900 border border-slate-800 text-xs text-slate-100 rounded-xl px-3 py-2 w-44 uppercase font-mono focus:ring-emerald-500 focus:border-emerald-500">
                    <button type="submit" class="px-3 py-2 bg-emerald-600/90 hover:bg-emerald-500 text-white text-xs font-semibold rounded-xl transition">Join Code</button>
                </form>
                <button type="button" id="btn-open-create-sandbox" class="inline-flex items-center gap-2 px-4 py-2 bg-sky-600 hover:bg-sky-500 text-white text-sm font-semibold rounded-xl transition">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                    New Sandbox
                </button>
            </div>
        </div>

        <?php if (empty($sandboxes)): ?>
            <div class="p-12 text-center bg-slate-900/30 border border-dashed border-slate-800 rounded-2xl">
                <h3 class="text-sm font-semibold text-slate-300">No sandboxes yet</h3>
                <p class="text-xs text-slate-500 mt-1">Click “New Sandbox” to stage your first playtest table.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                <?php foreach ($sandboxes as $sbx): /** @var BgSandbox $sbx */ ?>
                    <?php
                    $lockedByOther = $sbx->isLockedByOther($currentUserId);
                    $activeRoom = $activeRooms[(int)$sbx->id] ?? null;
                    $activeMemberCount = $activeRoom !== null ? count($sandboxService->prunePresence($activeRoom['presenceState'])['members']) : 0;
                    $publication = $publications[(int)$sbx->id] ?? null;
                    ?>
                    <div data-sandbox-card="<?php echo (int)$sbx->id; ?>" data-has-publication="<?php echo $publication !== null ? '1' : '0'; ?>" class="bg-slate-900 border <?php echo $activeRoom !== null ? 'border-emerald-500/40' : ($publication !== null ? 'border-amber-500/40' : 'border-slate-800/80'); ?> p-5 rounded-2xl flex flex-col justify-between hover:border-slate-700/80 transition">
                        <div class="space-y-1">
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="font-bold text-slate-200 truncate"><?php echo SecurityHelper::escape($sbx->name); ?></h3>
                                <div class="flex items-center gap-1 flex-shrink-0">
                                    <?php if ($publication !== null): ?>
                                        <span class="text-[10px] font-bold text-amber-300 bg-amber-500/15 border border-amber-500/30 px-2 py-0.5 rounded-full" title="Published to Game Lobby (Admins Only)">
                                            Lobby v<?php echo (int)$publication['publishedVersion']; ?> · Admin
                                        </span>
                                    <?php endif; ?>
                                    <span data-sbx-room-badge class="<?php echo $activeRoom !== null ? '' : 'hidden'; ?> text-[10px] font-bold text-emerald-300 bg-emerald-500/15 border border-emerald-500/30 px-2 py-0.5 rounded-full font-mono">
                                        <?php echo $activeRoom !== null ? ('Live · ' . SecurityHelper::escape($activeRoom['roomCode'])) : ''; ?>
                                    </span>
                                    <?php if ($lockedByOther): ?>
                                        <span class="text-[10px] font-semibold text-amber-400 bg-amber-500/10 border border-amber-500/20 px-2 py-0.5 rounded-full">Being edited</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p class="text-xs text-slate-400">
                                <?php echo (int)$sbx->defaultPlayerCount; ?> player<?php echo $sbx->defaultPlayerCount === 1 ? '' : 's'; ?>
                                · <?php echo $sandboxService->countComponents($sbx); ?> components
                                <span data-sbx-room-host class="<?php echo $activeRoom !== null ? '' : 'hidden'; ?> text-emerald-400 font-semibold">
                                    <?php if ($activeRoom !== null): ?>
                                        · Hosted by <?php echo SecurityHelper::escape($activeRoom['hostUserName']); ?><?php echo $activeMemberCount > 0 ? ' (' . $activeMemberCount . ' online)' : ''; ?>
                                    <?php endif; ?>
                                </span>
                            </p>
                            <p class="text-[10px] text-slate-500">Last modified: <?php echo SecurityHelper::escape(date('M d, Y H:i', strtotime($sbx->updatedAt) ?: time())); ?></p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 mt-5 pt-3 border-t border-slate-800/60">
                            <a href="sandbox.php?id=<?php echo (int)$sbx->id; ?>" class="text-xs font-semibold text-sky-400 hover:text-sky-300 bg-sky-500/5 hover:bg-sky-500/10 px-3 py-1.5 rounded-lg border border-sky-500/20 transition">
                                Edit Setup
                            </a>
                            <?php if ($activeRoom !== null): ?>
                                <a data-sbx-room-action href="sandbox.php?id=<?php echo (int)$sbx->id; ?>&live=1" class="text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-500 px-3 py-1.5 rounded-lg transition">
                                    Join Live Room
                                </a>
                            <?php else: ?>
                                <a data-sbx-room-action href="sandbox.php?id=<?php echo (int)$sbx->id; ?>&host=1" class="text-xs font-semibold text-emerald-400 hover:text-emerald-300 bg-emerald-500/5 hover:bg-emerald-500/10 px-3 py-1.5 rounded-lg border border-emerald-500/20 transition">
                                    Host Live Session
                                </a>
                            <?php endif; ?>
                            <form method="POST" class="m-0 ml-auto" onsubmit="return showCustomConfirm('Delete this sandbox and its saved table? This cannot be undone.', this);">
                                <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                                <input type="hidden" name="action" value="delete_sandbox">
                                <input type="hidden" name="sandbox_id" value="<?php echo (int)$sbx->id; ?>">
                                <button type="submit" <?php echo $lockedByOther ? 'disabled' : ''; ?> class="text-xs text-rose-500 hover:text-rose-400 disabled:opacity-40 disabled:cursor-not-allowed transition p-1 rounded hover:bg-rose-500/10">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Create Sandbox Modal -->
        <dialog id="create-sandbox-dialog" class="bg-transparent p-0 backdrop:bg-slate-950/80 backdrop:backdrop-blur-sm">
            <form method="POST" class="bg-slate-900 border border-slate-800 p-6 rounded-2xl w-[22rem] max-w-full space-y-4 text-slate-100">
                <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                <input type="hidden" name="action" value="create_sandbox">
                <h3 class="text-lg font-bold">New Sandbox</h3>
                <div>
                    <label for="sandbox_name" class="block text-sm font-medium text-slate-300 mb-1">Sandbox name</label>
                    <input type="text" id="sandbox_name" name="sandbox_name" required maxlength="<?php echo BgSandbox::MAX_NAME_LENGTH; ?>" placeholder="e.g. Standard 4-Player Balance Test" class="w-full bg-slate-950 border border-slate-800 text-sm rounded-xl focus:ring-sky-500 focus:border-sky-500 p-2.5">
                </div>
                <div>
                    <label for="default_player_count" class="block text-sm font-medium text-slate-300 mb-1">Default player count</label>
                    <select id="default_player_count" name="default_player_count" class="w-full bg-slate-950 border border-slate-800 text-sm rounded-xl p-2.5">
                        <?php for ($n = BgSandbox::MIN_PLAYERS; $n <= BgSandbox::MAX_PLAYERS; $n++): ?>
                            <option value="<?php echo $n; ?>" <?php echo $n === 4 ? 'selected' : ''; ?>><?php echo $n; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" id="btn-cancel-create-sandbox" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm font-semibold rounded-xl transition">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-sky-600 hover:bg-sky-500 text-white text-sm font-bold rounded-xl transition">Create</button>
                </div>
            </form>
        </dialog>
        <script>
            (function () {
                const dialog = document.getElementById('create-sandbox-dialog');
                document.getElementById('btn-open-create-sandbox').addEventListener('click', () => {
                    dialog.showModal();
                    document.getElementById('sandbox_name').focus();
                });
                document.getElementById('btn-cancel-create-sandbox').addEventListener('click', () => dialog.close());

                const projectId = <?php echo (int)$activeProjectId; ?>;
                const csrfToken = <?php echo json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_AMP); ?>;
                const cards = Array.from(document.querySelectorAll('[data-sandbox-card]'));
                if (!cards.length) return;

                function applyRoomsSnapshot(rooms) {
                    cards.forEach((card) => {
                        const sbxId = card.getAttribute('data-sandbox-card');
                        const hasPub = card.getAttribute('data-has-publication') === '1';
                        const room = rooms && sbxId ? rooms[sbxId] : null;
                        const badgeEl = card.querySelector('[data-sbx-room-badge]');
                        const hostEl = card.querySelector('[data-sbx-room-host]');
                        const actionEl = card.querySelector('[data-sbx-room-action]');

                        card.classList.remove('border-emerald-500/40', 'border-amber-500/40', 'border-slate-800/80');
                        if (room) {
                            card.classList.add('border-emerald-500/40');
                            if (badgeEl) {
                                badgeEl.textContent = `Live · ${room.roomCode || ''}`;
                                badgeEl.classList.remove('hidden');
                            }
                            if (hostEl) {
                                const countText = room.memberCount > 0 ? ` (${room.memberCount} online)` : '';
                                hostEl.textContent = ` · Hosted by ${room.hostUserName || 'Host'}${countText}`;
                                hostEl.classList.remove('hidden');
                            }
                            if (actionEl) {
                                actionEl.href = `sandbox.php?id=${encodeURIComponent(sbxId)}&live=1`;
                                actionEl.textContent = 'Join Live Room';
                                actionEl.className = 'text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-500 px-3 py-1.5 rounded-lg transition';
                            }
                        } else {
                            card.classList.add(hasPub ? 'border-amber-500/40' : 'border-slate-800/80');
                            if (badgeEl) {
                                badgeEl.textContent = '';
                                badgeEl.classList.add('hidden');
                            }
                            if (hostEl) {
                                hostEl.textContent = '';
                                hostEl.classList.add('hidden');
                            }
                            if (actionEl) {
                                actionEl.href = `sandbox.php?id=${encodeURIComponent(sbxId)}&host=1`;
                                actionEl.textContent = 'Host Live Session';
                                actionEl.className = 'text-xs font-semibold text-emerald-400 hover:text-emerald-300 bg-emerald-500/5 hover:bg-emerald-500/10 px-3 py-1.5 rounded-lg border border-emerald-500/20 transition';
                            }
                        }
                    });
                }

                let refreshInFlight = false;
                async function refreshLiveRooms() {
                    if (refreshInFlight || document.hidden) return;
                    refreshInFlight = true;
                    try {
                        const fd = new FormData();
                        fd.append('action', 'get_project_sandbox_rooms');
                        fd.append('project_id', String(projectId));
                        fd.append('csrf_token', csrfToken);
                        const res = await fetch('api.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        if (data && data.success && data.rooms) {
                            applyRoomsSnapshot(data.rooms);
                        }
                    } catch (_) {
                        // Ignore transient polling failures
                    } finally {
                        refreshInFlight = false;
                    }
                }

                setInterval(refreshLiveRooms, 3000);
                window.addEventListener('pageshow', refreshLiveRooms);
                document.addEventListener('visibilitychange', () => {
                    if (!document.hidden) refreshLiveRooms();
                });
            })();
        </script>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
