<?php

declare(strict_types=1);

namespace App\Mcp\Concerns;

use App\Services\RedmineService;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

trait RegistersForRedmineAdmin
{
    public function shouldRegister(RedmineService $redmine): bool
    {
        try {
            $user = $redmine->getCurrentUser();

            return (bool) ($user['admin'] ?? false);
        } catch (RuntimeException|ConnectionException) {
            try {
                $redmine->getUsers(0, 1);

                return true;
            } catch (RuntimeException|ConnectionException) {
                return false;
            }
        }
    }
}
