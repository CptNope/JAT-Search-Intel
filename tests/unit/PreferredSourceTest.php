<?php
namespace JAT_Search_Intel\Tests\Unit;

use JAT_Search_Intel\Preferred_Source;

class PreferredSourceTest extends ParityTestCase {

	public function test_url_points_at_google_with_site_host(): void {
		$this->assertSame( 'example.com', Preferred_Source::site_host() );
		$url = Preferred_Source::url();
		$this->assertStringStartsWith( 'https://www.google.com/preferences/source', $url );
		$this->assertStringContainsString( 'q=example.com', $url );
	}

	public function test_root_site_is_eligible(): void {
		$this->assertTrue( Preferred_Source::is_eligible() );
	}

	public function test_render_escapes_label(): void {
		$html = Preferred_Source::render( '<img src=x onerror=alert(1)>' );
		$this->assertStringContainsString( 'jsi-preferred-source', $html );
		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringContainsString( 'rel="noopener"', $html );
	}

	public function test_render_default_label_uses_site_name(): void {
		$this->assertStringContainsString( 'as a preferred source on Google', Preferred_Source::render() );
	}

	public function test_block_and_shortcode_use_label(): void {
		$this->assertStringContainsString( 'Follow us', Preferred_Source::render_block( [ 'label' => 'Follow us' ] ) );
		$this->assertStringContainsString( 'Follow us', Preferred_Source::shortcode( [ 'label' => 'Follow us' ] ) );
		$this->assertStringContainsString( 'preferred source', Preferred_Source::render_block( null ) );
	}

	public function test_append_adds_button_to_main_singular_content(): void {
		$source = new Preferred_Source( [ 'preferred_source_post_types' => [ 'post' ] ] );
		$this->assertStringContainsString( 'jsi-preferred-source', $source->append( '<p>Body</p>' ) );
	}

	public function test_append_skips_outside_the_loop_or_singular(): void {
		$source = new Preferred_Source( [] );
		$GLOBALS['si_test_state']['conditionals']['in_the_loop'] = false;
		$this->assertSame( '<p>Body</p>', $source->append( '<p>Body</p>' ) );

		$GLOBALS['si_test_state']['conditionals'] = [ 'is_singular' => false ];
		$this->assertSame( '<p>Body</p>', $source->append( '<p>Body</p>' ) );
	}

	public function test_append_does_not_duplicate(): void {
		$source  = new Preferred_Source( [] );
		$content = '<p>Body</p>' . Preferred_Source::render();
		$this->assertSame( $content, $source->append( $content ) );
	}
}
