<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetValuation;
use App\Models\FxRate;
use App\Models\GoldPrice;
use App\Support\OwnerContext;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class MarketDataService
{
    private const TROY_OUNCE_TO_GRAM = 31.1034768;

    private const FX_SOURCE = 'ExchangeRate-API';

    private const GOLD_SOURCE = 'Gold API + ExchangeRate-API';

    private const VALUATION_SOURCE = 'daily_market_sync';

    /** @return array<string, mixed> */
    public function syncForOwner(): array
    {
        $today = now()->toDateString();
        $result = [
            'fxUpdated' => false,
            'goldUpdated' => false,
            'assetsUpdated' => 0,
            'errors' => [],
        ];
        $usdToEgp = null;

        try {
            $fxPayload = $this->getJson((string) config('finance.market_rates.fx_url'));
            $usdToEgp = $this->numericValue(data_get($fxPayload, 'rates.EGP'));
            if ($usdToEgp <= 0 || data_get($fxPayload, 'result') !== 'success') {
                throw new RuntimeException('The FX provider returned an invalid USD/EGP rate.');
            }

            FxRate::updateOrCreate(
                [
                    'base_currency' => 'USD',
                    'quote_currency' => 'EGP',
                    'rate_date' => $today,
                    'source' => self::FX_SOURCE,
                ],
                [
                    'rate' => $usdToEgp,
                    'method' => 'daily_mid_market',
                    'notes' => 'Fetched from ExchangeRate-API open access endpoint.',
                ],
            );
            $result['fxUpdated'] = true;
            $result['assetsUpdated'] += $this->updateAssetValues($usdToEgp, null, $today);
        } catch (Throwable $exception) {
            $result['errors']['fx'] = $exception->getMessage();
            Log::warning('Daily FX rate sync failed.', [
                'owner_id' => OwnerContext::id(),
                'exception' => $exception,
            ]);
        }

        if ($usdToEgp !== null) {
            try {
                $goldPayload = $this->getJson((string) config('finance.market_rates.gold_url'));
                $ounceUsd = $this->numericValue(data_get($goldPayload, 'price'));
                if ($ounceUsd <= 0 || strtoupper((string) data_get($goldPayload, 'symbol')) !== 'XAU') {
                    throw new RuntimeException('The gold provider returned an invalid XAU price.');
                }

                $gold24kPerGramEgp = round($ounceUsd * $usdToEgp / self::TROY_OUNCE_TO_GRAM, 6);
                $sourceUpdatedAt = $this->parseTimestamp(data_get($goldPayload, 'updatedAt'));
                GoldPrice::updateOrCreate(
                    [
                        'karat' => 24,
                        'unit' => 'gram',
                        'currency' => 'EGP',
                        'price_date' => $today,
                        'source' => self::GOLD_SOURCE,
                    ],
                    [
                        'price' => $gold24kPerGramEgp,
                        'method' => 'spot_ounce_converted_to_24k_gram',
                        'source_updated_at' => $sourceUpdatedAt,
                        'metadata' => [
                            'gold_provider' => 'https://api.gold-api.com/price/XAU',
                            'fx_provider' => config('finance.market_rates.fx_url'),
                            'ounce_price_usd' => $ounceUsd,
                            'usd_to_egp' => $usdToEgp,
                        ],
                    ],
                );
                $result['goldUpdated'] = true;
                $result['assetsUpdated'] += $this->updateAssetValues(null, $gold24kPerGramEgp, $today);
            } catch (Throwable $exception) {
                $result['errors']['gold'] = $exception->getMessage();
                Log::warning('Daily gold price sync failed.', [
                    'owner_id' => OwnerContext::id(),
                    'exception' => $exception,
                ]);
            }
        } else {
            $result['errors']['gold'] = 'Gold price needs the USD/EGP rate to be converted to EGP.';
        }

        return $result;
    }

    /** @return array<string, mixed> */
    public function dashboardPayload(): array
    {
        $fx = FxRate::query()
            ->where('base_currency', 'USD')
            ->where('quote_currency', 'EGP')
            ->where('source', self::FX_SOURCE)
            ->latest('rate_date')
            ->latest('id')
            ->first();
        $gold = GoldPrice::query()
            ->where('karat', 24)
            ->where('unit', 'gram')
            ->where('currency', 'EGP')
            ->where('source', self::GOLD_SOURCE)
            ->latest('price_date')
            ->latest('id')
            ->first();
        $staleAfterHours = max(1, (int) config('finance.market_rates.stale_after_hours', 48));
        $fxStale = $this->isStale($fx?->updated_at, $staleAfterHours);
        $goldStale = $this->isStale($gold?->updated_at, $staleAfterHours);
        $timestamps = collect([$fx?->updated_at, $gold?->updated_at])
            ->filter()
            ->map(fn (CarbonInterface $timestamp): int => $timestamp->timestamp);
        $updatedAt = $timestamps->isNotEmpty() ? Carbon::createFromTimestamp($timestamps->max())->toIso8601String() : null;

        return [
            'status' => $fx === null || $gold === null ? 'missing' : ($fxStale || $goldStale ? 'stale' : 'current'),
            'updatedAt' => $updatedAt,
            'staleAfterHours' => $staleAfterHours,
            'usdToEgp' => $fx ? [
                'rate' => (float) $fx->rate,
                'priceDate' => $fx->rate_date->toDateString(),
                'updatedAt' => $fx->updated_at?->toIso8601String(),
                'source' => $fx->source,
                'stale' => $fxStale,
            ] : null,
            'gold24kPerGram' => $gold ? [
                'price' => (float) $gold->price,
                'currency' => $gold->currency,
                'unit' => $gold->unit,
                'priceDate' => $gold->price_date->toDateString(),
                'updatedAt' => $gold->updated_at?->toIso8601String(),
                'sourceUpdatedAt' => $gold->source_updated_at?->toIso8601String(),
                'source' => $gold->source,
                'stale' => $goldStale,
            ] : null,
            'disclaimer' => 'Gold is a 24K spot estimate per gram. It does not include Egyptian dealer premiums, workmanship, or taxes.',
        ];
    }

    /** @return array<string, mixed> */
    private function getJson(string $url): array
    {
        if ($url === '') {
            throw new RuntimeException('A market-data provider URL is not configured.');
        }

        try {
            $response = Http::acceptJson()
                ->connectTimeout(5)
                ->timeout(15)
                ->retry(2, 500)
                ->get($url);
        } catch (RequestException $exception) {
            if ($exception->response?->status() === 429) {
                $retryAfter = $exception->response->header('Retry-After');
                $retryMessage = is_numeric($retryAfter) ? " Try again in {$retryAfter} seconds." : ' Try again later.';

                throw new RuntimeException('The market-data provider rate limit was reached.'.$retryMessage, previous: $exception);
            }

            throw $exception;
        }

        if ($response->status() === 429) {
            $retryAfter = $response->header('Retry-After');
            $retryMessage = is_numeric($retryAfter) ? " Try again in {$retryAfter} seconds." : ' Try again later.';

            throw new RuntimeException('The market-data provider rate limit was reached.'.$retryMessage);
        }

        if ($response->failed()) {
            throw new RuntimeException("The market-data provider returned HTTP {$response->status()}.");
        }

        $response->throw();
        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('The market-data provider returned an invalid JSON payload.');
        }

        return $payload;
    }

    private function numericValue(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function parseTimestamp(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function isStale(?CarbonInterface $updatedAt, int $staleAfterHours): bool
    {
        return $updatedAt === null || $updatedAt->lt(now()->subHours($staleAfterHours));
    }

    private function updateAssetValues(?float $usdToEgp, ?float $gold24kPerGramEgp, string $date): int
    {
        $updated = 0;

        foreach (Asset::query()->get() as $asset) {
            $type = strtolower(trim((string) $asset->type));
            $currency = strtoupper(trim((string) $asset->currency));
            $quantity = $asset->quantity === null ? null : (float) $asset->quantity;
            if ($quantity === null || $quantity <= 0) {
                continue;
            }

            $price = null;
            $valuationNotes = null;
            if ($usdToEgp !== null && ($currency === 'USD' || $type === 'usd')) {
                $price = $usdToEgp;
                $valuationNotes = 'Automatic daily mark-to-market using the USD/EGP rate.';
            } elseif ($gold24kPerGramEgp !== null && (str_contains($type, 'gold') || in_array($currency, ['GOLD', 'XAU'], true))) {
                $price = $gold24kPerGramEgp;
                $valuationNotes = 'Automatic daily mark-to-market using the 24K gold spot estimate per gram.';
            }

            if ($price === null) {
                continue;
            }

            $value = round($quantity * $price, 2);
            $previousValue = (float) $asset->current_value_egp;
            $asset->update([
                'current_value_egp' => $value,
                'unit_price_egp' => round($price, 6),
            ]);
            $this->scaleBucketAllocations($asset, $previousValue, $value);
            $valuation = AssetValuation::query()
                ->where('asset_id', $asset->id)
                ->whereDate('valued_on', $date)
                ->where('source', self::VALUATION_SOURCE)
                ->first();
            $valuationData = [
                'asset_id' => $asset->id,
                'valued_on' => $date,
                'value_egp' => $value,
                'quantity' => $quantity,
                'currency' => 'EGP',
                'source' => self::VALUATION_SOURCE,
                'valuation_method' => 'daily_market_price',
                'notes' => $valuationNotes,
            ];
            if ($valuation === null) {
                AssetValuation::create($valuationData);
            } else {
                $valuation->update($valuationData);
            }
            $updated++;
        }

        return $updated;
    }

    private function scaleBucketAllocations(Asset $asset, float $previousValue, float $currentValue): void
    {
        if ($previousValue <= 0 || $asset->buckets()->doesntExist()) {
            return;
        }

        $ratio = $currentValue / $previousValue;
        $before = [];
        $after = [];
        foreach ($asset->buckets()->get() as $bucket) {
            $amount = (float) data_get($bucket, 'pivot.amount_egp', 0);
            $before[] = ['bucket_id' => $bucket->id, 'amount_egp' => $amount];
            $newAmount = round($amount * $ratio, 2);
            $asset->buckets()->updateExistingPivot($bucket->id, [
                'amount_egp' => $newAmount,
            ]);
            $after[] = ['bucket_id' => $bucket->id, 'amount_egp' => $newAmount];
        }
        AuditLogger::record('market_revaluation', $asset, ['bucket_allocations' => $before], ['bucket_allocations' => $after]);
    }
}
