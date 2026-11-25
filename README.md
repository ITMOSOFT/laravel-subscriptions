# ITMOSOFT Laravel Subscriptions

Gateway-agnostic subscription lifecycle primitives for Laravel 12 and 13.

## Features
- Subscription plans with amount in integer minor currency units, currency, interval and interval count.
- Persisted polymorphic subscribers.
- Trial, active, paused and cancelled states.
- Start, renew, pause, resume and immediate cancellation operations.
- Status history and configurable database table prefix.
- Renewal does not collect payment; no gateway API is called.

## Install
```bash
composer require itmosoft/laravel-subscriptions
php artisan vendor:publish --tag=subscriptions-config
php artisan vendor:publish --tag=subscriptions-migrations
php artisan migrate
```

## Use
```php
use ItmoSoft\Subscriptions\Models\SubscriptionPlan;
use ItmoSoft\Subscriptions\Services\SubscriptionManager;

$plan = SubscriptionPlan::create([
    'name' => 'Pro Monthly', 'slug' => 'pro-monthly',
    'amount' => 2900, 'currency' => 'USD',
    'interval' => 'month', 'interval_count' => 1, 'is_active' => true,
]);
$subscription = app(SubscriptionManager::class)->start($user, $plan, now()->addDays(14));
```

Plans require a persisted record, a non-negative integer amount in minor units, a three-letter currency code, and an interval of `day`, `week`, `month`, or `year` with a positive interval count. Subscribers must be persisted Eloquent models. A supplied trial end must be in the future.

Lifecycle operations reload and lock the subscription row before checking its current status. Resuming uses the period end from the locked database row, not potentially stale attributes on the model passed by the caller. Each successful status change is recorded in status history within the same transaction.

Renew only after the integrating application confirms successful payment. Schedule renewal work in the host application and use distributed locking plus gateway idempotency. Cancellation is immediate; no proration, grace periods, metered billing, dunning, tax or payment collection is included.

## Test
```bash
composer validate --strict
composer install
composer test
```
