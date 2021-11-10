<?php

declare(strict_types=1);

namespace Tests\unit\Routing;

use Mockery;
use Tests\UnitTest;
use Snicco\Support\WP;
use Snicco\Events\Event;
use Snicco\Routing\Router;
use Snicco\View\ViewFactory;
use Snicco\Http\Psr7\Request;
use Contracts\ContainerAdapter;
use Snicco\Http\ResponseFactory;
use Snicco\Routing\UrlGenerator;
use Tests\stubs\TestViewFactory;
use Tests\fixtures\Conditions\IsPost;
use Tests\helpers\CreateTestSubjects;
use Tests\helpers\CreateUrlGenerator;
use Tests\helpers\CreateRouteCollection;
use Tests\helpers\CreateDefaultWpApiMocks;

class FallbackRouteTest extends UnitTest
{
    
    use CreateTestSubjects;
    use CreateDefaultWpApiMocks;
    use CreateUrlGenerator;
    use CreateRouteCollection;
    
    private Router           $router;
    private ContainerAdapter $container;
    
    /** @test */
    public function for_web_request_the_fallback_route_controller_evaluates_all_routes_with_WP_conditions_and_no_url()
    {
        
        $this->createRoutes(function () {
            
            $this->router->get()->where(IsPost::class, true)
                         ->handle(function () {
                
                             return 'FOO';
                
                         });
            
            $this->router->createFallbackWebRoute();
            
        });
        
        $request = $this->webRequest('GET', '/post1');
        $this->runAndAssertOutput('FOO', $request);
        
    }
    
    /** @test */
    public function routes_that_do_have_a_wordpress_condition_AND_a_url_pattern_lead_to_not_calling_the_fallback_controller()
    {
        
        $this->createRoutes(function () {
            
            $this->router->get('post2')->where(IsPost::class, true)
                         ->handle(function () {
                
                             return 'FOO';
                         });
            
            $this->router->createFallbackWebRoute();
            
        });
        
        $request = $this->webRequest('GET', 'post1');
        $this->runAndAssertEmptyOutput($request);
        
        $request = $this->webRequest('GET', 'post2');
        $this->runAndAssertOutput('FOO', $request);
        
    }
    
    /** @test */
    public function users_can_create_a_custom_fallback_route_that_gets_run_if_the_fallback_controller_could_not_resolve_any_valid_wp_condition_route()
    {
        
        $this->createRoutes(function () {
            
            $this->router->get()->where(IsPost::class, false)
                         ->handle(function () {
                
                             return 'FOO';
                
                         });
            
            $this->router->createFallbackWebRoute();
            
        });
        
        $request = $this->webRequest('GET', 'post1');
        $this->runAndAssertEmptyOutput($request);
        
        $this->createRoutes(function () {
            
            $this->router->get()->where(IsPost::class, false)
                         ->handle(function () {
                
                             return 'FOO';
                
                         });
            
            $this->router->createFallbackWebRoute();
            
            $this->router->fallback(function (Request $request) {
                
                return 'FOO';
                
            });
        });
        
        $request = $this->webRequest('GET', 'post1');
        $this->runAndAssertOutput('FOO', $request);
        
    }
    
    protected function beforeTestRun()
    {
        
        $this->container = $this->createContainer();
        $this->routes = $this->newCachedRouteCollection();
        $this->container->instance(UrlGenerator::class, $this->newUrlGenerator());
        $this->container->instance(ViewFactory::class, new TestViewFactory());
        $this->container->instance(ResponseFactory::class, $this->createResponseFactory());
        Event::make($this->container);
        Event::fake();
        WP::setFacadeContainer($this->container);
        
    }
    
    protected function beforeTearDown()
    {
        
        Mockery::close();
        Event::setInstance(null);
        WP::reset();
        
    }
    
}