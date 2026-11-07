<?php
namespace ItmoSoft\Subscriptions\Models;
use Illuminate\Database\Eloquent\Model;
class Subscription extends Model { protected $guarded=[]; public function getTable(){ return config('subscriptions.table_prefix','itmosoft_').'subscriptions'; } public function subscriber(){ return $this->morphTo(); } public function plan(){ return $this->belongsTo(SubscriptionPlan::class,'plan_id'); } public function statusHistory(){ return $this->hasMany(SubscriptionStatusHistory::class); } protected function casts(): array { return ['amount'=>'integer','trial_ends_at'=>'datetime','current_period_starts_at'=>'datetime','current_period_ends_at'=>'datetime','next_billing_at'=>'datetime','cancelled_at'=>'datetime','paused_at'=>'datetime','ended_at'=>'datetime','metadata'=>'array']; } }
