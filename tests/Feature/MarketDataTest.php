<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\FxRate;
use App\Models\GoldPrice;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MarketDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_sync_stores_rates_and_revalues_usd_and_gold_assets(): void
    {
        $usd = Asset::create([
            'name' => 'USD cash',
            'type' => 'USD',
            'quantity' => 100,
            'currency' => 'USD',
            'current_value_egp' => 0,
            'unit_price_egp' => 0,
            'liquidity' => 'immediate',
        ]);
        $gold = Asset::create([
            'name' => '24K gold',
            'type' => 'Gold',
            'quantity' => 10,
            'currency' => 'Gold',
            'current_value_egp' => 0,
            'unit_price_egp' => 0,
            'liquidity' => 'longer_term',
        ]);

        Http::fake([
            config('finance.market_rates.fx_url') => Http::response([
                'result' => 'success',
                'base_code' => 'USD',
                'rates' => ['EGP' => 50.88],
            ]),
            config('finance.market_rates.gold_url') => Http::response([
                'symbol' => 'XAU',
                'currency' => 'USD',
                'price' => 4604.399902,
                'updatedAt' => now()->toIso8601String(),
            ]),
        ]);

        $this->artisan('finance:update-market-rates')->assertSuccessful();

        $this->assertDatabaseHas('fx_rates', [
            'base_currency' => 'USD',
            'quote_currency' => 'EGP',
            'source' => 'ExchangeRate-API',
            'rate' => 50.88,
        ]);
        $this->assertDatabaseHas('gold_prices', [
            'karat' => 24,
            'unit' => 'gram',
            'currency' => 'EGP',
            'source' => 'Gold API + ExchangeRate-API',
        ]);
        $this->assertEqualsWithDelta(5088, (float) $usd->fresh()->current_value_egp, 0.01);
        $goldPrice = (float) GoldPrice::query()->value('price');
        $this->assertEqualsWithDelta($goldPrice * 10, (float) $gold->fresh()->current_value_egp, 0.01);
        $this->assertSame('current', app(FinanceService::class)->dashboard()['marketRates']['status']);
    }

    public function test_market_rates_endpoint_is_owner_scoped_and_returns_dashboard_payload(): void
    {
        FxRate::create([
            'base_currency' => 'USD',
            'quote_currency' => 'EGP',
            'rate_date' => now()->toDateString(),
            'rate' => 50.88,
            'source' => 'ExchangeRate-API',
            'method' => 'daily_mid_market',
        ]);
        GoldPrice::create([
            'karat' => 24,
            'unit' => 'gram',
            'currency' => 'EGP',
            'price_date' => now()->toDateString(),
            'price' => 7532.12,
            'source' => 'Gold API + ExchangeRate-API',
            'method' => 'spot_ounce_converted_to_24k_gram',
        ]);

        $this->getJson(route('api.market-rates'))
            ->assertOk()
            ->assertJsonPath('data.usdToEgp.rate', 50.88)
            ->assertJsonPath('data.gold24kPerGram.price', 7532.12);
    }

    public function test_failed_provider_does_not_create_market_data(): void
    {
        Http::fake([
            '*' => Http::response(['error' => 'unavailable'], 503),
        ]);

        $this->artisan('finance:update-market-rates')->assertFailed();

        $this->assertDatabaseCount('fx_rates', 0);
        $this->assertDatabaseCount('gold_prices', 0);
    }
}
