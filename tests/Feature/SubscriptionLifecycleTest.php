<?php

namespace ItmoSoft\Subscriptions\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use ItmoSoft\Subscriptions\Exceptions\InvalidSubscriptionTransition;
use ItmoSoft\Subscriptions\Models\Subscription;
use ItmoSoft\Subscriptions\Models\SubscriptionPlan;
use ItmoSoft\Subscriptions\Services\SubscriptionManager;
use ItmoSoft\Subscriptions\Tests\TestCase;

class SubscriptionLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        $this->artisan('migrate', ['--database' => 'testing']);
    }

    private function fixtures(): array
    {
        $userModel = new class extends Model {
            protected $table = 'users';
            protected $guarded = [];
        };

        $user = $userModel->newQuery()->create(['name' => 'Test']);
        $plan = SubscriptionPlan::create([
            'name' => 'Monthly',
            'slug' => 'monthly',
            'amount' => 1200,
            'currency' => 'USD',
            'interval' => 'month',
            'interval_count' => 1,
            'is_active' => true,
        ]);

        return [$user, $plan];
    }

    public function test_starts_and_persists_subscription(): void
    {
        [$user, $plan] = $this->fixtures();

        $subscription = app(SubscriptionManager::class)->start($user, $plan);

        $this->assertSame('active', $subscription->status);
        $this->assertDatabaseHas('itmosoft_subscription_status_history', [
            'subscription_id' => $subscription->id,
            'to_status' => 'active',
        ]);
    }

    public function test_pause_resume_and_cancel(): void
    {
        [$user, $plan] = $this->fixtures();
        $manager = app(SubscriptionManager::class);
        $subscription = $manager->start($user, $plan);

        $this->assertSame('paused', $manager->pause($subscription)->status);
        $this->assertSame('active', $manager->resume($subscription)->status);
        $this->assertSame('cancelled', $manager->cancel($subscription)->status);
    }

    public function test_cancelled_subscription_cannot_be_renewed(): void
    {
        [$user, $plan] = $this->fixtures();
        $manager = app(SubscriptionManager::class);
        $subscription = $manager->cancel($manager->start($user, $plan));

        $this->expectException(InvalidSubscriptionTransition::class);
        $manager->renew($subscription);
    }

    public function test_unsaved_subscriber_is_rejected(): void
    {
        [, $plan] = $this->fixtures();
        $unsaved = new class extends Model {
            protected $table = 'users';
        };

        $this->expectException(InvalidArgumentException::class);
        app(SubscriptionManager::class)->start($unsaved, $plan);
    }

    public function test_unsaved_plan_is_rejected(): void
    {
        [$user] = $this->fixtures();

        $this->expectException(InvalidArgumentException::class);
        app(SubscriptionManager::class)->start($user, new SubscriptionPlan([
            'name' => 'Unsaved',
            'slug' => 'unsaved',
            'amount' => 100,
            'currency' => 'USD',
            'interval' => 'month',
            'interval_count' => 1,
            'is_active' => true,
        ]));
    }

    public function test_past_trial_end_is_rejected(): void
    {
        [$user, $plan] = $this->fixtures();

        $this->expectException(InvalidArgumentException::class);
        app(SubscriptionManager::class)->start($user, $plan, now()->subDay());
    }

    public function test_trial_start_uses_trial_end_as_next_billing_date(): void
    {
        [$user, $plan] = $this->fixtures();
        $trialEnd = now()->addDays(14);

        $subscription = app(SubscriptionManager::class)->start($user, $plan, $trialEnd);

        $this->assertSame('trialing', $subscription->status);
        $this->assertEquals($trialEnd->timestamp, $subscription->next_billing_at->timestamp);
    }

    public function test_resume_uses_locked_subscription_period_end_not_stale_model_data(): void
    {
        [$user, $plan] = $this->fixtures();
        $manager = app(SubscriptionManager::class);
        $subscription = $manager->pause($manager->start($user, $plan));

        $stale = Subscription::query()->findOrFail($subscription->id);
        $newPeriodEnd = now()->addMonths(3);
        $subscription->forceFill(['current_period_ends_at' => $newPeriodEnd])->save();

        $resumed = $manager->resume($stale);

        $this->assertEquals($newPeriodEnd->timestamp, $resumed->next_billing_at->timestamp);
    }

    public function test_plan_rejects_invalid_currency_and_interval(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SubscriptionPlan::create([
            'name' => 'Invalid',
            'slug' => 'invalid',
            'amount' => 100,
            'currency' => 'USDD',
            'interval' => 'fortnight',
            'interval_count' => 1,
            'is_active' => true,
        ]);
    }

    public function test_plan_rejects_non_integer_or_negative_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SubscriptionPlan::create([
            'name' => 'Invalid Amount',
            'slug' => 'invalid-amount',
            'amount' => -1,
            'currency' => 'USD',
            'interval' => 'month',
            'interval_count' => 1,
            'is_active' => true,
        ]);
    }

    public function test_unsaved_subscription_cannot_be_cancelled(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(SubscriptionManager::class)->cancel(new Subscription());
    }
}
