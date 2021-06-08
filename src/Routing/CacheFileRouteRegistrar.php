<?php


    declare(strict_types = 1);


    namespace WPEmerge\Routing;

    use Symfony\Component\Finder\Finder;
    use WPEmerge\Application\ApplicationConfig;
    use WPEmerge\Contracts\RouteRegistrarInterface;
    use WPEmerge\ExceptionHandling\Exceptions\ConfigurationException;

    class CacheFileRouteRegistrar implements RouteRegistrarInterface
    {

        /**
         * @var RouteRegistrar
         */
        private $registrar;

        public function __construct(RouteRegistrar $registrar)
        {
            $this->registrar = $registrar;
        }

        public function globalRoutes(ApplicationConfig $config) : bool
        {
            $dir = $config->get('routing.cache_dir', '');

            if ($this->cacheFilesCreates($dir)) {
                return true;
            } else {
                $this->clearRouteCache($dir);
            }

            $this->createCacheDirIfNotExists($dir);

            return $this->registrar->globalRoutes($config);

        }

        public function standardRoutes(ApplicationConfig $config)
        {

            $dir = $config->get('routing.cache_dir', '');

            if ($this->cacheFilesCreates($dir)) {
                return;
            } else {
                $this->clearRouteCache($dir);
            }

            $this->createCacheDirIfNotExists($dir);
            $this->registrar->standardRoutes($config);

        }

        public function loadIntoRouter() : void
        {
            // This will do nothing for the CachedRouteCollection if the cache file exists.
            $this->registrar->loadIntoRouter();

        }

        private function createCacheDirIfNotExists (string $dir) {


            if ($dir === '') {
                throw new ConfigurationException("Route caching is enabled but no cache dir was provided.");
            }

            if ( ! is_dir($dir) ) {

                wp_mkdir_p($dir);

            }

        }

        private function cacheFilesCreates($dir) :bool {

            return is_file($dir . DIRECTORY_SEPARATOR . '__generated_route_map') &&
                   is_file($dir . DIRECTORY_SEPARATOR . '__generated_route_collection');


        }

        private function clearRouteCache(string $dir)
        {

            if ( ! is_dir( $dir ) ) {
                return;
            }

            $finder = new Finder();
            $finder->in($dir);

            if (iterator_count($finder) === 0) {
                rmdir($dir);
            }

            foreach ($finder as $file) {

                $path = $file->getRealPath();
                unlink($path);

            }

            rmdir($dir);

        }


    }