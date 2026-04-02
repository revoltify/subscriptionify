<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify;

use Closure;
use Revoltify\Subscriptionify\Contracts\Subscribable;

final class Subscriptionify
{
    private static ?Closure $subscribableResolver = null;

    public static function resolveSubscribableUsing(Closure $resolver): void
    {
        self::$subscribableResolver = $resolver;
    }

    public static function resolveSubscribable(): ?Subscribable
    {
        if (self::$subscribableResolver instanceof Closure) {
            $resolver = (self::$subscribableResolver)();

            return $resolver instanceof Subscribable ? $resolver : null;
        }

        $user = auth()->user();

        return $user instanceof Subscribable ? $user : null;
    }

    public static function flush(): void
    {
        self::$subscribableResolver = null;
    }
}
