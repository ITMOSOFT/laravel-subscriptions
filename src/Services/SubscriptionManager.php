<?php
namespace ItmoSoft\Subscriptions\Services;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use ItmoSoft\Subscriptions\Exceptions\InvalidSubscriptionTransition;
use ItmoSoft\Subscriptions\Models\Subscription;
use ItmoSoft\Subscriptions\Models\SubscriptionPlan;
class SubscriptionManager {
 public function start(Model $subscriber, SubscriptionPlan $plan, ?Carbon $trialEndsAt=null): Subscription {
  if(!$plan->is_active) throw new InvalidSubscriptionTransition('Cannot subscribe to an inactive plan.');
  $now=now(); $trial=$trialEndsAt && $trialEndsAt->isFuture(); $end=$this->advance($now,$plan->interval,(int)$plan->interval_count);
  return DB::transaction(function() use($subscriber,$plan,$trialEndsAt,$trial,$now,$end){
   $s=Subscription::create(['plan_id'=>$plan->id,'subscriber_type'=>$subscriber->getMorphClass(),'subscriber_id'=>$subscriber->getKey(),'name'=>$plan->name,'status'=>$trial?'trialing':'active','amount'=>$plan->amount,'currency'=>$plan->currency,'interval'=>$plan->interval,'interval_count'=>$plan->interval_count,'trial_ends_at'=>$trialEndsAt,'current_period_starts_at'=>$now,'current_period_ends_at'=>$end,'next_billing_at'=>$trialEndsAt??$end]);
   $this->history($s,null,$s->status); return $s;
  });
 }
 public function renew(Subscription $subscription): Subscription { return DB::transaction(function()use($subscription){$s=Subscription::whereKey($subscription->id)->lockForUpdate()->firstOrFail(); if(!in_array($s->status,['active','trialing'],true)) throw new InvalidSubscriptionTransition('Only active or trialing subscriptions can renew.'); $start=$s->current_period_ends_at && $s->current_period_ends_at->isFuture()?$s->current_period_ends_at:now(); $end=$this->advance($start,$s->interval,(int)$s->interval_count); $from=$s->status; $s->forceFill(['status'=>'active','trial_ends_at'=>null,'current_period_starts_at'=>$start,'current_period_ends_at'=>$end,'next_billing_at'=>$end])->save(); if($from!=='active')$this->history($s,$from,'active'); return $s->refresh();}); }
 public function cancel(Subscription $s): Subscription {return $this->transition($s,'cancelled',['trialing','active','paused'],['cancelled_at'=>now(),'ended_at'=>now(),'next_billing_at'=>null]);}
 public function pause(Subscription $s): Subscription {return $this->transition($s,'paused',['active','trialing'],['paused_at'=>now(),'next_billing_at'=>null]);}
 public function resume(Subscription $s): Subscription {return $this->transition($s,'active',['paused'],['paused_at'=>null,'next_billing_at'=>$s->current_period_ends_at]);}
 private function transition(Subscription $s,string $to,array $allowed,array $attrs): Subscription {return DB::transaction(function()use($s,$to,$allowed,$attrs){$locked=Subscription::whereKey($s->id)->lockForUpdate()->firstOrFail();$from=$locked->status;if(!in_array($from,$allowed,true))throw new InvalidSubscriptionTransition("Cannot transition from {$from} to {$to}.");$locked->forceFill($attrs+['status'=>$to])->save();$this->history($locked,$from,$to);return $locked->refresh();});}
 private function history(Subscription $s,?string $from,string $to):void{$s->statusHistory()->create(['from_status'=>$from,'to_status'=>$to,'created_at'=>now()]);}
 private function advance(Carbon $date,string $interval,int $count):Carbon {if($count<1)throw new InvalidSubscriptionTransition('interval_count must be at least 1.');return match($interval){'day'=>$date->copy()->addDays($count),'week'=>$date->copy()->addWeeks($count),'month'=>$date->copy()->addMonthsNoOverflow($count),'year'=>$date->copy()->addYearsNoOverflow($count),default=>throw new InvalidSubscriptionTransition("Unsupported interval: {$interval}")};}
}
