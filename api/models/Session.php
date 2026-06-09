<?php

class Session
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function create(int $userId, string $uuid): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO sessions (user_id, session_uuid, started_at) VALUES (?, ?, NOW())'
        );
        $stmt->execute([$userId, $uuid]);
        return (int) $this->db->lastInsertId();
    }

    public function findByUuid(string $uuid): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM sessions WHERE session_uuid = ? LIMIT 1');
        $stmt->execute([$uuid]);
        return $stmt->fetch();
    }

    public function end(string $uuid): array|false
    {
        $session = $this->findByUuid($uuid);
        if (!$session || $session['ended_at'] !== null) {
            return false;
        }

        $stmt = $this->db->prepare(
            'UPDATE sessions
             SET ended_at = NOW(),
                 duration_seconds = TIMESTAMPDIFF(SECOND, started_at, NOW())
             WHERE session_uuid = ?'
        );
        $stmt->execute([$uuid]);

        return $this->findByUuid($uuid);
    }

    public function findWithStats(string $uuid): array|false
    {
        $stmt = $this->db->prepare(
            'SELECT s.*, st.score, st.shots_or_slashes, st.result, st.recorded_at
             FROM sessions s
             LEFT JOIN stats st ON st.session_id = s.id
             WHERE s.session_uuid = ?'
        );
        $stmt->execute([$uuid]);
        return $stmt->fetch();
    }
}
