<?php

namespace App\Console\Commands;

use App\Mcp\FinancialMcpServer;
use App\Models\User;
use App\Support\OwnerContext;
use Illuminate\Console\Command;

class McpServeCommand extends Command
{
    protected $signature = 'mcp:serve';

    protected $description = 'Run the authenticated local-owner Personal Finance MCP server over stdio';

    public function handle(FinancialMcpServer $server): int
    {
        $email = (string) config('finance.owner_email');
        $owner = User::query()->where('email', $email)->first();
        if ($owner === null) {
            $this->error('No local owner exists. Run php artisan finance:bootstrap-owner first.');

            return self::FAILURE;
        }
        OwnerContext::set($owner);
        $server->run();

        return self::SUCCESS;
    }
}
