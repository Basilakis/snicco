<?php

declare(strict_types=1);

namespace Snicco\Middleware\OpenRedirectProtection;

use Psr\Http\Message\ResponseInterface;
use Snicco\Component\HttpRouting\AbstractMiddleware;
use Snicco\Component\HttpRouting\Http\Psr7\Request;
use Snicco\Component\HttpRouting\Http\Response\RedirectResponse;
use Snicco\Component\HttpRouting\NextMiddleware;
use Snicco\Component\HttpRouting\Routing\Exception\RouteNotFound;
use Snicco\Component\StrArr\Str;

/**
 * @todo Its currently possible to redirect to a whitelisted host from an external referer.
 * @todo It this a problem tho? Neither rails nor symfony have this feature.
 *       The only way we could prevent this is to sign all outgoing urls with a HMAC and then strip
 *       that from the query string before the redirect.
 */
final class OpenRedirectProtection extends AbstractMiddleware
{

    private string $route;

    private array $whitelist;

    private string $host;

    public function __construct(string $host, $whitelist = [], $route = 'redirect.protection')
    {
        $this->route = $route;
        $this->whitelist = $this->formatWhiteList($whitelist);
        $this->host = $host;
        $this->whitelist[] = $this->allSubdomainsOfApplication();
    }

    private function formatWhiteList(array $whitelist): array
    {
        return array_map(function ($pattern) {
            if (Str::startsWith($pattern, '*.')) {
                return $this->allSubdomains(Str::afterFirst($pattern, '*.'));
            }

            return '/' . preg_quote($pattern, '/') . '/';
        }, $whitelist);
    }

    private function allSubdomains(string $host): string
    {
        return '/^(.+\.)?' . preg_quote($host, '/') . '$/';
    }

    private function allSubdomainsOfApplication(): ?string
    {
        if ($host = parse_url($this->host, PHP_URL_HOST)) {
            return $this->allSubdomains($host);
        }

        return null;
    }

    public function handle(Request $request, NextMiddleware $next): ResponseInterface
    {
        $response = $next($request);

        if (!$response->isRedirect()) {
            return $response;
        }

        if ($response instanceof RedirectResponse && $response->externalRedirectAllowed()) {
            return $response;
        }

        $target = $response->getHeaderLine('location');

        $target_host = parse_url($target, PHP_URL_HOST);
        $is_same_site = $this->isSameSiteRedirect($request, $target);

        // Always allow relative redirects
        if ($is_same_site) {
            return $response;
        }

        // Only allow redirects away to whitelisted hosts.
        if ($this->isWhitelisted($target_host)) {
            return $response;
        }

        return $this->forbiddenRedirect($target);
    }

    private function isSameSiteRedirect(Request $request, string $location): bool
    {
        $parsed = parse_url($location);
        $target = $parsed['host'] ?? null;

        if (!$target && $parsed['path']) {
            return true;
        }

        return $request->getUri()->getHost() === $target;
    }

    private function isWhitelisted(string $host): bool
    {
        if (in_array($host, $this->whitelist, true)) {
            return true;
        }

        foreach ($this->whitelist as $pattern) {
            if (preg_match($pattern, $host)) {
                return true;
            }
        }

        return false;
    }

    private function forbiddenRedirect(string $location): RedirectResponse
    {
        try {
            return $this->redirect()
                ->toRoute($this->route, ['intended_redirect' => $location]);
        } catch (RouteNotFound $e) {
            return $this->redirect()->home(['intended_redirect' => $location]);
        }
    }

}