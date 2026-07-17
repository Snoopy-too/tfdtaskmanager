<?php
declare(strict_types=1);

// Automated verification script for the Board Game Studio Rulebook Module
$container = require_once __DIR__ . '/../src/bootstrap.php';

use App\Application\Services\BgRulebookService;
use App\Application\Services\ProjectService;
use App\Domain\Entities\BgRulebook;
use App\Domain\Entities\BgGlossary;

echo "===========================================\n";
echo "RULEBOOK MODULE SELF-AUDIT & DI VERIFICATION\n";
echo "===========================================\n\n";

try {
    // 1. Verify DI wiring
    echo "1. Resolving BgRulebookService from DI Container... ";
    $rulebookService = $container->get(BgRulebookService::class);
    $projectService = $container->get(ProjectService::class);
    echo "SUCCESS!\n";

    // 2. Resolve or create a project context for testing
    echo "2. Finding project context... ";
    $projects = $projectService->getAllProjects();
    if (empty($projects)) {
        // Fallback to manual PDO insert of a temporary project to satisfy FKs if database is empty
        $db = $container->get(PDO::class);
        $db->exec("INSERT INTO projects (name, description) VALUES ('Temporary Integration Test Project', 'Auto-created')");
        $projectId = (int)$db->lastInsertId();
        $tempCreated = true;
        echo "CREATED temporary project ID $projectId... ";
    } else {
        $projectId = $projects[0]->getId();
        $tempCreated = false;
        echo "FOUND existing project ID $projectId... ";
    }
    echo "SUCCESS!\n";

    // Fetch system user for created_by constraint
    $db = $container->get(PDO::class);
    $userRow = $db->query("SELECT id FROM users LIMIT 1")->fetch();
    if (!$userRow) {
        throw new \Exception("No users found in database to link test constraints.");
    }
    $userId = (int)$userRow['id'];

    // 3. Test Rulebook CRUD Operations via Service
    echo "3. Creating integration test rulebook... ";
    $testContent = [
        [
            'type' => 'markdown',
            'text' => '## Game setup\nPlace [gold_coin] token next to the [[banish_zone]] boundary.'
        ]
    ];
    $rulebook = $rulebookService->createRulebook($projectId, 'Unit Test Rulebook', $testContent, $userId);
    echo "SUCCESS (ID: " . $rulebook->getId() . ")\n";

    echo "4. Retrieving created rulebook... ";
    $loaded = $rulebookService->getRulebookById($rulebook->getId());
    if (!$loaded || $loaded->getName() !== 'Unit Test Rulebook') {
        throw new \Exception("Rulebook mismatch or not loaded.");
    }
    if ($loaded->getContent()[0]['type'] !== 'markdown') {
        throw new \Exception("Rulebook content JSON parsing failed.");
    }
    echo "SUCCESS!\n";

    echo "5. Updating rulebook... ";
    $updatedContent = array_merge($testContent, [['type' => 'component_list']]);
    $updated = $rulebookService->updateRulebook($rulebook->getId(), 'Updated Unit Test Title', $updatedContent);
    if ($updated->getName() !== 'Updated Unit Test Title' || count($updated->getContent()) !== 2) {
        throw new \Exception("Rulebook update verify failed.");
    }
    echo "SUCCESS!\n";

    // 4. Test Glossary Operations via Service
    echo "6. Creating glossary term... ";
    $glossary = $rulebookService->saveGlossaryTerm($projectId, null, 'banish_zone', 'Banish Zone', 'Where cards are exiled.', $userId);
    echo "SUCCESS (ID: " . $glossary->getId() . ")\n";

    echo "7. Verifying glossary uniqueness constraint... ";
    try {
        $rulebookService->saveGlossaryTerm($projectId, null, 'banish_zone', 'Duplicate Key', 'Will fail', $userId);
        throw new \Exception("Duplicate glossary key constraint bypassed validation!");
    } catch (\App\Application\Exceptions\ValidationException $e) {
        echo "PASSED (Intercepted expected error: " . $e->getMessage() . ")\n";
    }

    // 4.5. Test Glossary CSV Import via Service
    echo "7.5. Importing glossary terms from CSV... ";
    $csvContent = "term_key,term_name,term_description\n" .
                  "exhaust,Exhaust,Tap the card.\n" .
                  "combat_zone,Combat Zone,Where combat happens.";
    $count = $rulebookService->importGlossaryFromCsv($projectId, $csvContent, $userId);
    if ($count !== 2) {
        throw new \Exception("Expected 2 imported terms, got $count");
    }
    
    // Find the imported terms to fetch IDs for cleanup
    $terms = $rulebookService->getGlossaryByProject($projectId);
    $exhaustFound = false;
    $combatFound = false;
    $exhaustId = 0;
    $combatId = 0;
    foreach ($terms as $t) {
        if ($t->getTermKey() === 'exhaust') {
            $exhaustFound = true;
            $exhaustId = (int)$t->getId();
        }
        if ($t->getTermKey() === 'combat_zone') {
            $combatFound = true;
            $combatId = (int)$t->getId();
        }
    }
    if (!$exhaustFound || !$combatFound) {
        throw new \Exception("CSV imported terms not found in project glossary list.");
    }
    echo "SUCCESS!\n";

    // 5. Clean up tests
    echo "8. Cleaning up database rulebook and glossary records... ";
    $rulebookService->deleteRulebook($rulebook->getId());
    $rulebookService->deleteGlossaryTerm($glossary->getId());
    $rulebookService->deleteGlossaryTerm($exhaustId);
    $rulebookService->deleteGlossaryTerm($combatId);
    if ($tempCreated) {
        $db->exec("DELETE FROM projects WHERE id = $projectId");
    }
    echo "SUCCESS!\n\n";

    echo "===========================================\n";
    echo "ALL AUDITS AND PERSISTENCE CHECKS PASSED!\n";
    echo "===========================================\n";

} catch (\Throwable $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
