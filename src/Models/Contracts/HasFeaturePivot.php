<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Models\Contracts;

use DateTimeInterface;
use Revoltify\Subscriptionify\Enums\Interval;

interface HasFeaturePivot
{
    /** @return numeric-string */
    public function getValue(): string;

    /** @return numeric-string */
    public function getUnitPrice(): string;

    public function getResetDate(): ?DateTimeInterface;

    public function getResetPeriod(): ?int;

    public function getResetInterval(): ?Interval;
}
