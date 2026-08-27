<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Entities\Task;
use App\Domain\Entities\TaskHistory;
use App\Domain\Entities\Comment;
use App\Domain\Repositories\TaskRepositoryInterface;
use App\Domain\Repositories\TaskHistoryRepositoryInterface;
use App\Domain\Repositories\CommentRepositoryInterface;
use App\Domain\Repositories\ProjectRepositoryInterface;
use App\Application\Exceptions\ValidationException;

class TaskService
{
    private TaskRepositoryInterface $taskRepository;
    private TaskHistoryRepositoryInterface $historyRepository;
    private CommentRepositoryInterface $commentRepository;
    private ?ProjectRepositoryInterface $projectRepository;

    public function __construct(
        TaskRepositoryInterface $taskRepository,
        TaskHistoryRepositoryInterface $historyRepository,
        CommentRepositoryInterface $commentRepository,
        ?ProjectRepositoryInterface $projectRepository = null
    ) {
        $this->taskRepository = $taskRepository;
        $this->historyRepository = $historyRepository;
        $this->commentRepository = $commentRepository;
        $this->projectRepository = $projectRepository;
    }

    public function getTaskById(int $id, ?int $forUserId = null): ?Task
    {
        $task = $this->taskRepository->findById($id);
        if (!$task) {
            return null;
        }

        if ($forUserId !== null && $forUserId > 0 && $this->projectRepository !== null) {
            $project = $this->projectRepository->findById($task->getProjectId());
            if ($project && !$project->isAccessibleBy($forUserId)) {
                return null;
            }
        }

        return $task;
    }

    public function getTasksFiltered(?int $projectId, ?string $status, bool $onlyBugs = false, ?string $sortBy = null, ?bool $isArchived = false, ?int $forUserId = null): array
    {
        return $this->taskRepository->findByFilters($projectId, $status, $onlyBugs, $sortBy, $isArchived, $forUserId);
    }

    public function createTask(int $projectId, string $title, string $details, ?string $deadline, int $creatorId, bool $isBug = false): Task
    {
        $title = trim($title);
        $details = trim($details);
        $deadline = $deadline ? trim($deadline) : null;

        if (empty($title)) {
            throw new ValidationException("Task title is required.");
        }

        if ($projectId <= 0) {
            throw new ValidationException("Valid project selection is required.");
        }

        if ($this->projectRepository !== null) {
            $project = $this->projectRepository->findById($projectId);
            if (!$project || !$project->isAccessibleBy($creatorId)) {
                throw new ValidationException("Selected project is not available.");
            }
        }

        if ($deadline !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline)) {
            throw new ValidationException("Deadline must be in YYYY-MM-DD format.");
        }

        $task = new Task(null, $projectId, $title, $details, 'To Do', $deadline, $creatorId, null, null, '', $isBug);
        $savedTask = $this->taskRepository->save($task);

        $history = new TaskHistory(null, $savedTask->getId(), $creatorId, 'created', 'Task created.');
        $this->historyRepository->save($history);

