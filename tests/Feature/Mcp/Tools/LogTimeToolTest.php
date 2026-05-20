<?php

declare(strict_types=1);

use App\Mcp\Tools\LogTimeTool;
use App\Services\RedmineService;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request;

beforeEach(function (): void {
    config(['redmine.base_url' => 'https://redmine.test', 'redmine.api_key' => 'key']);
});

it('logs time with explicit activity_id', function (): void {
    Http::fake([
        'redmine.test/time_entries.json' => Http::response([
            'time_entry' => ['id' => 1, 'hours' => 1.5, 'spent_on' => '2026-05-19', 'issue' => ['id' => 42]],
        ], 201),
    ]);

    $response = (new LogTimeTool)->handle(
        new Request(['issue_id' => 42, 'hours' => 1.5, 'comment' => 'Work done', 'date' => '2026-05-19', 'activity_id' => 9]),
        new RedmineService,
    );

    expect($response->isError())->toBeFalse()
        ->and($response->content()->toArray()['text'])->toContain('Time logged successfully');
});

it('auto-resolves default activity when activity_id is omitted', function (): void {
    Http::fake([
        'redmine.test/enumerations/time_entry_activities.json' => Http::response([
            'time_entry_activities' => [
                ['id' => 9, 'name' => 'Design', 'is_default' => false],
                ['id' => 10, 'name' => 'Development', 'is_default' => true],
            ],
        ], 200),
        'redmine.test/time_entries.json' => Http::response([
            'time_entry' => ['id' => 1, 'hours' => 2.0, 'spent_on' => '2026-05-19', 'issue' => ['id' => 42]],
        ], 201),
    ]);

    $response = (new LogTimeTool)->handle(
        new Request(['issue_id' => 42, 'hours' => 2.0, 'comment' => 'Auto activity']),
        new RedmineService,
    );

    expect($response->isError())->toBeFalse()
        ->and($response->content()->toArray()['text'])->toContain('Time logged successfully');

    Http::assertSent(fn ($req): bool => str_contains((string) $req->url(), 'time_entry_activities'));
});

it('returns error response when redmine is unavailable', function (): void {
    Http::fake([
        'redmine.test/enumerations/time_entry_activities.json' => Http::response([
            'time_entry_activities' => [['id' => 9, 'name' => 'Design', 'is_default' => true]],
        ], 200),
        'redmine.test/time_entries.json' => Http::response(null, 503),
    ]);

    $response = (new LogTimeTool)->handle(
        new Request(['issue_id' => 1, 'hours' => 1.0, 'comment' => 'Test']),
        new RedmineService,
    );

    expect($response->isError())->toBeTrue()
        ->and($response->content()->toArray()['text'])->toContain('Failed to log time');
});
