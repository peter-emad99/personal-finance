<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoldPrice extends Model
{
    use BelongsToUser, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price_date' => 'date',
            'price' => 'decimal:6',
            'source_updated_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
