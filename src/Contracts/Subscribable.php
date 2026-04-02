<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Revoltify\Subscriptionify\Models\Contracts\HasPlan;
use Revoltify\Subscriptionify\Models\Contracts\HasSubscription;
use Revoltify\Subscriptionify\Models\Feature;
use Revoltify\Subscriptionify\Models\FeatureUsage;
use Revoltify\Subscriptionify\Models\Subscription;

/**
 * @method int|string getKey()
 * @method string getMorphClass()
 *
 * @mixin Model
 */
interface Subscribable
{
    /** @return MorphMany<Subscription, $this&Model> */
    public function subscriptions(): MorphMany;

    /** @return MorphMany<FeatureUsage, $this&Model> */
    public function featureUsages(): MorphMany;

    /** @return MorphToMany<Feature, $this&Model> */
    public function directFeatures(): MorphToMany;

    public function subscription(): ?HasSubscription;

    public function clearSubscriptionCache(): static;

    public function subscribed(): bool;

    public function hasFeature(string $slug): bool;

    public function onTrial(): bool;

    public function onFreePlan(): bool;

    public function canChangePlan(HasPlan $plan): bool;
}
