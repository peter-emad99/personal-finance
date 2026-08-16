<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property float|string $amount_egp
 */
class AssetBucketAllocation extends Pivot
{
    protected function casts(): array
    {
        return ['amount_egp' => 'decimal:2'];
    }
}
