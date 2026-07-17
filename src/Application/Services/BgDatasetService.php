<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Entities\BgDataset;
use App\Domain\Repositories\BgDatasetRepositoryInterface;
use App\Application\Exceptions\ValidationException;

class BgDatasetService
{
    private BgDatasetRepositoryInterface $datasetRepository;

    public function __construct(BgDatasetRepositoryInterface $datasetRepository)
    {
        $this->datasetRepository = $datasetRepository;
    }

    public function getDatasetsByProject(int $projectId): array
    {
        return $this->datasetRepository->findByProjectId($projectId);
    }

    public function getDatasetById(int $id): ?BgDataset
    {
        return $this->datasetRepository->findById($id);
    }

    public function createDataset(int $projectId, string $name, array $columnMap, array $rowData, int $createdByUserId): BgDataset
    {
        $name = trim($name);
        if (empty($name)) {
            throw new ValidationException("Dataset name is required.");
        }
        if (empty($columnMap)) {
            throw new ValidationException("Dataset must have at least one column.");
        }

        $dataset = new BgDataset(null, $projectId, $name, $columnMap, $rowData, $createdByUserId);
        return $this->datasetRepository->save($dataset);
    }

    public function updateDataset(int $id, string $name, array $columnMap, array $rowData): BgDataset
    {
        $name = trim($name);
        if (empty($name)) {
            throw new ValidationException("Dataset name is required.");
        }
        if (empty($columnMap)) {
            throw new ValidationException("Dataset must have at least one column.");
        }

        $dataset = $this->datasetRepository->findById($id);
        if (!$dataset) {
            throw new ValidationException("Dataset not found.");
        }

        $updated = new BgDataset(
            $id,
            $dataset->getProjectId(),
            $name,
            $columnMap,
            $rowData,
            $dataset->getCreatedBy(),
            $dataset->getCreatedAt()
        );
        $this->datasetRepository->update($updated);
        return $updated;
    }

    public function deleteDataset(int $id): void
    {
        $this->datasetRepository->delete($id);
    }

    /**
     * Parses raw CSV content into columnMap and rowData.
     * Supports basic comma or semicolon delimited CSV files.
     */
    public function parseCsvContent(string $csvContent): array
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
        $columnMap = [];
        foreach ($headers as $index => $header) {
            $headerName = trim($header);
            if ($headerName === '') {
                $headerName = 'Column_' . ($index + 1);
            }
            // Sanitize column name for simple variable usage {{ColumnName}}
            $headerName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $headerName);
            $columnMap[] = $headerName;
        }

        $rowData = [];
        for ($i = 1; $i < count($lines); $i++) {
            if (trim($lines[$i]) === '') {
                continue;
            }
            $rowValues = str_getcsv($lines[$i], $delimiter);
            
            $rowObject = [];
            foreach ($columnMap as $index => $colName) {
                $rowObject[$colName] = isset($rowValues[$index]) ? trim($rowValues[$index]) : '';
            }
            $rowData[] = $rowObject;
        }

        return [
            'columnMap' => $columnMap,
            'rowData' => $rowData
        ];
    }

    public function isDatasetLockedByOther(BgDataset $dataset, int $currentUserId): bool
    {
        if ($dataset->getLockedByUserId() === null) {
            return false;
        }
        if ($dataset->getLockedByUserId() === $currentUserId) {
            return false;
        }
        $lockedTime = strtotime($dataset->getLockedAt() ?? '');
        if ($lockedTime === false) {
            return false;
        }
        return (time() - $lockedTime) < 60; // Lock is valid for 60 seconds
    }

    public function acquireOrRefreshLock(int $datasetId, int $userId): bool
    {
        $dataset = $this->datasetRepository->findById($datasetId);
        if (!$dataset) {
            return false;
        }
        if ($this->isDatasetLockedByOther($dataset, $userId)) {
            return false;
        }
        $this->datasetRepository->updateLock($datasetId, $userId, date('Y-m-d H:i:s'));
        return true;
    }

    public function releaseLock(int $datasetId, int $userId): void
    {
        $dataset = $this->datasetRepository->findById($datasetId);
        if ($dataset && $dataset->getLockedByUserId() === $userId) {
            $this->datasetRepository->updateLock($datasetId, null, null);
        }
    }
}
