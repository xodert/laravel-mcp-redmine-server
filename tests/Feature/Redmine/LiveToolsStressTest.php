<?php

declare(strict_types=1);

use App\Mcp\Tools\CheckUnloggedUsersTool;
use App\Mcp\Tools\CreateIssueTool;
use App\Mcp\Tools\GetAssignedIssuesTool;
use App\Mcp\Tools\GetIssuePrioritiesTool;
use App\Mcp\Tools\GetIssueStatusesTool;
use App\Mcp\Tools\GetIssueTool;
use App\Mcp\Tools\GetMyTimesTool;
use App\Mcp\Tools\GetProjectIssuesTool;
use App\Mcp\Tools\GetProjectsTool;
use App\Mcp\Tools\GetTimeEntryActivitiesTool;
use App\Mcp\Tools\GetTrackersTool;
use App\Mcp\Tools\GetUsersTool;
use App\Mcp\Tools\LogTimeTool;
use App\Mcp\Tools\UpdateIssueStatusTool;
use App\Services\RedmineService;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

/**
 * @return array<string, mixed>
 */
function liveFixtures(): array
{
    static $fixtures = null;

    if ($fixtures !== null) {
        return $fixtures;
    }

    $redmine = new RedmineService;
    $stressLabId = liveFindProjectIdByIdentifier($redmine, 'stress-lab');
    $aliceId = liveFindUserIdByLogin($redmine, 'alice');

    $fixtures = [
        'redmine' => $redmine,
        'stress_date' => liveStressDate(),
        'stress_lab_id' => $stressLabId,
        'alice_id' => $aliceId,
        'journal_issue_id' => liveFindJournalIssueId($redmine, $stressLabId),
    ];

    return $fixtures;
}

function liveStressDate(): string
{
    return now()->subDay()->toDateString();
}

function liveToolText(Response $response): string
{
    return $response->content()->toArray()['text'];
}

function liveFindUserIdByLogin(RedmineService $redmine, string $login): int
{
    $offset = 0;

    do {
        $page = $redmine->getUsers($offset, 100);

        foreach ($page['items'] as $user) {
            if (($user['login'] ?? '') === $login) {
                return (int) $user['id'];
            }
        }

        $offset += count($page['items']);
    } while ($offset < $page['total'] && $page['items'] !== []);

    throw new RuntimeException(sprintf("Redmine user '%s' not found — run docker/redmine seed first.", $login));
}

function liveFindProjectIdByIdentifier(RedmineService $redmine, string $identifier): int
{
    $offset = 0;

    do {
        $page = $redmine->getProjects($offset, 100);

        foreach ($page['items'] as $project) {
            if (($project['identifier'] ?? '') === $identifier) {
                return (int) $project['id'];
            }
        }

        $offset += count($page['items']);
    } while ($offset < $page['total'] && $page['items'] !== []);

    throw new RuntimeException(sprintf("Redmine project '%s' not found — run docker/redmine seed first.", $identifier));
}

function liveFindJournalIssueId(RedmineService $redmine, int $stressLabProjectId): int
{
    $offset = 0;

    do {
        $page = $redmine->getProjectIssues($stressLabProjectId, [
            'status' => 'all',
            'limit' => 100,
            'offset' => $offset,
        ]);

        foreach ($page['items'] as $issue) {
            if (($issue['subject'] ?? '') === 'Journal history stress issue') {
                return (int) $issue['id'];
            }
        }

        $offset += count($page['items']);
    } while ($offset < $page['total'] && $page['items'] !== []);

    throw new RuntimeException('Journal history stress issue not found — run docker/redmine seed first.');
}

beforeEach(function (): void {
    if (! env('REDMINE_STRESS_TEST')) {
        $this->markTestSkipped('Set REDMINE_STRESS_TEST=1 to run live Redmine stress tests.');
    }

    Http::allowStrayRequests();

    $baseUrl = config('redmine.base_url');
    $apiKey = config('redmine.api_key');

    if (! is_string($baseUrl) || $baseUrl === '' || ! is_string($apiKey) || $apiKey === '') {
        $this->markTestSkipped('REDMINE_BASE_URL and REDMINE_API_KEY must be configured in .env.');
    }

    try {
        Http::timeout(5)->withHeaders(['X-Redmine-API-Key' => $apiKey])->get(mb_rtrim($baseUrl, '/').'/users.json', ['limit' => 1])->throw();
    } catch (Throwable) {
        $this->markTestSkipped(sprintf('Redmine at %s is not reachable.', $baseUrl));
    }
});

it('paginates users against live seed data', function (): void {
    $response = (new GetUsersTool)->handle(
        new Request(['offset' => 0, 'limit' => 1]),
        new RedmineService,
    );

    $text = liveToolText($response);

    expect($response->isError())->toBeFalse()
        ->and($text)->toMatch('/\d+ total/')
        ->and($text)->toContain('offset=1');
});

it('paginates projects against live seed data', function (): void {
    $response = (new GetProjectsTool)->handle(
        new Request(['offset' => 0, 'limit' => 1]),
        new RedmineService,
    );

    $text = liveToolText($response);

    preg_match('/(\d+)\s+total/', $text, $matches);

    expect($response->isError())->toBeFalse()
        ->and((int) ($matches[1] ?? 0))->toBeGreaterThanOrEqual(100)
        ->and($text)->toContain('offset=1');
});

