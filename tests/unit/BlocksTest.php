<?php
namespace JAT_Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\Blocks;

class BlocksTest extends TestCase {

	private Blocks $blocks;

	protected function setUp(): void {
		global $si_test_state, $si_registered_meta, $si_registered_blocks, $si_registered_scripts, $si_registered_styles;

		$si_test_state = [
			'post_meta'    => [],
			'user_meta'    => [],
			'options'      => [],
			'transients'   => [],
			'actions'      => [],
			'filters'      => [],
			'posts'        => [],
			'current_user' => 1,
			'is_admin'     => true, // Editor assets are registered in wp-admin.
		];

		$si_registered_meta    = [];
		$si_registered_blocks  = [];
		$si_registered_scripts = [];
		$si_registered_styles  = [];

		$si_test_state['posts'][1] = [
			'ID'           => 1,
			'post_title'   => 'Test Post',
			'post_content' => '<h2>Introduction</h2><p>Hello world</p><h3>Details</h3><p>More info</p><h2>Conclusion</h2><p>Goodbye</p>',
			'post_excerpt' => 'Test excerpt',
			'post_status'  => 'publish',
			'post_type'    => 'post',
			'post_author'  => 1,
		];

		$this->blocks = new Blocks();
	}

	// ── Constructor ─────────────────────────────────────────────────────────

	public function test_constructor_registers_init_hooks(): void {
		global $si_test_state;

		$actions = array_keys( $si_test_state['actions'] );

		$this->assertContains( 'init', $actions );
		$this->assertContains( 'enqueue_block_editor_assets', $actions );
	}

	public function test_blocks_on_init_and_meta_on_rest_api_init(): void {
		global $si_test_state;

		$names = static function ( string $hook ) use ( $si_test_state ): array {
			return array_map( function ( $action ) {
				return $action['callback'][1] ?? null;
			}, $si_test_state['actions'][ $hook ] ?? [] );
		};

		$this->assertContains( 'register_blocks', $names( 'init' ) );
		$this->assertNotContains( 'register_meta', $names( 'init' ), 'Page views do not need meta registered' );
		$this->assertContains( 'register_meta', $names( 'rest_api_init' ) );
	}

	public function test_constructor_registers_sidebar_enqueue(): void {
		global $si_test_state;

		$callbacks = array_map( function ( $action ) {
			return $action['callback'][1] ?? null;
		}, $si_test_state['actions']['enqueue_block_editor_assets'] );

		$this->assertContains( 'enqueue_sidebar', $callbacks );
	}

	// ── register_meta() ─────────────────────────────────────────────────────

	public function test_register_meta_registers_all_31_keys(): void {
		global $si_registered_meta;

		$this->blocks->register_meta();

		// get_post_types stub returns ['post' => ...], so we check 'post'.
		$this->assertArrayHasKey( 'post', $si_registered_meta );
		$this->assertCount( 31, $si_registered_meta['post'] );
	}

	public function test_register_meta_keys_start_with_prefix(): void {
		global $si_registered_meta;

		$this->blocks->register_meta();

		foreach ( array_keys( $si_registered_meta['post'] ) as $key ) {
			$this->assertStringStartsWith( '_jat_search_intel_', $key, "Meta key {$key} missing prefix" );
		}
	}

	public function test_register_meta_all_show_in_rest(): void {
		global $si_registered_meta;

		$this->blocks->register_meta();

		foreach ( $si_registered_meta['post'] as $key => $args ) {
			$this->assertTrue( $args['show_in_rest'], "Meta key {$key} should show in REST" );
		}
	}

	public function test_register_meta_all_are_single(): void {
		global $si_registered_meta;

		$this->blocks->register_meta();

		foreach ( $si_registered_meta['post'] as $key => $args ) {
			$this->assertTrue( $args['single'], "Meta key {$key} should be single" );
		}
	}

	public function test_register_meta_all_have_auth_callback(): void {
		global $si_registered_meta;

		$this->blocks->register_meta();

		foreach ( $si_registered_meta['post'] as $key => $args ) {
			$this->assertArrayHasKey( 'auth_callback', $args, "Meta key {$key} missing auth_callback" );
			$this->assertIsCallable( $args['auth_callback'] );
		}
	}

	public function test_register_meta_score_keys_default_to_zero(): void {
		global $si_registered_meta;

		$this->blocks->register_meta();

		$score_keys = [
			'_jat_search_intel_seo_score',
			'_jat_search_intel_aeo_score',
			'_jat_search_intel_geo_score',
			'_jat_search_intel_readability_score',
		];

		foreach ( $score_keys as $key ) {
			$this->assertSame( '0', $si_registered_meta['post'][ $key ]['default'], "{$key} should default to '0'" );
		}
	}

