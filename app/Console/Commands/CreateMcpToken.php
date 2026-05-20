<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class CreateMcpToken extends Command
{
    protected $signature = 'mcp:create-token {name : Token name / client identifier}';

    protected $description = 'Create a Sanctum token for an MCP client';

    public function handle(): int
    {
        $name = (string) ($this->argument('name'));

        $user = User::query()->firstOrCreate(
            ['email' => 'mcp-service@localhost'],
            [
                'name' => 'MCP Service Account',
                'password' => bcrypt(Str::random(32)),
            ]
        );

        $token = $user->createToken($name, ['mcp'])->plainTextToken;

        $this->info('Token created for client: '.$name);
        $this->line('');
        $this->warn('Store this token securely — it will not be shown again.');
        $this->line('');
        $this->line($token);

        return self::SUCCESS;
    }
}
