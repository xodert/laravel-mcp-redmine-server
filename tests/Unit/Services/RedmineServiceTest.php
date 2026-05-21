<?php

declare(strict_types=1);

use App\Services\RedmineService;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'redmine.base_url' => 'https://redmine.test',
        'redmine.api_key' => 'test-api-key',
    ]);

    $this->service = new RedmineService;
});

it('logs time successfully', function (): void {
    Http::fake([
        'redmine.test/time_entries.json' => Http::response([
            'time_entry' => [
                'id' => 101,
                'hours' => 2.5,
                'comments' => 'Fixed the bug',
                'spent_on' => '2026-05-19',
                'issue' => ['id' => 42],
            ],
        ], 201),
    ]);

    $result = $this->service->logTime(42, 2.5, 'Fixed the bug', '2026-05-19');

    expect($result['id'])->toBe(101)
        ->and($result['hours'])->toBe(2.5);

    Http::assertSent(fn ($req): bool => $req->url() === 'https://redmine.test/time_entries.json'
        && $req->method() === 'POST'
        && $req->header('X-Redmine-API-Key')[0] === 'test-api-key'
    );
});

it('throws a RuntimeException when logTime receives an API error', function (): void {
    Http::fake([
        'redmine.test/time_entries.json' => Http::response(['errors' => ['Issue is invalid']], 422),
    ]);

    expect(fn () => $this->service->logTime(999, 1.0, 'Test'))
        ->toThrow(RuntimeException::class, 'logTime');
});

it('retrieves user time logs', function (): void {
    Http::fake([
        'redmine.test/time_entries.json*' => Http::response([
            'time_entries' => [
                ['id' => 1, 'hours' => 3.0, 'spent_on' => '2026-05-19', 'issue' => ['id' => 10]],
                ['id' => 2, 'hours' => 1.5, 'spent_on' => '2026-05-18', 'issue' => ['id' => 11]],
            ],
        ], 200),
    ]);

    $entries = $this->service->getUserTimeLogs(5, '2026-05-18', '2026-05-19');

    expect($entries)->toHaveCount(2)
        ->and($entries[0]['hours'])->toEqual(3.0);
});

it('retrieves assigned issues', function (): void {
    Http::fake([
        'redmine.test/issues.json*' => Http::response([
            'issues' => [
                ['id' => 55, 'subject' => 'Fix login', 'status' => ['name' => 'In Progress'], 'priority' => ['name' => 'High'], 'project' => ['id' => 1, 'name' => 'App']],
            ],
        ], 200),
    ]);

    $issues = $this->service->getAssignedIssues(5);

    expect($issues)->toHaveCount(1)
        ->and($issues[0]['subject'])->toBe('Fix login');
});

it('creates an issue successfully', function (): void {
    Http::fake([
        'redmine.test/issues.json' => Http::response([
            'issue' => [
                'id' => 200,
                'subject' => 'New feature',
                'project' => ['id' => 1, 'name' => 'App'],
            ],
        ], 201),
    ]);

    $issue = $this->service->createIssue(1, 'New feature', 'Implement dark mode', 5, 2);

    expect($issue['id'])->toBe(200)
        ->and($issue['subject'])->toBe('New feature');
});

it('updates issue status successfully', function (): void {
    Http::fake([
        'redmine.test/issues/55.json' => Http::response(null, 200),
    ]);

    $result = $this->service->updateIssueStatus(55, 3);

    expect($result['issue_id'])->toBe(55)
        ->and($result['status_id'])->toBe(3);

    Http::assertSent(fn ($req): bool => str_contains((string) $req->url(), '/issues/55.json')
        && $req->method() === 'PUT'
    );
});

it('throws when updating status of a non-existent issue', function (): void {
    Http::fake([
        'redmine.test/issues/9999.json' => Http::response(null, 404),
    ]);

    expect(fn () => $this->service->updateIssueStatus(9999, 5))
        ->toThrow(RuntimeException::class, 'not found');
});

