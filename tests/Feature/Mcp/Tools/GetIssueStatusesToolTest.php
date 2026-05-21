<?php

declare(strict_types=1);

use App\Mcp\Tools\GetIssueStatusesTool;
use App\Services\RedmineService;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request;

beforeEach(function (): void {
    config(['redmine.base_url' => 'https://redmine.test', 'redmine.api_key' => 'key']);
});

it('lists issue statuses with closed flag', function (): void {
    Http::fake([
        'redmine.test/issue_statuses.json' => Http::response([
            'issue_statuses' => [
                ['id' => 1, 'name' => 'New', 'is_closed' => false],
                ['id' => 2, 'name' => 'Assigned', 'is_closed' => false],
                ['id' => 5, 'name' => 'Closed', 'is_closed' => true],
            ],
        ], 200),
    ]);

    $response = (new GetIssueStatusesTool)->handle(new Request([]), new RedmineService);
    $text = $response->content()->toArray()['text'];

    expect($response->isError())->toBeFalse()
        ->and($text)->toContain('#1')
        ->and($text)->toContain('New')
        ->and($text)->toContain('#5')
        ->and($text)->toContain('Closed  [closed]');

    expect($text)->not->toContain('New  [closed]');
});

it('returns message when no statuses found', function (): void {
    Http::fake(['redmine.test/issue_statuses.json' => Http::response(['issue_statuses' => []], 200)]);

    $response = (new GetIssueStatusesTool)->handle(new Request([]), new RedmineService);

    expect($response->content()->toArray()['text'])->toContain('No issue statuses found');
});

it('returns error on api failure', function (): void {
    Http::fake(['redmine.test/issue_statuses.json' => Http::response(null, 500)]);

    $response = (new GetIssueStatusesTool)->handle(new Request([]), new RedmineService);

    expect($response->isError())->toBeTrue()
        ->and($response->content()->toArray()['text'])->toContain('Failed to retrieve issue statuses');
});
