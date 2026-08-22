<?php

namespace App\Services;

use App\Models\Liability;
use App\Models\RecurringCommitment;
use Illuminate\Support\Collection;

/**
 * Compares the obligations captured when a review was closed with today's
 * active records. The comparison is intentionally item-level so a user can
 * see exactly which commitment or debt changed the next month's capacity.
 */
class ObligationChangeService
{
    /**
     * @param  array<string, mixed>|null  $snapshot
     * @param  Collection<int, RecurringCommitment>  $commitments
     * @param  Collection<int, Liability>  $liabilities
     * @return array<string, mixed>
     */
    public function compare(?array $snapshot, Collection $commitments, Collection $liabilities): array
    {
        if (! $snapshot) {
            return [
                'status' => 'no_baseline',
                'hasChanges' => false,
                'capturedAt' => null,
                'summary' => [
                    'previousMonthly' => null,
                    'currentMonthly' => $this->currentMonthly($commitments, $liabilities),
                    'monthlyDelta' => null,
                    'freeCashFlowImpact' => null,
                ],
                'commitments' => $this->emptyGroup($commitments, true),
                'liabilities' => $this->emptyGroup($liabilities, false),
            ];
        }

        $commitmentGroup = $this->compareCommitments($snapshot, $commitments);
        $liabilityGroup = $this->compareLiabilities($snapshot, $liabilities);
        $previousMonthly = round(
            (float) data_get($snapshot, 'commitments.configuredMonthly', 0)
                + (float) data_get($snapshot, 'liabilities.configuredMonthlyPayments', 0),
            2,
        );
        $currentMonthly = round(
            (float) $commitmentGroup['currentMonthly'] + (float) $liabilityGroup['currentMonthly'],
            2,
        );
        $monthlyDelta = round($currentMonthly - $previousMonthly, 2);
        $hasChanges = $commitmentGroup['hasChanges'] || $liabilityGroup['hasChanges'];

        return [
            'status' => $hasChanges ? 'changed' : 'unchanged',
            'hasChanges' => $hasChanges,
            'capturedAt' => data_get($snapshot, 'capturedAt'),
            'summary' => [
                'previousMonthly' => $previousMonthly,
                'currentMonthly' => $currentMonthly,
                'monthlyDelta' => $monthlyDelta,
                'freeCashFlowImpact' => round(-$monthlyDelta, 2),
            ],
            'commitments' => $commitmentGroup,
            'liabilities' => $liabilityGroup,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  Collection<int, RecurringCommitment>  $commitments
     * @return array<string, mixed>
     */
    private function compareCommitments(array $snapshot, Collection $commitments): array
    {
        $before = $this->snapshotItemMap($snapshot, 'commitments.items');
        $after = $commitments->keyBy(fn (RecurringCommitment $item): string => (string) $item->id);
        $added = [];
        $removed = [];
        $changed = [];

        foreach ($after as $id => $item) {
            $current = $this->commitmentRecord($item);
            if (! array_key_exists($id, $before)) {
                $added[] = $current;
                continue;
            }
            $previous = $before[$id];
            $previousMonthly = (float) data_get($previous, 'monthlyAmount', 0);
            if ($previousMonthly !== (float) $current['monthlyAmount'] || (string) data_get($previous, 'name') !== (string) $current['name']) {
                $changed[] = $this->changedRecord($previous, $current, $current['monthlyAmount'] - $previousMonthly, null);
            }
        }
        foreach ($before as $id => $item) {
            if (! $after->has($id)) {
                $removed[] = (array) $item;
            }
        }

        return [
            'currentMonthly' => round((float) $commitments->sum(fn (RecurringCommitment $item): float => $item->monthlyAmount()), 2),
            'hasChanges' => count($added) + count($removed) + count($changed) > 0,
            'added' => $added,
            'removed' => $removed,
            'changed' => $changed,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  Collection<int, Liability>  $liabilities
     * @return array<string, mixed>
     */
    private function compareLiabilities(array $snapshot, Collection $liabilities): array
    {
        $before = $this->snapshotItemMap($snapshot, 'liabilities.items');
        $after = $liabilities->keyBy(fn (Liability $item): string => (string) $item->id);
        $added = [];
        $removed = [];
        $changed = [];

        foreach ($after as $id => $item) {
            $current = $this->liabilityRecord($item);
            if (! array_key_exists($id, $before)) {
                $added[] = $current;
                continue;
            }
            $previous = $before[$id];
            $previousPayment = (float) data_get($previous, 'monthlyPayment', 0);
            $previousBalance = (float) data_get($previous, 'balance', 0);
            if ($previousPayment !== (float) $current['monthlyPayment'] || $previousBalance !== (float) $current['balance'] || (string) data_get($previous, 'name') !== (string) $current['name']) {
                $changed[] = $this->changedRecord(
                    $previous,
                    $current,
                    $current['monthlyPayment'] - $previousPayment,
                    $current['balance'] - $previousBalance,
                );
            }
        }
        foreach ($before as $id => $item) {
            if (! $after->has($id)) {
                $removed[] = (array) $item;
            }
        }

        return [
            'currentMonthly' => round((float) $liabilities->sum(fn (Liability $item): float => (float) $item->monthly_payment_egp), 2),
            'hasChanges' => count($added) + count($removed) + count($changed) > 0,
            'added' => $added,
            'removed' => $removed,
            'changed' => $changed,
        ];
    }

    /** @return array<string, mixed> */
    private function commitmentRecord(RecurringCommitment $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'monthlyAmount' => round($item->monthlyAmount(), 2),
        ];
    }

    /** @return array<string, mixed> */
    private function liabilityRecord(Liability $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'balance' => (float) $item->balance_egp,
            'monthlyPayment' => (float) $item->monthly_payment_egp,
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, mixed>
     */
    private function changedRecord(array $before, array $after, float $monthlyDelta, ?float $balanceDelta): array
    {
        return [
            'before' => $before,
            'after' => $after,
            'monthlyDelta' => round($monthlyDelta, 2),
            'balanceDelta' => $balanceDelta === null ? null : round($balanceDelta, 2),
        ];
    }

    /**
     * @param  Collection<int, mixed>  $items
     * @return array<string, mixed>
     */
    private function emptyGroup(Collection $items, bool $commitments): array
    {
        return [
            'currentMonthly' => $commitments
                ? round((float) $items->sum(fn (RecurringCommitment $item): float => $item->monthlyAmount()), 2)
                : round((float) $items->sum(fn (Liability $item): float => (float) $item->monthly_payment_egp), 2),
            'hasChanges' => false,
            'added' => [],
            'removed' => [],
            'changed' => [],
        ];
    }

    /**
     * @param  Collection<int, RecurringCommitment>  $commitments
     * @param  Collection<int, Liability>  $liabilities
     */
    private function currentMonthly(Collection $commitments, Collection $liabilities): float
    {
        return round(
            (float) $commitments->sum(fn (RecurringCommitment $item): float => $item->monthlyAmount())
                + (float) $liabilities->sum(fn (Liability $item): float => (float) $item->monthly_payment_egp),
            2,
        );
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, array<string, mixed>>
     */
    private function snapshotItemMap(array $snapshot, string $path): array
    {
        $rawItems = data_get($snapshot, $path, []);
        if (! is_array($rawItems)) {
            return [];
        }

        $items = [];
        foreach ($rawItems as $item) {
            if (is_array($item) && array_key_exists('id', $item)) {
                $items[(string) $item['id']] = $item;
            }
        }

        return $items;
    }
}
