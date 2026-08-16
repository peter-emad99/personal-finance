<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Support\OwnerContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

final class AuditLogger
{
    private static bool $automaticLoggingMuted = false;

    public static function muteAutomaticLogging(callable $callback): mixed
    {
        $previous = self::$automaticLoggingMuted;
        self::$automaticLoggingMuted = true;
        try {
            return $callback();
        } finally {
            self::$automaticLoggingMuted = $previous;
        }
    }

    public static function automaticLoggingMuted(): bool
    {
        return self::$automaticLoggingMuted;
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public static function record(string $action, Model $model, ?array $before = null, ?array $after = null, ?string $tool = null): ?AuditLog
    {
        $ownerId = Auth::id() ?? (app()->runningInConsole() ? OwnerContext::id() : null);
        if ($ownerId === null || $model instanceof AuditLog) {
            return null;
        }

        return AuditLog::create([
            'user_id' => $ownerId,
            'action' => $action,
            'entity_type' => $model::class,
            'entity_id' => $model->getKey(),
            'tool_name' => $tool,
            'agent_id' => $tool !== null ? (string) (request()->header('X-Agent-Id') ?: 'local-owner-agent') : 'web-session',
            'request_id' => request()->header('X-Request-Id') ?: (string) (request()->attributes->get('request_id') ?: Str::uuid()),
            'before_state' => $before,
            'after_state' => $after,
        ]);
    }
}
