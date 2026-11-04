<?php
namespace JAT_Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\Core;
use JAT_Search_Intel\Custom_Schema;
use JAT_Search_Intel\Feed_SEO;
use JAT_Search_Intel\Media_SEO;
use JAT_Search_Intel\Sitemap;

/**
 * News/video/HTML sitemaps, media SEO, feed backlinks and custom schema.
 */
class SeoExtrasTest extends TestCase {

	protected function setUp(): void {
		global $si_test_state;
		$si_test_state['post_meta']  = [];
		$si_test_state['options']    = [];
		$si_test_state['actions']    = [];
		$si_test_state['filters']    = [];
		$si_test_state['transients'] = [];
	}

	private function settings( array $overrides = [] ): array {
		return array_merge( Core::get_default_settings(), $overrides );
	}

	// ── Video extraction ────────────────────────────────────────────────

	public function test_extract_videos_finds_youtube_vimeo_and_self_hosted(): void {
		$content = '<!-- wp:embed {"url":"https://www.youtube.com/watch?v=dQw4w9WgXcQ"} -->'
			. ' https://youtu.be/abcdefghijk https://vimeo.com/123456789'
			. ' <video controls src="https://example.com/v.mp4" poster="https://example.com/p.jpg"></video>';

		$videos = Sitemap::extract_videos( $content );

		$this->assertCount( 4, $videos );
		$this->assertSame( 'https://www.youtube.com/embed/dQw4w9WgXcQ', $videos[0]['player_loc'] );
		$this->assertSame( 'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', $videos[0]['thumbnail'] );
		$this->assertSame( 'https://player.vimeo.com/video/123456789', $videos[2]['player_loc'] );
		$this->assertSame( 'https://example.com/v.mp4', $videos[3]['content_loc'] );
		$this->assertSame( 'https://example.com/p.jpg', $videos[3]['thumbnail'] );
	}

	public function test_extract_videos_dedupes_repeated_youtube_ids(): void {
		$videos = Sitemap::extract_videos( 'https://youtu.be/abcdefghijk and https://www.youtube.com/embed/abcdefghijk' );
		$this->assertCount( 1, $videos );
	}

	public function test_sitemap_index_lists_news_and_video_when_enabled(): void {
		$sitemap = new Sitemap( $this->settings( [ 'enable_news_sitemap' => true, 'enable_video_sitemap' => true, 'sitemap_post_types' => [], 'sitemap_taxonomies' => [] ] ) );
		$method  = new \ReflectionMethod( $sitemap, 'generate_sitemap_index' );
		$method->setAccessible( true );
		$xml = $method->invoke( $sitemap );

		$this->assertStringContainsString( '/news-sitemap.xml', $xml );
		$this->assertStringContainsString( '/video-sitemap.xml', $xml );
	}

	public function test_sitemap_index_omits_news_and_video_by_default(): void {
		$sitemap = new Sitemap( $this->settings( [ 'sitemap_post_types' => [], 'sitemap_taxonomies' => [] ] ) );
		$method  = new \ReflectionMethod( $sitemap, 'generate_sitemap_index' );
		$method->setAccessible( true );
		$xml = $method->invoke( $sitemap );

		$this->assertStringNotContainsString( 'news-sitemap', $xml );
		$this->assertStringNotContainsString( 'video-sitemap', $xml );
	}

	public function test_news_sitemap_has_news_namespace(): void {
		$xml = ( new Sitemap( $this->settings( [ 'enable_news_sitemap' => true ] ) ) )->generate_news_sitemap();
		$this->assertStringContainsString( 'xmlns:news="http://www.google.com/schemas/sitemap-news/0.9"', $xml );
		$this->assertStringEndsWith( '</urlset>', $xml );
	}

	public function test_html_sitemap_shortcode_is_registered_even_without_xml_sitemaps(): void {
		global $si_test_state;
		new Sitemap( $this->settings( [ 'enable_sitemap' => false ] ) );
		$this->assertArrayHasKey( 'jat_search_intel_html_sitemap', $si_test_state['shortcodes'] ?? [] );
	}

	// ── Media SEO ───────────────────────────────────────────────────────

