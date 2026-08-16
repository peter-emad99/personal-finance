<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property float|string $amount_egp
 */
class AssetBucketAllocation extends Pivot
{
    use BelongsToUser;

    protected $table = 'asset_bucket_allocations';

    protected function casts(): array
    {
        return ['amount_egp' => 'decimal:2'];
    }
}
