<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Events;

use Revoltify\Subscriptionify\Contracts\Subscribable;
use Revoltify\Subscriptionify\Models\Contracts\HasFeature;

final readonly class FeatureConsumed
{
    /**
     * @param  numeric-string  $units
     * @param  numeric-string  $remaining
     * @param  numeric-string  $cost
     */
    public function __construct(
        public Subscribable $subscribable,
        public HasFeature $feature,
        public string $units,
        public string $remaining,
        public string $cost = '0.00000000',
        public bool $usedOverage = false,
    ) {}
}
