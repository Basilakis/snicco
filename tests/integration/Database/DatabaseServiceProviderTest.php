<?php

declare(strict_types=1);

namespace Tests\integration\Database;

use Faker\Generator;
use Snicco\Database\FakeDB;
use Snicco\Database\BetterWPDb;
use Snicco\Database\WPConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;
use Snicco\Database\WPConnectionResolver;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Snicco\Database\Contracts\BetterWPDbInterface;
use Snicco\Database\Illuminate\MySqlSchemaBuilder;
use Snicco\Database\Contracts\WPConnectionInterface;
use Snicco\Database\Contracts\ConnectionResolverInterface;
use Snicco\Database\Illuminate\IlluminateDispatcherAdapter;

class DatabaseServiceProviderTest extends DatabaseTestCase
{
    
    /** @test */
    public function the_illuminate_event_dispatcher_adapter_is_bound()
    {
        
        $this->bootApp();
        
        $this->assertInstanceOf(
            IlluminateDispatcherAdapter::class,
            $this->app->resolve(Dispatcher::class)
        );
        $this->assertInstanceOf(IlluminateDispatcherAdapter::class, $this->app->resolve('events'));
        
    }
    
    /** @test */
    public function eloquent_is_booted()
    {
        
        $this->bootApp();
        
        $events = Eloquent::getEventDispatcher();
        $this->assertInstanceOf(IlluminateDispatcherAdapter::class, $events);
        
        $resolver = Eloquent::getConnectionResolver();
        $this->assertInstanceOf(WPConnectionResolver::class, $resolver);
        
    }
    
    /** @test */
    public function the_connection_resolver_is_bound_correctly()
    {
        
        $this->bootApp();
        
        $this->assertInstanceOf(
            WPConnectionResolver::class,
            $this->app->resolve(ConnectionResolverInterface::class)
        );
        
    }
    
    /** @test */
    public function the_default_connection_is_set()
    {
        
        $this->bootApp();
        
        /** @var ConnectionResolverInterface $resolver */
        $resolver = $this->app->resolve(ConnectionResolverInterface::class);
        
        $this->assertSame('wp_connection', $resolver->getDefaultConnection());
        
    }
    
    /** @test */
    public function by_default_the_current_wpdb_instance_is_used()
    {
        
        $this->bootApp();
        
        /** @var ConnectionResolverInterface $resolver */
        $resolver = $this->app->resolve(ConnectionResolverInterface::class);
        $c = $resolver->connection();
        
        $this->assertInstanceOf(WPConnectionInterface::class, $c);
        
        $this->assertSame(DB_USER, $c->dbInstance()->dbuser);
        $this->assertSame(DB_HOST, $c->dbInstance()->dbhost);
        $this->assertSame(DB_NAME, $c->dbInstance()->dbname);
        $this->assertSame(DB_PASSWORD, $c->dbInstance()->dbpassword);
        
    }
    
    /** @test */
    public function the_wpdb_abstraction_can_be_changed()
    {
        
        $this->bootApp();
        $this->assertSame(BetterWPDb::class, $this->app->resolve(BetterWPDbInterface::class));
        
        $this->instance(BetterWPDbInterface::class, FakeDB::class);
        $this->assertSame(FakeDB::class, $this->app->resolve(BetterWPDbInterface::class));
        
    }
    
    /** @test */
    public function the_schema_builder_can_be_resolved_for_the_default_connection()
    {
        
        $this->bootApp();
        
        $b = $this->app->resolve(MySqlSchemaBuilder::class);
        $this->assertInstanceOf(MySqlSchemaBuilder::class, $b);
        
    }
    
    /** @test */
    public function the_connection_can_be_resolved_as_a_closure()
    {
        
        $this->bootApp();
        
        $connection = $this->app->resolve(WPConnectionInterface::class)();
        $this->assertInstanceOf(WPConnection::class, $connection);
        
    }
    
    /** @test */
    public function the_db_facade_works()
    {
        
        $this->bootApp();
        
        $connection = DB::connection();
        $this->assertInstanceOf(WPConnection::class, $connection);
        
        $builder = DB::table('foo');
        $this->assertInstanceOf(Builder::class, $builder);
        
    }
    
    /** @test */
    public function the_faker_instance_is_registered()
    {
        
        $this->bootApp();
        $this->assertInstanceOf(Generator::class, $this->app->resolve(Generator::class));
        
    }
    
}