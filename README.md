# ITMOSOFT Laravel Subscriptions

Gateway-agnostic subscription lifecycle primitives for Laravel 12 and 13.

## Features
- Subscription plans with amount (integer minor units), currency, interval and interval count.
- Polymorphic subscribers.
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

Renew only after the integrating application confirms successful payment. Schedule renewal work in the host application and use distributed locking plus gateway idempotency. v1.0.0 cancellation is immediate; no proration, grace periods, metered billing, dunning, tax or payment collection is included.

## Test
```bash
composer install
composer test
```
GitHub Actions runs `composer test` for Laravel 12 and 13; failed PHPUnit tests fail the job.
