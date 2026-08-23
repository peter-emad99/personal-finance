<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransactionCategory extends Model
{
    use BelongsToUser, SoftDeletes;

    protected $guarded = [];

    /** @return BelongsTo<BudgetCategory, $this> */
    public function budgetCategory(): BelongsTo
    {
        return $this->belongsTo(BudgetCategory::class, 'budget_category_id');
    }
}
