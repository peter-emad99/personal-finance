<?php

namespace App\Services;

use App\Models\AllocationPlan;
use App\Models\AllocationPlanItem;
use App\Models\Bucket;
use App\Models\LedgerTransaction;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

/**
 * Derives monthly allocation actuals from confirmed ledger transactions.
 *
 * The calculation is deterministic: running it again replaces the derived
 * values for the month instead of adding them a second time. An explicit
 * purpose bucket on a transaction or split wins; otherwise the service uses
 * a transparent text/type fallback for goal, emergency, and investment flows.
 */
class AllocationActualService
{
    public function __construct(private readonly LedgerService $ledger)
    {
    }

    /** @return array<string, mixed> */
    public function preview(AllocationPlan $plan): array
    {
        /** @var EloquentCollection<int, AllocationPlanItem> $items */
        $items = $plan->relationLoaded('items')
            ? $plan->items
            : $plan->items()->with('bucket.goal')->get();
        $items = $items->loadMissing('bucket.goal');
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
                'summary' => $this->emptySummary(),
                'unmappedPurposeAmount' => 0.0,
            ];
        }

        $actuals = [];
        $summary = $this->emptySummary();
        $unmappedPurposeAmount = 0.0;
        foreach ($transactions as $transaction) {
            if ($transaction->isTransfer()) {
                continue;
            }

            $parts = $transaction->splits->isNotEmpty()
                ? $transaction->splits->map(fn ($split): array => [
                    'amount' => (float) $split->amount_egp,
                    'type' => (string) $split->transaction_type,
                    'category' => strtolower((string) ($split->category->name ?? $transaction->category->name ?? '')),
                    'purposeBucketId' => $split->purpose_bucket_id ?? $transaction->purpose_bucket_id,
                ])
                : collect([[
                    'amount' => (float) $transaction->amount_egp,
                    'type' => (string) $transaction->transaction_type,
                    'category' => strtolower((string) ($transaction->category->name ?? '')),
                    'purposeBucketId' => $transaction->purpose_bucket_id,
                ]]);

            foreach ($parts as $part) {
                $amount = round((float) $part['amount'], 2);
                $type = (string) $part['type'];
                $category = (string) $part['category'];
                $this->addToSummary($summary, $type, $category, $amount);

                if (! $this->isPurposeFlow($type)) {
                    continue;
                }

                $bucket = $this->resolveBucket($buckets, $part['purposeBucketId'] ?? null, $type, $category, (string) $transaction->description);
                if ($bucket === null) {
                    $unmappedPurposeAmount = round($unmappedPurposeAmount + $amount, 2);

                    continue;
                }
                $actuals[$bucket->id] = round(($actuals[$bucket->id] ?? 0) + $amount, 2);
            }
        }

        return [
            'source' => 'confirmed_ledger',
            'transactionCount' => $transactions->count(),
            'actuals' => $actuals,
            'summary' => $summary,
            'unmappedPurposeAmount' => $unmappedPurposeAmount,
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
                    'actual_amount_egp' => round((float) ($preview['actuals'][$item->bucket_id] ?? 0), 2),
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
    private function resolveBucket(array $buckets, mixed $explicitBucketId, string $type, string $category, string $description): ?Bucket
    {
        if ($explicitBucketId !== null && isset($buckets[(int) $explicitBucketId])) {
            return $buckets[(int) $explicitBucketId];
        }

        $text = strtolower(trim($category.' '.$description));
        $matched = null;
        foreach ($buckets as $bucket) {
            $names = array_filter([
                strtolower((string) $bucket->name),
                strtolower((string) ($bucket->goal->name ?? '')),
            ]);
            $nameMatch = false;
            foreach ($names as $name) {
                if (str_contains($text, $name)) {
                    $nameMatch = true;
                    break;
                }
            }
            if (($nameMatch || (str_contains($text, 'emergency') && str_contains(strtolower((string) $bucket->name), 'emergency')))
                && ($matched === null || strlen((string) $bucket->name) > strlen((string) $matched->name))) {
                $matched = $bucket;
            }
        }
        if ($matched instanceof Bucket) {
            return $matched;
        }

        if (in_array($type, ['contribution', 'investment'], true)) {
            foreach ($buckets as $bucket) {
                if ($bucket->goal_id === null && ! str_contains(strtolower((string) $bucket->name), 'emergency')) {
                    return $bucket;
                }
            }
        }

        return null;
    }

    private function isPurposeFlow(string $type): bool
    {
        return in_array($type, ['contribution', 'investment'], true);
    }

    /** @param array<string, float> $summary */
    private function addToSummary(array &$summary, string $type, string $category, float $amount): void
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

            return;
        }
        if (in_array($type, ['expense', 'fee', 'tax', 'withdrawal'], true)) {
            if ($type === 'fee' || $type === 'tax') {
                $summary['otherExpenses'] = round($summary['otherExpenses'] + $amount, 2);
            } elseif (str_contains($category, 'recurring')) {
                $summary['commitments'] = round($summary['commitments'] + $amount, 2);
            } elseif (in_array($category, ['essential', 'housing', 'food', 'health', 'utility', 'utilities'], true)) {
                $summary['essentialExpenses'] = round($summary['essentialExpenses'] + $amount, 2);
            } else {
                $summary['lifestyleExpenses'] = round($summary['lifestyleExpenses'] + $amount, 2);
            }
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
        ];
    }
}
