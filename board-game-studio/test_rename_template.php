<?php
declare(strict_types=1);

$container = require_once __DIR__ . '/../src/bootstrap.php';

use App\Application\Services\BgTemplateService;
use App\Application\Services\BgRulebookService;
use App\Application\Services\ProjectService;

echo "===========================================\n";
echo "TEMPLATE RENAME & RULEBOOK SYNC TEST\n";
echo "===========================================\n\n";

try {
    $templateService = $container->get(BgTemplateService::class);
    $rulebookService = $container->get(BgRulebookService::class);
    $projectService = $container->get(ProjectService::class);
    $db = $container->get(PDO::class);

    // 1. Project context
    $projects = $projectService->getAllProjects();
    $tempProject = false;
    if (empty($projects)) {
        $db->exec("INSERT INTO projects (name, description) VALUES ('Test Rename Project', 'Auto-created')");
        $projectId = (int)$db->lastInsertId();
        $tempProject = true;
    } else {
        $projectId = $projects[0]->getId();
    }

    $userRow = $db->query("SELECT id FROM users LIMIT 1")->fetch();
    if (!$userRow) {
        throw new \Exception("No user found in database.");
    }
    $userId = (int)$userRow['id'];

    $compTypes = $templateService->getComponentTypes();
    if (empty($compTypes)) {
        throw new \Exception("No component types found in database.");
    }
    $compTypeId = $compTypes[0]->getId();

    // 2. Create template
    echo "1. Creating design template 'Original Card Template'... ";
    $tmpl = $templateService->createTemplate($projectId, $compTypeId, 'Original Card Template', 3.0, 5.0, null, $userId);
    echo "SUCCESS (ID: {$tmpl->getId()})\n";

    // 3. Create rulebook with content referencing 'Original Card Template'
    echo "2. Creating rulebook referencing template... ";
    $rbContent = [
        [
            'type' => 'markdown',
            'text' => 'Include 1x Original Card Template in component list.'
        ],
        [
            'type' => 'component_list',
            'title' => 'Inventory List'
        ]
    ];
    $rulebook = $rulebookService->createRulebook($projectId, 'Test Rulebook Sync', $rbContent, $userId);
    echo "SUCCESS (ID: {$rulebook->getId()})\n";

    // 4. Rename template via service
    echo "3. Renaming template to 'Renamed Card Template'... ";
    $updatedTmpl = $templateService->updateTemplate($tmpl->getId(), 'Renamed Card Template', 3.0, 5.0, null);
    if ($updatedTmpl->getName() !== 'Renamed Card Template') {
        throw new \Exception("Template rename failed in repository.");
    }
    echo "SUCCESS!\n";

    // 5. Verify rulebook sync
    echo "4. Verifying rulebook text content sync... ";
    $reloadedRb = $rulebookService->getRulebookById($rulebook->getId());
    $updatedText = $reloadedRb->getContent()[0]['text'] ?? '';
    if (!str_contains($updatedText, 'Renamed Card Template')) {
        throw new \Exception("Rulebook content was not synchronized with new template name. Content: " . $updatedText);
    }
    echo "SUCCESS (Found 'Renamed Card Template' in rulebook content!)\n";

    // 6. Clean up
    echo "5. Cleaning up test entities... ";
    $templateService->deleteTemplate($tmpl->getId());
    $rulebookService->deleteRulebook($rulebook->getId());
    if ($tempProject) {
        $db->exec("DELETE FROM projects WHERE id = $projectId");
    }
    echo "SUCCESS!\n\n";

    echo "===========================================\n";
    echo "ALL RENAMING & RULEBOOK SYNC CHECKS PASSED!\n";
    echo "===========================================\n";

} catch (\Throwable $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
