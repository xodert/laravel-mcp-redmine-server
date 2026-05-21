# Redmine MCP Server

An [MCP (Model Context Protocol)](https://modelcontextprotocol.io) server built with Laravel 13 that exposes Redmine project-management capabilities to AI agents (Claude Code, Cursor, custom harness agents).

## Requirements

- PHP 8.4+
- Composer
- A running Redmine instance with REST API enabled
- SQLite (default) or any Laravel-supported database

## Quick start

```bash
git clone <repo-url> redmine-mcp
cd redmine-mcp
composer setup
```

`composer setup` installs dependencies, copies `.env.example` → `.env`, generates an app key, and runs migrations.

### Configure `.env`

```dotenv
REDMINE_BASE_URL=https://your-redmine.example.com
REDMINE_API_KEY=your_admin_api_key

# Optional: fallback user ID when /users/current.json returns 403
REDMINE_DEFAULT_USER_ID=
```

### Create a Sanctum token for your client

```bash
php artisan mcp:create-token harness-agent
```

Store the printed token — it is shown only once.

---

## Transports

### stdio — Claude Code / local development

Add to `.mcp.json`:

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

### HTTP — harness agent / Cursor

Start the server:

```bash
php artisan serve --port=8080
# or via Docker: docker compose up -d mcp
```

Every request requires two headers:

| Header | Value |
|---|---|
| `Authorization` | `Bearer <sanctum_token>` |
| `X-Redmine-API-Key` | `<user_redmine_token>` |

The `X-Redmine-API-Key` header overrides the `.env` admin token for that request — all Redmine operations are performed under the user's own identity (correct author, time entry attribution, scoped permissions).

**Cursor** (`.cursor/mcp.json`):

```json
{
  "mcpServers": {
    "redmine": {
      "url": "http://localhost:8080/mcp/redmine",
      "headers": {
        "Authorization": "Bearer <sanctum_token>",
        "X-Redmine-API-Key": "<your_redmine_api_key>"
      }
    }
  }
}
```

---

## Docker

```bash
# Start Redmine + MySQL + MCP server
docker compose up -d

# Seed Redmine with default data, test users and projects (first time)
bash docker/scripts/redmine-init.sh

# Create a harness token
docker compose exec mcp php artisan mcp:create-token harness-agent
```

Services:

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
| `log-time-tool` | No | Log work time on an issue |
| `create-issue-tool` | No | Create a new issue |
| `update-issue-status-tool` | No | Change issue status |
| `check-unlogged-users-tool` | Yes | Users with no time entries on a date |

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
| `activity_id` | integer | No | Activity ID — use `get-time-entry-activities-tool` (default: Redmine default activity) |
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
    CreateMcpToken.php          — php artisan mcp:create-token <name>
  Http/Middleware/
    InjectRedmineApiKey.php     — maps X-Redmine-API-Key header → config per request
  Mcp/
    Concerns/
      CastsApiData.php          — strOf / intOf / floatOf for API response arrays
      FetchesRedminePages.php   — bounded pagination + user name map for journal history
      ResolvesRedmineUser.php   — user ID resolution chain (request → API → env fallback)
    Servers/
      RedmineServer.php         — registers all tools
    Tools/
      *.php                     — one file per tool
  Services/
    AbstractHttpService.php     — base HTTP client (get/post/put, error handling, JSON helpers)
    RedmineService.php          — Redmine REST API client
config/
  redmine.php
routes/
  ai.php                        — Mcp::web() and Mcp::local() registration
docs/
  mcp-integration.md            — detailed integration guide
```
