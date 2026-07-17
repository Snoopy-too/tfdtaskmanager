<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Entities\BgRulebook;
use App\Domain\Entities\BgGlossary;
use App\Domain\Repositories\BgRulebookRepositoryInterface;
use App\Domain\Repositories\BgGlossaryRepositoryInterface;
use App\Application\Exceptions\ValidationException;

class BgRulebookService
{
    private BgRulebookRepositoryInterface $rulebookRepo;
    private BgGlossaryRepositoryInterface $glossaryRepo;

    public function __construct(
        BgRulebookRepositoryInterface $rulebookRepo,
        BgGlossaryRepositoryInterface $glossaryRepo
    ) {
        $this->rulebookRepo = $rulebookRepo;
        $this->glossaryRepo = $glossaryRepo;
    }

    // --- Rulebooks ---

    public function getRulebooksByProject(int $projectId): array
    {
        return $this->rulebookRepo->findByProjectId($projectId);
    }

    public function getRulebookById(int $id): ?BgRulebook
    {
        return $this->rulebookRepo->findById($id);
    }

    public function createRulebook(int $projectId, string $name, array $content, int $userId): BgRulebook
    {
        $name = trim($name);
        if ($name === '') {
            throw new ValidationException("Rulebook name cannot be empty.");
        }

        $rulebook = new BgRulebook(
            null,
            $projectId,
            $name,
            $content,
            $userId
        );

        return $this->rulebookRepo->save($rulebook);
    }

    public function updateRulebook(int $id, string $name, array $content): BgRulebook
    {
        $rulebook = $this->rulebookRepo->findById($id);
        if (!$rulebook) {
            throw new ValidationException("Rulebook not found.");
        }

        $name = trim($name);
        if ($name === '') {
            throw new ValidationException("Rulebook name cannot be empty.");
        }

        $updated = new BgRulebook(
            $rulebook->getId(),
            $rulebook->getProjectId(),
            $name,
            $content,
            $rulebook->getCreatedBy(),
            $rulebook->getCreatedAt()
        );

        return $this->rulebookRepo->save($updated);
    }

    public function deleteRulebook(int $id): void
    {
        $this->rulebookRepo->delete($id);
    }

    // --- Glossary ---

    public function getGlossaryByProject(int $projectId): array
    {
        return $this->glossaryRepo->findByProjectId($projectId);
    }

    public function getGlossaryTermById(int $id): ?BgGlossary
    {
        return $this->glossaryRepo->findById($id);
    }

    public function saveGlossaryTerm(
        int $projectId,
        ?int $id,
        string $termKey,
        string $termName,
        string $termDescription,
        int $userId
    ): BgGlossary {
        $termKey = strtolower(trim($termKey));
        $termName = trim($termName);
        $termDescription = trim($termDescription);

        if ($termKey === '') {
            throw new ValidationException("Term key cannot be empty.");
        }
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $termKey)) {
            throw new ValidationException("Term key can only contain alphanumeric characters, underscores, or hyphens.");
        }
        if ($termName === '') {
            throw new ValidationException("Term name cannot be empty.");
        }
        if ($termDescription === '') {
            throw new ValidationException("Term description cannot be empty.");
        }

        // Check uniqueness of key in project
        $existing = $this->glossaryRepo->findByTermKey($projectId, $termKey);
        if ($existing && ($id === null || $existing->getId() !== $id)) {
            throw new ValidationException("A glossary term with the key '{$termKey}' already exists in this project.");
        }

        if ($id !== null) {
            $existingTerm = $this->glossaryRepo->findById($id);
            if (!$existingTerm) {
                throw new ValidationException("Glossary term not found.");
            }
            $glossary = new BgGlossary(
                $id,
                $projectId,
                $termKey,
                $termName,
                $termDescription,
                $existingTerm->getCreatedBy(),
                $existingTerm->getCreatedAt()
            );
        } else {
            $glossary = new BgGlossary(
                null,
                $projectId,
                $termKey,
                $termName,
                $termDescription,
                $userId
            );
        }

        return $this->glossaryRepo->save($glossary);
    }

    public function deleteGlossaryTerm(int $id): void
    {
        $this->glossaryRepo->delete($id);
    }

    public function importGlossaryFromCsv(int $projectId, string $csvContent, int $userId): int
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csvContent));
        if (empty($lines) || empty($lines[0])) {
            throw new ValidationException("CSV content is empty.");
        }

        // Determine delimiter by searching first line
        $firstLine = $lines[0];
        $delimiter = ',';
        if (strpos($firstLine, ';') !== false && strpos($firstLine, ',') === false) {
            $delimiter = ';';
        }

        // Parse header
        $headers = str_getcsv($firstLine, $delimiter);
        $keyIndex = 0;
        $nameIndex = 1;
        $descIndex = 2;

        $hasHeaders = false;
        foreach ($headers as $index => $header) {
            $header = strtolower(trim($header));
            if (in_array($header, ['key', 'term_key'])) {
                $keyIndex = $index;
                $hasHeaders = true;
            } elseif (in_array($header, ['name', 'term_name', 'term'])) {
                $nameIndex = $index;
                $hasHeaders = true;
            } elseif (in_array($header, ['description', 'term_description', 'definition', 'desc'])) {
                $descIndex = $index;
                $hasHeaders = true;
            }
        }

        $startIndex = $hasHeaders ? 1 : 0;
        $importedCount = 0;

        for ($i = $startIndex; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if ($line === '') {
                continue;
            }
            $rowValues = str_getcsv($line, $delimiter);
            
            $key = $rowValues[$keyIndex] ?? '';
            $name = $rowValues[$nameIndex] ?? '';
            $description = $rowValues[$descIndex] ?? '';

            // Clean values
            $key = strtolower(trim($key));
            $name = trim($name);
            $description = trim($description);

            if ($key === '' || $name === '' || $description === '') {
                continue;
            }

            if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $key)) {
                continue;
            }

            // Save term (upsert)
            $existing = $this->glossaryRepo->findByTermKey($projectId, $key);
            $id = $existing ? $existing->getId() : null;

            $this->saveGlossaryTerm($projectId, $id, $key, $name, $description, $userId);
            $importedCount++;
        }

        return $importedCount;
    }

    public function isRulebookLockedByOther(BgRulebook $rulebook, int $currentUserId): bool
    {
        if ($rulebook->getLockedByUserId() === null) {
            return false;
        }
        if ($rulebook->getLockedByUserId() === $currentUserId) {
            return false;
        }
        $lockedTime = strtotime($rulebook->getLockedAt() ?? '');
        if ($lockedTime === false) {
            return false;
        }
        return (time() - $lockedTime) < 60; // Lock is valid for 60 seconds
    }

    public function acquireOrRefreshLock(int $rulebookId, int $userId): bool
    {
        $rulebook = $this->rulebookRepo->findById($rulebookId);
        if (!$rulebook) {
            return false;
        }
        if ($this->isRulebookLockedByOther($rulebook, $userId)) {
            return false;
        }
        $this->rulebookRepo->updateLock($rulebookId, $userId, date('Y-m-d H:i:s'));
        return true;
    }

    public function releaseLock(int $rulebookId, int $userId): void
    {
        $rulebook = $this->rulebookRepo->findById($rulebookId);
        if ($rulebook && $rulebook->getLockedByUserId() === $userId) {
            $this->rulebookRepo->updateLock($rulebookId, null, null);
        }
    }
}
