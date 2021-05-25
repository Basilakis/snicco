<?php


    declare(strict_types = 1);


    namespace Tests\unit\Routing;

    use Contracts\ContainerAdapter;
    use Mockery;
    use Tests\stubs\TestRequest;
    use Tests\traits\CreateDefaultWpApiMocks;
    use Tests\traits\TestHelpers;
    use Tests\UnitTest;
    use WPEmerge\Application\ApplicationEvent;
    use WPEmerge\Events\IncomingWebRequest;
    use WPEmerge\Facade\WP;
    use WPEmerge\Routing\Router;

    class RouteRegistrationTest extends UnitTest
    {

        use TestHelpers;
        use CreateDefaultWpApiMocks;

        /**
         * @var ContainerAdapter
         */
        private $container;

        /** @var Router */
        private $router;

        protected function beforeTestRun()
        {

            $this->container = $this->createContainer();
            $this->routes = $this->newRouteCollection();
            ApplicationEvent::make($this->container);
            ApplicationEvent::fake();
            WP::setFacadeContainer($this->container);

        }

        protected function beforeTearDown()
        {

            ApplicationEvent::setInstance(null);
            Mockery::close();
            WP::reset();

        }

        /** @test */
        public function routes_can_be_defined_without_leading_slash()
        {

            $this->createRoutes(function () {

                $this->router->get('foo', function () {

                    return 'FOO';

                });


            });

            $request = new IncomingWebRequest('wp.php',TestRequest::fromFullUrl('GET', 'https://foobar.com/foo'));
            $this->runAndAssertOutput('FOO', $request);

        }

        /** @test */
        public function routes_can_be_defined_with_leading_slash()
        {

            $this->createRoutes(function () {

                $this->router->get('/foo', function () {

                    return 'FOO';

                });

            });


            $request = TestRequest::fromFullUrl('GET', 'https://foobar.com/foo');
            $this->runAndAssertOutput('FOO', new IncomingWebRequest('wp.php', $request));

        }

        /** @test */
        public function routes_without_trailing_slash_dont_match_request_with_trailing_slash()
        {

            $this->createRoutes(function () {

                $this->router->get('/foo', function () {

                    return 'FOO';

                });

            });


            $request = TestRequest::fromFullUrl('GET', 'https://foobar.com/foo/');
            $this->runAndAssertEmptyOutput(new IncomingWebRequest('wp.php', $request));

            $request = TestRequest::fromFullUrl('GET', 'https://foobar.com/foo');
            $this->runAndAssertOutput('FOO', new IncomingWebRequest('wp.php', $request));

        }

        /** @test */
        public function routes_with_trailing_slash_match_request_with_trailing_slash()
        {

            $this->createRoutes(function () {

                $this->router->get('/foo/', function () {

                    return 'FOO';

                });

            });


            $request = TestRequest::fromFullUrl('GET', 'https://foobar.com/foo/');
            $this->runAndAssertOutput('FOO', new IncomingWebRequest('wp.php', $request));

            $request = TestRequest::fromFullUrl('GET', 'https://foobar.com/foo');
            $this->runAndAssertEmptyOutput(new IncomingWebRequest('wp.php', $request));

        }

        /** @test */
        public function routes_with_trailing_slash_match_request_with_trailing_slash_when_inside_a_group()
        {

            $this->createRoutes(function () {

                $this->router->name('foo')->group(function () {

                    $this->router->get('/foo/', function () {

                        return 'FOO';

                    });

                    $this->router->prefix('bar')->group(function () {

                        $this->router->post('/foo/', function () {

                            return 'FOO';

                        });

                    });

                });

            });


            $request = TestRequest::fromFullUrl('GET', 'https://foobar.com/foo/');
            $this->runAndAssertOutput('FOO', new IncomingWebRequest('wp.php', $request));

            $request = TestRequest::fromFullUrl('GET', 'https://foobar.com/foo');
            $this->runAndAssertEmptyOutput(new IncomingWebRequest('wp.php', $request));

            $request = TestRequest::fromFullUrl('POST', 'https://foobar.com/bar/foo/');
            $this->runAndAssertOutput('FOO', new IncomingWebRequest('wp.php', $request));

            $request = TestRequest::fromFullUrl('POST', 'https://foobar.com/bar/foo');
            $this->runAndAssertEmptyOutput(new IncomingWebRequest('wp.php', $request));


        }


    }