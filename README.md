# Game Backend API

A PHP 8.2 + MySQL 8 REST API designed for Unity and Unreal Engine games. Handles player authentication, match sessions, and stats/leaderboards — all running in Docker with a single command.

---

## Requirements

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) 24+
- `curl` (for the smoke test)

---

## Installation

```bash
# 1. Clone the repo
git clone <your-repo-url> api-game
cd api-game

# 2. Copy the environment file and set your secrets
cp .env.example .env

# 3. Start the containers
docker compose up -d

# 4. Verify everything is running
docker compose ps
```

The API is now available at `http://localhost:8080`.

> **Production note:** Change `DB_PASS`, `API_TOKEN_SALT` (use a long random string), and enforce HTTPS at your reverse proxy before going live.

---

## Environment Variables

| Variable | Default | Description |
|---|---|---|
| `DB_HOST` | `db` | MySQL hostname (Docker service name) |
| `DB_PORT` | `3306` | MySQL port |
| `DB_NAME` | `gamedb` | Database name |
| `DB_USER` | `gameuser` | Database user |
| `DB_PASS` | `secret` | Database password |
| `API_TOKEN_SALT` | `changeme_in_production` | Salt mixed into token generation |

---

## Smoke Test

```bash
./test.sh
# or against a custom URL:
./test.sh http://your-server:8080
```

Runs the full flow: register → login → start session → record stats → end session → leaderboard → logout.

---

## API Reference

All responses follow this envelope:

```json
{ "success": true, "data": { ... } }
{ "success": false, "error": "message" }
```

Authenticated endpoints require the header:
```
Authorization: Bearer <token>
```

---

### Auth

#### `POST /auth/register`

Register a new player account.

**Request**
```json
{ "email": "player@example.com", "password": "password123" }
```

**Response** `201`
```json
{ "success": true, "data": { "user_id": 1, "email": "player@example.com" } }
```

---

#### `POST /auth/login`

Authenticate and receive a Bearer token. A new token is issued on every login.

**Request**
```json
{ "email": "player@example.com", "password": "password123" }
```

**Response** `200`
```json
{
  "success": true,
  "data": {
    "token": "e3b0c44298fc1c149afb...",
    "user_id": 1,
    "email": "player@example.com"
  }
}
```

---

#### `POST /auth/logout` — auth required

Invalidates the current token.

**Response** `200`
```json
{ "success": true, "data": { "message": "Logged out successfully" } }
```

---

### Sessions

#### `POST /session/start` — auth required

Opens a new match session.

**Response** `201`
```json
{
  "success": true,
  "data": {
    "session_uuid": "f47ac10b-58cc-4372-a567-0e02b2c3d479",
    "started_at": "2025-06-09T14:00:00+00:00"
  }
}
```

---

#### `POST /session/end` — auth required

Closes a session and records its duration.

**Request**
```json
{ "session_uuid": "f47ac10b-58cc-4372-a567-0e02b2c3d479" }
```

**Response** `200`
```json
{
  "success": true,
  "data": {
    "session_uuid": "f47ac10b-...",
    "started_at": "2025-06-09T14:00:00",
    "ended_at": "2025-06-09T14:12:34",
    "duration_seconds": 754
  }
}
```

---

#### `GET /session/{uuid}` — auth required

Fetch a session and its recorded stats.

**Response** `200`
```json
{
  "success": true,
  "data": {
    "id": 1,
    "session_uuid": "f47ac10b-...",
    "started_at": "2025-06-09T14:00:00",
    "ended_at": "2025-06-09T14:12:34",
    "duration_seconds": 754,
    "score": 1500,
    "shots_or_slashes": 42,
    "result": "win"
  }
}
```

---

### Stats

#### `POST /stats` — auth required

Record match stats for a session. Must be called before or after `session/end`.

**Request**
```json
{
  "session_uuid": "f47ac10b-58cc-4372-a567-0e02b2c3d479",
  "score": 1500,
  "shots_or_slashes": 42,
  "result": "win"
}
```

**Response** `201`
```json
{ "success": true, "data": { "stats_id": 7 } }
```

---

#### `GET /stats/user` — auth required

Aggregate stats totals for the authenticated player.

