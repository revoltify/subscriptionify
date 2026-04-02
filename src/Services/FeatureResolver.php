<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Revoltify\Subscriptionify\Contracts\Subscribable;
use Revoltify\Subscriptionify\DTOs\ResolvedFeature;
use Revoltify\Subscriptionify\Exceptions\FeatureException;
use Revoltify\Subscriptionify\Models\Contracts\HasFeature;
use Revoltify\Subscriptionify\Models\Contracts\HasFeaturePivot;
use Revoltify\Subscriptionify\Models\Contracts\HasSubscription;
use Revoltify\Subscriptionify\Models\FeatureUsage;

final class FeatureResolver
{
    /** @var array<string, ResolvedFeature|null> */
    private array $cache = [];

    public function resolve(Subscribable $subscribable, string $slug): ?ResolvedFeature
    {
        $key = $this->cacheKey($subscribable, $slug);

        if (! array_key_exists($key, $this->cache)) {
            $this->cache[$key] = $this->buildResolved($subscribable, $slug);
        }

        return $this->cache[$key];
    }

    public function resolveOrFail(Subscribable $subscribable, string $slug): ResolvedFeature
    {
        return $this->resolve($subscribable, $slug)
            ?? throw FeatureException::notFound($slug);
    }

    public function flush(): void
    {
        $this->cache = [];
    }

    public function findUsage(Subscribable $subscribable, ResolvedFeature $resolved): ?FeatureUsage
    {
        if (! $subscribable->relationLoaded('featureUsages')) {
            $subscribable->load('featureUsages');
        }

        /** @var Collection<int, FeatureUsage> $usages */
        $usages = $subscribable->getRelation('featureUsages');

        /** @var FeatureUsage|null */
        return $usages->firstWhere('feature_id', $resolved->feature->getKey());
    }

    private function buildResolved(Subscribable $subscribable, string $slug): ?ResolvedFeature
    {
        $direct = $this->findDirect($subscribable, $slug);
        $plan = $this->findInPlan($subscribable, $slug);

        $feature = $direct ?? $plan;

        if (! $feature instanceof HasFeature) {
            return null;
        }

        $directPivot = $this->extractPivot($direct);
        $planPivot = $this->extractPivot($plan);
        $sourcePivot = $directPivot ?? $planPivot;

        return new ResolvedFeature(
            feature: $feature,
            limit: $this->computeLimit($directPivot, $planPivot),
            unitPrice: $this->resolveUnitPrice($directPivot, $planPivot),
            validUntil: $sourcePivot?->getResetDate(),
            resetPeriod: $sourcePivot?->getResetPeriod(),
            resetInterval: $sourcePivot?->getResetInterval(),
        );
    }

    /**
     * ADDITIVE: plan + direct = total.
     * 0 from either source = unlimited.
     *
     * @return numeric-string
     */
    private function computeLimit(?HasFeaturePivot $direct, ?HasFeaturePivot $plan): string
    {
        // 0 from an existing source means unlimited
        if ($plan instanceof HasFeaturePivot && bccomp($plan->getValue(), '0') === 0) {
            return '0';
        }

        if ($direct instanceof HasFeaturePivot && bccomp($direct->getValue(), '0') === 0) {
            return '0';
        }

        $planValue = $plan?->getValue() ?? '0';
        $directValue = $direct?->getValue() ?? '0';

        return bcadd($planValue, $directValue);
    }

    /**
     * Direct overrides plan (price is not additive).
     *
     * @return numeric-string
     */
    private function resolveUnitPrice(?HasFeaturePivot $direct, ?HasFeaturePivot $plan): string
    {
        if ($direct instanceof HasFeaturePivot) {
            return $direct->getUnitPrice();
        }

        if ($plan instanceof HasFeaturePivot) {
            return $plan->getUnitPrice();
        }

        return '0.00000000';
    }

    private function extractPivot(?HasFeature $feature): ?HasFeaturePivot
    {
        if (! $feature instanceof Model || ! $feature->relationLoaded('pivot')) {
            return null;
        }

        $pivot = $feature->getRelation('pivot');

        return $pivot instanceof HasFeaturePivot ? $pivot : null;
    }

    private function findDirect(Subscribable $subscribable, string $slug): ?HasFeature
    {
        if (! $subscribable->relationLoaded('directFeatures')) {
            $subscribable->load('directFeatures');
        }

        /** @var Collection<int, Model> $features */
        $features = $subscribable->getRelation('directFeatures');

        /** @var HasFeature|null $feature */
        $feature = $features->firstWhere('slug', $slug);

        return $feature;
    }

    private function findInPlan(Subscribable $subscribable, string $slug): ?HasFeature
    {
        $subscription = $subscribable->subscription();

        if (! $subscription instanceof HasSubscription) {
            return null;
        }

        $plan = $subscription->getPlan();

        if (! $plan->relationLoaded('features')) {
            $plan->load('features');
        }

        /** @var Collection<int, Model> $features */
        $features = $plan->getRelation('features');

        /** @var HasFeature|null $feature */
        $feature = $features->firstWhere('slug', $slug);

        return $feature;
    }

    private function cacheKey(Subscribable $subscribable, string $slug): string
    {
        return $subscribable->getMorphClass().':'.$subscribable->getKey().':'.$slug;
    }
}
