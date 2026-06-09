<?php

class SessionController
{
    private Session $session;

    public function __construct()
    {
        $this->session = new Session();
    }

    public function start(int $userId): void
    {
        $uuid = $this->uuid4();
        $this->session->create($userId, $uuid);

        Response::json([
            'session_uuid' => $uuid,
            'started_at'   => date('c'),
        ], 201);
    }

    public function end(int $userId): void
    {
        $body = (array) json_decode(file_get_contents('php://input'), true);
        $uuid = trim($body['session_uuid'] ?? '');

        if (!$uuid) {
            Response::error('session_uuid is required');
        }

        $row = $this->session->findByUuid($uuid);

        if (!$row) {
            Response::error('Session not found', 404);
        }

        if ((int) $row['user_id'] !== $userId) {
            Response::error('Forbidden', 403);
        }

        $updated = $this->session->end($uuid);

        if (!$updated) {
            Response::error('Session already ended or not found');
        }

        Response::json([
            'session_uuid'     => $updated['session_uuid'],
            'started_at'       => $updated['started_at'],
            'ended_at'         => $updated['ended_at'],
            'duration_seconds' => (int) $updated['duration_seconds'],
        ]);
    }

    public function get(int $userId, string $uuid): void
    {
        $row = $this->session->findWithStats($uuid);

        if (!$row) {
            Response::error('Session not found', 404);
        }

        if ((int) $row['user_id'] !== $userId) {
            Response::error('Forbidden', 403);
        }

        Response::json($row);
    }

    private function uuid4(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
