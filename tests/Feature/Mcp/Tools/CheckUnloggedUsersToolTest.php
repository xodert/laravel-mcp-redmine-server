<?php

declare(strict_types=1);

use App\Mcp\Tools\CheckUnloggedUsersTool;
use App\Services\RedmineService;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request;

beforeEach(function (): void {
    config(['redmine.base_url' => 'https://redmine.test', 'redmine.api_key' => 'key']);
});

it('returns users who have not logged time', function (): void {
    Http::fake([
        'redmine.test/users.json*' => Http::response([
            'users' => [
                ['id' => 1, 'firstname' => 'Alice', 'lastname' => 'Smith'],
                ['id' => 2, 'firstname' => 'Bob', 'lastname' => 'Jones'],
            ],
            'total_count' => 2,
        ], 200),
        'redmine.test/time_entries.json*' => Http::response([
            'time_entries' => [
                ['id' => 10, 'hours' => 3.0, 'user' => ['id' => 1], 'spent_on' => '2026-05-18'],
            ],
            'total_count' => 1,
        ], 200),
    ]);

    $response = (new CheckUnloggedUsersTool)->handle(
        new Request(['date' => '2026-05-18']),
        new RedmineService,
    );

    expect($response->isError())->toBeFalse()
        ->and($response->content()->toArray()['text'])
        ->toContain('Bob Jones')
        ->not->toContain('Alice Smith');
});

it('reports all logged when everyone has entries', function (): void {
    Http::fake([
        'redmine.test/users.json*' => Http::response([
            'users' => [['id' => 1, 'firstname' => 'Alice', 'lastname' => 'Smith']],
            'total_count' => 1,
        ], 200),
        'redmine.test/time_entries.json*' => Http::response([
            'time_entries' => [['id' => 1, 'hours' => 2.0, 'user' => ['id' => 1], 'spent_on' => '2026-05-18']],
            'total_count' => 1,
        ], 200),
    ]);

    $response = (new CheckUnloggedUsersTool)->handle(
        new Request(['date' => '2026-05-18']),
        new RedmineService,
    );

    expect($response->content()->toArray()['text'])->toContain('All users have logged time');
});

it('fetches all pages when users exceed one page', function (): void {
    // Page 1: users 1–2, total = 3
    // Page 2: user 3, total = 3
    Http::fake([
        'redmine.test/users.json*' => Http::sequence()
            ->push([
                'users' => [
                    ['id' => 1, 'firstname' => 'Alice', 'lastname' => 'Smith'],
                    ['id' => 2, 'firstname' => 'Bob', 'lastname' => 'Jones'],
                ],
                'total_count' => 3,
            ], 200)
            ->push([
                'users' => [
                    ['id' => 3, 'firstname' => 'Carol', 'lastname' => 'Doe'],
                ],
                'total_count' => 3,
            ], 200),
        'redmine.test/time_entries.json*' => Http::response([
            'time_entries' => [
                ['id' => 1, 'hours' => 1.0, 'user' => ['id' => 1], 'spent_on' => '2026-05-18'],
                ['id' => 2, 'hours' => 2.0, 'user' => ['id' => 2], 'spent_on' => '2026-05-18'],
            ],
            'total_count' => 2,
        ], 200),
    ]);

    $response = (new CheckUnloggedUsersTool)->handle(
        new Request(['date' => '2026-05-18']),
        new RedmineService,
    );

    expect($response->isError())->toBeFalse()
        ->and($response->content()->toArray()['text'])
        ->toContain('Carol Doe')
        ->not->toContain('Alice Smith')
        ->not->toContain('Bob Jones');
});

it('stops fetching users when page returns empty despite non-zero total', function (): void {
    Http::fake([
        'redmine.test/users.json*' => Http::response([
            'users' => [],
            'total_count' => 5,
        ], 200),
        'redmine.test/time_entries.json*' => Http::response(['time_entries' => [], 'total_count' => 0], 200),
    ]);

    $response = (new CheckUnloggedUsersTool)->handle(
        new Request(['date' => '2026-05-18']),
        new RedmineService,
    );

    expect($response->isError())->toBeFalse()
        ->and($response->content()->toArray()['text'])->toContain('All users have logged time');
});

it('stops fetching time entries when page returns empty despite non-zero total', function (): void {
    Http::fake([
        'redmine.test/users.json*' => Http::response([
            'users' => [['id' => 1, 'firstname' => 'Alice', 'lastname' => 'Smith']],
            'total_count' => 1,
        ], 200),
        'redmine.test/time_entries.json*' => Http::sequence()
            ->push([
                'time_entries' => [['id' => 1, 'hours' => 1.0, 'user' => ['id' => 99], 'spent_on' => '2026-05-18']],
                'total_count' => 3,
            ], 200)
            ->push(['time_entries' => [], 'total_count' => 3], 200),
    ]);

    $response = (new CheckUnloggedUsersTool)->handle(
        new Request(['date' => '2026-05-18']),
        new RedmineService,
    );

    expect($response->isError())->toBeFalse()
        ->and($response->content()->toArray()['text'])->toContain('Alice Smith');
});

it('returns error on api failure', function (): void {
    Http::fake(['redmine.test/users.json*' => Http::response(null, 500)]);

    $response = (new CheckUnloggedUsersTool)->handle(
        new Request(['date' => '2026-05-18']),
        new RedmineService,
    );

    expect($response->isError())->toBeTrue()
        ->and($response->content()->toArray()['text'])->toContain('Failed to check unlogged users');
});
