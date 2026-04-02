<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Concerns;

trait InteractsWithSubscriptions
{
    use HasSubscriptionRelationships;
    use InteractsWithFeatures;
    use ManagesSubscription;
}
