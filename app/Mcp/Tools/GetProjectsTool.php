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

#[Description('List all Redmine projects. Use this to resolve a project name or identifier to its numeric ID before calling other tools.')]
#[IsReadOnly]
final class GetProjectsTool extends Tool
{
    use CastsApiData;

    public function handle(Request $request, RedmineService $redmine): Response
    {
        try {
            $request->validate([
                'offset' => ['nullable', 'integer', 'min:0'],
                'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);

            $offset = $request->integer('offset', 0);
            $limit = $request->integer('limit', 100);

            $result = $redmine->getProjects($offset, $limit);
            $projects = $result['items'];
            $total = $result['total'];

            if ($projects === []) {
                return Response::text('No projects found.');
            }

            $nextOffset = $offset + count($projects);
            $header = $nextOffset < $total
                ? sprintf('%d total project(s), showing %d–%d. Use offset=%d for the next page.', $total, $offset + 1, $nextOffset, $nextOffset)
                : sprintf('%d project(s):', count($projects));

            $lines = [$header."\n"];

            foreach ($projects as $project) {
                $lines[] = sprintf(
                    '• #%d  %-20s  %s',
                    $this->intOf($project['id']),
                    $this->strOf($project['identifier'] ?? ''),
                    $this->strOf($project['name'] ?? ''),
                );
            }

            return Response::text(implode("\n", $lines));
        } catch (RuntimeException $runtimeException) {
            return Response::error('Failed to retrieve projects: '.$runtimeException->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'offset' => $schema->integer()
                ->description('Number of projects to skip (0-based). Defaults to 0.')
                ->default(0),
            'limit' => $schema->integer()
                ->description('Number of projects to return (1–100). Defaults to 100.')
                ->default(100),
        ];
    }
}
