<?php
declare(strict_types=1);

define('CLI_VERIFY', true);

use App\Application\Services\UserService;
use App\Application\Services\ProjectService;
use App\Application\Services\TaskService;
use App\Infrastructure\Security\SecurityHelper;

try {
    $container = require_once __DIR__ . '/src/bootstrap.php';
    echo "1. Bootstrap and autoloader loaded successfully.\n";

    $userService = $container->get(UserService::class);
    $projectService = $container->get(ProjectService::class);
    $taskService = $container->get(TaskService::class);

    echo "2. Container services resolved successfully.\n";

    $users = $userService->getAllUsers();
    echo sprintf("3. Database connected. Found %d users in database.\n", count($users));

    $adminFound = false;
    foreach ($users as $u) {
        if ($u->getEmail() === 'admin@tfdtaskmgr.local') {
            $adminFound = true;
            break;
        }
    }
    
    if (!$adminFound) {
        throw new Exception("Seeded admin 'admin@tfdtaskmgr.local' not found.");
    }
    echo "4. Seeded Super-Admin account check passed.\n";

    $testProject = $projectService->createProject('Verification Project', 'Used for system sanity testing.');
    echo sprintf("5. Project Creation Use Case passed. ID: %d\n", $testProject->getId());

    $adminUser = null;
    foreach ($users as $u) {
        if ($u->getRole() === 'super_admin') {
            $adminUser = $u;
            break;
        }
    }
    
    $testTask = $taskService->createTask(
        $testProject->getId(),
        'Verification Task',
        'Details of validation task.',
        null,
        $adminUser->getId()
    );
    echo sprintf("6. Task Creation Use Case passed. ID: %d\n", $testTask->getId());

    $taskService->checkoutTask($testTask->getId(), $adminUser->getId());
    $updatedTask = $taskService->getTaskById($testTask->getId());
    if ($updatedTask->getStatus() !== 'In Progress' || $updatedTask->getAssignedTo() !== $adminUser->getId()) {
        throw new Exception("Task checkout failed state checks.");
    }
    echo "7. Task Checkout Engine and State checks passed.\n";

    $taskService->checkinTask($testTask->getId(), $adminUser->getId(), 'Completed validation step.');
    $updatedTask = $taskService->getTaskById($testTask->getId());
    if ($updatedTask->getStatus() !== 'To Do' || $updatedTask->getAssignedTo() !== null) {
        throw new Exception("Task check-in failed state checks.");
    }
    echo "8. Task Check-in Engine and State checks passed.\n";

    $db = $container->get(PDO::class);
    $db->exec(sprintf("DELETE FROM tasks WHERE id = %d", $testTask->getId()));
    $db->exec(sprintf("DELETE FROM projects WHERE id = %d", $testProject->getId()));
    echo "9. Verification clean-up completed.\n";

    echo "\nAll integration checks passed! Clean Architecture and local DB are working perfectly.\n";

} catch (Throwable $e) {
    fwrite(STDERR, "\nVerification Failed: " . $e->getMessage() . "\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(1);
}
