<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\AssetView;
use App\Models\AssetViewLine;
use App\Models\User;
use App\Services\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AssetViewCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_views_are_owner_scoped_and_types_can_be_reused_across_views(): void
    {
        $owner = auth()->user();
        $type = AssetType::query()->where('key', 'bank_cash')->firstOrFail();
        $first = AssetView::create(['user_id' => $owner->id, 'key' => 'liquid_lens', 'label' => 'Liquid lens', 'is_active' => true, 'is_system' => false]);
        $second = AssetView::create(['user_id' => $owner->id, 'key' => 'planning_lens', 'label' => 'Planning lens', 'is_active' => true, 'is_system' => false]);
        $firstLine = AssetViewLine::create(['asset_view_id' => $first->id, 'key' => 'cash', 'label' => 'Cash', 'is_active' => true, 'is_fallback' => false]);
        $secondLine = AssetViewLine::create(['asset_view_id' => $second->id, 'key' => 'near_term', 'label' => 'Near term', 'is_active' => true, 'is_fallback' => false]);

        $firstLine->assetTypes()->attach($type->id, ['asset_view_id' => $first->id]);
        $secondLine->assetTypes()->attach($type->id, ['asset_view_id' => $second->id]);

        $other = User::factory()->create();
        $otherViews = $this->actingAs($other)->getJson('/api/asset-views')->assertOk()->json();
        $ownerViews = $this->actingAs($owner)->getJson('/api/asset-views')->assertOk()->json();
        $this->assertNotContains($first->id, collect($otherViews)->pluck('id')->all());
        $this->assertContains($first->id, collect($ownerViews)->pluck('id')->all());
    }

    public function test_a_type_cannot_be_assigned_to_two_lines_in_one_view(): void
    {
        $view = AssetView::create(['user_id' => auth()->id(), 'key' => 'duplicate_guard', 'label' => 'Duplicate guard', 'is_active' => true, 'is_system' => false]);
        $first = AssetViewLine::create(['asset_view_id' => $view->id, 'key' => 'first', 'label' => 'First', 'is_active' => true, 'is_fallback' => false]);
        $second = AssetViewLine::create(['asset_view_id' => $view->id, 'key' => 'second', 'label' => 'Second', 'is_active' => true, 'is_fallback' => false]);
        $type = AssetType::query()->where('key', 'bank_cash')->firstOrFail();
        $first->assetTypes()->attach($type->id, ['asset_view_id' => $view->id]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $second->assetTypes()->attach($type->id, ['asset_view_id' => $view->id]);
    }

    public function test_view_allocation_is_presentation_only_and_keeps_owner_totals(): void
    {
        $type = AssetType::query()->where('key', 'bank_cash')->firstOrFail();
        $asset = Asset::create(['name' => 'View invariant cash', 'asset_type_id' => $type->id, 'currency' => 'EGP', 'current_value_egp' => 1234, 'liquidity' => 'immediate']);
        $before = (float) Asset::query()->sum('current_value_egp');
        $view = AssetView::create(['user_id' => auth()->id(), 'key' => 'invariant_view', 'label' => 'Invariant view', 'is_active' => true, 'is_system' => false]);
        $line = AssetViewLine::create(['asset_view_id' => $view->id, 'key' => 'cash_line', 'label' => 'Cash line', 'is_active' => true, 'is_fallback' => false]);
        $line->assetTypes()->attach($type->id, ['asset_view_id' => $view->id]);

        $data = app(FinanceService::class)->dashboard();
        $allocation = collect($data['assetViewAllocations'][(string) $view->id])->firstWhere('label', 'Cash line');

        $this->assertSame(1234.0, (float) $allocation['value']);
        $this->assertSame($before, (float) Asset::query()->sum('current_value_egp'));
        $this->assertSame(1234.0, (float) $asset->fresh()->current_value_egp);
        $this->assertDatabaseHas('asset_view_line_asset_type', ['asset_view_id' => $view->id, 'asset_type_id' => $type->id]);
    }

    public function test_default_view_covers_every_current_owner_asset_type(): void
    {
        $ownerId = auth()->id();
        $defaultViewId = AssetView::query()->where('is_system', true)->where('key', 'default_portfolio')->value('id');
        $usedTypeIds = Asset::query()->where('user_id', $ownerId)->whereNull('deleted_at')->whereNotNull('asset_type_id')->distinct()->pluck('asset_type_id')->map(fn ($id): int => (int) $id);
        $assignedTypeIds = DB::table('asset_view_line_asset_type')->where('asset_view_id', $defaultViewId)->pluck('asset_type_id')->map(fn ($id): int => (int) $id);

        $this->assertSame([], $usedTypeIds->diff($assignedTypeIds)->values()->all());
        $allocation = collect(app(FinanceService::class)->dashboard()['assetViewAllocations'][(string) $defaultViewId]);
        $this->assertSame(0.0, (float) ($allocation->firstWhere('label', 'Other')['value'] ?? 0));
    }
}
