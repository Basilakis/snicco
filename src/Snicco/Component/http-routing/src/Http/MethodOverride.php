<?php

declare(strict_types=1);

namespace Snicco\Component\HttpRouting\Http;

use Psr\Http\Message\ResponseInterface;
use Snicco\Component\HttpRouting\AbstractMiddleware;
use Snicco\Component\HttpRouting\Http\Psr7\Request;
use Snicco\Component\HttpRouting\NextMiddleware;

use function in_array;
use function is_string;
use function strtoupper;

/**
 * @api
 */
final class MethodOverride extends AbstractMiddleware
{

    const HEADER = 'X-HTTP-Method-Override';

    /**
     * @psalm-suppress MixedAssignment
     */
    public function handle(Request $request, NextMiddleware $next): ResponseInterface
    {
        if ('POST' !== $request->realMethod()) {
            return $next($request);
        }

        if ($request->hasHeader(self::HEADER)) {
            $method = $request->getHeaderLine(self::HEADER);
        } elseif (is_string($m = $request->post('_method'))) {
            $method = $m;
        } else {
            return $next($request);
        }

        if (!$this->validMethod($method)) {
            return $next($request);
        }

        $request = $request->withMethod($method);

        return $next($request);
    }

    private function validMethod(string $method): bool
    {
        $valid = ['PUT', 'PATCH', 'DELETE'];

        $method = strtoupper($method);

        return in_array($method, $valid, true);
    }

}