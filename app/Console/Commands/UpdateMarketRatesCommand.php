<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\MarketDataService;
use App\Support\OwnerContext;
use Illuminate\Console\Command;

class UpdateMarketRatesCommand extends Command
{
    protected $signature = 'finance:update-market-rates';

    protected $description = 'Fetch the daily USD/EGP and 24K gold price, then update matching assets.';

    public function handle(MarketDataService $marketData): int
    {
        $hasFailures = false;

        foreach (User::query()->orderBy('id')->get() as $owner) {
            OwnerContext::set($owner);
            $result = $marketData->syncForOwner();
            $this->line(sprintf(
                'Owner %s: FX %s, gold %s, assets updated %d.',
                $owner->email,
                $result['fxUpdated'] ? 'updated' : 'failed',
                $result['goldUpdated'] ? 'updated' : 'failed',
                $result['assetsUpdated'],
            ));
            foreach ($result['errors'] as $market => $error) {
                $this->warn(sprintf('%s: %s', strtoupper((string) $market), $error));
                $hasFailures = true;
            }
        }

        OwnerContext::clear();

        return $hasFailures ? self::FAILURE : self::SUCCESS;
    }
}
