<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class BootstrapOwnerCommand extends Command
{
    protected $signature = 'finance:bootstrap-owner {email?} {--name=Local owner} {--password=}';

    protected $description = 'Create or rotate the local finance owner account used by the dashboard and MCP.';

    public function handle(): int
    {
        $email = (string) ($this->argument('email') ?: config('finance.owner_email'));
        $password = (string) ($this->option('password') ?: $this->secret('Owner password (12+ characters)'));
        if (strlen($password) < 12) {
            $this->error('Use a password with at least 12 characters.');

            return self::FAILURE;
        }

        $owner = User::query()->firstOrNew(['email' => $email]);
        $owner->name = (string) $this->option('name');
        $owner->password = Hash::make($password);
        $owner->email_verified_at ??= Carbon::now();
        $owner->save();

        $this->info("Owner account ready: {$owner->email}");

        return self::SUCCESS;
    }
}
