<?php
namespace JAT_Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\FAQ_Generator;
use JAT_Search_Intel\QA_Detector;

class FAQGeneratorTest extends TestCase {

	private FAQ_Generator $generator;

	protected function setUp(): void {
		global $si_test_state;
		$si_test_state['options']    = [];
		$si_test_state['post_meta']  = [];
		$si_test_state['posts']      = [];
		$si_test_state['transients'] = [];

		$detector = new QA_Detector( [ 'enable_qa_detector' => true ] );

		$this->generator = new FAQ_Generator( [
			'enable_faq_generator' => true,
			'enabled_post_types'   => [ 'post', 'page' ],
			'faq_ai_api_key'       => '',
		], $detector );
	}

	// ── extract_from_headings ────────────────────────────────────────────────

	public function test_extract_from_headings_h2_question(): void {
		$content = '<h2>What is SEO?</h2><p>SEO stands for Search Engine Optimization and is the practice of improving website visibility in search results.</p>';

		$faqs = $this->generator->extract_from_headings( $content );

		$this->assertCount( 1, $faqs );
		$this->assertEquals( 'What is SEO?', $faqs[0]['question'] );
		$this->assertStringContainsString( 'Search Engine Optimization', $faqs[0]['answer'] );
		$this->assertEquals( 'heading', $faqs[0]['source'] );
		$this->assertTrue( $faqs[0]['editable'] );
	}

	public function test_extract_from_headings_multiple(): void {
		$content = '<h2>What is PHP?</h2><p>PHP is a server-side scripting language used for web development and backend logic.</p>'
			. '<h3>How does it work?</h3><p>PHP code is executed on the server, generating HTML that is then sent to the client browser.</p>';

		$faqs = $this->generator->extract_from_headings( $content );
		$this->assertCount( 2, $faqs );
	}

	public function test_extract_from_headings_skips_short_answer(): void {
		$content = '<h2>Why?</h2><p>Short.</p>';

		$faqs = $this->generator->extract_from_headings( $content );
		$this->assertEmpty( $faqs );
	}

	// ── extract_qa_patterns ──────────────────────────────────────────────────

	public function test_extract_qa_patterns(): void {
		$content = 'Q: What is caching? A: Caching is storing copies of files in a temporary location for faster subsequent access.';

		$faqs = $this->generator->extract_qa_patterns( $content );

		$this->assertCount( 1, $faqs );
		$this->assertEquals( 'What is caching?', $faqs[0]['question'] );
		$this->assertEquals( 'qa_pattern', $faqs[0]['source'] );
	}

	public function test_extract_qa_patterns_multiple(): void {
		$content = 'Q: First? A: First answer is long enough to pass the minimum length check. Q: Second? A: Second answer is also sufficiently long for testing purposes here.';

		$faqs = $this->generator->extract_qa_patterns( $content );
		$this->assertCount( 2, $faqs );
	}

	// ── generate_from_headings (implicit questions) ──────────────────────────

	public function test_generate_from_headings_benefits(): void {
		$content = '<h2>Benefits of SEO</h2><p>SEO provides many advantages including increased visibility, more organic traffic, and better user engagement for your website.</p>';

		$faqs = $this->generator->generate_from_headings( $content );

		$this->assertCount( 1, $faqs );
		$this->assertEquals( 'What are the benefits of seo?', $faqs[0]['question'] );
		$this->assertEquals( 'implicit', $faqs[0]['source'] );
	}

	public function test_generate_from_headings_how_to(): void {
		$content = '<h2>How to Install WordPress</h2><p>You can install WordPress by downloading the latest version from wordpress.org and uploading it to your server via FTP.</p>';

		$faqs = $this->generator->generate_from_headings( $content );

		$this->assertCount( 1, $faqs );
		$this->assertEquals( 'How to Install WordPress?', $faqs[0]['question'] );
	}

	public function test_generate_from_headings_types_of(): void {
		$content = '<h2>Types of SEO</h2><p>There are several types of SEO including on-page, off-page, and technical SEO that you should know about.</p>';

		$faqs = $this->generator->generate_from_headings( $content );

		$this->assertCount( 1, $faqs );
		$this->assertEquals( 'What are the types of seo?', $faqs[0]['question'] );
	}

	public function test_generate_from_headings_vs_comparison(): void {
		$content = '<h2>React vs Angular</h2><p>React and Angular are both popular JavaScript frameworks for building user interfaces with different approaches.</p>';

		$faqs = $this->generator->generate_from_headings( $content );

		$this->assertCount( 1, $faqs );
		$this->assertStringContainsString( 'difference between', $faqs[0]['question'] );
	}

	public function test_generate_from_headings_short_topic(): void {
		$content = '<h2>Caching</h2><p>Caching is an important web performance optimization technique that speeds up page load times significantly.</p>';

		$faqs = $this->generator->generate_from_headings( $content );

		$this->assertCount( 1, $faqs );
		$this->assertEquals( 'What is Caching?', $faqs[0]['question'] );
	}

	public function test_generate_from_headings_skips_intro_conclusion(): void {
		$content = '<h2>Introduction</h2><p>This article covers many important topics related to web development and optimization strategies.</p>';

		$faqs = $this->generator->generate_from_headings( $content );
		$this->assertEmpty( $faqs );
	}

	public function test_generate_from_headings_skips_short_answer(): void {
		$content = '<h2>Performance</h2><p>It is fast.</p>';

		$faqs = $this->generator->generate_from_headings( $content );
		$this->assertEmpty( $faqs );
	}

	// ── heading_to_question ──────────────────────────────────────────────────

	public function test_heading_to_question_why(): void {
		$this->assertEquals( 'Why SEO Matters?', $this->generator->heading_to_question( 'Why SEO Matters' ) );
	}

