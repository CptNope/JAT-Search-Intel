<?php
namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Block Editor integration — sidebar panel, custom blocks, and meta registration.
 *
 * @since 1.4.0
 */
class Blocks {

	/**
	 * Post meta keys to expose in the REST API for the sidebar.
	 *
	 * @var array<string,array>
	 */
	private const META_KEYS = [
		'_jat_search_intel_title'              => [ 'type' => 'string', 'default' => '' ],
		'_jat_search_intel_description'        => [ 'type' => 'string', 'default' => '' ],
		'_jat_search_intel_focus_keyword'      => [ 'type' => 'string', 'default' => '' ],
		'_jat_search_intel_canonical_url'      => [ 'type' => 'string', 'default' => '' ],
		'_jat_search_intel_noindex'            => [ 'type' => 'string', 'default' => '' ],
		'_jat_search_intel_nofollow'           => [ 'type' => 'string', 'default' => '' ],
		'_jat_search_intel_og_title'           => [ 'type' => 'string', 'default' => '' ],
		'_jat_search_intel_og_description'     => [ 'type' => 'string', 'default' => '' ],
		'_jat_search_intel_og_image'           => [ 'type' => 'string', 'default' => '' ],
		'_jat_search_intel_twitter_title'      => [ 'type' => 'string', 'default' => '' ],
		'_jat_search_intel_twitter_description'=> [ 'type' => 'string', 'default' => '' ],
		'_jat_search_intel_twitter_image'      => [ 'type' => 'string', 'default' => '' ],
		'_jat_search_intel_secondary_keywords' => [ 'type' => 'string', 'default' => '' ],
		'_jat_search_intel_direct_answer'      => [ 'type' => 'string', 'default' => '' ],
		'_jat_search_intel_schema_type'        => [ 'type' => 'string', 'default' => '' ],
		'_jat_search_intel_ai_summary'         => [ 'type' => 'string', 'default' => '' ],
		'_jat_search_intel_key_topics'         => [ 'type' => 'string', 'default' => '' ],
		'_jat_search_intel_content_type'       => [ 'type' => 'string', 'default' => '' ],
		'_jat_search_intel_content_intent'     => [ 'type' => 'string', 'default' => '' ],
		'_jat_search_intel_target_audience'    => [ 'type' => 'string', 'default' => '' ],
		'_jat_search_intel_expertise_level'    => [ 'type' => 'string', 'default' => '' ],
		'_jat_search_intel_fact_check_date'    => [ 'type' => 'string', 'default' => '' ],
		'_jat_search_intel_local_relevance'    => [ 'type' => 'string', 'default' => '' ],
		'_jat_search_intel_local_location'     => [ 'type' => 'string', 'default' => '' ],
		'_jat_search_intel_seo_score'          => [ 'type' => 'string', 'default' => '0' ],
		'_jat_search_intel_aeo_score'          => [ 'type' => 'string', 'default' => '0' ],
		'_jat_search_intel_geo_score'          => [ 'type' => 'string', 'default' => '0' ],
		'_jat_search_intel_readability_score'  => [ 'type' => 'string', 'default' => '0' ],
	];

	/**
	 * Constructor — register hooks.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register_blocks' ] );
		add_action( 'init', [ $this, 'register_meta' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_sidebar' ] );
	}

	/**
	 * Register post meta for the REST API so the sidebar can read/write.
	 */
	public function register_meta(): void {
		$post_types = get_post_types( [ 'public' => true ] );

		foreach ( self::META_KEYS as $key => $config ) {
			foreach ( $post_types as $post_type ) {
				register_post_meta( $post_type, $key, [
					'show_in_rest'  => true,
					'single'        => true,
					'type'          => $config['type'],
					'default'       => $config['default'],
					'auth_callback' => function () {
						return current_user_can( 'edit_posts' );
					},
				] );
			}
		}
	}

