# Redmine MCP — Agent Usage Guide

## When to activate this skill

Activate whenever you need to interact with Redmine: create or delete issues, delete time entries, update statuses, add notes to or reassign issues, log time, list projects or users, or check time-tracking compliance.

---

## Authentication

The MCP server exposes two transports:

- **stdio (local)** — no auth needed; used by Claude Code via `.mcp.json`.
- **HTTP `/mcp/redmine`** — requires `Authorization: Bearer <token>` matching `MCP_AUTH_TOKEN` in the server's `.env`.

For Redmine API calls, the server uses `REDMINE_API_KEY` by default. To act as a specific Redmine user, pass their personal API key via the `X-Redmine-API-Key` header on the HTTP request. The `get-users-tool` requires an **admin** API key.

To target a different Redmine instance per request, pass `X-Redmine-Base-URL` on the HTTP request — it overrides `REDMINE_BASE_URL` for that request only. Use this when one MCP server proxies multiple Redmine installations.

As an alternative to `X-Redmine-API-Key`, you can authenticate with Redmine credentials by passing both `X-Redmine-Username` and `X-Redmine-Password` headers. When both are present, the server uses HTTP Basic Auth for all Redmine API calls for that request. If only one is provided, the server falls back to the API key.

---

## Tool Inventory

### Discovery (read-only — call these first)

| Tool | Purpose |
|------|---------|
| `get-projects-tool` | List all Redmine projects (id, name, identifier). Paginated via `offset`/`limit`. |
| `get-trackers-tool` | List tracker types (Bug, Feature, Support, …). |
| `get-issue-statuses-tool` | List available issue statuses with their IDs. |
| `get-issue-priorities-tool` | List priority levels with their IDs. |
| `get-time-entry-activities-tool` | List time entry activity types (Development, Testing, …). |
| `get-users-tool` | List active Redmine users. **Requires admin API key.** Paginated. |

### Issues

| Tool | Purpose |
|------|---------|
| `get-issue-tool` | Fetch full issue details, optionally with change history (`include_journals`). |
| `get-assigned-issues-tool` | Issues assigned to a user. Filterable by status (`open`/`closed`/`all`), project, `updated_after`. |
| `get-project-issues-tool` | Issues in a project. Filterable by status, assignee, `updated_after`. |
| `create-issue-tool` | Create a new issue in a project. |
| `update-issue-status-tool` | Change an issue's status. |
| `update-issue-tool` | Add a note (comment) to an issue and/or reassign it. Provide `notes`, `assigned_to_id`, or both. |
| `delete-issue-tool` | Permanently delete an issue. **Irreversible — always confirm with the user first.** |

### Time Tracking

| Tool | Purpose |
|------|---------|
| `log-time-tool` | Log hours worked on an issue, with optional date and activity type. |
| `get-my-times-tool` | Retrieve a user's time entries for a date range. Paginated. |
| `check-unlogged-users-tool` | Find users who have not logged any time on a given date. |
| `delete-time-entry-tool` | Permanently delete a time entry (shown as `ID:<n>` by `get-my-times-tool`). **Irreversible — always confirm with the user first.** |

---

## Prerequisite Pattern — Always Look Up IDs First

Redmine tools require integer IDs, not names. Resolve them with discovery tools before calling mutations.

| Before calling… | Call first… |
|----------------|------------|
| `create-issue-tool` | `get-projects-tool` → `get-trackers-tool` → `get-issue-priorities-tool` |
| `update-issue-status-tool` | `get-issue-statuses-tool` |
| `update-issue-tool` | `get-issue-tool` (confirm the issue and its current assignee) |
| `log-time-tool` (with activity) | `get-time-entry-activities-tool` |
| any tool with `assigned_to_id` | `get-users-tool` (admin key required) |
| `create-issue-tool` with `assigned_to_id` | verify the assignee is a project member — see **Assignee validation** below |
| `delete-issue-tool` | `get-issue-tool` (confirm the issue exists and user approves deletion) |
| `delete-time-entry-tool` | `get-my-times-tool` (find the entry ID and get user approval before deleting) |

---

## Common Workflows

### 1. Create an issue

```
get-projects-tool           → find project_id
get-trackers-tool           → find tracker_id (optional)
get-issue-priorities-tool   → find priority_id (optional)
get-users-tool              → find assigned_to_id (optional, admin key)
create-issue-tool           → create with resolved IDs
```

