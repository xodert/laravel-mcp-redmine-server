<?php

declare(strict_types=1);

use App\Console\Commands\CreateMcpToken;
use App\Services\RedmineService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Tool;

arch('no debug functions are used')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'die'])
    ->not->toBeUsed();

arch('strict types are declared in all files')
    ->expect('App')
    ->toUseStrictTypes();

arch('MCP tools extend the Tool base class')
    ->expect('App\Mcp\Tools')
    ->toExtend(Tool::class);

arch('MCP tools are final')
    ->expect('App\Mcp\Tools')
    ->toBeFinal();

arch('MCP servers extend the Server base class')
    ->expect('App\Mcp\Servers')
    ->toExtend(Server::class);

arch('MCP servers are final')
    ->expect('App\Mcp\Servers')
    ->toBeFinal();

arch('models extend Eloquent Model')
    ->expect('App\Models')
    ->toExtend(Model::class);

arch('services are not coupled to HTTP layer')
    ->expect('App\Services')
    ->not->toUse(Request::class)
    ->not->toUse(Response::class);

arch('console commands extend Command')
    ->expect('App\Console\Commands')
    ->toExtend(Command::class);

arch('RedmineService is used by MCP layer only')
    ->expect(RedmineService::class)
    ->toOnlyBeUsedIn('App\Mcp');

arch('commands are final')
    ->expect('App\Console\Commands')
    ->toBeFinal();

arch('CreateMcpToken command exists and is a command')
    ->expect(CreateMcpToken::class)
    ->toExtend(Command::class);

arch('middleware classes are final')
    ->expect('App\Http\Middleware')
    ->toBeFinal();

arch('services are readonly')
    ->expect('App\Services')
    ->toBeReadonly();

arch('MCP concerns are traits')
    ->expect('App\Mcp\Concerns')
    ->toBeTraits();
