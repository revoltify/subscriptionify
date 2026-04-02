<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Contracts;

interface HasFunds
{
    /** @return numeric-string */
    public function getBalance(): string;

    public function hasSufficientFunds(string $amount): bool;

    public function deductFunds(string $amount, string $description): void;
}
