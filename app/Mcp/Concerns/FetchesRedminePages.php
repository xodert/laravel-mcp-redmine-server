<?php

declare(strict_types=1);

namespace App\Mcp\Concerns;

use App\Services\RedmineService;
use RuntimeException;

trait FetchesRedminePages
{
    /**
     * Fetches every active user across all pages (bounded by total_count).
     *
     * @return list<array<string, mixed>>
     *
     * @throws RuntimeException
     */
    private function fetchAllUsers(RedmineService $redmine): array
    {
        $all = [];
        $offset = 0;

        do {
            $result = $redmine->getUsers($offset, 100);

            if ($result['items'] === []) {
                break;
            }

            $all = array_merge($all, $result['items']);
            $offset += count($result['items']);
        } while ($offset < $result['total']);

        return $all;
    }

    /**
     * Fetches every time entry for the given date and returns unique user IDs.
     *
     * @return list<int>
     *
     * @throws RuntimeException
     */
    private function fetchAllLoggedUserIds(RedmineService $redmine, string $date): array
    {
        $userIds = [];
        $offset = 0;

        do {
            $result = $redmine->getTimeLogsByDate($date, $offset, 100);

            if ($result['items'] === []) {
                break;
            }

            foreach ($result['items'] as $entry) {
                $userId = $this->intOf(data_get($entry, 'user.id'));

                if ($userId > 0) {
                    $userIds[] = $userId;
                }
            }

            $offset += count($result['items']);
        } while ($offset < $result['total']);

        return array_values(array_unique($userIds));
    }

    /**
     * @param  list<array<string, mixed>>  $users
     * @return array<int, string>
     */
    private function buildUserNameMap(array $users): array
    {
        $map = [];

        foreach ($users as $user) {
            $id = $this->intOf($user['id']);

            if ($id <= 0) {
                continue;
            }

            $name = mb_trim(sprintf(
                '%s %s',
                $this->strOf($user['firstname'] ?? ''),
                $this->strOf($user['lastname'] ?? ''),
            ));

            $map[$id] = $name !== '' ? $name : $this->strOf($user['login'] ?? (string) $id);
        }

        return $map;
    }
}