**Response** `200`
```json
{
  "success": true,
  "data": {
    "matches_played": 10,
    "total_score": 14200,
    "total_shots_or_slashes": 380,
    "wins": 7,
    "losses": 2,
    "draws": 1
  }
}
```

---

#### `GET /stats/leaderboard`

Top players by total score. No auth required. Accepts optional `?limit=N` (default 10, max 100).

**Response** `200`
```json
{
  "success": true,
  "data": [
    { "email": "ace@example.com",    "total_score": 9800, "wins": 24, "losses": 6 },
    { "email": "player@example.com", "total_score": 7200, "wins": 18, "losses": 12 }
  ]
}
```

---

## Unity (C#) Integration

### GameApi.cs

Drop this helper class into your Unity project. It manages the token and wraps every endpoint.

```csharp
using System;
using System.Collections;
using System.Text;
using UnityEngine;
using UnityEngine.Networking;

public class GameApi : MonoBehaviour
{
    private const string BaseUrl = "http://localhost:8080";

    private string _token;
    private string _sessionUuid;

    // ── Auth ──────────────────────────────────────────────────────────────

    public IEnumerator Register(string email, string password,
                                Action<bool, string> callback)
    {
        var body = $"{{\"email\":\"{email}\",\"password\":\"{password}\"}}";
        yield return Post("/auth/register", body, false, (ok, json) =>
        {
            callback(ok, json);
        });
    }

    public IEnumerator Login(string email, string password,
                             Action<bool, string> callback)
    {
        var body = $"{{\"email\":\"{email}\",\"password\":\"{password}\"}}";
        yield return Post("/auth/login", body, false, (ok, json) =>
        {
            if (ok)
            {
                // Parse token — use a JSON library (e.g. JsonUtility or Newtonsoft)
                // for production; this keeps the example dependency-free.
                var data = JsonUtility.FromJson<LoginResponse>(
                    json.Replace("\"data\":", ""));
                _token = data.token;
                PlayerPrefs.SetString("api_token", _token);
            }
            callback(ok, json);
        });
    }

    public IEnumerator Logout(Action<bool, string> callback)
    {
        yield return Post("/auth/logout", "{}", true, callback);
        _token = null;
        PlayerPrefs.DeleteKey("api_token");
    }

    // ── Sessions ──────────────────────────────────────────────────────────

    public IEnumerator StartSession(Action<bool, string> callback)
    {
        yield return Post("/session/start", "{}", true, (ok, json) =>
        {
            if (ok)
            {
                var data = JsonUtility.FromJson<SessionResponse>(
                    json.Replace("\"data\":", ""));
                _sessionUuid = data.session_uuid;
            }
            callback(ok, json);
        });
    }

    public IEnumerator EndSession(Action<bool, string> callback)
    {
        var body = $"{{\"session_uuid\":\"{_sessionUuid}\"}}";
        yield return Post("/session/end", body, true, callback);
    }

    // ── Stats ─────────────────────────────────────────────────────────────

    public IEnumerator RecordStats(int score, int shotsOrSlashes, string result,
                                   Action<bool, string> callback)
    {
        var body = $"{{\"session_uuid\":\"{_sessionUuid}\"," +
                   $"\"score\":{score}," +
                   $"\"shots_or_slashes\":{shotsOrSlashes}," +
                   $"\"result\":\"{result}\"}}";
        yield return Post("/stats", body, true, callback);
    }

    public IEnumerator GetLeaderboard(Action<bool, string> callback)
    {
        yield return Get("/stats/leaderboard", false, callback);
    }

    public IEnumerator GetMyStats(Action<bool, string> callback)
    {
        yield return Get("/stats/user", true, callback);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private IEnumerator Post(string endpoint, string json, bool auth,
                              Action<bool, string> callback)
    {
        var req = new UnityWebRequest(BaseUrl + endpoint, "POST");
        req.uploadHandler   = new UploadHandlerRaw(Encoding.UTF8.GetBytes(json));
        req.downloadHandler = new DownloadHandlerBuffer();
        req.SetRequestHeader("Content-Type", "application/json");
        if (auth) req.SetRequestHeader("Authorization", "Bearer " + _token);

        yield return req.SendWebRequest();

        bool ok = req.result == UnityWebRequest.Result.Success;
        callback(ok, req.downloadHandler.text);
    }

    private IEnumerator Get(string endpoint, bool auth,
                             Action<bool, string> callback)
    {
        var req = UnityWebRequest.Get(BaseUrl + endpoint);
        if (auth) req.SetRequestHeader("Authorization", "Bearer " + _token);

        yield return req.SendWebRequest();

        bool ok = req.result == UnityWebRequest.Result.Success;
        callback(ok, req.downloadHandler.text);
    }

    // ── Response models (JsonUtility needs serialisable structs) ──────────

    [Serializable] private struct LoginResponse  { public string token; }
    [Serializable] private struct SessionResponse { public string session_uuid; }
}
```

