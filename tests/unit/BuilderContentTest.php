<?php
namespace JAT_Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\Builder_Content;

class BuilderContentTest extends TestCase {

	protected function setUp(): void {
		global $si_test_state;
		$si_test_state['post_meta'] = [];
		Builder_Content::flush();
	}

	private function post( int $id, string $content ): object {
		return (object) [ 'ID' => $id, 'post_content' => $content ];
	}

	public function test_plain_posts_are_unchanged(): void {
		$html = '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->';
		$this->assertSame( $html, Builder_Content::get( $this->post( 1, $html ) ) );
	}

	public function test_elementor_widgets_become_html(): void {
		global $si_test_state;
		$si_test_state['post_meta'][2]['_elementor_edit_mode'] = 'builder';
		$si_test_state['post_meta'][2]['_elementor_data']      = wp_json_encode( [
			[
				'elType'   => 'section',
				'elements' => [
					[ 'widgetType' => 'heading', 'settings' => [ 'title' => 'Hacked site repair', 'header_size' => 'h1' ] ],
					[ 'widgetType' => 'text-editor', 'settings' => [ 'editor' => '<p>We remove malware fast.</p>' ] ],
					[ 'widgetType' => 'icon-list', 'settings' => [ 'icon_list' => [ [ 'text' => 'Free scan' ], [ 'text' => 'Written quote' ] ] ] ],
					[ 'widgetType' => 'image', 'settings' => [ 'image' => [ 'url' => 'https://example.com/a.png', 'alt' => 'Dashboard' ] ] ],
				],
			],
		] );

		$html = Builder_Content::get( $this->post( 2, '<p>fallback</p>' ) );

		$this->assertStringContainsString( '<h1>Hacked site repair</h1>', $html );
		$this->assertStringContainsString( '<p>We remove malware fast.</p>', $html );
		$this->assertStringContainsString( '<li>Free scan</li>', $html );
		$this->assertStringContainsString( 'alt="Dashboard"', $html );
		$this->assertStringNotContainsString( 'fallback', $html );
	}

	public function test_divi_shortcodes_are_removed_and_titles_kept(): void {
		$content = '[et_pb_section][et_pb_row][et_pb_column][et_pb_blurb title="Fast fixes" header_level="h2"]Same-day cleanup.[/et_pb_blurb][et_pb_text]<p>More text.</p>[/et_pb_text][/et_pb_column][/et_pb_row][/et_pb_section]';

		$html = Builder_Content::get( $this->post( 3, $content ) );

		$this->assertStringNotContainsString( '[et_pb', $html );
		$this->assertStringContainsString( '<h2>Fast fixes</h2>', $html );
		$this->assertStringContainsString( 'Same-day cleanup.', $html );
		$this->assertStringContainsString( '<p>More text.</p>', $html );
	}

	public function test_wpbakery_shortcodes(): void {
		$content = '[vc_row][vc_column][vc_custom_heading text="Our services" font_container="tag:h2"][vc_column_text]<p>Web design.</p>[/vc_column_text][/vc_column][/vc_row]';

		$html = Builder_Content::get( $this->post( 4, $content ) );

		$this->assertSame( 'wpbakery', Builder_Content::detect( 4, $content ) );
		$this->assertStringContainsString( '<h2>Our services</h2>', $html );
		$this->assertStringContainsString( '<p>Web design.</p>', $html );
		$this->assertStringNotContainsString( '[vc_', $html );
	}

	public function test_empty_builder_data_falls_back_to_content(): void {
		global $si_test_state;
		$si_test_state['post_meta'][5]['_elementor_edit_mode'] = 'builder';
		$si_test_state['post_meta'][5]['_elementor_data']      = '[]';
		$this->assertSame( '<p>Body</p>', Builder_Content::get( $this->post( 5, '<p>Body</p>' ) ) );
	}
}
