<?php

declare(strict_types=1);

use App\Mcp\Tools\CreateIssueTool;
use App\Services\RedmineService;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request;

beforeEach(function (): void {
    config(['redmine.base_url' => 'https://redmine.test', 'redmine.api_key' => 'key']);
});

it('returns created issue id and url', function (): void {
    Http::fake([
        'redmine.test/issues.json' => Http::response([
            'issue' => ['id' => 200, 'subject' => 'New feature', 'project' => ['id' => 1, 'name' => 'App']],
        ], 201),
    ]);

    $response = (new CreateIssueTool)->handle(
        new Request(['project_id' => 1, 'subject' => 'New feature']),
        new RedmineService,
    );

    expect($response->isError())->toBeFalse()
        ->and($response->content()->toArray()['text'])
        ->toContain('#200')
        ->toContain('https://redmine.test/issues/200');
});

it('returns error when creation fails', function (): void {
    Http::fake(['redmine.test/issues.json' => Http::response(['errors' => ['Subject cannot be blank']], 422)]);

    $response = (new CreateIssueTool)->handle(
        new Request(['project_id' => 1, 'subject' => 'x']),
        new RedmineService,
    );

    expect($response->isError())->toBeTrue()
        ->and($response->content()->toArray()['text'])->toContain('Failed to create issue');
});