it('finds all unlogged users on the stress date', function (): void {
    $fixtures = liveFixtures();

    $response = (new CheckUnloggedUsersTool)->handle(
        new Request(['date' => $fixtures['stress_date']]),
        new RedmineService,
    );

    $text = liveToolText($response);

    expect($response->isError())->toBeFalse()
        ->and($text)->toContain('Bulk User111')
        ->and($text)->toContain('Bulk User120')
        ->and($text)->toContain('Carol')
        ->and($text)->toContain('Dave')
        ->and($text)->not->toContain('Bulk User001')
        ->and($text)->not->toContain('Bulk User110')
        ->and($text)->not->toContain('Alice');
});

it('paginates alice time entries from live seed data', function (): void {
    $fixtures = liveFixtures();
    $dateFrom = now()->subDay()->subDays(6)->toDateString();
    $dateTo = $fixtures['stress_date'];

    $response = (new GetMyTimesTool)->handle(
        new Request([
            'redmine_user_id' => $fixtures['alice_id'],
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'offset' => 0,
            'limit' => 25,
        ]),
        new RedmineService,
    );

    $text = liveToolText($response);

    expect($response->isError())->toBeFalse()
        ->and($text)->toMatch('/\d+ total/')
        ->and($text)->toContain('offset=25')
        ->and($text)->toContain('stress-alice-times');
});

it('paginates alice assigned issues from live seed data', function (): void {
    $fixtures = liveFixtures();

    $response = (new GetAssignedIssuesTool)->handle(
        new Request([
            'redmine_user_id' => $fixtures['alice_id'],
            'status' => 'open',
            'offset' => 0,
            'limit' => 10,
        ]),
        new RedmineService,
    );

    $text = liveToolText($response);

    expect($response->isError())->toBeFalse()
        ->and($text)->toMatch('/\d+ total/')
        ->and($text)->toContain('offset=10')
        ->and($text)->toContain('Stress assigned issue');
});

it('lists stress-lab project issues with pagination hint', function (): void {
    $fixtures = liveFixtures();

    $response = (new GetProjectIssuesTool)->handle(
        new Request([
            'project_id' => $fixtures['stress_lab_id'],
            'status' => 'all',
            'offset' => 0,
            'limit' => 5,
        ]),
        new RedmineService,
    );

    $text = liveToolText($response);

    preg_match('/(\d+)\s+total/', $text, $matches);

    expect($response->isError())->toBeFalse()
        ->and((int) ($matches[1] ?? 0))->toBeGreaterThanOrEqual(30)
        ->and($text)->toContain('offset=5')
        ->and($text)->toContain('Stress assigned issue');
});

it('renders journal history with resolved user names on live issue', function (): void {
    $fixtures = liveFixtures();

    $response = (new GetIssueTool)->handle(
        new Request(['issue_id' => $fixtures['journal_issue_id']]),
        new RedmineService,
    );

    $text = liveToolText($response);

    expect($response->isError())->toBeFalse()
        ->and($text)->toContain('Journal history stress issue')
        ->and($text)->toContain('── Change History ──')
        ->and($text)->toContain('Alice')
        ->and($text)->toContain('Bob')
        ->and($text)->toContain('Assigned to:');
});

it('loads reference data from live redmine', function (): void {
    $service = new RedmineService;

    $statuses = (new GetIssueStatusesTool)->handle(new Request([]), $service);
    $priorities = (new GetIssuePrioritiesTool)->handle(new Request([]), $service);
    $trackers = (new GetTrackersTool)->handle(new Request([]), $service);
    $activities = (new GetTimeEntryActivitiesTool)->handle(new Request([]), $service);

    expect($statuses->isError())->toBeFalse()
        ->and(liveToolText($statuses))->toContain('New')
        ->and($priorities->isError())->toBeFalse()
        ->and(liveToolText($priorities))->not->toBe('')
        ->and($trackers->isError())->toBeFalse()
        ->and(liveToolText($trackers))->not->toBe('')
        ->and($activities->isError())->toBeFalse()
        ->and(liveToolText($activities))->not->toBe('');
});

it('creates, logs time on, and updates status of a live issue', function (): void {
    $fixtures = liveFixtures();
    $service = new RedmineService;
    $subject = 'MCP stress test issue '.now()->format('Y-m-d H:i:s');

    $createResponse = (new CreateIssueTool)->handle(
        new Request([
            'project_id' => $fixtures['stress_lab_id'],
            'subject' => $subject,
            'description' => 'Ephemeral issue created by LiveToolsStressTest',
        ]),
        $service,
    );

    expect($createResponse->isError())->toBeFalse();

    $createText = liveToolText($createResponse);
    preg_match('/#(\d+)/', $createText, $matches);
    expect($matches)->not->toBeEmpty();

    $issueId = (int) $matches[1];

    $logResponse = (new LogTimeTool)->handle(
        new Request([
            'issue_id' => $issueId,
            'hours' => 0.25,
            'comment' => 'Live stress test time entry',
            'date' => now()->toDateString(),
        ]),
        $service,
    );

    expect($logResponse->isError())->toBeFalse()
        ->and(liveToolText($logResponse))->toContain('0.25');

    $statuses = $service->getIssueStatuses();
    $inProgressId = collect($statuses)->firstWhere('name', 'In Progress')['id'] ?? null;
    expect($inProgressId)->not->toBeNull();

    $updateResponse = (new UpdateIssueStatusTool)->handle(
        new Request([
            'issue_id' => $issueId,
            'status_id' => (int) $inProgressId,
        ]),
        $service,
    );

    expect($updateResponse->isError())->toBeFalse()
        ->and(liveToolText($updateResponse))->toContain('In Progress');
});
