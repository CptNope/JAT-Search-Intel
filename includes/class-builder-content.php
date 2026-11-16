<?php
/**
 * Read the real content of pages made with page builders.
 *
 * Page builders keep their content outside post_content (Elementor, Beaver
 * Builder, Bricks) or wrap it in their own shortcodes (Divi, WPBakery). This
 * turns each into plain HTML (headings, paragraphs, lists and images) so
 * scores, Passage Coach, descriptions and schema see what visitors see.
 * Pages without a builder are returned unchanged.
 *
 * @package JAT_Search_Intel
 * @since 3.8.0
 */

namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Page builder content extraction.
 */
class Builder_Content {

	/** @var array<string,string> Per-request cache: "post ID:content hash" => HTML. */
	private static $cache = [];

	/** Elementor/Bricks setting keys that hold visible text. */
	private const TEXT_KEYS = [ 'editor', 'text', 'content', 'description_text', 'title_text', 'testimonial_content', 'html', 'inner_text', 'alert_description', 'tab_content', 'item_description', 'description' ];

	/** Setting keys that hold a heading or title. */
	private const TITLE_KEYS = [ 'title', 'heading', 'alert_title', 'tab_title', 'item_title' ];

	/**
	 * The page content as HTML, with page-builder content extracted.
	 *
	 * @param \WP_Post|object $post Post.
	 * @return string
	 */
	public static function get( $post ): string {
		$content = (string) ( $post->post_content ?? '' );
		$post_id = (int) ( $post->ID ?? 0 );
		if ( $post_id <= 0 ) {
			return $content;
		}
		$key = $post_id . ':' . md5( $content );
		if ( isset( self::$cache[ $key ] ) ) {
			return self::$cache[ $key ];
		}

		$builder = self::detect( $post_id, $content );
		$html    = $content;

		switch ( $builder ) {
			case 'elementor':
				$data = json_decode( (string) get_post_meta( $post_id, '_elementor_data', true ), true );
				$html = is_array( $data ) ? self::from_tree( $data, 'elements', 'settings', 'widgetType' ) : $content;
				break;
			case 'bricks':
				$data = get_post_meta( $post_id, '_bricks_page_content_2', true );
				$html = is_array( $data ) ? self::from_flat( $data ) : $content;
				break;
			case 'beaver':
				$data = get_post_meta( $post_id, '_fl_builder_data', true );
				$html = is_array( $data ) ? self::from_beaver( $data ) : $content;
				break;
			case 'divi':
			case 'wpbakery':
				$html = self::from_shortcodes( $content );
				break;
		}

		if ( '' === $builder && self::has_dynamic_blocks( $content ) ) {
			$rendered = self::render_blocks( $post, $content );
			if ( '' !== trim( wp_strip_all_tags( $rendered ) ) ) {
				$html = $rendered;
			}
		}

		if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
			$html = $content;
		}

		/**
		 * Filter the content used for analysis and generated output.
		 *
		 * @param string $html    Content as HTML.
		 * @param int    $post_id Post ID.
		 * @param string $builder Detected builder ('' for none).
		 */
		$html = (string) apply_filters( 'jat_search_intel_builder_content', $html, $post_id, $builder );

