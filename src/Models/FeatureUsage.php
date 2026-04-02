<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $subscribable_type
 * @property int $subscribable_id
 * @property int $feature_id
 * @property numeric-string $used
 * @property Carbon|null $valid_until
 * @property Carbon|null $last_reset_at
 */
final class FeatureUsage extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'subscribable_type',
        'subscribable_id',
        'feature_id',
        'used',
        'valid_until',
        'last_reset_at',
    ];

    public function getTable(): string
    {
        return config()->string('subscriptionify.tables.feature_usages', 'feature_usages');
    }

    /** @return MorphTo<Model, $this> */
    public function subscribable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Feature, $this> */
    public function feature(): BelongsTo
    {
        /** @var class-string<Feature> $model */
        $model = config('subscriptionify.models.feature', Feature::class);

        return $this->belongsTo($model);
    }

    public function expired(): bool
    {
        return $this->valid_until !== null && $this->valid_until->isPast();
    }

    /** @return numeric-string */
    public function currentUsage(): string
    {
        return $this->expired() ? '0' : $this->used;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'used' => 'string',
            'valid_until' => 'datetime',
            'last_reset_at' => 'datetime',
        ];
    }
}
