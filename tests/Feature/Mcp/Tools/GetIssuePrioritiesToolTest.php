<?php

declare(strict_types=1);

use App\Mcp\Tools\GetIssuePrioritiesTool;
use App\Services\RedmineService;
use Illuminate\Support\Facades\Http;
use Laravel\Mcp\Request;

beforeEach(function (): void {
    config(['redmine.base_url' => 'https://redmine.test', 'redmine.api_key' => 'key']);
});

it('lists priorities with default flag', function (): void {
    Http::fake([
        'redmine.test/enumerations/issue_priorities.json' => Http::response([
            'issue_priorities' => [
                ['id' => 3, 'name' => 'Low', 'is_default' => false],
                ['id' => 4, 'name' => 'Normal', 'is_default' => true],
                ['id' => 5, 'name' => 'High', 'is_default' => false],
            ],
        ], 200),
    ]);

    $response = (new GetIssuePrioritiesTool)->handle(new Request([]), new RedmineService);
    $text = $response->content()->toArray()['text'];

    expect($response->isError())->toBeFalse()
        ->and($text)->toContain('#3')
        ->and($text)->toContain('Normal')
        ->and($text)->toContain('[default]');
});

it('returns message when no priorities found', function (): void {
    Http::fake(['redmine.test/enumerations/issue_priorities.json' => Http::response(['issue_priorities' => []], 200)]);

    $response = (new GetIssuePrioritiesTool)->handle(new Request([]), new RedmineService);

    expect($response->content()->toArray()['text'])->toContain('No issue priorities found');
});

it('returns error on api failure', function (): void {
    Http::fake(['redmine.test/enumerations/issue_priorities.json' => Http::response(null, 500)]);

    $response = (new GetIssuePrioritiesTool)->handle(new Request([]), new RedmineService);

    expect($response->isError())->toBeTrue()
        ->and($response->content()->toArray()['text'])->toContain('Failed to retrieve issue priorities');
});
