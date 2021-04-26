<?php



	namespace WPEmerge\Session;

	use ArrayAccess;
	use WPEmerge\Exceptions\ConfigurationException;
	use WPEmerge\Helpers\MixedType;
	use WPEmerge\Support\WPEmgereArr;


	class FlashStore {

		/**
		 * Keys for different request contexts.
		 */
		const CURRENT_KEY = 'current';
		const NEXT_KEY    = 'next';

		/**
		 * Key to store flashed data in store with.
		 *
		 * @var string
		 */
		private $store_key = '';

		/**
		 * Root store array or object implementing ArrayAccess.
		 *
		 * @var array|ArrayAccess
		 */
		private $store = null;

		/**
		 * FlashStore store array.
		 *
		 * @var array
		 */
		private $flashed = [];

		/**
		 * Constructor.
		 *
		 *
		 * @param  array|ArrayAccess  $store
		 * @param  string  $store_key
		 */
		public function __construct( &$store, $store_key = 'wpemerge.flash' ) {

			$this->store_key = $store_key;
			$this->setStore( $store );

		}

		/**
		 * Get whether a store object is valid.
		 *
		 * @param  mixed  $store
		 *
		 * @return boolean
		 */
		private function isValidStore( $store ) : bool {

			return ( is_array( $store ) || $store instanceof ArrayAccess );
		}

		/**
		 * Throw an exception if store is not valid.
		 *
		 * @return void
		 */
		private function validateStore() {

			if ( ! $this->isValidStore( $this->store ) ) {
				throw new ConfigurationException(
					'Attempted to use FlashStore without an active session. ' .
					'Did you miss to call session_start()?'
				);
			}
		}

		/**
		 * Get the store for flash messages.
		 *
		 * @return array|ArrayAccess
		 */
		public function getStore() {

			return $this->store;
		}

		/**
		 * Set the store for flash messages.
		 *
		 * @param  array|ArrayAccess  $store
		 *
		 * @return void
		 */
		public function setStore( &$store ) {

			if ( ! $this->isValidStore( $store ) ) {
				return;
			}

			$this->store = &$store;

			if ( ! isset( $this->store[ $this->store_key ] ) ) {
				$this->store[ $this->store_key ] = [
					static::CURRENT_KEY => [],
					static::NEXT_KEY    => [],
				];
			}

			$this->flashed = $store[ $this->store_key ];
		}

		/**
		 * Get whether the flash service is enabled.
		 *
		 * @return boolean
		 */
		public function enabled() {

			return $this->isValidStore( $this->store );
		}

		/**
		 * Get the entire store or the values for a key for a request.
		 *
		 * @param  string  $request_key
		 * @param  string|null  $key
		 * @param  mixed  $default
		 *
		 * @return mixed
		 */
		private function getFromRequest( $request_key, $key = null, $default = [] ) {

			$this->validateStore();

			if ( $key === null ) {
				return WPEmgereArr::get( $this->flashed, $request_key, $default );
			}

			return WPEmgereArr::get( $this->flashed[ $request_key ], $key, $default );
		}

		/**
		 * Add values for a key for a request.
		 *
		 * @param  string  $request_key
		 * @param  string  $key
		 * @param  mixed  $new_items
		 *
		 * @return void
		 */
		private function addToRequest( $request_key, $key, $new_items ) {

			$this->validateStore();

			$new_items = MixedType::toArray( $new_items );

			$method = ( $request_key === static::CURRENT_KEY ) ? 'get' : 'getNext';
			$items  = MixedType::toArray( $this->$method( $key, [] ) );

			$this->flashed[ $request_key ][ $key ] = array_merge( $items, $new_items );
		}

		/**
		 * Remove all values or values for a key from a request.
		 *
		 * @param  string  $request_key
		 * @param  string|null  $key
		 *
		 * @return void
		 */
		private function clearFromRequest( $request_key, $key = null ) {

			$this->validateStore();

			$keys = $key === null ? array_keys( $this->flashed[ $request_key ] ) : [ $key ];
			foreach ( $keys as $k ) {
				unset( $this->flashed[ $request_key ][ $k ] );
			}
		}

		/**
		 * Add values for a key for the next request.
		 *
		 * @param  string  $key
		 * @param  mixed  $new_items
		 *
		 * @return void
		 */
		public function add( $key, $new_items ) {

			$this->addToRequest( static::NEXT_KEY, $key, $new_items );
		}

		/**
		 * Add values for a key for the current request.
		 *
		 * @param  string  $key
		 * @param  mixed  $new_items
		 *
		 * @return void
		 */
		public function addNow( $key, $new_items ) {

			$this->addToRequest( static::CURRENT_KEY, $key, $new_items );

		}

		/**
		 * Get the entire store or the values for a key for the current request.
		 *
		 * @param  string|null  $key
		 * @param  mixed  $default
		 *
		 * @return mixed
		 */
		public function get( $key = null, $default = [] ) {

			return $this->getFromRequest( static::CURRENT_KEY, $key, $default );

		}

		/**
		 * Get the entire store or the values for a key for the next request.
		 *
		 * @param  string|null  $key
		 * @param  mixed  $default
		 *
		 * @return mixed
		 */
		public function getNext( $key = null, $default = [] ) {

			return $this->getFromRequest( static::NEXT_KEY, $key, $default );

		}

		/**
		 * Clear the entire store or the values for a key for the current request.
		 *
		 * @param  string|null  $key
		 *
		 * @return void
		 */
		public function clear( $key = null ) {

			$this->clearFromRequest( static::CURRENT_KEY, $key );
		}

		/**
		 * Clear the entire store or the values for a key for the next request.
		 *
		 * @param  string|null  $key
		 *
		 * @return void
		 */
		public function clearNext( $key = null ) {

			$this->clearFromRequest( static::NEXT_KEY, $key );
		}

		/**
		 * Shift current store and replace it with next store.
		 *
		 * @return void
		 */
		public function shift() {

			$this->validateStore();

			$this->flashed[ static::CURRENT_KEY ] = $this->flashed[ static::NEXT_KEY ];
			$this->flashed[ static::NEXT_KEY ]    = [];
		}

		/**
		 * Save flashed data to store.
		 *
		 * @return void
		 */
		public function save() {

			$this->validateStore();

			$this->store[ $this->store_key ] = $this->flashed;
		}

	}