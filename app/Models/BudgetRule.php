<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BudgetRule extends Model
{
    use BelongsToUser, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount_egp' => 'decimal:2',
            'percent_of_income' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<PlanTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(PlanTemplate::class, 'plan_template_id');
    }

    /** @return BelongsTo<BudgetCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(BudgetCategory::class, 'budget_category_id');
    }

    /** @return BelongsTo<RecurringCommitment, $this> */
    public function recurringCommitment(): BelongsTo
    {
        return $this->belongsTo(RecurringCommitment::class);
    }
}
