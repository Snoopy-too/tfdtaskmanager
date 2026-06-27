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

    public function getProjectById(int $id): ?Project
    {
        return $this->projectRepository->findById($id);
    }

    public function getAllProjects(): array
    {
        return $this->projectRepository->findAll();
    }

    public function createProject(string $name, string $description): Project
    {
        $name = trim($name);
        $description = trim($description);

        if (empty($name)) {
            throw new ValidationException("Project name is required.");
        }

        $project = new Project(null, $name, $description);
        return $this->projectRepository->save($project);
    }

    public function updateProject(int $id, string $name, string $description): Project
    {
        $name = trim($name);
        $description = trim($description);

        if (empty($name)) {
            throw new ValidationException("Project name is required.");
        }

        $project = $this->projectRepository->findById($id);
        if (!$project) {
            throw new ValidationException("Project not found.");
        }

        $updatedProject = new Project($id, $name, $description, $project->getCreatedAt());
        return $this->projectRepository->save($updatedProject);
    }
}
