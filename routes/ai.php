<?php

declare(strict_types=1);

use App\Http\Middleware\InjectRedmineApiKey;
use App\Mcp\Servers\RedmineServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/redmine', RedmineServer::class)
    ->middleware(['auth:sanctum', 'throttle:120,1', InjectRedmineApiKey::class]);

Mcp::local('redmine', RedmineServer::class);
