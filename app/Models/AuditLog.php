<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class AuditLog extends Model
{
    use BelongsToUser;

    private static bool $allowMaintenanceChanges = false;

    protected static function booted(): void
    {
        static::registerModelEvent('updating', function (): void {
            if (! self::$allowMaintenanceChanges) {
                throw new LogicException('Audit logs are append-only.');
            }
        });
        static::registerModelEvent('deleting', function (): void {
            throw new LogicException('Audit logs are append-only.');
        });
    }

    public static function allowMaintenanceChanges(callable $callback): mixed
    {
        $previous = self::$allowMaintenanceChanges;
        self::$allowMaintenanceChanges = true;
        try {
            return $callback();
        } finally {
            self::$allowMaintenanceChanges = $previous;
        }
    }

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'before_state' => 'array',
            'after_state' => 'array',
        ];
    }
}
