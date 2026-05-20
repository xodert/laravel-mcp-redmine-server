<?php

declare(strict_types=1);

use App\Mcp\Tools\UpdateIssueStatusTool;
use App\Services\RedmineService;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request;

beforeEach(function (): void {
    config(['redmine.base_url' => 'https://redmine.test', 'redmine.api_key' => 'key']);
});

it('shows changed properties before and after', function (): void {
    Http::fake([
        'redmine.test/issues/42.json' => Http::sequence()
            ->push(['issue' => ['id' => 42, 'subject' => 'Fix bug', 'status' => ['name' => 'New'],         'priority' => ['name' => 'Normal'], 'done_ratio' => 0]], 200)
            ->push(null, 200)
            ->push(['issue' => ['id' => 42, 'subject' => 'Fix bug', 'status' => ['name' => 'In Progress'], 'priority' => ['name' => 'Normal'], 'done_ratio' => 0]], 200),
    ]);

    $response = (new UpdateIssueStatusTool)->handle(
        new Request(['issue_id' => 42, 'status_id' => 2]),
        new RedmineService,
    );

    expect($response->isError())->toBeFalse()
        ->and($response->content()->toArray()['text'])
        ->toContain('#42')
        ->toContain('New')
        ->toContain('In Progress')
        ->toContain('→');
});

it('reports no changes when status is already set', function (): void {
    Http::fake([
        'redmine.test/issues/42.json' => Http::sequence()
            ->push(['issue' => ['id' => 42, 'subject' => 'Fix bug', 'status' => ['name' => 'In Progress'], 'priority' => ['name' => 'Normal'], 'done_ratio' => 0]], 200)
            ->push(null, 200)
            ->push(['issue' => ['id' => 42, 'subject' => 'Fix bug', 'status' => ['name' => 'In Progress'], 'priority' => ['name' => 'Normal'], 'done_ratio' => 0]], 200),
    ]);

    $response = (new UpdateIssueStatusTool)->handle(
        new Request(['issue_id' => 42, 'status_id' => 2]),
        new RedmineService,
    );

    expect($response->isError())->toBeFalse()
        ->and($response->content()->toArray()['text'])->toContain('No changes detected');
});

it('returns error when issue not found', function (): void {
    Http::fake(['redmine.test/issues/9999.json' => Http::response(null, 404)]);

    $response = (new UpdateIssueStatusTool)->handle(
        new Request(['issue_id' => 9999, 'status_id' => 5]),
        new RedmineService,
    );

    expect($response->isError())->toBeTrue()
        ->and($response->content()->toArray()['text'])->toContain('Failed to update issue status');
});
