<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AllocationRule extends Model
{
    use BelongsToUser, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'allocation_percent' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<PlanTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(PlanTemplate::class, 'plan_template_id');
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<Bucket, $this> */
    public function bucket(): BelongsTo
    {
        return $this->belongsTo(Bucket::class);
    }
}
