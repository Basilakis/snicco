<?php

declare(strict_types=1);

namespace Snicco\Bridge\Blade\Tests\wordpress;

use Illuminate\Container\Container;
use Codeception\TestCase\WPTestCase;
use Symfony\Component\Finder\Finder;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\Assert as PHPUnit;
use Snicco\Bridge\Blade\BladeStandalone;
use Snicco\Component\Templating\View\View;
use Snicco\Component\ScopableWP\ScopableWP;
use Snicco\Component\Templating\ViewEngine;
use Snicco\Component\Templating\GlobalViewContext;
use Snicco\Component\Templating\ViewComposer\ViewComposerCollection;

use function trim;
use function unlink;
use function dirname;
use function wp_logout;
use function class_exists;
use function preg_replace;
use function wp_set_current_user;

class CustomDirectivesTest extends WPTestCase
{
    
    protected string                 $blade_cache;
    protected string                 $blade_views;
    protected ViewEngine             $view_engine;
    protected ViewComposerCollection $composers;
    protected GlobalViewContext      $global_view_context;
    protected BladeStandalone        $blade;
    
    protected function setUp() :void
    {
        parent::setUp();
        
        if (class_exists(Facade::class)) {
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication(null);
        }
        
        if (class_exists(Container::class)) {
            Container::setInstance();
        }
        
        $this->blade_cache = dirname(__DIR__).'/fixtures/cache';
        $this->blade_views = dirname(__DIR__).'/fixtures/views';
        
        $this->composers = new ViewComposerCollection(
            null,
            $global_view_context = new GlobalViewContext()
        );
        $blade = new BladeStandalone($this->blade_cache, [$this->blade_views], $this->composers);
        $blade->boostrap();
        $this->blade = $blade;
        $this->view_engine = new ViewEngine($blade->getBladeViewFactory());
        $this->global_view_context = $global_view_context;
        
        $this->clearCache();
    }
    
    /** @test */
    public function custom_auth_user_directive_works()
    {
        $this->blade->bindWordPressDirectives(new ScopableWP());
        
        $user = $this->factory()->user->create_and_get();
        wp_set_current_user($user->ID);
        
        $view = $this->view('auth');
        $content = $view->toString();
        $this->assertViewContent('AUTHENTICATED', $content);
        
        wp_logout();
        
        $view = $this->view('auth');
        $content = $view->toString();
        $this->assertViewContent('', $content);
    }
    
    /** @test */
    public function custom_guest_user_directive_works()
    {
        $this->blade->bindWordPressDirectives(new ScopableWP());
        
        $view = $this->view('guest');
        $content = $view->toString();
        $this->assertViewContent('YOU ARE A GUEST', $content);
        
        wp_set_current_user($this->factory()->user->create_and_get()->ID);
        
        $view = $this->view('guest');
        $content = $view->toString();
        $this->assertViewContent('', $content);
    }
    
    /** @test */
    public function custom_wp_role_directives_work()
    {
        $this->blade->bindWordPressDirectives(new ScopableWP());
        
        $admin = $this->factory()->user->create_and_get(['role' => 'administrator']);
        wp_set_current_user($admin->ID);
        
        $view = $this->view('role');
        $content = $view->toString();
        $this->assertViewContent('ADMIN', $content);
        
        $editor = $this->factory()->user->create_and_get(['role' => 'editor']);
        wp_set_current_user($editor->ID);
        
        $view = $this->view('role');
        $content = $view->toString();
        $this->assertViewContent('EDITOR', $content);
        
        $author = $this->factory()->user->create_and_get(['role' => 'author']);
        wp_set_current_user($author->ID);
        
        $view = $this->view('role');
        $content = $view->toString();
        $this->assertViewContent('', $content);
    }
    
    protected function assertViewContent(string $expected, $actual)
    {
        $actual = ($actual instanceof View) ? $actual->toString() : $actual;
        
        $actual = preg_replace("/\r|\n|\t|\s{2,}/", '', $actual);
        
        PHPUnit::assertSame($expected, trim($actual), 'View not rendered correctly.');
    }
    
    private function view(string $view) :View
    {
        return $this->view_engine->make('blade-features.'.$view);
    }
    
    private function clearCache()
    {
        $files = Finder::create()->in([$this->blade_cache])->ignoreDotFiles(true);
        foreach ($files as $file) {
            unlink($file->getRealPath());
        }
    }
    
}