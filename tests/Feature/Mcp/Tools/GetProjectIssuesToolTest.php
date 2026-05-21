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
            'total_count' => 2,
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

it('shows pagination hint when more issues exist', function (): void {
    Http::fake([
        'redmine.test/issues.json*' => Http::response([
            'issues' => [
                ['id' => 1, 'subject' => 'Task A', 'status' => ['name' => 'New'], 'priority' => ['name' => 'Normal'], 'assigned_to' => ['name' => 'Alice']],
            ],
            'total_count' => 50,
        ], 200),
    ]);

    $response = (new GetProjectIssuesTool)->handle(
        new Request(['project_id' => 3, 'limit' => 1, 'offset' => 0]),
        new RedmineService,
    );

    $text = $response->content()->toArray()['text'];

    expect($response->isError())->toBeFalse()
        ->and($text)->toContain('50 total issue(s) in project #3')
        ->and($text)->toContain('offset=1');
});

it('returns message when no issues', function (): void {
    Http::fake(['redmine.test/issues.json*' => Http::response(['issues' => []], 200)]);

    $response = (new GetProjectIssuesTool)->handle(
        new Request(['project_id' => 99]),
        new RedmineService,
    );

    expect($response->content()->toArray()['text'])->toContain('No issues found');
});

it('passes assigned_to_id, offset and updated_after to the api', function (): void {
    Http::fake(['redmine.test/issues.json*' => Http::response(['issues' => []], 200)]);

    (new GetProjectIssuesTool)->handle(
        new Request(['project_id' => 1, 'assigned_to_id' => 7, 'offset' => 25, 'updated_after' => '2026-05-01']),
        new RedmineService,
    );

    Http::assertSent(function ($req): bool {
        $url = (string) $req->url();

        return str_contains($url, 'assigned_to_id=7')
            && str_contains($url, 'offset=25')
            && str_contains($url, '2026-05-01');
    });
});

it('passes offset and updated_after to the api', function (): void {
    Http::fake(['redmine.test/issues.json*' => Http::response(['issues' => []], 200)]);

    (new GetProjectIssuesTool)->handle(
        new Request(['project_id' => 1, 'offset' => 25, 'updated_after' => '2026-05-01']),
        new RedmineService,
    );

    Http::assertSent(function ($req): bool {
        $url = (string) $req->url();

        return str_contains($url, 'offset=25')
            && str_contains($url, '2026-05-01');
    });
});

it('maps status=all to status_id=* for Redmine API', function (): void {
    Http::fake(['redmine.test/issues.json*' => Http::response(['issues' => []], 200)]);

    (new GetProjectIssuesTool)->handle(
        new Request(['project_id' => 1, 'status' => 'all']),
        new RedmineService,
    );

    Http::assertSent(fn ($req): bool => str_contains((string) $req->url(), 'status_id=%2A'));
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
