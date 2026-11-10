<?php
namespace JAT_Search_Intel\Tests\Unit;

use JAT_Search_Intel\AI_Engine;
use JAT_Search_Intel\Citation_Tracker;

class CitationTrackerTest extends ParityTestCase {

	private function tracker( array $settings = [] ): Citation_Tracker {
		return new Citation_Tracker( $settings + [ 'knowledge_name' => 'Acme Bakery' ] );
	}

	private function ok( array $json ): array {
		return [ 'response' => [ 'code' => 200 ], 'body' => (string) json_encode( $json ) ];
	}

	private static function openai_answer( string $url ): array {
		return [
			'output' => [
				[ 'type' => 'web_search_call', 'action' => [ 'type' => 'search', 'sources' => [ [ 'url' => 'https://example.com/guide/' ], [ 'url' => 'https://other.org/a' ] ] ] ],
				[
					'type'    => 'message',
					'content' => [
						[
							'type'        => 'output_text',
							'text'        => 'Acme Bakery has a good guide.',
							'annotations' => [ [ 'type' => 'url_citation', 'url' => $url, 'title' => 'x' ] ],
						],
					],
				],
			],
		];
	}

	// ── Questions ───────────────────────────────────────────────────────────

	public function test_add_questions_skips_blanks_duplicates_and_respects_the_limit(): void {
		$this->assertSame( 2, Citation_Tracker::add_questions( "Best sourdough starter?\n\n  best sourdough starter?  \n<b>How long to proof bread?</b>" ) );
		$this->assertSame( [ 'Best sourdough starter?', 'How long to proof bread?' ], array_column( Citation_Tracker::questions(), 'q' ) );

		$many = implode( "\n", array_map( static fn( $i ) => "Question number $i?", range( 1, 40 ) ) );
		Citation_Tracker::add_questions( $many );
		$this->assertCount( Citation_Tracker::MAX_QUESTIONS, Citation_Tracker::questions() );
	}

	public function test_remove_question_drops_its_results(): void {
		Citation_Tracker::add_questions( 'Is rye flour gluten free?' );
		$id = (string) array_key_first( Citation_Tracker::questions() );
		Citation_Tracker::store( $id, 'openai', [ 't' => 1, 'cited' => true ] );
		Citation_Tracker::remove_question( $id );
		$this->assertSame( [], Citation_Tracker::questions() );
		$this->assertArrayNotHasKey( $id, Citation_Tracker::results() );
	}

	// ── Requests ────────────────────────────────────────────────────────────

	public function test_requests_turn_on_web_search_for_every_engine(): void {
		$openai = Citation_Tracker::request( 'openai', 'Q?', 'k' );
		$this->assertSame( 'https://api.openai.com/v1/responses', $openai['url'] );
		$this->assertSame( 'web_search', $openai['body']['tools'][0]['type'] );
		$this->assertSame( 'Bearer k', $openai['headers']['Authorization'] );

		$claude = Citation_Tracker::request( 'anthropic', 'Q?', 'k' );
		$this->assertSame( 'web_search_20250305', $claude['body']['tools'][0]['type'] );
		$this->assertSame( 'k', $claude['headers']['x-api-key'] );

		$gemini = Citation_Tracker::request( 'gemini', 'Q?', 'k' );
		$this->assertStringContainsString( 'gemini-2.5-flash:generateContent', $gemini['url'] );
		$this->assertArrayHasKey( 'google_search', $gemini['body']['tools'][0] );
		$this->assertSame( '{"google_search":{}}', json_encode( $gemini['body']['tools'][0] ) );

		$pplx = Citation_Tracker::request( 'perplexity', 'Q?', 'k' );
		$this->assertSame( 'sonar', $pplx['body']['model'] );
	}

	public function test_models_can_be_filtered(): void {
		add_filter( 'jat_search_intel_citation_models', static fn( $m ) => [ 'openai' => 'gpt-6-astra' ] + $m );
		$this->assertSame( 'gpt-6-astra', Citation_Tracker::model( 'openai' ) );
		$this->assertSame( 'sonar', Citation_Tracker::model( 'perplexity' ) );
	}

	// ── Parsing ─────────────────────────────────────────────────────────────

	public function test_parse_openai(): void {
		$out = Citation_Tracker::parse( 'openai', self::openai_answer( 'https://example.com/guide/' ) );
		$this->assertSame( [ 'https://example.com/guide/' ], $out['cited'] );
		$this->assertContains( 'https://other.org/a', $out['sources'] );
		$this->assertSame( 'Acme Bakery has a good guide.', $out['text'] );
	}

