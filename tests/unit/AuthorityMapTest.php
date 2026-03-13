<?php
namespace Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Search_Intel\Authority_Map;

class AuthorityMapTest extends TestCase {

	private Authority_Map $map;

	protected function setUp(): void {
		global $si_test_state;
		$si_test_state['options']    = [];
		$si_test_state['post_meta']  = [];
		$si_test_state['posts']      = [];
		$si_test_state['transients'] = [];

		$this->map = new Authority_Map( [
			'enable_authority_map' => true,
		] );
	}

	// ── Topic Authority Score ────────────────────────────────────────────────

	public function test_calculate_topic_authority_returns_structure(): void {
		$result = $this->map->calculate_topic_authority( 1 );

		$this->assertArrayHasKey( 'score', $result );
		$this->assertArrayHasKey( 'factors', $result );
		$this->assertArrayHasKey( 'grade', $result );
		$this->assertIsInt( $result['score'] );
		$this->assertGreaterThanOrEqual( 0, $result['score'] );
		$this->assertLessThanOrEqual( 100, $result['score'] );
	}

	public function test_topic_authority_has_all_factors(): void {
		$result = $this->map->calculate_topic_authority( 1 );

		$this->assertArrayHasKey( 'volume', $result['factors'] );
		$this->assertArrayHasKey( 'freshness', $result['factors'] );
		$this->assertArrayHasKey( 'eeat', $result['factors'] );
		$this->assertArrayHasKey( 'linking', $result['factors'] );
		$this->assertArrayHasKey( 'schema', $result['factors'] );
	}

	public function test_topic_authority_factor_structure(): void {
		$result = $this->map->calculate_topic_authority( 1 );

		foreach ( $result['factors'] as $factor ) {
			$this->assertArrayHasKey( 'label', $factor );
			$this->assertArrayHasKey( 'score', $factor );
			$this->assertArrayHasKey( 'max', $factor );
			$this->assertArrayHasKey( 'detail', $factor );
			$this->assertGreaterThanOrEqual( 0, $factor['score'] );
			$this->assertLessThanOrEqual( $factor['max'], $factor['score'] );
		}
	}

	public function test_topic_authority_empty_category(): void {
		$result = $this->map->calculate_topic_authority( 999 );
		$this->assertEquals( 0, $result['score'] );
		$this->assertEquals( 'F', $result['grade'] );
	}

	public function test_topic_authority_grade_values(): void {
		$result = $this->map->calculate_topic_authority( 1 );
		$this->assertContains( $result['grade'], [ 'A', 'B', 'C', 'D', 'F' ] );
	}

	// ── Content Gap Analysis ─────────────────────────────────────────────────

	public function test_analyze_content_gaps_returns_structure(): void {
		$result = $this->map->analyze_content_gaps();

		$this->assertArrayHasKey( 'topics', $result );
		$this->assertArrayHasKey( 'recommendations', $result );
		$this->assertIsArray( $result['topics'] );
		$this->assertIsArray( $result['recommendations'] );
	}

	public function test_content_gap_topic_structure(): void {
		$result = $this->map->analyze_content_gaps();

		foreach ( $result['topics'] as $topic ) {
			$this->assertArrayHasKey( 'term_id', $topic );
			$this->assertArrayHasKey( 'name', $topic );
			$this->assertArrayHasKey( 'count', $topic );
			$this->assertArrayHasKey( 'score', $topic );
			$this->assertArrayHasKey( 'grade', $topic );
		}
	}

	public function test_content_gap_recommendations_structure(): void {
		$result = $this->map->analyze_content_gaps();

		foreach ( $result['recommendations'] as $rec ) {
			$this->assertArrayHasKey( 'topic', $rec );
			$this->assertArrayHasKey( 'score', $rec );
			$this->assertArrayHasKey( 'priority', $rec );
			$this->assertArrayHasKey( 'message', $rec );
			$this->assertContains( $rec['priority'], [ 'high', 'medium', 'low' ] );
		}
	}

	public function test_content_gap_sorted_by_score(): void {
		$result = $this->map->analyze_content_gaps();

		for ( $i = 1; $i < count( $result['topics'] ); $i++ ) {
			$this->assertGreaterThanOrEqual(
				$result['topics'][ $i - 1 ]['score'],
				$result['topics'][ $i ]['score']
			);
		}
	}

	// ── Dashboard Data ───────────────────────────────────────────────────────

	public function test_get_dashboard_data_returns_structure(): void {
		$result = $this->map->get_dashboard_data();

		$this->assertArrayHasKey( 'clusters', $result );
		$this->assertArrayHasKey( 'site_authority', $result );
		$this->assertIsInt( $result['site_authority'] );
		$this->assertIsArray( $result['clusters'] );
	}

	public function test_dashboard_cluster_structure(): void {
		$result = $this->map->get_dashboard_data();

		foreach ( $result['clusters'] as $cluster ) {
			$this->assertArrayHasKey( 'term_id', $cluster );
			$this->assertArrayHasKey( 'name', $cluster );
			$this->assertArrayHasKey( 'authority', $cluster );
			$this->assertArrayHasKey( 'posts', $cluster );
			$this->assertArrayHasKey( 'score', $cluster['authority'] );
		}
	}

	// ── Multimodal Analysis ──────────────────────────────────────────────────

	public function test_analyze_multimodal_returns_structure(): void {
		$result = $this->map->analyze_multimodal();

		$this->assertArrayHasKey( 'summary', $result );
		$this->assertArrayHasKey( 'posts', $result );
		$this->assertArrayHasKey( 'total_posts', $result['summary'] );
		$this->assertArrayHasKey( 'posts_with_alt_text', $result['summary'] );
		$this->assertArrayHasKey( 'missing_alt_count', $result['summary'] );
		$this->assertArrayHasKey( 'posts_with_video', $result['summary'] );
		$this->assertArrayHasKey( 'posts_with_schema', $result['summary'] );
		$this->assertArrayHasKey( 'alt_coverage', $result['summary'] );
		$this->assertArrayHasKey( 'schema_coverage', $result['summary'] );
	}

	public function test_analyze_multimodal_detects_missing_alt(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Image Post',
			'post_content' => '<img src="photo.jpg"> <img src="other.jpg" alt="Good alt">',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];

		$result = $this->map->analyze_multimodal();

		$this->assertGreaterThan( 0, $result['summary']['missing_alt_count'] );
	}

	public function test_analyze_multimodal_detects_video(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Video Post',
			'post_content' => '<iframe src="https://www.youtube.com/embed/abc123"></iframe>',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];

		$result = $this->map->analyze_multimodal();
		$this->assertEquals( 1, $result['summary']['posts_with_video'] );
	}

	public function test_analyze_multimodal_all_alt_text_present(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Good Post',
			'post_content' => '<img src="photo.jpg" alt="A good description">',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];

		$result = $this->map->analyze_multimodal();
		$this->assertEquals( 0, $result['summary']['missing_alt_count'] );
		$this->assertEquals( 1, $result['summary']['posts_with_alt_text'] );
	}

	public function test_analyze_multimodal_empty(): void {
		$result = $this->map->analyze_multimodal();
		$this->assertEquals( 0, $result['summary']['total_posts'] );
		$this->assertEmpty( $result['posts'] );
	}

	// ── Disabled ─────────────────────────────────────────────────────────────

	public function test_disabled_does_not_register(): void {
		global $si_test_state;
		$si_test_state['actions'] = [];

		$map = new Authority_Map( [ 'enable_authority_map' => false ] );

		$this->assertArrayNotHasKey( 'admin_menu', $si_test_state['actions'] ?? [] );
	}
}
