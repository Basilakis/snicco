<?php

declare(strict_types=1);

namespace Snicco\HttpRouting\Middleware;

use Psr\Http\Message\ResponseInterface;
use Snicco\HttpRouting\Http\Psr7\Request;
use Snicco\HttpRouting\Http\AbstractMiddleware;
use Snicco\HttpRouting\Http\Responses\DelegatedResponse;
use Snicco\Component\Core\ExceptionHandling\Exceptions\NotFoundException;

/**
 * @api
 */
final class MustMatchRoute extends AbstractMiddleware
{
    
    /**
     * @throws NotFoundException
     */
    public function handle(Request $request, Delegate $next) :ResponseInterface
    {
        $response = $next($request);
        
        if ($response instanceof DelegatedResponse) {
            throw new NotFoundException(
                "A delegated response was returned for request [{$request->fullRequestTarget()}]."
            );
        }
        
        return $response;
    }
    
}