it('retrieves project issues', function (): void {
    Http::fake([
        'redmine.test/issues.json*' => Http::response([
            'issues' => [
                ['id' => 1, 'subject' => 'Task A', 'status' => ['name' => 'New'], 'priority' => ['name' => 'Normal']],
                ['id' => 2, 'subject' => 'Task B', 'status' => ['name' => 'In Progress'], 'priority' => ['name' => 'High']],
            ],
        ], 200),
    ]);

    $issues = $this->service->getProjectIssues(3, ['status' => 'open', 'limit' => 25]);

    expect($issues)->toHaveCount(2);
});

it('retrieves all users', function (): void {
    Http::fake([
        'redmine.test/users.json*' => Http::response([
            'users' => [
                ['id' => 1, 'login' => 'alice', 'firstname' => 'Alice', 'lastname' => 'Smith'],
                ['id' => 2, 'login' => 'bob', 'firstname' => 'Bob', 'lastname' => 'Jones'],
            ],
        ], 200),
    ]);

    $users = $this->service->getUsers();

    expect($users)->toHaveCount(2)
        ->and($users[0]['login'])->toBe('alice');
});

it('retrieves time logs by date', function (): void {
    Http::fake([
        'redmine.test/time_entries.json*' => Http::response([
            'time_entries' => [
                ['id' => 10, 'hours' => 4.0, 'user' => ['id' => 1], 'spent_on' => '2026-05-19'],
            ],
        ], 200),
    ]);

    $entries = $this->service->getTimeLogsByDate('2026-05-19');

    expect($entries)->toHaveCount(1)
        ->and($entries[0]['hours'])->toEqual(4.0);
});

it('retrieves time entry activities', function (): void {
    Http::fake([
        'redmine.test/enumerations/time_entry_activities.json' => Http::response([
            'time_entry_activities' => [
                ['id' => 9, 'name' => 'Design'],
                ['id' => 10, 'name' => 'Development'],
            ],
        ], 200),
    ]);

    $activities = $this->service->getTimeEntryActivities();

    expect($activities)->toHaveCount(2)
        ->and($activities[1]['name'])->toBe('Development');
});

it('retrieves projects', function (): void {
    Http::fake([
        'redmine.test/projects.json*' => Http::response([
            'projects' => [
                ['id' => 1, 'name' => 'Website', 'identifier' => 'website'],
                ['id' => 2, 'name' => 'Mobile App', 'identifier' => 'mobile'],
            ],
        ], 200),
    ]);

    $projects = $this->service->getProjects();

    expect($projects)->toHaveCount(2)
        ->and($projects[0]['name'])->toBe('Website');
});

it('throws a RuntimeException on a 503 connection error', function (): void {
    Http::fake([
        'redmine.test/*' => Http::response(null, 503),
    ]);

    expect(fn () => $this->service->getProjects())
        ->toThrow(RuntimeException::class);
});

it('filters assigned issues by project_id and updated_after', function (): void {
    Http::fake([
        'redmine.test/issues.json*' => Http::response([
            'issues' => [['id' => 10, 'subject' => 'Task']],
        ], 200),
    ]);

    $issues = $this->service->getAssignedIssues(5, 'open', 25, 0, '2026-05-01', 3);

    expect($issues)->toHaveCount(1);

    Http::assertSent(function ($req): bool {
        $url = (string) $req->url();

        return str_contains($url, 'project_id=3')
            && str_contains($url, 'updated_on')
            && str_contains($url, '2026-05-01');
    });
});

it('returns empty array when json list key is not an array', function (): void {
    Http::fake([
        'redmine.test/projects.json*' => Http::response(['projects' => null], 200),
    ]);

    $projects = $this->service->getProjects();

    expect($projects)->toBeArray()->toBeEmpty();
});

it('returns empty array when json object key is not an array', function (): void {
    Http::fake([
        'redmine.test/issues/1.json*' => Http::response(['issue' => 'invalid'], 200),
    ]);

    $issue = $this->service->getIssue(1);

    expect($issue)->toBeArray()->toBeEmpty();
});
