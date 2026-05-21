# Redmine MCP Server — Integration Guide

## Architecture

```
Google Chat user
      │
  Harness agent  ──LDAP──▶  Redmine API key (per user)
      │
  POST /mcp/redmine
  Authorization: Bearer <sanctum_harness_token>
  X-Redmine-API-Key: <user_redmine_token>
      │
  InjectRedmineApiKey middleware
      │  overrides config('redmine.api_key') for this request
      ▼
  RedmineService (fresh instance per request)
      │
  Redmine REST API  ──▶  all calls made as the user
```

Every Redmine operation is performed under the identity of the actual user whose token is passed in `X-Redmine-API-Key`. The `.env` admin token is only a fallback for stdio transport.

---

## Transports

The server exposes two transports.

### stdio (Claude Code, local development)

Used by Claude Code and any client that spawns a subprocess. No auth required — the process runs under the server's OS user.

`.mcp.json`:
```json
{
  "mcpServers": {
    "redmine": {
      "command": "php",
      "args": ["/var/www/mcp-test/artisan", "mcp:start", "redmine"]
    }
  }
}
```

On WSL2 from a Windows host:
```json
{
  "mcpServers": {
    "redmine": {
      "command": "wsl.exe",
      "args": ["/usr/bin/php8.4", "/var/www/mcp-test/artisan", "mcp:start", "redmine"]
    }
  }
}
```

The Redmine API key is read from `REDMINE_API_KEY` in `.env`.

---

### HTTP (harness agent, Cursor, any HTTP client)

Endpoint: `POST /mcp/redmine`

#### Authentication

Two headers are required on every request:

| Header | Value | Purpose |
|---|---|---|
| `Authorization` | `Bearer <sanctum_token>` | Identifies the calling service (harness) |
| `X-Redmine-API-Key` | `<user_redmine_token>` | Redmine token of the acting user |

The `X-Redmine-API-Key` header is injected by `InjectRedmineApiKey` middleware into `config('redmine.api_key')` before `RedmineService` is instantiated. This means every Redmine API call within that request uses the user's personal token — operations are attributed to them in Redmine (author, time entry owner, etc.).

If `X-Redmine-API-Key` is absent, the server falls back to `REDMINE_API_KEY` from `.env`.

#### Creating a Sanctum token for the harness

```bash
php artisan mcp:create-token harness-agent
```

Store the printed token securely — it is shown only once. Pass it as the `Authorization: Bearer` header on every request.

#### MCP session flow

```
POST /mcp/redmine   {"method": "initialize", ...}
  ← MCP-Session-Id: <uuid>

POST /mcp/redmine   {"method": "tools/call", ...}
  MCP-Session-Id: <uuid>
  X-Redmine-API-Key: <user_token>   ← injected on every call
```

#### Example: initialize

```http
POST /mcp/redmine HTTP/1.1
Authorization: Bearer 1|abc...
Content-Type: application/json
X-Redmine-API-Key: <alice_redmine_api_key>

{
  "jsonrpc": "2.0",
  "method": "initialize",
  "id": 1,
  "params": {
    "protocolVersion": "2024-11-05",
    "capabilities": {},
    "clientInfo": { "name": "harness", "version": "1.0" }
  }
}
```

#### Cursor (`.cursor/mcp.json`)

```json
{
  "mcpServers": {
    "redmine": {
      "url": "https://your-domain.com/mcp/redmine",
      "headers": {
        "Authorization": "Bearer <sanctum_token>",
        "X-Redmine-API-Key": "<your_redmine_api_key>"
      }
    }
  }
}
```

---

## Running with Docker

The `docker-compose.yml` includes three services:

| Service | Port | Description |
|---|---|---|
| `mcp` | 8080 | Laravel MCP HTTP server |
| `redmine` | 3000 | Redmine instance |
| `db` | — | MySQL for Redmine (internal only) |

```bash
# Start everything
docker compose up -d

# Create a Sanctum token for the harness (first time)
docker compose exec mcp php artisan mcp:create-token harness-agent

# Init Redmine with seed data
bash scripts/redmine-init.sh
```

The `mcp` service uses `REDMINE_BASE_URL=http://redmine:3000` to reach Redmine over the internal Docker network. Environment variables are read from `.env` via Docker Compose variable substitution (`${APP_KEY}`, `${REDMINE_API_KEY}`).

---

## Environment variables

| Variable | Required | Description |
|---|---|---|
| `REDMINE_BASE_URL` | Yes | Redmine instance URL, e.g. `https://redmine.company.com` |
| `REDMINE_API_KEY` | Yes | Default Redmine API key (admin or personal) used by stdio transport |
| `REDMINE_DEFAULT_USER_ID` | No | Fallback user ID when `/users/current.json` returns 403 |

---

## Available tools

| Tool | Read-only | Description |
|---|---|---|
| `get-projects-tool` | Yes | List all projects with IDs |
| `get-trackers-tool` | Yes | List issue trackers (Bug, Feature, etc.) |
| `get-issue-statuses-tool` | Yes | List issue statuses with closed flag |
| `get-issue-priorities-tool` | Yes | List issue priorities with default flag |
| `get-time-entry-activities-tool` | Yes | List time entry activity types |
| `get-users-tool` | Yes | List all active users with IDs (requires admin key) |
| `get-issue-tool` | Yes | Full issue details + change history |
| `get-my-times-tool` | Yes | Time entries for a user in a date range |
| `get-assigned-issues-tool` | Yes | Open issues assigned to a user |
| `get-project-issues-tool` | Yes | Issues in a project with optional filters |
| `log-time-tool` | No | Log work time on an issue |
| `create-issue-tool` | No | Create a new issue |
| `update-issue-status-tool` | No | Change issue status |
| `check-unlogged-users-tool` | Yes | Find users with no time entries on a date |

Call the reference tools before using IDs in mutating tools — Redmine instance IDs are not portable across installations.

When `redmine_user_id` is omitted from tools that need it, the server resolves the current user automatically via `/users/current.json` using the token from `X-Redmine-API-Key`. Falls back to `REDMINE_DEFAULT_USER_ID` if the endpoint returns 403.

### Pagination

Redmine list endpoints return at most 100 records per request. Tools that expose lists accept `offset` and `limit` and include a hint when more records exist:

| Tool | Default limit | Pagination params |
|---|---|---|
| `get-users-tool` | 100 | `offset`, `limit` |
| `get-projects-tool` | 100 | `offset`, `limit` |
| `get-my-times-tool` | 100 | `offset`, `limit` |
| `get-assigned-issues-tool` | 25 | `offset`, `limit` |
| `get-project-issues-tool` | 25 | `offset`, `limit` |

`check-unlogged-users-tool` does **not** accept pagination params — it fetches all users and all time entries for the date internally to produce a complete diff.

---

## Code structure

```
app/
  Mcp/
    Concerns/
      CastsApiData.php        — strOf/intOf/floatOf helpers for API response arrays
      FetchesRedminePages.php — bounded pagination helpers for full user/time-entry fetch
      ResolvesRedmineUser.php — user ID resolution chain
    Servers/
      RedmineServer.php       — registers all tools
    Tools/
      *.php                   — one file per tool
  Services/
    AbstractHttpService.php   — base class: get/post/put + assertSuccessful + typed JSON helpers
    RedmineService.php        — all Redmine REST API calls
  Http/
    Middleware/
      InjectRedmineApiKey.php — reads X-Redmine-API-Key header → sets config per request
  Console/
    Commands/
      CreateMcpToken.php      — php artisan mcp:create-token <name>
```
