<?php
namespace Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Search_Intel\Citation_Graph;

class CitationGraphTest extends TestCase {

	private Citation_Graph $graph;

	protected function setUp(): void {
		global $si_test_state;
		$si_test_state['options']    = [];
		$si_test_state['post_meta']  = [];
		$si_test_state['posts']      = [];
		$si_test_state['transients'] = [];

		$this->graph = new Citation_Graph( [
			'enable_citation_graph' => true,
		] );
	}

	// ── Internal Link Extraction ─────────────────────────────────────────────

	public function test_extract_internal_links_finds_links(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Source Post',
			'post_content' => '<p>Check out <a href="https://example.com/other-post">this post</a> and <a href="https://example.com/page">this page</a>.</p>',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];

		$result = $this->graph->extract_internal_links( 1 );

		$this->assertArrayHasKey( 'outbound', $result );
		$this->assertArrayHasKey( 'urls', $result );
		$this->assertCount( 2, $result['urls'] );
	}

	public function test_extract_internal_links_empty_for_no_content(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Empty', 'post_content' => '',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];

		$result = $this->graph->extract_internal_links( 1 );
		$this->assertEmpty( $result['outbound'] );
		$this->assertEmpty( $result['urls'] );
	}

	public function test_extract_internal_links_empty_for_missing_post(): void {
		$result = $this->graph->extract_internal_links( 999 );
		$this->assertEmpty( $result['outbound'] );
	}

	public function test_extract_internal_links_skips_external(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Post',
			'post_content' => '<a href="https://external.com/page">External</a>',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];

		$result = $this->graph->extract_internal_links( 1 );
		$this->assertEmpty( $result['urls'] );
	}

	// ── External Link Extraction ─────────────────────────────────────────────

	public function test_extract_external_links_finds_external(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Post',
			'post_content' => '<a href="https://external.com/page">Ext</a> <a href="https://another.org/doc">Another</a>',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];

		$result = $this->graph->extract_external_links( 1 );
		$this->assertCount( 2, $result );
	}

	public function test_extract_external_links_skips_internal(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Post',
			'post_content' => '<a href="https://example.com/page">Internal</a>',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];

		$result = $this->graph->extract_external_links( 1 );
		$this->assertEmpty( $result );
	}

	public function test_extract_external_links_empty_for_missing_post(): void {
		$this->assertEmpty( $this->graph->extract_external_links( 999 ) );
	}

	// ── Link Graph ───────────────────────────────────────────────────────────

	public function test_build_link_graph_returns_structure(): void {
		$result = $this->graph->build_link_graph();

		$this->assertArrayHasKey( 'nodes', $result );
		$this->assertArrayHasKey( 'edges', $result );
		$this->assertIsArray( $result['nodes'] );
		$this->assertIsArray( $result['edges'] );
	}

	public function test_get_inbound_links_empty(): void {
		$inbound = $this->graph->get_inbound_links( 1 );
		$this->assertIsArray( $inbound );
	}

	// ── Citation Data ────────────────────────────────────────────────────────

	public function test_get_citation_data_structure(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Post',
			'post_content' => '<a href="https://example.org/ref1">Ref1</a> <a href="https://example.org/ref2">Ref2</a> <a href="https://other.com/doc">Doc</a>',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];

		$data = $this->graph->get_citation_data( 1 );

		$this->assertArrayHasKey( 'outbound_citations', $data );
		$this->assertArrayHasKey( 'domain_distribution', $data );
		$this->assertCount( 3, $data['outbound_citations'] );
		$this->assertArrayHasKey( 'example.org', $data['domain_distribution'] );
		$this->assertEquals( 2, $data['domain_distribution']['example.org'] );
	}

	public function test_get_citation_data_empty_for_no_external(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Post',
			'post_content' => '<a href="https://example.com/internal">Internal</a>',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];

		$data = $this->graph->get_citation_data( 1 );
		$this->assertEmpty( $data['outbound_citations'] );
		$this->assertEmpty( $data['domain_distribution'] );
	}

	// ── Topic Cluster Detection ──────────────────────────────────────────────

	public function test_detect_topic_clusters_returns_array(): void {
		$clusters = $this->graph->detect_topic_clusters();
		$this->assertIsArray( $clusters );
	}

	// ── Orphaned Content ─────────────────────────────────────────────────────

	public function test_detect_orphaned_content_returns_array(): void {
		$orphaned = $this->graph->detect_orphaned_content();
		$this->assertIsArray( $orphaned );
	}

	// ── Pillar Pages ─────────────────────────────────────────────────────────

	public function test_identify_pillar_pages_returns_array(): void {
		$pillars = $this->graph->identify_pillar_pages();
		$this->assertIsArray( $pillars );
	}

	// ── Missing Links ────────────────────────────────────────────────────────

	public function test_detect_missing_links_returns_array(): void {
		$missing = $this->graph->detect_missing_links();
		$this->assertIsArray( $missing );
	}

	// ── Cluster Linking Score ────────────────────────────────────────────────

	public function test_calculate_cluster_linking_score_empty(): void {
		$graph = [ 'nodes' => [], 'edges' => [] ];
		$score = $this->graph->calculate_cluster_linking_score( [], $graph );
		$this->assertEquals( 0, $score );
	}

	public function test_calculate_cluster_linking_score_single_post(): void {
		$graph = [ 'nodes' => [], 'edges' => [] ];
		$score = $this->graph->calculate_cluster_linking_score( [ 1 ], $graph );
		$this->assertEquals( 0, $score );
	}

	public function test_calculate_cluster_linking_score_with_links(): void {
		$graph = [
			'nodes' => [],
			'edges' => [
				[ 'source' => 1, 'target' => 2 ],
				[ 'source' => 2, 'target' => 1 ],
				[ 'source' => 1, 'target' => 3 ],
			],
		];

		$score = $this->graph->calculate_cluster_linking_score( [ 1, 2, 3 ], $graph );
		$this->assertGreaterThan( 0, $score );
		$this->assertLessThanOrEqual( 100, $score );
	}

	public function test_calculate_cluster_linking_score_fully_linked(): void {
		$graph = [
			'nodes' => [],
			'edges' => [
				[ 'source' => 1, 'target' => 2 ],
				[ 'source' => 2, 'target' => 1 ],
				[ 'source' => 1, 'target' => 3 ],
				[ 'source' => 3, 'target' => 1 ],
				[ 'source' => 2, 'target' => 3 ],
				[ 'source' => 3, 'target' => 2 ],
			],
		];

		// 6 edges out of 6 possible = 100% density, well above 30% threshold.
		$score = $this->graph->calculate_cluster_linking_score( [ 1, 2, 3 ], $graph );
		$this->assertEquals( 100, $score );
	}

	// ── Update Post Graph ────────────────────────────────────────────────────

	public function test_update_post_graph_saves_meta(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Post',
			'post_content' => '<a href="https://external.com/doc">Doc</a>',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];

		$post = (object) $si_test_state['posts'][1];
		$this->graph->update_post_graph( 1, $post );

		$this->assertArrayHasKey( '_search_intel_internal_links', $si_test_state['post_meta'][1] ?? [] );
		$this->assertArrayHasKey( '_search_intel_external_links', $si_test_state['post_meta'][1] ?? [] );
	}

	public function test_update_post_graph_skips_revision(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Post', 'post_content' => 'Content.',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];

		// wp_is_post_revision returns false in stubs, so this tests the normal path.
		$post = (object) [ 'post_status' => 'draft' ];
		$this->graph->update_post_graph( 1, $post );

		// Draft should be skipped.
		$this->assertArrayNotHasKey( '_search_intel_internal_links', $si_test_state['post_meta'][1] ?? [] );
	}

	// ── Disabled ─────────────────────────────────────────────────────────────

	public function test_disabled_does_not_register(): void {
		global $si_test_state;
		$si_test_state['actions'] = [];

		$graph = new Citation_Graph( [ 'enable_citation_graph' => false ] );

		$this->assertArrayNotHasKey( 'save_post', $si_test_state['actions'] ?? [] );
	}
}
