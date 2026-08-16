<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Goal extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'target_amount_egp' => 'decimal:2',
            'deadline' => 'date',
        ];
    }

    /** @return HasMany<Bucket, $this> */
    public function buckets(): HasMany
    {
        return $this->hasMany(Bucket::class);
    }
}
