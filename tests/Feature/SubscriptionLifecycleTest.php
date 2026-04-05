<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Revoltify\Subscriptionify\Enums\Interval;
use Revoltify\Subscriptionify\Enums\SubscriptionStatus;
use Revoltify\Subscriptionify\Events\SubscriptionCancelled;
use Revoltify\Subscriptionify\Events\SubscriptionCreated;
use Revoltify\Subscriptionify\Events\SubscriptionExpired;
use Revoltify\Subscriptionify\Events\SubscriptionMarkedPastDue;
use Revoltify\Subscriptionify\Events\SubscriptionPlanChanged;
use Revoltify\Subscriptionify\Events\SubscriptionRenewed;
use Revoltify\Subscriptionify\Events\SubscriptionResumed;
use Revoltify\Subscriptionify\Exceptions\SubscriptionException;
use Revoltify\Subscriptionify\Models\Plan;
use Revoltify\Subscriptionify\Tests\Fixtures\User;

beforeEach(function (): void {
    Event::fake();

    $this->user = User::query()->create(['name' => 'Test', 'email' => 'test@example.com']);

    $this->plan = Plan::query()->create([
        'name' => 'Pro',
        'slug' => 'pro',
        'is_free' => false,
        'billing_period' => 1,
        'billing_interval' => Interval::Month,
        'trial_days' => 0,
        'grace_days' => 3,
    ]);
});

it('can create a new subscription', function (): void {
    $subscription = $this->user->subscribe($this->plan);

    expect($subscription->getStatus())->toBe(SubscriptionStatus::Active)
        ->and($this->user->subscribed())->toBeTrue();

    Event::assertDispatched(SubscriptionCreated::class);
});

it('creates subscription with trial when plan has trial days', function (): void {
    $trialPlan = Plan::query()->create([
        'name' => 'Trial Plan',
        'slug' => 'trial-plan',
        'is_free' => false,
        'billing_period' => 1,
        'billing_interval' => Interval::Month,
        'trial_days' => 14,
    ]);

    $subscription = $this->user->subscribe($trialPlan);

    expect($subscription->getStatus())->toBe(SubscriptionStatus::Trialing)
        ->and($subscription->onTrial())->toBeTrue()
        ->and($subscription->trialDaysRemaining())->toBe(14)
        ->and($this->user->onTrial())->toBeTrue();
});

it('creates free subscription with null ends_at', function (): void {
    $freePlan = Plan::query()->create([
        'name' => 'Free',
        'slug' => 'free',
        'is_free' => true,
    ]);

    $subscription = $this->user->subscribe($freePlan);

    expect($subscription->getEndsAt())->toBeNull()
        ->and($subscription->active())->toBeTrue();
});

it('creates subscription with custom ends_at', function (): void {
    $endsAt = now()->addMonths(6);

    $subscription = $this->user->subscribe($this->plan, endsAt: $endsAt);

    expect($subscription->getEndsAt()->toDateString())->toBe($endsAt->toDateString());
});

it('cannot create subscription when already subscribed', function (): void {
    $this->user->subscribe($this->plan);

    $this->user->subscribe($this->plan);
})->throws(SubscriptionException::class, 'already has an active subscription');

it('checks subscribed status', function (): void {
    expect($this->user->subscribed())->toBeFalse();

    $this->user->subscribe($this->plan);

    expect($this->user->subscribed())->toBeTrue();
});

it('checks on plan', function (): void {
    $this->user->subscribe($this->plan);

    $otherPlan = Plan::query()->create([
        'name' => 'Basic',
        'slug' => 'basic',
        'is_free' => false,
        'billing_period' => 1,
        'billing_interval' => Interval::Month,
    ]);

    expect($this->user->onPlan($this->plan))->toBeTrue()
        ->and($this->user->onPlan($otherPlan))->toBeFalse();
});

it('checks on free plan', function (): void {
    $freePlan = Plan::query()->create([
        'name' => 'Free',
        'slug' => 'free',
        'is_free' => true,
    ]);

    $this->user->subscribe($freePlan);

    expect($this->user->onFreePlan())->toBeTrue();
});

