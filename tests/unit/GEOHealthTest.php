<?php
namespace JAT_Search_Intel\Tests\Unit;

use JAT_Search_Intel\GEO_Health;

class GEOHealthTest extends ParityTestCase {

	private const NOW = 1_800_000_000;

	private function post( int $id, int $age_days ): array {
		return [ 'id' => $id, 'modified' => self::NOW - $age_days * DAY_IN_SECONDS, 'author' => 1 ];
	}

	private function full_settings(): array {
		return [
			'enable_sitemap'      => true,
			'enable_indexnow'     => true,
			'enable_schema'       => true,
			'enable_schema_types' => true,
			'knowledge_name'      => 'Acme',
			'knowledge_logo'      => 'https://example.com/logo.png',
			'enable_llms_txt'     => true,
			'enable_ai_meta'      => true,
		];
	}

	public function test_weights_add_up_to_100_and_extras_earn_nothing(): void {
		$result = GEO_Health::score( [], [], [ 'public' => true, 'now' => self::NOW ] );
		$this->assertSame( 100, array_sum( array_column( $result['factors'], 'max' ) ) );
		$this->assertSame( 0, $result['factors']['llms_txt']['max'] );
		$this->assertSame( 0, $result['factors']['ai_meta']['max'] );
	}

	public function test_llms_txt_and_ai_meta_do_not_change_the_score(): void {
		$posts = [ $this->post( 1, 10 ) ];
		$ctx   = [ 'public' => true, 'now' => self::NOW ];
		$off   = GEO_Health::score( [ 'enable_sitemap' => true ], $posts, $ctx );
		$on    = GEO_Health::score( [ 'enable_sitemap' => true, 'enable_llms_txt' => true, 'enable_ai_meta' => true ], $posts, $ctx );
		$this->assertSame( $off['score'], $on['score'] );
		$this->assertStringStartsWith( 'On.', $on['factors']['llms_txt']['detail'] );
	}

	public function test_well_set_up_site_scores_an_a(): void {
		$posts = [];
		for ( $i = 1; $i <= 10; $i++ ) {
			$posts[] = $this->post( $i, 30 );
			update_post_meta( $i, '_jat_search_intel_direct_answer', 'Yes.' );
			update_post_meta( $i, '_jat_search_intel_index_status', [ 'verdict' => 'PASS' ] );
		}
		$result = GEO_Health::score( $this->full_settings(), $posts, [ 'public' => true, 'now' => self::NOW, 'profiles' => [ 'https://x.com/acme' ], 'author_bios' => 1.0 ] );
		$this->assertSame( 100, $result['score'] );
		$this->assertSame( 'A', $result['grade'] );
	}

	public function test_private_site_gets_no_crawler_points(): void {
		$this->assertSame( 0, GEO_Health::crawler_access( [], false )['score'] );
	}

	public function test_blocking_training_bots_is_free_but_search_bots_cost_points(): void {
		$this->assertSame( 20, GEO_Health::crawler_access( [ 'ai_crawler_rules' => [ 'GPTBot' => 'block', 'CCBot' => 'block' ] ], true )['score'] );

		$blocked = GEO_Health::crawler_access( [ 'ai_crawler_rules' => [ 'OAI-SearchBot' => 'block', 'PerplexityBot' => 'block' ] ], true );
		$this->assertLessThan( 20, $blocked['score'] );
		$this->assertStringContainsString( 'OAI-SearchBot', $blocked['detail'] );
	}

	public function test_ai_input_no_signal_cuts_crawler_points(): void {
		$result = GEO_Health::crawler_access( [ 'content_signals' => [ 'ai-input' => 'no' ] ], true );
		$this->assertSame( 5, $result['score'] );
		$this->assertStringContainsString( 'ai-input=no', $result['detail'] );
	}

	public function test_indexing_uses_measured_share_once_enough_pages_are_checked(): void {
		$posts = [];
		for ( $i = 1; $i <= 6; $i++ ) {
			$posts[] = $this->post( $i, 1 );
			update_post_meta( $i, '_jat_search_intel_index_status', [ 'verdict' => $i <= 3 ? 'PASS' : 'NEUTRAL' ] );
		}
		$result = GEO_Health::indexing( [ 'enable_sitemap' => true, 'enable_indexnow' => true ], $posts );
		$this->assertSame( 15, $result['score'] ); // 5 + 5 + half of 10.
		$this->assertStringContainsString( '3 of 6', $result['detail'] );
	}

