<?php

declare(strict_types=1);

use App\Mcp\Tools\UpdateIssueTool;
use App\Services\RedmineService;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;

beforeEach(function (): void {
    config(['redmine.base_url' => 'https://redmine.test', 'redmine.api_key' => 'key']);
});

it('shows assignee change and added note', function (): void {
    Http::fake([
        'redmine.test/issues/42.json' => Http::sequence()
            ->push(['issue' => ['id' => 42, 'subject' => 'Fix bug', 'assigned_to' => ['id' => 3, 'name' => 'Alice Smith']]], 200)
            ->push(null, 204)
            ->push(['issue' => ['id' => 42, 'subject' => 'Fix bug', 'assigned_to' => ['id' => 7, 'name' => 'Bob Jones']]], 200),
    ]);

    $response = (new UpdateIssueTool)->handle(
        new Request(['issue_id' => 42, 'notes' => 'Handing this off', 'assigned_to_id' => 7]),
        new RedmineService,
    );

    expect($response->isError())->toBeFalse()
        ->and($response->content()->toArray()['text'])
        ->toContain('#42')
        ->toContain('Alice Smith')
        ->toContain('→')
        ->toContain('Bob Jones')
        ->toContain('Note added')
        ->toContain('Handing this off');

    Http::assertSent(fn ($req): bool => $req->method() === 'PUT'
        && $req->data() === ['issue' => ['notes' => 'Handing this off', 'assigned_to_id' => 7]]
    );
});

it('adds a note without changing the assignee', function (): void {
    Http::fake([
        'redmine.test/issues/42.json' => Http::sequence()
            ->push(['issue' => ['id' => 42, 'subject' => 'Fix bug']], 200)
            ->push(null, 204)
            ->push(['issue' => ['id' => 42, 'subject' => 'Fix bug']], 200),
    ]);

    $response = (new UpdateIssueTool)->handle(
        new Request(['issue_id' => 42, 'notes' => 'Still investigating']),
        new RedmineService,
    );

    expect($response->isError())->toBeFalse()
        ->and($response->content()->toArray()['text'])
        ->toContain('Note added')
        ->toContain('Still investigating')
        ->not->toContain('assigned_to:');

    Http::assertSent(fn ($req): bool => $req->method() === 'PUT'
        && $req->data() === ['issue' => ['notes' => 'Still investigating']]
    );
});

it('reports when the issue is already assigned to the requested user', function (): void {
    Http::fake([
        'redmine.test/issues/42.json' => Http::sequence()
            ->push(['issue' => ['id' => 42, 'subject' => 'Fix bug', 'assigned_to' => ['id' => 7, 'name' => 'Bob Jones']]], 200)
            ->push(null, 204)
            ->push(['issue' => ['id' => 42, 'subject' => 'Fix bug', 'assigned_to' => ['id' => 7, 'name' => 'Bob Jones']]], 200),
    ]);

    $response = (new UpdateIssueTool)->handle(
        new Request(['issue_id' => 42, 'assigned_to_id' => 7]),
        new RedmineService,
    );

    expect($response->isError())->toBeFalse()
        ->and($response->content()->toArray()['text'])
        ->toContain('Already assigned to Bob Jones')
        ->not->toContain('Note added');
});

it('returns error when redmine silently ignores the assignment', function (): void {
    Http::fake([
        'redmine.test/issues/42.json' => Http::sequence()
            ->push(['issue' => ['id' => 42, 'subject' => 'Fix bug', 'assigned_to' => ['id' => 3, 'name' => 'John Doe']]], 200)
            ->push(null, 204)
            ->push(['issue' => ['id' => 42, 'subject' => 'Fix bug', 'assigned_to' => ['id' => 3, 'name' => 'John Doe']]], 200),
    ]);

    $response = (new UpdateIssueTool)->handle(
        new Request(['issue_id' => 42, 'notes' => 'Handing this off', 'assigned_to_id' => 46]),
        new RedmineService,
    );

    expect($response->isError())->toBeTrue()
        ->and($response->content()->toArray()['text'])
        ->toContain('Note added')
        ->toContain('Assignment was NOT applied')
        ->toContain('the assignee is still John Doe')
        ->toContain('Edit issues')
        ->not->toContain('Already assigned');
});

it('returns error when redmine ignores the assignment of an unassigned issue', function (): void {
    Http::fake([
        'redmine.test/issues/42.json' => Http::sequence()
            ->push(['issue' => ['id' => 42, 'subject' => 'Fix bug']], 200)
            ->push(null, 204)
            ->push(['issue' => ['id' => 42, 'subject' => 'Fix bug']], 200),
    ]);

    $response = (new UpdateIssueTool)->handle(
        new Request(['issue_id' => 42, 'assigned_to_id' => 46]),
        new RedmineService,
    );

    expect($response->isError())->toBeTrue()
        ->and($response->content()->toArray()['text'])->toContain('the assignee is still Unassigned');
});

it('requires notes or assigned_to_id', function (): void {
    Http::fake();

    expect(fn () => (new UpdateIssueTool)->handle(
        new Request(['issue_id' => 42]),
        new RedmineService,
    ))->toThrow(ValidationException::class);

    Http::assertNothingSent();
});

it('returns error when issue not found', function (): void {
    Http::fake(['redmine.test/issues/9999.json' => Http::response(null, 404)]);

    $response = (new UpdateIssueTool)->handle(
        new Request(['issue_id' => 9999, 'notes' => 'Note']),
        new RedmineService,
    );

    expect($response->isError())->toBeTrue()
        ->and($response->content()->toArray()['text'])->toContain('Failed to update issue');
});
