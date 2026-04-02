<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Events;

use Revoltify\Subscriptionify\Contracts\Subscribable;
use Revoltify\Subscriptionify\Models\Contracts\HasFeature;

final readonly class FeatureReleased
{
    /**
     * @param  numeric-string  $units
     * @param  numeric-string  $remaining
     */
    public function __construct(
        public Subscribable $subscribable,
        public HasFeature $feature,
        public string $units,
        public string $remaining,
    ) {}
}
