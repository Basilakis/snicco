<?php

declare(strict_types=1);

namespace Snicco\Core\Middleware\Core;

use Snicco\Core\Http\Delegate;
use Snicco\Core\Http\Pipeline;
use Snicco\Core\Http\Psr7\Request;
use Snicco\Core\Http\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Snicco\Core\Shared\ContainerAdapter;
use Snicco\Core\Middleware\MiddlewareStack;
use Snicco\Core\Contracts\AbstractMiddleware;
use Snicco\Core\Routing\Internal\ControllerAction;

class RouteRunner extends AbstractMiddleware
{
    
    private Pipeline         $pipeline;
    private MiddlewareStack  $middleware_stack;
    private ContainerAdapter $container;
    
    public function __construct(Pipeline $pipeline, MiddlewareStack $middleware_stack, ContainerAdapter $container)
    {
        $this->pipeline = $pipeline;
        $this->middleware_stack = $middleware_stack;
        $this->container = $container;
    }
    
    public function handle(Request $request, Delegate $next) :ResponseInterface
    {
        $result = $request->routingResult();
        $route = $result->route();
        
        if ( ! $route) {
            return $this->delegateToWordPress($request);
        }
        
        $action = new ControllerAction(
            $route->getController(),
            $this->container,
        );
        
        $route_middleware = array_merge($route->getMiddleware(), $action->getMiddleware());
        
        $middleware = $this->middleware_stack->createWithRouteMiddleware($route_middleware);
        
        return $this->pipeline
            ->send($request)
            ->through($middleware)
            ->then(function (Request $request) use ($result, $action) {
                $response = $action->execute(
                    array_merge(
                        [$request],
                        $result->decodedSegments(),
                        $result->route()->getDefaults()
                    )
                );
                
                return $this->respond()->toResponse($response);
            });
    }
    
    private function delegateToWordPress(Request $request) :Response
    {
        $middleware = $this->middleware_stack->createForRequestWithoutRoute($request);
        
        if ( ! count($middleware)) {
            return $this->respond()->delegateToWP();
        }
        
        return $this->pipeline
            ->send($request)
            ->through($middleware)
            ->then(function () {
                return $this->respond()->delegateToWP();
            });
    }
    
}