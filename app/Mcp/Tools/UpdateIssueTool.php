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
use RuntimeException;

#[Description(
    'Add a note (comment) to a Redmine issue and/or reassign it to another user. '.
    'Provide at least one of notes or assigned_to_id. '.
    'Confirm the note text and new assignee with the user before applying.'
)]
final class UpdateIssueTool extends Tool
{
    use CastsApiData;

    public function handle(Request $request, RedmineService $redmine): Response
    {
        try {
            $request->validate([
                'issue_id' => ['required', 'integer', 'min:1'],
                'notes' => ['nullable', 'string', 'required_without:assigned_to_id'],
                'assigned_to_id' => ['nullable', 'integer', 'min:1', 'required_without:notes'],
            ]);

            $issueId = $request->integer('issue_id');
            $notes = $request->filled('notes') ? $request->string('notes')->toString() : null;
            $assignedToId = $request->filled('assigned_to_id') ? $request->integer('assigned_to_id') : null;

            $before = $redmine->getIssue($issueId);
            $redmine->updateIssue($issueId, $notes, $assignedToId);
            $after = $redmine->getIssue($issueId);

            $lines = [sprintf('Issue #%d: %s', $issueId, $this->strOf($before['subject'] ?? ''))];
            $lines[] = '';

            $oldAssignee = $this->assigneeName($before);
            $newAssignee = $this->assigneeName($after);

            if ($oldAssignee !== $newAssignee) {
                $lines[] = sprintf('  %-12s  %s  →  %s', 'assigned_to:', $oldAssignee, $newAssignee);
            }

            if ($notes !== null) {
                $lines[] = '  Note added:';
                $lines[] = '    '.$notes;
            }

            if ($assignedToId !== null && $this->assigneeId($after) !== $assignedToId) {
                $lines[] = '';
                $lines[] = sprintf(
                    'Assignment was NOT applied: the assignee is still %s. '.
                    'Redmine silently ignores fields the API user may not edit, so the API user likely lacks '.
                    'the "Edit issues" permission (or the workflow makes Assignee read-only) in this project.',
                    $newAssignee,
                );

                return Response::error(implode("\n", $lines));
            }

            if ($assignedToId !== null && $oldAssignee === $newAssignee) {
                $lines[] = sprintf('  Already assigned to %s.', $newAssignee);
            }

            return Response::text(implode("\n", $lines));
        } catch (RuntimeException $runtimeException) {
            return Response::error('Failed to update issue: '.$runtimeException->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'issue_id' => $schema->integer()
                ->description('Redmine issue number')
                ->required(),
            'notes' => $schema->string()
                ->description('Comment to add to the issue history. Required if assigned_to_id is not provided.'),
            'assigned_to_id' => $schema->integer()
                ->description('Redmine user ID to assign the issue to. Required if notes is not provided. Use get-users-tool or get-issue-tool to find user IDs.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $issue
     */
    private function assigneeName(array $issue): string
    {
        return $this->strOf(data_get($issue, 'assigned_to.name'), 'Unassigned');
    }

    /**
     * @param  array<string, mixed>  $issue
     */
    private function assigneeId(array $issue): ?int
    {
        $id = data_get($issue, 'assigned_to.id');

        if ($id === null) {
            return null;
        }

        return $this->intOf($id);
    }
}
