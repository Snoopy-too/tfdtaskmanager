<?php
declare(strict_types=1);

define('CLI_VERIFY', true);

use App\Application\Services\UserService;
use App\Application\Services\ProjectService;
use App\Application\Services\TaskService;
use App\Application\Services\BgTemplateService;
use App\Application\Services\BgRulebookService;
use App\Application\Exceptions\ValidationException;

try {
    $container = require_once __DIR__ . '/src/bootstrap.php';
    echo "[TEST] Bootstrap initialized.\n";

    $userService = $container->get(UserService::class);
    $projectService = $container->get(ProjectService::class);
    $taskService = $container->get(TaskService::class);
    $templateService = $container->get(BgTemplateService::class);
    $rulebookService = $container->get(BgRulebookService::class);

    $users = $userService->getAllUsers();
    if (count($users) < 1) {
        throw new \Exception("No users found in database.");
    }

    $userA = $users[0];
    
    // Ensure we have a second distinct user for isolation tests
    $userB = null;
    if (count($users) >= 2) {
        $userB = $users[1];
    } else {
        $userB = $userService->createUser('User B', 'userb@test.local', 'Password123!', 'member');
    }

    echo sprintf("[TEST] User A ID: %d (%s), User B ID: %d (%s)\n", $userA->getId(), $userA->getName(), $userB->getId(), $userB->getName());

    // 1. Create Public Project (default)
    $publicProject = $projectService->createProject('Sanity Public Game', 'Everyone can see this', false, $userA->getId());
    assert(!$publicProject->isPrivate(), "Public project isPrivate should be false");
    echo "[PASS] Public project created successfully.\n";

    // 2. Create Private Project for User A
    $privateProject = $projectService->createProject('User A Secret Prototype', 'Only User A should see this', true, $userA->getId());
    assert($privateProject->isPrivate(), "Private project isPrivate should be true");
    assert($privateProject->getCreatedBy() === $userA->getId(), "Private project creator should match User A");
    echo "[PASS] Private project created successfully.\n";

    // 3. Verify Project Visibility
    // User A can see both
    $userAProjects = $projectService->getAllProjects($userA->getId());
    $userAProjectIds = array_map(fn($p) => $p->getId(), $userAProjects);
    assert(in_array($publicProject->getId(), $userAProjectIds, true), "User A should see public project");
    assert(in_array($privateProject->getId(), $userAProjectIds, true), "User A should see their private project");

    // User B can see public, but NOT User A's private project
    $userBProjects = $projectService->getAllProjects($userB->getId());
    $userBProjectIds = array_map(fn($p) => $p->getId(), $userBProjects);
    assert(in_array($publicProject->getId(), $userBProjectIds, true), "User B should see public project");
    assert(!in_array($privateProject->getId(), $userBProjectIds, true), "User B must NOT see User A's private project in list");

    // Direct getProjectById lookup
    $lookupA = $projectService->getProjectById($privateProject->getId(), $userA->getId());
    assert($lookupA !== null, "User A should be able to get their private project by ID");
    $lookupB = $projectService->getProjectById($privateProject->getId(), $userB->getId());
    assert($lookupB === null, "User B should receive null when looking up User A's private project by ID");
    echo "[PASS] Project listing and direct lookup privacy checks passed.\n";

    // 4. Create Task under Private Project
    $privateTask = $taskService->createTask($privateProject->getId(), 'Top Secret Card Mechanic', 'Secret details', null, $userA->getId(), false);
    assert($privateTask->getId() > 0, "Private task should be created");
    echo "[PASS] Private task created under private project.\n";

    // 5. User B should NOT be able to create a task in User A's private project
    $blockedTaskCreation = false;
    try {
        $taskService->createTask($privateProject->getId(), 'Hacker Task', 'Details', null, $userB->getId(), false);
    } catch (ValidationException $e) {
        $blockedTaskCreation = true;
    }
    assert($blockedTaskCreation, "User B must be forbidden from creating tasks in User A's private project");
    echo "[PASS] Task creation authorization verified.\n";

    // 6. Verify Task Board Filter Scoping
    $userATasks = $taskService->getTasksFiltered(null, null, false, null, false, $userA->getId());
    $userATaskIds = array_map(fn($t) => $t->getId(), $userATasks);
    assert(in_array($privateTask->getId(), $userATaskIds, true), "User A should see private task in tasks list");

    $userBTasks = $taskService->getTasksFiltered(null, null, false, null, false, $userB->getId());
    $userBTaskIds = array_map(fn($t) => $t->getId(), $userBTasks);
    assert(!in_array($privateTask->getId(), $userBTaskIds, true), "User B must NOT see private task in tasks list");
    echo "[PASS] Task list filtering privacy checks passed.\n";

    // 7. Verify Direct Task Lookup & Mutation by Unauthorized User
    $taskLookupB = $taskService->getTaskById($privateTask->getId(), $userB->getId());
    assert($taskLookupB === null, "User B getTaskById on private task must return null");

    $blockedCheckout = false;
    try {
        $taskService->checkoutTask($privateTask->getId(), $userB->getId(), $privateTask->getVersion());
    } catch (ValidationException $e) {
        $blockedCheckout = true;
    }
    assert($blockedCheckout, "User B checkout on private task must throw ValidationException");

    $blockedComment = false;
    try {
        $taskService->addComment($privateTask->getId(), $userB->getId(), 'Sneaky comment');
    } catch (ValidationException $e) {
        $blockedComment = true;
    }
    assert($blockedComment, "User B addComment on private task must throw ValidationException");
    echo "[PASS] Task mutations blocked for unauthorized users.\n";

    // 8. Board Game Studio Isolation
    $rulebookA = $rulebookService->createRulebook($privateProject->getId(), 'Secret Rules', [], $userA->getId());
    assert($rulebookA->getId() > 0, "Rulebook created under private project");
    echo "[PASS] Rulebook created under private project.\n";

    // 9. Update project privacy toggle (Private -> Public)
    $projectService->updateProject($privateProject->getId(), 'Now Public Prototype', 'Now visible to all', false, $userA->getId());
    $unlockedProject = $projectService->getProjectById($privateProject->getId(), $userB->getId());
    assert($unlockedProject !== null, "User B should now be able to see project after it was converted to public");
    echo "[PASS] Project privacy toggle (Private -> Public) verified.\n";

    // Clean up
    $db = $container->get(PDO::class);
    $db->exec(sprintf("DELETE FROM bg_rulebooks WHERE id = %d", $rulebookA->getId()));
    $db->exec(sprintf("DELETE FROM tasks WHERE id = %d", $privateTask->getId()));
    $db->exec(sprintf("DELETE FROM projects WHERE id IN (%d, %d)", $publicProject->getId(), $privateProject->getId()));
    echo "[PASS] Test cleanup completed successfully.\n";

    echo "\n=== ALL PRIVACY TESTS PASSED PERFECTLY! ===\n";

} catch (\Throwable $e) {
    fwrite(STDERR, "\n[FAIL] Privacy Verification Failed: " . $e->getMessage() . "\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(1);
}
