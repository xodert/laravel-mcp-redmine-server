<?php

declare(strict_types=1);

use App\Mcp\Tools\GetTimeEntryActivitiesTool;
use App\Services\RedmineService;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request;

beforeEach(function (): void {
    config(['redmine.base_url' => 'https://redmine.test', 'redmine.api_key' => 'key']);
});

it('lists activities with default flag', function (): void {
    Http::fake([
        'redmine.test/enumerations/time_entry_activities.json' => Http::response([
            'time_entry_activities' => [
                ['id' => 8, 'name' => 'Design', 'is_default' => false],
                ['id' => 9, 'name' => 'Development', 'is_default' => true],
                ['id' => 10, 'name' => 'Overtime', 'is_default' => false],
            ],
        ], 200),
    ]);

    $response = (new GetTimeEntryActivitiesTool)->handle(new Request([]), new RedmineService);
    $text = $response->content()->toArray()['text'];

    expect($response->isError())->toBeFalse()
        ->and($text)->toContain('#8')
        ->and($text)->toContain('Development')
        ->and($text)->toContain('[default]');
});

it('returns message when no activities found', function (): void {
    Http::fake(['redmine.test/enumerations/time_entry_activities.json' => Http::response(['time_entry_activities' => []], 200)]);

    $response = (new GetTimeEntryActivitiesTool)->handle(new Request([]), new RedmineService);

    expect($response->content()->toArray()['text'])->toContain('No time entry activities found');
});

it('returns error on api failure', function (): void {
    Http::fake(['redmine.test/enumerations/time_entry_activities.json' => Http::response(null, 500)]);

    $response = (new GetTimeEntryActivitiesTool)->handle(new Request([]), new RedmineService);

    expect($response->isError())->toBeTrue()
        ->and($response->content()->toArray()['text'])->toContain('Failed to retrieve time entry activities');
});
