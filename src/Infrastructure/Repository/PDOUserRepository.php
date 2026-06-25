<?php
declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Entities\User;
use App\Domain\Repositories\UserRepositoryInterface;
use PDO;

class PDOUserRepository implements UserRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(int $id): ?User
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) return null;
        return $this->mapRowToEntity($row);
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        if (!$row) return null;
        return $this->mapRowToEntity($row);
    }

    public function save(User $user): User
    {
        if ($user->getId() === null) {
            $stmt = $this->pdo->prepare("
                INSERT INTO users (role, name, email, password_hash)
                VALUES (:role, :name, :email, :password_hash)
            ");
            $stmt->execute([
                'role' => $user->getRole(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'password_hash' => $user->getPasswordHash()
            ]);
            $id = (int)$this->pdo->lastInsertId();
            return new User(
                $id,
                $user->getRole(),
                $user->getName(),
                $user->getEmail(),
                $user->getPasswordHash(),
                date('Y-m-d H:i:s')
            );
        } else {
            $stmt = $this->pdo->prepare("
                UPDATE users
                SET role = :role, name = :name, email = :email, password_hash = :password_hash
                WHERE id = :id
            ");
            $stmt->execute([
                'role' => $user->getRole(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'password_hash' => $user->getPasswordHash(),
                'id' => $user->getId()
            ]);
            return $user;
        }
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM users ORDER BY name ASC");
        $rows = $stmt->fetchAll();
        $users = [];
        foreach ($rows as $row) {
            $users[] = $this->mapRowToEntity($row);
        }
        return $users;
    }

    public function countMembers(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM users WHERE role = 'member'");
        return (int)$stmt->fetchColumn();
    }

    private function mapRowToEntity(array $row): User
    {
        return new User(
            (int)$row['id'],
            $row['role'],
            $row['name'],
            $row['email'],
            $row['password_hash'],
            $row['created_at']
        );
    }
}
