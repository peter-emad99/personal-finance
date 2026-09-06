<?php

namespace App\Models;

use App\Support\OwnerContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AssetType extends Model
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

    /** @return HasMany<Asset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @param  Builder<AssetType>  $query
     * @return Builder<AssetType>
     */
    public function scopeAvailableToOwner(Builder $query, ?int $ownerId = null): Builder
    {
        $ownerId ??= Auth::id() ?? (app()->runningInConsole() ? OwnerContext::id() : null);

        return $query->where(function (Builder $available) use ($ownerId): void {
            $available->where(function (Builder $system) use ($ownerId): void {
                $system->where('is_system', true)->where(function (Builder $systemTypes) use ($ownerId): void {
                    $systemTypes->where('is_core', true);
                    if ($ownerId !== null) {
                        $systemTypes->orWhereExists(function ($assets) use ($ownerId): void {
                            $assets->select(DB::raw(1))
                                ->from('assets')
                                ->whereColumn('assets.asset_type_id', 'asset_types.id')
                                ->where('assets.user_id', $ownerId);
                        });
                    }
                });
            });
            if ($ownerId !== null) {
                $available->orWhere('user_id', $ownerId);
            }
        });
    }
}
