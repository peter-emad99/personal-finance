<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use BelongsToUser, SoftDeletes;

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
            ->withPivot('amount_egp', 'user_id')->withTimestamps();
    }

    /** @return HasMany<AssetValuation, $this> */
    public function valuations(): HasMany
    {
        return $this->hasMany(AssetValuation::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<AssetType, $this> */
    public function assetType(): BelongsTo
    {
        return $this->belongsTo(AssetType::class);
    }
}