	public function test_humanize_cleans_file_names(): void {
		$this->assertSame( 'Red bicycle in park', Media_SEO::humanize( 'red-bicycle_in-park-1024x768.jpg' ) );
		$this->assertSame( '', Media_SEO::humanize( 'IMG_.png' ) );
	}

	public function test_content_img_keeps_existing_alt(): void {
		$media = new Media_SEO( $this->settings() );
		$tag   = '<img src="a.jpg" alt="Hand-written">';
		$this->assertSame( $tag, $media->filter_content_img_tag( $tag, 'the_content', 5 ) );
	}

	public function test_content_img_gets_alt_from_attachment_meta(): void {
		global $si_test_state;
		$si_test_state['post_meta'][5]['_wp_attachment_image_alt'] = 'Sunset over harbor';

		$media = new Media_SEO( $this->settings() );
		$this->assertSame( '<img alt="Sunset over harbor" src="a.jpg">', $media->filter_content_img_tag( '<img src="a.jpg">', 'the_content', 5 ) );
		$this->assertSame( '<img src="a.jpg" alt="Sunset over harbor">', $media->filter_content_img_tag( '<img src="a.jpg" alt="">', 'the_content', 5 ) );
	}

	public function test_media_hooks_follow_settings(): void {
		global $si_test_state;
		new Media_SEO( $this->settings( [ 'enable_image_alt' => false, 'redirect_attachments' => false ] ) );
		$this->assertArrayNotHasKey( 'add_attachment', $si_test_state['actions'] );
		$this->assertArrayNotHasKey( 'template_redirect', $si_test_state['actions'] );
	}

	// ── Feed SEO ────────────────────────────────────────────────────────

	public function test_feed_footer_replaces_placeholders(): void {
		$feed = new Feed_SEO( $this->settings() );
		$html = $feed->render_footer( 'The post %%POSTLINK%% appeared first on %%BLOGLINK%%.' );

		$this->assertStringContainsString( '<a href="https://example.com/?p=', $html );
		$this->assertStringContainsString( 'appeared first on <a href="https://example.com/">', $html );
		$this->assertStringNotContainsString( '%%', $html );
	}

	public function test_feed_footer_not_hooked_when_text_empty(): void {
		global $si_test_state;
		new Feed_SEO( $this->settings( [ 'rss_after_content' => '  ' ] ) );
		$this->assertArrayNotHasKey( 'the_content_feed', $si_test_state['filters'] );
	}

	// ── Custom schema ───────────────────────────────────────────────────

	public function test_custom_schema_parses_object_list_graph_and_script_wrapper(): void {
		$this->assertCount( 1, Custom_Schema::parse( '{"@context":"https://schema.org","@type":"Event","name":"Launch"}' ) );
		$this->assertCount( 2, Custom_Schema::parse( '[{"@type":"Event"},{"@type":"Offer"}]' ) );
		$this->assertCount( 2, Custom_Schema::parse( '{"@context":"https://schema.org","@graph":[{"@type":"A"},{"@type":"B"}]}' ) );
		$this->assertCount( 1, Custom_Schema::parse( '<script type="application/ld+json">{"@type":"Recipe"}</script>' ) );

		$nodes = Custom_Schema::parse( '{"@context":"https://schema.org","@type":"Event"}' );
		$this->assertArrayNotHasKey( '@context', $nodes[0] );
	}

	public function test_custom_schema_rejects_invalid_or_typeless_json(): void {
		$this->assertNull( Custom_Schema::parse( '{not json' ) );
		$this->assertNull( Custom_Schema::parse( '{"name":"no type"}' ) );
		$this->assertSame( [], Custom_Schema::parse( '   ' ) );
	}

	public function test_custom_schema_joins_graph(): void {
		global $si_test_state;
		$si_test_state['post_meta'][1][ Custom_Schema::META_KEY ] = '{"@type":"Event","name":"Launch"}';

		$nodes = ( new Custom_Schema( $this->settings() ) )->add_graph_nodes( [ [ '@type' => 'WebSite' ] ] );

		$this->assertCount( 2, $nodes );
		$this->assertSame( 'Event', $nodes[1]['@type'] );
	}
}
