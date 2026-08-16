<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\BackupService;
use App\Services\IntegrityService;
use App\Support\OwnerContext;
use Illuminate\Console\Command;

class VerifyOperationsCommand extends Command
{
    protected $signature = 'finance:verify-operations';

    protected $description = 'Run owner-scoped data-integrity checks and verify the latest backup.';

    public function handle(BackupService $backups, IntegrityService $integrity): int
    {
        $owner = User::query()->where('email', config('finance.owner_email'))->first();
        if ($owner === null) {
            $this->error('No owner account exists.');

            return self::FAILURE;
        }
        OwnerContext::set($owner);
        $result = $integrity->run();
        $this->line('Integrity: '.$result['status']);
        $latest = $owner->backups()->latest()->first();
        if ($latest !== null) {
            $verification = $backups->verify($latest);
            $this->line('Backup: '.($verification['valid'] ? 'verified' : 'failed'));
        } else {
            $this->warn('No backup exists yet.');
        }

        return $result['status'] === 'passed' ? self::SUCCESS : self::FAILURE;
    }
}
