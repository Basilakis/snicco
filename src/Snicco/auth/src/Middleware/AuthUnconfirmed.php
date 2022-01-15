<?php

declare(strict_types=1);

namespace Snicco\Auth\Middleware;

use Psr\Http\Message\ResponseInterface;
use Snicco\Component\HttpRouting\Http\Psr7\Request;
use Snicco\Component\HttpRouting\Middleware\Delegate;
use Snicco\Component\HttpRouting\Http\AbstractMiddleware;
use Snicco\Component\HttpRouting\Routing\UrlGenerator\InternalUrlGenerator;

class AuthUnconfirmed extends AbstractMiddleware
{
    
    private InternalUrlGenerator $url;
    
    public function __construct(InternalUrlGenerator $url)
    {
        $this->url = $url;
    }
    
    public function handle(Request $request, Delegate $next) :ResponseInterface
    {
        $session = $request->session();
        
        if ($session->hasValidAuthConfirmToken()) {
            return $this->response_factory->redirect()->back(
                302,
                $this->url->toRoute('dashboard')
            );
        }
        
        return $next($request);
    }
    
}