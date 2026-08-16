<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AllocationPlanItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['planned_amount_egp' => 'decimal:2', 'actual_amount_egp' => 'decimal:2'];
    }

    /** @return BelongsTo<Bucket, $this> */
    public function bucket(): BelongsTo
    {
        return $this->belongsTo(Bucket::class);
    }
}
