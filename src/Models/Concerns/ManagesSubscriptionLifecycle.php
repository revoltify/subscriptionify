<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Models\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Revoltify\Subscriptionify\Contracts\Subscribable;
use Revoltify\Subscriptionify\Enums\FeatureType;
use Revoltify\Subscriptionify\Enums\SubscriptionStatus;
use Revoltify\Subscriptionify\Events\SubscriptionCancelled;
use Revoltify\Subscriptionify\Events\SubscriptionExpired;
use Revoltify\Subscriptionify\Events\SubscriptionMarkedPastDue;
use Revoltify\Subscriptionify\Events\SubscriptionPlanChanged;
use Revoltify\Subscriptionify\Events\SubscriptionRenewed;
use Revoltify\Subscriptionify\Events\SubscriptionResumed;
use Revoltify\Subscriptionify\Exceptions\SubscriptionException;
use Revoltify\Subscriptionify\Models\Contracts\HasPlan;

trait ManagesSubscriptionLifecycle
{
    public function renew(?CarbonInterface $endsAt = null): static
    {
        return DB::transaction(function () use ($endsAt): static {
            $now = now();

            $this->update([
                'status' => SubscriptionStatus::Active,
                'starts_at' => $now,
                'ends_at' => $endsAt ?? $this->resolveRenewalEndsAt($now),
                'renewed_at' => $now,
                'cancelled_at' => null,
            ]);

            $this->clearSubscribableCache();
            event(new SubscriptionRenewed($this));

            return $this;
        });
    }

    public function changePlan(HasPlan $plan, ?CarbonInterface $endsAt = null, bool $resetUsages = false): static
    {
        return DB::transaction(function () use ($plan, $endsAt, $resetUsages): static {
            $oldPlan = $this->getPlan();

            $this->update([
                'plan_id' => $plan->getKey(),
                'status' => SubscriptionStatus::Active,
                'ends_at' => $endsAt ?? $plan->calculateEndsAt(now()),
                'cancelled_at' => null,
            ]);

            $this->load('plan');

            if ($resetUsages) {
                $this->resetSubscribableUsages();
            }

            $this->clearSubscribableCache();
            event(new SubscriptionPlanChanged($this, $oldPlan, $plan));

            return $this;
        });
    }

    public function cancel(): void
    {
        DB::transaction(function (): void {
            $this->update([
                'status' => SubscriptionStatus::Cancelled,
                'cancelled_at' => now(),
            ]);

            $this->clearSubscribableCache();
            event(new SubscriptionCancelled($this));
        });
    }

    public function cancelNow(): void
    {
        DB::transaction(function (): void {
            $now = now();

            $this->update([
                'status' => SubscriptionStatus::Cancelled,
                'cancelled_at' => $now,
                'ends_at' => $now,
            ]);

            $this->clearSubscribableCache();
            event(new SubscriptionCancelled($this));
        });
    }

    public function resume(): void
    {
        if (! $this->canceled() || $this->ended()) {
            throw SubscriptionException::cannotResume();
        }

        DB::transaction(function (): void {
            $this->update([
                'status' => SubscriptionStatus::Active,
                'cancelled_at' => null,
            ]);

            $this->clearSubscribableCache();
            event(new SubscriptionResumed($this));
        });
    }

    public function expire(): void
    {
        DB::transaction(function (): void {
            $this->update([
                'status' => SubscriptionStatus::Expired,
            ]);

            $this->clearSubscribableCache();
            event(new SubscriptionExpired($this));
        });
    }

    public function markPastDue(): void
    {
        DB::transaction(function (): void {
            $this->update([
                'status' => SubscriptionStatus::PastDue,
            ]);

            $this->clearSubscribableCache();
            event(new SubscriptionMarkedPastDue($this));
        });
    }

    private function resolveRenewalEndsAt(CarbonInterface $now): ?CarbonInterface
    {
        $currentEndsAt = $this->getEndsAt();

        $base = $currentEndsAt?->isFuture() ? $currentEndsAt : $now;

        return $this->getPlan()->calculateEndsAt($base);
    }

    private function clearSubscribableCache(): void
    {
        $model = $this->subscribable()->getResults();

        if ($model instanceof Subscribable) {
            $model->clearSubscriptionCache();
        }
    }

    private function resetSubscribableUsages(): void
    {
        $model = $this->subscribable()->getResults();

        if ($model instanceof Subscribable) {
            $model->featureUsages()
                ->whereHas('feature', fn (Builder $query) => $query->where('type', FeatureType::Consumable))
                ->update(['used' => 0, 'last_reset_at' => now()]);
        }
    }
}
