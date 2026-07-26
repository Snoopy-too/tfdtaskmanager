<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Entities\Meeting;
use App\Domain\Repositories\MeetingRepositoryInterface;
use PDO;

class PDOMeetingRepository implements MeetingRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(int $id): ?Meeting
    {
        $this->ensureMeetingColumns();
        $stmt = $this->pdo->prepare("SELECT * FROM meetings WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        return $this->mapRowToEntity($row);
    }

    public function save(Meeting $meeting): Meeting
    {
        $this->ensureMeetingColumns();
        if ($meeting->getId() === null) {
            $stmt = $this->pdo->prepare("
                INSERT INTO meetings (title, scheduled_date, status, notes, created_by)
                VALUES (:title, :scheduled_date, :status, :notes, :created_by)
            ");
            $stmt->execute([
                'title' => $meeting->getTitle(),
                'scheduled_date' => $meeting->getScheduledDate(),
                'status' => $meeting->getStatus(),
                'notes' => $meeting->getNotes(),
                'created_by' => $meeting->getCreatedBy()
            ]);
            $id = (int)$this->pdo->lastInsertId();
            return new Meeting(
                $id,
                $meeting->getTitle(),
                $meeting->getScheduledDate(),
                $meeting->getCreatedBy(),
                date('Y-m-d H:i:s'),
                $meeting->getStatus(),
                $meeting->getNotes()
            );
        } else {
            $stmt = $this->pdo->prepare("
                UPDATE meetings
                SET title = :title, scheduled_date = :scheduled_date, status = :status, notes = :notes
                WHERE id = :id
            ");
            $stmt->execute([
                'title' => $meeting->getTitle(),
                'scheduled_date' => $meeting->getScheduledDate(),
                'status' => $meeting->getStatus(),
                'notes' => $meeting->getNotes(),
                'id' => $meeting->getId()
            ]);
            return $meeting;
        }
    }

    public function findAll(): array
    {
        $this->ensureMeetingColumns();
        // ponytail: Sort pending dates first, then scheduled dates in ascending order, finished last
        $stmt = $this->pdo->query("
            SELECT * FROM meetings 
            ORDER BY (status = 'Finished') ASC, (scheduled_date IS NULL) DESC, scheduled_date ASC, created_at DESC
        ");
        $rows = $stmt->fetchAll();
        $meetings = [];
        foreach ($rows as $row) {
            $meetings[] = $this->mapRowToEntity($row);
        }
        return $meetings;
    }

    private function mapRowToEntity(array $row): Meeting
    {
        $status = $row['status'] ?? ($row['scheduled_date'] ? 'Scheduled' : 'Pending');
        return new Meeting(
            (int)$row['id'],
            $row['title'],
            $row['scheduled_date'] ? (string)$row['scheduled_date'] : null,
            (int)$row['created_by'],
            $row['created_at'],
            $status,
            $row['notes'] ?? null
        );
    }

    private function ensureMeetingColumns(): void
    {
        static $checked = false;
        if ($checked) return;
        try {
            $cols = $this->pdo->query("SHOW COLUMNS FROM `meetings` LIKE 'status'")->fetchAll();
            if (empty($cols)) {
                $this->pdo->exec("ALTER TABLE `meetings` ADD COLUMN `status` ENUM('Pending', 'Scheduled', 'Finished') NOT NULL DEFAULT 'Pending' AFTER `scheduled_date`");
            }
            $noteCols = $this->pdo->query("SHOW COLUMNS FROM `meetings` LIKE 'notes'")->fetchAll();
            if (empty($noteCols)) {
                $this->pdo->exec("ALTER TABLE `meetings` ADD COLUMN `notes` TEXT DEFAULT NULL AFTER `status`");
            }
        } catch (\Throwable $e) {
            // Ignore if already exists or permission error
        }
        $checked = true;
    }
}
