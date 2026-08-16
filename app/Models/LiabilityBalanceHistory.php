<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LiabilityBalanceHistory extends Model
{
    use BelongsToUser, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'as_of' => 'date',
            'balance_egp' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Liability, $this> */
    public function liability(): BelongsTo
    {
        return $this->belongsTo(Liability::class);
    }
}
