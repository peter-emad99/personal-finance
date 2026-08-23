<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlanTemplate extends Model
{
    use BelongsToUser, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<BudgetRule, $this> */
    public function budgetRules(): HasMany
    {
        return $this->hasMany(BudgetRule::class);
    }

    /** @return HasMany<AllocationRule, $this> */
    public function allocationRules(): HasMany
    {
        return $this->hasMany(AllocationRule::class);
    }

    /** @return HasMany<AllocationPlan, $this> */
    public function allocationPlans(): HasMany
    {
        return $this->hasMany(AllocationPlan::class);
    }
}
