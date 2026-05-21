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
            'total_count' => 1,
        ], 200),
    ]);

    $response = (new GetMyTimesTool)->handle(
        new Request(['redmine_user_id' => 5, 'date_from' => '2026-05-19', 'date_to' => '2026-05-19']),
        new RedmineService,
    );

    expect($response->isError())->toBeFalse()
        ->and($response->content()->toArray()['text'])->toContain('Issue #10');
});

it('shows pagination hint when more entries exist', function (): void {
    Http::fake([
        'redmine.test/time_entries.json*' => Http::response([
            'time_entries' => [
                ['id' => 1, 'hours' => 2.0, 'spent_on' => '2026-05-19', 'issue' => ['id' => 10], 'comments' => 'Work', 'activity' => ['name' => 'Dev']],
            ],
            'total_count' => 150,
        ], 200),
    ]);

    $response = (new GetMyTimesTool)->handle(
        new Request(['redmine_user_id' => 5, 'date_from' => '2026-05-01', 'date_to' => '2026-05-31', 'offset' => 0, 'limit' => 1]),
        new RedmineService,
    );

    $text = $response->content()->toArray()['text'];

    expect($response->isError())->toBeFalse()
        ->and($text)->toContain('150 total entries')
        ->and($text)->toContain('offset=1');

    Http::assertSent(fn ($req): bool => str_contains((string) $req->url(), 'offset=0')
        && str_contains((string) $req->url(), 'limit=1')
    );
});

it('uses user_id=me when redmine_user_id is omitted', function (): void {
    Http::fake([
        'redmine.test/time_entries.json*' => Http::response(['time_entries' => []], 200),
    ]);

    $response = (new GetMyTimesTool)->handle(new Request([]), new RedmineService);

    expect($response->isError())->toBeFalse();

    Http::assertSent(fn ($req): bool => str_contains((string) $req->url(), 'user_id=me'));
    Http::assertNotSent(fn ($req): bool => str_contains((string) $req->url(), '/users/current.json'));
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

it('returns error on api failure', function (): void {
    Http::fake(['redmine.test/time_entries.json*' => Http::response(null, 500)]);

    $response = (new GetMyTimesTool)->handle(
        new Request(['redmine_user_id' => 5]),
        new RedmineService,
    );

    expect($response->isError())->toBeTrue()
        ->and($response->content()->toArray()['text'])->toContain('Failed to retrieve time logs');
});