	public function test_parse_anthropic(): void {
		$out = Citation_Tracker::parse(
			'anthropic',
			[
				'content' => [
					[ 'type' => 'server_tool_use', 'name' => 'web_search' ],
					[ 'type' => 'web_search_tool_result', 'content' => [ [ 'type' => 'web_search_result', 'url' => 'https://read.example.net/' ] ] ],
					[ 'type' => 'text', 'text' => 'Answer.', 'citations' => [ [ 'type' => 'web_search_result_location', 'url' => 'https://cited.org/x' ] ] ],
				],
			]
		);
		$this->assertSame( [ 'https://cited.org/x' ], $out['cited'] );
		$this->assertSame( [ 'https://read.example.net/', 'https://cited.org/x' ], $out['sources'] );
	}

	public function test_anthropic_search_error_is_not_mistaken_for_results(): void {
		$out = Citation_Tracker::parse( 'anthropic', [ 'content' => [ [ 'type' => 'web_search_tool_result', 'content' => [ 'type' => 'web_search_tool_result_error', 'error_code' => 'unavailable' ] ] ] ] );
		$this->assertSame( [], $out['sources'] );
	}

	public function test_parse_gemini_uses_the_title_for_redirect_links(): void {
		$out = Citation_Tracker::parse(
			'gemini',
			[
				'candidates' => [
					[
						'content'           => [ 'parts' => [ [ 'text' => 'Answer.' ] ] ],
						'groundingMetadata' => [
							'groundingChunks' => [
								[ 'web' => [ 'uri' => 'https://vertexaisearch.cloud.google.com/grounding-api-redirect/abc', 'title' => 'example.com' ] ],
								[ 'web' => [ 'uri' => 'https://direct.org/page', 'title' => 'Direct' ] ],
							],
						],
					],
				],
			]
		);
		$this->assertSame( [ 'https://example.com/', 'https://direct.org/page' ], $out['cited'] );
	}

	public function test_parse_perplexity(): void {
		$out = Citation_Tracker::parse(
			'perplexity',
			[
				'choices'        => [ [ 'message' => [ 'content' => 'Answer [1].' ] ] ],
				'citations'      => [ 'https://www.example.com/a' ],
				'search_results' => [ [ 'url' => 'https://www.example.com/a' ], [ 'url' => 'https://b.org/' ] ],
			]
		);
		$this->assertSame( [ 'https://www.example.com/a' ], $out['cited'] );
		$this->assertCount( 2, $out['sources'] );
	}

	// ── Evaluating ──────────────────────────────────────────────────────────

	public function test_evaluate_matches_the_site_with_or_without_www_and_on_subdomains(): void {
		$answer = [ 'text' => 'x', 'cited' => [ 'https://www.example.com/a', 'https://shop.example.com/b', 'https://notexample.com/' ], 'sources' => [] ];
		$out    = Citation_Tracker::evaluate( $answer, 'example.com', [] );
		$this->assertTrue( $out['cited'] );
		$this->assertSame( [ 'https://www.example.com/a', 'https://shop.example.com/b' ], $out['urls'] );
		$this->assertSame( [ 'notexample.com' ], $out['top'] );
	}

	public function test_evaluate_read_but_not_cited_and_mentions(): void {
		$answer = [ 'text' => 'Try ACME BAKERY in town.', 'cited' => [ 'https://a.org/', 'https://b.org/', 'https://a.org/2' ], 'sources' => [ 'https://example.com/x' ] ];
		$out    = Citation_Tracker::evaluate( $answer, 'example.com', [ 'Acme Bakery' ] );
		$this->assertFalse( $out['cited'] );
		$this->assertTrue( $out['source'] );
		$this->assertTrue( $out['mentioned'] );
		$this->assertSame( [ 'a.org', 'b.org' ], $out['top'], 'Most-cited domain first.' );
	}

	public function test_short_names_do_not_count_as_mentions(): void {
		$out = Citation_Tracker::evaluate( [ 'text' => 'ab is everywhere', 'cited' => [], 'sources' => [] ], 'example.com', [ 'ab' ] );
		$this->assertFalse( $out['mentioned'] );
	}

	// ── Checks ──────────────────────────────────────────────────────────────

