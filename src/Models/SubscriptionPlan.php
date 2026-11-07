<?php
namespace ItmoSoft\Subscriptions\Models;
use Illuminate\Database\Eloquent\Model;
class SubscriptionPlan extends Model { protected $guarded=[]; public function getTable(){ return config('subscriptions.table_prefix','itmosoft_').'subscription_plans'; } protected function casts(): array { return ['amount'=>'integer','interval_count'=>'integer','is_active'=>'boolean','metadata'=>'array']; } }
