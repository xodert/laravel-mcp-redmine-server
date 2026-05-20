<?php

declare(strict_types=1);

use App\Mcp\Tools\GetIssueTool;
use App\Services\RedmineService;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request;

beforeEach(function (): void {
    config(['redmine.base_url' => 'https://redmine.test', 'redmine.api_key' => 'key']);
});

it('returns issue details without journals', function (): void {
    Http::fake([
        'redmine.test/issues/10.json*' => Http::response([
            'issue' => [
                'id' => 10,
                'subject' => 'Fix login bug',
                'project' => ['name' => 'App'],
                'status' => ['name' => 'In Progress'],
                'priority' => ['name' => 'High'],
                'assigned_to' => ['name' => 'Alice'],
                'author' => ['name' => 'Bob'],
                'created_on' => '2026-05-01T00:00:00Z',
                'updated_on' => '2026-05-10T00:00:00Z',
                'done_ratio' => 50,
                'description' => 'Login fails on mobile',
                'journals' => [],
            ],
        ], 200),
    ]);

    $response = (new GetIssueTool)->handle(
        new Request(['issue_id' => 10]),
        new RedmineService,
    );

    expect($response->isError())->toBeFalse()
        ->and($response->content()->toArray()['text'])
        ->toContain('Issue #10')
        ->toContain('Fix login bug')
        ->toContain('In Progress')
        ->toContain('Alice')
        ->toContain('Login fails on mobile')
        ->toContain('No changes recorded yet.');
});

it('renders journal change history with resolved names', function (): void {
    Http::fake([
        'redmine.test/issues/20.json*' => Http::response([
            'issue' => [
                'id' => 20,
                'subject' => 'Refactor auth',
                'project' => ['name' => 'App'],
                'status' => ['name' => 'Resolved'],
                'priority' => ['name' => 'Normal'],
                'author' => ['name' => 'Carol'],
                'created_on' => '2026-05-01T00:00:00Z',
                'updated_on' => '2026-05-15T00:00:00Z',
                'done_ratio' => 100,
                'journals' => [
                    [
                        'user' => ['name' => 'Alice'],
                        'created_on' => '2026-05-05T00:00:00Z',
                        'notes' => 'Started working on this.',
                        'details' => [
                            ['name' => 'status_id', 'old_value' => '1', 'new_value' => '2'],
                        ],
                    ],
                ],
            ],
        ], 200),
        'redmine.test/issue_statuses.json' => Http::response(['issue_statuses' => [
            ['id' => 1, 'name' => 'New'],
            ['id' => 2, 'name' => 'In Progress'],
        ]], 200),
        'redmine.test/enumerations/issue_priorities.json' => Http::response(['issue_priorities' => [
            ['id' => 1, 'name' => 'Normal'],
        ]], 200),
        'redmine.test/users.json*' => Http::response(['users' => [
            ['id' => 1, 'login' => 'alice', 'firstname' => 'Alice', 'lastname' => 'Smith'],
        ]], 200),
    ]);

    $response = (new GetIssueTool)->handle(
        new Request(['issue_id' => 20]),
        new RedmineService,
    );

    $text = $response->content()->toArray()['text'];

    expect($response->isError())->toBeFalse()
        ->and($text)->toContain('Change History')
        ->and($text)->toContain('Alice')
        ->and($text)->toContain('Started working on this.')
        ->and($text)->toContain('New')
        ->and($text)->toContain('In Progress');
});

it('returns error when issue not found', function (): void {
    Http::fake(['redmine.test/issues/9999.json*' => Http::response(null, 404)]);

    $response = (new GetIssueTool)->handle(
        new Request(['issue_id' => 9999]),
        new RedmineService,
    );

    expect($response->isError())->toBeTrue()
        ->and($response->content()->toArray()['text'])->toContain('Failed to retrieve issue');
});
