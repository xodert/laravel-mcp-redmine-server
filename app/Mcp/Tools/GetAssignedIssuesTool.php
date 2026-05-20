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
use Throwable;

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
            ]);

            $redmineUserId = $this->resolveRedmineUserId($request, $redmine);
            $status = $request->filled('status') ? $request->string('status')->toString() : 'open';
            $issues = $redmine->getAssignedIssues($redmineUserId, $status);

            if ($request->filled('project_id')) {
                $projectId = $request->integer('project_id');
                $issues = array_filter(
                    $issues,
                    fn (array $i): bool => $this->intOf(data_get($i, 'project.id')) === $projectId
                );
            }

            if ($issues === []) {
                return Response::text(sprintf('No %s issues assigned to this user.', $status));
            }

            $lines = [count($issues)." {$status} issue(s) assigned:\n"];

            foreach ($issues as $issue) {
                $id = $this->intOf($issue['id']);
                $subject = $this->strOf($issue['subject'] ?? '');
                $project = $this->strOf(data_get($issue, 'project.name'), 'N/A');
                $issueStatus = $this->strOf(data_get($issue, 'status.name'));
                $priority = $this->strOf(data_get($issue, 'priority.name'));
                $lines[] = "• #{$id} [{$priority}] {$subject}\n  Project: {$project} | Status: {$issueStatus}";
            }

            return Response::text(implode("\n", $lines));
        } catch (Throwable $throwable) {
            return Response::error('Failed to retrieve assigned issues: '.$throwable->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'redmine_user_id' => $schema->integer()
                ->description('Redmine user ID. If omitted, resolved from the authenticated token.'),
            'status' => $schema->string()
                ->enum(['open', 'closed', 'all'])
                ->description('Issue status filter. Defaults to "open".')
                ->default('open'),
            'project_id' => $schema->integer()
                ->description('Filter results to a specific project ID'),
        ];
    }
}
