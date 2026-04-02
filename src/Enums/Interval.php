<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Enums;

use Carbon\CarbonInterface;

enum Interval: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';

    public function addToDate(CarbonInterface $date, int $period): CarbonInterface
    {
        return $date->copy()->add($this->value, $period);
    }
}
