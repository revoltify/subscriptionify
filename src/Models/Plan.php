<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;
use Revoltify\Subscriptionify\Enums\Interval;
use Revoltify\Subscriptionify\Enums\SubscriptionStatus;
use Revoltify\Subscriptionify\Models\Contracts\HasPlan;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $is_free
 * @property bool $is_active
 * @property int $trial_days
 * @property int $billing_period
 * @property Interval $billing_interval
 * @property int $grace_days
 * @property int $sort_order
 */
class Plan extends Model implements HasPlan
{
    /** @var list<string> */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_free',
        'is_active',
        'trial_days',
        'billing_period',
        'billing_interval',
        'grace_days',
        'sort_order',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_free' => false,
        'is_active' => true,
        'trial_days' => 0,
        'billing_period' => 1,
        'billing_interval' => 'month',
        'grace_days' => 0,
        'sort_order' => 0,
    ];

    public function getTable(): string
    {
        return config()->string('subscriptionify.tables.plans', 'plans');
    }

    /** @return BelongsToMany<Feature, $this, FeaturePlan> */
    public function features(): BelongsToMany
    {
        $table = config()->string('subscriptionify.tables.feature_plan', 'feature_plan');

        /** @var class-string<Feature> $model */
        $model = config('subscriptionify.models.feature', Feature::class);

        return $this->belongsToMany($model, $table)
            ->using(FeaturePlan::class)
            ->withPivot(['value', 'unit_price', 'reset_period', 'reset_interval']);
    }

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        /** @var class-string<Subscription> $model */
        $model = config('subscriptionify.models.subscription', Subscription::class);

        return $this->hasMany($model);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function isFree(): bool
    {
        return $this->is_free;
    }

    public function getTrialDays(): int
    {
        return $this->trial_days;
    }

    public function hasTrialDays(): bool
    {
        return $this->trial_days > 0;
    }

    public function getBillingInterval(): Interval
    {
        return $this->billing_interval;
    }

    public function getBillingPeriod(): int
    {
        return $this->billing_period;
    }

    public function getGraceDays(): int
    {
        return $this->grace_days;
    }

    public function hasGraceDays(): bool
    {
        return $this->grace_days > 0;
    }

    public function getSortOrder(): int
    {
        return $this->sort_order;
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function calculateEndsAt(CarbonInterface $from): ?CarbonInterface
    {
        if ($this->isFree()) {
            return null;
        }

        return $this->billing_interval->addToDate($from, $this->billing_period);
    }

    protected static function booted(): void
    {
        static::deleting(function (self $plan): void {
            $hasActiveSubscriptions = $plan->subscriptions()
                ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Trialing])
                ->exists();

            if ($hasActiveSubscriptions) {
                throw new LogicException(
                    sprintf('Cannot delete plan [%s] because it has active subscriptions.', $plan->getSlug()),
                );
            }

            $plan->features()->detach();
        });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @return array<string, string|class-string> */
    protected function casts(): array
    {
        return [
            'is_free' => 'boolean',
            'is_active' => 'boolean',
            'trial_days' => 'integer',
            'billing_period' => 'integer',
            'billing_interval' => Interval::class,
            'grace_days' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
