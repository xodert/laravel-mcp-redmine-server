<?php

declare(strict_types=1);

use App\Mcp\Tools\GetMyTimesTool;
use App\Services\RedmineService;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request;

beforeEach(function (): void {
    config(['redmine.base_url' => 'https://redmine.test', 'redmine.api_key' => 'key']);
});

it('returns formatted time entries', function (): void {
    Http::fake([
        'redmine.test/time_entries.json*' => Http::response([
            'time_entries' => [
                ['id' => 1, 'hours' => 2.0, 'spent_on' => '2026-05-19', 'issue' => ['id' => 10], 'comments' => 'Design work', 'activity' => ['name' => 'Design']],
            ],
        ], 200),
    ]);

    $response = (new GetMyTimesTool)->handle(
        new Request(['redmine_user_id' => 5, 'date_from' => '2026-05-19', 'date_to' => '2026-05-19']),
        new RedmineService,
    );

    expect($response->isError())->toBeFalse()
        ->and($response->content()->toArray()['text'])->toContain('Issue #10');
});

it('resolves user id from /users/current.json when not provided', function (): void {
    Http::fake([
        'redmine.test/users/current.json' => Http::response(['user' => ['id' => 7, 'login' => 'alice']], 200),
        'redmine.test/time_entries.json*' => Http::response(['time_entries' => []], 200),
    ]);

    $response = (new GetMyTimesTool)->handle(new Request([]), new RedmineService);

    expect($response->isError())->toBeFalse();
    Http::assertSent(fn ($req): bool => str_contains((string) $req->url(), '/users/current.json'));
});

it('falls back to REDMINE_DEFAULT_USER_ID when /users/current.json returns 403', function (): void {
    config(['redmine.default_user_id' => 99]);

    Http::fake([
        'redmine.test/users/current.json' => Http::response(null, 403),
        'redmine.test/time_entries.json*' => Http::response(['time_entries' => []], 200),
    ]);

    $response = (new GetMyTimesTool)->handle(new Request([]), new RedmineService);

    expect($response->isError())->toBeFalse();
});

it('returns error when user id cannot be resolved at all', function (): void {
    config(['redmine.default_user_id' => null]);

    Http::fake(['redmine.test/users/current.json' => Http::response(null, 403)]);

    $response = (new GetMyTimesTool)->handle(new Request([]), new RedmineService);

    expect($response->isError())->toBeTrue()
        ->and($response->content()->toArray()['text'])->toContain('REDMINE_DEFAULT_USER_ID');
});

it('returns message when no entries found', function (): void {
    Http::fake(['redmine.test/time_entries.json*' => Http::response(['time_entries' => []], 200)]);

    $response = (new GetMyTimesTool)->handle(
        new Request(['redmine_user_id' => 5, 'date_from' => '2026-05-01', 'date_to' => '2026-05-01']),
        new RedmineService,
    );

    expect($response->isError())->toBeFalse()
        ->and($response->content()->toArray()['text'])->toContain('No time entries found');
});
