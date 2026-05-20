<?php

declare(strict_types=1);

use App\Models\User;

it('creates a sanctum token and outputs it', function (): void {
    $this->artisan('mcp:create-token', ['name' => 'test-client'])
        ->expectsOutputToContain('Token created for client: test-client')
        ->expectsOutputToContain('Store this token securely')
        ->assertSuccessful();
});

it('creates the mcp service account user on first run', function (): void {
    expect(User::query()->where('email', 'mcp-service@localhost')->exists())->toBeFalse();

    $this->artisan('mcp:create-token', ['name' => 'test-client'])->assertSuccessful();

    expect(User::query()->where('email', 'mcp-service@localhost')->exists())->toBeTrue();
});

it('reuses existing mcp service account on repeated runs', function (): void {
    $this->artisan('mcp:create-token', ['name' => 'client-a'])->assertSuccessful();
    $this->artisan('mcp:create-token', ['name' => 'client-b'])->assertSuccessful();

    expect(User::query()->where('email', 'mcp-service@localhost')->count())->toBe(1);
});