	private function with_key( string $engine = 'openai' ): AI_Engine {
		$ai = new AI_Engine( [] );
		$ai->save_api_key( $engine, 'sk-test' );
		return $ai;
	}

	public function test_check_stores_a_cited_result(): void {
		Citation_Tracker::add_questions( 'Best bakery in town?' );
		$id = (string) array_key_first( Citation_Tracker::questions() );
		$GLOBALS['si_test_state']['remote_post_response'] = $this->ok( self::openai_answer( 'https://example.com/guide/' ) );

		$entry = $this->tracker()->check( $id, 'openai', $this->with_key() );
		$this->assertTrue( $entry['cited'] );
		$this->assertTrue( $entry['mentioned'] );
		$this->assertSame( $entry, Citation_Tracker::results()[ $id ]['openai'][0] );
		$this->assertSame( 'https://api.openai.com/v1/responses', $GLOBALS['si_test_state']['remote_posts'][0]['url'] );
	}

	public function test_check_records_errors_with_status(): void {
		Citation_Tracker::add_questions( 'Q one?' );
		$id = (string) array_key_first( Citation_Tracker::questions() );
		$GLOBALS['si_test_state']['remote_post_response'] = [ 'response' => [ 'code' => 401 ], 'body' => '{"error":{"message":"Invalid key"}}' ];
		$entry = $this->tracker()->check( $id, 'openai', $this->with_key() );
		$this->assertSame( 'Invalid key', $entry['error'] );
		$this->assertSame( 401, $entry['status'] );
	}

	public function test_check_without_key_fails_without_a_request(): void {
		Citation_Tracker::add_questions( 'Q one?' );
		$entry = $this->tracker()->check( (string) array_key_first( Citation_Tracker::questions() ), 'anthropic', new AI_Engine( [] ) );
		$this->assertArrayHasKey( 'error', $entry );
		$this->assertEmpty( $GLOBALS['si_test_state']['remote_posts'] ?? [] );
	}

	public function test_perplexity_falls_back_to_the_legacy_endpoint_on_404(): void {
		Citation_Tracker::add_questions( 'Q one?' );
		$GLOBALS['si_test_state']['remote_post_responses'] = [
			[ 'response' => [ 'code' => 404 ], 'body' => '' ],
			$this->ok( [ 'choices' => [ [ 'message' => [ 'content' => 'A' ] ] ], 'citations' => [ 'https://example.com/' ] ] ),
		];
		$entry = $this->tracker()->check( (string) array_key_first( Citation_Tracker::questions() ), 'perplexity', $this->with_key( 'perplexity' ) );
		$this->assertTrue( $entry['cited'] );
		$this->assertSame( 'https://api.perplexity.ai/chat/completions', $GLOBALS['si_test_state']['remote_posts'][1]['url'] );
	}

	public function test_history_is_capped(): void {
		for ( $i = 0; $i < Citation_Tracker::HISTORY + 3; $i++ ) {
			Citation_Tracker::store( 'q', 'openai', [ 't' => $i ] );
		}
		$history = Citation_Tracker::results()['q']['openai'];
		$this->assertCount( Citation_Tracker::HISTORY, $history );
		$this->assertSame( Citation_Tracker::HISTORY + 2, $history[0]['t'], 'Newest first.' );
	}

	// ── Queue ───────────────────────────────────────────────────────────────

	public function test_queue_covers_every_question_on_engines_with_keys(): void {
		Citation_Tracker::add_questions( "Q one?\nQ two?" );
		$this->with_key( 'openai' );
		$this->with_key( 'gemini' );
		$this->assertSame( 4, $this->tracker()->queue_all() );
		$this->assertArrayHasKey( Citation_Tracker::BATCH_HOOK, $GLOBALS['si_test_state']['single_events'] );
	}

	public function test_engines_can_be_switched_off(): void {
		$this->with_key( 'openai' );
		$this->with_key( 'gemini' );
		update_option( Citation_Tracker::ENGINES_OPTION, [ 'gemini' ] );
		$this->assertSame( [ 'gemini' ], $this->tracker()->active_engines() );
	}

	public function test_run_batch_stops_an_engine_whose_key_is_rejected(): void {
		Citation_Tracker::add_questions( "Q one?\nQ two?\nQ three?" );
		$this->with_key( 'openai' );
		$this->tracker()->queue_all();
		$GLOBALS['si_test_state']['remote_post_response'] = [ 'response' => [ 'code' => 401 ], 'body' => '{}' ];

		$this->assertSame( 1, $this->tracker()->run_batch() );
		$this->assertSame( [], get_option( Citation_Tracker::QUEUE_OPTION ) );
		$this->assertCount( 1, $GLOBALS['si_test_state']['remote_posts'] );
	}