	public function test_register_meta_text_keys_default_to_empty(): void {
		global $si_registered_meta;

		$this->blocks->register_meta();

		$text_keys = [
			'_jat_search_intel_title',
			'_jat_search_intel_description',
			'_jat_search_intel_focus_keyword',
			'_jat_search_intel_ai_summary',
		];

		foreach ( $text_keys as $key ) {
			$this->assertSame( '', $si_registered_meta['post'][ $key ]['default'], "{$key} should default to ''" );
		}
	}

	// ── register_blocks() ───────────────────────────────────────────────────

	public function test_register_blocks_registers_all_six(): void {
		global $si_registered_blocks;

		$this->blocks->register_blocks();

		$expected = [
			'jat-search-intel/faq',
			'jat-search-intel/howto',
			'jat-search-intel/answer-box',
			'jat-search-intel/citation',
			'jat-search-intel/toc',
			'jat-search-intel/breadcrumbs',
		];

		foreach ( $expected as $block_name ) {
			$this->assertArrayHasKey( $block_name, $si_registered_blocks, "Block {$block_name} not registered" );
		}
	}

	public function test_register_blocks_each_has_editor_script_handles(): void {
		global $si_registered_blocks;

		$this->blocks->register_blocks();

		foreach ( $si_registered_blocks as $name => $args ) {
			$this->assertArrayHasKey( 'editor_script_handles', $args, "{$name} missing editor_script_handles" );
			$this->assertIsArray( $args['editor_script_handles'] );
			$this->assertNotEmpty( $args['editor_script_handles'] );
		}
	}

	public function test_register_blocks_toc_has_render_callback(): void {
		global $si_registered_blocks;

		$this->blocks->register_blocks();

		$this->assertArrayHasKey( 'render_callback', $si_registered_blocks['jat-search-intel/toc'] );
		$this->assertIsCallable( $si_registered_blocks['jat-search-intel/toc']['render_callback'] );
	}

	public function test_register_blocks_breadcrumbs_has_render_callback(): void {
		global $si_registered_blocks;

		$this->blocks->register_blocks();

		$this->assertArrayHasKey( 'render_callback', $si_registered_blocks['jat-search-intel/breadcrumbs'] );
		$this->assertIsCallable( $si_registered_blocks['jat-search-intel/breadcrumbs']['render_callback'] );
	}

	public function test_register_blocks_static_blocks_have_no_render_callback(): void {
		global $si_registered_blocks;

		$this->blocks->register_blocks();

		$static = [ 'jat-search-intel/faq', 'jat-search-intel/howto', 'jat-search-intel/answer-box', 'jat-search-intel/citation' ];

		foreach ( $static as $block_name ) {
			$this->assertArrayNotHasKey( 'render_callback', $si_registered_blocks[ $block_name ], "{$block_name} should not have render_callback" );
		}
	}

	public function test_register_blocks_registers_scripts(): void {
		global $si_registered_scripts;

		$this->blocks->register_blocks();

		$expected_handles = [
			'jat-search-intel-faq',
			'jat-search-intel-howto',
			'jat-search-intel-answer-box',
			'jat-search-intel-citation',
			'jat-search-intel-toc',
			'jat-search-intel-breadcrumbs',
		];

		foreach ( $expected_handles as $handle ) {
			$this->assertArrayHasKey( $handle, $si_registered_scripts, "Script {$handle} not registered" );
		}
	}

	public function test_register_blocks_registers_styles_for_blocks_with_css(): void {
		global $si_registered_styles;

		$this->blocks->register_blocks();

		// FAQ, HowTo, Answer Box, Citation have editor CSS; TOC and Breadcrumbs don't.
		$expected_style_handles = [
			'jat-search-intel-faq-editor',
			'jat-search-intel-howto-editor',
			'jat-search-intel-answer-box-editor',
			'jat-search-intel-citation-editor',
		];

		foreach ( $expected_style_handles as $handle ) {
			$this->assertArrayHasKey( $handle, $si_registered_styles, "Style {$handle} not registered" );
		}
	}

	// ── render_toc() ────────────────────────────────────────────────────────

	public function test_render_toc_returns_empty_when_no_post(): void {
		global $post;
		$post = null;

		$result = $this->blocks->render_toc( [], '' );

		$this->assertSame( '', $result );
	}

	public function test_render_toc_returns_empty_when_no_headings(): void {
		global $post;
		$post = (object) [
			'post_content' => '<p>No headings here.</p>',
		];

		$result = $this->blocks->render_toc( [], '' );

		$this->assertSame( '', $result );
	}

	public function test_render_toc_generates_nav_with_headings(): void {
		global $post;
		$post = (object) $GLOBALS['si_test_state']['posts'][1];

		$result = $this->blocks->render_toc( [ 'maxDepth' => 3, 'ordered' => true ], '' );

		$this->assertStringContainsString( '<nav class="si-toc"', $result );
		$this->assertStringContainsString( 'Table of Contents', $result );
		$this->assertStringContainsString( '<ol', $result );
		$this->assertStringContainsString( 'Introduction', $result );
		$this->assertStringContainsString( 'Details', $result );
		$this->assertStringContainsString( 'Conclusion', $result );
	}

