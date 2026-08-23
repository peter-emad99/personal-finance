<?php

namespace App\Services;

use App\Models\AllocationPlan;
use App\Models\AllocationPlanItem;
use App\Models\Bucket;
use App\Models\BudgetCategory;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Derives monthly allocation actuals from confirmed ledger transactions.
 *
 * The calculation is deterministic: running it again replaces the derived
 * values for the month instead of adding them a second time. Actuals are
 * linked only through explicit transaction category and purpose-bucket IDs.
 * Unlinked transactions stay visible as unmapped; they are never guessed
 * from a description, category name, or bucket name.
 */
class AllocationActualService
{
    public function __construct(private readonly LedgerService $ledger) {}

    /** @return array<string, mixed> */
    public function preview(AllocationPlan $plan): array
    {
        /** @var EloquentCollection<int, AllocationPlanItem> $items */
        $items = $plan->relationLoaded('items')
            ? $plan->items
            : $plan->items()->with('bucket.goal')->get();
        $items = $items->loadMissing('bucket.goal');
        $expenseItems = $plan->relationLoaded('expenseItems')
            ? $plan->expenseItems
            : $plan->expenseItems()->with('category')->get();
        $expenseItems = $expenseItems->loadMissing('category');
        $plannedCategories = $expenseItems->pluck('category')->filter()->unique('id')->values();
        $buckets = [];
        foreach ($items as $item) {
            if ($item->bucket instanceof Bucket) {
                $buckets[(int) $item->bucket_id] = $item->bucket;
            }
        }
        $transactions = $this->ledger->confirmedForMonth(Carbon::parse($plan->month));

        if ($transactions->isEmpty()) {
            return [
                'source' => 'confirmed_ledger_unavailable',
                'transactionCount' => 0,
                'actuals' => [],
                'itemActuals' => [],
                'expenseActuals' => [],
                'summary' => $this->emptySummary(),
                'unmappedPurposeAmount' => 0.0,
                'unmappedExpenseAmount' => 0.0,
                'matchedExpenseAmount' => 0.0,
            ];
        }

        $actuals = [];
        $summary = $this->emptySummary();
        $expenseActuals = [];
        $unmappedPurposeAmount = 0.0;
        $totalExpenseAmount = 0.0;
        foreach ($transactions as $transaction) {
            if ($transaction->isTransfer()) {
                continue;
            }

            $parts = $transaction->splits->isNotEmpty()
                ? $transaction->splits->map(fn ($split): array => [
                    'amount' => (float) $split->amount_egp,
                    'type' => (string) $split->transaction_type,
                    'budgetCategoryId' => $split->category?->budget_category_id ?? $transaction->category?->budget_category_id,
                    'purposeBucketId' => $split->purpose_bucket_id ?? $transaction->purpose_bucket_id,
                ])
                : collect([[
                    'amount' => (float) $transaction->amount_egp,
                    'type' => (string) $transaction->transaction_type,
                    'budgetCategoryId' => $transaction->category?->budget_category_id,
                    'purposeBucketId' => $transaction->purpose_bucket_id,
                ]]);

            foreach ($parts as $part) {
                $amount = round((float) $part['amount'], 2);
                $type = (string) $part['type'];
                $this->addToSummary($summary, $type, $amount);

                if (in_array($type, ['expense', 'fee', 'tax', 'withdrawal', 'debt_payment', 'obligation'], true)) {
                    $totalExpenseAmount = round($totalExpenseAmount + $amount, 2);
                    $expenseCategory = $this->resolveExpenseCategory($part['budgetCategoryId'] ?? null, $plannedCategories);
                    if ($expenseCategory !== null) {
                        $expenseActuals[$expenseCategory->id] = round(($expenseActuals[$expenseCategory->id] ?? 0) + $amount, 2);
                    }
                }

                if (! $this->isPurposeFlow($type)) {
                    continue;
                }

                $bucket = $this->resolveBucket($buckets, $part['purposeBucketId'] ?? null);
                if ($bucket === null) {
                    $unmappedPurposeAmount = round($unmappedPurposeAmount + $amount, 2);

                    continue;
                }
                $actuals[$bucket->id] = round(($actuals[$bucket->id] ?? 0) + $amount, 2);
            }
        }

        $matchedExpenseAmount = round((float) collect($expenseActuals)->sum(), 2);

        return [
            'source' => 'confirmed_ledger',
            'transactionCount' => $transactions->count(),
            'actuals' => $actuals,
            'itemActuals' => $this->distributeActuals($items, $actuals),
            'expenseActuals' => $expenseActuals,
            'summary' => $summary,
            'unmappedPurposeAmount' => $unmappedPurposeAmount,
            'unmappedExpenseAmount' => round(max(0, $totalExpenseAmount - $matchedExpenseAmount), 2),
            'matchedExpenseAmount' => $matchedExpenseAmount,
        ];
    }

