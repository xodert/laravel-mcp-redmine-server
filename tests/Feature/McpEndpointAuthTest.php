<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns 401 when no bearer token is provided', function (): void {
    $this->postJson('/mcp/redmine')->assertUnauthorized();
});

it('returns 401 for an invalid bearer token without accept json header', function (): void {
    $response = $this->post('/mcp/redmine', [
        'jsonrpc' => '2.0',
        'method' => 'tools/list',
        'id' => 1,
    ], [
        'Authorization' => 'Bearer invalid-token',
        'Content-Type' => 'application/json',
    ]);

    $response->assertUnauthorized()
        ->assertJson(['message' => 'Unauthenticated.']);
});

it('returns 401 when no bearer token is provided without accept json header', function (): void {
    $response = $this->post('/mcp/redmine', [
        'jsonrpc' => '2.0',
        'method' => 'tools/list',
        'id' => 1,
    ], [
        'Content-Type' => 'application/json',
    ]);

    $response->assertUnauthorized()
        ->assertJson(['message' => 'Unauthenticated.']);
});

it('returns 403 when token lacks the mcp ability', function (): void {
    Sanctum::actingAs(User::factory()->create(), ['other-ability']);

    $this->postJson('/mcp/redmine')->assertForbidden();
});

it('passes auth when token has the mcp ability', function (): void {
    Sanctum::actingAs(User::factory()->create(), ['mcp']);

    $response = $this->postJson('/mcp/redmine', [
        'jsonrpc' => '2.0',
        'method' => 'tools/list',
        'id' => 1,
    ]);

    $response->assertOk();
});
