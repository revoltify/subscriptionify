<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\DTOs;

use Revoltify\Subscriptionify\Enums\FeatureType;
use Revoltify\Subscriptionify\Enums\Interval;

final readonly class FeatureInfo
{
    /**
     * @param  numeric-string  $limit
     * @param  numeric-string  $used
     * @param  numeric-string  $remaining
     * @param  numeric-string  $overage
     */
    public function __construct(
        public string $name,
        public string $slug,
        public FeatureType $type,
        public string $limit = '0',
        public string $used = '0',
        public string $remaining = '0',
        public string $overage = '0',
        public bool $unlimited = false,
        public bool $applicable = true,
        public string $percentage = '0%',
        public ?string $validUntil = null,
        public bool $overageAvailable = false,
        public ?string $unitPrice = null,
        public ?int $resetPeriod = null,
        public ?Interval $resetInterval = null,
    ) {}

    public static function toggle(
        string $name,
        string $slug
    ): self {
        return new self(
            name: $name,
            slug: $slug,
            type: FeatureType::Toggle,
            applicable: false,
        );
    }

    /**
     * @param  numeric-string  $used
     * @param  numeric-string  $overage
     */
    public static function metered(
        string $name,
        string $slug,
        string $used = '0',
        string $overage = '0',
        ?string $unitPrice = null,
        ?string $validUntil = null,
        ?int $resetPeriod = null,
        ?Interval $resetInterval = null
    ): self {
        return new self(
            name: $name,
            slug: $slug,
            type: FeatureType::Metered,
            used: $used,
            overage: $overage,
            unlimited: true,
            validUntil: $validUntil,
            unitPrice: $unitPrice,
            resetPeriod: $resetPeriod,
            resetInterval: $resetInterval,
        );
    }

    public static function unlimited(
        string $name,
        string $slug,
        FeatureType $type,
        ?string $validUntil = null,
        ?int $resetPeriod = null,
        ?Interval $resetInterval = null
    ): self {
        return new self(
            name: $name,
            slug: $slug,
            type: $type,
            unlimited: true,
            validUntil: $validUntil,
            resetPeriod: $resetPeriod,
            resetInterval: $resetInterval,
        );
    }
}