	/**
	 * Enqueue sidebar panel script in the block editor.
	 */
	public function enqueue_sidebar(): void {
		$asset_file = JAT_SEARCH_INTEL_DIR . 'build/sidebar.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'jat-search-intel-sidebar',
			JAT_SEARCH_INTEL_URL . 'build/sidebar.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style(
			'jat-search-intel-sidebar',
			JAT_SEARCH_INTEL_URL . 'build/sidebar.css',
			[],
			$asset['version']
		);

		wp_set_script_translations( 'jat-search-intel-sidebar', 'jat-search-intel' );

		// Pass theme.json color palette to JS for score ring theming.
		wp_localize_script( 'jat-search-intel-sidebar', 'jatSearchIntelTheme', [
			'colors' => $this->get_theme_json_colors(),
		] );
	}

	/**
	 * Read the active theme's theme.json color palette.
	 *
	 * Returns an associative array of color slugs → hex values so the
	 * sidebar score rings can adapt to the theme's design tokens.
	 *
	 * @return array<string,string> e.g. ['primary' => '#1e40af', 'secondary' => '#9333ea']
	 */
	private function get_theme_json_colors(): array {
		if ( ! class_exists( 'WP_Theme_JSON_Resolver' ) ) {
			return [];
		}

		$theme_json = \WP_Theme_JSON_Resolver::get_merged_data();
		if ( ! method_exists( $theme_json, 'get_settings' ) ) {
			return [];
		}

		$settings = $theme_json->get_settings();
		$palette  = $settings['color']['palette']['theme'] ?? [];

		if ( empty( $palette ) ) {
			return [];
		}

		$colors = [];
		foreach ( $palette as $entry ) {
			if ( ! empty( $entry['slug'] ) && ! empty( $entry['color'] ) ) {
				$colors[ $entry['slug'] ] = $entry['color'];
			}
		}

		return $colors;
	}

	/**
	 * Register custom Gutenberg blocks.
	 */
	public function register_blocks(): void {
		$blocks = [
			'faq'         => [],
			'howto'       => [],
			'answer-box'  => [],
			'citation'    => [],
			'toc'         => [ 'render_callback' => [ $this, 'render_toc' ] ],
			'breadcrumbs' => [ 'render_callback' => [ $this, 'render_breadcrumbs' ] ],
		];

		// Editor scripts and styles are only needed where the block editor runs.
		// On the front end, register the block types (for render callbacks) without
		// touching the script registry, which saves file reads on every page view.
		if ( ! is_admin() ) {
			foreach ( $blocks as $slug => $args ) {
				if ( ! empty( $args['render_callback'] ) ) {
					register_block_type( "jat-search-intel/{$slug}", [ 'render_callback' => $args['render_callback'] ] );
				}
			}
			return;
		}

		foreach ( $blocks as $slug => $args ) {
			$script_asset = JAT_SEARCH_INTEL_DIR . "build/blocks/{$slug}/index.asset.php";

			if ( ! file_exists( $script_asset ) ) {
				continue;
			}

			$asset = require $script_asset;

			$script_handle = "jat-search-intel-{$slug}";

			wp_register_script(
				$script_handle,
				JAT_SEARCH_INTEL_URL . "build/blocks/{$slug}/index.js",
				$asset['dependencies'],
				$asset['version'],
				true
			);
			wp_set_script_translations( $script_handle, 'jat-search-intel' );

			$style_path = JAT_SEARCH_INTEL_DIR . "build/blocks/{$slug}/index.css";
			$style_handle = null;

			if ( file_exists( $style_path ) ) {
				$style_handle = "jat-search-intel-{$slug}-editor";
				wp_register_style(
					$style_handle,
					JAT_SEARCH_INTEL_URL . "build/blocks/{$slug}/index.css",
					[],
					$asset['version']
				);
			}

			$block_args = [
				'editor_script_handles' => [ $script_handle ],
			];

			if ( $style_handle ) {
				$block_args['editor_style_handles'] = [ $style_handle ];
			}

			if ( ! empty( $args['render_callback'] ) ) {
				$block_args['render_callback'] = $args['render_callback'];
			}

			register_block_type( "jat-search-intel/{$slug}", $block_args );
		}
	}

	/**
	 * Render callback for the TOC block (dynamic, server-side).
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Block content.
	 * @return string
	 */
	public function render_toc( array $attributes, string $content ): string {
		global $post;

		if ( ! $post || empty( $post->post_content ) ) {
			return '';
		}

		$max_depth = $attributes['maxDepth'] ?? 3;
		$ordered   = $attributes['ordered'] ?? true;

		// Extract headings from post content.
		preg_match_all(
			'/<h([2-6])[^>]*>(.*?)<\/h\1>/si',
			$post->post_content,
			$matches,
			PREG_SET_ORDER
		);

		if ( empty( $matches ) ) {
			return '';
		}

		$list_tag = $ordered ? 'ol' : 'ul';
		$output   = '<nav class="si-toc" aria-label="' . esc_attr__( 'Table of Contents', 'jat-search-intel' ) . '">';
		$output  .= '<h3 class="si-toc__title">' . esc_html__( 'Table of Contents', 'jat-search-intel' ) . '</h3>';
		$output  .= "<{$list_tag} class=\"si-toc-list\">";

		foreach ( $matches as $match ) {
			$level = (int) $match[1];
			if ( $level > $max_depth + 1 ) {
				continue;
			}
			$text   = wp_strip_all_tags( $match[2] );
			$anchor = sanitize_title( $text );
			$indent = ( $level - 2 ) * 16;

			$output .= sprintf(
				'<li class="si-toc-item si-toc-item--h%d" style="margin-left:%dpx"><a href="#%s">%s</a></li>',
				$level,
				$indent,
				esc_attr( $anchor ),
				esc_html( $text )
			);
		}

		$output .= "</{$list_tag}></nav>";

		return $output;
	}

	/**
	 * Render callback for the Breadcrumbs block (dynamic, server-side).
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Block content.
	 * @return string
	 */
	public function render_breadcrumbs( array $attributes, string $content ): string {
		global $post;

		$separator  = esc_html( $attributes['separator'] ?? '›' );
		$home_label = esc_html( $attributes['homeLabel'] ?? __( 'Home', 'jat-search-intel' ) );

		$items = [];

		// Home.
		$items[] = [
			'name' => $home_label,
			'url'  => home_url( '/' ),
		];

		if ( is_singular() && $post ) {
			// Category (first one).
			$categories = get_the_category( $post->ID );
			if ( ! empty( $categories ) ) {
				$cat      = $categories[0];
				$items[] = [
					'name' => esc_html( $cat->name ),
					'url'  => esc_url( get_category_link( $cat->term_id ) ),
				];
			}

			// Current post.
			$items[] = [
				'name' => esc_html( get_the_title( $post->ID ) ),
				'url'  => '',
			];
		} elseif ( is_category() ) {
			$items[] = [
				'name' => single_cat_title( '', false ),
				'url'  => '',
			];
		} elseif ( is_archive() ) {
			$items[] = [
				'name' => get_the_archive_title(),
				'url'  => '',
			];
		}

		// Build HTML with BreadcrumbList schema.
		$output  = '<nav class="si-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'jat-search-intel' ) . '">';
		$output .= '<ol class="si-breadcrumbs__list" itemscope itemtype="https://schema.org/BreadcrumbList">';

		foreach ( $items as $i => $item ) {
			$position = $i + 1;
			$is_last  = $i === count( $items ) - 1;

			$output .= '<li class="si-breadcrumbs__item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';

			if ( ! $is_last && $item['url'] ) {
				$output .= '<a href="' . esc_url( $item['url'] ) . '" itemprop="item"><span itemprop="name">' . $item['name'] . '</span></a>';
			} else {
				$output .= '<span itemprop="name">' . $item['name'] . '</span>';
			}

			$output .= '<meta itemprop="position" content="' . $position . '">';
			$output .= '</li>';

			if ( ! $is_last ) {
				$output .= '<li class="si-breadcrumbs__separator" aria-hidden="true">' . $separator . '</li>';
			}
		}

		$output .= '</ol></nav>';

		return $output;
	}
}
