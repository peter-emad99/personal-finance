<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DecisionJournalEntry extends Model
{
    use BelongsToUser, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'assumptions' => 'array',
            'alternatives' => 'array',
            'rule_result' => 'array',
            'review_date' => 'date',
        ];
    }
}
