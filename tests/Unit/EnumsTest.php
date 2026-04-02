<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Date;
use Revoltify\Subscriptionify\Enums\FeatureType;
use Revoltify\Subscriptionify\Enums\Interval;
use Revoltify\Subscriptionify\Enums\SubscriptionStatus;

it('checks hasQuota correctly', function (FeatureType $type, bool $expected): void {
    expect($type->hasQuota())->toBe($expected);
})->with([
    [FeatureType::Toggle, false],
    [FeatureType::Consumable, true],
    [FeatureType::Limit, true],
    [FeatureType::Metered, false],
]);

it('adds intervals to date correctly', function (Interval $interval, int $period, string $expected): void {
    $base = Date::create(2026, 1, 1);
    $result = $interval->addToDate($base, $period);

    expect($result->toDateString())->toBe($expected);
})->with([
    [Interval::Day, 7, '2026-01-08'],
    [Interval::Week, 2, '2026-01-15'],
    [Interval::Month, 3, '2026-04-01'],
    [Interval::Year, 1, '2027-01-01'],
]);

it('does not mutate original date', function (): void {
    $base = Date::create(2026, 1, 1);
    Interval::Month->addToDate($base, 1);

    expect($base->toDateString())->toBe('2026-01-01');
});

it('checks isActive correctly', function (SubscriptionStatus $status, bool $expected): void {
    expect($status->isActive())->toBe($expected);
})->with([
    [SubscriptionStatus::Active, true],
    [SubscriptionStatus::Trialing, true],
    [SubscriptionStatus::PastDue, false],
    [SubscriptionStatus::Cancelled, false],
    [SubscriptionStatus::Expired, false],
]);
