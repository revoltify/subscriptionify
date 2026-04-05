<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Models\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Revoltify\Subscriptionify\Enums\SubscriptionStatus;
use Revoltify\Subscriptionify\Models\Plan;

/**
 * @method int|string getKey()
 */
interface HasSubscription
{
    /** @return MorphTo<Model, $this&Model> */
    public function subscribable(): MorphTo;

    /** @return BelongsTo<Plan, $this&Model> */
    public function plan(): BelongsTo;

    public function getPlan(): HasPlan;

    public function hasPlan(string $slug): bool;

    public function getStatus(): SubscriptionStatus;

    public function getStartsAt(): CarbonInterface;

    public function getEndsAt(): ?CarbonInterface;

    public function getTrialEndsAt(): ?CarbonInterface;

    public function active(): bool;

    public function onTrial(): bool;

    public function recurring(): bool;

    public function canceled(): bool;

    public function onGracePeriod(): bool;

    public function ended(): bool;

    public function pastDue(): bool;

    public function expired(): bool;

    public function valid(): bool;

    public function daysRemaining(): int;

    public function trialDaysRemaining(): int;

    public function renew(?CarbonInterface $endsAt = null): self;

    public function changePlan(HasPlan $plan, ?CarbonInterface $endsAt = null, bool $resetUsages = false): self;

    public function cancel(): void;

    public function cancelNow(): void;

    public function resume(): void;

    public function expire(): void;

    public function markPastDue(): void;
}
