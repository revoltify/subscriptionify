<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Events;

use Revoltify\Subscriptionify\Models\Contracts\HasSubscription;

final readonly class SubscriptionExpired
{
    public function __construct(
        public HasSubscription $subscription,
    ) {}
}