	public function test_render_toc_uses_unordered_list_when_ordered_false(): void {
		global $post;
		$post = (object) $GLOBALS['si_test_state']['posts'][1];

		$result = $this->blocks->render_toc( [ 'maxDepth' => 3, 'ordered' => false ], '' );

		$this->assertStringContainsString( '<ul', $result );
		$this->assertStringNotContainsString( '<ol', $result );
	}

	public function test_render_toc_respects_max_depth(): void {
		global $post;
		$post = (object) [
			'post_content' => '<h2>H2</h2><h3>H3</h3><h4>H4</h4><h5>H5</h5>',
		];

		// maxDepth 2 means h2 and h3 only (depth = level - 1).
		$result = $this->blocks->render_toc( [ 'maxDepth' => 2, 'ordered' => true ], '' );

		$this->assertStringContainsString( 'H2', $result );
		$this->assertStringContainsString( 'H3', $result );
		$this->assertStringNotContainsString( 'H4', $result );
	}

	public function test_render_toc_generates_anchor_links(): void {
		global $post;
		$post = (object) $GLOBALS['si_test_state']['posts'][1];

		$result = $this->blocks->render_toc( [], '' );

		$this->assertMatchesRegularExpression( '/href="#[a-z0-9-]+"/', $result );
	}

	// ── render_breadcrumbs() ────────────────────────────────────────────────

	public function test_render_breadcrumbs_returns_nav_with_schema(): void {
		global $post;
		$post = (object) $GLOBALS['si_test_state']['posts'][1];

		$result = $this->blocks->render_breadcrumbs( [], '' );

		$this->assertStringContainsString( '<nav class="si-breadcrumbs"', $result );
		$this->assertStringContainsString( 'BreadcrumbList', $result );
		$this->assertStringContainsString( 'ListItem', $result );
		$this->assertStringContainsString( 'itemprop="position"', $result );
	}

	public function test_render_breadcrumbs_contains_home_link(): void {
		global $post;
		$post = (object) $GLOBALS['si_test_state']['posts'][1];

		$result = $this->blocks->render_breadcrumbs( [], '' );

		$this->assertStringContainsString( 'Home', $result );
		$this->assertStringContainsString( home_url( '/' ), $result );
	}

	public function test_render_breadcrumbs_contains_post_title(): void {
		global $post;
		$post = (object) $GLOBALS['si_test_state']['posts'][1];

		$result = $this->blocks->render_breadcrumbs( [], '' );

		$this->assertStringContainsString( 'Test Post', $result );
	}

	public function test_render_breadcrumbs_uses_custom_separator(): void {
		global $post;
		$post = (object) $GLOBALS['si_test_state']['posts'][1];

		$result = $this->blocks->render_breadcrumbs( [ 'separator' => '/' ], '' );

		$this->assertStringContainsString( '/', $result );
	}

	public function test_render_breadcrumbs_uses_custom_home_label(): void {
		global $post;
		$post = (object) $GLOBALS['si_test_state']['posts'][1];

		$result = $this->blocks->render_breadcrumbs( [ 'homeLabel' => 'Start' ], '' );

		$this->assertStringContainsString( 'Start', $result );
	}

	public function test_render_breadcrumbs_default_separator_is_chevron(): void {
		global $post;
		$post = (object) $GLOBALS['si_test_state']['posts'][1];

		$result = $this->blocks->render_breadcrumbs( [], '' );

		$this->assertStringContainsString( '›', $result );
	}

	public function test_render_breadcrumbs_includes_category(): void {
		global $post;
		$post = (object) $GLOBALS['si_test_state']['posts'][1];

		$result = $this->blocks->render_breadcrumbs( [], '' );

		// get_the_category stub returns 'Uncategorized'.
		$this->assertStringContainsString( 'Uncategorized', $result );
	}

	public function test_render_breadcrumbs_position_meta_tags(): void {
		global $post;
		$post = (object) $GLOBALS['si_test_state']['posts'][1];

		$result = $this->blocks->render_breadcrumbs( [], '' );

		// Home=1, Category=2, Post=3.
		$this->assertStringContainsString( 'content="1"', $result );
		$this->assertStringContainsString( 'content="2"', $result );
		$this->assertStringContainsString( 'content="3"', $result );
	}

	public function test_front_end_registers_only_dynamic_blocks_without_assets(): void {
		global $si_test_state, $si_registered_blocks, $si_registered_scripts;
		$si_test_state['is_admin'] = false;
		$si_registered_blocks      = [];
		$si_registered_scripts     = [];

		( new \JAT_Search_Intel\Blocks() )->register_blocks();

		$this->assertSame( [ 'jat-search-intel/toc', 'jat-search-intel/breadcrumbs', 'jat-search-intel/preferred-source' ], array_keys( $si_registered_blocks ) );
		$this->assertSame( [], $si_registered_scripts );
	}
}
