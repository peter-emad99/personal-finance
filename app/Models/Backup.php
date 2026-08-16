<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $file_name
 * @property string $disk
 * @property string $path
 * @property string $status
 * @property bool $encrypted
 * @property string $checksum
 * @property int $size_bytes
 * @property Carbon|null $verified_at
 * @property Carbon|null $retention_until
 */
class Backup extends Model
{
    use BelongsToUser, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['encrypted' => 'boolean', 'metadata' => 'array', 'verified_at' => 'datetime', 'retention_until' => 'datetime'];
    }
}