it('checks valid status includes active, trial, and grace period', function (): void {
    $subscription = $this->user->subscribe($this->plan);

    expect($subscription->valid())->toBeTrue();

    // Cancel but still on grace period
    $subscription->cancel();

    expect($subscription->valid())->toBeTrue()
        ->and($subscription->onGracePeriod())->toBeTrue();
});

it('returns days remaining', function (): void {
    $subscription = $this->user->subscribe($this->plan);

    expect($subscription->daysRemaining())->toBeGreaterThan(0);
});

it('can renew a subscription', function (): void {
    $subscription = $this->user->subscribe($this->plan);

    $subscription->renew();

    expect($subscription->getStatus())->toBe(SubscriptionStatus::Active)
        ->and($subscription->renewed_at)->not->toBeNull();

    Event::assertDispatched(SubscriptionRenewed::class);
});

it('renews with custom ends_at', function (): void {
    $subscription = $this->user->subscribe($this->plan);
    $customEnd = now()->addYear();

    $subscription->renew(endsAt: $customEnd);

    expect($subscription->getEndsAt()->toDateString())->toBe($customEnd->toDateString());
});

it('can change to another plan', function (): void {
    $subscription = $this->user->subscribe($this->plan);

    $newPlan = Plan::query()->create([
        'name' => 'Enterprise',
        'slug' => 'enterprise',
        'is_free' => false,
        'billing_period' => 1,
        'billing_interval' => Interval::Year,
    ]);

    $subscription->changePlan($newPlan);

    expect($subscription->getPlan()->getSlug())->toBe('enterprise')
        ->and($this->user->clearSubscriptionCache()->onPlan($newPlan))->toBeTrue();

    Event::assertDispatched(SubscriptionPlanChanged::class);
});

it('can change plan and check canChangePlan', function (): void {
    $this->user->subscribe($this->plan);

    $newPlan = Plan::query()->create([
        'name' => 'Enterprise',
        'slug' => 'enterprise',
        'billing_period' => 1,
        'billing_interval' => Interval::Month,
    ]);

    expect($this->user->canChangePlan($newPlan))->toBeTrue()
        ->and($this->user->canChangePlan($this->plan))->toBeFalse();
});

it('can change plan with optional usage reset', function (): void {
    $subscription = $this->user->subscribe($this->plan);

    $newPlan = Plan::query()->create([
        'name' => 'Enterprise',
        'slug' => 'enterprise',
        'is_free' => false,
        'billing_period' => 1,
        'billing_interval' => Interval::Year,
    ]);

    $subscription->changePlan($newPlan, resetUsages: true);

    expect($subscription->getPlan()->getSlug())->toBe('enterprise');
});

it('can cancel a subscription', function (): void {
    $subscription = $this->user->subscribe($this->plan);
    $subscription->cancel();

    expect($subscription->canceled())->toBeTrue()
        ->and($subscription->getStatus())->toBe(SubscriptionStatus::Cancelled);

    Event::assertDispatched(SubscriptionCancelled::class);
});

it('can cancel immediately', function (): void {
    // Use a plan without grace days so ended() returns true
    $noGracePlan = Plan::query()->create([
        'name' => 'No Grace',
        'slug' => 'no-grace',
        'is_free' => false,
        'billing_period' => 1,
        'billing_interval' => Interval::Month,
        'grace_days' => 0,
    ]);

    $subscription = $this->user->subscribe($noGracePlan);
    $subscription->cancelNow();

    expect($subscription->canceled())->toBeTrue()
        ->and($subscription->ended())->toBeTrue();
});

it('shows grace period after cancel', function (): void {
    $subscription = $this->user->subscribe($this->plan);
    $subscription->cancel();

    expect($subscription->onGracePeriod())->toBeTrue()
        ->and($subscription->valid())->toBeTrue()
        ->and($subscription->ended())->toBeFalse();
});

