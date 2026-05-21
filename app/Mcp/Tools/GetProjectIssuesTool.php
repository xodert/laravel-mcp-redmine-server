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
use RuntimeException;

#[Description('List issues in a Redmine project with optional filters by status or assignee.')]
#[IsReadOnly]
final class GetProjectIssuesTool extends Tool
{
    use CastsApiData;

    public function handle(Request $request, RedmineService $redmine): Response
    {
        try {
            $request->validate([
                'project_id' => ['required', 'integer', 'min:1'],
                'status' => ['nullable', 'in:open,closed,all'],
                'assigned_to_id' => ['nullable', 'integer', 'min:1'],
                'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
                'offset' => ['nullable', 'integer', 'min:0'],
                'updated_after' => ['nullable', 'date_format:Y-m-d'],
            ]);

            $projectId = $request->integer('project_id');
            $offset = $request->filled('offset') ? $request->integer('offset') : 0;
            $limit = $request->filled('limit') ? $request->integer('limit') : 25;

            $result = $redmine->getProjectIssues($projectId, [
                'status' => $request->filled('status') ? $request->string('status')->toString() : 'open',
                'assigned_to_id' => $request->filled('assigned_to_id') ? $request->integer('assigned_to_id') : null,
                'limit' => $limit,
                'offset' => $offset,
                'updated_after' => $request->filled('updated_after') ? $request->string('updated_after')->toString() : null,
            ]);

            $issues = $result['items'];
            $total = $result['total'];

            if ($issues === []) {
                return Response::text(sprintf('No issues found for project #%d.', $projectId));
            }

            $nextOffset = $offset + count($issues);
            $header = $nextOffset < $total
                ? sprintf('%d total issue(s) in project #%d, showing %d–%d. Use offset=%d for the next page.', $total, $projectId, $offset + 1, $nextOffset, $nextOffset)
                : sprintf('%d issue(s) in project #%d:', count($issues), $projectId);

            $lines = [$header."\n"];

            foreach ($issues as $issue) {
                $id = $this->intOf($issue['id']);
                $subject = $this->strOf($issue['subject'] ?? '');
                $status = $this->strOf(data_get($issue, 'status.name'));
                $priority = $this->strOf(data_get($issue, 'priority.name'));
                $assigned = $this->strOf(data_get($issue, 'assigned_to.name'), 'Unassigned');
                $lines[] = "• #{$id} [{$priority}] {$subject}\n  Status: {$status} | Assigned: {$assigned}";
            }

            return Response::text(implode("\n", $lines));
        } catch (RuntimeException $runtimeException) {
            return Response::error('Failed to retrieve project issues: '.$runtimeException->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()
                ->description('Redmine project ID')
                ->required(),
            'status' => $schema->string()
                ->enum(['open', 'closed', 'all'])
                ->description('Filter by issue status. Defaults to "open".')
                ->default('open'),
            'assigned_to_id' => $schema->integer()
                ->description('Filter by assignee Redmine user ID'),
            'limit' => $schema->integer()
                ->description('Maximum number of issues to return (1–100). Defaults to 25.')
                ->default(25),
            'offset' => $schema->integer()
                ->description('Number of issues to skip for pagination. Defaults to 0.')
                ->default(0),
            'updated_after' => $schema->string()
                ->description('Return only issues updated on or after this date (YYYY-MM-DD).'),
        ];
    }
}
