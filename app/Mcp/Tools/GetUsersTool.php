<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\CastsApiData;
use App\Mcp\Concerns\RegistersForRedmineAdmin;
use App\Services\RedmineService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use RuntimeException;

#[Description('List all active Redmine users. Use this to resolve a name or login to a numeric user ID before calling other tools.')]
#[IsReadOnly]
final class GetUsersTool extends Tool
{
    use CastsApiData;
    use RegistersForRedmineAdmin;

    public function handle(Request $request, RedmineService $redmine): Response
    {
        try {
            $request->validate([
                'offset' => ['nullable', 'integer', 'min:0'],
                'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);

            $offset = $request->integer('offset', 0);
            $limit = $request->integer('limit', 100);

            $result = $redmine->getUsers($offset, $limit);
            $users = $result['items'];
            $total = $result['total'];

            if ($users === []) {
                return Response::text('No users found.');
            }

            $nextOffset = $offset + count($users);
            $header = $nextOffset < $total
                ? sprintf('%d total user(s), showing %d–%d. Use offset=%d for the next page.', $total, $offset + 1, $nextOffset, $nextOffset)
                : sprintf('%d user(s):', count($users));

            $lines = [$header."\n"];

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
        } catch (RuntimeException $runtimeException) {
            return Response::error('Failed to retrieve users: '.$runtimeException->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'offset' => $schema->integer()
                ->description('Number of users to skip (0-based). Defaults to 0.')
                ->default(0),
            'limit' => $schema->integer()
                ->description('Number of users to return (1–100). Defaults to 100.')
                ->default(100),
        ];
    }
}
