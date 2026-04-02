<?php

declare(strict_types=1);

use Revoltify\Subscriptionify\Enums\FeatureType;
use Revoltify\Subscriptionify\Enums\Interval;
use Revoltify\Subscriptionify\Enums\SubscriptionStatus;
use Revoltify\Subscriptionify\Models\Feature;
use Revoltify\Subscriptionify\Models\Plan;

it('creates a plan with all attributes', function (): void {
    $plan = Plan::query()->create([
        'name' => 'Pro',
        'slug' => 'pro',
        'description' => 'Professional plan',
        'is_free' => false,
        'is_active' => true,
        'trial_days' => 14,
        'billing_period' => 1,
        'billing_interval' => Interval::Month,
        'grace_days' => 3,
        'sort_order' => 1,
    ]);

    expect($plan->getName())->toBe('Pro')
        ->and($plan->getSlug())->toBe('pro')
        ->and($plan->isFree())->toBeFalse()
        ->and($plan->isActive())->toBeTrue()
        ->and($plan->getTrialDays())->toBe(14)
        ->and($plan->hasTrialDays())->toBeTrue()
        ->and($plan->getBillingPeriod())->toBe(1)
        ->and($plan->getBillingInterval())->toBe(Interval::Month)
        ->and($plan->getGraceDays())->toBe(3);
});

it('returns null ends_at for free plans', function (): void {
    $plan = Plan::query()->create([
        'name' => 'Free',
        'slug' => 'free',
        'is_free' => true,
    ]);

    expect($plan->calculateEndsAt(now()))->toBeNull();
});

it('calculates ends_at for paid plans', function (): void {
    $plan = Plan::query()->create([
        'name' => 'Monthly',
        'slug' => 'monthly',
        'is_free' => false,
        'billing_period' => 1,
        'billing_interval' => Interval::Month,
    ]);

    $endsAt = $plan->calculateEndsAt(now());

    expect($endsAt)->not->toBeNull()
        ->and($endsAt->toDateString())->toBe(now()->addMonth()->toDateString());
});

it('prevents deleting a plan with active subscriptions', function (): void {
    $plan = Plan::query()->create([
        'name' => 'Pro',
        'slug' => 'pro',
        'is_free' => false,
        'billing_period' => 1,
        'billing_interval' => Interval::Month,
    ]);

    // Create a subscription directly
    $plan->subscriptions()->create([
        'subscribable_type' => 'user',
        'subscribable_id' => 1,
        'status' => SubscriptionStatus::Active,
        'starts_at' => now(),
    ]);

    $plan->delete();
})->throws(LogicException::class, 'active subscriptions');

it('can delete a plan without active subscriptions', function (): void {
    $plan = Plan::query()->create([
        'name' => 'Old Plan',
        'slug' => 'old-plan',
        'is_free' => false,
        'billing_period' => 1,
        'billing_interval' => Interval::Month,
    ]);

    $feature = Feature::query()->create(['name' => 'Test', 'slug' => 'test', 'type' => FeatureType::Toggle]);
    $plan->features()->attach($feature);

    expect($plan->features)->toHaveCount(1);

    $plan->delete();

    expect(Plan::query()->find($plan->id))->toBeNull();
});

it('creates a feature with correct type', function (): void {
    $feature = Feature::query()->create([
        'name' => 'Custom Branding',
        'slug' => 'branding',
        'type' => FeatureType::Toggle,
    ]);

    expect($feature->getName())->toBe('Custom Branding')
        ->and($feature->getSlug())->toBe('branding')
        ->and($feature->getType())->toBe(FeatureType::Toggle)
        ->and($feature->isToggle())->toBeTrue()
        ->and($feature->isConsumable())->toBeFalse()
        ->and($feature->isLimit())->toBeFalse()
        ->and($feature->isMetered())->toBeFalse();
});

it('checks hasQuota on feature model', function (): void {
    $toggle = Feature::query()->create(['name' => 'A', 'slug' => 'a', 'type' => FeatureType::Toggle]);
    $consumable = Feature::query()->create(['name' => 'B', 'slug' => 'b', 'type' => FeatureType::Consumable]);
    $limit = Feature::query()->create(['name' => 'C', 'slug' => 'c', 'type' => FeatureType::Limit]);
    $metered = Feature::query()->create(['name' => 'D', 'slug' => 'd', 'type' => FeatureType::Metered]);

    expect($toggle->hasQuota())->toBeFalse()
        ->and($consumable->hasQuota())->toBeTrue()
        ->and($limit->hasQuota())->toBeTrue()
        ->and($metered->hasQuota())->toBeFalse();
});

it('returns null pivot without relationship context', function (): void {
    $feature = Feature::query()->create(['name' => 'Test', 'slug' => 'test', 'type' => FeatureType::Consumable]);

    expect($feature->pivot)->toBeNull();
});

it('returns value and unit price from pivot', function (): void {
    $plan = Plan::query()->create([
        'name' => 'Pro',
        'slug' => 'pro',
        'is_free' => false,
        'billing_period' => 1,
        'billing_interval' => Interval::Month,
    ]);

    $feature = Feature::query()->create(['name' => 'API', 'slug' => 'api', 'type' => FeatureType::Consumable]);
    $plan->features()->attach($feature, ['value' => 1000, 'unit_price' => '0.01000000']);

    $loadedFeature = $plan->features()->first();

    expect($loadedFeature->pivot->getValue())->toBe('1000')
        ->and($loadedFeature->pivot->getUnitPrice())->toBe('0.01000000');
});

it('cleans up related data on feature deletion', function (): void {
    $plan = Plan::query()->create([
        'name' => 'Pro',
        'slug' => 'pro',
        'is_free' => false,
        'billing_period' => 1,
        'billing_interval' => Interval::Month,
    ]);

    $feature = Feature::query()->create(['name' => 'Test', 'slug' => 'test', 'type' => FeatureType::Toggle]);
    $plan->features()->attach($feature);

    $feature->delete();

    expect($plan->features()->count())->toBe(0);
});
