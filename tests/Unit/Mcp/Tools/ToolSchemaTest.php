<?php

declare(strict_types=1);

use App\Mcp\Tools\CheckUnloggedUsersTool;
use App\Mcp\Tools\CreateIssueTool;
use App\Mcp\Tools\GetAssignedIssuesTool;
use App\Mcp\Tools\GetIssuePrioritiesTool;
use App\Mcp\Tools\GetIssueStatusesTool;
use App\Mcp\Tools\GetIssueTool;
use App\Mcp\Tools\GetMyTimesTool;
use App\Mcp\Tools\GetProjectIssuesTool;
use App\Mcp\Tools\GetProjectsTool;
use App\Mcp\Tools\GetTimeEntryActivitiesTool;
use App\Mcp\Tools\GetTrackersTool;
use App\Mcp\Tools\GetUsersTool;
use App\Mcp\Tools\LogTimeTool;
use App\Mcp\Tools\UpdateIssueStatusTool;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;

dataset('toolsWithSchema', [
    'LogTimeTool' => LogTimeTool::class,
    'GetMyTimesTool' => GetMyTimesTool::class,
    'GetAssignedIssuesTool' => GetAssignedIssuesTool::class,
    'GetIssueTool' => GetIssueTool::class,
    'CreateIssueTool' => CreateIssueTool::class,
    'UpdateIssueStatusTool' => UpdateIssueStatusTool::class,
    'GetProjectIssuesTool' => GetProjectIssuesTool::class,
    'CheckUnloggedUsersTool' => CheckUnloggedUsersTool::class,
    'GetUsersTool' => GetUsersTool::class,
    'GetProjectsTool' => GetProjectsTool::class,
]);

dataset('toolsWithoutSchema', [
    'GetTrackersTool' => GetTrackersTool::class,
    'GetIssueStatusesTool' => GetIssueStatusesTool::class,
    'GetIssuePrioritiesTool' => GetIssuePrioritiesTool::class,
    'GetTimeEntryActivitiesTool' => GetTimeEntryActivitiesTool::class,
]);

it('returns a non-empty schema array', function (string $toolClass): void {
    $schema = resolve($toolClass)->schema(new JsonSchemaTypeFactory);

    expect($schema)->toBeArray()->not->toBeEmpty();
})->with('toolsWithSchema');

it('returns an empty schema array for parameterless reference tools', function (string $toolClass): void {
    $schema = resolve($toolClass)->schema(new JsonSchemaTypeFactory);

    expect($schema)->toBeArray()->toBeEmpty();
})->with('toolsWithoutSchema');
