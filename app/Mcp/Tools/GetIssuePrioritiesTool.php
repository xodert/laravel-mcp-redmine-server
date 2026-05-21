<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\CastsApiData;
use App\Services\RedmineService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Throwable;

#[Description('List all available issue priorities in this Redmine instance. Call this before using create-issue-tool to get valid priority IDs.')]
#[IsReadOnly]
final class GetIssuePrioritiesTool extends Tool
{
    use CastsApiData;

    public function handle(Request $request, RedmineService $redmine): Response
    {
        try {
            $priorities = $redmine->getIssuePriorities();

            if ($priorities === []) {
                return Response::text('No issue priorities found.');
            }

            $lines = [count($priorities)." priority(ies):\n"];

            foreach ($priorities as $priority) {
                $id = $this->intOf($priority['id']);
                $name = $this->strOf($priority['name'] ?? '');
                $default = ($priority['is_default'] ?? false) === true ? '  [default]' : '';
                $lines[] = sprintf('• #%-4d %s%s', $id, $name, $default);
            }

            return Response::text(implode("\n", $lines));
        } catch (Throwable $throwable) {
            return Response::error('Failed to retrieve issue priorities: '.$throwable->getMessage());
        }
    }
}
