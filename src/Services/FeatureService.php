<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Revoltify\Subscriptionify\Contracts\HasFunds;
use Revoltify\Subscriptionify\Contracts\Subscribable;
use Revoltify\Subscriptionify\DTOs\ConsumptionResult;
use Revoltify\Subscriptionify\DTOs\FeatureInfo;
use Revoltify\Subscriptionify\DTOs\ResolvedFeature;
use Revoltify\Subscriptionify\Enums\FeatureType;
use Revoltify\Subscriptionify\Events\FeatureConsumed;
use Revoltify\Subscriptionify\Events\FeatureReleased;
use Revoltify\Subscriptionify\Exceptions\FeatureException;
use Revoltify\Subscriptionify\Exceptions\InsufficientFundsException;
use Revoltify\Subscriptionify\Models\FeatureUsage;

final readonly class FeatureService
{
    /** @var numeric-string */
    public const UNLIMITED = '999999999999';

    public function __construct(
        private FeatureResolver $resolver,
        private FeatureInfoBuilder $infoBuilder,
    ) {}

    /** @param numeric-string $units */
    public function check(Subscribable $subscribable, string $slug, string $units = '1'): bool
    {
        $resolved = $this->resolver->resolve($subscribable, $slug);

        if (! $resolved instanceof ResolvedFeature) {
            return false;
        }

        return match ($resolved->type()) {
            FeatureType::Toggle => true,
            FeatureType::Consumable, FeatureType::Limit => $this->checkQuota($subscribable, $resolved, $units),
            FeatureType::Metered => $this->checkMetered($subscribable, $resolved, $units),
        };
    }

    /** @param numeric-string $units */
    public function consume(Subscribable $subscribable, string $slug, string $units = '1'): void
    {
        $resolved = $this->resolver->resolveOrFail($subscribable, $slug);

        if ($resolved->hasQuota() && ! $this->check($subscribable, $slug, $units)) {
            throw FeatureException::quotaExceeded($slug, $this->remaining($subscribable, $slug), $units);
        }

        $result = match ($resolved->type()) {
            FeatureType::Toggle => ConsumptionResult::free('0'),
            FeatureType::Consumable, FeatureType::Limit => $this->consumeQuota($subscribable, $resolved, $units),
            FeatureType::Metered => $this->consumeMetered($subscribable, $resolved, $units),
        };

        event(new FeatureConsumed(
            subscribable: $subscribable,
            feature: $resolved->feature,
            units: $units,
            remaining: $result->remaining,
            cost: $result->cost,
            usedOverage: $result->usedOverage,
        ));

        // Invalidate the cached relation so subsequent remaining() calls see fresh data
        $subscribable->load('featureUsages');
    }

    /** @param numeric-string $units */
    public function tryConsume(Subscribable $subscribable, string $slug, string $units = '1'): bool
    {
        if (! $this->check($subscribable, $slug, $units)) {
            return false;
        }

        $this->consume($subscribable, $slug, $units);

        return true;
    }

    /** @param numeric-string $units */
    public function release(Subscribable $subscribable, string $slug, string $units = '1'): void
    {
        $resolved = $this->resolver->resolveOrFail($subscribable, $slug);

        if ($resolved->type() !== FeatureType::Limit) {
            throw new FeatureException(sprintf('Feature [%s] is not a limit-type feature.', $slug));
        }

        $usage = $this->getUsage($subscribable, $resolved);

        if (! $usage instanceof FeatureUsage) {
            return;
        }

        $newUsed = bcsub($usage->used, $units);

        if (bccomp($newUsed, '0') < 0) {
            $newUsed = '0';
        }

        $usage->update(['used' => $newUsed]);

        if ($resolved->isUnlimited()) {
            $remaining = self::UNLIMITED;
        } else {
            $remaining = bcsub($resolved->limit, $newUsed);

            if (bccomp($remaining, '0') < 0) {
                $remaining = '0';
            }
        }

        event(new FeatureReleased(
            subscribable: $subscribable,
            feature: $resolved->feature,
            units: $units,
            remaining: $remaining,
        ));

        // Invalidate the cached relation so subsequent remaining() calls see fresh data
        $subscribable->load('featureUsages');
    }

    /** @return numeric-string */
    public function remaining(Subscribable $subscribable, string $slug): string
    {
        $resolved = $this->resolver->resolve($subscribable, $slug);

        if (! $resolved instanceof ResolvedFeature || ! $resolved->hasQuota()) {
            return '0';
        }

        if ($resolved->isUnlimited()) {
            return self::UNLIMITED;
        }

        $used = $this->currentUsage($subscribable, $resolved);

        $remaining = bcsub($resolved->limit, $used);

        return bccomp($remaining, '0') < 0 ? '0' : $remaining;
    }

    public function isUnlimited(Subscribable $subscribable, string $slug): bool
    {
        $resolved = $this->resolver->resolve($subscribable, $slug);

        return $resolved instanceof ResolvedFeature && $resolved->isUnlimited();
    }

    /** @return numeric-string */
    public function remainingOverage(Subscribable $subscribable, string $slug): string
    {
        if (! $subscribable instanceof HasFunds) {
            return '0';
        }

        $resolved = $this->resolver->resolve($subscribable, $slug);

        if (! $resolved instanceof ResolvedFeature || ! $resolved->hasUnitPrice()) {
            return '0';
        }

        $balance = $subscribable->getBalance();

        if (bccomp($balance, '0', 8) <= 0) {
            return '0';
        }

        return bcdiv($balance, $resolved->unitPrice, 0);
    }

    public function usage(Subscribable $subscribable, string $slug): ?FeatureUsage
    {
        $resolved = $this->resolver->resolve($subscribable, $slug);

        if (! $resolved instanceof ResolvedFeature) {
            return null;
        }

        return $this->getUsage($subscribable, $resolved);
    }

    public function featureInfo(Subscribable $subscribable, string $slug): FeatureInfo
    {
        $resolved = $this->resolver->resolveOrFail($subscribable, $slug);

        return $this->infoBuilder->build($subscribable, $resolved);
    }

    /**
     * @return Collection<int, FeatureInfo>
     */
    public function allFeatures(Subscribable $subscribable): Collection
    {
        return $this->infoBuilder->buildAll($subscribable);
    }

    /** @param numeric-string $units */
    private function checkQuota(Subscribable $subscribable, ResolvedFeature $resolved, string $units): bool
    {
        if ($resolved->isUnlimited()) {
            return true;
        }

        $remaining = bcsub($resolved->limit, $this->currentUsage($subscribable, $resolved));

        if (bccomp($remaining, '0') < 0) {
            $remaining = '0';
        }

        if (bccomp($remaining, $units) >= 0) {
            return true;
        }

        if (! $subscribable instanceof HasFunds || ! $resolved->hasUnitPrice()) {
            return false;
        }

        /** @var numeric-string $cost */
        $cost = bcmul($resolved->unitPrice, bcsub($units, $remaining), 8);

        return $subscribable->hasSufficientFunds($cost);
    }

    /** @param numeric-string $units */
    private function checkMetered(Subscribable $subscribable, ResolvedFeature $resolved, string $units): bool
    {
        if (! $resolved->hasUnitPrice() || ! $subscribable instanceof HasFunds) {
            return true;
        }

        /** @var numeric-string $cost */
        $cost = bcmul($resolved->unitPrice, $units, 8);

        return $subscribable->hasSufficientFunds($cost);
    }

    /** @param numeric-string $units */
    private function consumeQuota(Subscribable $subscribable, ResolvedFeature $resolved, string $units): ConsumptionResult
    {
        return DB::transaction(function () use ($subscribable, $resolved, $units): ConsumptionResult {
            $usage = $this->getOrCreateUsage($subscribable, $resolved);

            if (! $subscribable instanceof HasFunds || $resolved->isUnlimited()) {
                return $this->incrementAndReturn($usage, $units, $resolved);
            }

            $remaining = bcsub($resolved->limit, $usage->currentUsage());

            if (bccomp($remaining, '0') < 0) {
                $remaining = '0';
            }

            if (bccomp($units, $remaining) <= 0) {
                return $this->incrementAndReturn($usage, $units, $resolved);
            }

            $overageUnits = bcsub($units, $remaining);
            $cost = $this->chargeOverage($subscribable, $resolved, $overageUnits);

            $usage->update([
                'used' => bcadd($usage->used, $remaining),
                'overage' => bcadd($usage->overage, $overageUnits),
            ]);
            $usage->refresh();

            return ConsumptionResult::withOverage(remaining: '0', cost: $cost);
        });
    }

    /** @param numeric-string $units */
    private function consumeMetered(Subscribable $subscribable, ResolvedFeature $resolved, string $units): ConsumptionResult
    {
        return DB::transaction(function () use ($subscribable, $resolved, $units): ConsumptionResult {
            $cost = '0.00000000';

            if ($resolved->hasUnitPrice() && $subscribable instanceof HasFunds) {
                /** @var numeric-string $cost */
                $cost = bcmul($resolved->unitPrice, $units, 8);

                if (! $subscribable->hasSufficientFunds($cost)) {
                    throw InsufficientFundsException::forAmount($cost);
                }

                $subscribable->deductFunds(
                    $cost,
                    sprintf('Usage: %s (%s units)', $resolved->name(), $units),
                );
            }

            $usage = $this->getOrCreateUsage($subscribable, $resolved);
            $usage->update(['used' => bcadd($usage->used, $units)]);

            return ConsumptionResult::withOverage(remaining: '0', cost: $cost);
        });
    }

    /** @param numeric-string $units */
    private function incrementAndReturn(FeatureUsage $usage, string $units, ResolvedFeature $resolved): ConsumptionResult
    {
        $newUsed = bcadd($usage->used, $units);
        $usage->update(['used' => $newUsed]);
        $usage->refresh();

        if ($resolved->isUnlimited()) {
            $remaining = self::UNLIMITED;
        } else {
            $remaining = bcsub($resolved->limit, $usage->used);

            if (bccomp($remaining, '0') < 0) {
                $remaining = '0';
            }
        }

        return ConsumptionResult::free($remaining);
    }

    /**
     * @param  numeric-string  $overageUnits
     * @return numeric-string
     */
    private function chargeOverage(Subscribable&HasFunds $subscribable, ResolvedFeature $resolved, string $overageUnits): string
    {
        if (! $resolved->hasUnitPrice()) {
            return '0.00000000';
        }

        /** @var numeric-string $cost */
        $cost = bcmul($resolved->unitPrice, $overageUnits, 8);

        if (! $subscribable->hasSufficientFunds($cost)) {
            throw InsufficientFundsException::forAmount($cost);
        }

        $subscribable->deductFunds(
            $cost,
            sprintf('Overage: %s (%s units)', $resolved->name(), $overageUnits),
        );

        return $cost;
    }

    /** @return numeric-string */
    private function currentUsage(Subscribable $subscribable, ResolvedFeature $resolved): string
    {
        return $this->getUsage($subscribable, $resolved)?->currentUsage() ?? '0';
    }

    private function getUsage(Subscribable $subscribable, ResolvedFeature $resolved): ?FeatureUsage
    {
        return $this->resolver->findUsage($subscribable, $resolved);
    }

    private function getOrCreateUsage(Subscribable $subscribable, ResolvedFeature $resolved): FeatureUsage
    {
        /** @var FeatureUsage $usage */
        $usage = $subscribable->featureUsages()
            ->lockForUpdate()
            ->firstOrCreate(
                ['feature_id' => $resolved->feature->getKey()],
                ['used' => '0'],
            );

        if ($usage->expired() || $usage->valid_until === null) {
            $updateData = ['valid_until' => $resolved->validUntil];

            if ($usage->expired()) {
                $updateData['used'] = '0';
                $updateData['overage'] = '0';
                $updateData['last_reset_at'] = now();
            }

            $usage->update($updateData);
            $usage->refresh();
        }

        return $usage;
    }
}
