<?php
namespace ItmoSoft\Subscriptions\Tests;
use ItmoSoft\Subscriptions\SubscriptionsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
abstract class TestCase extends Orchestra {protected function getPackageProviders($app):array{return [SubscriptionsServiceProvider::class];} protected function defineEnvironment($app):void{$app['config']->set('database.default','testing');$app['config']->set('database.connections.testing',['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true]);$app['config']->set('subscriptions.table_prefix','itmosoft_');}}