### Usage example

```csharp
// Attach GameApi to a GameObject in your scene, then:

public class GameManager : MonoBehaviour
{
    [SerializeField] private GameApi api;

    void Start()
    {
        StartCoroutine(api.Login("player@example.com", "password123", OnLogin));
    }

    private void OnLogin(bool success, string json)
    {
        if (!success) { Debug.LogError("Login failed: " + json); return; }
        Debug.Log("Logged in. Starting session...");
        StartCoroutine(api.StartSession(OnSessionStarted));
    }

    private void OnSessionStarted(bool success, string json)
    {
        if (!success) { Debug.LogError("Session start failed"); return; }
        Debug.Log("Session started: " + json);
        // ... play the match ...
    }

    // Call at end of match:
    public void OnMatchOver(int score, int shots, bool won)
    {
        string result = won ? "win" : "loss";
        StartCoroutine(api.RecordStats(score, shots, result, (ok, _) =>
        {
            if (ok) StartCoroutine(api.EndSession((_, __) => { }));
        }));
    }
}
```

---

## Unreal Engine (C++) Integration

### GameApiSubsystem.h

```cpp
#pragma once

#include "CoreMinimal.h"
#include "Subsystems/GameInstanceSubsystem.h"
#include "HttpModule.h"
#include "Interfaces/IHttpRequest.h"
#include "Interfaces/IHttpResponse.h"
#include "GameApiSubsystem.generated.h"

DECLARE_DYNAMIC_DELEGATE_TwoParams(FOnApiResponse, bool, bSuccess, const FString&, JsonBody);

UCLASS()
class YOURGAME_API UGameApiSubsystem : public UGameInstanceSubsystem
{
    GENERATED_BODY()

public:
    // Auth
    UFUNCTION(BlueprintCallable, Category = "GameAPI")
    void Register(const FString& Email, const FString& Password,
                  const FOnApiResponse& Callback);

    UFUNCTION(BlueprintCallable, Category = "GameAPI")
    void Login(const FString& Email, const FString& Password,
               const FOnApiResponse& Callback);

    UFUNCTION(BlueprintCallable, Category = "GameAPI")
    void Logout(const FOnApiResponse& Callback);

    // Sessions
    UFUNCTION(BlueprintCallable, Category = "GameAPI")
    void StartSession(const FOnApiResponse& Callback);

    UFUNCTION(BlueprintCallable, Category = "GameAPI")
    void EndSession(const FOnApiResponse& Callback);

    // Stats
    UFUNCTION(BlueprintCallable, Category = "GameAPI")
    void RecordStats(int32 Score, int32 ShotsOrSlashes, const FString& Result,
                     const FOnApiResponse& Callback);

    UFUNCTION(BlueprintCallable, Category = "GameAPI")
    void GetLeaderboard(const FOnApiResponse& Callback);

    UFUNCTION(BlueprintCallable, Category = "GameAPI")
    void GetMyStats(const FOnApiResponse& Callback);

private:
    const FString BaseUrl = TEXT("http://localhost:8080");

    FString StoredToken;
    FString SessionUuid;

    void PostRequest(const FString& Endpoint, const FString& JsonBody,
                     bool bAuth, const FOnApiResponse& Callback);

    void GetRequest(const FString& Endpoint, bool bAuth,
                    const FOnApiResponse& Callback);

    void ParseSessionUuid(const FString& JsonBody);
    void ParseToken(const FString& JsonBody);
};
```

