<?php

declare(strict_types=1);

namespace Snicco\HttpRouting\Middleware\Internal;

use Closure;
use Throwable;
use LogicException;
use Webmozart\Assert\Assert;
use Snicco\Component\StrArr\Arr;
use Psr\Http\Message\ResponseInterface;
use Snicco\HttpRouting\Http\Psr7\Request;
use Snicco\HttpRouting\Http\Psr7\Response;
use Snicco\HttpRouting\Middleware\Delegate;
use Snicco\Component\Core\ExceptionHandling\ExceptionHandler;

use function is_string;
use function array_map;
use function strtolower;

/**
 * @interal
 */
final class MiddlewarePipeline
{
    
    private ExceptionHandler  $error_handler;
    private MiddlewareFactory $middleware_factory;
    
    /**
     * @var MiddlewareBlueprint[]
     */
    private array   $middleware = [];
    private Request $current_request;
    private Closure $request_handler;
    private bool    $exhausted  = false;
    
    public function __construct(MiddlewareFactory $middleware_factory, ExceptionHandler $error_handler)
    {
        $this->middleware_factory = $middleware_factory;
        $this->error_handler = $error_handler;
    }
    
    public function send(Request $request) :MiddlewarePipeline
    {
        $new = clone $this;
        $new->current_request = $request;
        return $new;
    }
    
    /**
     * @param  MiddlewareBlueprint|MiddlewareBlueprint[]  $middleware
     */
    public function through($middleware) :MiddlewarePipeline
    {
        $new = clone $this;
        $middleware = Arr::toArray($middleware);
        Assert::allIsInstanceOf($middleware, MiddlewareBlueprint::class);
        $new->middleware = $middleware;
        return $new;
    }
    
    public function then(Closure $request_handler) :Response
    {
        $this->request_handler = $request_handler;
        
        return $this->run();
    }
    
    private function run() :Response
    {
        if ( ! isset($this->current_request)) {
            throw new LogicException(
                "You cant run a middleware pipeline twice without calling send() first."
            );
        }
        
        $stack = $this->buildMiddlewareStack();
        
        $response = $stack($this->current_request);
        unset($this->current_request);
        return $response;
    }
    
    private function buildMiddlewareStack() :Delegate
    {
        return $this->nextMiddleware();
    }
    
    private function nextMiddleware() :Delegate
    {
        if ($this->exhausted) {
            throw new LogicException("The middleware pipeline is exhausted.");
        }
        
        if ($this->middleware === []) {
            $this->exhausted = true;
            
            return new Delegate(function (Request $request) {
                try {
                    return call_user_func($this->request_handler, $request);
                } catch (Throwable $e) {
                    return $this->exceptionToHttpResponse($e, $request);
                }
            });
        }
        
        return new Delegate(function (Request $request) {
            try {
                return $this->runNextMiddleware($request);
            } catch (Throwable $e) {
                return $this->exceptionToHttpResponse($e, $request);
            }
        });
    }
    
    private function runNextMiddleware(Request $request) :ResponseInterface
    {
        /** @var MiddlewareBlueprint $middleware_blueprint */
        $middleware_blueprint = array_shift($this->middleware);
        
        $middleware_instance = $this->middleware_factory->create(
            $middleware_blueprint->class(),
            $this->convertStrings($middleware_blueprint->arguments())
        );
        
        return $middleware_instance->process($request, $this->nextMiddleware());
    }
    
    private function convertStrings(array $constructor_args) :array
    {
        return array_map(function ($value) {
            if ( ! is_string($value)) {
                return $value;
            }
            
            if (strtolower($value) === 'true') {
                return true;
            }
            if (strtolower($value) === 'false') {
                return false;
            }
            
            if (is_numeric($value)) {
                return intval($value);
            }
            
            return $value;
        }, $constructor_args);
    }
    
    private function exceptionToHttpResponse(Throwable $e, Request $request) :Response
    {
        $this->error_handler->report($e, $request);
        
        return $this->error_handler->toHttpResponse($e, $request);
    }
    
}