    /** @return array<string, mixed>|null */
    public function previewForMonth(CarbonInterface $month): ?array
    {
        $plan = AllocationPlan::with('items.bucket.goal')->whereDate('month', $month->copy()->startOfMonth()->toDateString())->first();

        return $plan ? $this->preview($plan) : null;
    }

    /** @return array<string, mixed> */
    public function sync(AllocationPlan $plan): array
    {
        $preview = $this->preview($plan);
        if ($preview['source'] !== 'confirmed_ledger') {
            return $preview + ['synced' => false];
        }

        $syncedAt = now();
        DB::transaction(function () use ($plan, $preview, $syncedAt): void {
            $items = $plan->items()->get();
            foreach ($items as $item) {
                $item->update([
                    'actual_amount_egp' => round((float) ($preview['itemActuals'][$item->id] ?? $preview['actuals'][$item->bucket_id] ?? 0), 2),
                    'actual_source' => 'confirmed_ledger',
                    'actual_synced_at' => $syncedAt,
                ]);
            }
            $expenseItems = $plan->expenseItems()->get();
            foreach ($expenseItems as $item) {
                $item->update([
                    'actual_amount_egp' => round((float) ($preview['expenseActuals'][$item->budget_category_id] ?? 0), 2),
                    'actual_source' => 'confirmed_ledger',
                    'actual_synced_at' => $syncedAt,
                ]);
            }
        });

        return $preview + ['synced' => true, 'syncedAt' => $syncedAt->toIso8601String()];
    }

    public function syncMonth(CarbonInterface $month): void
    {
        $plan = AllocationPlan::whereDate('month', $month->copy()->startOfMonth()->toDateString())->first();
        if ($plan !== null) {
            $this->sync($plan);
        }
    }

    /** @param array<int, Bucket> $buckets */
    private function resolveBucket(array $buckets, mixed $explicitBucketId): ?Bucket
    {
        if ($explicitBucketId !== null && isset($buckets[(int) $explicitBucketId])) {
            return $buckets[(int) $explicitBucketId];
        }

        return null;
    }

    private function isPurposeFlow(string $type): bool
    {
        return in_array($type, ['contribution', 'investment'], true);
    }

    private function resolveExpenseCategory(mixed $budgetCategoryId, Collection $plannedCategories): ?BudgetCategory
    {
        if ($budgetCategoryId === null) {
            return null;
        }

        return $plannedCategories->first(fn (BudgetCategory $item): bool => (int) $item->id === (int) $budgetCategoryId);
    }

    /** @param EloquentCollection<int, AllocationPlanItem> $items @param array<int|string, float> $actuals @return array<int, float> */
    private function distributeActuals(EloquentCollection $items, array $actuals): array
    {
        $distributed = [];
        foreach ($items->groupBy('bucket_id') as $bucketId => $bucketItems) {
            $actual = round((float) ($actuals[$bucketId] ?? 0), 2);
            $plannedTotal = (float) $bucketItems->sum('planned_amount_egp');
            $remaining = $actual;
            foreach ($bucketItems->values() as $index => $item) {
                $isLast = $index === $bucketItems->count() - 1;
                $amount = $isLast
                    ? $remaining
                    : ($plannedTotal > 0 ? round($actual * (float) $item->planned_amount_egp / $plannedTotal, 2) : 0.0);
                $distributed[$item->id] = round($amount, 2);
                $remaining = round($remaining - $amount, 2);
            }
        }

        return $distributed;
    }

    /** @param array<string, float> $summary */
    private function addToSummary(array &$summary, string $type, float $amount): void
    {
        if (in_array($type, ['income', 'interest', 'dividend', 'withdrawal_reversal'], true)) {
            $summary['income'] = round($summary['income'] + $amount, 2);

            return;
        }
        if (in_array($type, ['contribution', 'investment'], true)) {
            $summary['invested'] = round($summary['invested'] + $amount, 2);

            return;
        }
        if (in_array($type, ['debt_payment', 'obligation'], true)) {
            $summary['debtPayments'] = round($summary['debtPayments'] + $amount, 2);
            $summary['totalOutflow'] = round($summary['totalOutflow'] + $amount, 2);

            return;
        }
        if (in_array($type, ['expense', 'fee', 'tax', 'withdrawal'], true)) {
            // The exact plan-category totals live in expenseActuals. Keep
            // this compatibility summary neutral instead of classifying by
            // a transaction/category name.
            $summary['otherExpenses'] = round($summary['otherExpenses'] + $amount, 2);
            $summary['totalOutflow'] = round($summary['totalOutflow'] + $amount, 2);
        }
    }

    /** @return array<string, float> */
    private function emptySummary(): array
    {
        return [
            'income' => 0.0,
            'essentialExpenses' => 0.0,
            'lifestyleExpenses' => 0.0,
            'commitments' => 0.0,
            'otherExpenses' => 0.0,
            'debtPayments' => 0.0,
            'invested' => 0.0,
            'totalOutflow' => 0.0,
        ];
    }
}
