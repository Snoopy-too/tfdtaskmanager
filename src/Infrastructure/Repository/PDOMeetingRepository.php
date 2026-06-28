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
        if ($meeting->getId() === null) {
            $stmt = $this->pdo->prepare("
                INSERT INTO meetings (title, scheduled_date, created_by)
                VALUES (:title, :scheduled_date, :created_by)
            ");
            $stmt->execute([
                'title' => $meeting->getTitle(),
                'scheduled_date' => $meeting->getScheduledDate(),
                'created_by' => $meeting->getCreatedBy()
            ]);
            $id = (int)$this->pdo->lastInsertId();
            return new Meeting(
                $id,
                $meeting->getTitle(),
                $meeting->getScheduledDate(),
                $meeting->getCreatedBy(),
                date('Y-m-d H:i:s')
            );
        } else {
            $stmt = $this->pdo->prepare("
                UPDATE meetings
                SET title = :title, scheduled_date = :scheduled_date
                WHERE id = :id
            ");
            $stmt->execute([
                'title' => $meeting->getTitle(),
                'scheduled_date' => $meeting->getScheduledDate(),
                'id' => $meeting->getId()
            ]);
            return $meeting;
        }
    }

    public function findAll(): array
    {
        // ponytail: Sort pending dates first, then scheduled dates in ascending order
        $stmt = $this->pdo->query("
            SELECT * FROM meetings 
            ORDER BY (scheduled_date IS NULL) DESC, scheduled_date ASC, created_at DESC
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
        return new Meeting(
            (int)$row['id'],
            $row['title'],
            $row['scheduled_date'] ? (string)$row['scheduled_date'] : null,
            (int)$row['created_by'],
            $row['created_at']
        );
    }
}
