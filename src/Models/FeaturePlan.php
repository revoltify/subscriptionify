<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Revoltify\Subscriptionify\Enums\Interval;
use Revoltify\Subscriptionify\Models\Contracts\HasFeaturePivot;

/**
 * @property int $plan_id
 * @property int $feature_id
 * @property numeric-string $value
 * @property numeric-string $unit_price
 * @property int|null $reset_period
 * @property Interval|null $reset_interval
 */
final class FeaturePlan extends Pivot implements HasFeaturePivot
{
    /** @var bool */
    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = [
        'value',
        'unit_price',
        'reset_period',
        'reset_interval',
    ];

    public function getTable(): string
    {
        return config()->string('subscriptionify.tables.feature_plan', 'feature_plan');
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        /** @var class-string<Plan> $model */
        $model = config('subscriptionify.models.plan', Plan::class);

        return $this->belongsTo($model);
    }

    /** @return BelongsTo<Feature, $this> */
    public function feature(): BelongsTo
    {
        /** @var class-string<Feature> $model */
        $model = config('subscriptionify.models.feature', Feature::class);

        return $this->belongsTo($model);
    }

    /** @return numeric-string */
    public function getValue(): string
    {
        return (string) $this->value;
    }

    /** @return numeric-string */
    public function getUnitPrice(): string
    {
        return $this->unit_price;
    }

    public function getResetDate(): ?DateTimeInterface
    {
        if ($this->reset_period === null || ! $this->reset_interval instanceof Interval) {
            return null;
        }

        return $this->reset_interval->addToDate(now(), $this->reset_period);
    }

    public function getResetPeriod(): ?int
    {
        return $this->reset_period;
    }

    public function getResetInterval(): ?Interval
    {
        return $this->reset_interval;
    }

    /** @return array<string, string|class-string> */
    protected function casts(): array
    {
        return [
            'value' => 'string',
            'unit_price' => 'decimal:8',
            'reset_period' => 'integer',
            'reset_interval' => Interval::class,
        ];
    }
}
