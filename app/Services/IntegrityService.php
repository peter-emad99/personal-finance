<?php

namespace App\Services;

use App\Models\AssetBucketAllocation;
use App\Models\IntegrityCheck;
use App\Models\LedgerTransaction;

class IntegrityService
{
    /** @return array{status: string, error_count: int, findings: list<string>, id: int|null} */
    public function run(): array
    {
        $findings = [];
        $invalidAllocations = AssetBucketAllocation::query()->join('assets', 'assets.id', '=', 'asset_bucket_allocations.asset_id')->whereColumn('asset_bucket_allocations.user_id', '!=', 'assets.user_id')->count();
        if ($invalidAllocations > 0) {
            $findings[] = "{$invalidAllocations} allocation rows cross an asset owner boundary.";
        }
        $duplicates = LedgerTransaction::query()->whereNotNull('fingerprint')->select('fingerprint')->groupBy('fingerprint')->havingRaw('count(*) > 1')->count();
        if ($duplicates > 0) {
            $findings[] = "{$duplicates} duplicate transaction fingerprints require review.";
        }
        $orphaned = AssetBucketAllocation::query()->leftJoin('assets', 'assets.id', '=', 'asset_bucket_allocations.asset_id')->leftJoin('buckets', 'buckets.id', '=', 'asset_bucket_allocations.bucket_id')->where(function ($query): void {
            $query->whereNull('assets.id')->orWhereNull('buckets.id');
        })->count();
        if ($orphaned > 0) {
            $findings[] = "{$orphaned} allocation rows reference missing records.";
        }
        $status = $findings === [] ? 'passed' : 'failed';
        $check = IntegrityCheck::create(['status' => $status, 'error_count' => count($findings), 'findings' => $findings, 'request_id' => request()->header('X-Request-Id') ?: request()->attributes->get('request_id')]);

        return ['status' => $status, 'error_count' => count($findings), 'findings' => $findings, 'id' => $check->getKey()];
    }
}
