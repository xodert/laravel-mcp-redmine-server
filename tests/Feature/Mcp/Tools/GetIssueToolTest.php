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

it('skips journal entries that have no details and no notes', function (): void {
    Http::fake([
        'redmine.test/issues/15.json*' => Http::response([
            'issue' => [
                'id' => 15,
                'subject' => 'Test issue',
                'project' => ['name' => 'App'],
                'status' => ['name' => 'New'],
                'priority' => ['name' => 'Normal'],
                'author' => ['name' => 'Alice'],
                'created_on' => '2026-05-01T00:00:00Z',
                'updated_on' => '2026-05-01T00:00:00Z',
                'done_ratio' => 0,
                'journals' => [
                    ['user' => ['name' => 'Bob'], 'created_on' => '2026-05-02T00:00:00Z', 'notes' => '', 'details' => []],
                ],
            ],
        ], 200),
    ]);

    $response = (new GetIssueTool)->handle(
        new Request(['issue_id' => 15]),
        new RedmineService,
    );

    $text = $response->content()->toArray()['text'];

    expect($response->isError())->toBeFalse()
        ->and($text)->toContain('Change History')
        ->and($text)->not->toContain('Bob');
});

it('renders all supported journal detail field types', function (): void {
    Http::fake([
        'redmine.test/issues/30.json*' => Http::response([
            'issue' => [
                'id' => 30,
                'subject' => 'Field types test',
                'project' => ['name' => 'App'],
                'status' => ['name' => 'New'],
                'priority' => ['name' => 'Normal'],
                'author' => ['name' => 'Alice'],
                'created_on' => '2026-05-01T00:00:00Z',
                'updated_on' => '2026-05-01T00:00:00Z',
                'done_ratio' => 0,
                'journals' => [
                    [
                        'user' => ['name' => 'Alice'],
                        'created_on' => '2026-05-05T00:00:00Z',
                        'notes' => '',
                        'details' => [
                            ['name' => 'priority_id',    'old_value' => '1', 'new_value' => '3'],
                            ['name' => 'assigned_to_id', 'old_value' => '1', 'new_value' => '2'],
                            ['name' => 'done_ratio',     'old_value' => '0', 'new_value' => '50'],
                            ['name' => 'subject',        'old_value' => 'Old title', 'new_value' => 'New title'],
                            ['name' => 'description',    'old_value' => 'Old description text', 'new_value' => 'New description text'],
                            ['name' => 'due_date',       'old_value' => '2026-01-01', 'new_value' => '2026-02-01'],
                            ['name' => 'custom_field',   'old_value' => 'old_val', 'new_value' => 'new_val'],
                        ],
                    ],
                    [
                        'user' => ['name' => 'Bob Jones'],
                        'created_on' => '2026-05-06T00:00:00Z',
                        'notes' => '',
                        'details' => [
                            ['name' => 'assigned_to_id', 'old_value' => '2', 'new_value' => '3'],
                            ['name' => 'assigned_to_id', 'old_value' => '3', 'new_value' => '1'],
                        ],
                    ],
                ],
            ],
        ], 200),
        'redmine.test/issue_statuses.json' => Http::response(['issue_statuses' => [
            ['id' => 1, 'name' => 'New'],
            ['id' => 3, 'name' => 'Resolved'],
        ]], 200),
        'redmine.test/enumerations/issue_priorities.json' => Http::response(['issue_priorities' => [
            ['id' => 1, 'name' => 'Normal'],
            ['id' => 3, 'name' => 'High'],
        ]], 200),
        'redmine.test/users.json*' => Http::response(['users' => [
            ['id' => 0, 'login' => 'invalid', 'firstname' => 'Skip', 'lastname' => 'Me'],
            ['id' => 1, 'login' => 'alice', 'firstname' => 'Alice', 'lastname' => 'Smith'],
            ['id' => 2, 'login' => 'bob',   'firstname' => 'Bob',   'lastname' => 'Jones'],
            ['id' => 3, 'login' => 'svc-bot', 'firstname' => '', 'lastname' => ''],
        ], 'total_count' => 4], 200),
    ]);

    $response = (new GetIssueTool)->handle(
        new Request(['issue_id' => 30]),
        new RedmineService,
    );

    $text = $response->content()->toArray()['text'];

    expect($response->isError())->toBeFalse()
        ->and($text)->toContain('Priority')
        ->and($text)->toContain('High')
        ->and($text)->toContain('Done')
        ->and($text)->toContain('50')
        ->and($text)->toContain('Subject')
        ->and($text)->toContain('New title')
        ->and($text)->toContain('Due date')
        ->and($text)->toContain('2026-02-01')
        ->and($text)->toContain('Assigned to:')
        ->and($text)->toContain('Alice Smith')
        ->and($text)->toContain('Bob Jones')
        ->and($text)->toContain('svc-bot')
        ->and($text)->toContain('new_val')
        ->and($text)->toContain('custom_field');
});
