<?php
namespace ItmoSoft\Subscriptions\Tests\Feature;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use ItmoSoft\Subscriptions\Exceptions\InvalidSubscriptionTransition;
use ItmoSoft\Subscriptions\Models\SubscriptionPlan;
use ItmoSoft\Subscriptions\Services\SubscriptionManager;
use ItmoSoft\Subscriptions\Tests\TestCase;
class SubscriptionLifecycleTest extends TestCase {
 protected function setUp():void {parent::setUp();Schema::create('users',function(Blueprint $t){$t->id();$t->string('name');$t->timestamps();});$this->artisan('migrate',['--database'=>'testing']);}
 private function fixtures():array {$user=new class extends Model {protected $table='users';protected $guarded=[];};$user->create(['name'=>'Test']);$plan=SubscriptionPlan::create(['name'=>'Monthly','slug'=>'monthly','amount'=>1200,'currency'=>'USD','interval'=>'month','interval_count'=>1,'is_active'=>true]);return [$user,$plan];}
 public function test_starts_and_persists_subscription():void {[$u,$p]=$this->fixtures();$s=app(SubscriptionManager::class)->start($u,$p);$this->assertSame('active',$s->status);$this->assertDatabaseHas('itmosoft_subscription_status_history',['subscription_id'=>$s->id,'to_status'=>'active']);}
 public function test_pause_resume_and_cancel():void {[$u,$p]=$this->fixtures();$m=app(SubscriptionManager::class);$s=$m->start($u,$p);$this->assertSame('paused',$m->pause($s)->status);$this->assertSame('active',$m->resume($s)->status);$this->assertSame('cancelled',$m->cancel($s)->status);}
 public function test_cancelled_subscription_cannot_be_renewed():void {[$u,$p]=$this->fixtures();$m=app(SubscriptionManager::class);$s=$m->cancel($m->start($u,$p));$this->expectException(InvalidSubscriptionTransition::class);$m->renew($s);}
}
