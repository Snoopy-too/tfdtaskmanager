<?php
declare(strict_types=1);

$container = require_once __DIR__ . '/src/bootstrap.php';

use App\Infrastructure\Security\SecurityHelper;
use App\Application\Services\MeetingService;
use App\Application\Services\UserService;
use App\Application\Exceptions\ValidationException;

SecurityHelper::requireLogin();

$meetingService = $container->get(MeetingService::class);
$userService = $container->get(UserService::class);
$currentUserId = SecurityHelper::getCurrentUserId();

$meetingId = isset($_GET['id']) ? (int)$_GET['id'] : null;
if (!$meetingId) {
    header('Location: meetings.php');
    exit();
}

$meeting = $meetingService->getMeetingById($meetingId);
if (!$meeting) {
    header('Location: meetings.php');
    exit();
}

$error = '';
$success = '';
$isEditing = isset($_GET['edit']) && $_GET['edit'] === '1';
$csrfToken = SecurityHelper::generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!SecurityHelper::verifyCsrfToken($submittedToken)) {
        $error = 'Security check failed. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'edit_meeting') {
            $title = trim($_POST['title'] ?? '');
            $scheduledDate = $_POST['scheduled_date'] ?? '';
            try {
                $meeting = $meetingService->updateMeeting($meetingId, $title, $scheduledDate);
                $success = 'Meeting details successfully updated.';
                $isEditing = false;
            } catch (ValidationException $e) {
                $error = $e->getMessage();
            }
        } elseif ($action === 'add_topic') {
            $topicTitle = trim($_POST['topic_title'] ?? '');
            try {
                if ($currentUserId === null) {
                    throw new ValidationException("User not authenticated.");
                }

                $meetingService->addTopic($meetingId, $currentUserId, $topicTitle);
                $success = 'Topic successfully added to the agenda.';
            } catch (ValidationException $e) {
                $error = $e->getMessage();
            }
        } elseif ($action === 'edit_topic') {
            $topicId = isset($_POST['topic_id']) ? (int)$_POST['topic_id'] : null;
            $topicTitle = trim($_POST['topic_title'] ?? '');
            try {
                if ($currentUserId === null) {
                    throw new ValidationException("User not authenticated.");
                }
                if (!$topicId) {
                    throw new ValidationException("Topic ID is required.");
                }
                $meetingService->updateTopic($topicId, $currentUserId, $topicTitle);
                $success = 'Topic successfully updated.';
            } catch (ValidationException $e) {
                $error = $e->getMessage();
            }
        } elseif ($action === 'finish_meeting') {
            $notes = $_POST['notes'] ?? null;
            try {
                $meeting = $meetingService->finishMeeting($meetingId, $notes);
                $success = 'Meeting marked as finished.';
            } catch (ValidationException $e) {
                $error = $e->getMessage();
            }
        } elseif ($action === 'reopen_meeting') {
            try {
                $meeting = $meetingService->reopenMeeting($meetingId);
                $success = 'Meeting re-opened.';
            } catch (ValidationException $e) {
                $error = $e->getMessage();
            }
        } elseif ($action === 'save_notes') {
            $notes = $_POST['notes'] ?? '';
            try {
                $meeting = $meetingService->updateMeeting($meetingId, $meeting->getTitle(), $meeting->getScheduledDate(), $meeting->getStatus(), $notes);
                $success = 'Meeting notes saved successfully.';
            } catch (ValidationException $e) {
                $error = $e->getMessage();
            }
        }
    }
}

// Fetch active topics for the meeting
$topics = $meetingService->getTopicsForMeeting($meetingId);

// Load all users to map names
$users = $userService->getAllUsers();
$userMap = [];
foreach ($users as $u) {
    $userMap[$u->getId()] = $u->getName();
}

$statusLabel = $meeting->isFinished() ? 'Finished' : ($meeting->getScheduledDate() ? 'Scheduled' : 'Pending');

require_once __DIR__ . '/templates/header.php';

