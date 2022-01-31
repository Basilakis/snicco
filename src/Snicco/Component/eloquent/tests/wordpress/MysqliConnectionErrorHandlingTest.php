<?php

declare(strict_types=1);

namespace Snicco\Component\Eloquent\Tests\wordpress;

use mysqli_sql_exception;
use Codeception\TestCase\WPTestCase;
use Illuminate\Database\QueryException;
use Snicco\Component\Eloquent\Mysqli\MysqliFactory;
use Snicco\Component\Eloquent\Illuminate\MysqliConnection;

final class MysqliConnectionErrorHandlingTest extends WPTestCase
{
    
    private MysqliConnection $connection;
    
    protected function setUp() :void
    {
        parent::setUp();
        $this->connection = (new MysqliFactory())->create();
    }
    
    /** @test */
    public function errors_get_handled_for_inserts()
    {
        try {
            $this->connection->insert('foo', ['bar']);
            $this->fail(
                "No exception thrown when inserting a value that is to big for a column"
            );
        } catch (QueryException $e) {
            $this->assertStringContainsString(
                'error: You have an error in your SQL syntax',
                $e->getMessage()
            );
        }
    }
    
    /** @test */
    public function errors_get_handles_for_updates()
    {
        try {
            $this->connection->update('foobar', ['foo' => 'bar']);
            $this->fail('No query exception thrown');
        } catch (QueryException $e) {
            $this->assertStringStartsWith(
                'error: You have an error in your SQL syntax',
                $e->getMessage()
            );
            $this->assertSame('foobar', $e->getSql());
            $this->assertSame(['foo' => 'bar'], $e->getBindings());
        }
    }
    
    /** @test */
    public function errors_get_handled_for_deletes()
    {
        try {
            $this->connection->delete('foobar', ['foo' => 'bar']);
            $this->fail('No query exception thrown');
        } catch (QueryException $e) {
            $this->assertStringStartsWith(
                'error: You have an error in your SQL syntax',
                $e->getMessage()
            );
            $this->assertSame('foobar', $e->getSql());
            $this->assertSame(['foo' => 'bar'], $e->getBindings());
        }
    }
    
    /** @test */
    public function errors_get_handled_for_unprepared_queries()
    {
        try {
            $this->connection->unprepared('foobar');
            $this->fail('No query exception thrown');
        } catch (QueryException $e) {
            $this->assertStringStartsWith(
                'error: You have an error in your SQL syntax',
                $e->getMessage()
            );
            $this->assertSame('foobar', $e->getSql());
            $this->assertSame([], $e->getBindings());
        }
    }
    
    /** @test */
    public function errors_get_handled_for_cursor_selects()
    {
        try {
            $generator = $this->connection->cursor('foobar', ['foo' => 'bar']);
            
            foreach ($generator as $foo) {
                $this->fail('No Exception thrown');
            }
        } catch (QueryException $e) {
            $this->assertInstanceOf(mysqli_sql_exception::class, $e->getPrevious());
            $this->assertSame('foobar', $e->getSql());
            $this->assertSame(['foo' => 'bar'], $e->getBindings());
        }
    }
    
    /** @test */
    public function errors_get_handled_for_selects()
    {
        try {
            $this->connection->select('foobar', ['foo' => 'bar']);
            $this->fail("no exception thrown");
        } catch (QueryException $e) {
            $this->assertStringStartsWith(
                'error: You have an error in your SQL syntax',
                $e->getMessage()
            );
            $this->assertSame('foobar', $e->getSql());
            $this->assertSame(['foo' => 'bar'], $e->getBindings());
        }
    }
    
}