<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\BackupService;
use App\Support\OwnerContext;
use Illuminate\Console\Command;

class PurgeExpiredBackupsCommand extends Command
{
    protected $signature = 'finance:purge-expired-backups {--force : Confirm deletion of expired encrypted backup archives}';

    protected $description = 'Permanently purge encrypted backup files past their retention date.';

    public function handle(BackupService $backups): int
    {
        if (! $this->option('force')) {
            $this->error('Pass --force after reviewing retention and recovery requirements.');

            return self::FAILURE;
        }
        $owner = User::query()->where('email', config('finance.owner_email'))->first();
        if ($owner === null) {
            $this->error('No owner account exists.');

            return self::FAILURE;
        }
        OwnerContext::set($owner);
        $this->info('Purged '.$backups->purgeExpired($owner).' expired backup(s).');

        return self::SUCCESS;
    }
}
