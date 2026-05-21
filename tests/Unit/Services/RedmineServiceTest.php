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
            'total_count' => 2,
        ], 200),
    ]);

    $result = $this->service->getUserTimeLogs(5, '2026-05-18', '2026-05-19');

    expect($result['items'])->toHaveCount(2)
        ->and($result['items'][0]['hours'])->toEqual(3.0)
        ->and($result['total'])->toBe(2);
});

it('passes offset and limit to user time logs endpoint', function (): void {
    Http::fake([
        'redmine.test/time_entries.json*' => Http::response(['time_entries' => [], 'total_count' => 150], 200),
    ]);

    $result = $this->service->getUserTimeLogs(5, '2026-05-01', '2026-05-31', 100, 50);

    expect($result['total'])->toBe(150);

    Http::assertSent(fn ($req): bool => str_contains((string) $req->url(), 'offset=100')
        && str_contains((string) $req->url(), 'limit=50')
    );
});

it('passes user_id=me to user time logs endpoint', function (): void {
    Http::fake([
        'redmine.test/time_entries.json*' => Http::response(['time_entries' => [], 'total_count' => 0], 200),
    ]);

    $this->service->getUserTimeLogs(RedmineService::CURRENT_USER, '2026-05-01', '2026-05-31');

    Http::assertSent(fn ($req): bool => str_contains((string) $req->url(), 'user_id=me'));
});

it('passes assigned_to_id=me to assigned issues endpoint', function (): void {
    Http::fake([
        'redmine.test/issues.json*' => Http::response(['issues' => [], 'total_count' => 0], 200),
    ]);

    $this->service->getAssignedIssues(RedmineService::CURRENT_USER);

    Http::assertSent(fn ($req): bool => str_contains((string) $req->url(), 'assigned_to_id=me'));
});

it('retrieves assigned issues', function (): void {
    Http::fake([
        'redmine.test/issues.json*' => Http::response([
            'issues' => [
                ['id' => 55, 'subject' => 'Fix login', 'status' => ['name' => 'In Progress'], 'priority' => ['name' => 'High'], 'project' => ['id' => 1, 'name' => 'App']],
            ],
            'total_count' => 1,
        ], 200),
    ]);

    $result = $this->service->getAssignedIssues(5);

    expect($result['items'])->toHaveCount(1)
        ->and($result['items'][0]['subject'])->toBe('Fix login')
        ->and($result['total'])->toBe(1);
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
            'total_count' => 2,
        ], 200),
    ]);

    $result = $this->service->getProjectIssues(3, ['status' => 'open', 'limit' => 25]);

    expect($result['items'])->toHaveCount(2)
        ->and($result['total'])->toBe(2);
});

it('retrieves all users', function (): void {
    Http::fake([
        'redmine.test/users.json*' => Http::response([
            'users' => [
                ['id' => 1, 'login' => 'alice', 'firstname' => 'Alice', 'lastname' => 'Smith'],
                ['id' => 2, 'login' => 'bob', 'firstname' => 'Bob', 'lastname' => 'Jones'],
            ],
            'total_count' => 2,
        ], 200),
    ]);

    $result = $this->service->getUsers();

    expect($result['items'])->toHaveCount(2)
        ->and($result['items'][0]['login'])->toBe('alice')
        ->and($result['total'])->toBe(2);
});

it('passes offset and limit to users endpoint', function (): void {
    Http::fake([
        'redmine.test/users.json*' => Http::response(['users' => [], 'total_count' => 250], 200),
    ]);

    $result = $this->service->getUsers(100, 50);

    expect($result['total'])->toBe(250);

    Http::assertSent(fn ($req): bool => str_contains((string) $req->url(), 'offset=100')
        && str_contains((string) $req->url(), 'limit=50')
    );
});

it('retrieves time logs by date', function (): void {
    Http::fake([
        'redmine.test/time_entries.json*' => Http::response([
            'time_entries' => [
                ['id' => 10, 'hours' => 4.0, 'user' => ['id' => 1], 'spent_on' => '2026-05-19'],
            ],
            'total_count' => 1,
        ], 200),
    ]);

    $result = $this->service->getTimeLogsByDate('2026-05-19');

    expect($result['items'])->toHaveCount(1)
        ->and($result['items'][0]['hours'])->toEqual(4.0)
        ->and($result['total'])->toBe(1);
});

it('passes offset and limit to time logs endpoint', function (): void {
    Http::fake([
        'redmine.test/time_entries.json*' => Http::response(['time_entries' => [], 'total_count' => 200], 200),
    ]);

    $result = $this->service->getTimeLogsByDate('2026-05-19', 100, 50);

    expect($result['total'])->toBe(200);

    Http::assertSent(fn ($req): bool => str_contains((string) $req->url(), 'offset=100')
        && str_contains((string) $req->url(), 'limit=50')
    );
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
            'total_count' => 2,
        ], 200),
    ]);

    $result = $this->service->getProjects();

    expect($result['items'])->toHaveCount(2)
        ->and($result['items'][0]['name'])->toBe('Website')
        ->and($result['total'])->toBe(2);
});

it('passes offset and limit to projects endpoint', function (): void {
    Http::fake([
        'redmine.test/projects.json*' => Http::response(['projects' => [], 'total_count' => 300], 200),
    ]);

    $result = $this->service->getProjects(200, 50);

    expect($result['total'])->toBe(300);

    Http::assertSent(fn ($req): bool => str_contains((string) $req->url(), 'offset=200')
        && str_contains((string) $req->url(), 'limit=50')
    );
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
            'total_count' => 1,
        ], 200),
    ]);

    $result = $this->service->getAssignedIssues(5, 'open', 25, 0, '2026-05-01', 3);

    expect($result['items'])->toHaveCount(1);

    Http::assertSent(function ($req): bool {
        $url = (string) $req->url();

        return str_contains($url, 'project_id=3')
            && str_contains($url, 'updated_on')
            && str_contains($url, '2026-05-01');
    });
});

it('retrieves trackers', function (): void {
    Http::fake([
        'redmine.test/trackers.json' => Http::response([
            'trackers' => [
                ['id' => 1, 'name' => 'Bug'],
                ['id' => 2, 'name' => 'Feature'],
            ],
        ], 200),
    ]);

    $trackers = $this->service->getTrackers();

    expect($trackers)->toHaveCount(2)
        ->and($trackers[0]['name'])->toBe('Bug');
});

it('returns empty items when json list key is not an array', function (): void {
    Http::fake([
        'redmine.test/projects.json*' => Http::response(['projects' => null, 'total_count' => 0], 200),
    ]);

    $result = $this->service->getProjects();

    expect($result['items'])->toBeArray()->toBeEmpty()
        ->and($result['total'])->toBe(0);
});

it('returns empty array when json object key is not an array', function (): void {
    Http::fake([
        'redmine.test/issues/1.json*' => Http::response(['issue' => 'invalid'], 200),
    ]);

    $issue = $this->service->getIssue(1);

    expect($issue)->toBeArray()->toBeEmpty();
});
