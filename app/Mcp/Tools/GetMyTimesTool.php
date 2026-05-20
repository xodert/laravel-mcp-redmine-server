<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesRedmineUser;
use App\Services\RedmineService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Throwable;

#[Description('Retrieve time log entries for a Redmine user over a specified date range.')]
#[IsReadOnly]
final class GetMyTimesTool extends Tool
{
    use ResolvesRedmineUser;

    public function handle(Request $request, RedmineService $redmine): Response
    {
        try {
            $validated = $request->validate([
                'redmine_user_id' => ['nullable', 'integer', 'min:1'],
                'date_from' => ['nullable', 'date_format:Y-m-d'],
                'date_to' => ['nullable', 'date_format:Y-m-d'],
            ]);

            $redmineUserId = $this->resolveRedmineUserId($request, $redmine);
            $dateFrom = $validated['date_from'] ?? now()->startOfWeek()->toDateString();
            $dateTo = $validated['date_to'] ?? now()->toDateString();

            $entries = $redmine->getUserTimeLogs($redmineUserId, $dateFrom, $dateTo);

            if ($entries === []) {
                return Response::text(sprintf('No time entries found for the period %s — %s.', $dateFrom, $dateTo));
            }

            $totalHours = array_sum(array_column($entries, 'hours'));
            $lines = ["Time entries from {$dateFrom} to {$dateTo} (total: {$totalHours}h):\n"];

            foreach ($entries as $entry) {
                $issueId = $entry['issue']['id'] ?? 'N/A';
                $hours = $entry['hours'] ?? 0;
                $date = $entry['spent_on'] ?? '';
                $comment = $entry['comments'] ?? '';
                $activity = $entry['activity']['name'] ?? '';
                $lines[] = sprintf('• [%s] Issue #%s — %sh (%s): %s', $date, $issueId, $hours, $activity, $comment);
            }

            return Response::text(implode("\n", $lines));
        } catch (Throwable $throwable) {
            return Response::error('Failed to retrieve time logs: '.$throwable->getMessage());
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'redmine_user_id' => $schema->integer()
                ->description('Redmine user ID. If omitted, resolved from the authenticated token.'),
            'date_from' => $schema->string()
                ->description('Start date in YYYY-MM-DD format. Defaults to start of current week.'),
            'date_to' => $schema->string()
                ->description('End date in YYYY-MM-DD format. Defaults to today.'),
        ];
    }
}
