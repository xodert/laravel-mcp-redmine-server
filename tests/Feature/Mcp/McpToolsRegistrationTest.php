<?php

declare(strict_types=1);

use App\Mcp\Tools\CheckUnloggedUsersTool;
use App\Mcp\Tools\GetProjectsTool;
use App\Mcp\Tools\GetUsersTool;
use App\Models\User;
use App\Services\RedmineService;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    config([
        'redmine.base_url' => 'https://redmine.test',
        'redmine.api_key' => 'test-api-key',
    ]);
});

it('registers admin-only tools for redmine admins', function (): void {
    Http::fake([
        'redmine.test/users/current.json' => Http::response(['user' => ['id' => 1, 'admin' => true]], 200),
    ]);

    expect((new GetUsersTool)->shouldRegister(new RedmineService))->toBeTrue()
        ->and((new CheckUnloggedUsersTool)->shouldRegister(new RedmineService))->toBeTrue();
});

it('does not register admin-only tools for non-admin redmine users', function (): void {
    Http::fake([
        'redmine.test/users/current.json' => Http::response(['user' => ['id' => 5, 'admin' => false]], 200),
    ]);

    expect((new GetUsersTool)->shouldRegister(new RedmineService))->toBeFalse()
        ->and((new CheckUnloggedUsersTool)->shouldRegister(new RedmineService))->toBeFalse();
});

it('does not register admin-only tools when current user lookup fails and users list is forbidden', function (): void {
    Http::fake([
        'redmine.test/users/current.json' => Http::response(null, 403),
        'redmine.test/users.json*' => Http::response(null, 403),
    ]);

    expect((new GetUsersTool)->shouldRegister(new RedmineService))->toBeFalse()
        ->and((new CheckUnloggedUsersTool)->shouldRegister(new RedmineService))->toBeFalse();
});

it('registers admin-only tools when current user lookup fails but users list is allowed', function (): void {
    Http::fake([
        'redmine.test/users/current.json' => Http::response(null, 403),
        'redmine.test/users.json*' => Http::response(['users' => [['id' => 1]], 'total_count' => 1], 200),
    ]);

    expect((new GetUsersTool)->shouldRegister(new RedmineService))->toBeTrue()
        ->and((new CheckUnloggedUsersTool)->shouldRegister(new RedmineService))->toBeTrue();
});

it('always registers tools without admin restriction', function (): void {
    Http::fake([
        'redmine.test/users/current.json' => Http::response(['user' => ['id' => 5, 'admin' => false]], 200),
    ]);

    expect((new GetProjectsTool)->eligibleForRegistration())->toBeTrue();
});

it('omits admin-only tools from tools list for non-admin api keys', function (): void {
    Http::fake([
        'redmine.test/users/current.json' => Http::response(['user' => ['id' => 5, 'admin' => false]], 200),
    ]);

    Sanctum::actingAs(User::factory()->create(), ['mcp']);

    $initialize = $this->postJson('/mcp/redmine', [
        'jsonrpc' => '2.0',
        'method' => 'initialize',
        'id' => 1,
        'params' => [
            'protocolVersion' => '2024-11-05',
            'capabilities' => new stdClass,
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ],
    ], [
        'X-Redmine-API-Key' => 'alice-personal-key',
    ])->assertSuccessful();

    $sessionId = $initialize->headers->get('MCP-Session-Id');

    $response = $this->postJson('/mcp/redmine', [
        'jsonrpc' => '2.0',
        'method' => 'tools/list',
        'id' => 2,
    ], [
        'X-Redmine-API-Key' => 'alice-personal-key',
        'MCP-Session-Id' => $sessionId,
    ])->assertSuccessful();

    $toolNames = collect(data_get($response->json(), 'result.tools', []))
        ->pluck('name')
        ->all();

    expect($toolNames)->toContain('get-projects-tool')
        ->and($toolNames)->not->toContain('get-users-tool')
        ->and($toolNames)->not->toContain('check-unlogged-users-tool')
        ->and($toolNames)->toHaveCount(12);
});

it('includes admin-only tools in tools list for admin api keys', function (): void {
    Http::fake([
        'redmine.test/users/current.json' => Http::response(['user' => ['id' => 1, 'admin' => true]], 200),
    ]);

    Sanctum::actingAs(User::factory()->create(), ['mcp']);

    $initialize = $this->postJson('/mcp/redmine', [
        'jsonrpc' => '2.0',
        'method' => 'initialize',
        'id' => 1,
        'params' => [
            'protocolVersion' => '2024-11-05',
            'capabilities' => new stdClass,
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ],
    ], [
        'X-Redmine-API-Key' => 'admin-personal-key',
    ])->assertSuccessful();

    $sessionId = $initialize->headers->get('MCP-Session-Id');

    $response = $this->postJson('/mcp/redmine', [
        'jsonrpc' => '2.0',
        'method' => 'tools/list',
        'id' => 2,
    ], [
        'X-Redmine-API-Key' => 'admin-personal-key',
        'MCP-Session-Id' => $sessionId,
    ])->assertSuccessful();

    $toolNames = collect(data_get($response->json(), 'result.tools', []))
        ->pluck('name')
        ->all();

    expect($toolNames)->toContain('get-users-tool')
        ->and($toolNames)->toContain('check-unlogged-users-tool')
        ->and($toolNames)->toHaveCount(14);
});
