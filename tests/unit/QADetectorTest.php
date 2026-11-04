<?php
namespace JAT_Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\QA_Detector;

class QADetectorTest extends TestCase {

	private QA_Detector $detector;

	protected function setUp(): void {
		global $si_test_state;
		$si_test_state['options']   = [];
		$si_test_state['post_meta'] = [];
		$si_test_state['posts']     = [];
		$si_test_state['transients'] = [];

		$this->detector = new QA_Detector( [
			'enable_qa_detector' => true,
		] );
	}

	// ── Heading Questions ────────────────────────────────────────────────────

	public function test_detect_heading_questions_h2(): void {
		$content = '<h2>What is SEO?</h2><p>SEO stands for Search Engine Optimization and is the practice of improving website visibility.</p>';

		$results = $this->detector->detect_heading_questions( $content );

		$this->assertCount( 1, $results );
		$this->assertEquals( 'What is SEO?', $results[0]['question'] );
		$this->assertStringContainsString( 'Search Engine Optimization', $results[0]['answer'] );
		$this->assertEquals( 'heading_question', $results[0]['source'] );
		$this->assertEquals( 'h2', $results[0]['heading'] );
	}

	public function test_detect_heading_questions_h3(): void {
		$content = '<h3>How does AEO work?</h3><p>Answer Engine Optimization focuses on providing direct answers to user queries in a structured format.</p>';

		$results = $this->detector->detect_heading_questions( $content );

		$this->assertCount( 1, $results );
		$this->assertEquals( 'How does AEO work?', $results[0]['question'] );
		$this->assertEquals( 'h3', $results[0]['heading'] );
	}

	public function test_detect_heading_questions_multiple(): void {
		$content = '<h2>What is PHP?</h2><p>PHP is a server-side scripting language for web development.</p>'
			. '<h2>What is JavaScript?</h2><p>JavaScript is a client-side programming language for interactive web pages.</p>';

		$results = $this->detector->detect_heading_questions( $content );
		$this->assertCount( 2, $results );
	}

	public function test_detect_heading_questions_skips_non_question(): void {
		$content = '<h2>Getting Started</h2><p>This guide will help you begin with the basics of web development.</p>';

		$results = $this->detector->detect_heading_questions( $content );
		$this->assertEmpty( $results );
	}

	public function test_detect_heading_questions_skips_short_answer(): void {
		$content = '<h2>Why?</h2><p>Because.</p>';

		$results = $this->detector->detect_heading_questions( $content );
		$this->assertEmpty( $results );
	}

	// ── Q&A Patterns ─────────────────────────────────────────────────────────

	public function test_detect_qa_patterns_q_a_format(): void {
		$content = 'Q: What is WordPress? A: WordPress is a content management system used by millions of websites worldwide.';

		$results = $this->detector->detect_qa_patterns( $content );

		$this->assertCount( 1, $results );
		$this->assertEquals( 'What is WordPress?', $results[0]['question'] );
		$this->assertStringContainsString( 'content management system', $results[0]['answer'] );
	}

	public function test_detect_qa_patterns_question_answer_format(): void {
		$content = 'Question: How do I install plugins? Answer: You can install plugins through the WordPress admin dashboard by navigating to the Plugins section.';

		$results = $this->detector->detect_qa_patterns( $content );

		$this->assertCount( 1, $results );
		$this->assertEquals( 'How do I install plugins?', $results[0]['question'] );
	}

	public function test_detect_qa_patterns_bold_question(): void {
		$content = '<strong>What is caching?</strong> Caching is the process of storing copies of files in a temporary location for faster access.';

		$results = $this->detector->detect_qa_patterns( $content );

		$this->assertCount( 1, $results );
		$this->assertEquals( 'What is caching?', $results[0]['question'] );
		$this->assertEquals( 'bold_question', $results[0]['source'] );
	}

