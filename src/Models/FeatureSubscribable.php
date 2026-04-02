<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Revoltify\Subscriptionify\Enums\Interval;
use Revoltify\Subscriptionify\Models\Contracts\HasFeaturePivot;

/**
 * @property int $id
 * @property int $feature_id
 * @property string $subscribable_type
 * @property int $subscribable_id
 * @property numeric-string $value
 * @property numeric-string $unit_price
 * @property int|null $reset_period
 * @property Interval|null $reset_interval
 */
final class FeatureSubscribable extends MorphPivot implements HasFeaturePivot
{
    /** @var list<string> */
    protected $fillable = [
        'feature_id',
        'subscribable_type',
        'subscribable_id',
        'value',
        'unit_price',
        'reset_period',
        'reset_interval',
    ];

    public function getTable(): string
    {
        return config()->string('subscriptionify.tables.feature_subscribable', 'feature_subscribable');
    }

    /** @return BelongsTo<Feature, $this> */
    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }

    /** @return MorphTo<Model, $this> */
    public function subscribable(): MorphTo
    {
        return $this->morphTo();
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
