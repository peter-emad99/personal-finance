<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key', 80);
            $table->string('label', 120);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(100);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_core')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'key']);
            $table->index(['is_system', 'is_active', 'sort_order']);
        });

        Schema::create('asset_view_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_view_id')->constrained('asset_views')->cascadeOnDelete();
            $table->string('key', 80);
            $table->string('label', 120);
            $table->text('description')->nullable();
            $table->string('color', 32)->nullable();
            $table->unsignedInteger('sort_order')->default(100);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_fallback')->default(false);
            $table->timestamps();
            $table->unique(['asset_view_id', 'key']);
            $table->index(['asset_view_id', 'is_active', 'sort_order']);
        });

        Schema::create('asset_view_line_asset_type', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_view_id')->constrained('asset_views')->cascadeOnDelete();
            $table->foreignId('asset_view_line_id')->constrained('asset_view_lines')->cascadeOnDelete();
            $table->foreignId('asset_type_id')->constrained('asset_types')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(100);
            $table->timestamps();
            $table->unique(['asset_view_id', 'asset_type_id']);
            $table->unique(['asset_view_line_id', 'asset_type_id']);
            $table->index(['asset_view_line_id', 'sort_order']);
        });

        $defaultViewId = DB::table('asset_views')->insertGetId([
            'user_id' => null,
            'key' => 'default_portfolio',
            'label' => 'Default portfolio',
            'description' => 'Core portfolio lens supplied by the application.',
            'sort_order' => 10,
            'is_active' => true,
            'is_system' => true,
            'is_core' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $systemGroups = DB::table('asset_groups')->whereNull('user_id')->orderBy('sort_order')->orderBy('id')->get();
        $lineIds = [];
        foreach ($systemGroups as $group) {
            $lineIds[$group->id] = DB::table('asset_view_lines')->insertGetId([
                'asset_view_id' => $defaultViewId,
                'key' => $group->key,
                'label' => $group->label,
                'description' => $group->description,
                'color' => $group->color,
                'sort_order' => $group->sort_order,
                'is_active' => true,
                'is_fallback' => $group->key === 'other',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $fallbackLineId = $lineIds[DB::table('asset_groups')->whereNull('user_id')->where('key', 'other')->value('id')] ?? DB::table('asset_view_lines')->insertGetId([
            'asset_view_id' => $defaultViewId,
            'key' => 'unassigned_other',
            'label' => 'Unassigned / Other',
            'sort_order' => 999,
            'is_active' => true,
            'is_fallback' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (DB::table('asset_types')->whereNull('user_id')->whereNotNull('asset_group_id')->get() as $type) {
            $lineId = $lineIds[$type->asset_group_id] ?? $fallbackLineId;
            DB::table('asset_view_line_asset_type')->insertOrIgnore([
                'asset_view_id' => $defaultViewId,
                'asset_view_line_id' => $lineId,
                'asset_type_id' => $type->id,
                'sort_order' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $ownerIds = DB::table('asset_types')->whereNotNull('user_id')->distinct()->pluck('user_id');
        foreach ($ownerIds as $ownerId) {
            $customTypes = DB::table('asset_types')->where('user_id', $ownerId)->get();
            $customGroups = DB::table('asset_groups')->where('user_id', $ownerId)->orderBy('sort_order')->orderBy('id')->get();
            $legacyViewId = DB::table('asset_views')->insertGetId([
                'user_id' => $ownerId,
                'key' => 'migrated_portfolio',
                'label' => 'Migrated portfolio',
                'description' => 'Compatibility view created from the previous asset grouping catalog.',
                'sort_order' => 20,
                'is_active' => true,
                'is_system' => false,
                'is_core' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $legacyLines = [];
            foreach ($systemGroups as $group) {
                $legacyLines[$group->id] = DB::table('asset_view_lines')->insertGetId([
                    'asset_view_id' => $legacyViewId,
                    'key' => $group->key,
                    'label' => $group->label,
                    'description' => $group->description,
                    'color' => $group->color,
                    'sort_order' => $group->sort_order,
                    'is_active' => true,
                    'is_fallback' => $group->key === 'other',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            foreach ($customGroups as $group) {
                $legacyLines[$group->id] = DB::table('asset_view_lines')->insertGetId([
                    'asset_view_id' => $legacyViewId,
                    'key' => 'custom_'.Str::slug($group->key, '_'),
                    'label' => $group->label,
                    'description' => $group->description,
                    'color' => $group->color,
                    'sort_order' => $group->sort_order,
                    'is_active' => true,
                    'is_fallback' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $legacyFallback = $legacyLines[DB::table('asset_groups')->whereNull('user_id')->where('key', 'other')->value('id')] ?? null;
            foreach (DB::table('asset_types')->whereNull('user_id')->whereNotNull('asset_group_id')->get()->merge($customTypes) as $type) {
                $lineId = $legacyLines[$type->asset_group_id] ?? $legacyFallback;
                if ($lineId === null) {
                    continue;
                }
                DB::table('asset_view_line_asset_type')->insertOrIgnore([
                    'asset_view_id' => $legacyViewId,
                    'asset_view_line_id' => $lineId,
                    'asset_type_id' => $type->id,
                    'sort_order' => 100,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_view_line_asset_type');
        Schema::dropIfExists('asset_view_lines');
        Schema::dropIfExists('asset_views');
    }
};
