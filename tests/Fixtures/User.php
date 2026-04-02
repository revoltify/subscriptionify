<?php

declare(strict_types=1);

namespace Revoltify\Subscriptionify\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Revoltify\Subscriptionify\Concerns\InteractsWithSubscriptions;
use Revoltify\Subscriptionify\Contracts\Subscribable;

final class User extends Model implements Subscribable
{
    use InteractsWithSubscriptions;

    protected $guarded = [];

    /** @var string */
    protected $table = 'users';
}
