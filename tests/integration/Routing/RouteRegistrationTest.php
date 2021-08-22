<?php

declare(strict_types=1);

namespace Tests\integration\Routing;

use Snicco\Support\WP;
use Snicco\Support\Arr;
use Tests\stubs\TestApp;
use Tests\FrameworkTestCase;
use Tests\helpers\CreatesWpUrls;
use Snicco\Contracts\ServiceProvider;
use Snicco\Events\IncomingAjaxRequest;
use Snicco\Events\IncomingAdminRequest;
use Tests\helpers\CreateDefaultWpApiMocks;

class RouteRegistrationTest extends FrameworkTestCase
{
    
    /** @test */
    public function web_routes_are_loaded_in_the_main_wp_function()
    {
        
        $this->withRequest($this->frontendRequest('GET', '/foo'));
        $this->bootApp();
        
        global $wp;
        // init loads the routes.
        do_action('init');
        $wp->main();
        
        $this->sentResponse()->assertSee('foo')->assertOk();
        
    }
    
    /** @test */
    public function admin_routes_are_run_on_the_loaded_on_admin_init_hook()
    {
        
        $this->withRequest($this->adminRequest('GET', 'foo'));
    
        $this->withAddedProvider(SimulateAdminProvider::class)
             ->withoutHooks()
             ->bootApp();
        
        WP::shouldReceive('pluginPageHook')->andReturn('toplevel_page_foo');
        
        do_action('init');
        do_action('admin_init');
        $hook = WP::pluginPageHook();
        do_action("load-$hook");
        
        $this->sentResponse()->assertOk()->assertSee('FOO_ADMIN');
        
    }
    
    /** @test */
    public function ajax_routes_are_loaded_first_on_admin_init()
    {
        
        $this->withRequest($this->adminAjaxRequest('POST', 'foo_action'));
        $this->withoutHooks();
        $this->bootApp();
        
        do_action('init');
        do_action('admin_init');
        
        $this->sentResponse()->assertOk()->assertSee('FOO_AJAX_ACTION');
        
    }
    
    /** @test */
    public function admin_routes_are_also_run_for_other_admin_pages()
    {
        
        $this->withRequest($this->adminRequest('GET', '', 'index.php'));
    
        $this->withAddedProvider(SimulateAdminProvider::class)
             ->withoutHooks()
             ->bootApp();
        
        WP::shouldReceive('pluginPageHook')->andReturnNull();
        
        do_action('init');
        do_action('admin_init');
        global $pagenow;
        do_action("load-$pagenow");
        
        $this->sentResponse()->assertRedirect('/foo');
        
    }
    
    /** @test */
    public function ajax_routes_are_only_run_if_the_request_has_an_action_parameter()
    {
        
        $request = $this->adminAjaxRequest('POST', 'foo_action')->withParsedBody([]);
        $this->withRequest($request);
        $this->withoutHooks()->bootApp();
        
        do_action('init');
        do_action('admin_init');
        
        $this->assertNoResponse();
        
    }
    
    /** @test */
    public function the_fallback_route_controller_is_registered_for_web_routes()
    {
    
        $this->withRequest($this->frontendRequest('GET', '/post1'));
        $this->bootApp();
        $this->makeFallbackConditionPass();
        
        global $wp;
        // init loads the routes.
        do_action('init');
        $wp->main();
        
        $this->sentResponse()->assertSee('get_fallback')->assertOk();
        
    }
    
    /** @test */
    public function the_fallback_controller_does_not_match_admin_routes()
    {
    
        $this->withAddedProvider(SimulateAdminProvider::class)
             ->withoutHooks()
             ->bootApp();
        
        $this->withRequest($request = $this->adminAjaxRequest('GET', 'bogus'));
        $this->makeFallbackConditionPass();
        
        do_action('init');
        IncomingAdminRequest::dispatch([$request]);
        
        $this->sentResponse()->assertDelegatedToWordPress();
        
    }
    
