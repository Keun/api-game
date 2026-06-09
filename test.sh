#!/usr/bin/env bash
# End-to-end smoke test for the Game Backend API
# Usage: ./test.sh [base_url]

BASE="${1:-http://localhost:8080}"
EMAIL="player_$(date +%s)@example.com"
PASS="password123"

ok()  { echo "[PASS] $1"; }
fail() { echo "[FAIL] $1"; exit 1; }

check_success() {
  local label="$1" body="$2"
  echo "$body" | grep -q '"success":true' && ok "$label" || fail "$label -> $body"
}

echo "=== Game API Smoke Test ==="
echo "Base URL : $BASE"
echo "Email    : $EMAIL"
echo ""

# 1. Register
echo "1. POST /auth/register"
R=$(curl -sf -X POST "$BASE/auth/register" \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"$EMAIL\",\"password\":\"$PASS\"}")
check_success "register" "$R"

# 2. Login — capture token
echo "2. POST /auth/login"
R=$(curl -sf -X POST "$BASE/auth/login" \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"$EMAIL\",\"password\":\"$PASS\"}")
check_success "login" "$R"
TOKEN=$(echo "$R" | sed 's/.*"token":"\([^"]*\)".*/\1/')
[ -n "$TOKEN" ] && ok "token extracted" || fail "could not extract token"

AUTH="Authorization: Bearer $TOKEN"

# 3. Start session
echo "3. POST /session/start"
R=$(curl -sf -X POST "$BASE/session/start" -H "$AUTH")
check_success "session/start" "$R"
UUID=$(echo "$R" | sed 's/.*"session_uuid":"\([^"]*\)".*/\1/')
[ -n "$UUID" ] && ok "session_uuid extracted: $UUID" || fail "could not extract session_uuid"

# 4. Record stats
echo "4. POST /stats"
R=$(curl -sf -X POST "$BASE/stats" \
  -H "$AUTH" -H "Content-Type: application/json" \
  -d "{\"session_uuid\":\"$UUID\",\"score\":1500,\"shots_or_slashes\":42,\"result\":\"win\"}")
check_success "stats/record" "$R"

# 5. End session
echo "5. POST /session/end"
R=$(curl -sf -X POST "$BASE/session/end" \
  -H "$AUTH" -H "Content-Type: application/json" \
  -d "{\"session_uuid\":\"$UUID\"}")
check_success "session/end" "$R"

# 6. Get session
echo "6. GET /session/{uuid}"
R=$(curl -sf "$BASE/session/$UUID" -H "$AUTH")
check_success "session/get" "$R"

# 7. Get user stats
echo "7. GET /stats/user"
R=$(curl -sf "$BASE/stats/user" -H "$AUTH")
check_success "stats/user" "$R"

# 8. Leaderboard (no auth required)
echo "8. GET /stats/leaderboard"
R=$(curl -sf "$BASE/stats/leaderboard")
check_success "stats/leaderboard" "$R"

# 9. Logout
echo "9. POST /auth/logout"
R=$(curl -sf -X POST "$BASE/auth/logout" -H "$AUTH")
check_success "logout" "$R"

echo ""
echo "All tests passed."
