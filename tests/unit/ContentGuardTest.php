<?php
namespace JAT_Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\Content_Guard;
use JAT_Search_Intel\Core;
use JAT_Search_Intel\SEO_Manager;

/**
 * Password-protected content never reaches front-end output.
 */
class ContentGuardTest extends TestCase {

	protected function setUp(): void {
		global $si_test_state;
		$si_test_state['post_meta'] = [];
		$si_test_state['posts']     = [
			50 => [ 'ID' => 50, 'post_type' => 'post', 'post_status' => 'publish', 'post_password' => 'pw', 'post_title' => 'Locked', 'post_content' => '<p>Hidden body text</p>', 'post_excerpt' => 'Hidden excerpt' ],
			51 => [ 'ID' => 51, 'post_type' => 'post', 'post_status' => 'publish', 'post_password' => '', 'post_title' => 'Open', 'post_content' => '<p>Visible body text</p>', 'post_excerpt' => '' ],
		];
	}

	public function test_helpers(): void {
		$this->assertTrue( Content_Guard::is_protected( 50 ) );
		$this->assertFalse( Content_Guard::is_protected( 51 ) );
		$this->assertFalse( Content_Guard::is_protected( 999 ) );
		$this->assertSame( '', Content_Guard::content( get_post( 50 ) ) );
		$this->assertSame( '', Content_Guard::excerpt( get_post( 50 ) ) );
		$this->assertSame( '<p>Visible body text</p>', Content_Guard::content( get_post( 51 ) ) );
	}

	public function test_auto_description_never_uses_protected_body(): void {
		$seo = new SEO_Manager( Core::get_default_settings() );
		$this->assertStringNotContainsString( 'Hidden', $seo->get_description( 50 ) );
		$this->assertStringContainsString( 'Visible body text', $seo->get_description( 51 ) );
	}

	public function test_explicit_description_is_still_used(): void {
		global $si_test_state;
		$si_test_state['post_meta'][50]['_jat_search_intel_description'] = 'Members get volume pricing.';
		$seo = new SEO_Manager( Core::get_default_settings() );
		$this->assertSame( 'Members get volume pricing.', $seo->get_description( 50 ) );
	}
}
