<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Events;

use Revoltify\Subscriptionify\Models\Contracts\HasSubscription;

final readonly class SubscriptionResumed
{
    public function __construct(
        public HasSubscription $subscription,
    ) {}
}
