<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Services\RedmineService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Throwable;

#[Description(
    'Change the status of a Redmine issue. '.
    'Standard statuses: 1=New, 2=In Progress, 3=Resolved, 4=Feedback, 5=Closed, 6=Rejected. '.
    'Confirm the desired status with the user before applying.'
)]
final class UpdateIssueStatusTool extends Tool
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
                'status_id' => ['required', 'integer', 'min:1'],
            ]);

            $issueId = (int) $validated['issue_id'];
            $statusId = (int) $validated['status_id'];

            $before = $redmine->getIssue($issueId);
            $redmine->updateIssueStatus($issueId, $statusId);
            $after = $redmine->getIssue($issueId);

            $lines = [sprintf('Issue #%d: %s', $issueId, $before['subject'])];
            $lines[] = '';

            $changed = $this->diff($before, $after);

            if ($changed === []) {
                $lines[] = 'No changes detected.';
            } else {
                foreach ($changed as $field => [$old, $new]) {
                    $lines[] = sprintf('  %-12s  %s  →  %s', $field.':', $old, $new);
                }
            }

            return Response::text(implode("\n", $lines));
        } catch (Throwable $throwable) {
            return Response::error('Failed to update issue status: '.$throwable->getMessage());
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
                ->description('Redmine issue number')
                ->required(),
            'status_id' => $schema->integer()
                ->description('New status ID: 1=New, 2=In Progress, 3=Resolved, 4=Feedback, 5=Closed, 6=Rejected')
                ->required(),
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{0: string, 1: string}>
     */
    private function diff(array $before, array $after): array
    {
        $changed = [];

        $fields = [
            'status' => fn (array $i) => $i['status']['name'] ?? '—',
            'assigned_to' => fn (array $i) => $i['assigned_to']['name'] ?? 'Unassigned',
            'priority' => fn (array $i) => $i['priority']['name'] ?? '—',
            'done_ratio' => fn (array $i): string => ($i['done_ratio'] ?? 0).'%',
        ];

        foreach ($fields as $field => $extract) {
            $old = $extract($before);
            $new = $extract($after);

            if ($old !== $new) {
                $changed[$field] = [$old, $new];
            }
        }

        return $changed;
    }
}
