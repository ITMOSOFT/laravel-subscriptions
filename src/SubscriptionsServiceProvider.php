<?php
namespace ItmoSoft\Subscriptions;
use Illuminate\Support\ServiceProvider;
use ItmoSoft\Subscriptions\Services\SubscriptionManager;
class SubscriptionsServiceProvider extends ServiceProvider {
 public function register(): void { $this->mergeConfigFrom(__DIR__.'/../config/subscriptions.php','subscriptions'); $this->app->singleton('subscriptions',fn()=>new SubscriptionManager()); }
 public function boot(): void { $this->publishes([__DIR__.'/../config/subscriptions.php'=>config_path('subscriptions.php')],'subscriptions-config'); $this->publishes([__DIR__.'/../database/migrations'=>database_path('migrations')],'subscriptions-migrations'); $this->loadMigrationsFrom(__DIR__.'/../database/migrations'); }
}
