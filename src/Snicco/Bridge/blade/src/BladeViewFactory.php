<?php

declare(strict_types=1);

namespace Snicco\Bridge\Blade;

use Illuminate\Support\Str;
use Illuminate\View\ViewName;
use InvalidArgumentException;
use Snicco\Component\Templating\View\View;
use Illuminate\View\Factory as IlluminateViewFactory;
use Snicco\Component\Templating\Exception\ViewNotFound;
use Snicco\Component\Templating\ViewFactory\ViewFactory;

use function is_file;

/**
 * @api
 */
final class BladeViewFactory implements ViewFactory
{
    
    private IlluminateViewFactory $view_factory;
    
    private array $view_directories;
    
    public function __construct(IlluminateViewFactory $view_factory, array $view_directories)
    {
        $this->view_factory = $view_factory;
        $this->view_directories = $view_directories;
    }
    
    /**
     * @interal
     * @throws ViewNotFound
     */
    public function make(string $view) :View
    {
        try {
            $view = $this->view_factory->first(
                [$this->normalizeNames($view)]
            );
            
            return new BladeView($view);
        } catch (InvalidArgumentException $e) {
            throw new ViewNotFound($e->getMessage(), $e->getCode(), $e);
        }
    }
    
    private function normalizeNames(string $name) :string
    {
        if ( ! is_file($name)) {
            return $name;
        }
        
        $name = $this->convertAbsolutePathToName($name);
        
        return ViewName::normalize($name);
    }
    
    // We need to do this because Blade only supports views by name relative to one of the view directories.
    private function convertAbsolutePathToName($path) :string
    {
        foreach ($this->view_directories as $view_directory) {
            if (Str::startsWith($path, $view_directory)) {
                return (string) Str::of($path)
                                   ->after($view_directory)
                                   ->replace('/', '.')
                                   ->ltrim('.')
                                   ->before('.blade');
            }
        }
        
        return $path;
    }
    
}