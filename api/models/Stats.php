<?php

class Stats
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function record(int $userId, int $sessionId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO stats (user_id, session_id, score, shots_or_slashes, result, recorded_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $userId,
            $sessionId,
            (int) ($data['score']            ?? 0),
            (int) ($data['shots_or_slashes'] ?? 0),
            $data['result'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function getUserTotals(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                COUNT(*)                               AS matches_played,
                COALESCE(SUM(score), 0)                AS total_score,
                COALESCE(SUM(shots_or_slashes), 0)     AS total_shots_or_slashes,
                SUM(result = "win")                    AS wins,
                SUM(result = "loss")                   AS losses,
                SUM(result = "draw")                   AS draws
             FROM stats
             WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }

    public function getLeaderboard(int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                u.email,
                COALESCE(SUM(st.score), 0)  AS total_score,
                SUM(st.result = "win")       AS wins,
                SUM(st.result = "loss")      AS losses
             FROM users u
             LEFT JOIN stats st ON st.user_id = u.id
             GROUP BY u.id, u.email
             ORDER BY total_score DESC
             LIMIT ?'
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
}
