<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Entities\MeetingTopic;
use App\Domain\Repositories\MeetingTopicRepositoryInterface;
use PDO;

class PDOMeetingTopicRepository implements MeetingTopicRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(int $id): ?MeetingTopic
    {
        $stmt = $this->pdo->prepare("SELECT * FROM meeting_topics WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        return $this->mapRowToEntity($row);
    }

    public function save(MeetingTopic $topic): MeetingTopic
    {
        if ($topic->getId() === null) {
            $stmt = $this->pdo->prepare("
                INSERT INTO meeting_topics (meeting_id, user_id, title)
                VALUES (:meeting_id, :user_id, :title)
            ");
            $stmt->execute([
                'meeting_id' => $topic->getMeetingId(),
                'user_id' => $topic->getUserId(),
                'title' => $topic->getTitle()
            ]);
            $id = (int)$this->pdo->lastInsertId();
            return new MeetingTopic(
                $id,
                $topic->getMeetingId(),
                $topic->getUserId(),
                $topic->getTitle(),
                date('Y-m-d H:i:s')
            );
        } else {
            $stmt = $this->pdo->prepare("
                UPDATE meeting_topics
                SET title = :title
                WHERE id = :id
            ");
            $stmt->execute([
                'title' => $topic->getTitle(),
                'id' => $topic->getId()
            ]);
            return $topic;
        }
    }

    public function findByMeetingId(int $meetingId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM meeting_topics 
            WHERE meeting_id = :meeting_id 
            ORDER BY created_at ASC
        ");
        $stmt->execute(['meeting_id' => $meetingId]);
        $rows = $stmt->fetchAll();
        $topics = [];
        foreach ($rows as $row) {
            $topics[] = $this->mapRowToEntity($row);
        }
        return $topics;
    }

    private function mapRowToEntity(array $row): MeetingTopic
    {
        return new MeetingTopic(
            (int)$row['id'],
            (int)$row['meeting_id'],
            (int)$row['user_id'],
            $row['title'],
            $row['created_at']
        );
    }
}
