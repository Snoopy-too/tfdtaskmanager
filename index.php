<?php
declare(strict_types=1);

$container = require_once __DIR__ . '/src/bootstrap.php';

use App\Infrastructure\Security\SecurityHelper;
use App\Application\Services\TaskService;
use App\Application\Services\ProjectService;
use App\Application\Services\UserService;

SecurityHelper::requireLogin();

$taskService = $container->get(TaskService::class);
$projectService = $container->get(ProjectService::class);
$userService = $container->get(UserService::class);

$selectedProjectId = isset($_GET['project_id']) && $_GET['project_id'] !== '' ? (int)$_GET['project_id'] : null;
$selectedStatus = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null;

$tasks = $taskService->getTasksFiltered($selectedProjectId, $selectedStatus);

$projects = $projectService->getAllProjects();
$users = $userService->getAllUsers();

$projectMap = [];
foreach ($projects as $p) {
    $projectMap[$p->getId()] = $p->getName();
}

$userMap = [];
foreach ($users as $u) {
    $userMap[$u->getId()] = $u->getName();
}

$todoTasks = [];
$inProgressTasks = [];
$doneTasks = [];

foreach ($tasks as $task) {
    if ($task->getStatus() === 'To Do') {
        $todoTasks[] = $task;
    } elseif ($task->getStatus() === 'In Progress') {
        $inProgressTasks[] = $task;
    } elseif ($task->getStatus() === 'Done') {
        $doneTasks[] = $task;
    }
}

require_once __DIR__ . '/templates/header.php';
?>

