<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Revoltify\Subscriptionify\Enums\FeatureType;
use Revoltify\Subscriptionify\Models\Contracts\HasFeature;
use Revoltify\Subscriptionify\Models\Contracts\HasFeaturePivot;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property FeatureType $type
 * @property int $sort_order
 * @property-read HasFeaturePivot|null $pivot
 */
class Feature extends Model implements HasFeature
{
    /** @var list<string> */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'type',
        'sort_order',
    ];

    public function getTable(): string
    {
        return config()->string('subscriptionify.tables.features', 'features');
    }

    /** @return BelongsToMany<Plan, $this, FeaturePlan> */
    public function plans(): BelongsToMany
    {
        $table = config()->string('subscriptionify.tables.feature_plan', 'feature_plan');

        /** @var class-string<Plan> $model */
        $model = config('subscriptionify.models.plan', Plan::class);

        return $this->belongsToMany($model, $table)
            ->using(FeaturePlan::class)
            ->withPivot(['value', 'unit_price', 'reset_period', 'reset_interval']);
    }

    /** @return HasMany<FeatureUsage, $this> */
    public function usages(): HasMany
    {
        return $this->hasMany(FeatureUsage::class);
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

    public function getType(): FeatureType
    {
        return $this->type;
    }

    public function hasQuota(): bool
    {
        return $this->type->hasQuota();
    }

    public function isToggle(): bool
    {
        return $this->type === FeatureType::Toggle;
    }

    public function isConsumable(): bool
    {
        return $this->type === FeatureType::Consumable;
    }

    public function isLimit(): bool
    {
        return $this->type === FeatureType::Limit;
    }

    public function isMetered(): bool
    {
        return $this->type === FeatureType::Metered;
    }

    protected static function booted(): void
    {
        static::deleting(function (self $feature): void {
            $feature->plans()->detach();
            $feature->usages()->delete();
            FeatureSubscribable::query()->where('feature_id', $feature->getKey())->delete();
        });
    }

    /** @return array<string, string|class-string> */
    protected function casts(): array
    {
        return [
            'type' => FeatureType::class,
            'sort_order' => 'integer',
        ];
    }
}
