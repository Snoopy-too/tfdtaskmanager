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
        $title = trim($_POST['title'] ?? '');
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

// Prepare header actions
ob_start();
?>
<button type="button" onclick="toggleCollapsible('meeting-form-panel')"
    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-xs font-semibold bg-indigo-600 hover:bg-indigo-500 text-white shadow-sm hover:shadow transition duration-150">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
    <span>Schedule Meeting</span>
</button>
<?php
$headerActionsHtml = ob_get_clean();

// Render sticky-top header
renderPageHeader('Div/Dev Meetings', count($meetings) . ' meetings', $headerActionsHtml);
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

    <!-- Collapsible Schedule Meeting Form Panel (Hidden until needed) -->
    <div id="meeting-form-panel" class="form-panel-collapsible <?php echo !empty($error) ? 'is-open' : ''; ?>">
        <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-800">
            <div>
                <h2 class="text-base font-bold text-slate-100">Schedule Developers' Meeting</h2>
                <p class="text-xs text-slate-400">Plan a new prototyping sync, alignment session, or design review.</p>
            </div>
            <button type="button" onclick="toggleCollapsible('meeting-form-panel')" class="text-slate-400 hover:text-slate-200 text-sm font-semibold p-1">
                &times; Close
            </button>
        </div>

        <form action="meetings.php" method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="meeting_title" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Meeting Title *</label>
                    <input type="text" id="meeting_title" name="title" required
                        placeholder="e.g., Weekly Prototyping Review"
                        class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 text-sm outline-none transition">
                </div>

                <div>
                    <label for="scheduled_date" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Scheduled Date (Optional)</label>
                    <input type="date" id="scheduled_date" name="scheduled_date"
                        class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 text-sm outline-none transition">
                    <p class="text-[11px] text-slate-500 mt-1">Leave empty to display as "Pending" while the team aligns.</p>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800/80">
                <button type="button" onclick="toggleCollapsible('meeting-form-panel')"
                    class="px-4 py-2 rounded-lg text-xs font-medium text-slate-400 hover:text-slate-200 transition">
                    Cancel
                </button>
                <button type="submit"
                    class="px-5 py-2 rounded-lg text-xs font-semibold bg-indigo-600 hover:bg-indigo-500 text-white shadow-sm transition">
                    Schedule Meeting
                </button>
            </div>
        </form>
    </div>

    <!-- Data Table (Replaces Cards) -->
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Meeting Title</th>
                    <th>Status</th>
                    <th>Scheduled Date</th>
                    <th>Organizer</th>
                    <th>Created</th>
                    <th class="text-right" style="width: 140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($meetings)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-12 text-slate-400">
                            <div class="max-w-sm mx-auto space-y-2">
                                <p class="text-base font-semibold text-slate-300">No meetings scheduled</p>
                                <p class="text-xs text-slate-500">Plan a developers' meeting to discuss new prototypes, resolve design bugs, and plan sprints.</p>
                                <div class="pt-2">
                                    <button type="button" onclick="toggleCollapsible('meeting-form-panel')"
                                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg transition">
                                        + Schedule Meeting
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($meetings as $meeting): ?>
                        <tr>
                            <!-- Meeting Title -->
                            <td data-sort-value="<?php echo SecurityHelper::escape($meeting->getTitle()); ?>">
                                <a href="meeting_detail.php?id=<?php echo $meeting->getId(); ?>"
                                   class="font-semibold text-slate-100 hover:text-indigo-400 transition">
                                    <?php echo SecurityHelper::escape($meeting->getTitle()); ?>
                                </a>
                            </td>

                            <!-- Status Badge -->
                            <td data-sort-value="<?php echo $meeting->isFinished() ? 'Finished' : ($meeting->getScheduledDate() === null ? 'Pending' : 'Scheduled'); ?>">
                                <?php if ($meeting->isFinished()): ?>
                                    <span class="stamp-finished">Finished</span>
                                <?php elseif ($meeting->getScheduledDate() === null): ?>
                                    <span class="status-pill status-pill--private">Pending</span>
                                <?php else: ?>
                                    <span class="status-pill status-pill--public">Scheduled</span>
                                <?php endif; ?>
                            </td>

                            <!-- Scheduled Date -->
                            <td class="text-xs" data-sort-value="<?php echo $meeting->getScheduledDate() ? strtotime($meeting->getScheduledDate()) : ''; ?>">
                                <?php if ($meeting->getScheduledDate()): ?>
                                    <span class="text-slate-200 font-medium">
                                        <?php echo date('F d, Y', strtotime($meeting->getScheduledDate())); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-amber-400 font-medium">TBD (Pending date)</span>
                                <?php endif; ?>
                            </td>

                            <!-- Organizer -->
                            <td class="text-xs text-slate-300" data-sort-value="<?php echo SecurityHelper::escape($userMap[$meeting->getCreatedBy()] ?? ''); ?>">
                                <?php echo SecurityHelper::escape($userMap[$meeting->getCreatedBy()] ?? 'Unknown User'); ?>
                            </td>

                            <!-- Created -->
                            <td class="text-xs text-slate-400 whitespace-nowrap" data-sort-value="<?php echo strtotime($meeting->getCreatedAt()); ?>">
                                <?php echo date('M d, Y', strtotime($meeting->getCreatedAt())); ?>
                            </td>

                            <!-- Actions -->
                            <td class="text-right text-xs">
                                <a href="meeting_detail.php?id=<?php echo $meeting->getId(); ?>"
                                   class="text-indigo-400 hover:text-indigo-300 font-medium transition">
                                    View Agenda &rarr;
                                </a>
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
