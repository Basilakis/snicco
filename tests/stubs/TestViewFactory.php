<?php

declare(strict_types=1);

namespace Tests\stubs;

use Snicco\Contracts\ViewInterface;
use Snicco\Contracts\ViewFactoryInterface;

class TestViewFactory implements ViewFactoryInterface
{
    
    public function compose(ViewInterface $view)
    {
        //
    }
    
    public function make($views) :ViewInterface
    {
        
        $view = is_array($views) ? $views[0] : $views;
        
        return new TestView($view);
        
    }
    
    public function render($views, array $context = []) :string
    {
        $view = $this->make($views);
        $view->with($context);
        return $view->toString();
    }
    
}