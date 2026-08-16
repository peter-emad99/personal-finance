<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Goal extends Model
{
    use BelongsToUser, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'target_amount_egp' => 'decimal:2',
            'monthly_contribution_egp' => 'decimal:2',
            'deadline' => 'date',
        ];
    }

    /** @return HasMany<Bucket, $this> */
    public function buckets(): HasMany
    {
        return $this->hasMany(Bucket::class);
    }
}
