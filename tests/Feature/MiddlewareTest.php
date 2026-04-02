<?php

declare(strict_types=1);

use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Revoltify\Subscriptionify\Enums\FeatureType;
use Revoltify\Subscriptionify\Enums\Interval;
use Revoltify\Subscriptionify\Http\Middleware\EnsureFeature;
use Revoltify\Subscriptionify\Http\Middleware\EnsurePlan;
use Revoltify\Subscriptionify\Http\Middleware\EnsureSubscribed;
use Revoltify\Subscriptionify\Models\Feature;
use Revoltify\Subscriptionify\Models\Plan;
use Revoltify\Subscriptionify\Subscriptionify;
use Revoltify\Subscriptionify\Tests\Fixtures\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    Event::fake();

    $this->plan = Plan::query()->create([
        'name' => 'Pro',
        'slug' => 'pro',
        'is_free' => false,
        'billing_period' => 1,
        'billing_interval' => Interval::Month,
    ]);
});

it('allows subscribed users through', function (): void {
    $user = User::query()->create(['name' => 'Test', 'email' => 'test@example.com']);
    $user->subscribe($this->plan);

    Subscriptionify::resolveSubscribableUsing(fn () => $user);

    $middleware = new EnsureSubscribed;
    $response = $middleware->handle(Request::create('/test'), fn (): ResponseFactory|Response => response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('rejects unsubscribed users', function (): void {
    $user = User::query()->create(['name' => 'Test', 'email' => 'test@example.com']);

    Subscriptionify::resolveSubscribableUsing(fn () => $user);

    $middleware = new EnsureSubscribed;
    $middleware->handle(Request::create('/test'), fn (): ResponseFactory|Response => response('ok'));
})->throws(HttpException::class);

it('rejects when no subscribable resolved', function (): void {
    Subscriptionify::resolveSubscribableUsing(fn (): null => null);

    $middleware = new EnsureSubscribed;
    $middleware->handle(Request::create('/test'), fn (): ResponseFactory|Response => response('ok'));
})->throws(HttpException::class);

it('allows users on the correct plan', function (): void {
    $user = User::query()->create(['name' => 'Test', 'email' => 'test@example.com']);
    $user->subscribe($this->plan);

    Subscriptionify::resolveSubscribableUsing(fn () => $user);

    $middleware = new EnsurePlan;
    $response = $middleware->handle(Request::create('/test'), fn (): ResponseFactory|Response => response('ok'), 'pro');

    expect($response->getContent())->toBe('ok');
});

it('rejects users on wrong plan', function (): void {
    $user = User::query()->create(['name' => 'Test', 'email' => 'test@example.com']);
    $user->subscribe($this->plan);

    Subscriptionify::resolveSubscribableUsing(fn () => $user);

    $middleware = new EnsurePlan;
    $middleware->handle(Request::create('/test'), fn (): ResponseFactory|Response => response('ok'), 'enterprise');
})->throws(HttpException::class);

it('allows users with the required feature', function (): void {
    $user = User::query()->create(['name' => 'Test', 'email' => 'test@example.com']);
    $feature = Feature::query()->create(['name' => 'Branding', 'slug' => 'branding', 'type' => FeatureType::Toggle]);
    $this->plan->features()->attach($feature);
    $user->subscribe($this->plan);

    Subscriptionify::resolveSubscribableUsing(fn () => $user);

    $middleware = new EnsureFeature;
    $response = $middleware->handle(Request::create('/test'), fn (): ResponseFactory|Response => response('ok'), 'branding');

    expect($response->getContent())->toBe('ok');
});

it('rejects users without the required feature', function (): void {
    $user = User::query()->create(['name' => 'Test', 'email' => 'test@example.com']);
    $user->subscribe($this->plan);

    Subscriptionify::resolveSubscribableUsing(fn () => $user);

    $middleware = new EnsureFeature;
    $middleware->handle(Request::create('/test'), fn (): ResponseFactory|Response => response('ok'), 'branding');
})->throws(HttpException::class);

afterEach(function (): void {
    Subscriptionify::flush();
});
