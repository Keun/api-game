<?php

declare(strict_types=1);

// Load .env variables into $_ENV
foreach (parse_ini_file(__DIR__ . '/../.env') ?: [] as $key => $value) {
    $_ENV[$key] = $value;
}

// Autoload classes
spl_autoload_register(function (string $class): void {
    $dirs = [
        __DIR__ . '/config/',
        __DIR__ . '/helpers/',
        __DIR__ . '/middleware/',
        __DIR__ . '/models/',
        __DIR__ . '/controllers/',
    ];
    foreach ($dirs as $dir) {
        $file = $dir . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Parse method and path
$method   = $_SERVER['REQUEST_METHOD'];
$uri      = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$segments = array_values(array_filter(explode('/', trim($uri, '/'))));

// Helper to resolve optional auth
$requireAuth = static function (): int {
    return AuthMiddleware::handle();
};

// Route table
try {
    // POST /auth/register
    if ($method === 'POST' && $segments === ['auth', 'register']) {
        (new AuthController())->register();
    }

    // POST /auth/login
    elseif ($method === 'POST' && $segments === ['auth', 'login']) {
        (new AuthController())->login();
    }

    // POST /auth/logout
    elseif ($method === 'POST' && $segments === ['auth', 'logout']) {
        $userId = $requireAuth();
        (new AuthController())->logout($userId);
    }

    // POST /session/start
    elseif ($method === 'POST' && $segments === ['session', 'start']) {
        $userId = $requireAuth();
        (new SessionController())->start($userId);
    }

    // POST /session/end
    elseif ($method === 'POST' && $segments === ['session', 'end']) {
        $userId = $requireAuth();
        (new SessionController())->end($userId);
    }

    // GET /session/{uuid}
    elseif ($method === 'GET' && count($segments) === 2 && $segments[0] === 'session') {
        $userId = $requireAuth();
        (new SessionController())->get($userId, $segments[1]);
    }

    // POST /stats
    elseif ($method === 'POST' && $segments === ['stats']) {
        $userId = $requireAuth();
        (new StatsController())->record($userId);
    }

    // GET /stats/user
    elseif ($method === 'GET' && $segments === ['stats', 'user']) {
        $userId = $requireAuth();
        (new StatsController())->getUserStats($userId);
    }

    // GET /stats/leaderboard
    elseif ($method === 'GET' && $segments === ['stats', 'leaderboard']) {
        (new StatsController())->getLeaderboard();
    }

    else {
        Response::error('Route not found', 404);
    }
} catch (PDOException $e) {
    Response::error('Database error: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    Response::error('Server error: ' . $e->getMessage(), 500);
}
