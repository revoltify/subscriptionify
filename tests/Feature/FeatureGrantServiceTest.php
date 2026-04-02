<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Revoltify\Subscriptionify\Enums\FeatureType;
use Revoltify\Subscriptionify\Enums\Interval;
use Revoltify\Subscriptionify\Models\Feature;
use Revoltify\Subscriptionify\Models\Plan;
use Revoltify\Subscriptionify\Services\FeatureService;
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
    ]);
});

it('can grant a direct feature to a subscribable', function (): void {
    $feature = Feature::query()->create(['name' => 'API Calls', 'slug' => 'api-calls', 'type' => FeatureType::Consumable]);
    $this->user->subscribe($this->plan);

    $this->user->grantFeature('api-calls', value: 5000, unitPrice: '0.001');

    $this->user->load('directFeatures');
    expect($this->user->directFeatures)->toHaveCount(1)
        ->and($this->user->hasFeature('api-calls'))->toBeTrue();
});

it('direct grant adds to plan quota', function (): void {
    $feature = Feature::query()->create(['name' => 'API Calls', 'slug' => 'api-calls', 'type' => FeatureType::Consumable]);
    $this->plan->features()->attach($feature, ['value' => 1000]);
    $this->user->subscribe($this->plan);

    $this->user->grantFeature('api-calls', value: 500);

    expect($this->user->remainingUsage('api-calls'))->toBe('1500');
});

it('direct grant of 0 makes feature unlimited', function (): void {
    $feature = Feature::query()->create(['name' => 'API Calls', 'slug' => 'api-calls', 'type' => FeatureType::Consumable]);
    $this->plan->features()->attach($feature, ['value' => 100]);
    $this->user->subscribe($this->plan);

    $this->user->grantFeature('api-calls', value: 0);

    expect($this->user->remainingUsage('api-calls'))->toBe(FeatureService::UNLIMITED)
        ->and($this->user->isUnlimitedUsage('api-calls'))->toBeTrue();
});

it('can revoke a direct feature', function (): void {
    $feature = Feature::query()->create(['name' => 'API Calls', 'slug' => 'api-calls', 'type' => FeatureType::Consumable]);
    $this->plan->features()->attach($feature, ['value' => 1000]);
    $this->user->subscribe($this->plan);

    $this->user->grantFeature('api-calls', value: 500);

    expect($this->user->remainingUsage('api-calls'))->toBe('1500');

    $this->user->revokeFeature('api-calls');

    // After revoke, reload direct features and check only plan quota remains
    $this->user->load('directFeatures');

    expect($this->user->remainingUsage('api-calls'))->toBe('1000');
});

it('grants feature without plan subscription (direct only)', function (): void {
    $feature = Feature::query()->create(['name' => 'Custom Feature', 'slug' => 'custom', 'type' => FeatureType::Toggle]);

    $this->user->grantFeature('custom');

    expect($this->user->hasFeature('custom'))->toBeTrue();
});

it('can grant feature with reset interval', function (): void {
    Feature::query()->create(['name' => 'API Calls', 'slug' => 'api-calls', 'type' => FeatureType::Consumable]);
    $this->user->subscribe($this->plan);

    $this->user->grantFeature(
        'api-calls',
        value: 1000,
        resetPeriod: 1,
        resetInterval: Interval::Month,
    );

    $this->user->load('directFeatures');

    expect($this->user->directFeatures)->toHaveCount(1);
});
