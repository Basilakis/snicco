<?php


	declare( strict_types = 1 );


	namespace WPEmerge\Application;

	use Contracts\ContainerAdapter;
    use WPEmerge\Http\Cookies;
    use WPEmerge\Http\ResponseFactory;
	use WPEmerge\Routing\Route;
	use WPEmerge\Routing\Router;
    use WPEmerge\Routing\UrlGenerator;
	use WPEmerge\Contracts\ViewInterface;
    use WPEmerge\Session\SessionStore;
    use WPEmerge\View\GlobalContext;

    /**
	 * Can be applied to your App class via a "@mixin" annotation for better IDE support.
	 * This class is not meant to be used in any other capacity.
	 *
	 * @codeCoverageIgnore
	 */
	final class ApplicationMixin {

		/**
		 * Prevent class instantiation.
		 */
		private function __construct() {
		}

		/**
		 *
		 * Resolve an item from the applications config.
		 *
		 * @param  string  $key
		 * @param $default
		 *
		 * @return mixed
		 */
		public static function config( string $key, $default = null ) {
		}

        /**
         * Return the application session store.
         * @return SessionStore
         */
        public static function session() : SessionStore
        {}

        /**
         * Creates hidden csrf input fields based on the current user session.
         * If a csrf token is present in the session its used if not a new hash is created and saved
         * in the session.
         *
         * Does NOT echo the output but returns the html as a string.
         *
         * @return string
         */
        public static function csrfField() :string
        {


        }

		/**
		 * Bootstrap the application.
		 *
		 * @param  array  $config
		 * @param  boolean  $run
		 *
		 * @return void
         * @see Application::boot()
		 */
		public static function bootstrap( array $config = [], $run = true ) {
		}

		/**
		 * Get the IoC container instance.
		 *
		 * @return \Contracts\ContainerAdapter
		 */
		public static function container() : ContainerAdapter {
		}

		/**
		 * Set the IoC container instance.
		 *
		 * @param  ContainerAdapter  $container
		 *
		 * @return void
		 */
		public static function setContainer( ContainerAdapter $container ) {
		}

		/**
		 * Resolve a dependency from the IoC container.
		 *
		 * @param  string  $key
		 *
		 * @return mixed|null
         * @see HasContainer::resolve()
		 */
		public static function resolve( string $key ) {
		}

		// --- Aliases --------------------------------------- //

		/**
		 * Get the Application instance.
		 *
		 * @return Application
		 */
		public static function app() : Application {
		}

		/**
		 * Returns a response factory instance.
		 *
		 * @return  \WPEmerge\Http\ResponseFactory
		 * @see \WPEmerge\Http\ResponseFactory
		 */
		public static function response() : ResponseFactory {}

		/**
		 * Create a view
		 *
		 * @param  string|string[]  $views
		 *
		 * @return ViewInterface
		 * @see    \WPEmerge\View\ViewFactory::make()
		 */
		public static function view( $views ) : ViewInterface {
		}

		/**
		 * Output child layout content.
		 *
		 * @return void
		 * @see    \WPEmerge\View\PhpViewEngine::includeNextView()
		 */
		public static function includeChildViews() :void {}

		/**
		 * Output the specified view.
		 *
		 * @param  string|string[]  $views
		 * @param  array<string, mixed>  $context
		 *
		 * @return string
		 * @see    \WPEmerge\Contracts\ViewInterface::toString()
		 * @see    \WPEmerge\View\ViewFactory::make()
		 */
		public static function render( $views, array $context = [] ) :string {}

		/**
		 *
		 * Add a new view composer to the given views
		 *
		 * @param  string|string[]  $views
		 * @param  string|array|callable|\Closure  $callable
		 *
		 * @return void
		 * @see \WPEmerge\View\ViewComposerCollection::addComposer()
		 */
		public static function addComposer( $views, $callable ) :void {}

		/**
		 *
		 * Returns the global variable bag used by view composers.
         *
         * Arrays are converted to instances of VariableBag.
         *
		 * @see GlobalContext::add()
		 */
		public static function globals(string $name, $context) {}

        /**
         * Return the response cookies instance
         * @return Cookies
         */
        public static function cookies() :Cookies
        {


        }


		/**
		 * Create a new route.
		 *
		 * @return \WPEmerge\Routing\Router
		 */
		public static function route() : Router {
		}

		/**
		 * Get the url to a named route
		 *
		 * @see UrlGenerator::toRoute()
		 */
		public static function routeUrl( string $route_name, array $arguments = [], bool $secure = true, bool $absolute = true  ) : string {
		}

		/**
		 * Create a new post route
		 *
		 * @see Router::post()
		 */
		public static function post( string $url = '*', $action = null ) : Route {


		}

		/**
		 * Create a new get route
		 *
		 * @see Router::get()
		 */
		public static function get( string $url = '*', $action = null ) : Route {


		}

		/**
		 * Create a new patch route
		 *
		 * @see Router::patch()
		 */
		public static function patch( string $url = '*', $action = null ) : Route {


		}

		/** Create a new put route
		 *
		 * @see Router::put()
		 */
		public static function put( string $url = '*', $action = null ) : Route {
		}

		/**
		 * Create a new options route
		 *
		 * @see Router::options()
		 */
		public static function options( string $url = '*', $action = null ) : Route {}

		/**
		 * Create a new delete route
		 *
		 * @see Router::delete()
		 */
		public static function delete( string $url = '*', $action = null ) : Route {}




	}
