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
        ], 200),
        'redmine.test/time_entries.json*' => Http::response([
            'time_entries' => [
                ['id' => 10, 'hours' => 3.0, 'user' => ['id' => 1], 'spent_on' => '2026-05-18'],
            ],
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
        ], 200),
        'redmine.test/time_entries.json*' => Http::response([
            'time_entries' => [['id' => 1, 'hours' => 2.0, 'user' => ['id' => 1], 'spent_on' => '2026-05-18']],
        ], 200),
    ]);

    $response = (new CheckUnloggedUsersTool)->handle(
        new Request(['date' => '2026-05-18']),
        new RedmineService,
    );

    expect($response->content()->toArray()['text'])->toContain('All users have logged time');
});