<div class="space-y-8">
    
    <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-white">Task Board</h1>
            <p class="text-slate-400 mt-1">Create and track TFD tasks, checkout tasks, and manage progress of TFD games.</p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="add_task.php" class="inline-flex items-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white font-medium text-sm rounded-lg shadow transition duration-200">
                + Create Task
            </a>
        </div>
    </div>

    <div class="bg-slate-900/50 border border-slate-800 p-4 rounded-xl shadow-md">
        <form action="index.php" method="GET" class="flex flex-col md:flex-row items-end gap-4">
            <div class="flex-grow w-full md:max-w-xs">
                <label for="project_filter" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Filter by Project</label>
                <select id="project_filter" name="project_id" onchange="this.form.submit()"
                    class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 text-sm transition outline-none">
                    <option value="">All Projects</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?php echo $project->getId(); ?>" <?php echo $selectedProjectId === $project->getId() ? 'selected' : ''; ?>>
                            <?php echo SecurityHelper::escape($project->getName()); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <?php if ($selectedProjectId !== null): ?>
                <a href="index.php" class="text-xs text-slate-500 hover:text-slate-300 font-medium pb-2.5 transition">
                    Clear Filters
                </a>
            <?php endif; ?>
        </form>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <div class="bg-slate-950/40 border border-slate-800/80 rounded-2xl p-4 flex flex-col min-h-[500px]">
            <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-800/60">
                <div class="flex items-center space-x-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-slate-400"></span>
                    <h2 class="text-lg font-bold text-slate-200">To Do</h2>
                </div>
                <span class="bg-slate-800 text-slate-400 text-xs px-2.5 py-0.5 rounded-full font-semibold">
                    <?php echo count($todoTasks); ?>
                </span>
            </div>

            <div class="space-y-4 flex-grow overflow-y-auto">
                <?php if (empty($todoTasks)): ?>
                    <p class="text-slate-500 text-center py-8 text-sm">No tasks in To Do.</p>
                <?php else: ?>
                    <?php foreach ($todoTasks as $task): ?>
                        <?php 
                        $deadlineTime = $task->getDeadline() ? strtotime($task->getDeadline()) : null;
                        $isOverdue = $deadlineTime && $deadlineTime < strtotime(date('Y-m-d'));
                        ?>
                        <div class="bg-slate-900 border border-slate-800/80 p-4 rounded-xl hover:border-slate-700 transition duration-200 flex flex-col justify-between space-y-3 group shadow-lg">
                            <div>
                                <span class="text-xs font-semibold text-indigo-400 uppercase tracking-wider">
                                    <?php echo SecurityHelper::escape($projectMap[$task->getProjectId()] ?? 'Unknown Project'); ?>
                                </span>
                                <h3 class="text-base font-bold text-slate-100 mt-1 group-hover:text-indigo-300 transition">
                                    <a href="task_detail.php?id=<?php echo $task->getId(); ?>">
                                        <?php echo SecurityHelper::escape($task->getTitle()); ?>
                                    </a>
                                </h3>
                                <p class="text-xs text-slate-400 line-clamp-2 mt-2">
                                    <?php echo SecurityHelper::escape(strip_tags($task->getDetails() ?: 'No details provided.')); ?>
                                </p>
                            </div>
                            
                            <div class="flex items-center justify-between pt-3 border-t border-slate-800/40 text-xs">
                                <div>
                                    <?php if ($deadlineTime): ?>
                                        <span class="<?php echo $isOverdue ? 'text-rose-400 font-medium' : 'text-slate-500'; ?>">
                                            Due: <?php echo date('M d, Y', $deadlineTime); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-600">No deadline</span>
                                    <?php endif; ?>
                                </div>
                                <a href="task_detail.php?id=<?php echo $task->getId(); ?>" class="text-indigo-400 hover:text-indigo-300 font-semibold transition">View Details &rarr;</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-slate-950/40 border border-slate-800/80 rounded-2xl p-4 flex flex-col min-h-[500px]">
            <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-800/60">
                <div class="flex items-center space-x-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                    <h2 class="text-lg font-bold text-slate-200">In Progress</h2>
                </div>
                <span class="bg-amber-500/10 text-amber-400 text-xs px-2.5 py-0.5 rounded-full font-semibold border border-amber-500/20">
                    <?php echo count($inProgressTasks); ?>
                </span>
            </div>

            <div class="space-y-4 flex-grow overflow-y-auto">
                <?php if (empty($inProgressTasks)): ?>
                    <p class="text-slate-500 text-center py-8 text-sm">No tasks in progress.</p>
                <?php else: ?>
                    <?php foreach ($inProgressTasks as $task): ?>
                        <div class="bg-slate-900 border border-amber-500/10 p-4 rounded-xl hover:border-amber-500/20 transition duration-200 flex flex-col justify-between space-y-3 shadow-lg">
                            <div>
                                <span class="text-xs font-semibold text-indigo-400 uppercase tracking-wider">
                                    <?php echo SecurityHelper::escape($projectMap[$task->getProjectId()] ?? 'Unknown Project'); ?>
                                </span>
                                <h3 class="text-base font-bold text-slate-100 mt-1 hover:text-indigo-300 transition">
                                    <a href="task_detail.php?id=<?php echo $task->getId(); ?>">
                                        <?php echo SecurityHelper::escape($task->getTitle()); ?>
                                    </a>
                                </h3>
                                
                                <div class="mt-3 flex items-center space-x-2 bg-slate-950/40 border border-slate-800/80 px-2.5 py-1.5 rounded-lg">
                                    <div class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></div>
                                    <div class="text-[11px] text-slate-300">
                                        Assigned: <span class="font-bold text-slate-200"><?php echo SecurityHelper::escape($userMap[$task->getAssignedTo()] ?? 'Unknown'); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between pt-3 border-t border-slate-800/40 text-xs">
                                <span class="text-slate-500">
                                    Active: <?php echo date('M d', strtotime($task->getCheckedOutAt())); ?>
                                </span>
                                <a href="task_detail.php?id=<?php echo $task->getId(); ?>" class="text-indigo-400 hover:text-indigo-300 font-semibold transition">Manage Task &rarr;</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-slate-950/40 border border-slate-800/80 rounded-2xl p-4 flex flex-col min-h-[500px]">
            <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-800/60">
                <div class="flex items-center space-x-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                    <h2 class="text-lg font-bold text-slate-200">Done</h2>
                </div>
                <span class="bg-emerald-500/10 text-emerald-400 text-xs px-2.5 py-0.5 rounded-full font-semibold border border-emerald-500/20">
                    <?php echo count($doneTasks); ?>
                </span>
            </div>

            <div class="space-y-4 flex-grow overflow-y-auto">
                <?php if (empty($doneTasks)): ?>
                    <p class="text-slate-500 text-center py-8 text-sm">No completed tasks.</p>
                <?php else: ?>
                    <?php foreach ($doneTasks as $task): ?>
                        <div class="bg-slate-900 border border-slate-800/80 p-4 rounded-xl hover:border-slate-700 transition duration-200 flex flex-col justify-between space-y-3 shadow-lg opacity-75 hover:opacity-100">
                            <div>
                                <span class="text-xs font-semibold text-indigo-400 uppercase tracking-wider">
                                    <?php echo SecurityHelper::escape($projectMap[$task->getProjectId()] ?? 'Unknown Project'); ?>
                                </span>
                                <h3 class="text-base font-bold text-slate-300 line-through mt-1 hover:text-indigo-300 transition">
                                    <a href="task_detail.php?id=<?php echo $task->getId(); ?>">
                                        <?php echo SecurityHelper::escape($task->getTitle()); ?>
                                    </a>
                                </h3>
                            </div>
                            
                            <div class="flex items-center justify-between pt-3 border-t border-slate-800/40 text-xs">
                                <span class="text-emerald-500 font-medium flex items-center space-x-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                    <span>Done</span>
                                </span>
                                <a href="task_detail.php?id=<?php echo $task->getId(); ?>" class="text-indigo-400 hover:text-indigo-300 font-semibold transition">View Details &rarr;</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php
require_once __DIR__ . '/templates/footer.php';
?>
