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
                $name = $_POST['name'] ?? '';
                $email = $_POST['email'] ?? '';
                $password = $_POST['password'] ?? '';
                $role = $_POST['role'] ?? 'member';

                $userService->createUser($name, $email, $password, $role);
                $success = "User '$name' successfully created.";
            } elseif ($action === 'update') {
                $id = (int)($_POST['id'] ?? 0);
                $name = $_POST['name'] ?? '';
                $email = $_POST['email'] ?? '';
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
?>

<div class="space-y-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-white">User Management</h1>
            <p class="text-slate-400 mt-1">Manage team member accounts and system access.</p>
        </div>
        <div class="bg-indigo-600/10 border border-indigo-500/20 px-4 py-2 rounded-xl text-indigo-400 text-sm">
            Team Members: <span class="font-bold"><?php echo $memberCount; ?></span> / 4
        </div>
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
        
        <div class="bg-slate-900/50 border border-slate-800 p-6 rounded-2xl shadow-xl h-fit">
            <h2 class="text-xl font-bold text-slate-200 mb-6">
                <?php echo $editUser ? 'Edit User' : 'Create Team Member'; ?>
            </h2>

            <form action="users.php" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                <input type="hidden" name="action" value="<?php echo $editUser ? 'update' : 'create'; ?>">
                <?php if ($editUser): ?>
                    <input type="hidden" name="id" value="<?php echo (int)$editUser->getId(); ?>">
                <?php endif; ?>

                <div>
                    <label for="name" class="block text-sm font-medium text-slate-300 mb-1">Full Name</label>
                    <input type="text" id="name" name="name" required
                        value="<?php echo $editUser ? SecurityHelper::escape($editUser->getName()) : ''; ?>"
                        class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 placeholder-slate-500 transition outline-none">
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-slate-300 mb-1">Email Address</label>
                    <input type="email" id="email" name="email" required
                        value="<?php echo $editUser ? SecurityHelper::escape($editUser->getEmail()) : ''; ?>"
                        class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 placeholder-slate-500 transition outline-none">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-300 mb-1">
                        Password <?php echo $editUser ? '<span class="text-slate-500 text-xs">(leave blank to keep current)</span>' : ''; ?>
                    </label>
                    <input type="password" id="password" name="password" <?php echo $editUser ? '' : 'required'; ?>
                        class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 placeholder-slate-500 transition outline-none">
                </div>

                <div>
                    <label for="role" class="block text-sm font-medium text-slate-300 mb-1">System Role</label>
                    <select id="role" name="role" required
                        class="w-full bg-slate-950/60 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-lg px-3 py-2 text-slate-100 transition outline-none">
                        <option value="member" <?php echo ($editUser && $editUser->getRole() === 'member') ? 'selected' : ''; ?>>Team Member</option>
                        <option value="super_admin" <?php echo ($editUser && $editUser->getRole() === 'super_admin') ? 'selected' : ''; ?>>Super-Admin</option>
                    </select>
                </div>

                <div class="flex space-x-3 pt-2">
                    <button type="submit"
                        class="flex-1 bg-indigo-600 hover:bg-indigo-500 text-white font-medium py-2 rounded-lg transition duration-200">
                        <?php echo $editUser ? 'Save Changes' : 'Create User'; ?>
                    </button>
                    <?php if ($editUser): ?>
                        <a href="users.php" class="bg-slate-800 hover:bg-slate-700 text-slate-300 px-4 py-2 rounded-lg transition duration-200 flex items-center justify-center">
                            Cancel
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="lg:col-span-2 bg-slate-900/50 border border-slate-800 p-6 rounded-2xl shadow-xl">
            <h2 class="text-xl font-bold text-slate-200 mb-6">System Accounts</h2>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="text-xs uppercase text-slate-400 border-b border-slate-800">
                        <tr>
                            <th scope="col" class="py-3 px-4">Name</th>
                            <th scope="col" class="py-3 px-4">Email</th>
                            <th scope="col" class="py-3 px-4">Role</th>
                            <th scope="col" class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <?php foreach ($users as $user): ?>
                            <tr class="hover:bg-slate-800/20 transition duration-150">
                                <td class="py-4 px-4 font-semibold text-slate-200">
                                    <?php echo SecurityHelper::escape($user->getName()); ?>
                                </td>
                                <td class="py-4 px-4">
                                    <?php echo SecurityHelper::escape($user->getEmail()); ?>
                                </td>
                                <td class="py-4 px-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $user->isSuperAdmin() ? 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20' : 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'; ?>">
                                        <?php echo SecurityHelper::escape($user->getRole() === 'super_admin' ? 'Super-Admin' : 'Team Member'); ?>
                                    </span>
                                </td>
                                <td class="py-4 px-4 text-right space-x-2">
                                    <a href="users.php?edit=<?php echo $user->getId(); ?>" class="text-indigo-400 hover:text-indigo-300 text-sm font-medium transition">Edit</a>
                                    
                                    <?php if (!$user->isSuperAdmin()): ?>
                                        <form action="users.php" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo SecurityHelper::escape($csrfToken); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $user->getId(); ?>">
                                            <button type="submit" class="text-rose-400 hover:text-rose-300 text-sm font-medium transition">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php
require_once __DIR__ . '/templates/footer.php';
?>