### GameApiSubsystem.cpp

```cpp
#include "GameApiSubsystem.h"
#include "HttpModule.h"
#include "Serialization/JsonReader.h"
#include "Serialization/JsonSerializer.h"

void UGameApiSubsystem::Register(const FString& Email, const FString& Password,
                                  const FOnApiResponse& Callback)
{
    FString Body = FString::Printf(
        TEXT("{\"email\":\"%s\",\"password\":\"%s\"}"), *Email, *Password);
    PostRequest(TEXT("/auth/register"), Body, false, Callback);
}

void UGameApiSubsystem::Login(const FString& Email, const FString& Password,
                               const FOnApiResponse& Callback)
{
    FString Body = FString::Printf(
        TEXT("{\"email\":\"%s\",\"password\":\"%s\"}"), *Email, *Password);

    FOnApiResponse Inner;
    Inner.BindLambda([this, Callback](bool bSuccess, const FString& Json)
    {
        if (bSuccess) ParseToken(Json);
        Callback.ExecuteIfBound(bSuccess, Json);
    });

    PostRequest(TEXT("/auth/login"), Body, false, Inner);
}

void UGameApiSubsystem::Logout(const FOnApiResponse& Callback)
{
    PostRequest(TEXT("/auth/logout"), TEXT("{}"), true, Callback);
    StoredToken.Empty();
}

void UGameApiSubsystem::StartSession(const FOnApiResponse& Callback)
{
    FOnApiResponse Inner;
    Inner.BindLambda([this, Callback](bool bSuccess, const FString& Json)
    {
        if (bSuccess) ParseSessionUuid(Json);
        Callback.ExecuteIfBound(bSuccess, Json);
    });

    PostRequest(TEXT("/session/start"), TEXT("{}"), true, Inner);
}

void UGameApiSubsystem::EndSession(const FOnApiResponse& Callback)
{
    FString Body = FString::Printf(
        TEXT("{\"session_uuid\":\"%s\"}"), *SessionUuid);
    PostRequest(TEXT("/session/end"), Body, true, Callback);
}

void UGameApiSubsystem::RecordStats(int32 Score, int32 ShotsOrSlashes,
                                     const FString& Result,
                                     const FOnApiResponse& Callback)
{
    FString Body = FString::Printf(
        TEXT("{\"session_uuid\":\"%s\",\"score\":%d,\"shots_or_slashes\":%d,\"result\":\"%s\"}"),
        *SessionUuid, Score, ShotsOrSlashes, *Result);
    PostRequest(TEXT("/stats"), Body, true, Callback);
}

void UGameApiSubsystem::GetLeaderboard(const FOnApiResponse& Callback)
{
    GetRequest(TEXT("/stats/leaderboard"), false, Callback);
}

void UGameApiSubsystem::GetMyStats(const FOnApiResponse& Callback)
{
    GetRequest(TEXT("/stats/user"), true, Callback);
}

// ── Private helpers ────────────────────────────────────────────────────────

void UGameApiSubsystem::PostRequest(const FString& Endpoint, const FString& JsonBody,
                                     bool bAuth, const FOnApiResponse& Callback)
{
    TSharedRef<IHttpRequest> Request = FHttpModule::Get().CreateRequest();
    Request->SetURL(BaseUrl + Endpoint);
    Request->SetVerb(TEXT("POST"));
    Request->SetHeader(TEXT("Content-Type"), TEXT("application/json"));
    if (bAuth)
        Request->SetHeader(TEXT("Authorization"),
                           FString::Printf(TEXT("Bearer %s"), *StoredToken));

    Request->SetContentAsString(JsonBody);
    Request->OnProcessRequestComplete().BindLambda(
        [Callback](FHttpRequestPtr, FHttpResponsePtr Response, bool bConnected)
        {
            bool bOk = bConnected && Response.IsValid() &&
                       Response->GetResponseCode() < 300;
            FString Body = Response.IsValid() ? Response->GetContentAsString() : TEXT("");
            Callback.ExecuteIfBound(bOk, Body);
        });

    Request->ProcessRequest();
}

void UGameApiSubsystem::GetRequest(const FString& Endpoint, bool bAuth,
                                    const FOnApiResponse& Callback)
{
    TSharedRef<IHttpRequest> Request = FHttpModule::Get().CreateRequest();
    Request->SetURL(BaseUrl + Endpoint);
    Request->SetVerb(TEXT("GET"));
    if (bAuth)
        Request->SetHeader(TEXT("Authorization"),
                           FString::Printf(TEXT("Bearer %s"), *StoredToken));

    Request->OnProcessRequestComplete().BindLambda(
        [Callback](FHttpRequestPtr, FHttpResponsePtr Response, bool bConnected)
        {
            bool bOk = bConnected && Response.IsValid() &&
                       Response->GetResponseCode() < 300;
            FString Body = Response.IsValid() ? Response->GetContentAsString() : TEXT("");
            Callback.ExecuteIfBound(bOk, Body);
        });

    Request->ProcessRequest();
}

void UGameApiSubsystem::ParseToken(const FString& JsonBody)
{
    TSharedPtr<FJsonObject> Root, Data;
    TSharedRef<TJsonReader<>> Reader = TJsonReaderFactory<>::Create(JsonBody);
    if (FJsonSerializer::Deserialize(Reader, Root) && Root->HasField(TEXT("data")))
    {
        Data = Root->GetObjectField(TEXT("data"));
        StoredToken = Data->GetStringField(TEXT("token"));
    }
}

void UGameApiSubsystem::ParseSessionUuid(const FString& JsonBody)
{
    TSharedPtr<FJsonObject> Root, Data;
    TSharedRef<TJsonReader<>> Reader = TJsonReaderFactory<>::Create(JsonBody);
    if (FJsonSerializer::Deserialize(Reader, Root) && Root->HasField(TEXT("data")))
    {
        Data = Root->GetObjectField(TEXT("data"));
        SessionUuid = Data->GetStringField(TEXT("session_uuid"));
    }
}
```

