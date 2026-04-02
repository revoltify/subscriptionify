<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Models\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Revoltify\Subscriptionify\Enums\Interval;
use Revoltify\Subscriptionify\Models\Feature;
use Revoltify\Subscriptionify\Models\FeaturePlan;
use Revoltify\Subscriptionify\Models\Subscription;

/**
 * @method int|string getKey()
 *
 * @mixin Model
 */
interface HasPlan
{
    public function getName(): string;

    public function getSlug(): string;

    public function getDescription(): ?string;

    public function isFree(): bool;

    public function isActive(): bool;

    public function getTrialDays(): int;

    public function hasTrialDays(): bool;

    public function getBillingInterval(): Interval;

    public function getBillingPeriod(): int;

    public function getGraceDays(): int;

    public function hasGraceDays(): bool;

    public function getSortOrder(): int;

    public function calculateEndsAt(CarbonInterface $from): ?CarbonInterface;

    /** @return BelongsToMany<Feature, $this&Model, FeaturePlan> */
    public function features(): BelongsToMany;

    /** @return HasMany<Subscription, $this&Model> */
    public function subscriptions(): HasMany;
}
