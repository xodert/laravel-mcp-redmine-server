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
use Throwable;

#[Description('Create a new issue (task) in a Redmine project.')]
final class CreateIssueTool extends Tool
{
    use CastsApiData;

    public function handle(Request $request, RedmineService $redmine): Response
    {
        try {
            $request->validate([
                'project_id' => ['required', 'integer', 'min:1'],
                'subject' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'assigned_to_id' => ['nullable', 'integer', 'min:1'],
                'priority_id' => ['nullable', 'integer', 'min:1'],
                'tracker_id' => ['nullable', 'integer', 'min:1'],
            ]);

            $issue = $redmine->createIssue(
                $request->integer('project_id'),
                $request->string('subject')->toString(),
                $request->filled('description') ? $request->string('description')->toString() : '',
                $request->filled('assigned_to_id') ? $request->integer('assigned_to_id') : null,
                $request->filled('priority_id') ? $request->integer('priority_id') : null,
                $request->filled('tracker_id') ? $request->integer('tracker_id') : null,
            );

            $id = $this->intOf($issue['id']);
            $baseUrl = $this->strOf(config('redmine.base_url'));
            $url = sprintf('%s/issues/%d', mb_rtrim($baseUrl, '/'), $id);

            return Response::text(
                "Issue created successfully.\n".
                sprintf('ID: #%d%s', $id, PHP_EOL).
                sprintf('Subject: %s%s', $request->string('subject')->toString(), PHP_EOL).
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
                ->description('Redmine user ID to assign the issue to. Use get-users-tool to find user IDs.'),
            'tracker_id' => $schema->integer()
                ->description('Issue tracker ID (e.g. Bug, Feature, Support). Use get-trackers-tool to see available trackers.'),
            'priority_id' => $schema->integer()
                ->description('Priority ID. Use get-issue-priorities-tool to see available priorities for this Redmine instance.'),
        ];
    }
}
