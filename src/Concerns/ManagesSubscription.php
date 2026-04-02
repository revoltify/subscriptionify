<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Revoltify\Subscriptionify\DTOs\FeatureInfo;
use Revoltify\Subscriptionify\DTOs\SubscriptionInfo;
use Revoltify\Subscriptionify\Enums\SubscriptionStatus;
use Revoltify\Subscriptionify\Events\SubscriptionCreated;
use Revoltify\Subscriptionify\Exceptions\SubscriptionException;
use Revoltify\Subscriptionify\Models\Contracts\HasPlan;
use Revoltify\Subscriptionify\Models\Contracts\HasSubscription;
use Revoltify\Subscriptionify\Services\FeatureService;

trait ManagesSubscription
{
    private ?HasSubscription $subscriptionCache = null;

    private bool $subscriptionCacheResolved = false;

    final public function subscription(): ?HasSubscription
    {
        if (! $this->subscriptionCacheResolved) {
            $this->subscriptionCache = $this->resolveSubscription();
            $this->subscriptionCacheResolved = true;
        }

        return $this->subscriptionCache;
    }

    final public function clearSubscriptionCache(): static
    {
        $this->subscriptionCache = null;
        $this->subscriptionCacheResolved = false;

        return $this;
    }

    public function subscribed(): bool
    {
        return $this->subscription() !== null;
    }

    public function subscribe(HasPlan $plan, ?CarbonInterface $endsAt = null): HasSubscription
    {
        if ($this->subscribed()) {
            throw SubscriptionException::alreadySubscribed();
        }

        return DB::transaction(function () use ($plan, $endsAt): HasSubscription {
            $now = now();
            $isTrialing = $plan->getTrialDays() > 0;

            $trialEndsAt = $isTrialing
                ? $now->copy()->addDays($plan->getTrialDays())
                : null;

            $billingBase = $trialEndsAt ?? $now;

            $subscription = $this->subscriptions()->create([
                'plan_id' => $plan->getKey(),
                'status' => $isTrialing ? SubscriptionStatus::Trialing : SubscriptionStatus::Active,
                'starts_at' => $now,
                'ends_at' => $endsAt ?? $plan->calculateEndsAt($billingBase),
                'trial_ends_at' => $trialEndsAt,
            ]);

            $this->clearSubscriptionCache();
            event(new SubscriptionCreated($subscription));

            return $subscription;
        });
    }

    public function onTrial(): bool
    {
        return $this->subscription()?->onTrial() === true;
    }

    public function onPlan(HasPlan $plan): bool
    {
        $subscription = $this->subscription();

        if (! $subscription) {
            return false;
        }

        return $subscription->getPlan()->getKey() === $plan->getKey();
    }

    public function canChangePlan(HasPlan $plan): bool
    {
        $subscription = $this->subscription();

        if (! $subscription) {
            return false;
        }

        return $subscription->getPlan()->getKey() !== $plan->getKey();
    }

    public function onFreePlan(): bool
    {
        $subscription = $this->subscription();

        if (! $subscription) {
            return false;
        }

        return $subscription->getPlan()->isFree();
    }

    public function subscriptionInfo(): SubscriptionInfo
    {
        $subscription = $this->subscription();

        if (! $subscription) {
            return SubscriptionInfo::empty();
        }

        $plan = $subscription->getPlan();

        $featureService = resolve(FeatureService::class);

        /** @var Collection<int, FeatureInfo> $features */
        $features = collect($featureService->allFeatures($this));

        return new SubscriptionInfo(
            planName: $plan->getName(),
            planSlug: $plan->getSlug(),
            isFree: $plan->isFree(),
            status: $subscription->getStatus(),
            billingInterval: $plan->getBillingInterval(),
            billingPeriod: $plan->getBillingPeriod(),
            startsAt: $subscription->getStartsAt()->toDateTimeString(),
            endsAt: $subscription->getEndsAt()?->toDateTimeString(),
            trialEndsAt: $subscription->getTrialEndsAt()?->toDateTimeString(),
            onTrial: $subscription->onTrial(),
            onGracePeriod: $subscription->onGracePeriod(),
            features: $features,
        );
    }

    /**
     * Resolve the active subscription query.
     *
     * Override this method to customise which subscription is resolved
     * (e.g. include PastDue, change ordering, or add scopes).
     */
    protected function resolveSubscription(): ?HasSubscription
    {
        return $this->subscriptions()
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Trialing])
            ->with('plan')
            ->latest()
            ->first();
    }
}
