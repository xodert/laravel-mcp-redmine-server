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

it('passes tracker_id and priority_id to the api', function (): void {
    Http::fake([
        'redmine.test/issues.json' => Http::response([
            'issue' => ['id' => 201, 'subject' => 'Bug report', 'project' => ['id' => 1, 'name' => 'App']],
        ], 201),
    ]);

    (new CreateIssueTool)->handle(
        new Request(['project_id' => 1, 'subject' => 'Bug report', 'tracker_id' => 1, 'priority_id' => 5]),
        new RedmineService,
    );

    Http::assertSent(function ($req): bool {
        $data = $req->data();

        return isset($data['issue']['tracker_id']) && $data['issue']['tracker_id'] === 1
            && isset($data['issue']['priority_id']) && $data['issue']['priority_id'] === 5;
    });
});

it('accepts priority_id outside the old hardcoded enum range', function (): void {
    Http::fake([
        'redmine.test/issues.json' => Http::response([
            'issue' => ['id' => 202, 'subject' => 'Custom priority', 'project' => ['id' => 1, 'name' => 'App']],
        ], 201),
    ]);

    $response = (new CreateIssueTool)->handle(
        new Request(['project_id' => 1, 'subject' => 'Custom priority', 'priority_id' => 6]),
        new RedmineService,
    );

    expect($response->isError())->toBeFalse();

    Http::assertSent(fn ($req): bool => ($req->data()['issue']['priority_id'] ?? null) === 6);
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
