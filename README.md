# Redmine MCP Server

[![tests](https://github.com/xodert/laravel-mcp-redmine-server/actions/workflows/tests.yml/badge.svg)](https://github.com/xodert/laravel-mcp-redmine-server/actions/workflows/tests.yml)

An [MCP (Model Context Protocol)](https://modelcontextprotocol.io) server built with Laravel 13 that exposes Redmine project-management capabilities to AI agents (Claude Code, Cursor, custom harness agents).

## Requirements

- PHP 8.4+
- Composer
- A running Redmine instance with REST API enabled

No database required — authentication uses a single bearer token stored in `.env`.

## Quick start

```bash
git clone <repo-url> redmine-mcp
cd redmine-mcp
composer install
cp .env.example .env
php artisan key:generate
```

### Configure `.env`

```dotenv
# Redmine instance
REDMINE_BASE_URL=https://your-redmine.example.com
REDMINE_API_KEY=your_admin_api_key

# Optional: fallback user ID when /users/current.json returns 403
REDMINE_DEFAULT_USER_ID=

# Bearer token that clients must send to use the HTTP endpoint
MCP_AUTH_TOKEN=your-secret-token
```

> **Where to find your Redmine API key:** open `https://your-redmine.example.com/my/account` → **API access key** (right sidebar). If the field is missing, ask your Redmine administrator to enable REST API in **Administration → Settings → API**.

Generate a random `MCP_AUTH_TOKEN`:

```bash
php artisan mcp:create-token
```

The command prints a 64-character token — copy it to `.env` and to your MCP client config.

---

## Transports

### stdio — Claude Code / local development

No authentication required. Add to `.mcp.json`:

```json
{
  "mcpServers": {
    "redmine": {
      "command": "php",
      "args": ["/path/to/artisan", "mcp:start", "redmine"]
    }
  }
}
```

### HTTP — harness agent / Cursor / remote clients

Start the server:

```bash
php artisan serve --port=8080
# or via Docker: docker compose up -d mcp
```

Every request requires:

| Header | Value |
|---|---|
| `Authorization` | `Bearer <MCP_AUTH_TOKEN>` |
| `X-Redmine-API-Key` | *(optional)* personal Redmine API key — overrides `REDMINE_API_KEY` for this request |
| `X-Redmine-Username` | *(optional)* Redmine username for Basic Auth — use together with `X-Redmine-Password` |
| `X-Redmine-Password` | *(optional)* Redmine password for Basic Auth — use together with `X-Redmine-Username` |
| `X-Redmine-Base-URL` | *(optional)* Redmine base URL — overrides `REDMINE_BASE_URL` for this request |

`X-Redmine-API-Key` scopes all Redmine operations to the user's own identity (correct author, time entry attribution, scoped permissions).

If `X-Redmine-Username` and `X-Redmine-Password` are both provided, the server uses HTTP Basic Auth for Redmine API calls instead of the API key. This is useful when the user doesn't know their Redmine API token.

`X-Redmine-Base-URL` lets a single MCP server instance proxy requests to different Redmine installations.

**Claude Code** (`.mcp.json`):

```json
{
  "mcpServers": {
    "redmine-production": {
      "type": "streamable-http",
      "url": "http://localhost:8080/mcp/redmine",
      "headers": {
        "Authorization": "Bearer <MCP_AUTH_TOKEN>",
        "X-Redmine-API-Key": "<your_redmine_api_key>",
        "X-Redmine-Base-URL": "https://your-redmine.example.com"
      }
    }
  }
}
```

**Cursor** (`.cursor/mcp.json`):

```json
{
  "mcpServers": {
    "redmine-production": {
      "url": "http://localhost:8080/mcp/redmine",
      "headers": {
        "Authorization": "Bearer <MCP_AUTH_TOKEN>",
        "X-Redmine-API-Key": "<your_redmine_api_key>",
        "X-Redmine-Base-URL": "https://your-redmine.example.com"
      }
    }
  }
}
```

---

## Docker

### Production — MCP server only

`docker-compose.yml` runs only the MCP server. It connects to an external Redmine instance configured via environment variables.

```bash
# Set required vars (or put them in .env)
export APP_KEY=...
export MCP_AUTH_TOKEN=...
export REDMINE_BASE_URL=https://your-redmine.example.com
export REDMINE_API_KEY=...   # or use REDMINE_USERNAME + REDMINE_PASSWORD

docker compose up -d
```

| Service | Port | Description |
|---|---|---|
| `mcp` | 8080 | Laravel MCP HTTP server |

### Local / test — full stack

`docker-compose.local.yml` starts Redmine + MySQL + MCP server for local development and integration testing.

```bash
# Start Redmine + MySQL + MCP server
docker compose -f docker-compose.local.yml up -d

# Seed Redmine with default data, test users and projects (first time)
bash docker/scripts/redmine-init.sh
```

| Service | Port | Description |
|---|---|---|
| `mcp` | 8080 | Laravel MCP HTTP server |
| `redmine` | 3000 | Redmine |
| `db` | — | MySQL for Redmine (internal) |

---

## Available tools

All tools that accept `redmine_user_id` resolve it automatically when omitted: first via `/users/current.json` (personal API key), then via `REDMINE_DEFAULT_USER_ID`.

| Tool | Read-only | Description |
|---|---|---|
| `get-projects-tool` | Yes | List all projects with numeric IDs |
| `get-trackers-tool` | Yes | List issue trackers (Bug, Feature, etc.) |
| `get-issue-statuses-tool` | Yes | List issue statuses with closed flag |
| `get-issue-priorities-tool` | Yes | List issue priorities with default flag |
| `get-time-entry-activities-tool` | Yes | List time entry activity types |
| `get-users-tool` | Yes | List active users (requires admin key) |
| `get-issue-tool` | Yes | Full issue details + change history |
| `get-my-times-tool` | Yes | Time entries for a user in a date range |
| `get-assigned-issues-tool` | Yes | Open issues assigned to a user |
| `get-project-issues-tool` | Yes | Issues in a project with filters |
| `check-unlogged-users-tool` | Yes | Users with no time entries on a date |
| `log-time-tool` | No | Log work time on an issue |
| `create-issue-tool` | No | Create a new issue |
| `update-issue-status-tool` | No | Change issue status |
| `update-issue-tool` | No | Add a note and/or reassign an issue |
| `delete-issue-tool` | No | Permanently delete an issue (irreversible) |
| `delete-time-entry-tool` | No | Permanently delete a time entry (irreversible) |

Call the reference tools (`get-trackers-tool`, `get-issue-priorities-tool`, `get-issue-statuses-tool`, `get-time-entry-activities-tool`) before using IDs in mutating tools — Redmine instance IDs are not portable across installations.

List tools that return more than one page include a pagination hint in the response (e.g. `Use offset=100 for the next page`). Pass `offset` and `limit` to fetch subsequent pages.

### Tool parameters

#### `get-users-tool`

| Parameter | Type | Required | Description |
|---|---|---|---|
| `offset` | integer | No | Number of users to skip (default: 0) |
| `limit` | integer | No | 1–100 (default: 100) |

#### `get-projects-tool`

| Parameter | Type | Required | Description |
|---|---|---|---|
| `offset` | integer | No | Number of projects to skip (default: 0) |
| `limit` | integer | No | 1–100 (default: 100) |

#### `log-time-tool`

| Parameter | Type | Required | Description |
|---|---|---|---|
| `issue_id` | integer | Yes | Redmine issue number |
| `hours` | number | Yes | Hours spent (e.g. `1.5`) |
| `comment` | string | Yes | Description of work done |
| `date` | string | No | `YYYY-MM-DD` (default: today) |
| `activity_id` | integer | No | Activity ID — use `get-time-entry-activities-tool` |
| `user_id` | integer | No | Log on behalf of another user (admin key required) |

#### `get-my-times-tool`

| Parameter | Type | Required | Description |
|---|---|---|---|
| `redmine_user_id` | integer | No | Defaults to current API key owner |
| `date_from` | string | No | `YYYY-MM-DD` (default: start of week) |
| `date_to` | string | No | `YYYY-MM-DD` (default: today) |
| `offset` | integer | No | Number of entries to skip (default: 0) |
| `limit` | integer | No | 1–100 (default: 100) |

#### `get-assigned-issues-tool`

| Parameter | Type | Required | Description |
|---|---|---|---|
| `redmine_user_id` | integer | No | Defaults to current API key owner |
| `status` | string | No | `open` / `closed` / `all` (default: `open`) |
| `project_id` | integer | No | Filter to a specific project |
| `updated_after` | string | No | `YYYY-MM-DD` — only issues updated on or after this date |
| `offset` | integer | No | Number of issues to skip (default: 0) |
| `limit` | integer | No | 1–100 (default: 25) |

#### `create-issue-tool`

| Parameter | Type | Required | Description |
|---|---|---|---|
| `project_id` | integer | Yes | Target project |
| `subject` | string | Yes | Issue title |
| `description` | string | No | Detailed description |
| `assigned_to_id` | integer | No | Redmine user ID — use `get-users-tool` |
| `tracker_id` | integer | No | Tracker ID — use `get-trackers-tool` |
| `priority_id` | integer | No | Priority ID — use `get-issue-priorities-tool` |

#### `update-issue-status-tool`

| Parameter | Type | Required | Description |
|---|---|---|---|
| `issue_id` | integer | Yes | Redmine issue number |
| `status_id` | integer | Yes | Status ID — use `get-issue-statuses-tool` |

#### `update-issue-tool`

At least one of `notes` or `assigned_to_id` is required.

| Parameter | Type | Required | Description |
|---|---|---|---|
| `issue_id` | integer | Yes | Redmine issue number |
| `notes` | string | No | Comment to add to the issue history |
| `assigned_to_id` | integer | No | Redmine user ID — use `get-users-tool` |

#### `delete-issue-tool`

| Parameter | Type | Required | Description |
|---|---|---|---|
| `issue_id` | integer | Yes | Redmine issue number to permanently delete |

#### `delete-time-entry-tool`

| Parameter | Type | Required | Description |
|---|---|---|---|
| `time_entry_id` | integer | Yes | Redmine time entry ID to permanently delete. `get-my-times-tool` prints it as `ID:<n>`. |

#### `get-project-issues-tool`

| Parameter | Type | Required | Description |
|---|---|---|---|
| `project_id` | integer | Yes | Redmine project ID |
| `status` | string | No | `open` / `closed` / `all` (default: `open`) |
| `assigned_to_id` | integer | No | Filter by assignee |
| `updated_after` | string | No | `YYYY-MM-DD` — only issues updated on or after this date |
| `offset` | integer | No | Number of issues to skip (default: 0) |
| `limit` | integer | No | 1–100 (default: 25) |

#### `check-unlogged-users-tool`

| Parameter | Type | Required | Description |
|---|---|---|---|
| `date` | string | No | `YYYY-MM-DD` (default: yesterday) |

Automatically fetches all pages of users and time entries for the date to compute the diff. No `offset`/`limit` parameters — the tool handles pagination internally.

---

## Development

```bash
# Run tests
php artisan test --compact

# Static analysis
vendor/bin/phpstan analyse

# Lint / format
vendor/bin/pint --dirty

# All checks at once
composer test
```

Redmine HTTP calls are mocked via `Http::fake()` — no live Redmine connection required for tests.

Logs for Redmine API calls: `storage/logs/redmine-YYYY-MM-DD.log`

---

## Project structure

```
app/
  Console/Commands/
    CreateMcpToken.php              — php artisan mcp:create-token
  Http/Middleware/
    VerifyMcpToken.php              — validates Authorization: Bearer <MCP_AUTH_TOKEN>
    InjectRedmineApiKey.php         — maps X-Redmine-API-Key / X-Redmine-Base-URL headers → config per request
  Mcp/
    Concerns/
      CastsApiData.php              — strOf / intOf / floatOf for API response arrays
      FetchesRedminePages.php       — bounded pagination + user name map for journal history
      RegistersForRedmineAdmin.php  — shouldRegister() — skips tool when API key lacks admin access
      ResolvesRedmineUser.php       — user ID resolution chain (request → API → env fallback)
    Servers/
      RedmineServer.php             — registers all tools
    Tools/
      *.php                         — one file per tool
  Services/
    AbstractHttpService.php         — base HTTP client (get/post/put/delete, error handling, JSON helpers)
    RedmineService.php              — Redmine REST API client
config/
  mcp.php                           — MCP_AUTH_TOKEN
  redmine.php                       — REDMINE_BASE_URL, REDMINE_API_KEY, REDMINE_DEFAULT_USER_ID
routes/
  ai.php                            — Mcp::web() and Mcp::local() registration
```

---

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for local setup, the checks CI enforces, and how to get a change merged. Bug reports and feature requests are welcome through the [issue templates](https://github.com/xodert/laravel-mcp-redmine-server/issues/new/choose).

## Security

Please report vulnerabilities privately — see [SECURITY.md](SECURITY.md).

## License

Released under the [MIT License](LICENSE).
