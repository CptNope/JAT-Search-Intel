<?php
namespace Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Search_Intel\Migration;

class MigrationTest extends TestCase {

	private Migration $migration;

	protected function setUp(): void {
		global $si_test_state;
		$si_test_state = [
			'options'    => [],
			'post_meta'  => [],
			'posts'      => [],
			'transients' => [],
			'actions'    => [],
		];

		$this->migration = new Migration( [] );
	}

	// ── Detection ────────────────────────────────────────────────────────────

	public function test_detect_sources_empty_when_no_data(): void {
		$this->assertEmpty( $this->migration->detect_sources() );
	}

	public function test_detect_sources_finds_yoast(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Post', 'post_content' => 'Content.',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];
		$si_test_state['post_meta'][1]['_yoast_wpseo_title'] = 'Yoast Title';

		$detected = $this->migration->detect_sources();
		$this->assertArrayHasKey( 'yoast', $detected );
		$this->assertEquals( 1, $detected['yoast']['posts'] );
		$this->assertEquals( 'Yoast SEO', $detected['yoast']['label'] );
	}

	public function test_detect_sources_finds_rankmath(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Post', 'post_content' => 'Content.',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];
		$si_test_state['post_meta'][1]['rank_math_title'] = 'RM Title';

		$detected = $this->migration->detect_sources();
		$this->assertArrayHasKey( 'rankmath', $detected );
	}

	// ── Dry Run ──────────────────────────────────────────────────────────────

	public function test_dry_run_unknown_source(): void {
		$result = $this->migration->dry_run( 'unknown' );
		$this->assertArrayHasKey( 'error', $result );
	}

	public function test_dry_run_yoast_returns_preview(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'My Post', 'post_content' => 'Content.',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];
		$si_test_state['post_meta'][1]['_yoast_wpseo_title'] = 'Yoast Title';
		$si_test_state['post_meta'][1]['_yoast_wpseo_metadesc'] = 'Yoast desc';

		$result = $this->migration->dry_run( 'yoast' );

		$this->assertEquals( 'Yoast SEO', $result['source'] );
		$this->assertEquals( 1, $result['post_count'] );
		$this->assertArrayHasKey( 'title', $result['fields'] );
		$this->assertArrayHasKey( 'description', $result['fields'] );
		$this->assertEquals( 1, $result['fields']['title']['count'] );
	}

	public function test_dry_run_has_samples(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Post', 'post_content' => 'Content.',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];
		$si_test_state['post_meta'][1]['_yoast_wpseo_title'] = 'Title';

		$result = $this->migration->dry_run( 'yoast' );
		$this->assertNotEmpty( $result['fields']['title']['samples'] );
		$this->assertEquals( 1, $result['fields']['title']['samples'][0]['post_id'] );
	}

	// ── Migration ────────────────────────────────────────────────────────────

	public function test_migrate_unknown_source(): void {
		$result = $this->migration->migrate( 'unknown' );
		$this->assertArrayHasKey( 'error', $result );
	}

	public function test_migrate_yoast_title_and_description(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Post', 'post_content' => 'Content.',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];
		$si_test_state['post_meta'][1]['_yoast_wpseo_title'] = 'Yoast Title';
		$si_test_state['post_meta'][1]['_yoast_wpseo_metadesc'] = 'Yoast description';

		$result = $this->migration->migrate( 'yoast' );

		$this->assertEquals( 1, $result['migrated'] );
		$this->assertEquals( 'Yoast Title', $si_test_state['post_meta'][1]['_search_intel_title'] );
		$this->assertEquals( 'Yoast description', $si_test_state['post_meta'][1]['_search_intel_description'] );
	}

	public function test_migrate_does_not_overwrite_by_default(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Post', 'post_content' => 'Content.',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];
		$si_test_state['post_meta'][1]['_yoast_wpseo_title'] = 'Yoast Title';
		$si_test_state['post_meta'][1]['_search_intel_title'] = 'Existing Title';

		$result = $this->migration->migrate( 'yoast', false );

		$this->assertEquals( 0, $result['migrated'] );
		$this->assertEquals( 1, $result['skipped'] );
		$this->assertEquals( 'Existing Title', $si_test_state['post_meta'][1]['_search_intel_title'] );
	}

	public function test_migrate_overwrite_replaces_existing(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Post', 'post_content' => 'Content.',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];
		$si_test_state['post_meta'][1]['_yoast_wpseo_title'] = 'Yoast Title';
		$si_test_state['post_meta'][1]['_search_intel_title'] = 'Existing Title';

		$result = $this->migration->migrate( 'yoast', true );

		$this->assertEquals( 1, $result['migrated'] );
		$this->assertEquals( 'Yoast Title', $si_test_state['post_meta'][1]['_search_intel_title'] );
	}

	public function test_migrate_canonical_sanitized_as_url(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Post', 'post_content' => 'Content.',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];
		$si_test_state['post_meta'][1]['_yoast_wpseo_canonical'] = 'https://example.com/page';

		$this->migration->migrate( 'yoast' );

		$this->assertEquals( 'https://example.com/page', $si_test_state['post_meta'][1]['_search_intel_canonical'] );
	}

	public function test_migrate_multiple_sources(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Post', 'post_content' => 'Content.',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];
		$si_test_state['post_meta'][1]['rank_math_title'] = 'RM Title';
		$si_test_state['post_meta'][1]['rank_math_description'] = 'RM Desc';

		$result = $this->migration->migrate( 'rankmath' );
		$this->assertEquals( 1, $result['migrated'] );
		$this->assertEquals( 'RM Title', $si_test_state['post_meta'][1]['_search_intel_title'] );
	}

	// ── Redirect Migration ───────────────────────────────────────────────────

	public function test_migrate_yoast_redirects(): void {
		global $si_test_state;
		// No posts with meta but has redirects option.
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Post', 'post_content' => 'Content.',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];
		$si_test_state['post_meta'][1]['_yoast_wpseo_title'] = 'Title';
		$si_test_state['options']['wpseo-premium-redirects-base'] = [
			[ 'origin' => '/old-page', 'url' => '/new-page', 'type' => 301 ],
		];

		$result = $this->migration->migrate( 'yoast' );
		$this->assertEquals( 1, $result['redirects'] );

		$redirects = $si_test_state['options']['search_intel_redirects'];
		$this->assertCount( 1, $redirects );
		$this->assertEquals( '/old-page', $redirects[0]['from'] );
		$this->assertEquals( '/new-page', $redirects[0]['to'] );
	}
}
