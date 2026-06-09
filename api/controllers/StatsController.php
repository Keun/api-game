<?php

class StatsController
{
    private Stats   $stats;
    private Session $session;

    public function __construct()
    {
        $this->stats   = new Stats();
        $this->session = new Session();
    }

    public function record(int $userId): void
    {
        $body = (array) json_decode(file_get_contents('php://input'), true);

        $uuid   = trim($body['session_uuid'] ?? '');
        $result = trim($body['result']       ?? '');

        if (!$uuid) {
            Response::error('session_uuid is required');
        }

        if (!in_array($result, ['win', 'loss', 'draw'], true)) {
            Response::error('result must be win, loss, or draw');
        }

        $sessionRow = $this->session->findByUuid($uuid);

        if (!$sessionRow) {
            Response::error('Session not found', 404);
        }

        if ((int) $sessionRow['user_id'] !== $userId) {
            Response::error('Forbidden', 403);
        }

        $id = $this->stats->record($userId, (int) $sessionRow['id'], $body);

        Response::json(['stats_id' => $id], 201);
    }

    public function getUserStats(int $userId): void
    {
        $totals = $this->stats->getUserTotals($userId);
        Response::json($totals);
    }

    public function getLeaderboard(): void
    {
        $limit = max(1, min(100, (int) ($_GET['limit'] ?? 10)));
        Response::json($this->stats->getLeaderboard($limit));
    }
}
