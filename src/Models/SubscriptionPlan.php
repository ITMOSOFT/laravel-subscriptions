<?php

namespace ItmoSoft\Subscriptions\Models;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class SubscriptionPlan extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::saving(function (self $plan): void {
            if (trim((string) $plan->name) === '' || trim((string) $plan->slug) === '') {
                throw new InvalidArgumentException('Subscription plan name and slug are required.');
            }

            if (! is_numeric($plan->amount) || ! is_finite((float) $plan->amount)
                || (float) $plan->amount < 0 || floor((float) $plan->amount) !== (float) $plan->amount) {
                throw new InvalidArgumentException('Plan amount must be a finite, non-negative integer in minor currency units.');
            }

            $currency = strtoupper(trim((string) $plan->currency));
            if (! preg_match('/^[A-Z]{3}$/', $currency)) {
                throw new InvalidArgumentException('Plan currency must be a three-letter code.');
            }

            if (! in_array($plan->interval, ['day', 'week', 'month', 'year'], true)) {
                throw new InvalidArgumentException('Plan interval must be day, week, month or year.');
            }

            $count = filter_var($plan->interval_count, FILTER_VALIDATE_INT);
            if ($count === false || $count < 1 || $count > 65535) {
                throw new InvalidArgumentException('Plan interval_count must be between 1 and 65535.');
            }

            $plan->currency = $currency;
            $plan->interval_count = $count;
        });
    }

    public function getTable()
    {
        return config('subscriptions.table_prefix', 'itmosoft_').'subscription_plans';
    }

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'interval_count' => 'integer',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
