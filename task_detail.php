<?php
declare(strict_types=1);

$container = require_once __DIR__ . '/src/bootstrap.php';

use App\Infrastructure\Security\SecurityHelper;
use App\Application\Services\TaskService;
use App\Application\Services\ProjectService;
use App\Application\Services\UserService;
use App\Application\Exceptions\ValidationException;

SecurityHelper::requireLogin();

$taskService = $container->get(TaskService::class);
$projectService = $container->get(ProjectService::class);
$userService = $container->get(UserService::class);

$currentUserId = SecurityHelper::getCurrentUserId() ?? 0;
$taskId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$task = $taskService->getTaskById($taskId, $currentUserId);

if (!$task) {
    http_response_code(404);
    require_once __DIR__ . '/templates/header.php';
    renderPageHeader('404 - Task Not Found', '', '', 'index.php');
    ?>
    <div class="page-container">
        <div class="max-w-lg mx-auto my-12 p-8 bg-slate-900 border border-slate-800 rounded-2xl text-center space-y-4">
            <h2 class="text-xl font-bold text-white">Task Not Found</h2>
            <p class="text-slate-400 text-xs">Task #<?php echo SecurityHelper::escape((string)$taskId); ?> does not exist, belongs to a private project, or was deleted.</p>
            <div class="pt-2">
                <a href="index.php" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-medium text-xs rounded-lg transition inline-block">
                    &larr; Back to Task Board
                </a>
            </div>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/templates/footer.php';
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
        $action = $_POST['action'] ?? '';

        try {
            $expectedVersion = isset($_POST['version']) ? (int)$_POST['version'] : 0;

            if ($action === 'checkout') {
                $taskService->checkoutTask($taskId, $currentUserId, $expectedVersion);
                $success = "Task checked out successfully.";
            } elseif ($action === 'checkin') {
                $reason = trim($_POST['reason'] ?? '');
                $taskService->checkinTask($taskId, $currentUserId, $reason, $expectedVersion);
                $success = "Task checked in successfully.";
            } elseif ($action === 'complete') {
                $taskService->completeTask($taskId, $currentUserId, $expectedVersion);
                $success = "Task completed successfully.";
            } elseif ($action === 'add_comment') {
                $message = trim($_POST['message'] ?? '');
                $taskService->addComment($taskId, $currentUserId, $message);
                $success = "Comment added successfully.";
            } elseif ($action === 'edit_comment') {
                $commentId = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : 0;
                $message = trim($_POST['message'] ?? '');
                $taskService->editComment($commentId, $currentUserId, $message);
                $success = "Comment updated successfully.";
            } elseif ($action === 'archive') {
                $taskService->archiveTask($taskId, $currentUserId, $expectedVersion);
                $success = "Task archived successfully.";
            } elseif ($action === 'unarchive') {
                $taskService->unarchiveTask($taskId, $currentUserId, $expectedVersion);
                $success = "Task unarchived successfully.";
            }
            
            $task = $taskService->getTaskById($taskId, $currentUserId);
        } catch (ValidationException $e) {
            $error = $e->getMessage();
        } catch (\Throwable $e) {
            $error = "An error occurred: " . $e->getMessage();
        }
    }
}

$project = $projectService->getProjectById($task->getProjectId(), $currentUserId);
$creator = $userService->getUserById($task->getCreatedBy());
$assignee = $task->getAssignedTo() ? $userService->getUserById($task->getAssignedTo()) : null;

$comments = $taskService->getTaskComments($taskId, $currentUserId);
$historyLogs = $taskService->getTaskHistory($taskId, $currentUserId);

$users = $userService->getAllUsers();
$userMap = [];
foreach ($users as $u) {
    $userMap[$u->getId()] = $u->getName();
}

$statusLabel = $task->isArchived() ? 'Archived' : $task->getStatus();

require_once __DIR__ . '/templates/header.php';

// Prepare header action buttons
ob_start();
?>
<?php if ($task->isArchived()): ?>
    <form action="task_detail.php?id=<?php echo $taskId; ?>" method="POST" class="inline">
        <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
        <input type="hidden" name="action" value="unarchive">
        <input type="hidden" name="version" value="<?php echo $task->getVersion(); ?>">
        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white font-medium text-xs rounded-lg transition shadow-sm">
            Unarchive Task
        </button>
    </form>
