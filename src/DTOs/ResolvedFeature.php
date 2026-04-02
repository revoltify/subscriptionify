<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\DTOs;

use DateTimeInterface;
use Revoltify\Subscriptionify\Enums\FeatureType;
use Revoltify\Subscriptionify\Enums\Interval;
use Revoltify\Subscriptionify\Models\Contracts\HasFeature;

final readonly class ResolvedFeature
{
    /**
     * @param  numeric-string  $limit
     * @param  numeric-string  $unitPrice
     */
    public function __construct(
        public HasFeature $feature,
        public string $limit,
        public string $unitPrice,
        public ?DateTimeInterface $validUntil,
        public ?int $resetPeriod = null,
        public ?Interval $resetInterval = null,
    ) {}

    public function name(): string
    {
        return $this->feature->getName();
    }

    public function slug(): string
    {
        return $this->feature->getSlug();
    }

    public function type(): FeatureType
    {
        return $this->feature->getType();
    }

    public function isUnlimited(): bool
    {
        return bccomp($this->limit, '0') === 0;
    }

    public function hasQuota(): bool
    {
        return $this->feature->hasQuota();
    }

    public function hasUnitPrice(): bool
    {
        return bccomp($this->unitPrice, '0', 8) > 0;
    }
}
