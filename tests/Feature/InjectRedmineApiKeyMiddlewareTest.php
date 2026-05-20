<?php

declare(strict_types=1);

use App\Http\Middleware\InjectRedmineApiKey;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

it('sets redmine.api_key config from X-Redmine-API-Key header', function (): void {
    config(['redmine.api_key' => 'default-token']);

    $request = Request::create('/mcp/redmine', 'POST');
    $request->headers->set('X-Redmine-API-Key', 'user-personal-token');

    (new InjectRedmineApiKey)->handle($request, fn (): Response => new Response);

    expect(config('redmine.api_key'))->toBe('user-personal-token');
});

it('keeps existing config when header is absent', function (): void {
    config(['redmine.api_key' => 'default-token']);

    $request = Request::create('/mcp/redmine', 'POST');

    (new InjectRedmineApiKey)->handle($request, fn (): Response => new Response);

    expect(config('redmine.api_key'))->toBe('default-token');
});

it('keeps existing config when header is empty string', function (): void {
    config(['redmine.api_key' => 'default-token']);

    $request = Request::create('/mcp/redmine', 'POST');
    $request->headers->set('X-Redmine-API-Key', '');

    (new InjectRedmineApiKey)->handle($request, fn (): Response => new Response);

    expect(config('redmine.api_key'))->toBe('default-token');
});
