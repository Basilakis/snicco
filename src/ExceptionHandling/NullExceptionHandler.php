<?php

declare(strict_types=1);

namespace Snicco\ExceptionHandling;

use Throwable;
use Snicco\Http\Psr7\Request;
use Snicco\Http\Psr7\Response;
use Snicco\Contracts\ExceptionHandler;

class NullExceptionHandler implements ExceptionHandler
{
    
    public function report(Throwable $e, Request $request)
    {
        //
    }
    
    public function toHttpResponse(Throwable $e, Request $request) :Response
    {
        throw $e;
    }
    
}