### Usage example (in any Actor or GameMode)

```cpp
// Get the subsystem from anywhere
UGameApiSubsystem* Api = GetGameInstance()->GetSubsystem<UGameApiSubsystem>();

// Login
FOnApiResponse OnLogin;
OnLogin.BindLambda([Api](bool bSuccess, const FString& Json)
{
    if (!bSuccess) { UE_LOG(LogTemp, Error, TEXT("Login failed: %s"), *Json); return; }

    // Token is stored inside the subsystem — start a session immediately
    FOnApiResponse OnSession;
    OnSession.BindLambda([](bool bOk, const FString&)
    {
        if (bOk) UE_LOG(LogTemp, Log, TEXT("Session started"));
    });
    Api->StartSession(OnSession);
});

Api->Login(TEXT("player@example.com"), TEXT("password123"), OnLogin);

// At match end:
FOnApiResponse OnStats;
OnStats.BindLambda([Api](bool bOk, const FString&)
{
    if (bOk) Api->EndSession(FOnApiResponse());
});
Api->RecordStats(2400, 87, TEXT("win"), OnStats);
```

> **Unreal setup:** Add `"HTTP"` and `"Json"` to your module's `PublicDependencyModuleNames` in `YourGame.Build.cs`.

---

## Adding Custom Stats

No controller changes needed — just add a column and include it in your POST body.

```sql
-- Run once, or add to init.sql before first launch
ALTER TABLE stats ADD COLUMN headshots INT DEFAULT 0 AFTER shots_or_slashes;
```

Then send the extra field from your client:

```json
{
  "session_uuid": "...",
  "score": 1500,
  "shots_or_slashes": 42,
  "headshots": 12,
  "result": "win"
}
```

---

## Security Checklist

- [x] Passwords hashed with `password_hash()` bcrypt (cost 12)
- [x] Token regenerated on every login — old token invalidated on logout
- [x] All queries use PDO prepared statements
- [x] `.env` excluded from version control via `.gitignore`
- [ ] Set `API_TOKEN_SALT` to a long random string in production
- [ ] Enforce HTTPS (terminate at nginx / load balancer)
- [ ] Add rate limiting (`nginx limit_req` or a Redis counter)
