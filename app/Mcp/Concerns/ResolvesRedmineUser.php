<?php

declare(strict_types=1);

namespace App\Mcp\Concerns;

use App\Services\RedmineService;
use Laravel\Mcp\Request;
use RuntimeException;
use Throwable;

trait ResolvesRedmineUser
{
    /**
     * Resolves the Redmine user ID using the following priority:
     *   1. Explicit `redmine_user_id` argument in the request
     *   2. GET /users/current.json (works when API key belongs to a personal account)
     *   3. REDMINE_DEFAULT_USER_ID from config (fallback for instances that restrict /users/current.json)
     *
     * @throws RuntimeException when no user ID can be resolved by any method
     */
    private function resolveRedmineUserId(Request $request, RedmineService $redmine): int
    {
        if ($request->has('redmine_user_id')) {
            return $request->integer('redmine_user_id');
        }

        try {
            $user = $redmine->getCurrentUser();

            if (! empty($user['id'])) {
                return is_scalar($user['id']) ? (int) $user['id'] : 0;
            }
        } catch (Throwable) {
            // Fall through to config default
        }

        $defaultUserId = config('redmine.default_user_id');

        if ($defaultUserId !== null) {
            return is_scalar($defaultUserId) ? (int) $defaultUserId : 0;
        }

        throw new RuntimeException(
            'Could not resolve Redmine user ID. '.
            'Either pass "redmine_user_id" explicitly, or set REDMINE_DEFAULT_USER_ID in .env.'
        );
    }
}
