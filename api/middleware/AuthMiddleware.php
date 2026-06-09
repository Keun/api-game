<?php

class AuthMiddleware
{
    public static function handle(): int
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
            Response::error('Missing or malformed Authorization header', 401);
        }

        $token = $matches[1];

        $pdo  = Database::connect();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE api_token = ? LIMIT 1');
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) {
            Response::error('Invalid or expired token', 401);
        }

        return (int) $user['id'];
    }
}
