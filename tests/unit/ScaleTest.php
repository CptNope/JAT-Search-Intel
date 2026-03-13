<?php
namespace Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Search_Intel\Scale;

class ScaleTest extends TestCase {

	private Scale $scale;

	protected function setUp(): void {
		global $si_test_state;
		$si_test_state = [
			'options'          => [],
			'post_meta'        => [],
			'posts'            => [],
			'transients'       => [],
			'actions'          => [],
			'object_cache'     => [],
			'ext_object_cache' => false,
			'scheduled_events' => [],
		];

		$this->scale = new Scale( [ 'enable_scale' => true ] );
	}

	// ── Object Cache ─────────────────────────────────────────────────────────

	public function test_has_object_cache_false_by_default(): void {
		$this->assertFalse( $this->scale->has_object_cache() );
	}

	public function test_has_object_cache_true(): void {
		global $si_test_state;
		$si_test_state['ext_object_cache'] = true;
		$this->assertTrue( $this->scale->has_object_cache() );
	}

	public function test_cache_set_and_get_via_transient(): void {
		$this->scale->cache_set( 'test_key', 'hello', 3600 );
		$result = $this->scale->cache_get( 'test_key' );
		$this->assertEquals( 'hello', $result );
	}

	public function test_cache_set_and_get_via_object_cache(): void {
		global $si_test_state;
		$si_test_state['ext_object_cache'] = true;

		$this->scale->cache_set( 'test_key', [ 'data' => 42 ], 3600 );
		$result = $this->scale->cache_get( 'test_key' );
		$this->assertEquals( [ 'data' => 42 ], $result );
	}

	public function test_cache_delete_transient(): void {
		$this->scale->cache_set( 'del_key', 'value' );
		$this->scale->cache_delete( 'del_key' );
		$this->assertFalse( $this->scale->cache_get( 'del_key' ) );
	}

	public function test_cache_delete_object_cache(): void {
		global $si_test_state;
		$si_test_state['ext_object_cache'] = true;

		$this->scale->cache_set( 'del_key', 'value' );
		$this->scale->cache_delete( 'del_key' );
		$this->assertFalse( $this->scale->cache_get( 'del_key' ) );
	}

	public function test_cache_get_miss_returns_false(): void {
		$this->assertFalse( $this->scale->cache_get( 'nonexistent' ) );
	}

	// ── Background Queue ─────────────────────────────────────────────────────

	public function test_enqueue_job_returns_uuid(): void {
		$id = $this->scale->enqueue_job( 'rescore', [ 'post_id' => 1 ] );
		$this->assertIsString( $id );
		$this->assertNotEmpty( $id );
	}

	public function test_get_queue_empty(): void {
		$this->assertSame( [], $this->scale->get_queue() );
	}

	public function test_enqueue_job_adds_to_queue(): void {
		$this->scale->enqueue_job( 'rescore', [ 'post_id' => 1 ] );
		$queue = $this->scale->get_queue();
		$this->assertCount( 1, $queue );
		$this->assertEquals( 'rescore', $queue[0]['type'] );
		$this->assertEquals( 'pending', $queue[0]['status'] );
		$this->assertEquals( 1, $queue[0]['data']['post_id'] );
	}

	public function test_enqueue_multiple_jobs(): void {
		$this->scale->enqueue_job( 'rescore', [ 'post_id' => 1 ] );
		$this->scale->enqueue_job( 'regenerate', [ 'post_id' => 2 ] );
		$this->scale->enqueue_job( 'link_graph', [ 'post_id' => 3 ] );

		$queue = $this->scale->get_queue();
		$this->assertCount( 3, $queue );
	}

	public function test_process_queue_empty(): void {
		$processed = $this->scale->process_queue();
		$this->assertEquals( 0, $processed );
	}

	public function test_process_queue_unknown_type_fails(): void {
		$this->scale->enqueue_job( 'unknown_type', [] );
		$processed = $this->scale->process_queue();
		$this->assertEquals( 1, $processed );

		$queue = $this->scale->get_queue();
		$this->assertEquals( 'failed', $queue[0]['status'] );
	}

	public function test_process_queue_respects_batch_size(): void {
		for ( $i = 0; $i < 10; $i++ ) {
			$this->scale->enqueue_job( 'unknown_type', [] );
		}

		$processed = $this->scale->process_queue( 3 );
		$this->assertEquals( 3, $processed );

		$queue = $this->scale->get_queue();
		$pending = count( array_filter( $queue, fn( $j ) => $j['status'] === 'pending' ) );
		$this->assertEquals( 7, $pending );
	}

	// ── Bulk Editor ──────────────────────────────────────────────────────────

	public function test_get_bulk_editor_data_empty(): void {
		$data = $this->scale->get_bulk_editor_data();
		$this->assertArrayHasKey( 'posts', $data );
		$this->assertArrayHasKey( 'total', $data );
		$this->assertEmpty( $data['posts'] );
	}

	public function test_bulk_update_meta(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Post', 'post_content' => '',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];

		$count = $this->scale->bulk_update_meta( [
			1 => [
				'title'       => 'New Title',
				'description' => 'New Desc',
				'keyword'     => 'seo',
			],
		] );

		$this->assertEquals( 1, $count );
		$this->assertEquals( 'New Title', $si_test_state['post_meta'][1]['_search_intel_title'] );
		$this->assertEquals( 'New Desc', $si_test_state['post_meta'][1]['_search_intel_description'] );
		$this->assertEquals( 'seo', $si_test_state['post_meta'][1]['_search_intel_focus_keyword'] );
	}

	public function test_bulk_update_meta_ignores_invalid_fields(): void {
		$count = $this->scale->bulk_update_meta( [
			1 => [ 'invalid_field' => 'value' ],
		] );
		$this->assertEquals( 0, $count );
	}

	public function test_bulk_update_meta_ignores_zero_id(): void {
		$count = $this->scale->bulk_update_meta( [
			0 => [ 'title' => 'Test' ],
		] );
		$this->assertEquals( 0, $count );
	}

	// ── CDN-Friendly ─────────────────────────────────────────────────────────

	public function test_get_asset_url_nonexistent(): void {
		$url = $this->scale->get_asset_url( 'assets/nonexistent.js' );
		$this->assertStringContainsString( 'assets/nonexistent.js', $url );
	}

	// ── Disabled ─────────────────────────────────────────────────────────────

	public function test_disabled_does_not_register(): void {
		global $si_test_state;
		$si_test_state['actions'] = [];

		$scale = new Scale( [ 'enable_scale' => false ] );
		$this->assertArrayNotHasKey( 'rest_api_init', $si_test_state['actions'] ?? [] );
	}
}
