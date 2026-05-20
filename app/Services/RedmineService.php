<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

final readonly class RedmineService extends AbstractHttpService
{
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
     * @return list<array<string, mixed>>
     *
     * @throws ConnectionException
     * @throws RuntimeException
     */
    public function getUserTimeLogs(int $redmineUserId, string $dateFrom, string $dateTo): array
    {
        $response = $this->get('/time_entries.json', [
            'user_id' => $redmineUserId,
            'from' => $dateFrom,
            'to' => $dateTo,
            'limit' => 100,
        ]);
        $this->assertSuccessful(__FUNCTION__, $response);

        return $this->jsonList($response, 'time_entries');
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws ConnectionException
     * @throws RuntimeException
     */
    public function getAssignedIssues(int $redmineUserId, string $status = 'open'): array
    {
        $response = $this->get('/issues.json', [
            'assigned_to_id' => $redmineUserId,
            'status_id' => $status,
            'limit' => 100,
        ]);
        $this->assertSuccessful(__FUNCTION__, $response);

        return $this->jsonList($response, 'issues');
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     * @throws RuntimeException
     */
    public function createIssue(int $projectId, string $subject, string $description = '', ?int $assignedToId = null, ?int $priorityId = null): array
    {
        $payload = [
            'issue' => array_filter([
                'project_id' => $projectId,
                'subject' => $subject,
                'description' => $description,
                'assigned_to_id' => $assignedToId,
                'priority_id' => $priorityId,
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
     * @return list<array<string, mixed>>
     *
     * @throws ConnectionException
     * @throws RuntimeException
     */
    public function getProjectIssues(int $projectId, array $filters = []): array
    {
        $params = array_merge([
            'project_id' => $projectId,
            'limit' => $filters['limit'] ?? 25,
        ], array_filter([
            'status_id' => $filters['status'] ?? 'open',
            'assigned_to_id' => $filters['assigned_to_id'] ?? null,
        ], fn (mixed $v): bool => $v !== null));

        $response = $this->get('/issues.json', $params);
        $this->assertSuccessful(__FUNCTION__, $response);

        return $this->jsonList($response, 'issues');
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws ConnectionException
     * @throws RuntimeException
     */
    public function getUsers(): array
    {
        $response = $this->get('/users.json', ['limit' => 100, 'status' => 1]);
        $this->assertSuccessful(__FUNCTION__, $response);

        return $this->jsonList($response, 'users');
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws ConnectionException
     * @throws RuntimeException
     */
    public function getTimeLogsByDate(string $date): array
    {
        $response = $this->get('/time_entries.json', ['from' => $date, 'to' => $date, 'limit' => 100]);
        $this->assertSuccessful(__FUNCTION__, $response);

        return $this->jsonList($response, 'time_entries');
    }

    /**
     * @return array<int, string>
     *
     * @throws ConnectionException
     * @throws RuntimeException
     */
    public function getIssueStatuses(): array
    {
        $response = $this->get('/issue_statuses.json');
        $this->assertSuccessful(__FUNCTION__, $response);

        $data = $this->jsonList($response, 'issue_statuses');

        /** @var array<int, string> */
        return array_column($data, 'name', 'id');
    }

    /**
     * @return array<int, string>
     *
     * @throws ConnectionException
     * @throws RuntimeException
     */
    public function getIssuePriorities(): array
    {
        $response = $this->get('/enumerations/issue_priorities.json');
        $this->assertSuccessful(__FUNCTION__, $response);

        $data = $this->jsonList($response, 'issue_priorities');

        /** @var array<int, string> */
        return array_column($data, 'name', 'id');
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
     * @return list<array<string, mixed>>
     *
     * @throws ConnectionException
     * @throws RuntimeException
     */
    public function getProjects(): array
    {
        $response = $this->get('/projects.json', ['limit' => 100]);
        $this->assertSuccessful(__FUNCTION__, $response);

        return $this->jsonList($response, 'projects');
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
