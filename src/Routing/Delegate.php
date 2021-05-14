<?php


    declare(strict_types = 1);


    namespace WPEmerge\Routing;

    use Psr\Http\Server\MiddlewareInterface;
    use Psr\Http\Server\RequestHandlerInterface;
    use Psr\Http\Message\ResponseInterface;
    use Psr\Http\Message\ServerRequestInterface;
    use WPEmerge\Exceptions\Exception;

    /**
     * PSR-15 delegate wrapper for internal callbacks generated by {@see Dispatcher} during dispatch.
     */
    class Delegate implements RequestHandlerInterface, MiddlewareInterface
    {

        /**
         * @var callable
         */
        private $callback;

        /**
         * @param  callable  $callback  function (RequestInterface $request) : ResponseInterface
         */
        public function __construct(callable $callback)
        {
            $this->callback = $callback;
        }

        /**
         * Dispatch the next available middleware and return the response.
         *
         * @param  ServerRequestInterface  $request
         *
         * @return ResponseInterface
         */
        public function handle(ServerRequestInterface $request) : ResponseInterface
        {

            try {

                return ($this->callback)($request);

            /** @todo Check why this gets called for every middleware in the stack.  */
            } catch (\Throwable $e ) {

                throw new \RuntimeException('Invalid Response returned from a middleware: '.
                    PHP_EOL . $e->getMessage()
                );

            }



        }

        /**
         * Dispatch the next available middleware and return the response.
         *
         * This method duplicates `handle()` to provide support for `callable` middleware.
         *
         * @param  ServerRequestInterface  $request
         *
         * @return ResponseInterface
         */
        public function __invoke(ServerRequestInterface $request) : ResponseInterface
        {

            return $this->handle($request);

        }

        public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
        {
            return $this->handle($request);

        }

    }