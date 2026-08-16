<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Services\AuditLogger;
use App\Support\OwnerContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use LogicException;

/**
 * Adds a mandatory owner boundary to financial records.
 *
 * The owner is taken from the authenticated web session or the explicit local
 * MCP owner context. There is deliberately no "show everything" fallback.
 */
trait BelongsToUser
{
    /** @var array<string, mixed> */
    protected array $auditBeforeState = [];

    protected static function bootBelongsToUser(): void
    {
        static::addGlobalScope('owner', function (Builder $builder): void {
            $ownerId = Auth::id() ?? (app()->runningInConsole() ? OwnerContext::id() : null);
            if ($ownerId === null) {
                $builder->whereRaw('1 = 0');

                return;
            }

            $builder->where($builder->getModel()->qualifyColumn('user_id'), $ownerId);
        });

        static::registerModelEvent('creating', function ($model): void {
            $ownerId = Auth::id() ?? (app()->runningInConsole() ? OwnerContext::id() : null);
            if ($ownerId === null) {
                throw new LogicException('A financial owner context is required to create this record.');
            }
            if ($model->getAttribute('user_id') !== null && (int) $model->getAttribute('user_id') !== (int) $ownerId) {
                throw new LogicException('A record cannot be created for another owner.');
            }
            $model->setAttribute('user_id', $ownerId);
        });

        static::registerModelEvent('updating', function ($model): void {
            $ownerId = Auth::id() ?? (app()->runningInConsole() ? OwnerContext::id() : null);
            if ($ownerId === null || ($model->getAttribute('user_id') !== null && (int) $model->getAttribute('user_id') !== (int) $ownerId)) {
                throw new LogicException('A record cannot be reassigned to another owner.');
            }
            $model->setAttribute('user_id', $ownerId);
            $model->auditBeforeState = $model->getRawOriginal();
        });
        static::registerModelEvent('created', function ($model): void {
            if (! AuditLogger::automaticLoggingMuted()) {
                AuditLogger::record('create', $model, null, $model->toArray());
            }
        });
        static::registerModelEvent('updated', function ($model): void {
            if (! AuditLogger::automaticLoggingMuted()) {
                AuditLogger::record('update', $model, $model->auditBeforeState ?: null, $model->toArray());
            }
            $model->auditBeforeState = [];
        });
        static::registerModelEvent('deleted', function ($model): void {
            if (! AuditLogger::automaticLoggingMuted()) {
                AuditLogger::record('archive', $model, $model->getRawOriginal(), $model->toArray());
            }
        });
        static::registerModelEvent('restored', function ($model): void {
            if (! AuditLogger::automaticLoggingMuted()) {
                AuditLogger::record('restore', $model, $model->getRawOriginal(), $model->toArray());
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->withoutGlobalScope('owner')->where($query->getModel()->qualifyColumn('user_id'), $userId);
    }
}
