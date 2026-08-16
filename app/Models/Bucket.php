<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Bucket extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['target_amount_egp' => 'decimal:2'];
    }

    /** @return BelongsTo<Goal, $this> */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    /** @return BelongsToMany<Asset, $this, AssetBucketAllocation> */
    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'asset_bucket_allocations')
            ->using(AssetBucketAllocation::class)
            ->withPivot('amount_egp')->withTimestamps();
    }
}
