<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Asset extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:6',
            'cost_basis_egp' => 'decimal:2',
            'current_value_egp' => 'decimal:2',
            'unit_price_egp' => 'decimal:6',
            'acquired_on' => 'date',
            'is_liquid' => 'boolean',
        ];
    }

    /** @return BelongsToMany<Bucket, $this, AssetBucketAllocation> */
    public function buckets(): BelongsToMany
    {
        return $this->belongsToMany(Bucket::class, 'asset_bucket_allocations')
            ->using(AssetBucketAllocation::class)
            ->withPivot('amount_egp')->withTimestamps();
    }
}
