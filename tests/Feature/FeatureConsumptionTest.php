<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Revoltify\Subscriptionify\Enums\FeatureType;
use Revoltify\Subscriptionify\Enums\Interval;
use Revoltify\Subscriptionify\Events\FeatureConsumed;
use Revoltify\Subscriptionify\Events\FeatureReleased;
use Revoltify\Subscriptionify\Exceptions\FeatureException;
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

it('grants access to toggle features', function (): void {
    $feature = Feature::query()->create(['name' => 'Custom Branding', 'slug' => 'branding', 'type' => FeatureType::Toggle]);
    $this->plan->features()->attach($feature);
    $this->user->subscribe($this->plan);

    expect($this->user->hasFeature('branding'))->toBeTrue()
        ->and($this->user->canConsume('branding'))->toBeTrue();
});

it('denies access to toggle features without subscription', function (): void {
    Feature::query()->create(['name' => 'Custom Branding', 'slug' => 'branding', 'type' => FeatureType::Toggle]);

    expect($this->user->hasFeature('branding'))->toBeFalse();
});

it('can consume toggle features (no-op)', function (): void {
    $feature = Feature::query()->create(['name' => 'Custom Branding', 'slug' => 'branding', 'type' => FeatureType::Toggle]);
    $this->plan->features()->attach($feature);
    $this->user->subscribe($this->plan);

    $this->user->consume('branding');

    Event::assertDispatched(FeatureConsumed::class, fn (FeatureConsumed $event): bool => $event->units === '1' && $event->cost === '0.00000000');
});

it('can consume consumable features within quota', function (): void {
    $feature = Feature::query()->create(['name' => 'API Calls', 'slug' => 'api-calls', 'type' => FeatureType::Consumable]);
    $this->plan->features()->attach($feature, ['value' => 100]);
    $this->user->subscribe($this->plan);

    expect($this->user->canConsume('api-calls', '50'))->toBeTrue();

    $this->user->consume('api-calls', '50');

    expect($this->user->remainingUsage('api-calls'))->toBe('50');

    Event::assertDispatched(FeatureConsumed::class);
});

it('tracks cumulative consumable usage', function (): void {
    $feature = Feature::query()->create(['name' => 'API Calls', 'slug' => 'api-calls', 'type' => FeatureType::Consumable]);
    $this->plan->features()->attach($feature, ['value' => 100]);
    $this->user->subscribe($this->plan);

    $this->user->consume('api-calls', '30');
    $this->user->consume('api-calls', '20');

    expect($this->user->remainingUsage('api-calls'))->toBe('50');
});

it('denies consumption when quota exceeded', function (): void {
    $feature = Feature::query()->create(['name' => 'API Calls', 'slug' => 'api-calls', 'type' => FeatureType::Consumable]);
    $this->plan->features()->attach($feature, ['value' => 10]);
    $this->user->subscribe($this->plan);

    expect($this->user->canConsume('api-calls', '20'))->toBeFalse();
});

it('throws on consume when quota exceeded', function (): void {
    $feature = Feature::query()->create(['name' => 'API Calls', 'slug' => 'api-calls', 'type' => FeatureType::Consumable]);
    $this->plan->features()->attach($feature, ['value' => 5]);
    $this->user->subscribe($this->plan);

    $this->user->consume('api-calls', '10');
})->throws(FeatureException::class, 'quota exceeded');

it('tryConsume returns false when quota exceeded', function (): void {
    $feature = Feature::query()->create(['name' => 'API Calls', 'slug' => 'api-calls', 'type' => FeatureType::Consumable]);
    $this->plan->features()->attach($feature, ['value' => 5]);
    $this->user->subscribe($this->plan);

    expect($this->user->tryConsume('api-calls', '10'))->toBeFalse();
});

it('tryConsume returns true and consumes when within quota', function (): void {
    $feature = Feature::query()->create(['name' => 'API Calls', 'slug' => 'api-calls', 'type' => FeatureType::Consumable]);
    $this->plan->features()->attach($feature, ['value' => 100]);
    $this->user->subscribe($this->plan);

    expect($this->user->tryConsume('api-calls', '10'))->toBeTrue()
        ->and($this->user->remainingUsage('api-calls'))->toBe('90');
});

