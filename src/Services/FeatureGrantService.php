<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Services;

use Revoltify\Subscriptionify\Contracts\Subscribable;
use Revoltify\Subscriptionify\Enums\Interval;
use Revoltify\Subscriptionify\Models\Contracts\HasFeature;
use Revoltify\Subscriptionify\Models\Feature;

final readonly class FeatureGrantService
{
    public function __construct(
        private FeatureResolver $resolver,
    ) {}

    public function grant(
        Subscribable $subscribable,
        string $slug,
        ?int $value = null,
        ?string $unitPrice = null,
        ?int $resetPeriod = null,
        ?Interval $resetInterval = null,
    ): void {
        /** @var class-string<Feature> $model */
        $model = config('subscriptionify.models.feature', Feature::class);
        /** @var HasFeature $feature */
        $feature = $model::query()->where('slug', $slug)->firstOrFail();
        $featureId = is_numeric($feature->getKey()) ? (int) $feature->getKey() : 0;

        $pivotData = [
            'value' => $value ?? 0,
            'unit_price' => $unitPrice ?? '0.00000000',
        ];

        if ($resetPeriod !== null) {
            $pivotData['reset_period'] = $resetPeriod;
        }

        if ($resetInterval instanceof Interval) {
            $pivotData['reset_interval'] = $resetInterval;
        }

        $subscribable->directFeatures()->syncWithoutDetaching([$featureId => $pivotData]);
        $this->resolver->flush();
    }

    public function revoke(Subscribable $subscribable, string $slug): void
    {
        /** @var class-string<Feature> $model */
        $model = config('subscriptionify.models.feature', Feature::class);
        /** @var HasFeature $feature */
        $feature = $model::query()->where('slug', $slug)->firstOrFail();

        $subscribable->directFeatures()->detach($feature->getKey());
        $this->resolver->flush();
    }
}
