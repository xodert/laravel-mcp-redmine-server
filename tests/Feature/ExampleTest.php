<?php

declare(strict_types=1);

it('returns service status on the root endpoint', function (): void {
    $this->getJson('/')->assertOk()->assertJson(['service' => 'Redmine MCP Server', 'status' => 'ok']);
});
