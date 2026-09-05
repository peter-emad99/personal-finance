<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\User;
use App\Services\FinanceService;
use Database\Seeders\DemoWorkspaceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AssetTypeCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_types_are_visible_but_custom_types_are_owner_scoped(): void
    {
        $system = AssetType::query()->where('is_system', true)->where('key', 'etf')->firstOrFail();
        $other = User::factory()->create();
        $custom = AssetType::create([
            'user_id' => $other->id,
            'key' => 'private_collectible',
            'label' => 'Private collectible',
            'class' => 'other',
            'default_liquidity' => 'illiquid',
            'pricing_behavior' => 'manual',
            'is_active' => true,
            'is_system' => false,
        ]);

        $available = AssetType::query()->availableToOwner()->pluck('key')->all();

        $this->assertContains($system->key, $available);
        $this->assertNotContains($custom->key, $available);
    }

    public function test_owner_can_manage_custom_asset_types_from_the_settings_page(): void
    {
        $this->get(route('asset-types.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('assetTypes'));

        $this->post(route('asset-types.store'), [
            'key' => 'crypto_asset',
            'label' => 'Crypto asset',
            'class' => 'investment',
            'default_liquidity' => 'longer_term',
            'pricing_behavior' => 'manual',
        ])->assertRedirect();

        $type = AssetType::query()->where('key', 'crypto_asset')->firstOrFail();
        $this->put(route('asset-types.update', $type), [
            'key' => 'crypto_asset',
            'label' => 'Digital asset',
            'class' => 'investment',
            'default_liquidity' => 'within_3_days',
            'pricing_behavior' => 'manual',
        ])->assertRedirect();

        $this->post(route('asset-types.archive', $type))->assertRedirect();
        $this->assertDatabaseHas('asset_types', [
            'id' => $type->id,
            'label' => 'Digital asset',
            'default_liquidity' => 'within_3_days',
            'is_active' => 0,
        ]);
    }

    public function test_catalog_metadata_does_not_change_owner_numeric_values_and_supports_class_and_type_views(): void
    {
        $etf = AssetType::query()->where('is_system', true)->where('key', 'etf')->firstOrFail();
        $cash = AssetType::query()->where('is_system', true)->where('key', 'foreign_currency')->firstOrFail();
        $cashAsset = Asset::create(['name' => 'USD cash', 'type' => 'USD', 'asset_type_id' => $cash->id, 'currency' => 'USD', 'quantity' => 100, 'cost_basis_egp' => 5000, 'current_value_egp' => 6000, 'liquidity' => 'immediate']);
        $etfAsset = Asset::create(['name' => 'VOO', 'type' => 'ETF', 'asset_type_id' => $etf->id, 'currency' => 'USD', 'quantity' => 1, 'cost_basis_egp' => 5000, 'current_value_egp' => 5500, 'liquidity' => 'longer_term']);
        $before = [$cashAsset->id => $cashAsset->current_value_egp, $etfAsset->id => $etfAsset->current_value_egp];

        $dashboard = app(FinanceService::class)->dashboard();
        $after = Asset::query()->pluck('current_value_egp', 'id')->all();

        $this->assertSame((float) $before[$cashAsset->id], (float) $after[$cashAsset->id]);
        $this->assertSame((float) $before[$etfAsset->id], (float) $after[$etfAsset->id]);
        $this->assertSame(6000.0, (float) collect($dashboard['assetAllocation'])->firstWhere('label', 'Cash')['value']);
        $this->assertSame(5500.0, (float) collect($dashboard['assetTypeAllocation'])->firstWhere('label', 'ETF')['value']);
        $this->assertSame('investment', $dashboard['assets']->firstWhere('id', $etfAsset->id)['assetClass']);
        $this->assertSame('cash', $dashboard['assets']->firstWhere('id', $cashAsset->id)['assetClass']);
    }

    public function test_demo_seeder_is_idempotent_and_uses_catalog_metadata(): void
    {
        config(['finance.demo_enabled' => true]);
        $seeder = app(DemoWorkspaceSeeder::class);
        $seeder->run();
        $demo = User::query()->where('email', config('finance.demo_email'))->firstOrFail();

        $this->assertDatabaseHas('buckets', ['user_id' => $demo->id, 'name' => 'Emergency Reserve', 'target_amount_egp' => 120000]);
        $asset = Asset::forUser($demo->id)->where('name', 'USD ETF example')->firstOrFail();
        $this->assertSame('etf', $asset->assetType?->key);
        $count = Asset::forUser($demo->id)->count();

        $seeder->run();

        $this->assertSame($count, Asset::forUser($demo->id)->count());
        $this->assertSame('etf', Asset::forUser($demo->id)->where('name', 'USD ETF example')->firstOrFail()->assetType?->key);
    }
}
