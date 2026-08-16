<?php

namespace App\Support;

use App\Models\User;

/** Explicit owner identity for local MCP/CLI operations. */
final class OwnerContext
{
    private static ?int $id = null;

    public static function id(): ?int
    {
        return self::$id;
    }

    public static function set(int|User $user): void
    {
        self::$id = $user instanceof User ? (int) $user->getKey() : $user;
    }

    public static function clear(): void
    {
        self::$id = null;
    }
}
