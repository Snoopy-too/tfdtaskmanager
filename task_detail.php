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

$taskId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$task = $taskService->getTaskById($taskId);

if (!$task) {
    http_response_code(404);
    echo "Task not found.";
    exit();
}

$error = '';
$success = '';
$csrfToken = SecurityHelper::generateCsrfToken();
$currentUserId = SecurityHelper::getCurrentUserId() ?? 0;

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
                $reason = $_POST['reason'] ?? '';
                $taskService->checkinTask($taskId, $currentUserId, $reason, $expectedVersion);
                $success = "Task checked in successfully.";
            } elseif ($action === 'complete') {
                $taskService->completeTask($taskId, $currentUserId, $expectedVersion);
                $success = "Task completed successfully.";
            } elseif ($action === 'add_comment') {
                $message = $_POST['message'] ?? '';
                $taskService->addComment($taskId, $currentUserId, $message);
                $success = "Comment added successfully.";
            } elseif ($action === 'edit_comment') {
                $commentId = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : 0;
                $message = $_POST['message'] ?? '';
                $taskService->editComment($commentId, $currentUserId, $message);
                $success = "Comment updated successfully.";
            }
            
            $task = $taskService->getTaskById($taskId);
        } catch (ValidationException $e) {
            $error = $e->getMessage();
        }
    }
}

$project = $projectService->getProjectById($task->getProjectId());
$creator = $userService->getUserById($task->getCreatedBy());
$assignee = $task->getAssignedTo() ? $userService->getUserById($task->getAssignedTo()) : null;

$comments = $taskService->getTaskComments($taskId);
$historyLogs = $taskService->getTaskHistory($taskId);

$users = $userService->getAllUsers();
$userMap = [];
foreach ($users as $u) {
    $userMap[$u->getId()] = $u->getName();
}

require_once __DIR__ . '/templates/header.php';
?>

