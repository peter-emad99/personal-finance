<?php

namespace App\Services;

use App\Models\MonthlyFinancialReview;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/** Prevents closed-month actuals from changing outside the review screen. */
class MonthlyReviewGuard
{
    public function assertEditable(CarbonInterface|string $month): void
    {
        $month = Carbon::parse($month)->startOfMonth();
        $isClosed = MonthlyFinancialReview::query()
            ->whereDate('month', $month->toDateString())
            ->where('status', 'closed')
            ->exists();

        if ($isClosed) {
            throw ValidationException::withMessages([
                'month' => 'This month is closed. Reopen it before changing actual transactions.',
            ]);
        }
    }
}
