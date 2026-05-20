<?php

declare(strict_types=1);

use App\Mcp\Tools\GetUsersTool;
use App\Services\RedmineService;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request;

beforeEach(function (): void {
    config(['redmine.base_url' => 'https://redmine.test', 'redmine.api_key' => 'key']);
});

it('lists users with id, login and full name', function (): void {
    Http::fake([
        'redmine.test/users.json*' => Http::response([
            'users' => [
                ['id' => 3, 'login' => 'alice', 'firstname' => 'Alice', 'lastname' => 'Smith'],
                ['id' => 4, 'login' => 'bob',   'firstname' => 'Bob',   'lastname' => 'Jones'],
            ],
        ], 200),
    ]);

    $response = (new GetUsersTool)->handle(new Request([]), new RedmineService);

    $text = $response->content()->toArray()['text'];

    expect($response->isError())->toBeFalse()
        ->and($text)->toContain('#3')
        ->and($text)->toContain('alice')
        ->and($text)->toContain('Alice Smith')
        ->and($text)->toContain('#4');
});

it('returns message when no users found', function (): void {
    Http::fake(['redmine.test/users.json*' => Http::response(['users' => []], 200)]);

    $response = (new GetUsersTool)->handle(new Request([]), new RedmineService);

    expect($response->isError())->toBeFalse()
        ->and($response->content()->toArray()['text'])->toContain('No users found');
});

it('returns error on api failure', function (): void {
    Http::fake(['redmine.test/users.json*' => Http::response(null, 403)]);

    $response = (new GetUsersTool)->handle(new Request([]), new RedmineService);

    expect($response->isError())->toBeTrue()
        ->and($response->content()->toArray()['text'])->toContain('Failed to retrieve users');
});
