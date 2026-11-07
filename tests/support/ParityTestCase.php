<?php
namespace JAT_Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Shared reset for the 3.6 feature tests.
 */
abstract class ParityTestCase extends TestCase {

	protected function setUp(): void {
		global $si_test_state;
		$si_test_state = [
			'post_meta'    => [],
			'term_meta'    => [],
			'user_meta'    => [],
			'options'      => [],
			'transients'   => [],
			'actions'      => [],
			'filters'      => [],
			'posts'        => [],
			'current_user' => 1,
		];
		unset( $_POST, $_GET );
		$_POST = [];
		$_GET  = [];
	}

	protected function tearDown(): void {
		$_POST = [];
		$_GET  = [];
	}
}
