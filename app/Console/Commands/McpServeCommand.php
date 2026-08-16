<?php

namespace App\Console\Commands;

use App\Mcp\FinancialMcpServer;
use Illuminate\Console\Command;

class McpServeCommand extends Command
{
    protected $signature = 'mcp:serve';

    protected $description = 'Run the read-only Personal Finance MCP server over stdio';

    public function handle(FinancialMcpServer $server): int
    {
        $server->run();

        return self::SUCCESS;
    }
}