	public function test_detect_qa_patterns_multiple(): void {
		$content = 'Q: First question? A: First answer that is long enough to be valid and meaningful. Q: Second question? A: Second answer that is also sufficiently long for testing purposes.';

		$results = $this->detector->detect_qa_patterns( $content );
		$this->assertCount( 2, $results );
	}

	// ── Definitions ──────────────────────────────────────────────────────────

	public function test_detect_definitions_defined_as(): void {
		$content = 'SEO is defined as the practice of optimizing web content for search engines.';

		$results = $this->detector->detect_definitions( $content );

		$this->assertCount( 1, $results );
		$this->assertEquals( 'SEO', $results[0]['term'] );
		$this->assertStringContainsString( 'optimizing web content', $results[0]['definition'] );
	}

	public function test_detect_definitions_refers_to(): void {
		$content = 'Machine Learning refers to the subset of artificial intelligence that enables systems to learn from data.';

		$results = $this->detector->detect_definitions( $content );

		$this->assertCount( 1, $results );
		$this->assertEquals( 'Machine Learning', $results[0]['term'] );
	}

	public function test_detect_definitions_type_of(): void {
		$content = 'Python is a type of programming language designed for readability.';

		$results = $this->detector->detect_definitions( $content );

		$this->assertCount( 1, $results );
		$this->assertEquals( 'Python', $results[0]['term'] );
	}

	public function test_detect_definitions_empty_without_patterns(): void {
		$content = 'This content talks about various topics without defining anything.';

		$results = $this->detector->detect_definitions( $content );
		$this->assertEmpty( $results );
	}

	// ── Steps ────────────────────────────────────────────────────────────────

	public function test_detect_steps_numbered(): void {
		$content = 'Step 1: Install WordPress on your server. Step 2: Choose and activate a theme. Step 3: Configure your site settings.';

		$results = $this->detector->detect_steps( $content );

		$this->assertCount( 3, $results );
		$this->assertEquals( 1, $results[0]['number'] );
		$this->assertStringContainsString( 'Install WordPress', $results[0]['text'] );
		$this->assertEquals( 'step_prefix', $results[0]['source'] );
	}

	public function test_detect_steps_ordered_list(): void {
		$content = '<ol><li>Download the plugin ZIP file from the repository.</li><li>Upload it via WordPress admin interface.</li><li>Activate the plugin from the plugins page.</li></ol>';

		$results = $this->detector->detect_steps( $content );

		$this->assertGreaterThanOrEqual( 3, count( $results ) );
	}

	public function test_detect_steps_empty_without_patterns(): void {
		$content = '<p>Just a paragraph with no steps at all.</p>';

		$results = $this->detector->detect_steps( $content );
		$this->assertEmpty( $results );
	}

	// ── Comparisons ──────────────────────────────────────────────────────────

	public function test_detect_comparisons_vs_pattern(): void {
		$content = 'When comparing React vs Angular, both have their strengths.';

		$results = $this->detector->detect_comparisons( $content );

		$this->assertCount( 1, $results );
		$this->assertEquals( 'React', $results[0]['item_a'] );
		$this->assertEquals( 'Angular', $results[0]['item_b'] );
		$this->assertEquals( 'vs_pattern', $results[0]['source'] );
	}

	public function test_detect_comparisons_difference_between(): void {
		$content = 'The difference between SEO and SEM is that SEO is organic while SEM includes paid.';

		$results = $this->detector->detect_comparisons( $content );

		$this->assertCount( 1, $results );
		$this->assertEquals( 'SEO', $results[0]['item_a'] );
		$this->assertEquals( 'SEM', $results[0]['item_b'] );
		$this->assertEquals( 'difference_pattern', $results[0]['source'] );
	}

	public function test_detect_comparisons_empty_without_patterns(): void {
		$content = 'This article discusses web development best practices.';

		$results = $this->detector->detect_comparisons( $content );
		$this->assertEmpty( $results );
	}

