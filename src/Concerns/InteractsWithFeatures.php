<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Concerns;

use Illuminate\Support\Collection;
use Revoltify\Subscriptionify\DTOs\FeatureInfo;
use Revoltify\Subscriptionify\Enums\Interval;
use Revoltify\Subscriptionify\Services\FeatureGrantService;
use Revoltify\Subscriptionify\Services\FeatureService;

trait InteractsWithFeatures
{
    public function hasFeature(string $slug): bool
    {
        return $this->featureService()->check($this, $slug, '0');
    }

    public function canConsume(string $slug, int|float|string $units = 1): bool
    {
        return $this->featureService()->check($this, $slug, (string) $units);
    }

    public function consume(string $slug, int|float|string $units = 1): void
    {
        $this->featureService()->consume($this, $slug, (string) $units);
    }

    public function tryConsume(string $slug, int|float|string $units = 1): bool
    {
        return $this->featureService()->tryConsume($this, $slug, (string) $units);
    }

    public function release(string $slug, int|float|string $units = 1): void
    {
        $this->featureService()->release($this, $slug, (string) $units);
    }

    /** @return numeric-string */
    public function remainingUsage(string $slug): string
    {
        return $this->featureService()->remaining($this, $slug);
    }

    public function isUnlimitedUsage(string $slug): bool
    {
        return $this->featureService()->isUnlimited($this, $slug);
    }

    public function featureInfo(string $slug): FeatureInfo
    {
        return $this->featureService()->featureInfo($this, $slug);
    }

    /**
     * @return Collection<int, FeatureInfo>
     */
    public function allFeatures(): Collection
    {
        return $this->featureService()->allFeatures($this);
    }

    public function grantFeature(
        string $slug,
        ?int $value = null,
        ?string $unitPrice = null,
        ?int $resetPeriod = null,
        ?Interval $resetInterval = null,
    ): void {
        $this->featureGrantService()->grant(
            $this, $slug, $value, $unitPrice, $resetPeriod, $resetInterval,
        );
    }

    public function revokeFeature(string $slug): void
    {
        $this->featureGrantService()->revoke($this, $slug);
    }

    private function featureService(): FeatureService
    {
        return resolve(FeatureService::class);
    }

    private function featureGrantService(): FeatureGrantService
    {
        return resolve(FeatureGrantService::class);
    }
}
