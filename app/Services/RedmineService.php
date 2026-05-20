<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final readonly class RedmineService
{
    private string $baseUrl;

    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = mb_rtrim((string) config('redmine.base_url'), '/');
        $this->apiKey = (string) config('redmine.api_key');
    }

    /**
     * @param int $issueId
     * @param float $hours
     * @param string $comment
     * @param string|null $date
     * @param int|null $activityId
     * @param int|null $userId
     * @return array<string, mixed>
     * @throws ConnectionException
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

        $this->log('POST', '/time_entries.json', $payload);

        $response = $this->http()->post($this->url('/time_entries.json'), $payload);

        if (! $response->successful()) {
            $this->throw('logTime', $response);
        }

        return $response->json('time_entry') ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws ConnectionException
     */
    public function getUserTimeLogs(int $redmineUserId, string $dateFrom, string $dateTo): array
    {
        $this->log('GET', '/time_entries.json', ['redmineUserId' => $redmineUserId, 'dateFrom' => $dateFrom, 'dateTo' => $dateTo]);

        $response = $this->http()->get($this->url('/time_entries.json'), [
            'user_id' => $redmineUserId,
            'from' => $dateFrom,
            'to' => $dateTo,
            'limit' => 100,
        ]);

        if (! $response->successful()) {
            $this->throw('getUserTimeLogs', $response);
        }

        return $response->json('time_entries') ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws ConnectionException
     */
    public function getAssignedIssues(int $redmineUserId, string $status = 'open'): array
    {
        $this->log('GET', '/issues.json', ['redmineUserId' => $redmineUserId, 'status' => $status]);

        $response = $this->http()->get($this->url('/issues.json'), [
            'assigned_to_id' => $redmineUserId,
            'status_id' => $status,
            'limit' => 100,
        ]);

        if (! $response->successful()) {
            $this->throw('getAssignedIssues', $response);
        }

        return $response->json('issues') ?? [];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ConnectionException
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

        $this->log('POST', '/issues.json', $payload);

        $response = $this->http()->post($this->url('/issues.json'), $payload);

        if (! $response->successful()) {
            $this->throw('createIssue', $response);
        }

        return $response->json('issue') ?? [];
    }

    /**
     * @return array{issue_id: int, status_id: int}
     *
     * @throws ConnectionException
     * @throws Throwable
     */
    public function updateIssueStatus(int $issueId, int $statusId): array
    {
        $payload = ['issue' => ['status_id' => $statusId]];

        $this->log('PUT', sprintf('/issues/%d.json', $issueId), $payload);

        $response = $this->http()->put($this->url(sprintf('/issues/%d.json', $issueId)), $payload);

        throw_if($response->status() === 404, RuntimeException::class, sprintf('updateIssueStatus: Issue #%d not found.', $issueId));

        if (! $response->successful()) {
            $this->throw('updateIssueStatus', $response);
        }

        return ['issue_id' => $issueId, 'status_id' => $statusId];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     *
     * @throws ConnectionException
     */
    public function getProjectIssues(int $projectId, array $filters = []): array
    {
        $params = array_merge([
            'project_id' => $projectId,
            'limit' => $filters['limit'] ?? 25,
        ], array_filter([
            'status_id' => $filters['status'] ?? 'open',
            'assigned_to_id' => $filters['assigned_to_id'] ?? null,
        ], fn ($v): bool => $v !== null));

        $this->log('GET', '/issues.json', $params);

        $response = $this->http()->get($this->url('/issues.json'), $params);

        if (! $response->successful()) {
            $this->throw('getProjectIssues', $response);
        }

        return $response->json('issues') ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws ConnectionException
     */
    public function getUsers(): array
    {
        $this->log('GET', '/users.json');

        $response = $this->http()->get($this->url('/users.json'), ['limit' => 100, 'status' => 1]);

        if (! $response->successful()) {
            $this->throw('getUsers', $response);
        }

        return $response->json('users') ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws ConnectionException
     */
    public function getTimeLogsByDate(string $date): array
    {
        $this->log('GET', '/time_entries.json', ['date' => $date]);

        $response = $this->http()->get($this->url('/time_entries.json'), [
            'from' => $date,
            'to' => $date,
            'limit' => 100,
        ]);

        if (! $response->successful()) {
            $this->throw('getTimeLogsByDate', $response);
        }

        return $response->json('time_entries') ?? [];
    }

    /**
     * @return array<int, string>
     *
     * @throws ConnectionException
     */
    public function getIssueStatuses(): array
    {
        $this->log('GET', '/issue_statuses.json');

        $response = $this->http()->get($this->url('/issue_statuses.json'));

        if (! $response->successful()) {
            $this->throw('getIssueStatuses', $response);
        }

        // Return as id => name map
        return array_column($response->json('issue_statuses') ?? [], 'name', 'id');
    }

    /**
     * @return array<int, string>
     *
     * @throws ConnectionException
     */
    public function getIssuePriorities(): array
    {
        $this->log('GET', '/enumerations/issue_priorities.json');

        $response = $this->http()->get($this->url('/enumerations/issue_priorities.json'));

        if (! $response->successful()) {
            $this->throw('getIssuePriorities', $response);
        }

        return array_column($response->json('issue_priorities') ?? [], 'name', 'id');
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws ConnectionException
     */
    public function getTimeEntryActivities(): array
    {
        $this->log('GET', '/enumerations/time_entry_activities.json');

        $response = $this->http()->get($this->url('/enumerations/time_entry_activities.json'));

        if (! $response->successful()) {
            $this->throw('getTimeEntryActivities', $response);
        }

        return $response->json('time_entry_activities') ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws ConnectionException
     */
    public function getProjects(): array
    {
        $this->log('GET', '/projects.json');

        $response = $this->http()->get($this->url('/projects.json'), ['limit' => 100]);

        if (! $response->successful()) {
            $this->throw('getProjects', $response);
        }

        return $response->json('projects') ?? [];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     * @throws Throwable
     */
    public function getIssue(int $issueId, bool $withJournals = false): array
    {
        $params = $withJournals ? ['include' => 'journals'] : [];

        $this->log('GET', sprintf('/issues/%d.json', $issueId), $params);

        $response = $this->http()->get($this->url(sprintf('/issues/%d.json', $issueId)), $params);

        throw_if($response->status() === 404, RuntimeException::class, sprintf('getIssue: Issue #%d not found.', $issueId));

        if (! $response->successful()) {
            $this->throw('getIssue', $response);
        }

        return $response->json('issue') ?? [];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     */
    public function getCurrentUser(): array
    {
        $this->log('GET', '/users/current.json');

        $response = $this->http()->get($this->url('/users/current.json'));

        if (! $response->successful()) {
            $this->throw('getCurrentUser', $response);
        }

        return $response->json('user') ?? [];
    }

    private function http(): PendingRequest
    {
        return Http::timeout(10)
            ->withHeaders(['X-Redmine-API-Key' => $this->apiKey])
            ->acceptJson();
    }

    private function url(string $path): string
    {
        return $this->baseUrl.$path;
    }

    /** @param array<string, mixed> $params */
    private function log(string $method, string $path, array $params = []): void
    {
        Log::channel('redmine')->debug(sprintf('Redmine %s %s', $method, $path), $params);
    }

    private function throw(string $context, Response $response): never
    {
        $errors = $response->json('errors');
        $body = is_array($errors) ? implode(', ', $errors) : ($errors ?? $response->body());
        throw new RuntimeException(sprintf('%s: HTTP %d — %s', $context, $response->status(), $body));
    }
}
