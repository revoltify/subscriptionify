<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Revoltify\Subscriptionify\Concerns\InteractsWithSubscriptions;
use Revoltify\Subscriptionify\Contracts\HasFunds;
use Revoltify\Subscriptionify\Contracts\Subscribable;

/**
 * @property numeric-string $balance
 */
final class UserWithFunds extends Model implements HasFunds, Subscribable
{
    use InteractsWithSubscriptions;

    protected $guarded = [];

    /** @var string */
    protected $table = 'users';

    /** @return numeric-string */
    public function getBalance(): string
    {
        /** @var numeric-string $balance */
        $balance = (string) $this->balance;

        return $balance;
    }

    public function hasSufficientFunds(string $amount): bool
    {
        return bccomp($this->balance, $amount, 8) >= 0;
    }

    public function deductFunds(string $amount, string $description): void
    {
        /** @var numeric-string $newBalance */
        $newBalance = bcsub($this->balance, $amount, 8);

        $this->update(['balance' => $newBalance]);
    }
}
