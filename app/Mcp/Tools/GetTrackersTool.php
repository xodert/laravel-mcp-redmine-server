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

#[Description('List all available issue trackers in this Redmine instance (e.g. Bug, Feature, Support). Call this before using create-issue-tool to get valid tracker_id values.')]
#[IsReadOnly]
final class GetTrackersTool extends Tool
{
    use CastsApiData;

    public function handle(Request $request, RedmineService $redmine): Response
    {
        try {
            $trackers = $redmine->getTrackers();

            if ($trackers === []) {
                return Response::text('No trackers found.');
            }

            $lines = [count($trackers)." tracker(s):\n"];

            foreach ($trackers as $tracker) {
                $id = $this->intOf($tracker['id']);
                $name = $this->strOf($tracker['name'] ?? '');
                $lines[] = sprintf('• #%-4d %s', $id, $name);
            }

            return Response::text(implode("\n", $lines));
        } catch (RuntimeException $throwable) {
            return Response::error('Failed to retrieve trackers: '.$throwable->getMessage());
        }
    }
}
