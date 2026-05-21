<?php

declare(strict_types=1);

namespace App\Mcp\Concerns;

use App\Services\RedmineService;
use Laravel\Mcp\Request;

trait ResolvesRedmineUser
{
    /**
     * Returns an explicit Redmine user ID or {@see RedmineService::CURRENT_USER}
     * so Redmine resolves the API key owner via `user_id=me` / `assigned_to_id=me`.
     */
    private function resolveRedmineUserFilter(Request $request): int|string
    {
        if ($request->filled('redmine_user_id')) {
            return $request->integer('redmine_user_id');
        }

        return RedmineService::CURRENT_USER;
    }
}