<div class="max-w-4xl mx-auto space-y-8">
    
    <div>
        <a href="index.php" class="text-sm text-indigo-400 hover:text-indigo-300 font-medium transition">&larr; Back to Task Board</a>
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

    <div class="bg-slate-900/50 border border-slate-800 rounded-2xl p-6 md:p-8 shadow-xl space-y-6">
        
        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4 border-b border-slate-800/80 pb-6">
            <div>
                <span class="text-xs font-semibold text-indigo-400 uppercase tracking-wider">
                    <?php echo $project ? SecurityHelper::escape($project->getName()) : 'Unknown Project'; ?>
                </span>
                <div class="flex items-center space-x-3 mt-1">
                    <h1 class="text-2xl md:text-3xl font-extrabold text-white">
                        <span class="text-slate-400 font-medium mr-1.5"><?php echo ($task->isBug() ? 'bug #' : 'task #') . $task->getId(); ?>:</span><?php echo SecurityHelper::escape($task->getTitle()); ?>
                    </h1>
                    <?php if ($task->isBug()): ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-bold bg-rose-500/20 text-rose-400 border border-rose-500/30">BUG</span>
                    <?php endif; ?>
                    <a href="edit_task.php?id=<?php echo $taskId; ?>" class="text-xs px-2 py-1 bg-slate-800 hover:bg-slate-700 text-indigo-400 hover:text-indigo-300 border border-slate-700 rounded-lg transition duration-200">
                        Edit
                    </a>
                </div>
                
                <div class="flex flex-wrap gap-x-4 gap-y-2 mt-3 text-xs text-slate-400">
                    <span>Created by: <strong class="text-slate-300"><?php echo $creator ? SecurityHelper::escape($creator->getName()) : 'Unknown'; ?></strong></span>
                    <span>Created: <strong class="text-slate-300"><?php echo date('M d, Y', strtotime($task->getCreatedAt())); ?></strong></span>
                    <?php if ($task->getDeadline()): ?>
                        <span>Deadline: <strong class="text-slate-300"><?php echo date('M d, Y', strtotime($task->getDeadline())); ?></strong></span>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <?php if ($task->getStatus() === 'To Do'): ?>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-slate-800 text-slate-300 border border-slate-700">To Do</span>
                <?php elseif ($task->getStatus() === 'In Progress'): ?>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">In Progress</span>
                <?php elseif ($task->getStatus() === 'Done'): ?>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Done</span>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <h2 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Details</h2>
            <div class="bg-slate-950/40 border border-slate-800/80 rounded-xl p-4 text-slate-300 text-sm whitespace-pre-wrap min-h-[100px]"><?php echo SecurityHelper::escape($task->getDetails() ?: 'No additional details provided.'); ?></div>
        </div>

        <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="text-sm">
                <?php if ($task->getStatus() === 'To Do'): ?>
                    <span class="text-slate-400">This task is open. You can check it out to start working.</span>
                <?php elseif ($task->getStatus() === 'In Progress'): ?>
                    <span class="text-slate-400">
                        Currently checked out by <strong class="text-slate-200"><?php echo $assignee ? SecurityHelper::escape($assignee->getName()) : 'Unknown'; ?></strong>
                        since <?php echo date('M d, g:i a', strtotime($task->getCheckedOutAt() ?? '')); ?>
                    </span>
                <?php elseif ($task->getStatus() === 'Done'): ?>
                    <span class="text-slate-400">This task has been completed. No actions are required.</span>
                <?php endif; ?>
            </div>

            <div class="w-full sm:w-auto flex flex-col sm:flex-row gap-2">
                <?php if ($task->getStatus() === 'To Do'): ?>
                    <form action="task_detail.php?id=<?php echo $taskId; ?>" method="POST" class="w-full">
                        <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                        <input type="hidden" name="action" value="checkout">
                        <input type="hidden" name="version" value="<?php echo $task->getVersion(); ?>">
                        <button type="submit" class="w-full text-center px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-medium text-sm rounded-lg transition duration-200">
                            Check Out Task
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($task->getStatus() === 'In Progress' && $task->getAssignedTo() === $currentUserId): ?>
                    <button onclick="document.getElementById('checkin_modal').classList.remove('hidden')" 
                        class="w-full sm:w-auto px-5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 font-medium text-sm rounded-lg transition duration-200">
                        Return / Check In
                    </button>
                    
                    <form action="task_detail.php?id=<?php echo $taskId; ?>" method="POST" class="w-full sm:w-auto">
                        <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                        <input type="hidden" name="action" value="complete">
                        <input type="hidden" name="version" value="<?php echo $task->getVersion(); ?>">
                        <button type="submit" class="w-full text-center px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-medium text-sm rounded-lg transition duration-200">
                            Mark as Done
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <div id="checkin_modal" class="hidden fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
        <div class="relative bg-slate-900 border border-slate-800 max-w-lg w-full rounded-2xl p-6 shadow-2xl space-y-4">
            <h3 class="text-lg font-bold text-slate-200">Return Task (Check In)</h3>
            <p class="text-xs text-slate-400">Please provide a reason or note explaining what work was done or why the task is being returned to the To Do queue.</p>
            
            <form action="task_detail.php?id=<?php echo $taskId; ?>" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                <input type="hidden" name="action" value="checkin">
                <input type="hidden" name="version" value="<?php echo $task->getVersion(); ?>">

                <div>
                    <label for="reason" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Mandatory Note / Reason</label>
                    <textarea id="reason" name="reason" rows="4" required placeholder="e.g., Drafted card stats, but need artist feedback to complete card front layouts..."
                        class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 placeholder-slate-500 transition outline-none text-sm"></textarea>
                </div>

                <div class="flex justify-end space-x-3 pt-2">
                    <button type="button" onclick="document.getElementById('checkin_modal').classList.add('hidden')"
                        class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-medium text-sm rounded-lg transition duration-200">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-medium text-sm rounded-lg transition duration-200">
                        Submit & Return
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        
        <div class="bg-slate-900/50 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-6">
            <h2 class="text-xl font-bold text-slate-200">Comments Board</h2>
            
            <form action="task_detail.php?id=<?php echo $taskId; ?>" method="POST" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                <input type="hidden" name="action" value="add_comment">

                <textarea name="message" rows="3" required placeholder="Write a comment or update for the team..."
                    class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 placeholder-slate-500 transition outline-none text-sm"></textarea>
                
                <div class="text-right">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-medium text-sm rounded-lg transition duration-200">
                        Post Comment
                    </button>
                </div>
            </form>

            <div class="space-y-4 max-h-[350px] overflow-y-auto pr-2">
                <?php if (empty($comments)): ?>
                    <p class="text-slate-500 text-center py-6 text-sm">No comments yet. Start the discussion!</p>
                <?php else: ?>
                    <?php foreach ($comments as $comment): ?>
                        <?php $isOwner = ($comment->getUserId() === $currentUserId); ?>
                        <div class="bg-slate-950/40 border border-slate-800/60 p-4 rounded-xl space-y-2">
                            <div class="flex items-center justify-between text-xs">
                                <div class="flex items-center space-x-2">
                                    <span class="font-bold text-slate-300"><?php echo SecurityHelper::escape($userMap[$comment->getUserId()] ?? 'Unknown'); ?></span>
                                    <?php if ($isOwner): ?>
                                        <button onclick="toggleEditComment(<?php echo $comment->getId(); ?>)" class="text-indigo-400 hover:text-indigo-300 font-medium transition duration-200">Edit</button>
                                    <?php endif; ?>
                                </div>
                                <span class="text-slate-500"><?php echo date('M d, Y g:i a', strtotime($comment->getCreatedAt())); ?></span>
                            </div>
                            
                            <!-- View Mode -->
                            <p id="comment-text-<?php echo $comment->getId(); ?>" class="text-sm text-slate-300 whitespace-pre-wrap"><?php echo SecurityHelper::escape($comment->getMessage()); ?></p>
                            
                            <!-- Edit Mode -->
                            <?php if ($isOwner): ?>
                                <form id="comment-form-<?php echo $comment->getId(); ?>" action="task_detail.php?id=<?php echo $taskId; ?>" method="POST" class="hidden space-y-2 mt-2">
                                    <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                                    <input type="hidden" name="action" value="edit_comment">
                                    <input type="hidden" name="comment_id" value="<?php echo $comment->getId(); ?>">
                                    
                                    <textarea name="message" rows="2" required class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 placeholder-slate-500 transition outline-none text-sm whitespace-pre-wrap"><?php echo SecurityHelper::escape($comment->getMessage()); ?></textarea>
                                    
                                    <div class="flex space-x-2 justify-end">
                                        <button type="button" onclick="toggleEditComment(<?php echo $comment->getId(); ?>)" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs rounded transition duration-200">
                                            Cancel
                                        </button>
                                        <button type="submit" class="px-2.5 py-1 bg-indigo-600 hover:bg-indigo-500 text-white text-xs rounded font-medium transition duration-200">
                                            Save
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-slate-900/50 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-6">
            <h2 class="text-xl font-bold text-slate-200">Task Audit History</h2>
            
            <div class="relative border-l border-slate-800 ml-3 pl-6 space-y-6 max-h-[450px] overflow-y-auto pr-2">
                <?php if (empty($historyLogs)): ?>
                    <p class="text-slate-500 py-2 text-sm">No history logs recorded.</p>
                <?php else: ?>
                    <?php foreach ($historyLogs as $log): ?>
                        <?php
                        $color = 'bg-slate-600';
                        if ($log->getAction() === 'created') $color = 'bg-slate-500';
                        elseif ($log->getAction() === 'checked_out') $color = 'bg-amber-500';
                        elseif ($log->getAction() === 'checked_in') $color = 'bg-slate-400 border border-slate-600';
                        elseif ($log->getAction() === 'completed') $color = 'bg-emerald-500';
                        ?>
                        <div class="relative">
                            <span class="absolute -left-[31px] top-1.5 flex items-center justify-center w-2.5 h-2.5 rounded-full <?php echo $color; ?>"></span>
                            
                            <div class="space-y-1">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1 text-xs">
                                    <span class="font-bold text-slate-300">
                                        <?php echo SecurityHelper::escape($userMap[$log->getUserId()] ?? 'Unknown'); ?>
                                        <span class="font-normal text-slate-500">
                                            <?php 
                                            if ($log->getAction() === 'created') echo 'created the task';
                                            elseif ($log->getAction() === 'checked_out') echo 'checked out the task';
                                            elseif ($log->getAction() === 'checked_in') echo 'returned the task to To Do';
                                            elseif ($log->getAction() === 'completed') echo 'completed the task';
                                            ?>
                                        </span>
                                    </span>
                                    <span class="text-slate-500 text-[10px]"><?php echo date('M d, Y g:i a', strtotime($log->getCreatedAt())); ?></span>
                                </div>
                                <?php if ($log->getNote()): ?>
                                    <div class="bg-slate-950/60 text-slate-400 text-xs px-3 py-2 rounded-lg border border-slate-800/80 mt-1.5 font-sans whitespace-pre-wrap">
                                        &ldquo;<?php echo SecurityHelper::escape($log->getNote()); ?>&rdquo;
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
function toggleEditComment(commentId) {
    const textEl = document.getElementById('comment-text-' + commentId);
    const formEl = document.getElementById('comment-form-' + commentId);
    if (textEl && formEl) {
        if (formEl.classList.contains('hidden')) {
            formEl.classList.remove('hidden');
            textEl.classList.add('hidden');
        } else {
            formEl.classList.add('hidden');
            textEl.classList.remove('hidden');
        }
    }
}
</script>

<?php
require_once __DIR__ . '/templates/footer.php';
?>