	// ── detect_all ───────────────────────────────────────────────────────────

	public function test_detect_all_returns_all_categories(): void {
		$content = '<h2>What is PHP?</h2><p>PHP is a server-side scripting language used for web development.</p>';

		$results = $this->detector->detect_all( $content );

		$this->assertArrayHasKey( 'questions', $results );
		$this->assertArrayHasKey( 'qa_pairs', $results );
		$this->assertArrayHasKey( 'definitions', $results );
		$this->assertArrayHasKey( 'steps', $results );
		$this->assertArrayHasKey( 'comparisons', $results );
	}

	// ── classify ─────────────────────────────────────────────────────────────

	public function test_classify_faq_eligible(): void {
		$content = '<h2>What is SEO?</h2><p>SEO stands for Search Engine Optimization and helps improve visibility.</p>'
			. '<h2>What is AEO?</h2><p>AEO stands for Answer Engine Optimization and focuses on direct answers.</p>';

		$eligible = $this->detector->classify( $content );

		$types = array_column( $eligible, 'type' );
		$this->assertContains( 'FAQPage', $types );
	}

	public function test_classify_howto_eligible(): void {
		$content = 'Step 1: Install the plugin from repository. Step 2: Activate from the plugins page. Step 3: Configure the settings. Step 4: Start optimizing.';

		$eligible = $this->detector->classify( $content );

		$types = array_column( $eligible, 'type' );
		$this->assertContains( 'HowTo', $types );
	}

	public function test_classify_definition_eligible(): void {
		$content = 'SEO is defined as the process of improving the quality and quantity of website traffic.';

		$eligible = $this->detector->classify( $content );

		$types = array_column( $eligible, 'type' );
		$this->assertContains( 'DefinedTerm', $types );
	}

	public function test_classify_empty_for_plain_content(): void {
		$content = '<p>This is a simple paragraph with no special patterns.</p>';

		$eligible = $this->detector->classify( $content );
		$this->assertEmpty( $eligible );
	}

	public function test_classify_sorted_by_confidence(): void {
		$content = '<h2>What is PHP?</h2><p>PHP is a server-side scripting language for web development and server tasks.</p>'
			. '<h2>What is JS?</h2><p>JavaScript is a client-side language used for interactive web experiences.</p>'
			. '<h2>What is CSS?</h2><p>CSS is a styling language for controlling the visual presentation of web pages.</p>';

		$eligible = $this->detector->classify( $content );

		if ( count( $eligible ) >= 2 ) {
			$this->assertGreaterThanOrEqual( $eligible[1]['confidence'], $eligible[0]['confidence'] );
		}
	}

	// ── auto_inject_schema ───────────────────────────────────────────────────

	public function test_auto_inject_schema_skips_with_manual_faqs(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Test', 'post_content' => '<h2>What is X?</h2><p>X is a thing.</p>',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];
		$si_test_state['post_meta'][1]['_jat_search_intel_faqs'] = [
			[ 'question' => 'Manual Q', 'answer' => 'Manual A' ],
		];

		$schema = [ '@type' => 'Article' ];
		$result = $this->detector->auto_inject_schema( $schema, 1 );

		$this->assertEquals( $schema, $result );
	}

	public function test_auto_inject_schema_returns_schema_unchanged(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Test', 'post_content' => '<p>Simple content here.</p>',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];

		$schema = [ '@type' => 'Article' ];
		$result = $this->detector->auto_inject_schema( $schema, 1 );

		$this->assertEquals( 'Article', $result['@type'] );
	}

	public function test_disabled_does_not_register(): void {
		global $si_test_state;
		$si_test_state['filters'] = [];

		$detector = new QA_Detector( [ 'enable_qa_detector' => false ] );

		$this->assertArrayNotHasKey( 'jat_search_intel_singular_schema', $si_test_state['filters'] ?? [] );
	}
}