	public function test_indexing_gives_half_credit_when_not_measured(): void {
		$result = GEO_Health::indexing( [], [ $this->post( 1, 1 ) ] );
		$this->assertSame( 5, $result['score'] );
		$this->assertStringContainsString( 'IndexNow is off', $result['detail'] );
		$this->assertStringContainsString( 'not measured', $result['detail'] );
	}

	public function test_freshness_counts_six_months_fully_and_a_year_by_half(): void {
		$posts  = [ $this->post( 1, 30 ), $this->post( 2, 200 ), $this->post( 3, 400 ), $this->post( 4, 100 ) ];
		$result = GEO_Health::freshness( $posts, self::NOW );
		$this->assertSame( 13, $result['score'] ); // 20 × (1 + 0.5 + 0 + 1) / 4 = 12.5 → 13.
		$this->assertStringContainsString( '50%', $result['detail'] );
		$this->assertSame( 0, GEO_Health::freshness( [], self::NOW )['score'] );
	}

	public function test_answers_accepts_direct_answer_faqs_or_aeo_score(): void {
		update_post_meta( 1, '_jat_search_intel_direct_answer', 'An answer.' );
		update_post_meta( 2, '_jat_search_intel_faqs', [ [ 'q' => 'Q?', 'a' => 'A.' ] ] );
		update_post_meta( 3, '_jat_search_intel_aeo_score', '75' );
		update_post_meta( 4, '_jat_search_intel_aeo_score', '40' );
		$posts = [ $this->post( 1, 1 ), $this->post( 2, 1 ), $this->post( 3, 1 ), $this->post( 4, 1 ) ];
		$this->assertSame( 11, GEO_Health::answers( $posts )['score'] ); // 15 × 3/4 = 11.25.
	}

	public function test_entity_lists_what_is_missing(): void {
		$none = GEO_Health::entity( [], [], 0.0 );
		$this->assertSame( 0, $none['score'] );
		$this->assertStringContainsString( 'logo', $none['detail'] );

		$all = GEO_Health::entity( [ 'knowledge_name' => 'Acme', 'knowledge_logo' => 'x' ], [ 'https://x.com/a' ], 1.0 );
		$this->assertSame( 15, $all['score'] );
		$this->assertStringNotContainsString( 'Missing', $all['detail'] );
	}

	public function test_structured_data(): void {
		$this->assertSame( 0, GEO_Health::structured_data( [] )['score'] );
		$this->assertSame( 7, GEO_Health::structured_data( [ 'enable_schema' => true ] )['score'] );
		$this->assertSame( 10, GEO_Health::structured_data( [ 'enable_schema' => true, 'enable_schema_types' => true ] )['score'] );
	}

	public function test_every_scored_factor_has_a_recommendation(): void {
		$result = GEO_Health::score( [], [], [ 'public' => true, 'now' => self::NOW ] );
		foreach ( $result['factors'] as $key => $factor ) {
			if ( $factor['max'] > 0 ) {
				$this->assertNotSame( '', GEO_Health::recommendation( $key ), $key );
			}
		}
	}

	public function test_grades(): void {
		$this->assertSame( 'A', GEO_Health::grade( 90 ) );
		$this->assertSame( 'B', GEO_Health::grade( 85 ) );
		$this->assertSame( 'F', GEO_Health::grade( 10 ) );
	}

	public function test_sample_posts_reads_recent_published_posts(): void {
		$GLOBALS['si_test_state']['posts'][7] = [ 'ID' => 7, 'post_type' => 'post', 'post_status' => 'publish', 'post_modified_gmt' => '2026-01-01 00:00:00', 'post_author' => 2 ];
		$sample = GEO_Health::sample_posts( [ 'enabled_post_types' => [ 'post' ] ] );
		$this->assertSame( [ [ 'id' => 7, 'modified' => strtotime( '2026-01-01 00:00:00 UTC' ), 'author' => 2 ] ], $sample );
	}
}