    /** @test */
    public function the_fallback_controller_does_not_match_ajax_routes()
    {
    
        $this->withAddedProvider(SimulateAjaxProvider::class)
             ->withoutHooks()
             ->bootApp();
        
        $this->withRequest($request = $this->adminAjaxRequest('POST', 'bogus'));
        $this->makeFallbackConditionPass();
        
        do_action('init');
        IncomingAjaxRequest::dispatch([$request]);
        
        $this->sentResponse()->assertDelegatedToWordPress();
        
    }
    
    /** @test */
    public function named_groups_prefixes_are_applied_for_admin_routes()
    {
    
        $this->withAddedProvider(SimulateAdminProvider::class);
        $this->bootApp();
        
        $this->loadRoutes();
        
        $this->assertSame('/wp-admin/admin.php?page=foo', TestApp::routeUrl('admin.foo'));
        
    }
    
    /** @test */
    public function named_groups_are_applied_for_ajax_routes()
    {
    
        $this->withAddedProvider(SimulateAjaxProvider::class);
        $this->bootApp();
        $this->loadRoutes();
        
        $this->assertSame('/wp-admin/admin-ajax.php', TestApp::routeUrl('ajax.foo'));
        
    }
    
    /** @test */
    public function custom_routes_dirs_can_be_provided()
    {
        
        $request = $this->frontendRequest('GET', '/other');
        $this->withRequest($request);
        $this->withAddedProvider(RoutingDefinitionServiceProvider::class)->withoutHooks()
             ->bootApp();
        
        global $wp;
        // init loads the routes.
        do_action('init');
        $wp->main();
        
        $this->sentResponse()->assertOk()->assertSee('other');
        
    }
    
    /** @test */
    public function a_file_with_the_same_name_will_not_be_loaded_twice_for_standard_routes()
    {
        
        $request = $this->frontendRequest('GET', '/web-other');
    
        $this->withRequest($request);
        $this->withAddedProvider(RoutingDefinitionServiceProvider::class)->withoutHooks()
             ->bootApp();
        
        global $wp;
        // init loads the routes.
        do_action('init');
        $wp->main();
        
        // without the filtering of file names the route in /OtherRoutes/web.php would match
        $this->sentResponse()->assertDelegatedToWordPress();
        
    }
    
    protected function makeFallbackConditionPass()
    {
        $GLOBALS['test']['pass_fallback_route_condition'] = true;
    }
    
}

class SimulateAjaxProvider extends ServiceProvider
{
    
    use CreateDefaultWpApiMocks;
    
    public function register() :void
    {
        
        $this->createDefaultWpApiMocks();
        WP::shouldReceive('isAdminAjax')->andReturnTrue();
        WP::shouldReceive('isAdmin')->andReturnTrue();
        WP::shouldReceive('isUserLoggedIn')->andReturnTrue();
    }
    
    function bootstrap() :void
    {
    
    }
    
}

class SimulateAdminProvider extends ServiceProvider
{
    
    use CreateDefaultWpApiMocks;
    use CreatesWpUrls;
    
    public function register() :void
    {
        
        $this->createDefaultWpApiMocks();
        
        WP::shouldReceive('isAdminAjax')->andReturnFalse();
        WP::shouldReceive('isAdmin')->andReturnTrue();
        WP::shouldReceive('pluginPageUrl')->andReturnUsing(function ($page) {
            
            return $this->adminUrlTo($page);
            
        });
    }
    
    function bootstrap() :void
    {
    
    }
    
}

class RoutingDefinitionServiceProvider extends ServiceProvider
{
    
    public function register() :void
    {
        
        $routes = Arr::wrap($this->config->get('routing.definitions'));
        
        $routes = array_merge($routes, [TESTS_DIR.DS.'fixtures'.DS.'OtherRoutes']);
        
        $this->config->set('routing.definitions', $routes);
        
    }
    
    function bootstrap() :void
    {
    }
    
}