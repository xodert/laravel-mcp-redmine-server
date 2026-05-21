<?php

declare(strict_types=1);

use App\Mcp\Tools\GetProjectIssuesTool;
use App\Services\RedmineService;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request;

beforeEach(function (): void {
    config(['redmine.base_url' => 'https://redmine.test', 'redmine.api_key' => 'key']);
});

it('returns project issues', function (): void {
    Http::fake([
        'redmine.test/issues.json*' => Http::response([
            'issues' => [
                ['id' => 1, 'subject' => 'Task A', 'status' => ['name' => 'New'], 'priority' => ['name' => 'Normal'], 'assigned_to' => ['name' => 'Alice']],
                ['id' => 2, 'subject' => 'Task B', 'status' => ['name' => 'In Progress'], 'priority' => ['name' => 'High'], 'assigned_to' => ['name' => 'Bob']],
            ],
        ], 200),
    ]);

    $response = (new GetProjectIssuesTool)->handle(
        new Request(['project_id' => 3]),
        new RedmineService,
    );

    expect($response->isError())->toBeFalse()
        ->and($response->content()->toArray()['text'])
        ->toContain('Task A')
        ->toContain('Task B');
});

it('returns message when no issues', function (): void {
    Http::fake(['redmine.test/issues.json*' => Http::response(['issues' => []], 200)]);

    $response = (new GetProjectIssuesTool)->handle(
        new Request(['project_id' => 99]),
        new RedmineService,
    );

    expect($response->content()->toArray()['text'])->toContain('No issues found');
});

it('returns error on api failure', function (): void {
    Http::fake(['redmine.test/issues.json*' => Http::response(null, 500)]);

    $response = (new GetProjectIssuesTool)->handle(
        new Request(['project_id' => 1]),
        new RedmineService,
    );

    expect($response->isError())->toBeTrue()
        ->and($response->content()->toArray()['text'])->toContain('Failed to retrieve project issues');
});