it('allows unlimited usage when value is 0', function (): void {
    $feature = Feature::query()->create(['name' => 'API Calls', 'slug' => 'api-calls', 'type' => FeatureType::Consumable]);
    $this->plan->features()->attach($feature, ['value' => 0]);
    $this->user->subscribe($this->plan);

    expect($this->user->canConsume('api-calls', '999999'))->toBeTrue()
        ->and($this->user->remainingUsage('api-calls'))->toBe(FeatureService::UNLIMITED)
        ->and($this->user->isUnlimitedUsage('api-calls'))->toBeTrue();
});

it('can consume and release limit features', function (): void {
    $feature = Feature::query()->create(['name' => 'Projects', 'slug' => 'projects', 'type' => FeatureType::Limit]);
    $this->plan->features()->attach($feature, ['value' => 10]);
    $this->user->subscribe($this->plan);

    $this->user->consume('projects', '3');

    expect($this->user->remainingUsage('projects'))->toBe('7');

    $this->user->release('projects', '1');
    expect($this->user->remainingUsage('projects'))->toBe('8');

    Event::assertDispatched(FeatureReleased::class, fn (FeatureReleased $event): bool => $event->units === '1');
});

it('release does not go below zero', function (): void {
    $feature = Feature::query()->create(['name' => 'Projects', 'slug' => 'projects', 'type' => FeatureType::Limit]);
    $this->plan->features()->attach($feature, ['value' => 10]);
    $this->user->subscribe($this->plan);

    $this->user->consume('projects', '2');
    $this->user->release('projects', '5');

    expect($this->user->remainingUsage('projects'))->toBe('10');
});

it('cannot release non-limit features', function (): void {
    $feature = Feature::query()->create(['name' => 'API Calls', 'slug' => 'api-calls', 'type' => FeatureType::Consumable]);
    $this->plan->features()->attach($feature, ['value' => 100]);
    $this->user->subscribe($this->plan);

    $this->user->release('api-calls');
})->throws(FeatureException::class, 'not a limit-type');

it('throws when consuming non-existent feature', function (): void {
    $this->user->subscribe($this->plan);

    $this->user->consume('nonexistent');
})->throws(FeatureException::class, 'not found');

it('returns false for hasFeature on missing feature', function (): void {
    $this->user->subscribe($this->plan);

    expect($this->user->hasFeature('nonexistent'))->toBeFalse();
});

it('returns 0 remaining for missing feature', function (): void {
    $this->user->subscribe($this->plan);

    expect($this->user->remainingUsage('nonexistent'))->toBe('0');
});

it('returns feature info DTO', function (): void {
    $feature = Feature::query()->create(['name' => 'API Calls', 'slug' => 'api-calls', 'type' => FeatureType::Consumable]);
    $this->plan->features()->attach($feature, ['value' => 100]);
    $this->user->subscribe($this->plan);

    $this->user->consume('api-calls', '30');

    $info = $this->user->featureInfo('api-calls');

    expect($info->name)->toBe('API Calls')
        ->and($info->slug)->toBe('api-calls')
        ->and($info->type)->toBe(FeatureType::Consumable)
        ->and($info->limit)->toBe('100')
        ->and($info->used)->toBe('30')
        ->and($info->remaining)->toBe('70')
        ->and($info->unlimited)->toBeFalse()
        ->and($info->applicable)->toBeTrue();
});

it('returns all features info', function (): void {
    $toggle = Feature::query()->create(['name' => 'Branding', 'slug' => 'branding', 'type' => FeatureType::Toggle]);
    $consumable = Feature::query()->create(['name' => 'API Calls', 'slug' => 'api-calls', 'type' => FeatureType::Consumable]);
    $this->plan->features()->attach($toggle);
    $this->plan->features()->attach($consumable, ['value' => 100]);
    $this->user->subscribe($this->plan);

    $features = $this->user->allFeatures();

    expect($features)->toHaveCount(2);
});

it('returns unlimited quota info for value 0', function (): void {
    $feature = Feature::query()->create(['name' => 'Storage', 'slug' => 'storage', 'type' => FeatureType::Consumable]);
    $this->plan->features()->attach($feature, ['value' => 0]);
    $this->user->subscribe($this->plan);

    $info = $this->user->featureInfo('storage');

    expect($info->unlimited)->toBeTrue();
});

it('returns not applicable quota for toggle features', function (): void {
    $feature = Feature::query()->create(['name' => 'Branding', 'slug' => 'branding', 'type' => FeatureType::Toggle]);
    $this->plan->features()->attach($feature);
    $this->user->subscribe($this->plan);

    $info = $this->user->featureInfo('branding');

    expect($info->applicable)->toBeFalse();
});
