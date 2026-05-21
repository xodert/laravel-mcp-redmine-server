<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\CastsApiData;
use App\Services\RedmineService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use RuntimeException;

#[Description('List all available time entry activity types in this Redmine instance. Call this before using log-time-tool to get valid activity_id values.')]
#[IsReadOnly]
final class GetTimeEntryActivitiesTool extends Tool
{
    use CastsApiData;

    public function handle(Request $request, RedmineService $redmine): Response
    {
        try {
            $activities = $redmine->getTimeEntryActivities();

            if ($activities === []) {
                return Response::text('No time entry activities found.');
            }

            $lines = [count($activities)." activity(ies):\n"];

            foreach ($activities as $activity) {
                $id = $this->intOf($activity['id']);
                $name = $this->strOf($activity['name'] ?? '');
                $default = ($activity['is_default'] ?? false) === true ? '  [default]' : '';
                $lines[] = sprintf('• #%-4d %s%s', $id, $name, $default);
            }

            return Response::text(implode("\n", $lines));
        } catch (RuntimeException $throwable) {
            return Response::error('Failed to retrieve time entry activities: '.$throwable->getMessage());
        }
    }
}
