<?php

declare(strict_types=1);

use App\Mcp\Tools\GetAssignedIssuesTool;
use App\Services\RedmineService;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request;

beforeEach(function (): void {
    config(['redmine.base_url' => 'https://redmine.test', 'redmine.api_key' => 'key']);
});

it('returns assigned issues', function (): void {
    Http::fake([
        'redmine.test/issues.json*' => Http::response([
            'issues' => [
                ['id' => 55, 'subject' => 'Fix login', 'status' => ['name' => 'In Progress'], 'priority' => ['name' => 'High'], 'project' => ['id' => 1, 'name' => 'App']],
            ],
        ], 200),
    ]);

    $response = (new GetAssignedIssuesTool)->handle(
        new Request(['redmine_user_id' => 5]),
        new RedmineService,
    );

    expect($response->isError())->toBeFalse()
        ->and($response->content()->toArray()['text'])->toContain('#55');
});

it('filters by project_id', function (): void {
    Http::fake([
        'redmine.test/issues.json*' => Http::response([
            'issues' => [
                ['id' => 10, 'subject' => 'Task A', 'status' => ['name' => 'New'], 'priority' => ['name' => 'Normal'], 'project' => ['id' => 2, 'name' => 'Other']],
                ['id' => 11, 'subject' => 'Task B', 'status' => ['name' => 'New'], 'priority' => ['name' => 'Normal'], 'project' => ['id' => 1, 'name' => 'App']],
            ],
        ], 200),
    ]);

    $response = (new GetAssignedIssuesTool)->handle(
        new Request(['redmine_user_id' => 5, 'project_id' => 1]),
        new RedmineService,
    );

    expect($response->content()->toArray()['text'])
        ->toContain('#11')
        ->not->toContain('#10');
});

it('resolves user id from redmine api key when not provided', function (): void {
    Http::fake([
        'redmine.test/users/current.json' => Http::response(['user' => ['id' => 7, 'login' => 'alice']], 200),
        'redmine.test/issues.json*' => Http::response(['issues' => []], 200),
    ]);

    $response = (new GetAssignedIssuesTool)->handle(
        new Request([]),
        new RedmineService,
    );

    expect($response->isError())->toBeFalse();

    Http::assertSent(fn ($req): bool => str_contains((string) $req->url(), '/users/current.json'));
});

it('returns message when no issues found', function (): void {
    Http::fake(['redmine.test/issues.json*' => Http::response(['issues' => []], 200)]);

    $response = (new GetAssignedIssuesTool)->handle(
        new Request(['redmine_user_id' => 5]),
        new RedmineService,
    );

    expect($response->content()->toArray()['text'])->toContain('No open issues');
});
