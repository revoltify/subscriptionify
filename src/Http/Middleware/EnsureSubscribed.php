<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Revoltify\Subscriptionify\Contracts\Subscribable;
use Revoltify\Subscriptionify\Subscriptionify;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class EnsureSubscribed
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $subscribable = Subscriptionify::resolveSubscribable();

        throw_if(! $subscribable instanceof Subscribable || ! $subscribable->subscribed(), HttpException::class, 403, 'Active subscription required.');

        return $next($request);
    }
}
