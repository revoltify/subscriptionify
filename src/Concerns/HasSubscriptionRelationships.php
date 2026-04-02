<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Concerns;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Revoltify\Subscriptionify\Enums\SubscriptionStatus;
use Revoltify\Subscriptionify\Models\Contracts\HasPlan;
use Revoltify\Subscriptionify\Models\Feature;
use Revoltify\Subscriptionify\Models\FeatureSubscribable;
use Revoltify\Subscriptionify\Models\FeatureUsage;
use Revoltify\Subscriptionify\Models\Subscription;

trait HasSubscriptionRelationships
{
    public function subscriptions(): MorphMany
    {
        /** @var class-string<Subscription> $model */
        $model = config('subscriptionify.models.subscription', Subscription::class);

        return $this->morphMany($model, 'subscribable');
    }

    public function featureUsages(): MorphMany
    {
        return $this->morphMany(FeatureUsage::class, 'subscribable');
    }

    public function directFeatures(): MorphToMany
    {
        $table = config()->string('subscriptionify.tables.feature_subscribable', 'feature_subscribable');

        /** @var class-string<Feature> $model */
        $model = config('subscriptionify.models.feature', Feature::class);

        return $this->morphToMany($model, 'subscribable', $table)
            ->using(FeatureSubscribable::class)
            ->withPivot(['value', 'unit_price', 'reset_period', 'reset_interval'])
            ->withTimestamps();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function whereSubscribed(Builder $query): Builder
    {
        return $query->whereHas('subscriptions', function (Builder $q): void {
            $q->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Trialing]);
        });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function whereOnPlan(Builder $query, HasPlan $plan): Builder
    {
        return $query->whereHas('subscriptions', function (Builder $q) use ($plan): void {
            $q->where('plan_id', $plan->getKey())
                ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Trialing]);
        });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function whereOnTrial(Builder $query): Builder
    {
        return $query->whereHas('subscriptions', function (Builder $q): void {
            $q->where('status', SubscriptionStatus::Trialing)
                ->where('trial_ends_at', '>', now());
        });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function whereExpired(Builder $query): Builder
    {
        return $query->whereHas('subscriptions', function (Builder $q): void {
            $q->where('status', SubscriptionStatus::Expired);
        });
    }
}