<?php else: ?>
    <?php if ($task->getStatus() === 'To Do'): ?>
        <form action="task_detail.php?id=<?php echo $taskId; ?>" method="POST" class="inline">
            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
            <input type="hidden" name="action" value="checkout">
            <input type="hidden" name="version" value="<?php echo $task->getVersion(); ?>">
            <button type="submit" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-lg shadow-sm transition">
                Check Out Task
            </button>
        </form>
    <?php endif; ?>

    <?php if ($task->getStatus() === 'In Progress' && $task->getAssignedTo() === $currentUserId): ?>
        <button type="button" onclick="document.getElementById('checkin_modal').classList.remove('hidden')" 
            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 font-medium text-xs rounded-lg transition">
            Return / Check In
        </button>
        
        <form action="task_detail.php?id=<?php echo $taskId; ?>" method="POST" class="inline">
            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
            <input type="hidden" name="action" value="complete">
            <input type="hidden" name="version" value="<?php echo $task->getVersion(); ?>">
            <button type="submit" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs rounded-lg shadow-sm transition">
                &check; Mark as Done
            </button>
        </form>
    <?php endif; ?>

    <?php if ($task->getStatus() === 'Done'): ?>
        <form action="task_detail.php?id=<?php echo $taskId; ?>" method="POST" class="inline">
            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
            <input type="hidden" name="action" value="archive">
            <input type="hidden" name="version" value="<?php echo $task->getVersion(); ?>">
            <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-600 hover:bg-amber-500 text-white font-medium text-xs rounded-lg shadow-sm transition"
                onclick="return showCustomConfirm('Archive task #<?php echo $task->getId(); ?>?', this.form, 'Archive', 'Archive Task');">
                Archive Task
            </button>
        </form>
    <?php endif; ?>

    <a href="edit_task.php?id=<?php echo $taskId; ?>"
        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 font-medium text-xs rounded-lg transition">
        Edit Task
    </a>
<?php endif; ?>
<?php
$headerActionsHtml = ob_get_clean();

