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

#[Description('Create a new issue (task) in a Redmine project.')]
final class CreateIssueTool extends Tool
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
                'project_id' => ['required', 'integer', 'min:1'],
                'subject' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'assigned_to_id' => ['nullable', 'integer', 'min:1'],
                'priority_id' => ['nullable', 'integer', 'in:1,2,3,4,5'],
            ]);

            $issue = $redmine->createIssue(
                (int) $validated['project_id'],
                $validated['subject'],
                $validated['description'] ?? '',
                isset($validated['assigned_to_id']) ? (int) $validated['assigned_to_id'] : null,
                isset($validated['priority_id']) ? (int) $validated['priority_id'] : null,
            );

            $id = $issue['id'] ?? 'N/A';
            $baseUrl = mb_rtrim((string) config('redmine.base_url'), '/');
            $url = sprintf('%s/issues/%s', $baseUrl, $id);

            return Response::text(
                "Issue created successfully.\n".
                sprintf('ID: #%s%s', $id, PHP_EOL).
                sprintf('Subject: %s%s', $validated['subject'], PHP_EOL).
                ('URL: '.$url)
            );
        } catch (Throwable $throwable) {
            return Response::error('Failed to create issue: '.$throwable->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()
                ->description('Redmine project ID where the issue will be created')
                ->required(),
            'subject' => $schema->string()
                ->description('Issue title/subject')
                ->required(),
            'description' => $schema->string()
                ->description('Detailed description of the issue'),
            'assigned_to_id' => $schema->integer()
                ->description('Redmine user ID to assign the issue to'),
            'priority_id' => $schema->integer()
                ->enum([1, 2, 3, 4, 5])
                ->description('Priority: 1=Low, 2=Normal, 3=High, 4=Urgent, 5=Immediate')
                ->default(2),
        ];
    }
}
