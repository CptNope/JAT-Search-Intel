<?php
namespace JAT_Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\Headline_Analyzer;

class HeadlineAnalyzerTest extends TestCase {

	public function test_strong_headline_scores_high(): void {
		$result = Headline_Analyzer::analyze( 'How to Report a Scam to the FTC and FBI (Step by Step)' );
		$this->assertSame( 'how-to', $result['type'] );
		$this->assertGreaterThanOrEqual( 70, $result['score'] );
	}

	public function test_weak_headline_scores_low_with_suggestions(): void {
		$result   = Headline_Analyzer::analyze( 'Thoughts on the' );
		$improves = array_filter( $result['checks'], static function ( $c ) {
			return 'improve' === $c['status'];
		} );
		$this->assertLessThan( 50, $result['score'] );
		$this->assertGreaterThanOrEqual( 4, count( $improves ) );
	}

	public function test_types(): void {
		$this->assertSame( 'question', Headline_Analyzer::analyze( 'Is Your WordPress Site Violating the Privacy Act?' )['type'] );
		$this->assertSame( 'list', Headline_Analyzer::analyze( '10 Signs Your WordPress Site Has Been Hacked' )['type'] );
		$this->assertSame( 'list', Headline_Analyzer::analyze( 'Signs a Site Was Hacked (10 Signs)' )['type'] );
		$this->assertSame( 'guide', Headline_Analyzer::analyze( 'WordPress Hardening Guide for High-Risk Sites' )['type'] );
		$this->assertSame( 'general', Headline_Analyzer::analyze( 'Grand Royale Casino Simulator Launch' )['type'] );
	}

	public function test_counts_and_entities(): void {
		$result = Headline_Analyzer::analyze( 'Tips &amp; Tricks for <b>Faster</b> Sites' );
		$this->assertSame( 30, $result['characters'] );
		$this->assertSame( 5, $result['words'] );
	}

	public function test_empty_headline(): void {
		$result = Headline_Analyzer::analyze( '' );
		$this->assertSame( 0, $result['words'] );
		$this->assertLessThan( 30, $result['score'] );
	}

	public function test_score_is_capped(): void {
		$this->assertLessThanOrEqual( 100, Headline_Analyzer::analyze( '7 Proven Ways to Fix a Hacked WordPress Site Fast' )['score'] );
	}
}
