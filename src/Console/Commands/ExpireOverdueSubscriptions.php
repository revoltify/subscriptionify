<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Console\Commands;

use Illuminate\Console\Command;
use Revoltify\Subscriptionify\Enums\SubscriptionStatus;
use Revoltify\Subscriptionify\Models\Subscription;

final class ExpireOverdueSubscriptions extends Command
{
    /** @var string */
    protected $signature = 'subscriptionify:expire-overdue';

    /** @var string */
    protected $description = 'Expire active subscriptions whose ends_at date has passed';

    public function handle(): int
    {
        /** @var class-string<Subscription> $model */
        $model = config('subscriptionify.models.subscription', Subscription::class);

        $count = 0;

        $model::query()
            ->where('status', SubscriptionStatus::Active)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now())
            ->each(function (Subscription $subscription) use (&$count): void {
                $subscription->expire();
                $count++;
            });

        $this->info("Expired {$count} overdue subscription(s).");

        return self::SUCCESS;
    }
}
