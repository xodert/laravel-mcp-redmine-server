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
            'total_count' => 1,
        ], 200),
    ]);

    $response = (new GetAssignedIssuesTool)->handle(
        new Request(['redmine_user_id' => 5]),
        new RedmineService,
    );

    expect($response->isError())->toBeFalse()
        ->and($response->content()->toArray()['text'])->toContain('#55');
});

it('shows pagination hint when more issues exist', function (): void {
    Http::fake([
        'redmine.test/issues.json*' => Http::response([
            'issues' => [
                ['id' => 55, 'subject' => 'Fix login', 'status' => ['name' => 'In Progress'], 'priority' => ['name' => 'High'], 'project' => ['id' => 1, 'name' => 'App']],
            ],
            'total_count' => 42,
        ], 200),
    ]);

    $response = (new GetAssignedIssuesTool)->handle(
        new Request(['redmine_user_id' => 5, 'limit' => 1, 'offset' => 0]),
        new RedmineService,
    );

    $text = $response->content()->toArray()['text'];

    expect($response->isError())->toBeFalse()
        ->and($text)->toContain('42 total open issue(s)')
        ->and($text)->toContain('offset=1');
});

it('passes project_id as server-side filter', function (): void {
    Http::fake([
        'redmine.test/issues.json*' => Http::response([
            'issues' => [
                ['id' => 11, 'subject' => 'Task B', 'status' => ['name' => 'New'], 'priority' => ['name' => 'Normal'], 'project' => ['id' => 1, 'name' => 'App']],
            ],
        ], 200),
    ]);

    $response = (new GetAssignedIssuesTool)->handle(
        new Request(['redmine_user_id' => 5, 'project_id' => 1]),
        new RedmineService,
    );

    expect($response->content()->toArray()['text'])->toContain('#11');

    Http::assertSent(fn ($req): bool => str_contains((string) $req->url(), 'project_id=1'));
});

it('uses assigned_to_id=me when redmine_user_id is omitted', function (): void {
    Http::fake([
        'redmine.test/issues.json*' => Http::response(['issues' => []], 200),
    ]);

    $response = (new GetAssignedIssuesTool)->handle(
        new Request([]),
        new RedmineService,
    );

    expect($response->isError())->toBeFalse();

    Http::assertSent(fn ($req): bool => str_contains((string) $req->url(), 'assigned_to_id=me'));
    Http::assertNotSent(fn ($req): bool => str_contains((string) $req->url(), '/users/current.json'));
});

it('returns message when no issues found', function (): void {
    Http::fake(['redmine.test/issues.json*' => Http::response(['issues' => []], 200)]);

    $response = (new GetAssignedIssuesTool)->handle(
        new Request(['redmine_user_id' => 5]),
        new RedmineService,
    );

    expect($response->content()->toArray()['text'])->toContain('No open issues');
});

it('passes limit, offset and updated_after to the api', function (): void {
    Http::fake(['redmine.test/issues.json*' => Http::response(['issues' => []], 200)]);

    (new GetAssignedIssuesTool)->handle(
        new Request(['redmine_user_id' => 5, 'limit' => 10, 'offset' => 20, 'updated_after' => '2026-05-01']),
        new RedmineService,
    );

    Http::assertSent(function ($req): bool {
        $url = (string) $req->url();

        return str_contains($url, 'limit=10')
            && str_contains($url, 'offset=20')
            && str_contains($url, '2026-05-01');
    });
});

it('filters by project_id server-side', function (): void {
    Http::fake(['redmine.test/issues.json*' => Http::response(['issues' => []], 200)]);

    (new GetAssignedIssuesTool)->handle(
        new Request(['redmine_user_id' => 5, 'project_id' => 3]),
        new RedmineService,
    );

    Http::assertSent(fn ($req): bool => str_contains((string) $req->url(), 'project_id=3'));
});

it('maps status=all to status_id=* for Redmine API', function (): void {
    Http::fake(['redmine.test/issues.json*' => Http::response(['issues' => []], 200)]);

    (new GetAssignedIssuesTool)->handle(
        new Request(['redmine_user_id' => 5, 'status' => 'all']),
        new RedmineService,
    );

    Http::assertSent(fn ($req): bool => str_contains((string) $req->url(), 'status_id=%2A'));
});

it('returns error on api failure', function (): void {
    Http::fake(['redmine.test/issues.json*' => Http::response(null, 500)]);

    $response = (new GetAssignedIssuesTool)->handle(
        new Request(['redmine_user_id' => 5]),
        new RedmineService,
    );

    expect($response->isError())->toBeTrue()
        ->and($response->content()->toArray()['text'])->toContain('Failed to retrieve assigned issues');
});