	public function test_heading_to_question_long_heading_returns_empty(): void {
		$this->assertEquals( '', $this->generator->heading_to_question( 'This is a very long heading with many words that exceeds the limit' ) );
	}

	// ── generate (full pipeline) ─────────────────────────────────────────────

	public function test_generate_combines_all_sources(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID'           => 1,
			'post_title'   => 'SEO Guide',
			'post_content' => '<h2>What is SEO?</h2><p>SEO stands for Search Engine Optimization and improves your website visibility in search engine results.</p>'
				. '<h2>Benefits of SEO</h2><p>SEO provides many advantages including increased organic traffic, better user engagement, and brand visibility.</p>'
				. '<p>SEO is defined as the practice of optimizing websites for better search engine rankings.</p>',
			'post_status'  => 'publish',
			'post_type'    => 'post',
			'post_author'  => 1,
		];

		$faqs = $this->generator->generate( 1 );

		$this->assertNotEmpty( $faqs );
		// Should have heading question + implicit question + definition.
		$sources = array_column( $faqs, 'source' );
		$this->assertContains( 'heading', $sources );
	}

	public function test_generate_empty_for_missing_post(): void {
		$this->assertEmpty( $this->generator->generate( 999 ) );
	}

	public function test_generate_empty_for_empty_content(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Empty', 'post_content' => '',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];

		$this->assertEmpty( $this->generator->generate( 1 ) );
	}

	public function test_generate_deduplicates(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID'           => 1,
			'post_title'   => 'Test',
			'post_content' => '<h2>What is SEO?</h2><p>SEO is defined as the process of improving website ranking in search engines and visibility online.</p>',
			'post_status'  => 'publish',
			'post_type'    => 'post',
			'post_author'  => 1,
		];

		$faqs = $this->generator->generate( 1 );

		// "What is SEO?" from heading and definition should be deduped.
		$questions = array_column( $faqs, 'question' );
		$lower     = array_map( function ( $q ) { return strtolower( preg_replace( '/\W+/', '', $q ) ); }, $questions );
		$this->assertCount( count( array_unique( $lower ) ), $faqs );
	}

	// ── enhance_with_ai ──────────────────────────────────────────────────────

	public function test_enhance_with_ai_returns_empty_without_key(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Test', 'post_content' => 'Content here.',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];

		$result = $this->generator->enhance_with_ai( 1, '' );
		$this->assertEmpty( $result );
	}

	public function test_enhance_with_ai_parses_response(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'SEO Guide', 'post_content' => 'Content about SEO optimization.',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];
		$si_test_state['remote_post_response'] = [
			'response' => [ 'code' => 200, 'message' => 'OK' ],
			'body'     => json_encode( [
				'choices' => [
					[
						'message' => [
							'content' => json_encode( [
								[ 'question' => 'What is SEO?', 'answer' => 'SEO is search engine optimization.' ],
								[ 'question' => 'Why is SEO important?', 'answer' => 'SEO drives organic traffic.' ],
							] ),
						],
					],
				],
			] ),
		];

		// Set up AI Engine with properly encrypted key.
		$si_test_state['options']['jat_search_intel_settings'] = [ 'ai_provider' => 'openai' ];

		// Reset Core singleton so it picks up the fresh state.
		$ref = new \ReflectionClass( \JAT_Search_Intel\Core::class );
		$prop = $ref->getProperty( 'instance' );
		$prop->setAccessible( true );
		$prop->setValue( null, null );

		// Use AI Engine's own encryption to store the key.
		\JAT_Search_Intel\Core::get_instance()->ai_engine->save_api_key( 'openai', 'sk-test-key' );

		$result = $this->generator->enhance_with_ai( 1 );

		$this->assertCount( 2, $result );
		$this->assertEquals( 'What is SEO?', $result[0]['question'] );
		$this->assertEquals( 'ai', $result[0]['source'] );

		// Clean up singleton.
		$prop->setValue( null, null );
	}

	public function test_enhance_with_ai_handles_invalid_json(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Test', 'post_content' => 'Content.',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];
		$si_test_state['remote_post_response'] = [
			'response' => [ 'code' => 200, 'message' => 'OK' ],
			'body'     => json_encode( [
				'choices' => [
					[ 'message' => [ 'content' => 'not valid json' ] ],
				],
			] ),
		];

		// Set up AI Engine with properly encrypted key.
		$si_test_state['options']['jat_search_intel_settings'] = [ 'ai_provider' => 'openai' ];

		$ref = new \ReflectionClass( \JAT_Search_Intel\Core::class );
		$prop = $ref->getProperty( 'instance' );
		$prop->setAccessible( true );
		$prop->setValue( null, null );

		\JAT_Search_Intel\Core::get_instance()->ai_engine->save_api_key( 'openai', 'sk-test-key' );

		$result = $this->generator->enhance_with_ai( 1 );
		$this->assertEmpty( $result );

		$prop->setValue( null, null );
	}

	// ── Disabled ─────────────────────────────────────────────────────────────

	public function test_disabled_does_not_register_metabox(): void {
		global $si_test_state;
		$si_test_state['actions'] = [];

		$gen = new FAQ_Generator( [ 'enable_faq_generator' => false ] );

		// AJAX handlers are always registered (used by the main metabox AEO tab).
		$this->assertArrayHasKey( 'wp_ajax_jat_search_intel_generate_faqs', $si_test_state['actions'] ?? [] );

		// But the separate metabox should NOT be registered when disabled.
		$this->assertArrayNotHasKey( 'add_meta_boxes', $si_test_state['actions'] ?? [] );
	}
}