// Prepare header actions
ob_start();
?>
<button type="button" onclick="toggleCollapsible('edit-meeting-panel')"
    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 transition">
    Edit Details
</button>

<button type="button" onclick="toggleCollapsible('add-topic-panel')"
    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-xs font-semibold bg-indigo-600 hover:bg-indigo-500 text-white shadow-sm transition">
    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
    <span>Add Topic</span>
</button>

<?php if (!$meeting->isFinished()): ?>
    <button type="button" onclick="document.getElementById('finish_meeting_modal').classList.remove('hidden')"
        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-xs font-semibold bg-emerald-600 hover:bg-emerald-500 text-white shadow-sm transition">
        <span>&check; Finish</span>
    </button>
<?php else: ?>
    <form action="meeting_detail.php?id=<?php echo $meetingId; ?>" method="POST" class="inline">
        <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
        <input type="hidden" name="action" value="reopen_meeting">
        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 transition">
            Reopen
        </button>
    </form>
<?php endif; ?>
<?php
$headerActionsHtml = ob_get_clean();

// Render single-row sticky-top header with back button to meetings list
renderPageHeader($meeting->getTitle(), $statusLabel, $headerActionsHtml, 'meetings.php');
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

    <!-- Collapsible Edit Meeting Form Panel (Hidden until needed) -->
    <div id="edit-meeting-panel" class="form-panel-collapsible <?php echo $isEditing ? 'is-open' : ''; ?>">
        <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-800">
            <div>
                <h2 class="text-base font-bold text-slate-100">Edit Meeting Details</h2>
                <p class="text-xs text-slate-400">Update title and scheduled date for this sync.</p>
            </div>
            <button type="button" onclick="toggleCollapsible('edit-meeting-panel')" class="text-slate-400 hover:text-slate-200 text-sm font-semibold p-1">
                &times; Close
            </button>
        </div>

        <form action="meeting_detail.php?id=<?php echo $meetingId; ?>" method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
            <input type="hidden" name="action" value="edit_meeting">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="edit_title" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Meeting Title *</label>
                    <input type="text" id="edit_title" name="title" required
                        value="<?php echo SecurityHelper::escape($meeting->getTitle()); ?>"
                        class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 text-sm outline-none transition">
                </div>

                <div>
                    <label for="edit_scheduled_date" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Scheduled Date</label>
                    <input type="date" id="edit_scheduled_date" name="scheduled_date"
                        value="<?php echo $meeting->getScheduledDate() ? SecurityHelper::escape($meeting->getScheduledDate()) : ''; ?>"
                        class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 text-sm outline-none transition">
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800/80">
                <button type="button" onclick="toggleCollapsible('edit-meeting-panel')"
                    class="px-4 py-2 rounded-lg text-xs font-medium text-slate-400 hover:text-slate-200 transition">
                    Cancel
                </button>
                <button type="submit"
                    class="px-5 py-2 rounded-lg text-xs font-semibold bg-indigo-600 hover:bg-indigo-500 text-white shadow-sm transition">
                    Save Changes
                </button>
            </div>
        </form>
    </div>

    <!-- Collapsible Add Topic Form Panel (Hidden until needed) -->
    <div id="add-topic-panel" class="form-panel-collapsible">
        <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-800">
            <div>
                <h2 class="text-base font-bold text-slate-100">Add Agenda Topic</h2>
                <p class="text-xs text-slate-400">Suggest a topic, design question, or blocker for this meeting.</p>
            </div>
            <button type="button" onclick="toggleCollapsible('add-topic-panel')" class="text-slate-400 hover:text-slate-200 text-sm font-semibold p-1">
                &times; Close
            </button>
        </div>

        <form action="meeting_detail.php?id=<?php echo $meetingId; ?>" method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
            <input type="hidden" name="action" value="add_topic">

            <div>
                <label for="topic_title_input" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Topic Description *</label>
                <textarea id="topic_title_input" name="topic_title" rows="3" required
                    placeholder="Describe the agenda item, feedback requested, or prototype issue to review..."
                    class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 text-sm outline-none transition"></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800/80">
                <button type="button" onclick="toggleCollapsible('add-topic-panel')"
                    class="px-4 py-2 rounded-lg text-xs font-medium text-slate-400 hover:text-slate-200 transition">
                    Cancel
                </button>
                <button type="submit"
                    class="px-5 py-2 rounded-lg text-xs font-semibold bg-indigo-600 hover:bg-indigo-500 text-white shadow-sm transition">
                    Add Topic
                </button>
            </div>
        </form>
    </div>

    <!-- Meeting Overview Information Table (Stats Aligned) -->
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Meeting Status</th>
                    <th>Scheduled Date</th>
                    <th>Organized By</th>
                    <th>Created</th>
                    <th>Agenda Items</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <?php if ($meeting->isFinished()): ?>
                            <span class="stamp-finished">Finished</span>
                        <?php elseif ($meeting->getScheduledDate() === null): ?>
                            <span class="status-pill status-pill--private">Pending</span>
                        <?php else: ?>
                            <span class="status-pill status-pill--public">Scheduled</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-xs font-medium text-slate-200">
                        <?php echo $meeting->getScheduledDate() ? date('F d, Y', strtotime($meeting->getScheduledDate())) : '<span class="text-amber-400">TBD (Pending date)</span>'; ?>
                    </td>
                    <td class="text-xs text-slate-300">
                        <?php echo SecurityHelper::escape($userMap[$meeting->getCreatedBy()] ?? 'Unknown User'); ?>
                    </td>
                    <td class="text-xs text-slate-400">
                        <?php echo date('M d, Y', strtotime($meeting->getCreatedAt())); ?>
                    </td>
                    <td class="text-xs font-semibold text-indigo-400">
                        <?php echo count($topics); ?> topic<?php echo count($topics) === 1 ? '' : 's'; ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Agenda Topics Table (Replaces Cards) -->
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <h3 class="text-base font-bold text-slate-100 flex items-center gap-2">
                <span>Meeting Agenda</span>
                <span class="text-xs px-2 py-0.5 rounded-full bg-slate-800 text-slate-400 font-normal">
                    <?php echo count($topics); ?>
                </span>
            </h3>
            <button type="button" onclick="toggleCollapsible('add-topic-panel')"
                class="text-xs text-indigo-400 hover:text-indigo-300 font-semibold transition">
                + Add Topic
            </button>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Agenda Topic Description</th>
                        <th>Added By</th>
                        <th>Added Date</th>
                        <th class="text-right" style="width: 100px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($topics)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-8 text-slate-400 text-xs">
                                No topics added to the agenda yet. Click "+ Add Topic" to propose an item.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($topics as $index => $topic): ?>
                            <tr>
                                <td class="font-mono text-xs text-slate-400" data-sort-value="<?php echo $index + 1; ?>">
                                    <?php echo $index + 1; ?>
                                </td>
                                <td data-sort-value="<?php echo SecurityHelper::escape($topic->getTitle()); ?>">
                                    <?php if (isset($_GET['edit_topic']) && (int)$_GET['edit_topic'] === $topic->getId() && $topic->getUserId() === $currentUserId): ?>
                                        <form action="meeting_detail.php?id=<?php echo $meetingId; ?>" method="POST" class="space-y-2 py-1">
                                            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                                            <input type="hidden" name="action" value="edit_topic">
                                            <input type="hidden" name="topic_id" value="<?php echo $topic->getId(); ?>">
                                            <textarea name="topic_title" rows="2" required class="w-full bg-slate-950/80 border border-indigo-500 rounded-lg p-2 text-slate-100 text-xs outline-none"><?php echo SecurityHelper::escape($topic->getTitle()); ?></textarea>
                                            <div class="flex items-center gap-2">
                                                <button type="submit" class="px-2.5 py-1 bg-indigo-600 hover:bg-indigo-500 text-white font-medium text-xs rounded transition">Save</button>
                                                <a href="meeting_detail.php?id=<?php echo $meetingId; ?>" class="text-slate-400 hover:text-slate-200 text-xs">Cancel</a>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <div class="font-medium text-slate-200 whitespace-pre-wrap text-sm">
                                            <?php echo SecurityHelper::escape($topic->getTitle()); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-xs text-slate-400 whitespace-nowrap" data-sort-value="<?php echo SecurityHelper::escape($userMap[$topic->getUserId()] ?? ''); ?>">
                                    <?php echo SecurityHelper::escape($userMap[$topic->getUserId()] ?? 'Unknown User'); ?>
                                </td>
                                <td class="text-xs text-slate-400 whitespace-nowrap" data-sort-value="<?php echo strtotime($topic->getCreatedAt()); ?>">
                                    <?php echo date('M d, Y', strtotime($topic->getCreatedAt())); ?>
                                </td>
                                <td class="text-right text-xs">
                                    <?php if ($topic->getUserId() === $currentUserId): ?>
                                        <a href="meeting_detail.php?id=<?php echo $meetingId; ?>&edit_topic=<?php echo $topic->getId(); ?>"
                                           class="text-indigo-400 hover:text-indigo-300 font-medium transition">
                                            Edit
                                        </a>
                                    <?php else: ?>
                                        <span class="text-slate-600">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Meeting Notes Section -->
    <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-5 shadow-sm space-y-3">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-200 flex items-center gap-2">
                <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                <span>Meeting Notes & Decisions</span>
            </h3>
            <?php if ($meeting->getNotes()): ?>
                <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">Recorded</span>
            <?php endif; ?>
        </div>

        <form action="meeting_detail.php?id=<?php echo $meetingId; ?>" method="POST" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
            <input type="hidden" name="action" value="save_notes">
            
            <textarea name="notes" rows="4"
                placeholder="Record takeaways, agreed action items, prototyping conclusions..."
                class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg p-3 text-slate-200 text-sm outline-none transition resize-y"><?php echo SecurityHelper::escape($meeting->getNotes() ?? ''); ?></textarea>
            
            <div class="flex justify-end">
                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-medium text-xs rounded-lg shadow transition">
                    Save Notes
                </button>
            </div>
        </form>
    </div>

