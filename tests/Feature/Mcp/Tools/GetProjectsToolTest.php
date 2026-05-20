<?php

declare(strict_types=1);

use App\Mcp\Tools\GetProjectsTool;
use App\Services\RedmineService;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request;

beforeEach(function (): void {
    config(['redmine.base_url' => 'https://redmine.test', 'redmine.api_key' => 'key']);
});

it('lists projects with id, identifier and name', function (): void {
    Http::fake([
        'redmine.test/projects.json*' => Http::response([
            'projects' => [
                ['id' => 1, 'identifier' => 'backend-api', 'name' => 'Backend API'],
                ['id' => 2, 'identifier' => 'mobile-app', 'name' => 'Mobile App'],
            ],
        ], 200),
    ]);

    $response = (new GetProjectsTool)->handle(new Request([]), new RedmineService);

    $text = $response->content()->toArray()['text'];

    expect($response->isError())->toBeFalse()
        ->and($text)->toContain('#1')
        ->and($text)->toContain('backend-api')
        ->and($text)->toContain('Backend API')
        ->and($text)->toContain('#2');
});

it('returns message when no projects found', function (): void {
    Http::fake(['redmine.test/projects.json*' => Http::response(['projects' => []], 200)]);

    $response = (new GetProjectsTool)->handle(new Request([]), new RedmineService);

    expect($response->isError())->toBeFalse()
        ->and($response->content()->toArray()['text'])->toContain('No projects found');
});

it('returns error on api failure', function (): void {
    Http::fake(['redmine.test/projects.json*' => Http::response(null, 500)]);

    $response = (new GetProjectsTool)->handle(new Request([]), new RedmineService);

    expect($response->isError())->toBeTrue()
        ->and($response->content()->toArray()['text'])->toContain('Failed to retrieve projects');
});
