<?php

declare(strict_types=1);

use App\Mcp\Tools\CheckUnloggedUsersTool;
use App\Mcp\Tools\CreateIssueTool;
use App\Mcp\Tools\GetAssignedIssuesTool;
use App\Mcp\Tools\GetIssueTool;
use App\Mcp\Tools\GetMyTimesTool;
use App\Mcp\Tools\GetProjectIssuesTool;
use App\Mcp\Tools\LogTimeTool;
use App\Mcp\Tools\UpdateIssueStatusTool;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;

dataset('tools', [
    'LogTimeTool' => LogTimeTool::class,
    'GetMyTimesTool' => GetMyTimesTool::class,
    'GetAssignedIssuesTool' => GetAssignedIssuesTool::class,
    'GetIssueTool' => GetIssueTool::class,
    'CreateIssueTool' => CreateIssueTool::class,
    'UpdateIssueStatusTool' => UpdateIssueStatusTool::class,
    'GetProjectIssuesTool' => GetProjectIssuesTool::class,
    'CheckUnloggedUsersTool' => CheckUnloggedUsersTool::class,
]);

it('returns a non-empty schema array', function (string $toolClass): void {
    $schema = resolve($toolClass)->schema(new JsonSchemaTypeFactory);

    expect($schema)->toBeArray()->not->toBeEmpty();
})->with('tools');
