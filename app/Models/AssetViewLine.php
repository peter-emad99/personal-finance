<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AssetViewLine extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_fallback' => 'boolean',
        ];
    }

    /** @return BelongsTo<AssetView, $this> */
    public function view(): BelongsTo
    {
        return $this->belongsTo(AssetView::class, 'asset_view_id');
    }

    /** @return BelongsToMany<AssetType, $this> */
    public function assetTypes(): BelongsToMany
    {
        return $this->belongsToMany(AssetType::class, 'asset_view_line_asset_type', 'asset_view_line_id', 'asset_type_id')
            ->withPivot('asset_view_id', 'sort_order')
            ->orderBy('asset_view_line_asset_type.sort_order')
            ->orderBy('asset_types.label');
    }
}
