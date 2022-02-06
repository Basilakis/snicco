<?php

declare(strict_types=1);

namespace Snicco\Component\HttpRouting\Tests;

use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Snicco\Component\HttpRouting\Http\FileTemplateRenderer;
use Snicco\Component\HttpRouting\Http\MethodOverride;
use Snicco\Component\HttpRouting\Http\NegotiateContent;
use Snicco\Component\HttpRouting\Http\Psr7\Request;
use Snicco\Component\HttpRouting\Http\Psr7\ResponseFactory;
use Snicco\Component\HttpRouting\Http\Redirector;
use Snicco\Component\HttpRouting\Http\ResponsePreparation;
use Snicco\Component\HttpRouting\HttpKernel;
use Snicco\Component\HttpRouting\KernelMiddleware;
use Snicco\Component\HttpRouting\MiddlewarePipeline;
use Snicco\Component\HttpRouting\MiddlewareStack;
use Snicco\Component\HttpRouting\PrepareResponse;
use Snicco\Component\HttpRouting\RouteRunner;
use Snicco\Component\HttpRouting\Routing\AdminDashboard\AdminArea;
use Snicco\Component\HttpRouting\Routing\AdminDashboard\WPAdminArea;
use Snicco\Component\HttpRouting\Routing\Condition\RouteConditionFactory;
use Snicco\Component\HttpRouting\Routing\Controller\FallBackController;
use Snicco\Component\HttpRouting\Routing\Controller\RedirectController;
use Snicco\Component\HttpRouting\Routing\Controller\ViewController;
use Snicco\Component\HttpRouting\Routing\Router;
use Snicco\Component\HttpRouting\Routing\RoutingConfigurator\AdminRoutingConfigurator;
use Snicco\Component\HttpRouting\Routing\RoutingConfigurator\RoutingConfigurator;
use Snicco\Component\HttpRouting\Routing\RoutingConfigurator\RoutingConfiguratorUsingRouter;
use Snicco\Component\HttpRouting\Routing\RoutingConfigurator\WebRoutingConfigurator;
use Snicco\Component\HttpRouting\Routing\UrlGenerator\RFC3986Encoder;
use Snicco\Component\HttpRouting\Routing\UrlGenerator\UrlGenerationContext;
use Snicco\Component\HttpRouting\Routing\UrlGenerator\UrlGeneratorFactory;
use Snicco\Component\HttpRouting\Routing\UrlGenerator\UrlGeneratorInterface;
use Snicco\Component\HttpRouting\RoutingMiddleware;
use Snicco\Component\HttpRouting\Testing\AssertableResponse;
use Snicco\Component\HttpRouting\Testing\CreatesPsrRequests;
use Snicco\Component\HttpRouting\Tests\fixtures\BarMiddleware;
use Snicco\Component\HttpRouting\Tests\fixtures\BazMiddleware;
use Snicco\Component\HttpRouting\Tests\fixtures\Controller\RoutingTestController;
use Snicco\Component\HttpRouting\Tests\fixtures\FoobarMiddleware;
use Snicco\Component\HttpRouting\Tests\fixtures\FooMiddleware;
use Snicco\Component\HttpRouting\Tests\fixtures\NullErrorHandler;
use Snicco\Component\HttpRouting\Tests\helpers\CreateHttpErrorHandler;
use Snicco\Component\HttpRouting\Tests\helpers\CreateTestPsr17Factories;
use Snicco\Component\HttpRouting\Tests\helpers\CreateTestPsrContainer;
use Snicco\Component\Kernel\DIContainer;
use Snicco\Component\Kernel\ValueObject\PHPCacheFile;
use Snicco\Component\Psr7ErrorHandler\HttpErrorHandlerInterface;

/**
 * @interal
 */
class HttpRunnerTestCase extends TestCase
{

    use CreateTestPsr17Factories;
    use CreatesPsrRequests;
    use CreateTestPsrContainer;
    use CreateHttpErrorHandler;

    const CONTROLLER_NAMESPACE = 'Snicco\\Component\\HttpRouting\\Tests\\fixtures\\Controller';

    protected string $app_domain = 'foobar.com';
    protected string $routes_dir;
    protected DIContainer $container;
    protected UrlGeneratorInterface $generator;

