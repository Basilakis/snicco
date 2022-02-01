<?php

declare(strict_types=1);

namespace Snicco\Component\HttpRouting\Tests\Routing;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Snicco\Component\HttpRouting\Routing\Exception\RouteNotFound;
use Snicco\Component\HttpRouting\Routing\Route\Route;
use Snicco\Component\HttpRouting\Routing\Route\RouteCollection;
use stdClass;

final class RouteCollectionTest extends TestCase
{

    /**
     * @test
     */
    public function test_exception_if_constructed_with_bad_route(): void
    {
        $this->expectException(InvalidArgumentException::class);
        /** @noinspection PhpParamsInspection */
        $routes = new RouteCollection([new stdClass()]);
    }

    /**
     * @test
     */
    public function test_exception_if_route_with_duplicate_name_added(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            sprintf('Duplicate route with name [r1] while create [%s].', RouteCollection::class)
        );

        $r1 = Route::create('/foo', Route::DELEGATE, 'r1');
        $r2 = Route::create('/bar', Route::DELEGATE, 'r1');

        $routes = new RouteCollection([$r1, $r2]);
    }

    /**
     * @test
     */
    public function test_count(): void
    {
        $r1 = Route::create('/foo', Route::DELEGATE, 'r1');
        $r2 = Route::create('/bar', Route::DELEGATE, 'r2');

        $routes = new RouteCollection([$r1, $r2]);

        $this->assertSame(2, count($routes));
    }

    /**
     * @test
     */
    public function test_iterator(): void
    {
        $r1 = Route::create('/foo', Route::DELEGATE, 'r1');
        $r2 = Route::create('/bar', Route::DELEGATE, 'r2');

        $routes = new RouteCollection([$r1, $r2]);

        $count = 0;
        foreach ($routes as $route) {
            $this->assertInstanceOf(Route::class, $route);
            $count++;
        }
        $this->assertSame(2, $count);
    }

    /**
     * @test
     */
    public function test_exception_for_bad_route_name(): void
    {
        $this->expectException(RouteNotFound::class);

        $r1 = Route::create('/foo', Route::DELEGATE, 'r1');
        $r2 = Route::create('/bar', Route::DELEGATE, 'r2');

        $routes = new RouteCollection([$r1, $r2]);

        $route = $routes->getByName('r1');
        $this->assertEquals($r1, $route);

        $route = $routes->getByName('r3');
    }

}