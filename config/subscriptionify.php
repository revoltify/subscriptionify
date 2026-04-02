<?php

declare(strict_types=1);

use Revoltify\Subscriptionify\Models\Feature;
use Revoltify\Subscriptionify\Models\Plan;
use Revoltify\Subscriptionify\Models\Subscription;

return [

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | Customise the Eloquent models used by the package. Your custom models
    | should extend the package's base models or implement the corresponding
    | contract interfaces (HasPlan, HasFeature, HasSubscription).
    |
    */

    'models' => [
        'plan' => Plan::class,
        'feature' => Feature::class,
        'subscription' => Subscription::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    | If your application already uses one of the default table names
    | (e.g. you have a "subscriptions" table from Cashier), you can
    | rename any Subscriptionify table here.
    |
    */

    'tables' => [
        'plans' => 'plans',
        'features' => 'features',
        'feature_plan' => 'feature_plan',
        'subscriptions' => 'subscriptions',
        'feature_usages' => 'feature_usages',
        'feature_subscribable' => 'feature_subscribable',
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware Aliases
    |--------------------------------------------------------------------------
    |
    | The route middleware aliases registered by the package.
    | Rename them here if they conflict with your application's existing aliases.
    |
    */

    'middleware' => [
        'subscribed' => 'subscribed',
        'plan' => 'plan',
        'feature' => 'feature',
    ],

];
