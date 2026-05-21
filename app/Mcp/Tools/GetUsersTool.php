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

#[Description('List all active Redmine users. Use this to resolve a name or login to a numeric user ID before calling other tools.')]
#[IsReadOnly]
final class GetUsersTool extends Tool
{
    use CastsApiData;

    public function handle(Request $request, RedmineService $redmine): Response
    {
        try {
            $users = $redmine->getUsers();

            if ($users === []) {
                return Response::text('No users found.');
            }

            $lines = [count($users)." user(s):\n"];

            foreach ($users as $user) {
                $lines[] = sprintf(
                    '• #%d  %-12s  %s %s',
                    $this->intOf($user['id']),
                    $this->strOf($user['login'] ?? ''),
                    $this->strOf($user['firstname'] ?? ''),
                    $this->strOf($user['lastname'] ?? ''),
                );
            }

            return Response::text(implode("\n", $lines));
        } catch (Throwable $throwable) {
            return Response::error('Failed to retrieve users: '.$throwable->getMessage());
        }
    }
}
