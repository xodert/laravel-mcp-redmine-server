#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
CONTAINER="${REDMINE_CONTAINER:-redmine}"
REDMINE_URL="${REDMINE_URL:-http://localhost:3000}"
SEED_SCRIPT="/seed/seed.rb"
MAX_WAIT="${MAX_WAIT:-120}"

GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; NC='\033[0m'
info()  { echo -e "${GREEN}[stress]${NC} $*"; }
warn()  { echo -e "${YELLOW}[stress]${NC} $*"; }
error() { echo -e "${RED}[stress]${NC} $*" >&2; exit 1; }

cd "${ROOT}"

info "Waiting for Redmine at ${REDMINE_URL} (up to ${MAX_WAIT}s)..."
elapsed=0
until curl -sf -o /dev/null "${REDMINE_URL}"; do
  if [ "${elapsed}" -ge "${MAX_WAIT}" ]; then
    error "Redmine did not become ready within ${MAX_WAIT}s"
  fi
  sleep 3
  elapsed=$((elapsed + 3))
  echo -n "."
done
echo ""
info "Redmine is responding"

info "Running seed script inside container '${CONTAINER}'..."
docker exec "${CONTAINER}" bundle exec rails runner "${SEED_SCRIPT}" 2>&1 \
  | grep -v "^\(W,\|I,\|E,\)" \
  | grep -E "^\[seed\]|━|Redmine is ready|API key:|Stress date"

ENV_FILE="${ROOT}/.env"
if [ -f "${ENV_FILE}" ]; then
  API_KEY=$(docker exec "${CONTAINER}" bundle exec rails runner \
    "puts User.find_by(login: 'admin').api_key" 2>/dev/null | tail -1)

  if [ -n "${API_KEY}" ]; then
    if grep -q "^REDMINE_BASE_URL=" "${ENV_FILE}"; then
      sed -i "s|^REDMINE_BASE_URL=.*|REDMINE_BASE_URL=${REDMINE_URL}|" "${ENV_FILE}"
      sed -i "s|^REDMINE_API_KEY=.*|REDMINE_API_KEY=${API_KEY}|" "${ENV_FILE}"
      sed -i "s|^REDMINE_DEFAULT_USER_ID=.*|REDMINE_DEFAULT_USER_ID=1|" "${ENV_FILE}"
      info ".env updated with local Redmine credentials"
    fi
  fi
fi

php artisan config:clear --ansi 2>/dev/null || true

info "Running live MCP tool stress tests..."
REDMINE_STRESS_TEST=1 php artisan test --compact tests/Feature/Redmine/LiveToolsStressTest.php

info "All live stress tests passed."
