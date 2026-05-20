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

#[Description('List issues in a Redmine project with optional filters by status or assignee.')]
#[IsReadOnly]
final class GetProjectIssuesTool extends Tool
{
    public function handle(Request $request, RedmineService $redmine): Response
    {
        try {
            $validated = $request->validate([
                'project_id' => ['required', 'integer', 'min:1'],
                'status' => ['nullable', 'in:open,closed,all'],
                'assigned_to_id' => ['nullable', 'integer', 'min:1'],
                'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);

            $issues = $redmine->getProjectIssues((int) $validated['project_id'], [
                'status' => $validated['status'] ?? 'open',
                'assigned_to_id' => $validated['assigned_to_id'] ?? null,
                'limit' => $validated['limit'] ?? 25,
            ]);

            if ($issues === []) {
                return Response::text(sprintf('No issues found for project #%s.', $validated['project_id']));
            }

            $lines = [count($issues)." issue(s) in project #{$validated['project_id']}:\n"];

            foreach ($issues as $issue) {
                $id = $issue['id'];
                $subject = $issue['subject'] ?? '';
                $status = $issue['status']['name'] ?? '';
                $priority = $issue['priority']['name'] ?? '';
                $assigned = $issue['assigned_to']['name'] ?? 'Unassigned';
                $lines[] = "• #{$id} [{$priority}] {$subject}\n  Status: {$status} | Assigned: {$assigned}";
            }

            return Response::text(implode("\n", $lines));
        } catch (Throwable $throwable) {
            return Response::error('Failed to retrieve project issues: '.$throwable->getMessage());
        }
    }

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
        ];
    }
}
