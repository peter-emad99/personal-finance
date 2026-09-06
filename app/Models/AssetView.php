<?php

namespace App\Models;

use App\Support\OwnerContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class AssetView extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_system' => 'boolean',
            'is_core' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<AssetViewLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(AssetViewLine::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return Builder<AssetView> */
    public function scopeAvailableToOwner(Builder $query, ?int $ownerId = null): Builder
    {
        $ownerId ??= Auth::id() ?? (app()->runningInConsole() ? OwnerContext::id() : null);

        return $query->where(function (Builder $available) use ($ownerId): void {
            $available->where('is_system', true);
            if ($ownerId !== null) {
                $available->orWhere('user_id', $ownerId);
            }
        });
    }
}
