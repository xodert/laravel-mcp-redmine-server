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

#[Description('Get full details of a Redmine issue including its change history (journals).')]
#[IsReadOnly]
final class GetIssueTool extends Tool
{
    use CastsApiData;

    public function handle(Request $request, RedmineService $redmine): Response
    {
        try {
            $request->validate([
                'issue_id' => ['required', 'integer', 'min:1'],
            ]);

            $issue = $redmine->getIssue($request->integer('issue_id'), withJournals: true);

            $lines = [
                sprintf('Issue #%d: %s', $this->intOf($issue['id']), $this->strOf($issue['subject'] ?? '')),
                '',
                sprintf('Project:    %s', $this->strOf(data_get($issue, 'project.name'), '—')),
                sprintf('Status:     %s', $this->strOf(data_get($issue, 'status.name'), '—')),
                sprintf('Priority:   %s', $this->strOf(data_get($issue, 'priority.name'), '—')),
                sprintf('Assigned:   %s', $this->strOf(data_get($issue, 'assigned_to.name'), 'Unassigned')),
                sprintf('Author:     %s', $this->strOf(data_get($issue, 'author.name'), '—')),
                sprintf('Created:    %s', mb_substr($this->strOf($issue['created_on'] ?? ''), 0, 10)),
                sprintf('Updated:    %s', mb_substr($this->strOf($issue['updated_on'] ?? ''), 0, 10)),
                sprintf('Done:       %d%%', $this->intOf($issue['done_ratio'] ?? 0)),
            ];

            if (! empty($issue['description'])) {
                $lines[] = '';
                $lines[] = 'Description:';
                $lines[] = $this->strOf($issue['description']);
            }

            /**
             * @var list<array<string, mixed>> $journals
             */
            $journals = $issue['journals'] ?? [];

            if ($journals !== []) {
                $hasDetails = collect($journals)->contains(
                    fn (array $j): bool => ! empty($j['details']) || mb_trim($this->strOf($j['notes'] ?? '')) !== ''
                );

                $statuses = [];
                $priorities = [];
                $users = [];

                if ($hasDetails) {
                    /** @var array<int, string> $statuses */
                    $statuses = array_column($redmine->getIssueStatuses(), 'name', 'id');

                    /** @var array<int, string> $priorities */
                    $priorities = array_column($redmine->getIssuePriorities(), 'name', 'id');

                    /** @var array<int, string> $users */
                    $users = array_column($redmine->getUsers(), 'name', 'id');
                }

                $lines[] = '';
                $lines[] = '── Change History ──────────────────────────────';

                foreach ($journals as $journal) {
                    $author = $this->strOf(data_get($journal, 'user.name'), 'Unknown');
                    $date = mb_substr($this->strOf($journal['created_on'] ?? ''), 0, 10);
                    $note = mb_trim($this->strOf($journal['notes'] ?? ''));

                    /** @var list<array<string, mixed>> $details */
                    $details = is_array($journal['details']) ? $journal['details'] : [];

                    if ($details === [] && $note === '') {
                        continue;
                    }

                    $lines[] = '';
                    $lines[] = sprintf('[%s] %s', $date, $author);

                    foreach ($details as $detail) {
                        $field = $this->strOf($detail['name'] ?? '');
                        $old = $this->strOf($detail['old_value'] ?? '—');
                        $new = $this->strOf($detail['new_value'] ?? '—');

                        [$label, $old, $new] = match ($field) {
                            'status_id' => ['Status', $statuses[(int) $old] ?? $old, $statuses[(int) $new] ?? $new],
                            'priority_id' => ['Priority', $priorities[(int) $old] ?? $old, $priorities[(int) $new] ?? $new],
                            'assigned_to_id' => ['Assigned to', $users[(int) $old] ?? $old, $users[(int) $new] ?? $new],
                            'done_ratio' => ['Done', $old.'%', $new.'%'],
                            'subject' => ['Subject', $old, $new],
                            'description' => ['Description', mb_substr($old, 0, 30).'…', mb_substr($new, 0, 30).'…'],
                            'due_date' => ['Due date', $old, $new],
                            default => [$field, $old, $new],
                        };

                        $lines[] = sprintf('  %-14s %s  →  %s', $label.':', $old, $new);
                    }

                    if ($note !== '') {
                        $lines[] = '  Note: '.$note;
                    }
                }
            } else {
                $lines[] = '';
                $lines[] = 'No changes recorded yet.';
            }

            return Response::text(implode("\n", $lines));
        } catch (Throwable $throwable) {
            return Response::error('Failed to retrieve issue: '.$throwable->getMessage());
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
        ];
    }
}
