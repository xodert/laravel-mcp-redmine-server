<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

final readonly class RedmineService extends AbstractHttpService
{
    public const CURRENT_USER = 'me';

    private string $baseUrl;

    private string $apiKey;

    public function __construct()
    {
        $baseUrl = config('redmine.base_url');
        $apiKey = config('redmine.api_key');
        $this->baseUrl = mb_rtrim(is_string($baseUrl) ? $baseUrl : '', '/');
        $this->apiKey = is_string($apiKey) ? $apiKey : '';
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     * @throws RuntimeException
     */
    public function logTime(int $issueId, float $hours, string $comment, ?string $date = null, ?int $activityId = null, ?int $userId = null): array
    {
        $payload = [
            'time_entry' => array_filter([
                'issue_id' => $issueId,
                'hours' => $hours,
                'comments' => $comment,
                'spent_on' => $date ?? now()->toDateString(),
                'activity_id' => $activityId,
                'user_id' => $userId,
            ], fn (float|string|int|null $v): bool => $v !== null),
        ];

        $response = $this->post('/time_entries.json', $payload);
        $this->assertSuccessful(__FUNCTION__, $response);

        return $this->jsonObject($response, 'time_entry');
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     *
     * @throws ConnectionException
     * @throws RuntimeException
     */
    public function getUserTimeLogs(int|string $redmineUserId, string $dateFrom, string $dateTo, int $offset = 0, int $limit = 100): array
    {
        $response = $this->get('/time_entries.json', [
            'user_id' => $redmineUserId,
            'from' => $dateFrom,
            'to' => $dateTo,
            'limit' => $limit,
            'offset' => $offset,
        ]);
        $this->assertSuccessful(__FUNCTION__, $response);

        $items = $this->jsonList($response, 'time_entries');
        $rawTotal = $response->json('total_count');

        return ['items' => $items, 'total' => is_int($rawTotal) ? $rawTotal : count($items)];
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     *
     * @throws ConnectionException
     * @throws RuntimeException
     */
    public function getAssignedIssues(
        int|string $redmineUserId,
        string $status = 'open',
        int $limit = 25,
        int $offset = 0,
        ?string $updatedAfter = null,
        ?int $projectId = null,
    ): array {
        $params = [
            'assigned_to_id' => $redmineUserId,
            'status_id' => $status === 'all' ? '*' : $status,
            'limit' => $limit,
            'offset' => $offset,
        ];

        if ($projectId !== null) {
            $params['project_id'] = $projectId;
        }

        if ($updatedAfter !== null) {
            $params['updated_on'] = '>='.$updatedAfter;
        }

        $response = $this->get('/issues.json', $params);
        $this->assertSuccessful(__FUNCTION__, $response);

        $items = $this->jsonList($response, 'issues');
        $rawTotal = $response->json('total_count');

        return ['items' => $items, 'total' => is_int($rawTotal) ? $rawTotal : count($items)];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     * @throws RuntimeException
     */
    public function createIssue(int $projectId, string $subject, string $description = '', ?int $assignedToId = null, ?int $priorityId = null, ?int $trackerId = null): array
    {
        $payload = [
            'issue' => array_filter([
                'project_id' => $projectId,
                'subject' => $subject,
                'description' => $description,
                'assigned_to_id' => $assignedToId,
                'priority_id' => $priorityId,
                'tracker_id' => $trackerId,
            ], fn (string|int|null $v): bool => $v !== null && $v !== ''),
        ];

        $response = $this->post('/issues.json', $payload);
        $this->assertSuccessful(__FUNCTION__, $response);

        return $this->jsonObject($response, 'issue');
    }

    /**
     * @return array{issue_id: int, status_id: int}
     *
     * @throws ConnectionException
     * @throws RuntimeException
     */
    public function updateIssueStatus(int $issueId, int $statusId): array
    {
        $response = $this->put(sprintf('/issues/%d.json', $issueId), ['issue' => ['status_id' => $statusId]]);

        throw_if($response->status() === 404, RuntimeException::class, sprintf('updateIssueStatus: Issue #%d not found.', $issueId));
        $this->assertSuccessful(__FUNCTION__, $response);

        return ['issue_id' => $issueId, 'status_id' => $statusId];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{items: list<array<string, mixed>>, total: int}
     *
     * @throws ConnectionException
     * @throws RuntimeException
     */
    public function getProjectIssues(int $projectId, array $filters = []): array
    {
        $status = $filters['status'] ?? 'open';

        $params = [
            'project_id' => $projectId,
            'status_id' => $status === 'all' ? '*' : $status,
            'limit' => $filters['limit'] ?? 25,
            'offset' => $filters['offset'] ?? 0,
        ];

        if (isset($filters['assigned_to_id'])) {
            $params['assigned_to_id'] = $filters['assigned_to_id'];
        }

        if (isset($filters['updated_after']) && is_string($filters['updated_after'])) {
            $params['updated_on'] = '>='.$filters['updated_after'];
        }

        $response = $this->get('/issues.json', $params);
        $this->assertSuccessful(__FUNCTION__, $response);

        $items = $this->jsonList($response, 'issues');
        $rawTotal = $response->json('total_count');

        return ['items' => $items, 'total' => is_int($rawTotal) ? $rawTotal : count($items)];
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     *
     * @throws ConnectionException
     * @throws RuntimeException
     */
    public function getUsers(int $offset = 0, int $limit = 100): array
    {
        $response = $this->get('/users.json', ['limit' => $limit, 'offset' => $offset, 'status' => 1]);
        $this->assertSuccessful(__FUNCTION__, $response);

        $items = $this->jsonList($response, 'users');
        $rawTotal = $response->json('total_count');

        return ['items' => $items, 'total' => is_int($rawTotal) ? $rawTotal : count($items)];
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     *
     * @throws ConnectionException
     * @throws RuntimeException
     */
    public function getTimeLogsByDate(string $date, int $offset = 0, int $limit = 100): array
    {
        $response = $this->get('/time_entries.json', [
            'from' => $date,
            'to' => $date,
            'limit' => $limit,
            'offset' => $offset,
        ]);
        $this->assertSuccessful(__FUNCTION__, $response);

        $items = $this->jsonList($response, 'time_entries');
        $rawTotal = $response->json('total_count');

        return ['items' => $items, 'total' => is_int($rawTotal) ? $rawTotal : count($items)];
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws ConnectionException
     * @throws RuntimeException
     */
    public function getIssueStatuses(): array
    {
        $response = $this->get('/issue_statuses.json');
        $this->assertSuccessful(__FUNCTION__, $response);

        return $this->jsonList($response, 'issue_statuses');
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws ConnectionException
     * @throws RuntimeException
     */
    public function getIssuePriorities(): array
    {
        $response = $this->get('/enumerations/issue_priorities.json');
        $this->assertSuccessful(__FUNCTION__, $response);

        return $this->jsonList($response, 'issue_priorities');
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws ConnectionException
     * @throws RuntimeException
     */
    public function getTrackers(): array
    {
        $response = $this->get('/trackers.json');
        $this->assertSuccessful(__FUNCTION__, $response);

        return $this->jsonList($response, 'trackers');
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws ConnectionException
     * @throws RuntimeException
     */
    public function getTimeEntryActivities(): array
    {
        $response = $this->get('/enumerations/time_entry_activities.json');
        $this->assertSuccessful(__FUNCTION__, $response);

        return $this->jsonList($response, 'time_entry_activities');
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     *
     * @throws ConnectionException
     * @throws RuntimeException
     */
    public function getProjects(int $offset = 0, int $limit = 100): array
    {
        $response = $this->get('/projects.json', ['limit' => $limit, 'offset' => $offset]);
        $this->assertSuccessful(__FUNCTION__, $response);

        $items = $this->jsonList($response, 'projects');
        $rawTotal = $response->json('total_count');

        return ['items' => $items, 'total' => is_int($rawTotal) ? $rawTotal : count($items)];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     * @throws RuntimeException
     */
    public function getIssue(int $issueId, bool $withJournals = false): array
    {
        $response = $this->get(
            sprintf('/issues/%d.json', $issueId),
            $withJournals ? ['include' => 'journals'] : [],
        );

        throw_if($response->status() === 404, RuntimeException::class, sprintf('getIssue: Issue #%d not found.', $issueId));
        $this->assertSuccessful(__FUNCTION__, $response);

        return $this->jsonObject($response, 'issue');
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     * @throws RuntimeException
     */
    public function getCurrentUser(): array
    {
        $response = $this->get('/users/current.json');
        $this->assertSuccessful(__FUNCTION__, $response);

        return $this->jsonObject($response, 'user');
    }

    protected function apiBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * @return array<string, string>
     */
    protected function apiHeaders(): array
    {
        return ['X-Redmine-API-Key' => $this->apiKey];
    }

    protected function apiLogChannel(): string
    {
        return 'redmine';
    }
}
