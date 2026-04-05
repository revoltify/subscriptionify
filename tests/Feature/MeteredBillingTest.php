<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Revoltify\Subscriptionify\Enums\FeatureType;
use Revoltify\Subscriptionify\Enums\Interval;
use Revoltify\Subscriptionify\Exceptions\FeatureException;
use Revoltify\Subscriptionify\Exceptions\InsufficientFundsException;
use Revoltify\Subscriptionify\Models\Feature;
use Revoltify\Subscriptionify\Models\Plan;
use Revoltify\Subscriptionify\Tests\Fixtures\UserWithFunds;

beforeEach(function (): void {
    Event::fake();

    $this->user = UserWithFunds::query()->create([
        'name' => 'Test',
        'email' => 'test@example.com',
        'balance' => '100.00000000',
    ]);

    $this->plan = Plan::query()->create([
        'name' => 'Pro',
        'slug' => 'pro',
        'is_free' => false,
        'billing_period' => 1,
        'billing_interval' => Interval::Month,
    ]);
});

it('allows metered consumption with sufficient funds', function (): void {
    $feature = Feature::query()->create(['name' => 'Compute', 'slug' => 'compute', 'type' => FeatureType::Metered]);
    $this->plan->features()->attach($feature, ['unit_price' => '0.01000000']);
    $this->user->subscribe($this->plan);

    expect($this->user->canConsume('compute', '100'))->toBeTrue();

    $this->user->consume('compute', '100');

    $this->user->refresh();

    expect(bccomp((string) $this->user->balance, '99.00000000', 8))->toBe(0);
});

it('denies metered consumption with insufficient funds', function (): void {
    $feature = Feature::query()->create(['name' => 'Compute', 'slug' => 'compute', 'type' => FeatureType::Metered]);
    $this->plan->features()->attach($feature, ['unit_price' => '50.00000000']);
    $this->user->subscribe($this->plan);

    expect($this->user->canConsume('compute', '3'))->toBeFalse();
});

it('throws insufficient funds on metered consume', function (): void {
    $feature = Feature::query()->create(['name' => 'Compute', 'slug' => 'compute', 'type' => FeatureType::Metered]);
    $this->plan->features()->attach($feature, ['unit_price' => '200.00000000']);
    $this->user->subscribe($this->plan);

    $this->user->consume('compute', '1');
})->throws(InsufficientFundsException::class);

it('charges overage when consumable quota exceeded with HasFunds', function (): void {
    $feature = Feature::query()->create(['name' => 'API Calls', 'slug' => 'api-calls', 'type' => FeatureType::Consumable]);
    $this->plan->features()->attach($feature, ['value' => 5, 'unit_price' => '1.00000000']);
    $this->user->subscribe($this->plan);

    // canConsume checks if funds cover overage
    expect($this->user->canConsume('api-calls', '10'))->toBeTrue();

    $this->user->consume('api-calls', '10');

    // 5 within quota (free) + 5 overage at $1 each = $5 charged
    $this->user->refresh();

    expect(bccomp((string) $this->user->balance, '95.00000000', 8))->toBe(0);
});

it('throws quota exceeded on overage when balance too low', function (): void {
    $user = UserWithFunds::query()->create([
        'name' => 'Broke',
        'email' => 'broke@example.com',
        'balance' => '1.00000000',
    ]);

    $feature = Feature::query()->create(['name' => 'API Calls', 'slug' => 'api-calls', 'type' => FeatureType::Consumable]);
    $this->plan->features()->attach($feature, ['value' => 5, 'unit_price' => '10.00000000']);
    $user->subscribe($this->plan);

    // check() detects insufficient funds for overage and throws quota exceeded
    $user->consume('api-calls', '10');
})->throws(FeatureException::class, 'quota exceeded');

it('allows metered consumption without unit price', function (): void {
    $feature = Feature::query()->create(['name' => 'Events', 'slug' => 'events', 'type' => FeatureType::Metered]);
    $this->plan->features()->attach($feature, ['unit_price' => '0.00000000']);
    $this->user->subscribe($this->plan);

    expect($this->user->canConsume('events', '1000'))->toBeTrue();

    $this->user->consume('events', '1000');

    $this->user->refresh();
    // Balance unchanged — no charge for free metered
    expect(bccomp((string) $this->user->balance, '100.00000000', 8))->toBe(0);
});

it('returns metered quota info with usage', function (): void {
    $feature = Feature::query()->create(['name' => 'Compute', 'slug' => 'compute', 'type' => FeatureType::Metered]);
    $this->plan->features()->attach($feature, ['unit_price' => '0.01000000']);
    $this->user->subscribe($this->plan);

    $this->user->consume('compute', '50');

    $info = $this->user->featureInfo('compute');

    expect($info->type)->toBe(FeatureType::Metered)
        ->and($info->used)->toBe('50')
        ->and($info->unitPrice)->toBe('0.01000000');
});

it('overage does not inflate used column', function (): void {
    $feature = Feature::query()->create(['name' => 'API Calls', 'slug' => 'api-calls', 'type' => FeatureType::Consumable]);
    $this->plan->features()->attach($feature, ['value' => 5, 'unit_price' => '1.00000000']);
    $this->user->subscribe($this->plan);

    $this->user->consume('api-calls', '10');

    $usage = $this->user->featureUsages()->where('feature_id', $feature->getKey())->first();

    // used = 5 (plan quota only), overage = 5 (fund-charged)
    expect($usage->used)->toBe('5')
        ->and($usage->overage)->toBe('5');
});

it('featureInfo reflects correct values after overage', function (): void {
    $feature = Feature::query()->create(['name' => 'API Calls', 'slug' => 'api-calls', 'type' => FeatureType::Consumable]);
    $this->plan->features()->attach($feature, ['value' => 5, 'unit_price' => '1.00000000']);
    $this->user->subscribe($this->plan);

    $this->user->consume('api-calls', '10');

    $info = $this->user->featureInfo('api-calls');

    expect($info->used)->toBe('5')
        ->and($info->overage)->toBe('5')
        ->and($info->remaining)->toBe('0')
        ->and($info->percentage)->toBe('100.00%');
});

it('period reset clears both used and overage', function (): void {
    $feature = Feature::query()->create([
        'name' => 'API Calls',
        'slug' => 'api-calls',
        'type' => FeatureType::Consumable,
        'reset_period' => 1,
        'reset_interval' => Interval::Month,
    ]);
    $this->plan->features()->attach($feature, ['value' => 5, 'unit_price' => '1.00000000']);
    $this->user->subscribe($this->plan);

    $this->user->consume('api-calls', '10');

    // Expire the usage
    $usage = $this->user->featureUsages()->where('feature_id', $feature->getKey())->first();
    $usage->update(['valid_until' => now()->subDay()]);

    // Consume again — should reset both used and overage
    $this->user->load('featureUsages');
    $this->user->consume('api-calls', '2');

    $usage->refresh();

    expect($usage->used)->toBe('2')
        ->and($usage->overage)->toBe('0');
});
