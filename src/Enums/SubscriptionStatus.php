<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case Trialing = 'trialing';
    case PastDue = 'past_due';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function isActive(): bool
    {
        return in_array($this, [self::Active, self::Trialing], true);
    }
}
