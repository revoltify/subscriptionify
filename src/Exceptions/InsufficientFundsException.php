<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Exceptions;

use RuntimeException;

final class InsufficientFundsException extends RuntimeException
{
    public static function forAmount(string $required): self
    {
        return new self(sprintf('Insufficient funds. Required: %s.', $required));
    }
}
