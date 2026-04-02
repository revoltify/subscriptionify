<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Exceptions;

use RuntimeException;

final class SubscriptionException extends RuntimeException
{
    public static function alreadySubscribed(): self
    {
        return new self('This model already has an active subscription.');
    }

    public static function notFound(): self
    {
        return new self('No active subscription found.');
    }

    public static function cannotResume(): self
    {
        return new self('This subscription cannot be resumed. It may not be cancelled or may have already ended.');
    }

    public static function cannotChangePlan(string $reason): self
    {
        return new self(sprintf('Cannot change plan: %s', $reason));
    }
}
