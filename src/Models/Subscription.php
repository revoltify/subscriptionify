<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Revoltify\Subscriptionify\Enums\SubscriptionStatus;
use Revoltify\Subscriptionify\Models\Concerns\ManagesSubscriptionLifecycle;
use Revoltify\Subscriptionify\Models\Contracts\HasPlan;
use Revoltify\Subscriptionify\Models\Contracts\HasSubscription;

/**
 * @property int $id
 * @property string $subscribable_type
 * @property int $subscribable_id
 * @property int $plan_id
 * @property SubscriptionStatus $status
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $renewed_at
 */
class Subscription extends Model implements HasSubscription
{
    use ManagesSubscriptionLifecycle;

    /** @var list<string> */
    protected $fillable = [
        'subscribable_type',
        'subscribable_id',
        'plan_id',
        'status',
        'starts_at',
        'ends_at',
        'trial_ends_at',
        'cancelled_at',
        'renewed_at',
    ];

    public function getTable(): string
    {
        return config()->string('subscriptionify.tables.subscriptions', 'subscriptions');
    }

    /** @return MorphTo<Model, $this> */
    public function subscribable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        /** @var class-string<Plan> $model */
        $model = config('subscriptionify.models.plan', Plan::class);

        return $this->belongsTo($model);
    }

    public function getStatus(): SubscriptionStatus
    {
        return $this->status;
    }

    public function getPlan(): HasPlan
    {
        $this->loadMissing('plan');

        /** @var HasPlan $plan */
        $plan = $this->plan;

        return $plan;
    }

    public function hasPlan(string $slug): bool
    {
        return $this->getPlan()->getSlug() === $slug;
    }

    public function getStartsAt(): CarbonInterface
    {
        return $this->starts_at;
    }

    public function getEndsAt(): ?CarbonInterface
    {
        return $this->ends_at;
    }

    public function getTrialEndsAt(): ?CarbonInterface
    {
        return $this->trial_ends_at;
    }

    public function active(): bool
    {
        return $this->status->isActive();
    }

    public function onTrial(): bool
    {
        return $this->status === SubscriptionStatus::Trialing
            && $this->trial_ends_at?->isFuture() === true;
    }

    public function recurring(): bool
    {
        return $this->status === SubscriptionStatus::Active;
    }

    public function canceled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function onGracePeriod(): bool
    {
        if (! $this->canceled()) {
            return false;
        }

        if ($this->ends_at === null) {
            return false;
        }

        if ($this->ends_at->isFuture()) {
            return true;
        }

        $graceDays = $this->getPlan()->getGraceDays();

        return $graceDays > 0 && $this->ends_at->copy()->addDays($graceDays)->isFuture();
    }

    public function ended(): bool
    {
        return $this->canceled() && ! $this->onGracePeriod();
    }

    public function pastDue(): bool
    {
        return $this->status === SubscriptionStatus::PastDue;
    }

    public function expired(): bool
    {
        if ($this->status === SubscriptionStatus::Expired) {
            return true;
        }

        return $this->status === SubscriptionStatus::Active
            && $this->ends_at !== null
            && $this->ends_at->isPast();
    }

    public function valid(): bool
    {
        if ($this->active()) {
            return true;
        }

        if ($this->onTrial()) {
            return true;
        }

        return $this->onGracePeriod();
    }

    public function daysRemaining(): int
    {
        if ($this->ends_at === null) {
            return 0;
        }

        return max(0, (int) round(now()->diffInDays($this->ends_at, false)));
    }

    public function trialDaysRemaining(): int
    {
        if (! $this->onTrial() || $this->trial_ends_at === null) {
            return 0;
        }

        return max(0, (int) round(now()->diffInDays($this->trial_ends_at, false)));
    }

    /** @return array<string, string|class-string> */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'renewed_at' => 'datetime',
        ];
    }
}
