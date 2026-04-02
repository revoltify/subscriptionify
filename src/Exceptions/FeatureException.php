<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Exceptions;

use RuntimeException;

final class FeatureException extends RuntimeException
{
    public static function notFound(string $slug): self
    {
        return new self(sprintf('Feature [%s] not found on the current plan.', $slug));
    }

    public static function quotaExceeded(string $slug, string $remaining, string $requested): self
    {
        return new self(sprintf('Feature [%s] quota exceeded. Remaining: %s, requested: %s.', $slug, $remaining, $requested));
    }
}