        return $savedTask;
    }

    public function checkoutTask(int $taskId, int $userId, int $expectedVersion): Task
    {
        if ($userId <= 0) {
            throw new ValidationException("Invalid session or user. Please log in again.");
        }

        $task = $this->getTaskById($taskId, $userId);
        if (!$task) {
            throw new ValidationException("Task not found.");
        }

        try {
            $task = new Task(
                $task->getId(),
                $task->getProjectId(),
                $task->getTitle(),
                $task->getDetails(),
                $task->getStatus(),
                $task->getDeadline(),
                $task->getCreatedBy(),
                $task->getAssignedTo(),
                $task->getCheckedOutAt(),
                $task->getCreatedAt(),
                $task->isBug(),
                $task->isArchived(),
                $expectedVersion
            );
            $task->checkout($userId);
            $savedTask = $this->taskRepository->save($task);

            $history = new TaskHistory(null, $taskId, $userId, 'checked_out', 'Task checked out.');
            $this->historyRepository->save($history);

            return $savedTask;
        } catch (\LogicException $e) {
            throw new ValidationException($e->getMessage());
        }
    }

    public function checkinTask(int $taskId, int $userId, string $reason, int $expectedVersion): Task
    {
        $task = $this->getTaskById($taskId, $userId);
        if (!$task) {
            throw new ValidationException("Task not found.");
        }

        $reason = trim($reason);
        if (empty($reason)) {
            throw new ValidationException("Reason for check-in is mandatory.");
        }

        if ($task->getAssignedTo() !== $userId) {
            throw new ValidationException("You can only check in a task that is currently assigned to you.");
        }

        try {
            $task = new Task(
                $task->getId(),
                $task->getProjectId(),
                $task->getTitle(),
                $task->getDetails(),
                $task->getStatus(),
                $task->getDeadline(),
                $task->getCreatedBy(),
                $task->getAssignedTo(),
                $task->getCheckedOutAt(),
                $task->getCreatedAt(),
                $task->isBug(),
                $task->isArchived(),
                $expectedVersion
            );
            $task->checkin();
            $savedTask = $this->taskRepository->save($task);

            $history = new TaskHistory(null, $taskId, $userId, 'checked_in', $reason);
            $this->historyRepository->save($history);

            return $savedTask;
        } catch (\LogicException $e) {
            throw new ValidationException($e->getMessage());
        }
    }

    public function completeTask(int $taskId, int $userId, int $expectedVersion): Task
    {
        $task = $this->getTaskById($taskId, $userId);
        if (!$task) {
            throw new ValidationException("Task not found.");
        }

        if ($task->getStatus() === 'In Progress' && $task->getAssignedTo() !== $userId) {
             throw new ValidationException("This task is checked out by another team member.");
        }

        try {
            $task = new Task(
                $task->getId(),
                $task->getProjectId(),
                $task->getTitle(),
                $task->getDetails(),
                $task->getStatus(),
                $task->getDeadline(),
                $task->getCreatedBy(),
                $task->getAssignedTo(),
                $task->getCheckedOutAt(),
                $task->getCreatedAt(),
                $task->isBug(),
                $task->isArchived(),
                $expectedVersion
            );
            $task->complete();
            $savedTask = $this->taskRepository->save($task);

            $history = new TaskHistory(null, $taskId, $userId, 'completed', 'Task marked as completed.');
            $this->historyRepository->save($history);

            return $savedTask;
        } catch (\LogicException $e) {
            throw new ValidationException($e->getMessage());
        }
    }

    public function addComment(int $taskId, int $userId, string $message): Comment
    {
        $message = trim($message);
        if (empty($message)) {
            throw new ValidationException("Comment message cannot be empty.");
        }

        $task = $this->getTaskById($taskId, $userId);
        if (!$task) {
            throw new ValidationException("Task not found.");
        }

        $comment = new Comment(null, $taskId, $userId, $message);
        return $this->commentRepository->save($comment);
    }

    public function editComment(int $commentId, int $userId, string $message): Comment
    {
        $message = trim($message);
        if (empty($message)) {
            throw new ValidationException("Comment message cannot be empty.");
        }

        $comment = $this->commentRepository->findById($commentId);
        if (!$comment) {
            throw new ValidationException("Comment not found.");
        }

        if ($comment->getUserId() !== $userId) {
            throw new ValidationException("You can only edit your own comments.");
        }

        $task = $this->getTaskById($comment->getTaskId(), $userId);
        if (!$task) {
            throw new ValidationException("Task not found.");
        }

        $updatedComment = new Comment(
            $comment->getId(),
            $comment->getTaskId(),
            $comment->getUserId(),
            $message,
            $comment->getCreatedAt()
        );

        return $this->commentRepository->save($updatedComment);
    }

    public function updateTask(
        int $taskId,
        int $projectId,
        string $title,
        string $details,
        ?string $deadline,
        string $status,
        bool $isBug,
        int $updaterId,
        int $expectedVersion
    ): Task {
        $task = $this->getTaskById($taskId, $updaterId);
        if (!$task) {
            throw new ValidationException("Task not found.");
        }

        $title = trim($title);
        $details = trim($details);
        $deadline = $deadline ? trim($deadline) : null;
        $status = trim($status);

        if (empty($title)) {
            throw new ValidationException("Task title is required.");
        }

        if ($projectId <= 0) {
            throw new ValidationException("Valid project selection is required.");
        }

        if ($this->projectRepository !== null) {
            $targetProject = $this->projectRepository->findById($projectId);
            if (!$targetProject || !$targetProject->isAccessibleBy($updaterId)) {
                throw new ValidationException("Selected project is not available.");
            }
        }

        if ($deadline !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline)) {
            throw new ValidationException("Deadline must be in YYYY-MM-DD format.");
        }

        if (!in_array($status, ['To Do', 'In Progress', 'Done'])) {
            throw new ValidationException("Invalid status selected.");
        }

        // Detect changes to construct an audit trail note
        $changes = [];
        if ($task->getTitle() !== $title) $changes[] = "title";
        if ($task->getProjectId() !== $projectId) $changes[] = "project";
        if ($task->getDetails() !== $details) $changes[] = "details";
        if ($task->getDeadline() !== $deadline) $changes[] = "deadline";
        if ($task->getStatus() !== $status) $changes[] = "status";
        if ($task->isBug() !== $isBug) $changes[] = "type";

        // Create updated entity copy
        $updatedTask = new Task(
            $task->getId(),
            $projectId,
            $title,
            $details,
            $status,
            $deadline,
            $task->getCreatedBy(),
            $task->getAssignedTo(),
            $task->getCheckedOutAt(),
            $task->getCreatedAt(),
            $isBug,
            $task->isArchived(),
            $expectedVersion
        );

        $savedTask = $this->taskRepository->save($updatedTask);

        // If something changed, write a history entry
        if (!empty($changes)) {
            $note = "Task fields updated: " . implode(', ', $changes) . ".";
            $history = new TaskHistory(null, $taskId, $updaterId, 'updated', $note);
            $this->historyRepository->save($history);
        }

        return $savedTask;
    }

    public function deleteTask(int $taskId, ?int $userId = null): void
    {
        $task = $this->getTaskById($taskId, $userId);
        if (!$task) {
            throw new ValidationException("Task not found.");
        }
        $this->taskRepository->delete($taskId);
    }

    public function getTaskComments(int $taskId, ?int $forUserId = null): array
    {
        if ($forUserId !== null) {
            $task = $this->getTaskById($taskId, $forUserId);
            if (!$task) {
                return [];
            }
        }
        return $this->commentRepository->findByTaskId($taskId);
    }

    public function getTaskHistory(int $taskId, ?int $forUserId = null): array
    {
        if ($forUserId !== null) {
            $task = $this->getTaskById($taskId, $forUserId);
            if (!$task) {
                return [];
            }
        }
        return $this->historyRepository->findByTaskId($taskId);
    }

    public function archiveTask(int $taskId, int $userId, int $expectedVersion = 0): Task
    {
        $task = $this->getTaskById($taskId, $userId);
        if (!$task) {
            throw new ValidationException("Task not found.");
        }

        try {
            $updatedTask = new Task(
                $task->getId(),
                $task->getProjectId(),
                $task->getTitle(),
                $task->getDetails(),
                $task->getStatus(),
                $task->getDeadline(),
                $task->getCreatedBy(),
                $task->getAssignedTo(),
                $task->getCheckedOutAt(),
                $task->getCreatedAt(),
                $task->isBug(),
                $task->isArchived(),
                $expectedVersion > 0 ? $expectedVersion : $task->getVersion()
            );
            $updatedTask->archive();
            $savedTask = $this->taskRepository->save($updatedTask);

            $history = new TaskHistory(null, $taskId, $userId, 'archived', 'Task moved to archive.');
            $this->historyRepository->save($history);

            return $savedTask;
        } catch (\LogicException $e) {
            throw new ValidationException($e->getMessage());
        }
    }

    public function unarchiveTask(int $taskId, int $userId, int $expectedVersion = 0): Task
    {
        $task = $this->getTaskById($taskId, $userId);
        if (!$task) {
            throw new ValidationException("Task not found.");
        }

        try {
            $updatedTask = new Task(
                $task->getId(),
                $task->getProjectId(),
                $task->getTitle(),
                $task->getDetails(),
                $task->getStatus(),
                $task->getDeadline(),
                $task->getCreatedBy(),
                $task->getAssignedTo(),
                $task->getCheckedOutAt(),
                $task->getCreatedAt(),
                $task->isBug(),
                $task->isArchived(),
                $expectedVersion > 0 ? $expectedVersion : $task->getVersion()
            );
            $updatedTask->unarchive();
            $savedTask = $this->taskRepository->save($updatedTask);

            $history = new TaskHistory(null, $taskId, $userId, 'unarchived', 'Task restored from archive.');
            $this->historyRepository->save($history);

            return $savedTask;
        } catch (\LogicException $e) {
            throw new ValidationException($e->getMessage());
        }
    }
}
