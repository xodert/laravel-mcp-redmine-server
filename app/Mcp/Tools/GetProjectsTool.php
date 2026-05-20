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

#[Description('List all Redmine projects. Use this to resolve a project name or identifier to its numeric ID before calling other tools.')]
#[IsReadOnly]
final class GetProjectsTool extends Tool
{
    /**
     * @param Request $request
     * @param RedmineService $redmine
     * @return Response
     */
    public function handle(Request $request, RedmineService $redmine): Response
    {
        try {
            $projects = $redmine->getProjects();

            if ($projects === []) {
                return Response::text('No projects found.');
            }

            $lines = [count($projects)." project(s):\n"];

            foreach ($projects as $project) {
                $lines[] = sprintf(
                    '• #%d  %-20s  %s',
                    $project['id'],
                    $project['identifier'],
                    $project['name']
                );
            }

            return Response::text(implode("\n", $lines));
        } catch (Throwable $throwable) {
            return Response::error('Failed to retrieve projects: '.$throwable->getMessage());
        }
    }

    /**
     * @param JsonSchema $schema
     * @return array|mixed[]
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
