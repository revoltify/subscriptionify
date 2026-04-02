<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\DTOs;

use Illuminate\Support\Collection;
use Revoltify\Subscriptionify\Enums\Interval;
use Revoltify\Subscriptionify\Enums\SubscriptionStatus;

final readonly class SubscriptionInfo
{
    /**
     * @param  Collection<int, FeatureInfo>  $features
     */
    public function __construct(
        public string $planName,
        public string $planSlug,
        public bool $isFree,
        public ?SubscriptionStatus $status,
        public ?Interval $billingInterval,
        public ?int $billingPeriod,
        public ?string $startsAt,
        public ?string $endsAt,
        public ?string $trialEndsAt,
        public bool $onTrial,
        public bool $onGracePeriod,
        public Collection $features,
    ) {}

    public static function empty(): self
    {
        return new self(
            planName: '',
            planSlug: '',
            isFree: false,
            status: null,
            billingInterval: null,
            billingPeriod: null,
            startsAt: null,
            endsAt: null,
            trialEndsAt: null,
            onTrial: false,
            onGracePeriod: false,
            features: new Collection(),
        );
    }

    public function isActive(): bool
    {
        return $this->status?->isActive() ?? false;
    }
}
