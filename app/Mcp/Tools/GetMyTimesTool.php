<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\CastsApiData;
use App\Mcp\Concerns\ResolvesRedmineUser;
use App\Services\RedmineService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use RuntimeException;

#[Description('Retrieve time log entries for a Redmine user over a specified date range.')]
#[IsReadOnly]
final class GetMyTimesTool extends Tool
{
    use CastsApiData;
    use ResolvesRedmineUser;

    public function handle(Request $request, RedmineService $redmine): Response
    {
        try {
            $request->validate([
                'redmine_user_id' => ['nullable', 'integer', 'min:1'],
                'date_from' => ['nullable', 'date_format:Y-m-d'],
                'date_to' => ['nullable', 'date_format:Y-m-d'],
                'offset' => ['nullable', 'integer', 'min:0'],
                'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);

            $redmineUserId = $this->resolveRedmineUserFilter($request);
            $dateFrom = $request->filled('date_from') ? $request->string('date_from')->toString() : now()->startOfWeek()->toDateString();
            $dateTo = $request->filled('date_to') ? $request->string('date_to')->toString() : now()->toDateString();
            $offset = $request->integer('offset', 0);
            $limit = $request->integer('limit', 100);

            $result = $redmine->getUserTimeLogs($redmineUserId, $dateFrom, $dateTo, $offset, $limit);
            $entries = $result['items'];
            $total = $result['total'];

            if ($entries === []) {
                return Response::text(sprintf('No time entries found for the period %s — %s.', $dateFrom, $dateTo));
            }

            $totalHours = array_sum(array_column($entries, 'hours'));
            $nextOffset = $offset + count($entries);
            $header = $nextOffset < $total
                ? sprintf('%d total entries from %s to %s, showing %d–%d (%sh on this page). Use offset=%d for the next page.', $total, $dateFrom, $dateTo, $offset + 1, $nextOffset, $totalHours, $nextOffset)
                : sprintf('Time entries from %s to %s (%sh):', $dateFrom, $dateTo, $totalHours);

            $lines = [$header."\n"];

            foreach ($entries as $entry) {
                $issueId = $this->intOf(data_get($entry, 'issue.id'));
                $hours = $this->floatOf($entry['hours'] ?? 0);
                $date = $this->strOf($entry['spent_on'] ?? '');
                $comment = $this->strOf($entry['comments'] ?? '');
                $activity = $this->strOf(data_get($entry, 'activity.name'));
                $lines[] = sprintf('• [%s] Issue #%d — %sh (%s): %s', $date, $issueId, $hours, $activity, $comment);
            }

            return Response::text(implode("\n", $lines));
        } catch (RuntimeException $throwable) {
            return Response::error('Failed to retrieve time logs: '.$throwable->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'redmine_user_id' => $schema->integer()
                ->description('Redmine user ID. If omitted, returns entries for the API key owner.'),
            'date_from' => $schema->string()
                ->description('Start date in YYYY-MM-DD format. Defaults to start of current week.'),
            'date_to' => $schema->string()
                ->description('End date in YYYY-MM-DD format. Defaults to today.'),
            'offset' => $schema->integer()
                ->description('Number of entries to skip (0-based). Defaults to 0.')
                ->default(0),
            'limit' => $schema->integer()
                ->description('Number of entries to return (1–100). Defaults to 100.')
                ->default(100),
        ];
    }
}
