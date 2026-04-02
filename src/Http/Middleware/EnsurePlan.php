<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Revoltify\Subscriptionify\Contracts\Subscribable;
use Revoltify\Subscriptionify\Models\Contracts\HasSubscription;
use Revoltify\Subscriptionify\Subscriptionify;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class EnsurePlan
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next, string $planSlug): Response
    {
        $subscribable = Subscriptionify::resolveSubscribable();

        throw_if(! $subscribable instanceof Subscribable, HttpException::class, 403, sprintf('Plan [%s] required.', $planSlug));

        $subscription = $subscribable->subscription();

        throw_if(! $subscription instanceof HasSubscription || $subscription->getPlan()->getSlug() !== $planSlug, HttpException::class, 403, sprintf('Plan [%s] required.', $planSlug));

        return $next($request);
    }
}
