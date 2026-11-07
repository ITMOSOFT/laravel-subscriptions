<?php
namespace ItmoSoft\Subscriptions\Models;
use Illuminate\Database\Eloquent\Model;
class SubscriptionStatusHistory extends Model { protected $guarded=[]; public $timestamps=false; public function getTable(){ return config('subscriptions.table_prefix','itmosoft_').'subscription_status_history'; } public function subscription(){ return $this->belongsTo(Subscription::class); } protected function casts(): array { return ['created_at'=>'datetime','context'=>'array']; } }