    private Router $router;
    private HttpKernel $kernel;
    private AdminArea $admin_area;
    private UrlGenerationContext $request_context;
    private MiddlewareStack $middleware_stack;
    private RoutingConfiguratorUsingRouter $routing_configurator;
    private HttpErrorHandlerInterface $error_handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createNeededCollaborators();
        $this->container[RoutingTestController::class] = new RoutingTestController();
        $this->routes_dir = __DIR__ . '/fixtures/routes';
    }

    final protected function refreshRouter(
        PHPCacheFile $cache_file = null,
        UrlGenerationContext $context = null,
        array $config = []
    ): void {
        unset($this->container[ResponseFactory::class]);
        unset($this->container[UrlGeneratorInterface::class]);
        unset($this->container[Redirector::class]);

        if (is_null($context)) {
            $context = $this->request_context ?? UrlGenerationContext::forConsole(
                    $this->app_domain,
                );
        }

        $this->request_context = $context;

        $this->admin_area ??= WPAdminArea::fromDefaults();

        $this->router = new Router(
            new RouteConditionFactory($this->container),
            new UrlGeneratorFactory(
                $context,
                $this->admin_area,
                new RFC3986Encoder(),
            ),
            $this->admin_area,
            $cache_file
        );

        $this->routing_configurator = new RoutingConfiguratorUsingRouter(
            $this->router,
            $this->admin_area->urlPrefix(),
            $config
        );

        $this->generator = $this->router;
        $this->container->instance(UrlGeneratorInterface::class, $this->router);
        $rf = $this->createResponseFactory($this->generator);
        $this->container->instance(ResponseFactory::class, $rf);
        $this->container->instance(Redirector::class, $rf);
        $this->container->instance(StreamFactoryInterface::class, $rf);

        $this->kernel = $this->createKernel();
    }

    final protected function assertEmptyBody(Request $request): void
    {
        $this->assertResponseBody('', $request);
    }

    final protected function assertResponseBody(string $expected, Request $request): void
    {
        $response = $this->runKernel($request);
        $this->assertSame(
            $expected,
            $b = $response->body(),
            "Expected response body [$expected] for path [{$request->path()}].\nGot [$b]."
        );
    }

    final protected function runKernel(Request $request): AssertableResponse
    {
        $this->withMiddlewareAlias($this->defaultMiddlewareAliases());

        $response = $this->kernel->handle($request);
        return new AssertableResponse($response);
    }

    final protected function withMiddlewareAlias(array $aliases): void
    {
        $this->middleware_stack->middlewareAliases($aliases);
    }

    final protected function withGlobalMiddleware(array $middleware): void
    {
        $this->withMiddlewareGroups([RoutingConfigurator::GLOBAL_MIDDLEWARE => $middleware]);
    }

    /**
     * @param array<string,array<string>> $middlewares
     */
    final protected function withMiddlewareGroups(array $middlewares): void
    {
        foreach ($middlewares as $name => $middleware) {
            $this->middleware_stack->withMiddlewareGroup($name, $middleware);
        }
    }

    final protected function withNewMiddlewareStack(MiddlewareStack $middleware_stack): void
    {
        $this->middleware_stack = $middleware_stack;
        $this->refreshRouter();
    }

    final protected function withMiddlewarePriority(array $array): void
    {
        $this->middleware_stack->middlewarePriority($array);
    }

    final protected function routeConfigurator(): WebRoutingConfigurator
    {
        if (!$this->routing_configurator instanceof WebRoutingConfigurator) {
            throw new LogicException('$routing_configurator does not implement WebRoutingConfigurator');
        }
        return $this->routing_configurator;
    }

    final protected function adminRouteConfigurator(): AdminRoutingConfigurator
    {
        if (!$this->routing_configurator instanceof AdminRoutingConfigurator) {
            throw new LogicException('$routing_configurator does not implement AdminRoutingConfigurator');
        }
        return $this->routing_configurator;
    }

    final protected function refreshUrlGenerator(UrlGenerationContext $context = null): UrlGeneratorInterface
    {
        $this->refreshRouter(null, $context);
        return $this->generator;
    }

    protected function baseUrl(): string
    {
        return 'https://' . $this->app_domain;
    }

    protected function adminArea(): AdminArea
    {
        return $this->admin_area;
    }

    protected function urlGenerator(): UrlGeneratorInterface
    {
        return $this->generator;
    }

    private function createNeededCollaborators(): void
    {
        $this->container = $this->createContainer();
        $this->container->instance(DIContainer::class, $this->container);

        $this->admin_area = WPAdminArea::fromDefaults();
        $this->container[AdminArea::class] = $this->admin_area;

        $this->error_handler = new NullErrorHandler();
        $this->middleware_stack = new MiddlewareStack();

        $this->refreshRouter();

        // internal controllers
        $this->container->instance(FallBackController::class, new FallBackController());
        $this->container->instance(
            ViewController::class,
            new ViewController(new FileTemplateRenderer())
        );
        $this->container->instance(RedirectController::class, new RedirectController());
    }

    private function createKernel(): HttpKernel
    {
        return new HttpKernel(
            $this->createKernelMiddleware(),
            new MiddlewarePipeline(
                $this->container,
                $this->error_handler,
            ),
            new class implements EventDispatcherInterface {

                public function dispatch(object $event): void
                {
                    //
                }

            }
        );
    }

    private function createKernelMiddleware(): KernelMiddleware
    {
        return new KernelMiddleware(
            new NegotiateContent(['en']),
            new PrepareResponse(new ResponsePreparation($this->psrStreamFactory())),
            new MethodOverride(),
            new RoutingMiddleware(
                $this->router,
            ),
            new RouteRunner(
                new MiddlewarePipeline(
                    $this->container,
                    $this->error_handler,
                ),
                $this->middleware_stack,
                $this->container,
            )
        );
    }

    private function defaultMiddlewareAliases(): array
    {
        return [
            'foo' => FooMiddleware::class,
            'bar' => BarMiddleware::class,
            'baz' => BazMiddleware::class,
            'foobar' => FoobarMiddleware::class,
        ];
    }

}