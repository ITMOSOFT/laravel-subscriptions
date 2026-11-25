<?php

namespace ItmoSoft\Subscriptions\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use ItmoSoft\Subscriptions\Exceptions\InvalidSubscriptionTransition;
use ItmoSoft\Subscriptions\Models\Subscription;
use ItmoSoft\Subscriptions\Models\SubscriptionPlan;

class SubscriptionManager
{
    public function start(Model $subscriber, SubscriptionPlan $plan, ?Carbon $trialEndsAt = null): Subscription
    {
        if (! $subscriber->exists || $subscriber->getKey() === null) {
            throw new InvalidArgumentException('Subscriber must be a persisted Eloquent model.');
        }

        if (! $plan->exists || $plan->getKey() === null) {
            throw new InvalidArgumentException('Subscription plan must be persisted.');
        }

        if ($trialEndsAt !== null && ! $trialEndsAt->isFuture()) {
            throw new InvalidArgumentException('Trial end must be in the future.');
        }

        return DB::transaction(function () use ($subscriber, $plan, $trialEndsAt): Subscription {
            /** @var SubscriptionPlan $lockedPlan */
            $lockedPlan = SubscriptionPlan::query()->lockForUpdate()->findOrFail($plan->getKey());

            if (! $lockedPlan->is_active) {
                throw new InvalidSubscriptionTransition('Cannot subscribe to an inactive plan.');
            }

            $now = now();
            $trial = $trialEndsAt !== null;
            $periodEnd = $this->advance($now, $lockedPlan->interval, (int) $lockedPlan->interval_count);

            $subscription = Subscription::create([
                'plan_id' => $lockedPlan->getKey(),
                'subscriber_type' => $subscriber->getMorphClass(),
                'subscriber_id' => $subscriber->getKey(),
                'name' => $lockedPlan->name,
                'status' => $trial ? 'trialing' : 'active',
                'amount' => $lockedPlan->amount,
                'currency' => $lockedPlan->currency,
                'interval' => $lockedPlan->interval,
                'interval_count' => $lockedPlan->interval_count,
                'trial_ends_at' => $trialEndsAt,
                'current_period_starts_at' => $now,
                'current_period_ends_at' => $periodEnd,
                'next_billing_at' => $trial ? $trialEndsAt : $periodEnd,
            ]);

            $this->history($subscription, null, $subscription->status);

            return $subscription;
        });
    }

    public function renew(Subscription $subscription): Subscription
    {
        $this->assertPersisted($subscription);

        return DB::transaction(function () use ($subscription): Subscription {
            $locked = Subscription::query()->lockForUpdate()->findOrFail($subscription->getKey());

            if (! in_array($locked->status, ['active', 'trialing'], true)) {
                throw new InvalidSubscriptionTransition('Only active or trialing subscriptions can renew.');
            }

            $start = $locked->current_period_ends_at && $locked->current_period_ends_at->isFuture()
                ? $locked->current_period_ends_at
                : now();
            $end = $this->advance($start, $locked->interval, (int) $locked->interval_count);
            $from = $locked->status;

            $locked->forceFill([
                'status' => 'active',
                'trial_ends_at' => null,
                'current_period_starts_at' => $start,
                'current_period_ends_at' => $end,
                'next_billing_at' => $end,
            ])->save();

            if ($from !== 'active') {
                $this->history($locked, $from, 'active');
            }

            return $locked->refresh();
        });
    }

    public function cancel(Subscription $subscription): Subscription
    {
        return $this->transition(
            $subscription,
            'cancelled',
            ['trialing', 'active', 'paused'],
            ['cancelled_at' => now(), 'ended_at' => now(), 'next_billing_at' => null]
        );
    }

    public function pause(Subscription $subscription): Subscription
    {
        return $this->transition(
            $subscription,
            'paused',
            ['active', 'trialing'],
            ['paused_at' => now(), 'next_billing_at' => null]
        );
    }

    public function resume(Subscription $subscription): Subscription
    {
        $this->assertPersisted($subscription);

        return DB::transaction(function () use ($subscription): Subscription {
            $locked = Subscription::query()->lockForUpdate()->findOrFail($subscription->getKey());
            $from = $locked->status;

            if ($from !== 'paused') {
                throw new InvalidSubscriptionTransition("Cannot transition from {$from} to active.");
            }

            $locked->forceFill([
                'status' => 'active',
                'paused_at' => null,
                'next_billing_at' => $locked->current_period_ends_at,
            ])->save();

            $this->history($locked, $from, 'active');

            return $locked->refresh();
        });
    }

    private function transition(
        Subscription $subscription,
        string $to,
        array $allowed,
        array $attributes
    ): Subscription {
        $this->assertPersisted($subscription);

        return DB::transaction(function () use ($subscription, $to, $allowed, $attributes): Subscription {
            $locked = Subscription::query()->lockForUpdate()->findOrFail($subscription->getKey());
            $from = $locked->status;

            if (! in_array($from, $allowed, true)) {
                throw new InvalidSubscriptionTransition("Cannot transition from {$from} to {$to}.");
            }

            $locked->forceFill($attributes + ['status' => $to])->save();
            $this->history($locked, $from, $to);

            return $locked->refresh();
        });
    }

    private function assertPersisted(Subscription $subscription): void
    {
        if (! $subscription->exists || $subscription->getKey() === null) {
            throw new InvalidArgumentException('Subscription must be persisted.');
        }
    }

    private function history(Subscription $subscription, ?string $from, string $to): void
    {
        $subscription->statusHistory()->create([
            'from_status' => $from,
            'to_status' => $to,
            'created_at' => now(),
        ]);
    }

    private function advance(Carbon $date, string $interval, int $count): Carbon
    {
        if ($count < 1) {
            throw new InvalidSubscriptionTransition('interval_count must be at least 1.');
        }

        return match ($interval) {
            'day' => $date->copy()->addDays($count),
            'week' => $date->copy()->addWeeks($count),
            'month' => $date->copy()->addMonthsNoOverflow($count),
            'year' => $date->copy()->addYearsNoOverflow($count),
            default => throw new InvalidSubscriptionTransition("Unsupported interval: {$interval}"),
        };
    }
}
