<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\CastsApiData;
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
    use CastsApiData;

    public function handle(Request $request, RedmineService $redmine): Response
    {
        try {
            $request->validate([
                'date' => ['nullable', 'date_format:Y-m-d'],
            ]);

            $date = $request->filled('date') ? $request->string('date')->toString() : now()->subDay()->toDateString();

            $allUsers = $redmine->getUsers();
            $timeLogs = $redmine->getTimeLogsByDate($date);

            $loggedUserIds = [];

            foreach ($timeLogs as $entry) {
                $userId = $this->intOf(data_get($entry, 'user.id'));

                if ($userId > 0) {
                    $loggedUserIds[] = $userId;
                }
            }

            $loggedUserIds = array_unique($loggedUserIds);

            $unlogged = array_filter(
                $allUsers,
                fn (array $user): bool => ! in_array($this->intOf($user['id']), $loggedUserIds, true)
            );

            if ($unlogged === []) {
                return Response::text(sprintf('All users have logged time for %s.', $date));
            }

            $names = array_map(
                fn (array $u): string => sprintf(
                    '• %s %s (ID: %d)',
                    $this->strOf($u['firstname'] ?? ''),
                    $this->strOf($u['lastname'] ?? ''),
                    $this->intOf($u['id']),
                ),
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
        ];
    }
}
