<?php

class AuthController
{
    private User $user;

    public function __construct()
    {
        $this->user = new User();
    }

    public function register(): void
    {
        $body = $this->body();

        $email    = trim($body['email']    ?? '');
        $password = trim($body['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('Invalid email address');
        }

        if (strlen($password) < 8) {
            Response::error('Password must be at least 8 characters');
        }

        if ($this->user->findByEmail($email)) {
            Response::error('Email already registered', 409);
        }

        $hash   = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $userId = $this->user->create($email, $hash);

        Response::json(['user_id' => $userId, 'email' => $email], 201);
    }

    public function login(): void
    {
        $body = $this->body();

        $email    = trim($body['email']    ?? '');
        $password = trim($body['password'] ?? '');

        $record = $this->user->findByEmail($email);

        if (!$record || !password_verify($password, $record['password_hash'])) {
            Response::error('Invalid credentials', 401);
        }

        $token = hash('sha256', $record['email'] . microtime() . $_ENV['API_TOKEN_SALT']);
        $this->user->setToken((int) $record['id'], $token);

        Response::json([
            'token'   => $token,
            'user_id' => (int) $record['id'],
            'email'   => $record['email'],
        ]);
    }

    public function logout(int $userId): void
    {
        $this->user->clearToken($userId);
        Response::json(['message' => 'Logged out successfully']);
    }

    private function body(): array
    {
        return (array) json_decode(file_get_contents('php://input'), true);
    }
}
