<?php


	declare( strict_types = 1 );


	namespace WPEmerge\Session\Drivers;

	use Illuminate\Support\InteractsWithTime;
    use Psr\Http\Message\ServerRequestInterface;
    use WPEmerge\Session\SessionDriver;

    class ArraySessionDriver implements SessionDriver {

		use InteractsWithTime;

		/**
		 * The array of stored values.
		 *
		 * @var array
		 */
		private $storage = [];

		/**
		 * The number of minutes the session should be valid.
		 *
		 * @var int
		 */
		private $minutes;

		public function __construct(int $minutes )
		{
			$this->minutes = $minutes;
		}

		public function open($savePath, $sessionName)
		{
			return true;
		}

		public function close()
		{
			return true;
		}

		public function read($sessionId)
		{
			if (! isset($this->storage[$sessionId])) {
				return '';
			}

			$session = $this->storage[$sessionId];

			$expiration = $this->calculateExpiration($this->minutes * 60);

			if (isset($session['time']) && $session['time'] >= $expiration) {
				return $session['data'];
			}

			return '';
		}

		public function write($sessionId, $data)
		{
			$this->storage[$sessionId] = [
				'data' => $data,
				'time' => $this->currentTime(),
			];

			return true;
		}

		public function destroy($sessionId)
		{
			if (isset($this->storage[$sessionId])) {
				unset($this->storage[$sessionId]);
			}

			return true;
		}

		public function gc($lifetime)
		{
			$expiration = $this->calculateExpiration($lifetime);

			foreach ($this->storage as $sessionId => $session) {
				if ($session['time'] < $expiration) {
					unset($this->storage[$sessionId]);
				}
			}

			return true;
		}

		private function calculateExpiration(int $seconds) :int
		{
			return $this->currentTime() - $seconds;
		}

        public function setRequest(ServerRequestInterface $request)
        {
            //
        }

    }