<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AllocationPlanItem extends Model
{
    use BelongsToUser;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'planned_amount_egp' => 'decimal:2',
            'actual_amount_egp' => 'decimal:2',
            'allocation_percent' => 'decimal:3',
            'actual_synced_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Bucket, $this> */
    public function bucket(): BelongsTo
    {
        return $this->belongsTo(Bucket::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
