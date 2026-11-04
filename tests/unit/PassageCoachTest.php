<?php
namespace JAT_Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\Passage_Coach;

/**
 * Passage-level suggestions.
 */
class PassageCoachTest extends TestCase {

	private function types( array $suggestions ): array {
		return array_column( $suggestions, 'type' );
	}

	private function p( string $text ): string {
		return "<!-- wp:paragraph -->\n<p>{$text}</p>\n<!-- /wp:paragraph -->\n\n";
	}

	private function h( string $text, int $level = 2 ): string {
		$attrs = 2 === $level ? '' : ' {"level":' . $level . '}';
		return "<!-- wp:heading{$attrs} -->\n<h{$level} class=\"wp-block-heading\">{$text}</h{$level}>\n<!-- /wp:heading -->\n\n";
	}

	private function words( int $n, string $word = 'word' ): string {
		return implode( ' ', array_fill( 0, $n, $word ) );
	}

	public function test_slow_intro_is_flagged_with_insert_action(): void {
		$s = Passage_Coach::analyze( $this->p( 'In this article we will look at running shoes.' ) . $this->p( 'More.' ) );
		$intro = array_values( array_filter( $s, static function ( $x ) { return 'intro-slow' === $x['type']; } ) );

		$this->assertCount( 1, $intro );
		$this->assertSame( 0, $intro[0]['block'] );
		$this->assertSame( 'insert-intro', $intro[0]['action'] );
		$this->assertSame( 'high', $intro[0]['severity'] );
	}

	public function test_heading_first_means_missing_intro(): void {
		$this->assertContains( 'intro-missing', $this->types( Passage_Coach::analyze( $this->h( 'Setup' ) . $this->p( 'Text.' ) ) ) );
	}

	public function test_good_intro_passes(): void {
		$types = $this->types( Passage_Coach::analyze( $this->p( 'Running shoes last 500 to 800 kilometres for most runners.' ), [ 'keyword' => 'running shoes' ] ) );
		$this->assertNotContains( 'intro-slow', $types );
		$this->assertNotContains( 'intro-long', $types );
		$this->assertNotContains( 'intro-keyword', $types );
	}

	public function test_question_heading_followed_by_list_is_unanswered(): void {
		$content = $this->p( 'Short answer first.' ) . $this->h( 'How long do shoes last?' ) . "<!-- wp:list -->\n<ul><li>a</li></ul>\n<!-- /wp:list -->\n\n";
		$s       = Passage_Coach::analyze( $content );
		$q       = array_values( array_filter( $s, static function ( $x ) { return 'question-unanswered' === $x['type']; } ) );
		$this->assertSame( 1, $q[0]['block'], 'Points at the heading block' );
	}

	public function test_unsourced_statistic_and_sourced_one(): void {
		$content = $this->p( 'Intro answer.' ) . $this->p( 'About 45% of runners replace shoes too late.' ) . $this->p( 'Some 30% do, according to <a href="https://example.org/study">a study</a>.' );
		$stats   = array_values( array_filter( Passage_Coach::analyze( $content ), static function ( $x ) { return 'stat-unsourced' === $x['type']; } ) );
		$this->assertCount( 1, $stats );
		$this->assertSame( 1, $stats[0]['block'] );
	}

	public function test_comparison_without_table(): void {
		$content = $this->p( 'Answer.' ) . $this->h( 'Nike vs Adidas' ) . $this->p( 'Text.' );
		$s       = Passage_Coach::analyze( $content, [ 'title' => 'Nike vs Adidas running shoes' ] );
		$t       = array_values( array_filter( $s, static function ( $x ) { return 'comparison-table' === $x['type']; } ) );
		$this->assertSame( 'insert-table', $t[0]['action'] );
		$this->assertSame( 1, $t[0]['block'], 'Inserted after the first H2' );

		$with_table = $content . "<!-- wp:table -->\n<figure class=\"wp-block-table\"><table><tr><td>a</td></tr></table></figure>\n<!-- /wp:table -->";
		$this->assertNotContains( 'comparison-table', $this->types( Passage_Coach::analyze( $with_table, [ 'title' => 'Nike vs Adidas' ] ) ) );
	}

	public function test_long_paragraph_and_structure_and_sources(): void {
		$content = $this->p( 'Answer.' ) . $this->p( $this->words( 700 ) );
		$types   = $this->types( Passage_Coach::analyze( $content, [ 'site_host' => 'example.test' ] ) );
		$this->assertContains( 'long-paragraph', $types );
		$this->assertContains( 'few-headings', $types );
		$this->assertContains( 'no-sources', $types );
		$this->assertContains( 'ai-summary', $types );
	}

	public function test_internal_links_do_not_count_as_sources(): void {
		$content = $this->p( 'Answer.' ) . $this->p( $this->words( 400 ) . ' <a href="https://example.test/other">more</a>' );
		$this->assertContains( 'no-sources', $this->types( Passage_Coach::analyze( $content, [ 'site_host' => 'example.test' ] ) ) );
	}

	public function test_old_year_flagged(): void {
		$this->assertContains( 'stale-year', $this->types( Passage_Coach::analyze( $this->p( 'Answer.' ) . $this->p( 'Prices rose sharply in 2019.' ) ) ) );
	}

	public function test_whitespace_blocks_do_not_shift_indexes(): void {
		$blocks = Passage_Coach::top_level_blocks( "\n\n" . $this->p( 'One.' ) . "\n\n\n" . $this->p( 'Two.' ) );
		$this->assertCount( 2, $blocks );
		$this->assertSame( 'Two.', $blocks[1]['text'] );
	}

	public function test_classic_content_points_at_single_block(): void {
		$s = Passage_Coach::analyze( "<h2>Intro</h2>\n<p>About 45% of people.</p>" );
		$this->assertNotEmpty( $s );
		foreach ( $s as $item ) {
			$this->assertContains( $item['block'], [ -1, 0 ] );
		}
	}

	public function test_empty_content(): void {
		$this->assertSame( [], Passage_Coach::analyze( '' ) );
	}

	public function test_results_sorted_by_severity(): void {
		$s    = Passage_Coach::analyze( $this->p( 'In this post, ' . $this->words( 200 ) ) );
		$rank = [ 'high' => 0, 'medium' => 1, 'low' => 2 ];
		$prev = 0;
		foreach ( $s as $item ) {
			$this->assertGreaterThanOrEqual( $prev, $rank[ $item['severity'] ] );
			$prev = $rank[ $item['severity'] ];
		}
	}
}
