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

    /** @var array<string, mixed> */
    private static array $context = [];

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
     * @param  array<string, mixed>  $context
     */
    public static function withContext(array $context, callable $callback): mixed
    {
        $previous = self::$context;
        self::$context = [...$previous, ...$context];
        try {
            return $callback();
        } finally {
            self::$context = $previous;
        }
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public static function record(string $action, Model $model, ?array $before = null, ?array $after = null, ?string $tool = null, ?string $channel = null): ?AuditLog
    {
        if ($model instanceof AuditLog) {
            return null;
        }

        $ownerId = self::$context['owner_id'] ?? Auth::id() ?? (app()->runningInConsole() ? OwnerContext::id() : null);
        $ownerId ??= $model->getAttribute('user_id');
        if ($ownerId === null) {
            return null;
        }

        return self::recordEvent(
            $action,
            $model::class,
            $model->getKey(),
            $before,
            $after,
            $tool,
            $channel,
            (int) $ownerId,
        );
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public static function recordEvent(
        string $action,
        string $entityType,
        int|string|null $entityId = null,
        ?array $before = null,
        ?array $after = null,
        ?string $tool = null,
        ?string $channel = null,
        ?int $ownerId = null,
    ): ?AuditLog {
        $ownerId ??= self::$context['owner_id'] ?? Auth::id() ?? (app()->runningInConsole() ? OwnerContext::id() : null);
        if ($ownerId === null) {
            return null;
        }

        $channel ??= self::$context['channel'] ?? self::defaultChannel();
        $tool ??= self::$context['tool'] ?? null;
        $requestId = self::$context['request_id'] ?? null;
        if ($requestId === null && ! app()->runningInConsole()) {
            $requestId = request()->header('X-Request-Id') ?: request()->attributes->get('request_id');
        }

        return AuditLog::create([
            'user_id' => $ownerId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'channel' => $channel,
            'tool_name' => $tool,
            'agent_id' => self::$context['agent_id'] ?? match ($channel) {
                'mcp' => 'local-owner-agent',
                'cli' => 'artisan',
                default => 'web-session',
            },
            'request_id' => $requestId ?: (string) Str::uuid(),
            'before_state' => $before,
            'after_state' => $after,
        ]);
    }

    private static function defaultChannel(): string
    {
        if (app()->bound('request') && request()->route() !== null) {
            return 'web';
        }

        return app()->runningInConsole() ? 'cli' : 'web';
    }
}
