#!/usr/bin/env bash
set -euo pipefail

REDMINE_URL="${REDMINE_URL:-http://localhost:3000}"
CONTAINER="${REDMINE_CONTAINER:-redmine}"
SEED_SCRIPT="/seed/seed.rb"
MAX_WAIT=120

# ── Colours ───────────────────────────────────────────────────────────────────
GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; NC='\033[0m'
info()    { echo -e "${GREEN}[init]${NC} $*"; }
warn()    { echo -e "${YELLOW}[init]${NC} $*"; }
error()   { echo -e "${RED}[init]${NC} $*" >&2; exit 1; }

# ── Start containers ──────────────────────────────────────────────────────────
info "Starting containers..."
docker-compose up -d

# ── Wait for Redmine ──────────────────────────────────────────────────────────
info "Waiting for Redmine at ${REDMINE_URL} (up to ${MAX_WAIT}s)..."
elapsed=0
until curl -sf -o /dev/null "${REDMINE_URL}"; do
  if [ $elapsed -ge $MAX_WAIT ]; then
    error "Redmine did not become ready within ${MAX_WAIT}s"
  fi
  sleep 3
  elapsed=$((elapsed + 3))
  echo -n "."
done
echo ""
info "Redmine is responding"

# ── Run seed script ───────────────────────────────────────────────────────────
info "Running seed script inside container '${CONTAINER}'..."
docker exec "${CONTAINER}" bundle exec rails runner "${SEED_SCRIPT}" 2>&1 \
  | grep -v "^\(W,\|I,\|E,\)" \
  | grep -E "^\[seed\]|━|Redmine is ready|Login:|API key:"

# ── Update .env ───────────────────────────────────────────────────────────────
ENV_FILE="$(dirname "$0")/../.env"
API_KEY=$(docker exec "${CONTAINER}" bundle exec rails runner \
  "puts User.find_by(login: 'admin').api_key" 2>/dev/null | tail -1)

if [ -f "$ENV_FILE" ] && [ -n "$API_KEY" ]; then
  if grep -q "^REDMINE_BASE_URL=" "$ENV_FILE"; then
    sed -i "s|^REDMINE_BASE_URL=.*|REDMINE_BASE_URL=${REDMINE_URL}|" "$ENV_FILE"
    sed -i "s|^REDMINE_API_KEY=.*|REDMINE_API_KEY=${API_KEY}|" "$ENV_FILE"
    sed -i "s|^REDMINE_DEFAULT_USER_ID=.*|REDMINE_DEFAULT_USER_ID=1|" "$ENV_FILE"
    info ".env updated with local Redmine credentials"
  else
    warn ".env does not contain REDMINE_BASE_URL — skipping auto-update"
  fi
  php artisan config:clear --ansi 2>/dev/null || true
fi
