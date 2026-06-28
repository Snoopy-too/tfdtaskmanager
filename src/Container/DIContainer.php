<?php
declare(strict_types=1);

namespace App\Container;

use App\Infrastructure\Database\Connection;
use App\Infrastructure\Repository\PDOUserRepository;
use App\Infrastructure\Repository\PDOProjectRepository;
use App\Infrastructure\Repository\PDOTaskRepository;
use App\Infrastructure\Repository\PDOCommentRepository;
use App\Infrastructure\Repository\PDOTaskHistoryRepository;
use App\Infrastructure\Repository\PDOMeetingRepository;
use App\Infrastructure\Repository\PDOMeetingTopicRepository;
use App\Application\Services\AuthService;
use App\Application\Services\UserService;
use App\Application\Services\ProjectService;
use App\Application\Services\TaskService;
use App\Application\Services\MeetingService;
use PDO;

class DIContainer
{
    private array $services = [];
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->bootstrap();
    }

    private function bootstrap(): void
    {
        $this->services[PDO::class] = function() {
            $connection = new Connection($this->config['db']);
            return $connection->getPDO();
        };

        $this->services[PDOUserRepository::class] = function() {
            return new PDOUserRepository($this->get(PDO::class));
        };

        $this->services[PDOProjectRepository::class] = function() {
            return new PDOProjectRepository($this->get(PDO::class));
        };

        $this->services[PDOTaskRepository::class] = function() {
            return new PDOTaskRepository($this->get(PDO::class));
        };

        $this->services[PDOCommentRepository::class] = function() {
            return new PDOCommentRepository($this->get(PDO::class));
        };

        $this->services[PDOTaskHistoryRepository::class] = function() {
            return new PDOTaskHistoryRepository($this->get(PDO::class));
        };

        $this->services[AuthService::class] = function() {
            return new AuthService($this->get(PDOUserRepository::class));
        };

        $this->services[UserService::class] = function() {
            return new UserService($this->get(PDOUserRepository::class));
        };

        $this->services[ProjectService::class] = function() {
            return new ProjectService($this->get(PDOProjectRepository::class));
        };

        $this->services[TaskService::class] = function() {
            return new TaskService(
                $this->get(PDOTaskRepository::class),
                $this->get(PDOTaskHistoryRepository::class),
                $this->get(PDOCommentRepository::class)
            );
        };

        $this->services[PDOMeetingRepository::class] = function() {
            return new PDOMeetingRepository($this->get(PDO::class));
        };

        $this->services[PDOMeetingTopicRepository::class] = function() {
            return new PDOMeetingTopicRepository($this->get(PDO::class));
        };

        $this->services[MeetingService::class] = function() {
            return new MeetingService(
                $this->get(PDOMeetingRepository::class),
                $this->get(PDOMeetingTopicRepository::class)
            );
        };
    }

    public function get(string $class)
    {
        if (!isset($this->services[$class])) {
            throw new \InvalidArgumentException("Service not found: " . $class);
        }

        if ($this->services[$class] instanceof \Closure) {
            $this->services[$class] = $this->services[$class]();
        }

        return $this->services[$class];
    }
}
