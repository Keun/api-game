<?php

class User
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function findByEmail(string $email): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    public function create(string $email, string $passwordHash): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (email, password_hash, created_at) VALUES (?, ?, NOW())'
        );
        $stmt->execute([$email, $passwordHash]);
        return (int) $this->db->lastInsertId();
    }

    public function setToken(int $userId, string $token): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET api_token = ?, last_login = NOW() WHERE id = ?'
        );
        $stmt->execute([$token, $userId]);
    }

    public function clearToken(int $userId): void
    {
        $stmt = $this->db->prepare('UPDATE users SET api_token = NULL WHERE id = ?');
        $stmt->execute([$userId]);
    }
}
