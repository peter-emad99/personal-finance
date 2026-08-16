<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AllocationPlan extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'planned_income_egp' => 'decimal:2',
            'planned_expenses_egp' => 'decimal:2',
        ];
    }

    /** @return HasMany<AllocationPlanItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(AllocationPlanItem::class);
    }
}
