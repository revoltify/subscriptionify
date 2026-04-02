<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Support;

use Illuminate\Support\Facades\Blade;
use Revoltify\Subscriptionify\Contracts\Subscribable;
use Revoltify\Subscriptionify\Subscriptionify;

final class BladeDirectives
{
    private static ?Subscribable $subscribable = null;

    private static bool $resolved = false;

    public static function register(): void
    {
        Blade::if('subscribed', self::subscribed(...));
        Blade::if('plan', self::plan(...));
        Blade::if('feature', self::feature(...));
        Blade::if('onTrial', self::onTrial(...));
        Blade::if('onFreePlan', self::onFreePlan(...));
        Blade::if('onGracePeriod', self::onGracePeriod(...));
    }

    public static function subscribed(): bool
    {
        return self::subscribable()?->subscribed() === true;
    }

    public static function plan(string $slug): bool
    {
        return self::subscribable()
            ?->subscription()
            ?->hasPlan($slug) === true;
    }

    public static function feature(string $slug): bool
    {
        return self::subscribable()?->hasFeature($slug) === true;
    }

    public static function onTrial(): bool
    {
        return self::subscribable()?->onTrial() === true;
    }

    public static function onFreePlan(): bool
    {
        return self::subscribable()?->onFreePlan() === true;
    }

    public static function onGracePeriod(): bool
    {
        return self::subscribable()
            ?->subscription()
            ?->onGracePeriod() === true;
    }

    private static function subscribable(): ?Subscribable
    {
        if (! self::$resolved) {
            self::$subscribable = Subscriptionify::resolveSubscribable();
            self::$resolved = true;
        }

        return self::$subscribable;
    }
}