	public function test_run_batch_continues_later_when_work_remains(): void {
		Citation_Tracker::add_questions( implode( "\n", array_map( static fn( $i ) => "Q $i?", range( 1, Citation_Tracker::BATCH_SIZE + 2 ) ) ) );
		$this->with_key( 'openai' );
		$this->tracker()->queue_all();
		unset( $GLOBALS['si_test_state']['single_events'] );
		$GLOBALS['si_test_state']['remote_post_response'] = $this->ok( self::openai_answer( 'https://other.org/' ) );

		$this->assertSame( Citation_Tracker::BATCH_SIZE, $this->tracker()->run_batch() );
		$this->assertCount( 2, get_option( Citation_Tracker::QUEUE_OPTION ) );
		$this->assertArrayHasKey( Citation_Tracker::BATCH_HOOK, $GLOBALS['si_test_state']['single_events'] );
	}

	// ── Summary ─────────────────────────────────────────────────────────────

	public function test_summary_counts_share_lost_citations_and_competitors(): void {
		Citation_Tracker::add_questions( "Q one?\nQ two?" );
		[ $one, $two ] = array_keys( Citation_Tracker::questions() );
		Citation_Tracker::store( $one, 'openai', [ 't' => 1, 'cited' => true, 'urls' => [ 'https://example.com/guide/' ], 'top' => [] ] );
		Citation_Tracker::store( $one, 'openai', [ 't' => 2, 'error' => 'timeout' ] );
		Citation_Tracker::store( $one, 'openai', [ 't' => 3, 'cited' => false, 'top' => [ 'rival.com' ] ] );
		Citation_Tracker::store( $two, 'openai', [ 't' => 3, 'cited' => true, 'mentioned' => true, 'urls' => [ 'https://example.com/b/' ], 'top' => [ 'rival.com' ] ] );

		$summary = Citation_Tracker::summary();
		$this->assertSame( 2, $summary['checked'] );
		$this->assertSame( 1, $summary['cited'] );
		$this->assertSame( 1, $summary['mentioned'] );
		$this->assertSame( [ 'rival.com' => 2 ], $summary['competitors'] );
		$this->assertSame( [ [ 'question' => 'Q one?', 'engine' => 'openai', 'urls' => [ 'https://example.com/guide/' ] ] ], $summary['lost'], 'Errors are skipped when comparing with the previous check.' );

		$GLOBALS['si_test_state']['url_to_postid']['https://example.com/guide/'] = 42;
		$this->assertSame( [ 42 => [ 'Q one?' ] ], Citation_Tracker::lost_citation_posts() );
	}

	public function test_constructor_schedules_weekly_checks_only_when_enabled(): void {
		$this->tracker();
		$this->assertArrayNotHasKey( Citation_Tracker::CRON_HOOK, $GLOBALS['si_test_state']['scheduled_events'] ?? [] );
		$this->tracker( [ 'enable_citation_tracking' => true ] );
		$this->assertArrayHasKey( Citation_Tracker::CRON_HOOK, $GLOBALS['si_test_state']['scheduled_events'] );
	}

	public function test_ability_reports_results_for_agents(): void {
		Citation_Tracker::add_questions( 'Q one?' );
		$id = (string) array_key_first( Citation_Tracker::questions() );
		Citation_Tracker::store( $id, 'openai', [ 't' => 1800000000, 'cited' => true, 'source' => true, 'mentioned' => false, 'urls' => [ 'https://example.com/a/' ], 'top' => [ 'rival.com' ] ] );
		Citation_Tracker::store( $id, 'gemini', [ 't' => 1800000000, 'error' => 'quota' ] );

		$out = ( new \JAT_Search_Intel\Abilities( \JAT_Search_Intel\Core::get_default_settings() ) )->get_ai_citations();
		$this->assertSame( 1.0, $out['citation_share'] );
		$this->assertTrue( $out['questions'][0]['engines']['openai']['cited'] );
		$this->assertSame( [ 'https://example.com/a/' ], $out['questions'][0]['engines']['openai']['pages'] );
		$this->assertSame( [ 'error' => 'quota' ], $out['questions'][0]['engines']['gemini'] );
	}
}
