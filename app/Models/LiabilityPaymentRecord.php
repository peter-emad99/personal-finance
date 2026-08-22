<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LiabilityPaymentRecord extends Model
{
    use BelongsToUser, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'payment_egp' => 'decimal:2',
            'principal_egp' => 'decimal:2',
            'interest_egp' => 'decimal:2',
            'fees_egp' => 'decimal:2',
            'balance_after_egp' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Liability, $this> */
    public function liability(): BelongsTo
    {
        return $this->belongsTo(Liability::class);
    }
}
