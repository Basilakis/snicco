<?php

declare(strict_types=1);

namespace Tests\Core;

use Snicco\Routing\RouteCollection;
use Snicco\Contracts\RouteUrlGenerator;
use Snicco\Routing\FastRoute\FastRouteUrlGenerator;
use Tests\Codeception\shared\helpers\CreatePsr17Factories;
use Snicco\Testing\MiddlewareTestCase as FrameworkMiddlewareTestCase;

class MiddlewareTestCase extends FrameworkMiddlewareTestCase
{
    
    use CreatePsr17Factories;
    
    protected RouteCollection $routes;
    
    protected function routeUrlGenerator() :RouteUrlGenerator
    {
        return new FastRouteUrlGenerator($this->routes = new RouteCollection());
    }
    
}