<?php

declare(strict_types=1);

namespace Snicco\Component\HttpRouting\Tests\Routing;

use PHPUnit\Framework\TestCase;
use Snicco\Component\HttpRouting\Testing\CreatesPsrRequests;
use Snicco\Component\HttpRouting\Routing\AdminDashboard\AdminArea;
use Snicco\Component\HttpRouting\Routing\AdminDashboard\WPAdminArea;
use Snicco\Component\HttpRouting\Tests\helpers\CreateTestPsr17Factories;
use Snicco\Component\HttpRouting\Routing\Condition\QueryStringCondition;

final class QueryStringConditionTest extends TestCase
{
    
    use CreateTestPsr17Factories;
    use CreatesPsrRequests;
    
    /** @test */
    public function test_is_satisfied_can_pass()
    {
        $request = $this->frontendRequest('/foo?bar=baz');
        
        $condition = new QueryStringCondition(['bar' => 'baz']);
        
        $this->assertTrue($condition->isSatisfied($request));
    }
    
    /** @test */
    public function test_is_satisfied_can_fail()
    {
        $request = $this->frontendRequest('/static?foo=bar&baz=biz');
        
        $condition = new QueryStringCondition(['foo' => 'bar', 'baz' => 'boo']);
        
        $this->assertFalse($condition->isSatisfied($request));
    }
    
    /** @test */
    public function test_get_arguments()
    {
        $request = $this->frontendRequest('/static?foo=bar&baz=biz&bang=boo');
        
        $condition = new QueryStringCondition(['foo' => 'bar', 'baz' => 'biz']);
        
        // Boo not present
        $this->assertSame([
            'bar',
            'biz',
        ], $condition->getArguments($request));
    }
    
    protected function adminDashboard() :AdminArea
    {
        return WPAdminArea::fromDefaults();
    }
    
}