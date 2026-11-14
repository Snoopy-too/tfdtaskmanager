<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Entities\Project;
use App\Domain\Repositories\ProjectRepositoryInterface;
use App\Application\Exceptions\ValidationException;

class ProjectService
{
    private ProjectRepositoryInterface $projectRepository;

    public function __construct(ProjectRepositoryInterface $projectRepository)
    {
        $this->projectRepository = $projectRepository;
    }

    public function getProjectById(int $id, ?int $forUserId = null): ?Project
    {
        $project = $this->projectRepository->findById($id);
        if (!$project) {
            return null;
        }
        if ($forUserId !== null && !$project->isAccessibleBy($forUserId)) {
            return null;
        }
        return $project;
    }

    public function getAllProjects(?int $forUserId = null): array
    {
        return $this->projectRepository->findAll($forUserId);
    }

    public function createProject(
        string $name,
        string $description,
        bool $isPrivate = false,
        ?int $createdBy = null,
        bool $isBoardGame = false,
        string $type = 'standard'
    ): Project {
        $name = trim($name);
        $description = trim($description);

        if (empty($name)) {
            throw new ValidationException("Project name is required.");
        }

        if ($isBoardGame || $type === 'boardgame') {
            $isBoardGame = true;
            $type = 'boardgame';
        }

        $project = new Project(null, $name, $description, '', $isPrivate, $createdBy, $isBoardGame, $type);
        return $this->projectRepository->save($project);
    }

    public function updateProject(
        int $id,
        string $name,
        string $description,
        bool $isPrivate = false,
        ?int $userId = null,
        bool $isBoardGame = false,
        string $type = 'standard'
    ): Project {
        $name = trim($name);
        $description = trim($description);

        if (empty($name)) {
            throw new ValidationException("Project name is required.");
        }

        $project = $this->projectRepository->findById($id);
        if (!$project) {
            throw new ValidationException("Project not found.");
        }

        if ($userId !== null && !$project->isAccessibleBy($userId)) {
            throw new ValidationException("Access denied: You cannot edit this project.");
        }

        $createdBy = $project->getCreatedBy();
        if ($createdBy === null && $userId !== null) {
            $createdBy = $userId;
        }

        if ($isBoardGame || $type === 'boardgame') {
            $isBoardGame = true;
            $type = 'boardgame';
        }

        $updatedProject = new Project($id, $name, $description, $project->getCreatedAt(), $isPrivate, $createdBy, $isBoardGame, $type);
        return $this->projectRepository->save($updatedProject);
    }
}