// Render sticky-top header
$headerTitle = ($task->isBug() ? 'Bug #' : 'Task #') . $task->getId() . ': ' . $task->getTitle();
renderPageHeader($headerTitle, $statusLabel, $headerActionsHtml, 'index.php');
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

    <!-- Task Specification Table (Stats Aligned) -->
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Project</th>
                    <th>Status</th>
                    <th>Type</th>
                    <th>Assignee / Active</th>
                    <th>Created By</th>
                    <th>Deadline</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <div class="flex items-center gap-2">
                            <a href="index.php?project_id=<?php echo $task->getProjectId(); ?>" class="font-semibold text-indigo-400 hover:underline">
                                <?php echo $project ? SecurityHelper::escape($project->getName()) : 'Unknown Project'; ?>
                            </a>
                            <?php if ($project && $project->isPrivate()): ?>
                                <span class="status-pill status-pill--private text-[10px]">🔒 Private</span>
                            <?php endif; ?>
                            <?php if ($project && $project->isBoardGame()): ?>
                                <span class="status-pill status-pill--boardgame text-[10px]">🎲 Board Game</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <?php if ($task->isArchived()): ?>
                            <span class="status-pill status-pill--private">Archived</span>
                        <?php elseif ($task->getStatus() === 'To Do'): ?>
                            <span class="status-pill status-pill--todo">To Do</span>
                        <?php elseif ($task->getStatus() === 'In Progress'): ?>
                            <span class="status-pill status-pill--progress">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                                <span>In Progress</span>
                            </span>
                        <?php elseif ($task->getStatus() === 'Done'): ?>
                            <span class="status-pill status-pill--done">
                                <svg class="w-3.5 h-3.5 inline mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                <span>Done</span>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($task->isBug()): ?>
                            <span class="status-pill status-pill--bug">BUG DEFECT</span>
                        <?php else: ?>
                            <span class="status-pill status-pill--todo">STANDARD TASK</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-xs">
                        <?php if ($assignee): ?>
                            <span class="font-medium text-slate-200"><?php echo SecurityHelper::escape($assignee->getName()); ?></span>
                            <?php if ($task->getCheckedOutAt()): ?>
                                <div class="text-[11px] text-slate-400 mt-0.5">since <?php echo date('M d, g:i a', strtotime($task->getCheckedOutAt())); ?></div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-slate-500 font-mono">Unassigned</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-xs text-slate-400">
                        <span class="text-slate-300"><?php echo $creator ? SecurityHelper::escape($creator->getName()) : 'Unknown'; ?></span>
                        <div class="text-[11px] text-slate-500 mt-0.5"><?php echo date('M d, Y', strtotime($task->getCreatedAt())); ?></div>
                    </td>
                    <td class="text-xs">
                        <?php if ($task->getDeadline()): ?>
                            <?php 
                            $deadlineTime = strtotime($task->getDeadline());
                            $isOverdue = $deadlineTime < strtotime(date('Y-m-d')) && $task->getStatus() !== 'Done';
                            ?>
                            <span class="<?php echo $isOverdue ? 'text-rose-400 font-bold' : 'text-slate-300'; ?>">
                                <?php echo date('M d, Y', $deadlineTime); ?>
                                <?php if ($isOverdue): ?><span class="text-[10px] ml-1">(Overdue)</span><?php endif; ?>
                            </span>
                        <?php else: ?>
                            <span class="text-slate-500 font-mono">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Task Details Block -->
    <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-5 shadow-sm space-y-2">
        <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Description & Notes</h3>
        <div class="text-slate-200 text-sm whitespace-pre-wrap leading-relaxed">
            <?php echo SecurityHelper::escape($task->getDetails() ?: 'No additional details provided.'); ?>
        </div>
    </div>

    <!-- Comments and History Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        
        <!-- Comments Board -->
        <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-5 shadow-sm space-y-4">
            <div class="flex items-center justify-between pb-2 border-b border-slate-800">
                <h3 class="text-sm font-bold text-slate-100 flex items-center gap-2">
                    <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    <span>Comments Board</span>
                </h3>
                <span class="text-xs text-slate-400"><?php echo count($comments); ?> comment<?php echo count($comments) === 1 ? '' : 's'; ?></span>
            </div>

            <form action="task_detail.php?id=<?php echo $taskId; ?>" method="POST" class="space-y-2.5">
                <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                <input type="hidden" name="action" value="add_comment">

                <textarea name="message" rows="2" required placeholder="Write a comment or status update..."
                    class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg p-3 text-slate-100 placeholder-slate-500 outline-none text-xs transition"></textarea>
                
                <div class="text-right">
                    <button type="submit" class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white font-medium text-xs rounded-lg transition">
                        Post Comment
                    </button>
                </div>
            </form>

            <div class="space-y-3 max-h-[350px] overflow-y-auto pr-1">
                <?php if (empty($comments)): ?>
                    <p class="text-slate-500 text-center py-6 text-xs">No comments yet. Start the team discussion!</p>
                <?php else: ?>
                    <?php foreach ($comments as $comment): ?>
                        <?php $isOwner = ($comment->getUserId() === $currentUserId); ?>
                        <div class="bg-slate-950/50 border border-slate-800/80 p-3 rounded-lg space-y-1.5">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-bold text-slate-300"><?php echo SecurityHelper::escape($userMap[$comment->getUserId()] ?? 'Unknown'); ?></span>
                                <div class="flex items-center gap-2">
                                    <span class="text-[11px] text-slate-500"><?php echo date('M d, g:i a', strtotime($comment->getCreatedAt())); ?></span>
                                    <?php if ($isOwner): ?>
                                        <button type="button" onclick="toggleEditComment(<?php echo $comment->getId(); ?>)" class="text-[11px] text-indigo-400 hover:underline">Edit</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <p id="comment-text-<?php echo $comment->getId(); ?>" class="text-xs text-slate-300 whitespace-pre-wrap"><?php echo SecurityHelper::escape($comment->getMessage()); ?></p>
                            
                            <?php if ($isOwner): ?>
                                <form id="comment-form-<?php echo $comment->getId(); ?>" action="task_detail.php?id=<?php echo $taskId; ?>" method="POST" class="hidden space-y-2 mt-2">
                                    <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                                    <input type="hidden" name="action" value="edit_comment">
                                    <input type="hidden" name="comment_id" value="<?php echo $comment->getId(); ?>">
                                    
                                    <textarea name="message" rows="2" required class="w-full bg-slate-950 border border-slate-800 focus:border-indigo-500 rounded-lg p-2 text-slate-100 text-xs outline-none"><?php echo SecurityHelper::escape($comment->getMessage()); ?></textarea>
                                    
                                    <div class="flex gap-2 justify-end">
                                        <button type="button" onclick="toggleEditComment(<?php echo $comment->getId(); ?>)" class="px-2 py-1 bg-slate-800 text-slate-300 text-xs rounded">Cancel</button>
                                        <button type="submit" class="px-2.5 py-1 bg-indigo-600 text-white text-xs rounded font-medium">Save</button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Task Audit History -->
        <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-5 shadow-sm space-y-4">
            <div class="flex items-center justify-between pb-2 border-b border-slate-800">
                <h3 class="text-sm font-bold text-slate-100 flex items-center gap-2">
                    <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Audit History</span>
                </h3>
                <span class="text-xs text-slate-400"><?php echo count($historyLogs); ?> event<?php echo count($historyLogs) === 1 ? '' : 's'; ?></span>
            </div>

            <div class="relative border-l border-slate-800 ml-2 pl-4 space-y-4 max-h-[380px] overflow-y-auto pr-1">
                <?php if (empty($historyLogs)): ?>
                    <p class="text-slate-500 py-2 text-xs">No history recorded.</p>
                <?php else: ?>
                    <?php foreach ($historyLogs as $log): ?>
                        <?php
                        $color = 'bg-slate-600';
                        if ($log->getAction() === 'created') $color = 'bg-slate-400';
                        elseif ($log->getAction() === 'checked_out') $color = 'bg-amber-400';
                        elseif ($log->getAction() === 'checked_in') $color = 'bg-indigo-400';
                        elseif ($log->getAction() === 'completed') $color = 'bg-emerald-400';
                        ?>
                        <div class="relative text-xs">
                            <span class="absolute -left-[21px] top-1 w-2 h-2 rounded-full <?php echo $color; ?>"></span>
                            <div class="space-y-0.5">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-semibold text-slate-200"><?php echo SecurityHelper::escape(ucwords(str_replace('_', ' ', $log->getAction()))); ?></span>
                                    <span class="text-[11px] text-slate-500"><?php echo date('M d, g:i a', strtotime($log->getCreatedAt())); ?></span>
                                </div>
                                <p class="text-slate-400">
                                    By <strong class="text-slate-300"><?php echo SecurityHelper::escape($userMap[$log->getUserId()] ?? 'Unknown'); ?></strong>
                                    <?php if ($log->getNote()): ?>
                                        &ndash; <span class="italic text-slate-300">"<?php echo SecurityHelper::escape($log->getNote()); ?>"</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>