If `create-issue-tool` fails with `HTTP 422 — Assignee is invalid`, the assignee is not a member of that project. **Stop.** Do not retry without `assigned_to_id`. Report the failure and ask how to proceed (add the user to the project, change assignee, or skip that project).

### 2. Log time on an issue

```
get-issue-tool              → verify issue exists, get subject for confirmation
get-time-entry-activities-tool → find activity_id (optional)
log-time-tool               → log hours with date and comment
```

### 3. Close or resolve an issue

```
get-issue-statuses-tool     → find the status ID for "Closed" or "Resolved"
update-issue-status-tool    → apply the status change
```

### 4. Add a note or reassign an issue

```
get-issue-tool              → confirm the issue, current assignee, and history
get-users-tool              → find assigned_to_id (only when reassigning, admin key)
[user confirms the note text and/or new assignee]
update-issue-tool           → add the note and/or reassign
```

The response shows the assignee change (`old → new`) and the note that was added. If it returns **"Assignment was NOT applied"**, see **Assignee validation** below.

### 5. Delete an issue

```
get-issue-tool              → fetch details, show to user for confirmation
[user confirms]
delete-issue-tool           → permanently delete
```

### 6. Check time-tracking compliance

```
check-unlogged-users-tool   → find users with no time entries on a date
get-my-times-tool           → drill into a specific user's entries for context
```

### 7. Delete a time entry

```
get-my-times-tool           → find the entry ID (shown as ID:<n>)
[user confirms]
delete-time-entry-tool      → permanently delete the entry
```

---

## Assignee validation

When the user asks to assign an issue to a specific person:

1. Resolve the user ID with `get-users-tool`.
2. Call `create-issue-tool` (new issue) or `update-issue-tool` (existing issue) with `assigned_to_id`.
3. If Redmine returns `HTTP 422 — Assignee is invalid`, the user is **not a member of that project** (or the ID is wrong).
4. If `update-issue-tool` returns **"Assignment was NOT applied"**, Redmine accepted the request but silently ignored `assigned_to_id`. The acting API user cannot edit that issue — usually the role's **Edit issues** permission does not cover the issue's tracker, or the workflow makes Assignee read-only. Any note in the same call was still added.

**Forbidden workarounds — never do these:**

- Retry `create-issue-tool` **without** `assigned_to_id` to "complete" the batch.
- Create the issue unassigned and assume it can be fixed later.
- Silently skip reporting the failed project.
- Retry a failed `update-issue-tool` reassignment through another MCP identity, or report it as done.

Instead: stop, name the project and assignee, explain the likely cause, and ask the user how to proceed.

---

## Identity and permission boundaries

When the user specifies which MCP server identity should perform an action (e.g. `redmine-alice` for delete, `redmine-admin` for create):

- Perform that action **only** through the specified server.
- If the call fails with `403` or `422`, **do not silently switch** to another server (e.g. admin) to finish the job.
- Report the error, explain missing permissions or membership, and ask the user before using a different identity.

---

## Rules

1. **Never use curl or any HTTP client directly** — all Redmine interactions MUST go through the MCP server tools listed above. Do not call the Redmine REST API with curl, wget, fetch, or any other HTTP mechanism, even if a tool seems unavailable. If a needed operation has no corresponding tool, say so and ask the user — do not work around it.
2. **Confirm before mutating** — for create, update, delete, and log-time operations, summarise what you are about to do and get explicit user approval.
3. **Delete is irreversible** — always show the issue title and ID before calling `delete-issue-tool`, and the entry ID, date, hours, and comment before `delete-time-entry-tool`.
4. **Pagination** — list tools default to 25–100 results. Use `offset` + `limit` to page through large result sets.
5. **403 on get-users-tool** — means the configured API key is not an admin key; ask the user to provide one via `X-Redmine-API-Key`.
6. **Per-user context** — pass a user's personal Redmine API key as `X-Redmine-API-Key` on the HTTP request to perform actions on their behalf (time logging, issue assignment).
7. **No silent fallbacks on validation errors** — never weaken the original request (drop assignee, switch MCP identity, or substitute a different project) to make a tool call succeed. Stop and ask.
