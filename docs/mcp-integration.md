# Redmine MCP Server — Integration Guide

## Transports

The server supports two transports depending on the client.

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

The `X-Redmine-API-Key` header overrides the server's default `.env` token for that request. This allows the harness to act as any user by injecting their personal Redmine API key, resolved via LDAP.

If `X-Redmine-API-Key` is absent, the server falls back to `REDMINE_API_KEY` from `.env`.

#### Creating a Sanctum token for the harness

```bash
php artisan mcp:create-token harness-agent
```

Store the printed token securely — it is shown only once. Pass it as the `Authorization: Bearer` header.

#### Example request (MCP initialize)

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

## Environment variables

| Variable | Required | Description |
|---|---|---|
| `REDMINE_BASE_URL` | Yes | Redmine instance URL, e.g. `https://redmine.company.com` |
| `REDMINE_API_KEY` | Yes | Default Redmine API key (admin or personal) |
| `REDMINE_DEFAULT_USER_ID` | No | Fallback user ID when `/users/current.json` returns 403 |

---

## Available tools

| Tool | Read-only | Description |
|---|---|---|
| `get_projects` | Yes | List all projects with IDs |
| `get_users` | Yes | List all active users with IDs |
| `get_issue` | Yes | Full issue details + change history |
| `get_my_times` | Yes | Time entries for a user in a date range |
| `get_assigned_issues` | Yes | Open issues assigned to a user |
| `get_project_issues` | Yes | Issues in a project with filters |
| `log_time` | No | Log work time on an issue |
| `create_issue` | No | Create a new issue |
| `update_issue_status` | No | Change issue status |
| `check_unlogged_users` | Yes | Find users with no time entries on a date |
