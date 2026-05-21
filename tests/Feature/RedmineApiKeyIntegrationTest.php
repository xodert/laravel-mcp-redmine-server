<?php

declare(strict_types=1);

use App\Http\Middleware\InjectRedmineApiKey;
use App\Mcp\Tools\GetProjectsTool;
use App\Services\RedmineService;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function (): void {
    config(['redmine.base_url' => 'https://redmine.test', 'redmine.api_key' => 'default-admin-token']);
});

it('uses X-Redmine-API-Key header token instead of env token for Redmine API calls', function (): void {
    Http::fake(['redmine.test/projects.json*' => Http::response(['projects' => [
        ['id' => 1, 'identifier' => 'test', 'name' => 'Test'],
    ]], 200)]);

    // Simulate middleware injecting per-user token
    $httpRequest = Illuminate\Http\Request::create('/mcp/redmine', 'POST');
    $httpRequest->headers->set('X-Redmine-API-Key', 'user-personal-token');
    (new InjectRedmineApiKey)->handle($httpRequest, fn (): Response => new Response);

    // RedmineService now picks up the per-user token from config
    (new GetProjectsTool)->handle(new Request([]), new RedmineService);

    Http::assertSent(fn ($req): bool => $req->header('X-Redmine-API-Key')[0] === 'user-personal-token');
});

it('falls back to env token when X-Redmine-API-Key header is absent', function (): void {
    Http::fake(['redmine.test/projects.json*' => Http::response(['projects' => []], 200)]);

    (new GetProjectsTool)->handle(new Request([]), new RedmineService);

    Http::assertSent(fn ($req): bool => $req->header('X-Redmine-API-Key')[0] === 'default-admin-token');
});

it('ignores empty X-Redmine-API-Key header and uses env token', function (): void {
    Http::fake(['redmine.test/projects.json*' => Http::response(['projects' => []], 200)]);

    $httpRequest = Illuminate\Http\Request::create('/mcp/redmine', 'POST');
    $httpRequest->headers->set('X-Redmine-API-Key', '');
    (new InjectRedmineApiKey)->handle($httpRequest, fn (): Response => new Response);

    (new GetProjectsTool)->handle(new Request([]), new RedmineService);

    Http::assertSent(fn ($req): bool => $req->header('X-Redmine-API-Key')[0] === 'default-admin-token');
});