</div>

<!-- Check In Modal -->
<div id="checkin_modal" class="hidden fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
    <div class="relative bg-slate-900 border border-slate-800 max-w-lg w-full rounded-2xl p-6 shadow-2xl space-y-4">
        <h3 class="text-base font-bold text-slate-200">Return Task (Check In)</h3>
        <p class="text-xs text-slate-400">Please provide a reason or note explaining what progress was made or why the task is being returned.</p>
        
        <form action="task_detail.php?id=<?php echo $taskId; ?>" method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
            <input type="hidden" name="action" value="checkin">
            <input type="hidden" name="version" value="<?php echo $task->getVersion(); ?>">

            <div>
                <label for="reason" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Mandatory Note / Reason</label>
                <textarea id="reason" name="reason" rows="3" required placeholder="Describe progress or blockers..."
                    class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 rounded-lg p-2.5 text-slate-100 text-xs outline-none"></textarea>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-slate-800">
                <button type="button" onclick="document.getElementById('checkin_modal').classList.add('hidden')"
                    class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs rounded-lg transition">
                    Cancel
                </button>
                <button type="submit"
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs rounded-lg font-medium transition">
                    Submit & Return
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleEditComment(id) {
    const textEl = document.getElementById('comment-text-' + id);
    const formEl = document.getElementById('comment-form-' + id);
    if (textEl && formEl) {
        textEl.classList.toggle('hidden');
        formEl.classList.toggle('hidden');
    }
}
</script>

<?php
require_once __DIR__ . '/templates/footer.php';
?>
