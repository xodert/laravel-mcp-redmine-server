<?php

declare(strict_types=1);

use App\Mcp\Tools\GetTrackersTool;
use App\Services\RedmineService;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request;

beforeEach(function (): void {
    config(['redmine.base_url' => 'https://redmine.test', 'redmine.api_key' => 'key']);
});

it('lists trackers with id and name', function (): void {
    Http::fake([
        'redmine.test/trackers.json' => Http::response([
            'trackers' => [
                ['id' => 1, 'name' => 'Bug'],
                ['id' => 2, 'name' => 'Feature'],
                ['id' => 3, 'name' => 'Support'],
            ],
        ], 200),
    ]);

    $response = (new GetTrackersTool)->handle(new Request([]), new RedmineService);
    $text = $response->content()->toArray()['text'];

    expect($response->isError())->toBeFalse()
        ->and($text)->toContain('#1')
        ->and($text)->toContain('Bug')
        ->and($text)->toContain('#2')
        ->and($text)->toContain('Feature');
});

it('returns message when no trackers found', function (): void {
    Http::fake(['redmine.test/trackers.json' => Http::response(['trackers' => []], 200)]);

    $response = (new GetTrackersTool)->handle(new Request([]), new RedmineService);

    expect($response->content()->toArray()['text'])->toContain('No trackers found');
});

it('returns error on api failure', function (): void {
    Http::fake(['redmine.test/trackers.json' => Http::response(null, 500)]);

    $response = (new GetTrackersTool)->handle(new Request([]), new RedmineService);

    expect($response->isError())->toBeTrue()
        ->and($response->content()->toArray()['text'])->toContain('Failed to retrieve trackers');
});
