<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransactionCategory extends Model
{
    use BelongsToUser, SoftDeletes;

    protected $guarded = [];
}
