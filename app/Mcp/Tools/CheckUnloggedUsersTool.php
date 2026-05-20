<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Services\RedmineService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Throwable;

#[Description('Return a list of Redmine users who have not logged any time for a given date. Useful for identifying who needs to be reminded to fill in their timesheets.')]
#[IsReadOnly]
final class CheckUnloggedUsersTool extends Tool
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
                'date' => ['nullable', 'date_format:Y-m-d'],
                'project_id' => ['nullable', 'integer', 'min:1'],
            ]);

            $date = $validated['date'] ?? now()->subDay()->toDateString();

            $allUsers = $redmine->getUsers();
            $timeLogs = $redmine->getTimeLogsByDate($date);

            $loggedUserIds = array_unique(
                array_map(fn (array $entry): mixed => $entry['user']['id'] ?? null, $timeLogs)
            );
            $loggedUserIds = array_filter($loggedUserIds);

            $unlogged = array_filter(
                $allUsers,
                fn (array $user): bool => ! in_array($user['id'], $loggedUserIds, true)
            );

            if ($unlogged === []) {
                return Response::text(sprintf('All users have logged time for %s.', $date));
            }

            $names = array_map(
                fn (array $u): string => sprintf('• %s %s (ID: %s)', $u['firstname'], $u['lastname'], $u['id']),
                array_values($unlogged)
            );

            return Response::text(
                count($unlogged)." user(s) without time entries on {$date}:\n\n".
                implode("\n", $names)
            );
        } catch (Throwable $throwable) {
            return Response::error('Failed to check unlogged users: '.$throwable->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'date' => $schema->string()
                ->description('Date to check in YYYY-MM-DD format. Defaults to yesterday.')
                ->default(now()->subDay()->toDateString()),
            'project_id' => $schema->integer()
                ->description('Optional: filter users by project membership (not yet applied server-side — returns all active users)'),
        ];
    }
}
