<?php
declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Entities\MeetingTopic;

interface MeetingTopicRepositoryInterface
{
    public function save(MeetingTopic $topic): MeetingTopic;
    
    /**
     * @return MeetingTopic[]
     */
    public function findByMeetingId(int $meetingId): array;
}