		self::$cache[ $key ] = $html;
		return $html;
	}

	/** @var bool Guards against a block that renders this same content again. */
	private static $rendering = false;

	/**
	 * Whether the content uses server-rendered (dynamic) blocks.
	 *
	 * Their text is produced by PHP when the page is viewed, so post_content
	 * holds only the block comment and the analyzer would see an empty page.
	 *
	 * @param string $content post_content.
	 * @return bool
	 */
	public static function has_dynamic_blocks( string $content ): bool {
		if ( false === strpos( $content, '<!-- wp:' ) || ! function_exists( 'parse_blocks' ) || ! class_exists( 'WP_Block_Type_Registry' ) ) {
			return false;
		}
		$registry = \WP_Block_Type_Registry::get_instance();
		$stack    = parse_blocks( $content );
		while ( $stack ) {
			$block = array_pop( $stack );
			$name  = $block['blockName'] ?? null;
			if ( $name && ! in_array( $name, [ 'core/post-content', 'core/template-part' ], true ) ) {
				$type = $registry->get_registered( $name );
				if ( $type && $type->is_dynamic() ) {
					return true;
				}
			}
			foreach ( (array) ( $block['innerBlocks'] ?? [] ) as $inner ) {
				$stack[] = $inner;
			}
		}
		return false;
	}

	/**
	 * Render blocks for a post the way the front end would.
	 *
	 * @param \WP_Post|object $post    Post.
	 * @param string          $content post_content.
	 * @return string
	 */
	private static function render_blocks( $post, string $content ): string {
		if ( self::$rendering || ! function_exists( 'do_blocks' ) ) {
			return '';
		}
		self::$rendering = true;
		$previous        = $GLOBALS['post'] ?? null;
		if ( $post instanceof \WP_Post ) {
			$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Blocks read the current post while rendering; restored below.
			setup_postdata( $post );
		}
		try {
			$html = (string) do_blocks( $content );
		} catch ( \Throwable $e ) {
			$html = '';
		}
		$GLOBALS['post'] = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring the previous global.
		if ( $previous instanceof \WP_Post ) {
			setup_postdata( $previous );
		}
		self::$rendering = false;
		// Drop scripts, styles and inline JSON-LD that blocks print alongside their markup.
		return (string) preg_replace( '#<(script|style)\b[^>]*>.*?</\1>#is', '', $html );
	}

	/**
	 * Which builder made the page.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $content post_content.
	 * @return string elementor|bricks|beaver|divi|wpbakery or ''.
	 */
	public static function detect( int $post_id, string $content ): string {
		if ( 'builder' === get_post_meta( $post_id, '_elementor_edit_mode', true ) && '' !== (string) get_post_meta( $post_id, '_elementor_data', true ) ) {
			return 'elementor';
		}
		if ( ! empty( get_post_meta( $post_id, '_bricks_page_content_2', true ) ) ) {
			return 'bricks';
		}
		if ( get_post_meta( $post_id, '_fl_builder_enabled', true ) && ! empty( get_post_meta( $post_id, '_fl_builder_data', true ) ) ) {
			return 'beaver';
		}
		if ( false !== strpos( $content, '[et_pb_' ) ) {
			return 'divi';
		}
		if ( false !== strpos( $content, '[vc_' ) ) {
			return 'wpbakery';
		}
		return '';
	}

	/**
	 * Clear the per-request cache (after a save).
	 *
	 * @param int $post_id Post ID, or 0 for all.
	 */
	public static function flush( int $post_id = 0 ): void {
		if ( ! $post_id ) {
			self::$cache = [];
			return;
		}
		foreach ( array_keys( self::$cache ) as $key ) {
			if ( 0 === strpos( $key, $post_id . ':' ) ) {
				unset( self::$cache[ $key ] );
			}
		}
	}

	/**
	 * Elementor: a tree of elements with settings.
	 *
	 * @param array  $nodes        Nodes.
	 * @param string $children_key Key holding child nodes.
	 * @param string $settings_key Key holding settings.
	 * @param string $type_key     Key holding the widget type.
	 * @return string
	 */
	private static function from_tree( array $nodes, string $children_key, string $settings_key, string $type_key ): string {
		$html = '';
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			$html .= self::widget_html( (string) ( $node[ $type_key ] ?? '' ), (array) ( $node[ $settings_key ] ?? [] ) );
			if ( ! empty( $node[ $children_key ] ) && is_array( $node[ $children_key ] ) ) {
				$html .= self::from_tree( $node[ $children_key ], $children_key, $settings_key, $type_key );
			}
		}
		return $html;
	}

	/**
	 * Bricks: a flat list of elements in page order.
	 *
	 * @param array $elements Elements.
	 * @return string
	 */
	private static function from_flat( array $elements ): string {
		$html = '';
		foreach ( $elements as $element ) {
			if ( is_array( $element ) ) {
				$settings = (array) ( $element['settings'] ?? [] );
				if ( 'heading' === ( $element['name'] ?? '' ) && ! empty( $settings['text'] ) ) {
					$tag   = preg_match( '/^h[1-6]$/', (string) ( $settings['tag'] ?? '' ) ) ? $settings['tag'] : 'h2';
					$html .= '<' . $tag . '>' . wp_kses_post( (string) $settings['text'] ) . '</' . $tag . '>';
					continue;
				}
				$html .= self::widget_html( (string) ( $element['name'] ?? '' ), $settings );
			}
		}
		return $html;
	}

	/**
	 * Beaver Builder: nodes keyed by ID with settings objects, in order.
	 *
	 * @param array $nodes Nodes.
	 * @return string
	 */
	private static function from_beaver( array $nodes ): string {
		uasort(
			$nodes,
			static function ( $a, $b ) {
				return (int) ( is_object( $a ) ? ( $a->position ?? 0 ) : 0 ) <=> (int) ( is_object( $b ) ? ( $b->position ?? 0 ) : 0 );
			}
		);
		$html = '';
		foreach ( $nodes as $node ) {
			$settings = is_object( $node ) && isset( $node->settings ) ? (array) $node->settings : [];
			$type     = (string) ( $settings['type'] ?? '' );
			$html    .= self::widget_html( $type, $settings );
		}
		return $html;
	}

	/**
	 * HTML for one widget's settings.
	 *
	 * @param string $type     Widget type.
	 * @param array  $settings Settings.
	 * @return string
	 */
	private static function widget_html( string $type, array $settings ): string {
		$html = '';

		if ( in_array( $type, [ 'heading', 'animated-headline' ], true ) ) {
			$text = (string) ( $settings['title'] ?? $settings['heading'] ?? $settings['before_text'] ?? '' );
			if ( '' !== trim( $text ) ) {
				$size = (string) ( $settings['header_size'] ?? $settings['tag'] ?? 'h2' );
				$tag  = preg_match( '/^h[1-6]$/', $size ) ? $size : 'h2';
				return '<' . $tag . '>' . wp_kses_post( $text ) . '</' . $tag . '>';
			}
		}

		foreach ( self::TITLE_KEYS as $key ) {
			if ( isset( $settings[ $key ] ) && is_string( $settings[ $key ] ) && '' !== trim( $settings[ $key ] ) ) {
				$html .= '<h3>' . wp_kses_post( $settings[ $key ] ) . '</h3>';
				break;
			}
		}

		foreach ( self::TEXT_KEYS as $key ) {
			if ( isset( $settings[ $key ] ) && is_string( $settings[ $key ] ) && '' !== trim( $settings[ $key ] ) ) {
				$text  = $settings[ $key ];
				$html .= false === strpos( $text, '<' ) ? '<p>' . esc_html( $text ) . '</p>' : wp_kses_post( $text );
			}
		}

		// Repeaters such as icon lists, tabs, accordions and price lists.
		foreach ( $settings as $value ) {
			if ( ! is_array( $value ) || ! isset( $value[0] ) || ! is_array( $value[0] ) ) {
				continue;
			}
			$items = '';
			foreach ( $value as $item ) {
				$title = (string) ( $item['tab_title'] ?? $item['item_title'] ?? $item['title'] ?? $item['text'] ?? '' );
				$body  = (string) ( $item['tab_content'] ?? $item['item_description'] ?? $item['content'] ?? '' );
				if ( '' !== trim( $body ) ) {
					$items .= ( '' !== trim( $title ) ? '<h3>' . wp_kses_post( $title ) . '</h3>' : '' ) . wp_kses_post( $body );
				} elseif ( '' !== trim( $title ) ) {
					$items .= '<li>' . wp_kses_post( $title ) . '</li>';
				}
			}
			if ( '' !== $items ) {
				$html .= false !== strpos( $items, '<li>' ) && false === strpos( $items, '<h3>' ) ? '<ul>' . $items . '</ul>' : $items;
			}
		}

		$image = $settings['image']['url'] ?? ( $settings['photo_src'] ?? '' );
		if ( is_string( $image ) && '' !== $image ) {
			$alt   = (string) ( $settings['image']['alt'] ?? $settings['alt'] ?? '' );
			$html .= '<img src="' . esc_url( $image ) . '" alt="' . esc_attr( $alt ) . '">';
		}

		return $html;
	}

	/**
	 * Divi and WPBakery: drop builder shortcodes, keep the text inside them,
	 * and turn heading/title attributes into headings.
	 *
	 * @param string $content Shortcode content.
	 * @return string
	 */
	private static function from_shortcodes( string $content ): string {
		// Title-like attributes become headings so the outline survives.
		$content = (string) preg_replace_callback(
			'/\[(?:et_pb_[a-z_]+|vc_custom_heading|vc_[a-z_]+)\b([^\]]*)\]/i',
			static function ( $m ) {
				if ( preg_match( '/\b(?:title|heading|text)="([^"]+)"/i', $m[1], $attr ) ) {
					$tag = 'h3';
					if ( preg_match( '/\bheader_level="(h[1-6])"/i', $m[1], $level ) ) {
						$tag = strtolower( $level[1] );
					} elseif ( preg_match( '/font_container="[^"]*tag:(h[1-6])/i', $m[1], $level ) ) {
						$tag = strtolower( $level[1] );
					}
					return '<' . $tag . '>' . esc_html( html_entity_decode( $attr[1], ENT_QUOTES ) ) . '</' . $tag . '>';
				}
				return '';
			},
			$content
		);
		// Closing tags.
		$content = (string) preg_replace( '/\[\/(?:et_pb_[a-z_]+|vc_[a-z_]+)\]/i', '', $content );
		return trim( $content );
	}
}
