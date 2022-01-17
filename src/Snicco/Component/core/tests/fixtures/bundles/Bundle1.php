<?php

declare(strict_types=1);

namespace Snicco\Component\Core\Tests\fixtures\bundles;

use RuntimeException;
use Snicco\Component\Core\Bundle;
use Snicco\Component\Core\Environment;
use Snicco\Component\Core\Application;
use Snicco\Component\Core\Configuration\WritableConfig;

class Bundle1 implements Bundle
{
    
    private ?string $alias;
    
    public function __construct(string $alias = null)
    {
        $this->alias = $alias;
    }
    
    public function alias() :string
    {
        return $this->alias ?? 'bundle1';
    }
    
    public function configure(WritableConfig $config, Application $app) :void
    {
        if ( ! $app->usesBundle('bundle2')) {
            throw new RuntimeException(
                "The knowledge about bundle2 should be available in configure()"
            );
        }
        
        if ($config->has('bundle2.configured')) {
            throw new RuntimeException('bundle 2 configured first');
        }
        $config->set('bundle1.configured', true);
    }
    
    public function register(Application $app) :void
    {
        if (isset($app['bundle2.registered'])) {
            throw new RuntimeException('bundle 2 registered first');
        }
        if ( ! $app->config()->get('bundle2.configured')) {
            throw new RuntimeException("bundle1 was registered before bundle2 was configured.");
        }
        $app['bundle1.registered'] = true;
    }
    
    public function bootstrap(Application $app) :void
    {
        if (isset($app['bundle2.booted'])) {
            throw new RuntimeException('bundle 2 booted first');
        }
        if ( ! isset($app['bundle2.registered'])) {
            throw new RuntimeException('bundle1 was booted before bundle2 was registered.');
        }
        
        $app['bundle1.booted'] = true;
    }
    
    public function runsInEnvironments(Environment $env) :bool
    {
        return true;
    }
    
}