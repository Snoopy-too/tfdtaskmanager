<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Entities\Meeting;
use App\Domain\Entities\MeetingTopic;
use App\Domain\Repositories\MeetingRepositoryInterface;
use App\Domain\Repositories\MeetingTopicRepositoryInterface;
use App\Application\Exceptions\ValidationException;

class MeetingService
{
    private MeetingRepositoryInterface $meetingRepository;
    private MeetingTopicRepositoryInterface $topicRepository;

    public function __construct(
        MeetingRepositoryInterface $meetingRepository,
        MeetingTopicRepositoryInterface $topicRepository
    ) {
        $this->meetingRepository = $meetingRepository;
        $this->topicRepository = $topicRepository;
    }

    public function getAllMeetings(): array
    {
        return $this->meetingRepository->findAll();
    }

    public function getMeetingById(int $id): ?Meeting
    {
        return $this->meetingRepository->findById($id);
    }

    public function createMeeting(string $title, ?string $scheduledDate, int $createdByUserId): Meeting
    {
        $title = trim($title);
        $scheduledDate = $scheduledDate ? trim($scheduledDate) : null;
        if ($scheduledDate === '') {
            $scheduledDate = null;
        }

        if (empty($title)) {
            throw new ValidationException("Meeting title is required.");
        }

        if ($scheduledDate !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $scheduledDate)) {
            throw new ValidationException("Scheduled date must be in YYYY-MM-DD format.");
        }

        // ponytail: Keep domain validation clean and minimal
        $meeting = new Meeting(null, $title, $scheduledDate, $createdByUserId);
        return $this->meetingRepository->save($meeting);
    }

    public function updateMeeting(int $id, string $title, ?string $scheduledDate): Meeting
    {
        $title = trim($title);
        $scheduledDate = $scheduledDate ? trim($scheduledDate) : null;
        if ($scheduledDate === '') {
            $scheduledDate = null;
        }

        if (empty($title)) {
            throw new ValidationException("Meeting title is required.");
        }

        if ($scheduledDate !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $scheduledDate)) {
            throw new ValidationException("Scheduled date must be in YYYY-MM-DD format.");
        }

        $meeting = $this->meetingRepository->findById($id);
        if (!$meeting) {
            throw new ValidationException("Meeting not found.");
        }

        $updatedMeeting = new Meeting(
            $id,
            $title,
            $scheduledDate,
            $meeting->getCreatedBy(),
            $meeting->getCreatedAt()
        );
        return $this->meetingRepository->save($updatedMeeting);
    }

    public function addTopic(int $meetingId, int $userId, string $topicTitle): MeetingTopic
    {
        $topicTitle = trim($topicTitle);
        if (empty($topicTitle)) {
            throw new ValidationException("Topic title cannot be empty.");
        }

        $meeting = $this->meetingRepository->findById($meetingId);
        if (!$meeting) {
            throw new ValidationException("Meeting not found.");
        }

        $topic = new MeetingTopic(null, $meetingId, $userId, $topicTitle);
        return $this->topicRepository->save($topic);
    }

    public function getTopicsForMeeting(int $meetingId): array
    {
        return $this->topicRepository->findByMeetingId($meetingId);
    }

    public function getTopicById(int $id): ?MeetingTopic
    {
        return $this->topicRepository->findById($id);
    }

    public function updateTopic(int $id, int $userId, string $newTitle): MeetingTopic
    {
        $newTitle = trim($newTitle);
        if (empty($newTitle)) {
            throw new ValidationException("Topic title cannot be empty.");
        }

        $topic = $this->topicRepository->findById($id);
        if (!$topic) {
            throw new ValidationException("Topic not found.");
        }

        if ($topic->getUserId() !== $userId) {
            throw new ValidationException("Access Denied: You can only edit topics you suggested.");
        }

        $updatedTopic = new MeetingTopic(
            $id,
            $topic->getMeetingId(),
            $topic->getUserId(),
            $newTitle,
            $topic->getCreatedAt()
        );
        return $this->topicRepository->save($updatedTopic);
    }
}
