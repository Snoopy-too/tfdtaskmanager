<?php
declare(strict_types=1);

namespace App\Application\Services;

use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\Entities\User;
use App\Application\Exceptions\ValidationException;

class AuthService
{
    private UserRepositoryInterface $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function login(string $email, string $password): User
    {
        $email = trim($email);
        if (empty($email) || empty($password)) {
            throw new ValidationException("Email and password are required.");
        }

        $user = $this->userRepository->findByEmail($email);
        if (!$user) {
            throw new ValidationException("Invalid email or password.");
        }

        if (!password_verify($password, $user->getPasswordHash())) {
            throw new ValidationException("Invalid email or password.");
        }

        return $user;
    }
}
