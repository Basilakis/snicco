<?php


	namespace Tests\stubs\Controllers\Admin;

	use WPEmerge\Requests\Request;

	class AjaxController {

		public function handle( Request $request, $view) {

			return 'ajax_controller';

		}

	}