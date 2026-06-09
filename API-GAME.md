# Game Backend API — Project Plan for Claude Code

A PHP + MySQL REST API running in Docker, designed for use with Unity or Unreal Engine.
Token-authenticated, session-aware, and easy to extend by any game developer.

---

## Project Structure

```
game-api/
├── docker-compose.yml
├── .env
├── mysql/
│   └── init.sql                  # Database schema
├── api/
│   ├── Dockerfile
│   ├── .htaccess
│   ├── index.php                 # Router entry point
│   ├── config/
│   │   └── Database.php          # PDO connection
│   ├── middleware/
│   │   └── AuthMiddleware.php    # Token validation
│   ├── controllers/
│   │   ├── AuthController.php    # Login / logout
│   │   ├── SessionController.php # Session management
│   │   └── StatsController.php   # Scores, shots, wins/losses
│   ├── models/
│   │   ├── User.php
│   │   ├── Session.php
│   │   └── Stats.php
│   └── helpers/
│       └── Response.php          # Unified JSON responses
└── README.md
```

---

## Phase 1 — Docker Setup

### Goal
Spin up PHP 8.2 (Apache) + MySQL 8 with a single `docker compose up`.

### Files to create

**`docker-compose.yml`**
- Service `db`: MySQL 8, port 3306, volume-mounted data dir, env vars from `.env`
- Service `api`: PHP 8.2-Apache, port 8080→80, mounts `./api` to `/var/www/html`
- Shared network `game-net`

**`api/Dockerfile`**
- Base: `php:8.2-apache`
- Install: `pdo_mysql`, `mbstring`
- Enable `mod_rewrite` for clean URL routing

**`.env`**
```
DB_HOST=db
DB_PORT=3306
DB_NAME=gamedb
DB_USER=gameuser
DB_PASS=secret
API_TOKEN_SALT=changeme_in_production
```

---

## Phase 2 — Database Schema

**`mysql/init.sql`**

### `users`
| Column | Type | Notes |
|---|---|---|
| `id` | INT AUTO_INCREMENT PK | |
| `email` | VARCHAR(255) UNIQUE | |
| `password_hash` | VARCHAR(255) | bcrypt |
| `api_token` | VARCHAR(64) | SHA-256 hex, regenerated on login |
| `last_login` | DATETIME | Updated on each login |
| `created_at` | DATETIME | |

### `sessions`
| Column | Type | Notes |
|---|---|---|
| `id` | INT AUTO_INCREMENT PK | |
| `user_id` | INT FK → users | |
| `session_uuid` | VARCHAR(36) | UUID v4, unique per match |
| `started_at` | DATETIME | |
| `ended_at` | DATETIME NULL | NULL = still active |
| `duration_seconds` | INT NULL | Set when session ends |

### `stats`
| Column | Type | Notes |
|---|---|---|
| `id` | INT AUTO_INCREMENT PK | |
| `user_id` | INT FK → users | |
| `session_id` | INT FK → sessions | |
| `score` | INT DEFAULT 0 | |
| `shots_or_slashes` | INT DEFAULT 0 | Shots fired / sword slashes |
| `result` | ENUM('win','loss','draw') | Match outcome |
| `recorded_at` | DATETIME | |

> **Extension note:** Adding new per-session stats (e.g. `kills`, `damage_dealt`) only requires an `ALTER TABLE stats ADD COLUMN ...` — no controller changes needed for storage.

---

## Phase 3 — Core API Code

### Router (`index.php`)
- Parse `$_SERVER['REQUEST_URI']` into method + path segments
- Route table:

| Method | Path | Controller | Auth required |
|---|---|---|---|
| POST | `/auth/register` | AuthController | No |
| POST | `/auth/login` | AuthController | No |
| POST | `/auth/logout` | AuthController | Yes |
| POST | `/session/start` | SessionController | Yes |
| POST | `/session/end` | SessionController | Yes |
| GET | `/session/{uuid}` | SessionController | Yes |
| POST | `/stats` | StatsController | Yes |
| GET | `/stats/user` | StatsController | Yes |
| GET | `/stats/leaderboard` | StatsController | Yes |

### Auth Middleware (`AuthMiddleware.php`)
- Read `Authorization: Bearer <token>` header
- Query `users` table for matching `api_token`
- Inject `$user_id` into request context; return 401 if missing/invalid

### AuthController
- `register`: validate email + password → bcrypt → insert user
- `login`: verify bcrypt → generate new token (`hash('sha256', email . time() . salt)`) → update `api_token` + `last_login` → return token
- `logout`: clear `api_token` for user

