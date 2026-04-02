<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Events;

use Revoltify\Subscriptionify\Models\Contracts\HasPlan;
use Revoltify\Subscriptionify\Models\Contracts\HasSubscription;

final readonly class SubscriptionPlanChanged
{
    public function __construct(
        public HasSubscription $subscription,
        public HasPlan $oldPlan,
        public HasPlan $newPlan,
    ) {}
}
