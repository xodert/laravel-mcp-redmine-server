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
use RuntimeException;

#[Description('List all available issue statuses in this Redmine instance. Call this before using update-issue-status-tool to get valid status IDs.')]
#[IsReadOnly]
final class GetIssueStatusesTool extends Tool
{
    use CastsApiData;

    public function handle(Request $request, RedmineService $redmine): Response
    {
        try {
            $statuses = $redmine->getIssueStatuses();

            if ($statuses === []) {
                return Response::text('No issue statuses found.');
            }

            $lines = [count($statuses)." issue status(es):\n"];

            foreach ($statuses as $status) {
                $id = $this->intOf($status['id']);
                $name = $this->strOf($status['name'] ?? '');
                $closed = ($status['is_closed'] ?? false) === true ? '  [closed]' : '';
                $lines[] = sprintf('• #%-4d %s%s', $id, $name, $closed);
            }

            return Response::text(implode("\n", $lines));
        } catch (RuntimeException $runtimeException) {
            return Response::error('Failed to retrieve issue statuses: '.$runtimeException->getMessage());
        }
    }
}
