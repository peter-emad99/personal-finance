<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AllocationPlanIncome extends Model
{
    use BelongsToUser;

    protected $table = 'allocation_plan_income_items';

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

    /** @return BelongsTo<BudgetRule, $this> */
    public function budgetRule(): BelongsTo
    {
        return $this->belongsTo(BudgetRule::class);
    }
}
