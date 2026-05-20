# Redmine MCP Server

An [MCP (Model Context Protocol)](https://modelcontextprotocol.io) server built with Laravel 11 that exposes Redmine project-management capabilities to AI agents.

---

## Requirements

- PHP 8.2+
- Composer
- A running Redmine instance with REST API enabled
- SQLite (default) or MySQL

---

## Installation

```bash
git clone <repo-url> mcp-test
cd mcp-test
composer install
cp .env.example .env
php artisan key:generate
```

### Configure `.env`

```dotenv
REDMINE_BASE_URL=https://your-redmine.example.com
REDMINE_API_KEY=your_redmine_api_key

# Set to sqlite for development
DB_CONNECTION=sqlite
```

### Run migrations

```bash
php artisan migrate
```

### Generate an MCP client token

```bash
php artisan mcp:create-token "my-ai-agent"
```

The command prints a **Sanctum token** — store it securely. Use it as a Bearer token when connecting to the MCP endpoint.

---

## Running

```bash
php artisan serve
```

The MCP endpoint is available at:

```
POST/GET http://localhost:8000/mcp/redmine
```

---

## Authentication

All requests must include a Sanctum token in the `Authorization` header:

```
Authorization: Bearer 1|<your-token-here>
```

---

## Available Tools

| Tool | Description |
|------|-------------|
| `log-time` | Log work time against a Redmine issue |
| `get-my-times` | Retrieve time entries for a user over a date range |
| `get-assigned-issues` | List issues assigned to a user |
| `create-issue` | Create a new issue in a project |
| `update-issue-status` | Change the status of an issue |
| `get-project-issues` | List issues in a project with filters |
| `check-unlogged-users` | Find users who haven't logged time on a given date |

### Tool Details

#### `log-time`

Log work hours for a Redmine issue.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `issue_id` | integer | yes | Redmine issue number |
| `hours` | number | yes | Hours spent (e.g. `1.5`) |
| `comment` | string | yes | Description of work done |
| `date` | string | no | Date `YYYY-MM-DD` (default: today) |
| `activity_id` | integer | no | Redmine activity/work-type ID |

---

#### `get-my-times`

Retrieve time log entries for a user.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `redmine_user_id` | integer | yes | Redmine user ID |
| `date_from` | string | no | Start date `YYYY-MM-DD` (default: start of current week) |
| `date_to` | string | no | End date `YYYY-MM-DD` (default: today) |

---

#### `get-assigned-issues`

List issues assigned to a user.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `redmine_user_id` | integer | yes | Redmine user ID |
| `status` | string | no | `open` / `closed` / `all` (default: `open`) |
| `project_id` | integer | no | Filter to a specific project |

---

#### `create-issue`

Create a new Redmine issue.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `project_id` | integer | yes | Project to create the issue in |
| `subject` | string | yes | Issue title |
| `description` | string | no | Detailed description |
| `assigned_to_id` | integer | no | Assign to this Redmine user |
| `priority_id` | integer | no | 1=Low, 2=Normal, 3=High, 4=Urgent, 5=Immediate |

---

#### `update-issue-status`

Change the status of an issue.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `issue_id` | integer | yes | Redmine issue number |
| `status_id` | integer | yes | 1=New, 2=In Progress, 3=Resolved, 4=Feedback, 5=Closed, 6=Rejected |

---

#### `get-project-issues`

List issues in a project.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `project_id` | integer | yes | Redmine project ID |
| `status` | string | no | `open` / `closed` / `all` (default: `open`) |
| `assigned_to_id` | integer | no | Filter by assignee |
| `limit` | integer | no | Max results, 1–100 (default: 25) |

---

#### `check-unlogged-users`

Find users who haven't logged time on a given date.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `date` | string | no | Date `YYYY-MM-DD` (default: yesterday) |
| `project_id` | integer | no | Reserved for future filtering |

---

## User Mapping

The `user_mappings` table maps external service users to Redmine user IDs:

```sql
INSERT INTO user_mappings (external_service, external_user_id, redmine_user_id)
VALUES ('claude', 'user-123', 42);
```

This supports future integrations (e.g. Google Chat bot) where each external user identity maps to a Redmine account.

---

## Logs

Redmine API requests are logged to `storage/logs/redmine-YYYY-MM-DD.log` for debugging.

---

## Testing

```bash
php artisan test tests/Unit/Services/RedmineServiceTest.php
```

All HTTP requests are mocked via `Http::fake()` — no live Redmine connection required.

---

## Example cURL request

```bash
# 1. Get a token
php artisan mcp:create-token "curl-test"

# 2. Initialize MCP session (SSE handshake)
curl -X GET http://localhost:8000/mcp/redmine \
  -H "Authorization: Bearer <token>" \
  -H "Accept: text/event-stream"

# 3. Call a tool (MCP JSON-RPC)
curl -X POST http://localhost:8000/mcp/redmine \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "tools/call",
    "params": {
      "name": "log-time",
      "arguments": {
        "issue_id": 42,
        "hours": 2.5,
        "comment": "Implemented dark mode toggle",
        "date": "2026-05-19"
      }
    }
  }'
```

---

## Project Structure

```
app/
  Console/Commands/
    CreateMcpToken.php       # php artisan mcp:create-token
  Mcp/
    Servers/
      RedmineServer.php      # MCP server definition
    Tools/
      LogTimeTool.php
      GetMyTimesTool.php
      GetAssignedIssuesTool.php
      CreateIssueTool.php
      UpdateIssueStatusTool.php
      GetProjectIssuesTool.php
      CheckUnloggedUsersTool.php
  Models/
    UserMapping.php          # external_user_id <-> redmine_user_id
  Services/
    RedmineService.php       # Redmine REST API client
config/
  redmine.php                # REDMINE_BASE_URL, REDMINE_API_KEY
routes/
  ai.php                     # MCP server registration
```
