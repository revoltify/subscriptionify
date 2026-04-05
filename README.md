# Subscriptionify

[![Tests](https://github.com/revoltify/subscriptionify/actions/workflows/run-tests.yml/badge.svg)](https://github.com/revoltify/subscriptionify/actions/workflows/run-tests.yml)
[![Laravel](https://img.shields.io/badge/Laravel-11%2B-red.svg)](https://laravel.com/)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE.md)

Feature-based subscription management for Laravel. Gateway-agnostic plans, features, usage tracking, and optional overage billing.

---

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Plans](#plans)
- [Features](#features)
- [Subscriptions](#subscriptions)
- [Feature Usage](#feature-usage)
- [Direct Feature Grants](#direct-feature-grants)
- [Metered Billing & Overage](#metered-billing--overage)
- [DTOs](#dtos)
- [Middleware](#middleware)
- [Blade Directives](#blade-directives)
- [Query Scopes](#query-scopes)
- [Events](#events)
- [Exceptions](#exceptions)
- [Configuration](#configuration)
- [Customization](#customization)
- [Testing](#testing)

---

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13

## Installation

```bash
composer require revoltify/subscriptionify
```

Publish the config and migrations, then run them:

```bash
php artisan vendor:publish --tag=subscriptionify-config
php artisan vendor:publish --tag=subscriptionify-migrations
php artisan migrate
```

This creates six tables: `plans`, `features`, `feature_plan`, `subscriptions`, `feature_usages`, and `feature_subscribable`.

---

## Quick Start

Add the trait and contract to your subscribable model (e.g. `Team`, `User`, or `Organization`):

```php
use Revoltify\Subscriptionify\Concerns\InteractsWithSubscriptions;
use Revoltify\Subscriptionify\Contracts\Subscribable;

class Team extends Model implements Subscribable
{
    use InteractsWithSubscriptions;
}
```

Create a plan, add features, and subscribe:

```php
use Revoltify\Subscriptionify\Models\Plan;
use Revoltify\Subscriptionify\Models\Feature;
use Revoltify\Subscriptionify\Enums\FeatureType;
use Revoltify\Subscriptionify\Enums\Interval;

// Create a plan
$plan = Plan::create([
    'name'             => 'Pro',
    'slug'             => 'pro',
    'billing_period'   => 1,
    'billing_interval' => Interval::Month,
    'trial_days'       => 14,
]);

// Create features and attach to plan
$apiCalls = Feature::create(['name' => 'API Calls', 'slug' => 'api-calls', 'type' => FeatureType::Consumable]);

$plan->features()->attach($apiCalls, [
    'value'      => 10_000,
    'unit_price' => '0.00100000',
]);

// Subscribe
$team->subscribe($plan);

// Use features
$team->consume('api-calls', 100);
$team->remainingUsage('api-calls');   // 9900
$team->remainingOverage('api-calls'); // extra units affordable from balance
```

---

## Plans

Plans define billing cycles, trial periods, and grace periods.

```php
// Free plan — never expires
Plan::create(['name' => 'Free', 'slug' => 'free', 'is_free' => true]);

// Monthly plan with trial
Plan::create([
    'name'             => 'Pro',
    'slug'             => 'pro',
    'billing_period'   => 1,
    'billing_interval' => Interval::Month,
    'trial_days'       => 14,
    'grace_days'       => 3,
]);

// Quarterly plan
Plan::create([
    'name'             => 'Business',
    'slug'             => 'business',
    'billing_period'   => 3,
    'billing_interval' => Interval::Month,
]);

// Yearly plan
Plan::create([
    'name'             => 'Enterprise',
    'slug'             => 'enterprise',
    'billing_period'   => 1,
    'billing_interval' => Interval::Year,
    'grace_days'       => 7,
]);
```

### Plan columns

| Column | Type | Default | Description |
|--------|------|---------|-------------|
| `name` | `string` | — | Display name |
| `slug` | `string` | — | Unique identifier |
| `description` | `string\|null` | `null` | Optional description |
| `is_free` | `bool` | `false` | Free plans never expire (`ends_at` is null) |
| `is_active` | `bool` | `true` | Whether the plan accepts new subscriptions |
| `billing_period` | `int` | `1` | Number of intervals per billing cycle |
| `billing_interval` | `Interval` | `Month` | `Day`, `Week`, `Month`, or `Year` |
| `trial_days` | `int` | `0` | Trial length in days (0 = no trial) |
| `grace_days` | `int` | `0` | Days of access after cancellation |
| `sort_order` | `int` | `0` | Display ordering |

### Plan methods

```php
$plan->getName();              // 'Pro'
$plan->getSlug();              // 'pro'
$plan->getDescription();       // 'Professional plan'
$plan->isFree();               // false
$plan->isActive();             // true
$plan->getTrialDays();         // 14
$plan->hasTrialDays();         // true
$plan->getBillingPeriod();     // 1
$plan->getBillingInterval();   // Interval::Month
$plan->getGraceDays();         // 3
$plan->hasGraceDays();         // true
$plan->getSortOrder();         // 0
$plan->calculateEndsAt(now()); // Carbon (null for free plans)
```

---

## Features

Four feature types model different SaaS quota patterns:

| Type | Behaviour | Resets | Releases | Charges |
|------|-----------|--------|----------|---------|
| **Toggle** | On/off access gate | — | — | — |
| **Consumable** | Depletable quota | Periodically | No | On overage |
| **Limit** | Hard cap with release | No | Yes | On overage |
| **Metered** | Pay-per-use, no cap | — | No | Per unit |

```php
use Revoltify\Subscriptionify\Models\Feature;
use Revoltify\Subscriptionify\Enums\FeatureType;

Feature::create(['name' => 'Custom Branding', 'slug' => 'branding',  'type' => FeatureType::Toggle]);
Feature::create(['name' => 'API Calls',       'slug' => 'api-calls', 'type' => FeatureType::Consumable]);
Feature::create(['name' => 'Projects',        'slug' => 'projects',  'type' => FeatureType::Limit]);
Feature::create(['name' => 'Compute Hours',   'slug' => 'compute',   'type' => FeatureType::Metered]);
```

### Feature methods

```php
$feature->getName();           // 'API Calls'
$feature->getSlug();           // 'api-calls'
$feature->getDescription();    // 'Monthly API call quota'
$feature->getType();           // FeatureType::Consumable
$feature->hasQuota();          // true (consumable & limit)
$feature->isToggle();          // false
$feature->isConsumable();      // true
$feature->isLimit();           // false
$feature->isMetered();         // false
```

### Attaching features to plans

Features are attached to plans via a pivot table with allocation data:

```php
$plan->features()->attach($feature, [
    'value'          => 10_000,        // quota limit (0 = unlimited)
    'unit_price'     => '0.00100000',  // overage/metered price per unit
    'reset_period'   => 1,             // reset cycle length
    'reset_interval' => 'month',       // day, week, month, or year
]);
```

> **Unlimited**: Setting `value` to `0` grants unlimited usage for that feature.

### Pivot data access

Pivot allocation data is accessed through the `HasFeaturePivot` contract on the pivot models (`FeaturePlan`, `FeatureSubscribable`):

```php
$feature = $plan->features()->first();

$feature->pivot->getValue();        // 10000
$feature->pivot->getUnitPrice();    // '0.00100000'
$feature->pivot->getResetPeriod();  // 1
$feature->pivot->getResetInterval();// Interval::Month
$feature->pivot->getResetDate();    // Carbon (next reset date)
```

---

## Subscriptions

### Creating a subscription

```php
$team->subscribe($plan);

// With custom end date
$team->subscribe($plan, endsAt: now()->addMonths(6));
```

If a plan has `trial_days > 0`, the subscription starts in `Trialing` status automatically. Free plans create subscriptions with `ends_at` set to `null` (never expires).

### Subscription statuses

| Status | Description |
|--------|-------------|
| `Active` | Normal active subscription |
| `Trialing` | In trial period |
| `PastDue` | Payment overdue |
| `Cancelled` | Cancelled by user |
| `Expired` | Billing period ended |

### Checking status on the subscribable

```php
$team->subscribed();               // has active/trialing subscription
$team->onPlan($plan);             // on a specific plan
$team->onTrial();                 // currently in trial
$team->onFreePlan();              // on a free plan
$team->canChangePlan($otherPlan); // not already on that plan
```

### Checking status on the subscription

```php
$subscription = $team->subscription();

$subscription->active();              // active or trialing
$subscription->onTrial();             // in trial period
$subscription->recurring();           // active, not trialing
$subscription->canceled();            // has been cancelled
$subscription->onGracePeriod();       // cancelled but still within grace period
$subscription->ended();               // cancelled and past grace period
$subscription->pastDue();             // marked as past due
$subscription->valid();               // active || trialing || on grace period
$subscription->hasPlan('pro');        // on a specific plan by slug
$subscription->daysRemaining();       // days until ends_at
$subscription->trialDaysRemaining();  // days remaining in trial
```

### Managing subscriptions

```php
$subscription = $team->subscription();

// Change plans
$subscription->changePlan($newPlan);
$subscription->changePlan($newPlan, endsAt: now()->addYear());
$subscription->changePlan($newPlan, resetUsages: true); // resets consumable usages

// Renew
$subscription->renew();
$subscription->renew(endsAt: now()->addYear());

// Cancel
$subscription->cancel();       // at end of billing period (grace period applies)
$subscription->cancelNow();    // immediately

// Resume (only during grace period)
$subscription->resume();

// Lifecycle
$subscription->expire();
$subscription->markPastDue();
```

### `subscriptions()` vs `subscription()`

- `$team->subscriptions()` — raw `MorphMany` relationship (all records, any status)
- `$team->subscription()` — resolves the current active/trialing subscription, **cached per request**

---

## Feature Usage

All feature operations are available directly on the subscribable model:

```php
// Check access (does the subscribable have this feature?)
$team->hasFeature('api-calls');

// Check if specific units can be consumed
$team->canConsume('api-calls', 100);

// Consume units (throws FeatureException if quota exceeded)
$team->consume('api-calls', 100);

// Try to consume (returns false instead of throwing)
$team->tryConsume('api-calls', 100);

// Check remaining plan quota
$team->remainingUsage('api-calls');

// Check remaining overage capacity (balance / unit_price)
// Requires HasFunds + unit_price configured, returns '0' otherwise
$team->remainingOverage('api-calls');

// Check if feature has unlimited quota
$team->isUnlimitedUsage('api-calls'); // true if unlimited

// Release units (Limit type only — frees up slots)
$team->release('projects', 1);
```

### How consumption works per type

| Type | `consume()` behaviour |
|------|----------------------|
| **Toggle** | No-op (access is checked via `hasFeature`) |
| **Consumable** | Increments usage, resets when `valid_until` expires, charges overage if `HasFunds` |
| **Limit** | Increments usage (use `release()` to free slots), charges overage if `HasFunds` |
| **Metered** | Increments usage and charges per unit if `HasFunds` |

---

## Direct Feature Grants

Grant features directly to a subscribable, independent of their plan. Grants are **additive** — if a plan provides 10,000 API calls and a direct grant adds 50,000, the total quota is 60,000.

```php
// Grant with quota
$team->grantFeature('api-calls', value: 50_000);

// Grant with custom unit price for overage
$team->grantFeature('api-calls', value: 50_000, unitPrice: '0.00050000');

// Grant with auto-reset
$team->grantFeature('reports', value: 100, resetPeriod: 1, resetInterval: Interval::Month);

// Grant unlimited (value: 0)
$team->grantFeature('api-calls', value: 0);

// Revoke direct grant (plan quota still applies)
$team->revokeFeature('api-calls');
```

---

## Metered Billing & Overage

Implement `HasFunds` alongside `Subscribable` to enable pay-per-use and overage billing:

```php
use Revoltify\Subscriptionify\Contracts\HasFunds;

class Team extends Model implements Subscribable, HasFunds
{
    use InteractsWithSubscriptions;

    /** @return numeric-string */
    public function getBalance(): string
    {
        return $this->balance;
    }

    public function hasSufficientFunds(string $amount): bool
    {
        return bccomp($this->balance, $amount, 8) >= 0;
    }

    public function deductFunds(string $amount, string $description): void
    {
        /** @var numeric-string $newBalance */
        $newBalance = bcsub($this->balance, $amount, 8);

        $this->update(['balance' => $newBalance]);
    }
}
```

### Billing behaviour per feature type

| Feature Type | Without `HasFunds` | With `HasFunds` |
|---|---|---|
| **Toggle** | Access check only | Access check only |
| **Consumable** | Hard quota limit — exceeding throws | Quota + automatic overage charging when exceeded |
| **Limit** | Hard cap — exceeding throws | Hard cap + automatic overage charging when exceeded |
| **Metered** | Free unlimited usage tracking | Charged per unit consumed, deducted from balance |

**Overage** kicks in when a consumable or limit feature exceeds its quota and the subscribable has both:
1. A `unit_price` configured on the feature
2. `HasFunds` implemented with sufficient balance

### Checking remaining overage capacity

Use `remainingOverage()` to check how many additional overage units a subscribable can afford based on their current balance:

```php
// Plan quota: 10,000 | Unit price: $0.001 | Balance: $50.00
$team->remainingUsage('api-calls');   // '10000' — plan quota remaining
$team->remainingOverage('api-calls'); // '50000' — extra units affordable (50 / 0.001)
```

Returns `'0'` when:
- The subscribable does not implement `HasFunds`
- The feature has no `unit_price` configured
- The balance is zero or negative

> **Note:** Since the balance is shared across all features, consuming overage on one feature reduces the overage capacity for all others. The value represents a point-in-time snapshot.

---

## DTOs

### `FeatureInfo`

Rich snapshot of a feature's current state for a subscribable:

```php
$feature = $team->featureInfo('api-calls');

$feature->name;              // 'API Calls'
$feature->slug;              // 'api-calls'
$feature->type;              // FeatureType::Consumable
$feature->limit;             // 10000
$feature->used;              // 3500
$feature->remaining;         // 6500
$feature->percentage;        // '35.00%'
$feature->unlimited;         // false
$feature->applicable;        // true (false for toggle features)
$feature->validUntil;        // '2026-05-01 00:00:00'
$feature->overageAvailable;  // true (HasFunds + unit price configured)
$feature->unitPrice;         // '0.00100000'
$feature->resetPeriod;       // 1
$feature->resetInterval;     // Interval::Month
```

### `SubscriptionInfo`

Complete subscription snapshot for building UI:

```php
$info = $team->subscriptionInfo();

$info->planName;           // 'Pro'
$info->planSlug;           // 'pro'
$info->isFree;             // false
$info->status;             // SubscriptionStatus::Active
$info->billingInterval;    // Interval::Month
$info->billingPeriod;      // 1
$info->startsAt;           // '2026-04-01 00:00:00'
$info->endsAt;             // '2026-05-01 00:00:00'
$info->trialEndsAt;        // null
$info->onTrial;            // false
$info->onGracePeriod;      // false
$info->features;           // Collection<int, FeatureInfo>
$info->isActive();         // true
```

### `ConsumptionResult`

Returned internally after consuming units:

```php
$result->remaining;   // 6500
$result->cost;        // '0.00000000' or '0.50000000' (if overage)
$result->usedOverage; // false or true
```

### All features

```php
$features = $team->allFeatures(); // Collection<int, FeatureInfo>
```

---

## Middleware

Three middleware are registered automatically via the config. They throw `403` responses on failure.

| Middleware | Purpose | Usage |
|-----------|---------|-------|
| `subscribed` | Requires active subscription | `Route::middleware('subscribed')` |
| `plan:{slug}` | Requires specific plan | `Route::middleware('plan:pro')` |
| `feature:{slug}` | Requires specific feature | `Route::middleware('feature:api-calls')` |

```php
Route::middleware('subscribed')->group(function () {
    // Only accessible with an active subscription
});

Route::middleware('plan:pro')->group(function () {
    // Only accessible on the Pro plan
});

Route::middleware('feature:api-calls')->group(function () {
    // Only accessible if the subscribable has the api-calls feature
});
```

The subscribable is resolved via `Subscriptionify::resolveSubscribable()`, which defaults to `auth()->user()`. See [Subscribable Resolver](#subscribable-resolver) to customize.

---

## Blade Directives

```blade
@subscribed
    {{-- Active subscription content --}}
@endsubscribed

@plan('pro')
    {{-- Pro plan only content --}}
@endplan

@feature('custom-branding')
    {{-- Custom branding enabled --}}
@endfeature

@onTrial
    {{-- Trial period notice --}}
@endonTrial

@onFreePlan
    {{-- Upgrade prompt --}}
@endonFreePlan

@onGracePeriod
    {{-- Grace period warning --}}
@endonGracePeriod
```

---

## Query Scopes

Query scopes are available on models that use the `InteractsWithSubscriptions` trait:

```php
// All teams with active/trialing subscriptions
Team::whereSubscribed()->get();

// All teams on a specific plan
Team::whereOnPlan($proPlan)->get();

// All teams currently in trial
Team::whereOnTrial()->get();

// All teams with expired subscriptions
Team::whereExpired()->get();
```

---

## Events

All lifecycle events are dispatched automatically:

| Event | Dispatched when |
|-------|----------------|
| `SubscriptionCreated` | A new subscription is created |
| `SubscriptionRenewed` | A subscription is renewed |
| `SubscriptionCancelled` | A subscription is cancelled |
| `SubscriptionResumed` | A cancelled subscription is resumed |
| `SubscriptionPlanChanged` | The subscription's plan is changed |
| `SubscriptionExpired` | A subscription is expired |
| `SubscriptionExpiring` | A subscription is about to expire |
| `SubscriptionMarkedPastDue` | A subscription is marked as past due |
| `FeatureConsumed` | Feature units are consumed |
| `FeatureReleased` | Feature units are released (limit type) |

```php
// Example: Listen for feature consumption
use Revoltify\Subscriptionify\Events\FeatureConsumed;

class TrackApiUsage
{
    public function handle(FeatureConsumed $event): void
    {
        // $event->subscribable
        // $event->feature
        // $event->units
        // $event->remaining
        // $event->cost
        // $event->usedOverage
    }
}
```

---

## Exceptions

| Exception | When |
|-----------|------|
| `SubscriptionException` | Already subscribed, cannot resume ended subscription |
| `FeatureException` | Feature not found, quota exceeded, non-limit release |
| `InsufficientFundsException` | Balance too low for metered charge or overage |

```php
use Revoltify\Subscriptionify\Exceptions\SubscriptionException;
use Revoltify\Subscriptionify\Exceptions\FeatureException;
use Revoltify\Subscriptionify\Exceptions\InsufficientFundsException;

try {
    $team->consume('api-calls', 100);
} catch (FeatureException $e) {
    // Quota exceeded
} catch (InsufficientFundsException $e) {
    // Insufficient balance for overage
}
```

---

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=subscriptionify-config
```

```php
// config/subscriptionify.php

return [
    // Override with your own models (must extend base or implement contracts)
    'models' => [
        'plan'         => Plan::class,
        'feature'      => Feature::class,
        'subscription' => Subscription::class,
    ],

    // Rename tables if they conflict (e.g. with Cashier)
    'tables' => [
        'plans'                => 'plans',
        'features'             => 'features',
        'feature_plan'         => 'feature_plan',
        'subscriptions'        => 'subscriptions',
        'feature_usages'       => 'feature_usages',
        'feature_subscribable' => 'feature_subscribable',
    ],

    // Rename middleware aliases if they conflict
    'middleware' => [
        'subscribed' => 'subscribed',
        'plan'       => 'plan',
        'feature'    => 'feature',
    ],
];
```

---

## Customization

### Custom models

Extend the base models and register them in the config. All internal relationships resolve from config automatically.

```php
use Revoltify\Subscriptionify\Models\Plan as BasePlan;

class Plan extends BasePlan
{
    // Add your own columns, relationships, or methods
}
```

```php
// config/subscriptionify.php
'models' => [
    'plan' => \App\Models\Plan::class,
],
```

### Subscribable resolver

By default, `auth()->user()` is used as the subscribable for middleware and Blade directives. Override this in your `AppServiceProvider`:

```php
use Revoltify\Subscriptionify\Subscriptionify;

public function boot(): void
{
    Subscriptionify::resolveSubscribableUsing(fn () => Team::current());
}
```

### Custom subscription resolution

Override `resolveSubscription()` in your model to change which subscription is resolved. The default resolves the latest `Active` or `Trialing` subscription:

```php
use Revoltify\Subscriptionify\Enums\SubscriptionStatus;
use Revoltify\Subscriptionify\Models\Contracts\HasSubscription;

class Team extends Model implements Subscribable
{
    use InteractsWithSubscriptions;

    protected function resolveSubscription(): ?HasSubscription
    {
        return $this->subscriptions()
            ->whereIn('status', [
                SubscriptionStatus::Active,
                SubscriptionStatus::Trialing,
                SubscriptionStatus::PastDue, // also include past-due
            ])
            ->with('plan')
            ->latest()
            ->first();
    }
}
```

> `subscription()` is `final` — override `resolveSubscription()` instead. The caching layer stays intact.

---

## Testing

```bash
./vendor/bin/pest
```
