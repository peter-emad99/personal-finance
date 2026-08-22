<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AllocationPlan extends Model
{
    use BelongsToUser, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'planned_income_egp' => 'decimal:2',
            'planned_expenses_egp' => 'decimal:2',
            'generated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<MonthlyFinancialReview, $this> */
    public function sourceReview(): BelongsTo
    {
        return $this->belongsTo(MonthlyFinancialReview::class, 'source_review_id');
    }

    /** @return HasMany<AllocationPlanItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(AllocationPlanItem::class);
    }
}