### SessionController
- `start`: insert new row with UUID v4 + `started_at = NOW()` → return `session_uuid`
- `end`: set `ended_at = NOW()`, calculate `duration_seconds` → return summary
- `get`: fetch session row + joined stats

### StatsController
- `record`: insert stats row linked to session + user
- `getUserStats`: aggregate totals for the authenticated user
- `getLeaderboard`: top N users ordered by total score

### Response Helper (`Response.php`)
```php
// Consistent envelope for every response
{
  "success": true|false,
  "data": { ... },       // on success
  "error": "message"     // on failure
}
```

---

## Phase 4 — Authentication Flow

```
Client (Unity / Unreal)
        │
        ▼
POST /auth/login  { email, password }
        │
        ▼
API returns  { "token": "abc123..." }
        │
        ▼
All subsequent requests:
  Header:  Authorization: Bearer abc123...
        │
        ▼
Middleware validates token → injects user_id
```

---

## Phase 5 — Game Client Integration

### Unity (C#) example skeleton
```csharp
// Store token after login
PlayerPrefs.SetString("api_token", token);

// Authenticated request helper
IEnumerator PostRequest(string endpoint, string json) {
    var req = new UnityWebRequest(API_BASE + endpoint, "POST");
    req.SetRequestHeader("Authorization", "Bearer " + PlayerPrefs.GetString("api_token"));
    req.SetRequestHeader("Content-Type", "application/json");
    // ...
}
```

### Unreal (C++) example skeleton
```cpp
// Set auth header on every request
Request->SetHeader(TEXT("Authorization"),
    FString::Printf(TEXT("Bearer %s"), *StoredToken));
Request->SetHeader(TEXT("Content-Type"), TEXT("application/json"));
```

---

## Phase 6 — Extensibility Guide for Game Developers

The API is designed so new game metrics can be added without touching existing endpoints.

### Adding a new stat column
```sql
-- Example: add a headshots column
ALTER TABLE stats ADD COLUMN headshots INT DEFAULT 0 AFTER shots_or_slashes;
```
Then POST `/stats` with `{ ..., "headshots": 5 }` — the StatsController reads all known columns dynamically.

### Adding a new endpoint
1. Create a new controller in `api/controllers/`
2. Add a route entry in `index.php`
3. Optionally add a new table in `mysql/init.sql` (re-run or migrate)

### Environment promotion
- Copy `.env` and change credentials
- `docker compose up -d` — no other changes needed

---

## Phase 7 — Security Checklist

- [ ] Passwords hashed with `password_hash()` (bcrypt, cost ≥ 12)
- [ ] Token regenerated on every login (no token reuse after logout)
- [ ] All DB queries use PDO prepared statements (no raw interpolation)
- [ ] `.env` excluded from version control via `.gitignore`
- [ ] `API_TOKEN_SALT` set to a long random string in production
- [ ] HTTPS enforced in production (terminate at reverse proxy / load balancer)
- [ ] Rate limiting added (optional: nginx `limit_req` or PHP counter in Redis)

---

## Claude Code — Step-by-Step Execution Order

Run these prompts in Claude Code in order:

1. **"Create the full project directory structure as described in GAME_API_PLAN.md"**
2. **"Generate docker-compose.yml and api/Dockerfile"**
3. **"Generate mysql/init.sql with all three tables"**
4. **"Generate config/Database.php, helpers/Response.php, and middleware/AuthMiddleware.php"**
5. **"Generate all three controllers: AuthController, SessionController, StatsController"**
6. **"Generate all three models: User, Session, Stats"**
7. **"Generate the router in index.php and the .htaccess rewrite rule"**
8. **"Generate a .env.example file and a .gitignore"**
9. **"Run docker compose up -d and verify the containers start cleanly"**
10. **"Write a test script (test.sh or Postman collection) that registers a user, logs in, starts a session, records stats, and retrieves the leaderboard"**

---

## Expected API Response Examples

**POST /auth/login**
```json
{
  "success": true,
  "data": {
    "token": "e3b0c44298fc1c149afb...",
    "user_id": 42,
    "email": "player@example.com"
  }
}
```

**POST /session/start**
```json
{
  "success": true,
  "data": {
    "session_uuid": "f47ac10b-58cc-4372-a567-0e02b2c3d479",
    "started_at": "2025-06-09T14:00:00Z"
  }
}
```

**GET /stats/leaderboard**
```json
{
  "success": true,
  "data": [
    { "email": "ace@example.com", "total_score": 9800, "wins": 24, "losses": 6 },
    { "email": "player@example.com", "total_score": 7200, "wins": 18, "losses": 12 }
  ]
}
```