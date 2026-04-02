<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Revoltify\Subscriptionify\Contracts\Subscribable;
use Revoltify\Subscriptionify\Subscriptionify;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class EnsureFeature
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next, string $featureSlug): Response
    {
        $subscribable = Subscriptionify::resolveSubscribable();

        throw_if(! $subscribable instanceof Subscribable || ! $subscribable->hasFeature($featureSlug), HttpException::class, 403, sprintf('Feature [%s] required.', $featureSlug));

        return $next($request);
    }
}
