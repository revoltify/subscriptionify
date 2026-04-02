<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use Revoltify\Subscriptionify\Contracts\HasFunds;
use Revoltify\Subscriptionify\Contracts\Subscribable;
use Revoltify\Subscriptionify\DTOs\FeatureInfo;
use Revoltify\Subscriptionify\DTOs\ResolvedFeature;
use Revoltify\Subscriptionify\Enums\FeatureType;
use Revoltify\Subscriptionify\Models\Contracts\HasFeature;
use Revoltify\Subscriptionify\Models\Contracts\HasSubscription;

final readonly class FeatureInfoBuilder
{
    public function __construct(
        private FeatureResolver $resolver,
    ) {}

    public function build(Subscribable $subscribable, ResolvedFeature $resolved): FeatureInfo
    {
        return match ($resolved->type()) {
            FeatureType::Toggle => FeatureInfo::toggle($resolved->name(), $resolved->slug()),
            FeatureType::Metered => $this->buildMetered($subscribable, $resolved),
            FeatureType::Consumable, FeatureType::Limit => $this->buildConsumable($subscribable, $resolved),
        };
    }

    /**
     * @return Collection<int, FeatureInfo>
     */
    public function buildAll(Subscribable $subscribable): Collection
    {
        $subscription = $subscribable->subscription();

        if (! $subscription instanceof HasSubscription) {
            /** @var Collection<int, FeatureInfo> */
            return collect();
        }

        $plan = $subscription->getPlan();
        $plan->loadMissing('features');

        /** @var Collection<int, HasFeature> $features */
        $features = $plan->getRelation('features');

        return $features
            ->map(function (HasFeature $feature) use ($subscribable): ?FeatureInfo {
                $resolved = $this->resolver->resolve($subscribable, $feature->getSlug());

                return $resolved instanceof ResolvedFeature
                    ? $this->build($subscribable, $resolved)
                    : null;
            })
            ->filter()
            ->values();
    }

    private function buildMetered(Subscribable $subscribable, ResolvedFeature $resolved): FeatureInfo
    {
        $usage = $this->resolver->findUsage($subscribable, $resolved);

        return FeatureInfo::metered(
            name: $resolved->name(),
            slug: $resolved->slug(),
            used: $usage?->currentUsage() ?? '0',
            unitPrice: $resolved->hasUnitPrice() ? $resolved->unitPrice : null,
            validUntil: $usage?->valid_until?->toDateTimeString(),
            resetPeriod: $resolved->resetPeriod,
            resetInterval: $resolved->resetInterval,
        );
    }

    private function buildConsumable(Subscribable $subscribable, ResolvedFeature $resolved): FeatureInfo
    {
        $usage = $this->resolver->findUsage($subscribable, $resolved);
        $used = $usage?->currentUsage() ?? '0';

        if ($resolved->isUnlimited()) {
            return FeatureInfo::unlimited(
                name: $resolved->name(),
                slug: $resolved->slug(),
                type: $resolved->type(),
                validUntil: $usage?->valid_until?->toDateTimeString(),
                resetPeriod: $resolved->resetPeriod,
                resetInterval: $resolved->resetInterval,
            );
        }

        $remaining = bcsub($resolved->limit, $used);

        if (bccomp($remaining, '0') < 0) {
            $remaining = '0';
        }

        $usedPercent = bcdiv(bcmul($used, '100', 4), $resolved->limit, 4);
        $percentage = (string) Number::percentage((float) $usedPercent, precision: 2);

        $hasOverage = $subscribable instanceof HasFunds && $resolved->hasUnitPrice();

        return new FeatureInfo(
            name: $resolved->name(),
            slug: $resolved->slug(),
            type: $resolved->type(),
            limit: $resolved->limit,
            used: $used,
            remaining: $remaining,
            unlimited: false,
            applicable: true,
            percentage: $percentage,
            validUntil: $usage?->valid_until?->toDateTimeString(),
            overageAvailable: $hasOverage,
            unitPrice: $resolved->hasUnitPrice() ? $resolved->unitPrice : null,
            resetPeriod: $resolved->resetPeriod,
            resetInterval: $resolved->resetInterval,
        );
    }
}
