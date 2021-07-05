<?php


    declare(strict_types = 1);


    namespace BetterWP\Testing\Concerns;

    use BetterWP\Http\ResponseEmitter;
    use BetterWP\Testing\TestResponseEmitter;

    trait InteractsWithContainer
    {


        /**
         * Register an instance of an object in the container.
         *
         * @param  string  $abstract
         * @param  object  $instance
         * @return object
         */
        protected function swap($abstract, $instance)
        {
            return $this->instance($abstract, $instance);
        }

        /**
         * Register an instance of an object in the container.
         *
         * @param  string  $abstract
         * @param  object  $instance
         * @return object
         */
        protected function instance($abstract, $instance)
        {
            $this->app->container()->instance($abstract, $instance);

            return $instance;
        }

        private function replaceBindings()
        {

            $this->swap(ResponseEmitter::class, new TestResponseEmitter());

        }


    }