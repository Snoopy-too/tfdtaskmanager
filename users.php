<?php
declare(strict_types=1);

$container = require_once __DIR__ . '/src/bootstrap.php';

use App\Infrastructure\Security\SecurityHelper;
use App\Application\Services\UserService;
use App\Application\Exceptions\ValidationException;

SecurityHelper::requireSuperAdmin();

$userService = $container->get(UserService::class);

$error = '';
$success = '';
$editUser = null;

$csrfToken = SecurityHelper::generateCsrfToken();

if (isset($_GET['edit'])) {
    $editUser = $userService->getUserById((int)$_GET['edit']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    
    if (!SecurityHelper::verifyCsrfToken($submittedToken)) {
        $error = 'Security check failed. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        try {
            if ($action === 'create') {
                $name = trim($_POST['name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';
                $role = $_POST['role'] ?? 'member';

                $userService->createUser($name, $email, $password, $role);
                $success = "User '$name' successfully created.";
            } elseif ($action === 'update') {
                $id = (int)($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $password = $_POST['password'] ?? null;
                $role = $_POST['role'] ?? 'member';

                $userService->updateUser($id, $name, $email, $password, $role);
                $success = "User '$name' successfully updated.";
                $editUser = null;
            } elseif ($action === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                $userService->deleteUser($id);
                $success = "User successfully deleted.";
            }
        } catch (ValidationException $e) {
            $error = $e->getMessage();
        }
    }
}

$users = $userService->getAllUsers();
$memberCount = 0;
foreach ($users as $u) {
    if ($u->getRole() === 'member') {
        $memberCount++;
    }
}

require_once __DIR__ . '/templates/header.php';

// Prepare header actions
ob_start();
?>
<button type="button" onclick="toggleCollapsible('user-form-panel')"
    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg text-xs font-semibold bg-indigo-600 hover:bg-indigo-500 text-white shadow-sm hover:shadow transition duration-150">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
    <span><?php echo $editUser ? 'Edit User' : 'New User'; ?></span>
</button>
<?php
$headerActionsHtml = ob_get_clean();

// Render sticky-top header
renderPageHeader('User Management', $memberCount . ' / 4 Team Members', $headerActionsHtml);
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

    <!-- Collapsible Form Panel (Hidden until needed) -->
    <div id="user-form-panel" class="form-panel-collapsible <?php echo ($editUser !== null || !empty($error)) ? 'is-open' : ''; ?>">
        <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-800">
            <div>
                <h2 class="text-base font-bold text-slate-100">
                    <?php echo $editUser ? 'Edit User Account' : 'Create Team Member'; ?>
                </h2>
                <p class="text-xs text-slate-400">Manage developer credentials, login roles, and permissions.</p>
            </div>
            <button type="button" onclick="toggleCollapsible('user-form-panel')" class="text-slate-400 hover:text-slate-200 text-sm font-semibold p-1">
                &times; Close
            </button>
        </div>

        <form action="users.php" method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
            <input type="hidden" name="action" value="<?php echo $editUser ? 'update' : 'create'; ?>">
            <?php if ($editUser): ?>
                <input type="hidden" name="id" value="<?php echo (int)$editUser->getId(); ?>">
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="user_name" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Full Name *</label>
                    <input type="text" id="user_name" name="name" required
                        value="<?php echo $editUser ? SecurityHelper::escape($editUser->getName()) : ''; ?>"
                        class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 text-sm outline-none transition">
                </div>

                <div>
                    <label for="user_email" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Email Address *</label>
                    <input type="email" id="user_email" name="email" required
                        value="<?php echo $editUser ? SecurityHelper::escape($editUser->getEmail()) : ''; ?>"
                        class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 text-sm outline-none transition">
                </div>

                <div>
                    <label for="user_password" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                        Password <span class="text-slate-500 text-[11px]">(optional – managed via central TFD)</span>
                    </label>
                    <input type="password" id="user_password" name="password"
                        placeholder="<?php echo $editUser ? 'Leave blank to keep unchanged' : 'Leave blank for SSO account'; ?>"
                        class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 text-sm outline-none transition">
                </div>

                <div>
                    <label for="user_role" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">System Role *</label>
                    <select id="user_role" name="role" required
                        class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 text-sm outline-none transition cursor-pointer">
                        <option value="member" <?php echo ($editUser && $editUser->getRole() === 'member') ? 'selected' : ''; ?>>Team Member</option>
                        <option value="super_admin" <?php echo ($editUser && $editUser->getRole() === 'super_admin') ? 'selected' : ''; ?>>Super-Admin</option>
                    </select>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800/80">
                <?php if ($editUser): ?>
                    <a href="users.php" class="px-4 py-2 rounded-lg text-xs font-medium text-slate-400 hover:text-slate-200 transition">
                        Cancel Edit
                    </a>
                <?php else: ?>
                    <button type="button" onclick="toggleCollapsible('user-form-panel')"
                        class="px-4 py-2 rounded-lg text-xs font-medium text-slate-400 hover:text-slate-200 transition">
                        Cancel
                    </button>
                <?php endif; ?>
                <button type="submit"
                    class="px-5 py-2 rounded-lg text-xs font-semibold bg-indigo-600 hover:bg-indigo-500 text-white shadow-sm transition">
                    <?php echo $editUser ? 'Save Changes' : 'Create User'; ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Data Table (Full Width) -->
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>User Name</th>
                    <th>Email Address</th>
                    <th>System Role</th>
                    <th class="text-right" style="width: 140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <!-- Name -->
                        <td data-sort-value="<?php echo SecurityHelper::escape($user->getName()); ?>">
                            <div class="font-semibold text-slate-100">
                                <?php echo SecurityHelper::escape($user->getName()); ?>
                            </div>
                        </td>

                        <!-- Email -->
                        <td class="text-xs text-slate-300" data-sort-value="<?php echo SecurityHelper::escape($user->getEmail()); ?>">
                            <?php echo SecurityHelper::escape($user->getEmail()); ?>
                        </td>

                        <!-- Role -->
                        <td data-sort-value="<?php echo $user->isSuperAdmin() ? 'Super-Admin' : 'Team Member'; ?>">
                            <?php if ($user->isSuperAdmin()): ?>
                                <span class="status-pill status-pill--public">Super-Admin</span>
                            <?php else: ?>
                                <span class="status-pill status-pill--done">Team Member</span>
                            <?php endif; ?>
                        </td>

                        <!-- Actions -->
                        <td class="text-right text-xs">
                            <div class="flex items-center justify-end gap-3">
                                <a href="users.php?edit=<?php echo $user->getId(); ?>"
                                   class="text-indigo-400 hover:text-indigo-300 font-medium transition">
                                    Edit
                                </a>

                                <?php if (!$user->isSuperAdmin()): ?>
                                    <form action="users.php" method="POST" class="inline"
                                          onsubmit="return showCustomConfirm('Are you sure you want to delete user <?php echo SecurityHelper::escape($user->getName()); ?>?', this, 'Delete', 'Delete User');">
                                        <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $user->getId(); ?>">
                                        <button type="submit" class="text-rose-400 hover:text-rose-300 font-medium transition">
                                            Delete
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

<?php
require_once __DIR__ . '/templates/footer.php';
?>
