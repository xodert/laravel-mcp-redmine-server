<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\CheckUnloggedUsersTool;
use App\Mcp\Tools\CreateIssueTool;
use App\Mcp\Tools\GetAssignedIssuesTool;
use App\Mcp\Tools\GetIssueTool;
use App\Mcp\Tools\GetMyTimesTool;
use App\Mcp\Tools\GetProjectIssuesTool;
use App\Mcp\Tools\GetProjectsTool;
use App\Mcp\Tools\GetUsersTool;
use App\Mcp\Tools\LogTimeTool;
use App\Mcp\Tools\UpdateIssueStatusTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Redmine PM Server')]
#[Version('1.0.0')]
#[Instructions(
    'MCP Server for Redmine project management. '.
    'Use this server to log time, manage issues, and track work. '.
    'Always confirm actions with the user before making changes in Redmine. '.
    'When logging time, ask for clarification if hours or task description are ambiguous.'
)]
final class RedmineServer extends Server
{
    protected array $tools = [
        GetProjectsTool::class,
        GetUsersTool::class,
        GetIssueTool::class,
        LogTimeTool::class,
        GetMyTimesTool::class,
        GetAssignedIssuesTool::class,
        CreateIssueTool::class,
        UpdateIssueStatusTool::class,
        GetProjectIssuesTool::class,
        CheckUnloggedUsersTool::class,
    ];
}
