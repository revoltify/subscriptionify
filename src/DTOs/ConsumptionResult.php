<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\DTOs;

final readonly class ConsumptionResult
{
    /**
     * @param  numeric-string  $remaining
     * @param  numeric-string  $cost
     */
    public function __construct(
        public string $remaining,
        public string $cost = '0.00000000',
        public bool $usedOverage = false,
    ) {}

    /** @param numeric-string $remaining */
    public static function free(string $remaining): self
    {
        return new self(remaining: $remaining);
    }

    /**
     * @param  numeric-string  $remaining
     * @param  numeric-string  $cost
     */
    public static function withOverage(string $remaining, string $cost): self
    {
        return new self(remaining: $remaining, cost: $cost, usedOverage: true);
    }
}
