<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AllocationPlanExpense extends Model
{
    use BelongsToUser;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'planned_amount_egp' => 'decimal:2',
            'actual_amount_egp' => 'decimal:2',
            'actual_synced_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<AllocationPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(AllocationPlan::class, 'allocation_plan_id');
    }

    /** @return BelongsTo<BudgetCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(BudgetCategory::class, 'budget_category_id');
    }

    /** @return BelongsTo<BudgetRule, $this> */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(BudgetRule::class, 'budget_rule_id');
    }
}
