<?php
namespace JAT_Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\Head_Rest;

class HeadRestTest extends TestCase {

	protected function setUp(): void {
		global $si_test_state;
		$si_test_state = [
			'options'   => [],
			'post_meta' => [],
			'posts'     => [
				5 => [ 'ID' => 5, 'post_title' => 'Draft', 'post_content' => '', 'post_status' => 'draft', 'post_type' => 'post', 'post_password' => '' ],
				6 => [ 'ID' => 6, 'post_title' => 'Locked', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post', 'post_password' => 'x' ],
			],
		];
	}

	public function test_parse_head_reads_every_tag(): void {
		$html = <<<HTML
<title>Report a scam &amp; get help – Jeremy</title>
<meta name='robots' content='index, follow, max-image-preview:large' />
<meta name="description" content="How to report a scam to the FTC &amp; FBI." />
<link rel="canonical" href="https://example.com/report-a-scam/" />
<meta property="og:title" content="Report a scam" />
<meta property="og:image" content="https://example.com/a.jpg" />
<meta property="article:published_time" content="2026-11-01T10:00:00+00:00" />
<meta name="twitter:card" content="summary_large_image" />
<meta name="google-site-verification" content="abc" />
<script type="application/ld+json">{"@context":"https://schema.org","@graph":[{"@type":"Article"}]}</script>
HTML;
		$out = Head_Rest::parse_head( $html );

		$this->assertSame( 'Report a scam & get help – Jeremy', $out['title'] );
		$this->assertSame( 'How to report a scam to the FTC & FBI.', $out['description'] );
		$this->assertSame( 'https://example.com/report-a-scam/', $out['canonical'] );
		$this->assertSame( 'index, follow, max-image-preview:large', $out['robots'] );
		$this->assertSame( 'Report a scam', $out['og']['og:title'] );
		$this->assertSame( 'https://example.com/a.jpg', $out['og']['og:image'] );
		$this->assertArrayHasKey( 'article:published_time', $out['og'] );
		$this->assertSame( 'summary_large_image', $out['twitter']['twitter:card'] );
		$this->assertSame( 'abc', $out['meta']['google-site-verification'] );
		$this->assertSame( 'Article', $out['schema'][0]['@graph'][0]['@type'] );
	}

	public function test_parse_head_empty(): void {
		$out = Head_Rest::parse_head( '' );
		$this->assertSame( '', $out['title'] );
		$this->assertSame( [], $out['schema'] );
	}

	public function test_bad_schema_json_is_skipped(): void {
		$out = Head_Rest::parse_head( '<script type="application/ld+json">{not json</script>' );
		$this->assertSame( [], $out['schema'] );
	}

	public function test_list_requests_skip_the_field(): void {
		$rest    = new Head_Rest( [] );
		$request = new \WP_REST_Request( 'GET', '/wp/v2/posts' );
		$this->assertNull( $rest->get_html_field( [ 'id' => 5 ], Head_Rest::FIELD_HTML, $request ) );
	}

	public function test_fields_param_must_name_the_field(): void {
		$rest    = new Head_Rest( [] );
		$request = new \WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( '_fields', 'id,title' );
		$this->assertNull( $rest->get_json_field( [ 'id' => 5 ], Head_Rest::FIELD_JSON, $request ) );
	}

	public function test_drafts_and_protected_posts_return_null(): void {
		$rest = new Head_Rest( [] );
		foreach ( [ 5, 6, 999 ] as $id ) {
			$request = new \WP_REST_Request( 'GET', '/wp/v2/posts/' . $id );
			$this->assertNull( $rest->get_html_field( [ 'id' => $id ], Head_Rest::FIELD_HTML, $request ), "post $id" );
		}
	}

	public function test_off_when_seo_is_off(): void {
		global $si_test_state;
		new Head_Rest( [ 'enable_seo' => false ] );
		$hooks = $si_test_state['actions']['rest_api_init'] ?? [];
		$this->assertSame( [], $hooks );
	}
}
