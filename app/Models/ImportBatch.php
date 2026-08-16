<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ImportBatch extends Model
{
    use BelongsToUser, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    /** @return HasMany<ImportRow, $this> */
    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class);
    }

    public function refreshCounts(): self
    {
        $pending = $this->rows()->whereIn('review_state', ['pending', 'duplicate'])->count();
        $accepted = $this->rows()->where('review_state', 'accepted')->count();
        $this->update([
            'total_rows' => $this->rows()->count(),
            'duplicate_rows' => $this->rows()->whereIn('review_state', ['duplicate', 'rejected_duplicate'])->count(),
            'status' => $accepted === 0 ? 'review' : ($pending === 0 ? 'posted' : 'partially_posted'),
        ]);

        return $this->refresh();
    }
}
