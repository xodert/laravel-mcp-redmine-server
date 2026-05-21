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

#[Description('Get issues assigned to a Redmine user, optionally filtered by status.')]
#[IsReadOnly]
final class GetAssignedIssuesTool extends Tool
{
    use CastsApiData;
    use ResolvesRedmineUser;

    public function handle(Request $request, RedmineService $redmine): Response
    {
        try {
            $request->validate([
                'redmine_user_id' => ['nullable', 'integer', 'min:1'],
                'status' => ['nullable', 'in:open,closed,all'],
                'project_id' => ['nullable', 'integer', 'min:1'],
                'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
                'offset' => ['nullable', 'integer', 'min:0'],
                'updated_after' => ['nullable', 'date_format:Y-m-d'],
            ]);

            $redmineUserId = $this->resolveRedmineUserFilter($request);
            $status = $request->filled('status') ? $request->string('status')->toString() : 'open';
            $offset = $request->filled('offset') ? $request->integer('offset') : 0;
            $limit = $request->filled('limit') ? $request->integer('limit') : 25;

            $result = $redmine->getAssignedIssues(
                $redmineUserId,
                $status,
                $limit,
                $offset,
                $request->filled('updated_after') ? $request->string('updated_after')->toString() : null,
                $request->filled('project_id') ? $request->integer('project_id') : null,
            );

            $issues = $result['items'];
            $total = $result['total'];

            if ($issues === []) {
                return Response::text(sprintf('No %s issues assigned to this user.', $status));
            }

            $nextOffset = $offset + count($issues);
            $header = $nextOffset < $total
                ? sprintf('%d total %s issue(s), showing %d–%d. Use offset=%d for the next page.', $total, $status, $offset + 1, $nextOffset, $nextOffset)
                : sprintf('%d %s issue(s) assigned:', count($issues), $status);

            $lines = [$header."\n"];

            foreach ($issues as $issue) {
                $id = $this->intOf($issue['id']);
                $subject = $this->strOf($issue['subject'] ?? '');
                $project = $this->strOf(data_get($issue, 'project.name'), 'N/A');
                $issueStatus = $this->strOf(data_get($issue, 'status.name'));
                $priority = $this->strOf(data_get($issue, 'priority.name'));
                $lines[] = "• #{$id} [{$priority}] {$subject}\n  Project: {$project} | Status: {$issueStatus}";
            }

            return Response::text(implode("\n", $lines));
        } catch (RuntimeException $runtimeException) {
            return Response::error('Failed to retrieve assigned issues: '.$runtimeException->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'redmine_user_id' => $schema->integer()
                ->description('Redmine user ID. If omitted, returns issues assigned to the API key owner.'),
            'status' => $schema->string()
                ->enum(['open', 'closed', 'all'])
                ->description('Issue status filter. Defaults to "open".')
                ->default('open'),
            'project_id' => $schema->integer()
                ->description('Filter results to a specific project ID'),
            'updated_after' => $schema->string()
                ->description('Return only issues updated on or after this date (YYYY-MM-DD). Useful for "what changed recently".'),
            'limit' => $schema->integer()
                ->description('Number of issues to return (1–100). Defaults to 25.')
                ->default(25),
            'offset' => $schema->integer()
                ->description('Number of issues to skip for pagination. Defaults to 0.')
                ->default(0),
        ];
    }
}