</div>

<!-- Finish Meeting Modal -->
<div id="finish_meeting_modal" class="hidden fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
    <div class="relative bg-slate-900 border border-slate-800 max-w-lg w-full rounded-2xl p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                <span>Mark Meeting as Finished</span>
            </h3>
            <button type="button" onclick="document.getElementById('finish_meeting_modal').classList.add('hidden')" class="text-slate-400 hover:text-white">&times;</button>
        </div>
        
        <p class="text-xs text-slate-400">Save your summary notes or action items before marking this meeting as complete.</p>
        
        <form action="meeting_detail.php?id=<?php echo $meetingId; ?>" method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
            <input type="hidden" name="action" value="finish_meeting">

            <div>
                <label for="finish_notes" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Meeting Notes / Minutes (Optional)</label>
                <textarea id="finish_notes" name="notes" rows="4"
                    placeholder="Enter summary notes, decisions made, next steps..."
                    class="w-full bg-slate-950 border border-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 rounded-xl p-3 text-slate-200 text-sm outline-none resize-y"><?php echo SecurityHelper::escape($meeting->getNotes() ?? ''); ?></textarea>
            </div>

            <div class="flex justify-end space-x-3 pt-2 border-t border-slate-800">
                <button type="button" onclick="document.getElementById('finish_meeting_modal').classList.add('hidden')"
                    class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-medium text-xs rounded-lg transition">
                    Cancel
                </button>
                <button type="submit"
                    class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs rounded-lg shadow transition">
                    Finish Meeting
                </button>
            </div>
        </form>
    </div>
</div>

<?php
require_once __DIR__ . '/templates/footer.php';
?>
