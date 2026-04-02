<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Enums;

enum FeatureType: string
{
    case Toggle = 'toggle';
    case Consumable = 'consumable';
    case Limit = 'limit';
    case Metered = 'metered';

    public function hasQuota(): bool
    {
        return in_array($this, [self::Consumable, self::Limit], true);
    }
}
