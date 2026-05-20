<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Services\RedmineService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Client\ConnectionException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Throwable;

#[Description('Log work time for a Redmine issue. Provide issue ID, hours spent, and a description of work done.')]
final class LogTimeTool extends Tool
{
    /**
     * @param Request $request
     * @param RedmineService $redmine
     * @return Response
     */
    public function handle(Request $request, RedmineService $redmine): Response
    {
        try {
            $validated = $request->validate([
                'issue_id' => ['required', 'integer', 'min:1'],
                'hours' => ['required', 'numeric', 'min:0.01', 'max:24'],
                'comment' => ['required', 'string', 'max:255'],
                'date' => ['nullable', 'date_format:Y-m-d'],
                'activity_id' => ['nullable', 'integer', 'min:1'],
                'user_id' => ['nullable', 'integer', 'min:1'],
            ]);

            $activityId = isset($validated['activity_id'])
                ? (int) $validated['activity_id']
                : $this->resolveDefaultActivityId($redmine);

            $entry = $redmine->logTime(
                $validated['issue_id'],
                (float) $validated['hours'],
                $validated['comment'],
                $validated['date'] ?? null,
                $activityId,
                isset($validated['user_id']) ? (int) $validated['user_id'] : null,
            );

            $date = $entry['spent_on'] ?? ($validated['date'] ?? now()->toDateString());
            $hours = $entry['hours'] ?? $validated['hours'];
            $issueId = $entry['issue']['id'] ?? $validated['issue_id'];
            $user = $entry['user']['name'] ?? null;

            return Response::text(
                "Time logged successfully.\n".
                sprintf('Issue:   #%s%s', $issueId, PHP_EOL).
                sprintf('Hours:   %s%s', $hours, PHP_EOL).
                sprintf('Date:    %s%s', $date, PHP_EOL).
                ($user !== null ? sprintf('User:    %s%s', $user, PHP_EOL) : '').
                ('Comment: '.$validated['comment'])
            );
        } catch (Throwable $throwable) {
            return Response::error('Failed to log time: '.$throwable->getMessage());
        }
    }

    /**
     * @param JsonSchema $schema
     * @return array|mixed[]
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'issue_id' => $schema->integer()
                ->description('Redmine issue number (e.g. 1234)')
                ->required(),
            'hours' => $schema->number()
                ->description('Hours spent (e.g. 1.5 for one hour and 30 minutes)')
                ->required(),
            'comment' => $schema->string()
                ->description('Description of work performed')
                ->required(),
            'date' => $schema->string()
                ->description('Date in YYYY-MM-DD format. Defaults to today.')
                ->default(now()->toDateString()),
            'activity_id' => $schema->integer()
                ->description('Redmine activity ID (type of work). Omit to use the default activity.'),
            'user_id' => $schema->integer()
                ->description('Log time on behalf of another user (requires admin API key). Omit to log as the current API key owner.'),
        ];
    }

    /**
     * @param RedmineService $redmine
     * @return int|null
     * @throws ConnectionException
     */
    private function resolveDefaultActivityId(RedmineService $redmine): ?int
    {
        $activities = $redmine->getTimeEntryActivities();

        $default = array_filter($activities, fn (array $a): bool => ($a['is_default'] ?? false) === true);

        if ($default !== []) {
            return (int) array_values($default)[0]['id'];
        }

        return $activities !== [] ? (int) $activities[0]['id'] : null;
    }
}
