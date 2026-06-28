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

$error = '';
$success = '';
$csrfToken = SecurityHelper::generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!SecurityHelper::verifyCsrfToken($submittedToken)) {
        $error = 'Security check failed. Please try again.';
    } else {
        $title = $_POST['title'] ?? '';
        $scheduledDate = $_POST['scheduled_date'] ?? '';

        try {
            $currentUserId = SecurityHelper::getCurrentUserId();
            if ($currentUserId === null) {
                throw new ValidationException("User not authenticated.");
            }
            
            $meetingService->createMeeting($title, $scheduledDate, $currentUserId);
            $success = "Developers' meeting '$title' successfully scheduled.";
        } catch (ValidationException $e) {
            $error = $e->getMessage();
        }
    }
}

$meetings = $meetingService->getAllMeetings();

// Load all users to map names
$users = $userService->getAllUsers();
$userMap = [];
foreach ($users as $u) {
    $userMap[$u->getId()] = $u->getName();
}

require_once __DIR__ . '/templates/header.php';
?>

<div class="space-y-8">
    <div>
        <h1 class="text-3xl font-extrabold tracking-tight text-white">Div/Dev Meetings</h1>
        <p class="text-slate-400 mt-1">Schedule and manage developers' meetings, collaborate on agenda topics, and align on upcoming development plans.</p>
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
        <!-- Create Meeting Form -->
        <div class="bg-slate-900/50 border border-slate-800 p-6 rounded-2xl shadow-xl h-fit">
            <h2 class="text-xl font-bold text-slate-200 mb-6">Schedule New Meeting</h2>

            <form action="meetings.php" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">

                <div>
                    <label for="title" class="block text-sm font-medium text-slate-300 mb-1">Div/Dev Title</label>
                    <input type="text" id="title" name="title" required
                        placeholder="e.g., Weekly Prototyping Review"
                        class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 placeholder-slate-500 transition outline-none">
                </div>

                <div>
                    <label for="scheduled_date" class="block text-sm font-medium text-slate-300 mb-1">Scheduled Date (Optional)</label>
                    <!-- ponytail: native date picker, shows Pending if left empty -->
                    <input type="date" id="scheduled_date" name="scheduled_date"
                        class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 placeholder-slate-500 transition outline-none">
                    <p class="text-xs text-slate-500 mt-1.5">Leave empty to display as "Pending" while the team decides.</p>
                </div>

                <button type="submit"
                    class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-medium py-2 rounded-lg transition duration-200">
                    Schedule Meeting
                </button>
            </form>
        </div>

        <!-- Meetings List -->
        <div class="lg:col-span-2 space-y-4">
            <h2 class="text-xl font-bold text-slate-200 mb-6 font-semibold">Active Developers' Meetings</h2>
            
            <?php if (empty($meetings)): ?>
                <div class="bg-slate-900/30 border border-slate-800/80 rounded-2xl p-12 text-center text-slate-400">
                    <p class="text-lg font-medium">No meetings scheduled yet.</p>
                    <p class="text-sm mt-1 text-slate-500">Create one on the left to start planning agenda topics.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($meetings as $meeting): ?>
                        <div class="bg-slate-900/50 border border-slate-800/80 p-5 rounded-2xl hover:border-slate-700 transition duration-300 flex flex-col justify-between">
                            <div>
                                <div class="flex items-start justify-between gap-3 mb-2">
                                    <h3 class="text-lg font-bold text-indigo-300">
                                        <a href="meeting_detail.php?id=<?php echo $meeting->getId(); ?>" class="hover:underline">
                                            <?php echo SecurityHelper::escape($meeting->getTitle()); ?>
                                        </a>
                                    </h3>
                                    <?php if ($meeting->getScheduledDate() === null): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">Pending</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">Scheduled</span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-sm text-slate-400">
                                    Date: <?php echo $meeting->getScheduledDate() ? date('F d, Y', strtotime($meeting->getScheduledDate())) : '<span class="text-amber-500/80 font-medium">TBD (Pending date)</span>'; ?>
                                </p>
                                <p class="text-xs text-slate-500 mt-2">
                                    Organized by: <?php echo SecurityHelper::escape($userMap[$meeting->getCreatedBy()] ?? 'Unknown User'); ?>
                                </p>
                            </div>
                            <div class="mt-4 pt-3 border-t border-slate-800/60 text-xs text-slate-500 flex items-center justify-between">
                                <span>Created: <?php echo date('M d, Y', strtotime($meeting->getCreatedAt())); ?></span>
                                <a href="meeting_detail.php?id=<?php echo $meeting->getId(); ?>" class="text-indigo-400 hover:text-indigo-300 font-medium transition">View Agenda &rarr;</a>
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
