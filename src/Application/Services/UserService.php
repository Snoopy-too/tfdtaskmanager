<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Entities\User;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Application\Exceptions\ValidationException;

class UserService
{
    private UserRepositoryInterface $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function getUserById(int $id): ?User
    {
        return $this->userRepository->findById($id);
    }

    public function getAllUsers(): array
    {
        return $this->userRepository->findAll();
    }

    public function createUser(string $name, string $email, string $password, string $role): User
    {
        $name = trim($name);
        $email = trim($email);
        $role = trim($role);

        if (empty($name) || empty($email) || empty($password) || empty($role)) {
            throw new ValidationException("All fields are required.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException("Invalid email format.");
        }

        if ($role !== 'super_admin' && $role !== 'member') {
            throw new ValidationException("Invalid role selected.");
        }

        if ($role === 'member' && $this->userRepository->countMembers() >= 4) {
            throw new ValidationException("Limit reached: The team can have a maximum of 4 Team Members.");
        }

        $existingUser = $this->userRepository->findByEmail($email);
        if ($existingUser) {
            throw new ValidationException("A user with this email already exists.");
        }

        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        $hash = password_hash($password, $algo);
        $user = new User(null, $role, $name, $email, $hash);

        return $this->userRepository->save($user);
    }

    public function updateUser(int $id, string $name, string $email, ?string $password, string $role): User
    {
        $user = $this->userRepository->findById($id);
        if (!$user) {
            throw new ValidationException("User not found.");
        }

        $name = trim($name);
        $email = trim($email);
        $role = trim($role);

        if (empty($name) || empty($email) || empty($role)) {
            throw new ValidationException("Name, email, and role are required.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException("Invalid email format.");
        }

        if ($role !== 'super_admin' && $role !== 'member') {
            throw new ValidationException("Invalid role selected.");
        }

        $existingUser = $this->userRepository->findByEmail($email);
        if ($existingUser && $existingUser->getId() !== $id) {
            throw new ValidationException("A user with this email already exists.");
        }

        if ($role === 'member' && $user->getRole() !== 'member') {
            if ($this->userRepository->countMembers() >= 4) {
                throw new ValidationException("Limit reached: The team can have a maximum of 4 Team Members.");
            }
        }

        if ($user->getRole() === 'super_admin' && $role !== 'super_admin') {
            $all = $this->userRepository->findAll();
            $adminCount = 0;
            foreach ($all as $u) {
                if ($u->getRole() === 'super_admin') {
                    $adminCount++;
                }
            }
            if ($adminCount <= 1) {
                throw new ValidationException("Cannot demote the only Super-Admin in the system.");
            }
        }

        $hash = $user->getPasswordHash();
        if (!empty($password)) {
            $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
            $hash = password_hash($password, $algo);
        }

        $updatedUser = new User($id, $role, $name, $email, $hash, $user->getCreatedAt());
        return $this->userRepository->save($updatedUser);
    }

    public function deleteUser(int $id): void
    {
        $user = $this->userRepository->findById($id);
        if (!$user) {
            throw new ValidationException("User not found.");
        }

        if ($user->getRole() === 'super_admin') {
            throw new ValidationException("Super-Admin accounts cannot be deleted.");
        }

        $this->userRepository->delete($id);
    }
}
