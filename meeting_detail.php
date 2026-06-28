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
            $title = $_POST['title'] ?? '';
            $scheduledDate = $_POST['scheduled_date'] ?? '';
            try {
                $meeting = $meetingService->updateMeeting($meetingId, $title, $scheduledDate);
                $success = 'Meeting details successfully updated.';
                $isEditing = false;
            } catch (ValidationException $e) {
                $error = $e->getMessage();
            }
        } elseif ($action === 'add_topic') {
            $topicTitle = $_POST['topic_title'] ?? '';
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
            $topicTitle = $_POST['topic_title'] ?? '';
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

require_once __DIR__ . '/templates/header.php';
?>

<div class="space-y-8">
    <!-- Back to list link -->
    <div>
        <a href="meetings.php" class="text-indigo-400 hover:text-indigo-300 font-medium transition inline-flex items-center space-x-1.5">
            <span>&larr; Back to Meetings</span>
        </a>
    </div>

    <!-- Alert Messages -->
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

    <!-- Meeting Details Card -->
    <div class="bg-slate-900/50 border border-slate-800 p-6 rounded-2xl shadow-xl">
        <?php if ($isEditing): ?>
            <!-- Edit Form -->
            <form action="meeting_detail.php?id=<?php echo $meetingId; ?>" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                <input type="hidden" name="action" value="edit_meeting">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="title" class="block text-sm font-medium text-slate-300 mb-1">Div/Dev Title</label>
                        <input type="text" id="title" name="title" required
                            value="<?php echo SecurityHelper::escape($meeting->getTitle()); ?>"
                            class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 transition outline-none">
                    </div>

                    <div>
                        <label for="scheduled_date" class="block text-sm font-medium text-slate-300 mb-1">Scheduled Date (Optional)</label>
                        <input type="date" id="scheduled_date" name="scheduled_date"
                            value="<?php echo $meeting->getScheduledDate() ? SecurityHelper::escape($meeting->getScheduledDate()) : ''; ?>"
                            class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 transition outline-none">
                    </div>
                </div>

                <div class="flex items-center space-x-3 pt-2">
                    <button type="submit"
                        class="bg-indigo-600 hover:bg-indigo-500 text-white font-medium px-4 py-2 rounded-lg transition duration-200 text-sm">
                        Save Changes
                    </button>
                    <a href="meeting_detail.php?id=<?php echo $meetingId; ?>"
                        class="text-slate-400 hover:text-white font-medium text-sm transition">
                        Cancel
                    </a>
                </div>
            </form>
        <?php else: ?>
            <!-- View Details -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3 flex-wrap">
                        <h2 class="text-2xl font-extrabold text-white"><?php echo SecurityHelper::escape($meeting->getTitle()); ?></h2>
                        <?php if ($meeting->getScheduledDate() === null): ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">Pending</span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">Scheduled</span>
                        <?php endif; ?>
                    </div>
                    <p class="text-slate-400 mt-1">
                        Meeting Date: <?php echo $meeting->getScheduledDate() ? date('F d, Y', strtotime($meeting->getScheduledDate())) : '<span class="text-amber-500 font-medium">Pending team decision</span>'; ?>
                    </p>
                    <p class="text-xs text-slate-500 mt-2">
                        Organized by <?php echo SecurityHelper::escape($userMap[$meeting->getCreatedBy()] ?? 'Unknown User'); ?> &bull; Created on <?php echo date('M d, Y', strtotime($meeting->getCreatedAt())); ?>
                    </p>
                </div>
                <div>
                    <!-- ponytail: simple state toggle for edit via URL parameter -->
                    <a href="meeting_detail.php?id=<?php echo $meetingId; ?>&edit=1"
                        class="inline-flex items-center px-4 py-2 border border-slate-700 text-sm font-medium rounded-lg text-slate-300 hover:text-white hover:border-slate-500 hover:bg-slate-800 transition duration-200">
                        Edit Details
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Agenda Topics Section -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Topics List -->
        <div class="lg:col-span-2 space-y-4">
            <h3 class="text-xl font-bold text-slate-200 mb-4">Meeting Agenda Topics</h3>

            <?php if (empty($topics)): ?>
                <div class="bg-slate-900/30 border border-slate-800/80 rounded-2xl p-12 text-center text-slate-400">
                    <p class="text-lg font-medium">No topics added to the agenda yet.</p>
                    <p class="text-sm mt-1 text-slate-500">Be the first to add a topic you want covered in this meeting.</p>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($topics as $index => $topic): ?>
                        <div class="bg-slate-900/50 border border-slate-800/80 p-4 rounded-xl flex items-start gap-4">
                            <div class="flex-shrink-0 flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 font-bold text-sm">
                                <?php echo $index + 1; ?>
                            </div>
                            <div class="flex-grow">
                                <?php if (isset($_GET['edit_topic']) && (int)$_GET['edit_topic'] === $topic->getId() && $topic->getUserId() === $currentUserId): ?>
                                    <form action="meeting_detail.php?id=<?php echo $meetingId; ?>" method="POST" class="space-y-2">
                                        <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                                        <input type="hidden" name="action" value="edit_topic">
                                        <input type="hidden" name="topic_id" value="<?php echo $topic->getId(); ?>">
                                        <input type="text" name="topic_title" required
                                            value="<?php echo SecurityHelper::escape($topic->getTitle()); ?>"
                                            class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 transition outline-none text-sm">
                                        <div class="flex items-center space-x-2">
                                            <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white font-medium px-3 py-1 rounded text-xs transition duration-200">
                                                Save
                                            </button>
                                            <a href="meeting_detail.php?id=<?php echo $meetingId; ?>" class="text-slate-400 hover:text-white font-medium text-xs transition">
                                                Cancel
                                            </a>
                                        </div>
                                    </form>
                                <?php else: ?>
                                    <div class="flex items-start justify-between gap-3">
                                        <p class="text-slate-200 font-medium text-base"><?php echo SecurityHelper::escape($topic->getTitle()); ?></p>
                                        <?php if ($topic->getUserId() === $currentUserId): ?>
                                            <a href="meeting_detail.php?id=<?php echo $meetingId; ?>&edit_topic=<?php echo $topic->getId(); ?>" class="text-xs text-indigo-400 hover:text-indigo-300 font-medium transition whitespace-nowrap">
                                                Edit
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-1">
                                        Added by <?php echo SecurityHelper::escape($userMap[$topic->getUserId()] ?? 'Unknown User'); ?> &bull; <?php echo date('M d, Y, h:i A', strtotime($topic->getCreatedAt())); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Add Topic Form -->
        <div class="bg-slate-900/50 border border-slate-800 p-6 rounded-2xl shadow-xl h-fit">
            <h3 class="text-lg font-bold text-slate-200 mb-4">Suggest Agenda Topic</h3>

            <form action="meeting_detail.php?id=<?php echo $meetingId; ?>" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                <input type="hidden" name="action" value="add_topic">

                <div>
                    <label for="topic_title" class="block text-sm font-medium text-slate-300 mb-1">Topic Title</label>
                    <textarea id="topic_title" name="topic_title" rows="4" required
                        placeholder="What would you like to cover? Describe the task, challenge, or update..."
                        class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 placeholder-slate-500 transition outline-none"></textarea>
                </div>

                <button type="submit"
                    class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-medium py-2 rounded-lg transition duration-200">
                    Add to Agenda
                </button>
            </form>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/templates/footer.php';
?>
