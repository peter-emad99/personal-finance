<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class IntegrityCheck extends Model
{
    use BelongsToUser;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['findings' => 'array'];
    }
}
