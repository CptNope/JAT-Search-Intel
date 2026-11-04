<?php
namespace JAT_Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\Performance_Report;

/**
 * Page Performance report.
 */
class PerformanceReportTest extends TestCase {

	public function test_sources_merge_on_normalised_path(): void {
		$rows = Performance_Report::merge(
			[ '/guide/' => 3 ],
			[ '/guide' => 10, '/other/?utm=x' => 2 ],
			[ 'https://example.test/guide/' => [ 40, 900 ] ]
		);

		$this->assertSame( [ '/guide/', '/other/' ], array_keys( $rows ) );
		$this->assertSame( 3, $rows['/guide/']['ai_referrals'] );
		$this->assertSame( 10, $rows['/guide/']['ai_crawls'] );
		$this->assertSame( 40, $rows['/guide/']['clicks'] );
		$this->assertSame( 900, $rows['/guide/']['impressions'] );
	}

	public function test_file_paths_keep_no_trailing_slash(): void {
		$this->assertSame( '/llms.txt', Performance_Report::normalize_path( '/llms.txt' ) );
		$this->assertSame( '/', Performance_Report::normalize_path( 'https://example.test' ) );
	}

	public function test_next_step_priorities(): void {
		$this->assertStringContainsString( 'refresh', Performance_Report::next_step( [ 'days' => 400, 'clicks' => 5 ] ) );
		$this->assertStringContainsString( 'title and meta description', Performance_Report::next_step( [ 'impressions' => 2000, 'clicks' => 3, 'days' => 10 ] ) );
		$this->assertStringContainsString( 'answer-first', Performance_Report::next_step( [ 'ai_crawls' => 12, 'ai_referrals' => 0, 'days' => 10 ] ) );
		$this->assertStringContainsString( 'GEO', Performance_Report::next_step( [ 'geo' => 30.0, 'days' => 10 ] ) );
		$this->assertSame( '', Performance_Report::next_step( [ 'impressions' => 200, 'clicks' => 20, 'geo' => 80.0, 'days' => 10 ] ) );
	}
}
