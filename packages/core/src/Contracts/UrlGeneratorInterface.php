<?php

declare(strict_types=1);

namespace Snicco\Core\Contracts;

use Snicco\Core\ExceptionHandling\Exceptions\RouteNotFound;
use Snicco\Core\ExceptionHandling\Exceptions\BadRouteParameter;

/**
 * Implementations MUST respect a global, site wide configuration for the use of trailing slashes.
 *
 * @api
 */
interface UrlGeneratorInterface
{
    
    /**
     * Generate an absolute URL, e.g. 'https://example.com/foo/bar'.
     */
    const ABSOLUTE_URL = 0;
    
    /**
     * Generate an absolute path, e.g. '/foo/bar'.
     */
    const ABSOLUTE_PATH = 1;
    
    /**
     * @param  string  $path  The path MUST NOT be urlencoded.
     * @param  array<string,string|int>  $extra  The query arguments to append.
     * A "_fragment" key can be passed to include a fragment after the query string.
     * @param  int  $type
     * @param  bool|null  $secure  If null is passed the scheme of the current request will be used.
     *
     * @return string an rfc-compliant url
     */
    public function to(string $path, array $extra = [], int $type = self::ABSOLUTE_PATH, ?bool $secure = null) :string;
    
    /**
     * @throws RouteNotFound
     * @throws BadRouteParameter
     */
    public function toRoute(string $name, array $arguments = [], int $type = self::ABSOLUTE_PATH, ?bool $secure = null) :string;
    
    /**
     * Generates a secure, absolute URL to the provided path.
     */
    public function secure(string $path, array $extra = []) :string;
    
    /**
     * Returns the canonical url for the current request.
     * i.e: current request: https://foo.com/foo?bar=baz
     * => https://foo.com/foo
     */
    public function canonical() :string;
    
    /**
     * The full current uri as a string including query, fragment etc.
     * Returns an absolute URL.
     */
    public function full() :string;
    
    /**
     * Get the previous URL based on the referer headers, including query string and fragment.
     * Returns an absolute URL.
     */
    public function previous(string $fallback = '/') :string;
    
}