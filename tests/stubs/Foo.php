<?php


	namespace Tests\stubs;

	class Foo {

		public $foo = 'foo';

		public function __toString() {

			return $this->foo;

		}

	}