it('can resume a cancelled subscription', function (): void {
    $subscription = $this->user->subscribe($this->plan);
    $subscription->cancel();
    $subscription->resume();

    expect($subscription->getStatus())->toBe(SubscriptionStatus::Active)
        ->and($subscription->canceled())->toBeFalse();

    Event::assertDispatched(SubscriptionResumed::class);
});

it('cannot resume a non-cancelled subscription', function (): void {
    $subscription = $this->user->subscribe($this->plan);

    $subscription->resume();
})->throws(SubscriptionException::class, 'cannot be resumed');

it('cannot resume an ended subscription', function (): void {
    $noGracePlan = Plan::query()->create([
        'name' => 'No Grace 2',
        'slug' => 'no-grace-2',
        'is_free' => false,
        'billing_period' => 1,
        'billing_interval' => Interval::Month,
        'grace_days' => 0,
    ]);

    $subscription = $this->user->subscribe($noGracePlan);
    $subscription->cancelNow();

    $subscription->resume();
})->throws(SubscriptionException::class, 'cannot be resumed');

it('can expire a subscription', function (): void {
    $subscription = $this->user->subscribe($this->plan);
    $subscription->expire();

    expect($subscription->getStatus())->toBe(SubscriptionStatus::Expired);

    Event::assertDispatched(SubscriptionExpired::class);
});

it('reports expired when status is Expired', function (): void {
    $subscription = $this->user->subscribe($this->plan);
    $subscription->expire();

    expect($subscription->expired())->toBeTrue();
});

it('reports expired when active with past ends_at', function (): void {
    $subscription = $this->user->subscribe($this->plan);

    $subscription->update(['ends_at' => now()->subDay()]);

    expect($subscription->getStatus())->toBe(SubscriptionStatus::Active)
        ->and($subscription->expired())->toBeTrue();
});

it('does not report expired when active with future ends_at', function (): void {
    $subscription = $this->user->subscribe($this->plan);

    expect($subscription->expired())->toBeFalse();
});

it('does not report expired for free plan with null ends_at', function (): void {
    $freePlan = Plan::query()->create([
        'name' => 'Free',
        'slug' => 'free-expired-test',
        'is_free' => true,
    ]);

    $subscription = $this->user->subscribe($freePlan);

    expect($subscription->expired())->toBeFalse();
});

it('expires overdue subscriptions via artisan command', function (): void {
    $subscription = $this->user->subscribe($this->plan);

    // Make the subscription overdue
    $subscription->update(['ends_at' => now()->subDay()]);

    $this->artisan('subscriptionify:expire-overdue')
        ->expectsOutputToContain('Expired 1 overdue subscription(s)')
        ->assertSuccessful();

    $subscription->refresh();

    expect($subscription->getStatus())->toBe(SubscriptionStatus::Expired)
        ->and($subscription->expired())->toBeTrue();

    Event::assertDispatched(SubscriptionExpired::class);
});

it('does not expire subscriptions with future ends_at via artisan command', function (): void {
    $this->user->subscribe($this->plan);

    $this->artisan('subscriptionify:expire-overdue')
        ->expectsOutputToContain('Expired 0 overdue subscription(s)')
        ->assertSuccessful();
});

it('can mark subscription as past due', function (): void {
    $subscription = $this->user->subscribe($this->plan);
    $subscription->markPastDue();

    expect($subscription->pastDue())->toBeTrue()
        ->and($subscription->getStatus())->toBe(SubscriptionStatus::PastDue);

    Event::assertDispatched(SubscriptionMarkedPastDue::class);
});

it('returns subscription info DTO', function (): void {
    $this->user->subscribe($this->plan);

    $info = $this->user->subscriptionInfo();

    expect($info->planName)->toBe('Pro')
        ->and($info->planSlug)->toBe('pro')
        ->and($info->isFree)->toBeFalse()
        ->and($info->status)->toBe(SubscriptionStatus::Active)
        ->and($info->isActive())->toBeTrue();
});

it('returns empty subscription info when not subscribed', function (): void {
    $info = $this->user->subscriptionInfo();

    expect($info->planName)->toBe('')
        ->and($info->isActive())->toBeFalse();
});
