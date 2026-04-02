<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Models\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Revoltify\Subscriptionify\Enums\FeatureType;
use Revoltify\Subscriptionify\Models\FeaturePlan;
use Revoltify\Subscriptionify\Models\FeatureUsage;
use Revoltify\Subscriptionify\Models\Plan;

/**
 * @method int|string getKey()
 */
interface HasFeature
{
    public function getName(): string;

    public function getSlug(): string;

    public function getDescription(): ?string;

    public function getType(): FeatureType;

    public function hasQuota(): bool;

    public function isToggle(): bool;

    public function isConsumable(): bool;

    public function isLimit(): bool;

    public function isMetered(): bool;

    /** @return BelongsToMany<Plan, $this&Model, FeaturePlan> */
    public function plans(): BelongsToMany;

    /** @return HasMany<FeatureUsage, $this&Model> */
    public function usages(): HasMany;
}
