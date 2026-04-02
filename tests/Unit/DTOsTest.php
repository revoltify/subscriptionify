<?php

declare(strict_types=1);

use Revoltify\Subscriptionify\DTOs\ConsumptionResult;
use Revoltify\Subscriptionify\DTOs\FeatureInfo;
use Revoltify\Subscriptionify\DTOs\SubscriptionInfo;
use Revoltify\Subscriptionify\Enums\FeatureType;
use Revoltify\Subscriptionify\Enums\SubscriptionStatus;

it('creates free consumption result', function (): void {
    $result = ConsumptionResult::free('50');

    expect($result->remaining)->toBe('50')
        ->and($result->cost)->toBe('0.00000000')
        ->and($result->usedOverage)->toBeFalse();
});

it('creates overage consumption result', function (): void {
    $result = ConsumptionResult::withOverage('0', '5.00000000');

    expect($result->remaining)->toBe('0')
        ->and($result->cost)->toBe('5.00000000')
        ->and($result->usedOverage)->toBeTrue();
});

it('creates toggle feature info', function (): void {
    $info = FeatureInfo::toggle('Custom Branding', 'custom-branding');

    expect($info->name)->toBe('Custom Branding')
        ->and($info->slug)->toBe('custom-branding')
        ->and($info->type)->toBe(FeatureType::Toggle)
        ->and($info->applicable)->toBeFalse()
        ->and($info->unlimited)->toBeFalse();
});

it('creates unlimited feature info', function (): void {
    $info = FeatureInfo::unlimited('API Calls', 'api-calls', FeatureType::Consumable);

    expect($info->unlimited)->toBeTrue()
        ->and($info->applicable)->toBeTrue();
});

it('creates metered feature info', function (): void {
    $info = FeatureInfo::metered('Compute', 'compute', used: '100', unitPrice: '0.01000000');

    expect($info->used)->toBe('100')
        ->and($info->unlimited)->toBeTrue()
        ->and($info->applicable)->toBeTrue()
        ->and($info->unitPrice)->toBe('0.01000000');
});

it('creates consumable feature info', function (): void {
    $info = new FeatureInfo(
        name: 'API Calls',
        slug: 'api-calls',
        type: FeatureType::Consumable,
        limit: '1000',
        used: '300',
        remaining: '700',
        percentage: '30%',
    );

    expect($info->name)->toBe('API Calls')
        ->and($info->slug)->toBe('api-calls')
        ->and($info->type)->toBe(FeatureType::Consumable)
        ->and($info->limit)->toBe('1000')
        ->and($info->used)->toBe('300')
        ->and($info->remaining)->toBe('700');
});

it('creates empty subscription info', function (): void {
    $info = SubscriptionInfo::empty();

    expect($info->planName)->toBe('')
        ->and($info->isActive())->toBeFalse()
        ->and($info->status)->toBeNull()
        ->and($info->features)->toBeEmpty();
});

it('reports active status from subscription info', function (): void {
    $info = new SubscriptionInfo(
        planName: 'Pro',
        planSlug: 'pro',
        isFree: false,
        status: SubscriptionStatus::Active,
        billingInterval: null,
        billingPeriod: null,
        startsAt: now()->toDateTimeString(),
        endsAt: null,
        trialEndsAt: null,
        onTrial: false,
        onGracePeriod: false,
        features: collect(),
    );

    expect($info->isActive())->toBeTrue();
});
