<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Events;

use Revoltify\Subscriptionify\Models\Contracts\HasSubscription;

final readonly class SubscriptionExpiring
{
    public function __construct(
        public HasSubscription $subscription,
        public int $daysRemaining,
    ) {}
}
