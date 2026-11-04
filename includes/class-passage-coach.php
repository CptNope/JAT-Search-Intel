<?php
/**
 * Passage Coach: block-level advice for being quoted by search and AI answers.
 *
 * AI assistants and featured snippets lift individual passages, not whole pages.
 * This looks at each top-level block of a post and points at the exact passage to
 * change: a slow intro, a question heading that is not answered straight away, a
 * figure with no source, a wall of text, a comparison without a table, and so on.
 *
 * The analysis is deterministic (no AI call), so it is instant and free. The editor
 * panel (admin/js/passage-coach.js) can insert starter blocks for some findings.
 *
 * @package JAT_Search_Intel
 */

namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Passage Coach.
 */
class Passage_Coach {

	/** Ideal length of the opening answer, in words. */
	private const INTRO_MAX_WORDS = 60;

	/** Paragraphs longer than this are hard to quote. */
	private const LONG_PARAGRAPH_WORDS = 120;

	/** Openings that delay the answer. */
	private const SLOW_OPENINGS = [
		'in this article', 'in this post', 'in this guide', 'in this blog', 'welcome to', 'have you ever',
		'are you looking', 'if you are looking', "if you're looking", 'today we', "today, we", 'let\'s dive', 'let us dive',
		'when it comes to', 'in today\'s', 'in the world of', 'it\'s no secret', 'we all know',
	];

	private const COMPARISON_PATTERN = '/\b(vs\.?|versus|compared?|comparison|alternatives?|best\s+\w+\s+for|top\s+\d+|pros\s+and\s+cons)\b/i';

	/** @var array<string,mixed> */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;

		add_action( 'rest_api_init', [ $this, 'register_route' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue' ] );
	}

	/**
	 * POST jat-search-intel/v1/coach/{id} — analyse unsaved editor content.
	 */
	public function register_route(): void {
		register_rest_route(
			'jat-search-intel/v1',
			'/coach/(?P<id>\d+)',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'rest_analyze' ],
				'permission_callback' => static function ( \WP_REST_Request $request ) {
					return current_user_can( 'edit_post', (int) $request['id'] );
				},
				'args'                => [
					'content' => [
						'type'     => 'string',
						'required' => false,
					],
					'title'   => [
						'type'     => 'string',
						'required' => false,
					],
				],
			]
		);
	}

	/**
	 * REST callback.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function rest_analyze( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id = (int) $request['id'];
		$post    = get_post( $post_id );
		$content = null !== $request['content'] ? (string) $request['content'] : (string) ( $post->post_content ?? '' );
		$title   = null !== $request['title'] ? (string) $request['title'] : (string) ( $post->post_title ?? '' );

		return new \WP_REST_Response(
			[
				'suggestions' => self::analyze(
					$content,
					[
						'title'      => $title,
						'keyword'    => (string) get_post_meta( $post_id, '_jat_search_intel_focus_keyword', true ),
						'ai_summary' => (string) get_post_meta( $post_id, '_jat_search_intel_ai_summary', true ),
						'site_host'  => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
					]
				),
			],
			200
		);
	}

	/**
	 * Enqueue the editor panel (plain script, no build step).
	 */
	public function enqueue(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$types  = (array) ( $this->settings['enabled_post_types'] ?? [ 'post', 'page' ] );
		if ( ! $screen || ! in_array( $screen->post_type, $types, true ) ) {
			return;
		}
		wp_enqueue_script(
			'jat-search-intel-passage-coach',
			JAT_SEARCH_INTEL_URL . 'admin/js/passage-coach.js',
			[ 'wp-plugins', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-blocks', 'wp-api-fetch', 'wp-i18n', 'wp-compose' ],
			JAT_SEARCH_INTEL_VERSION,
			true
		);
		wp_set_script_translations( 'jat-search-intel-passage-coach', 'jat-search-intel' );
	}

	/**
	 * Analyse serialized post content.
	 *
	 * @param string $content Post content (block markup or classic HTML).
	 * @param array  $context title, keyword, ai_summary, site_host.
	 * @return array<int,array{id:string,type:string,severity:string,title:string,detail:string,block:int,excerpt:string,action:string}>
	 */
	public static function analyze( string $content, array $context = [] ): array {
		$blocks = self::top_level_blocks( $content );
		$out    = [];
		$add    = static function ( string $type, string $severity, string $title, string $detail, int $block = -1, string $excerpt = '', string $action = 'select' ) use ( &$out ): void {
			$out[] = [
				'id'       => $type . ':' . $block,
				'type'     => $type,
				'severity' => $severity,
				'title'    => $title,
				'detail'   => $detail,
				'block'    => $block,
				'excerpt'  => $excerpt,
				'action'   => $block < 0 && 'select' === $action ? 'none' : $action,
			];
		};

		if ( ! $blocks ) {
			return [];
		}

		$title    = (string) ( $context['title'] ?? '' );
		$keyword  = strtolower( trim( (string) ( $context['keyword'] ?? '' ) ) );
		$host     = strtolower( (string) ( $context['site_host'] ?? '' ) );
		$all_text = strtolower( $title . ' ' . implode( ' ', array_column( $blocks, 'text' ) ) );

		// 1. Answer-first intro.
		$first_p = null;
		foreach ( $blocks as $i => $b ) {
			if ( 'paragraph' === $b['kind'] && '' !== $b['text'] ) {
				$first_p = $i;
				break;
			}
			if ( 'heading' === $b['kind'] ) {
				break; // A heading before any paragraph means there is no intro.
			}
		}
		if ( null === $first_p ) {
			$add(
				'intro-missing',
				'high',
				__( 'Open with a direct answer', 'jat-search-intel' ),
				__( 'Start with a 40–60 word paragraph that answers the main question on its own. That is the passage AI answers and featured snippets are most likely to quote.', 'jat-search-intel' ),
				0,
				'',
				'insert-intro'
			);
		} else {
			$intro       = $blocks[ $first_p ];
			$intro_lower = strtolower( $intro['text'] );
			$slow        = '';
			foreach ( self::SLOW_OPENINGS as $phrase ) {
				if ( 0 === strpos( $intro_lower, $phrase ) ) {
					$slow = $phrase;
					break;
				}
			}
			if ( '' !== $slow ) {
				$add(
					'intro-slow',
					'high',
					__( 'Your intro warms up instead of answering', 'jat-search-intel' ),
					/* translators: %s: the opening phrase. */
					sprintf( __( 'Openings like "%s…" push the answer down. Lead with the answer in the first sentence, then add context.', 'jat-search-intel' ), $slow ),
					$first_p,
					self::excerpt( $intro['text'] ),
					'insert-intro'
				);
			} elseif ( $intro['words'] > self::INTRO_MAX_WORDS ) {
				$add(
					'intro-long',
					'medium',
					__( 'Tighten the opening paragraph', 'jat-search-intel' ),
					/* translators: %d: word count. */
					sprintf( __( 'The first paragraph is %d words. A self-contained 40–60 word answer is far easier to quote; move the detail into the next paragraph.', 'jat-search-intel' ), $intro['words'] ),
					$first_p,
					self::excerpt( $intro['text'] )
				);
			}
			if ( '' !== $keyword && false === strpos( $intro_lower, $keyword ) ) {
				$add(
					'intro-keyword',
					'low',
					__( 'Name the topic in the first paragraph', 'jat-search-intel' ),
					/* translators: %s: focus keyword. */
					sprintf( __( 'The focus keyword "%s" does not appear in the opening paragraph, so the answer is less clearly about it.', 'jat-search-intel' ), $keyword ),
					$first_p,
					self::excerpt( $intro['text'] )
				);
			}
		}

		$has_table   = false;
		$has_list    = false;
		$ext_links   = 0;
		$h2_count    = 0;
		$first_h2    = -1;
		$year_now    = (int) gmdate( 'Y' );
		$stale_found = 0;
		$stat_found  = 0;
		$long_found  = 0;

		foreach ( $blocks as $i => $b ) {
			if ( 'table' === $b['kind'] ) {
				$has_table = true;
			}
			if ( 'list' === $b['kind'] ) {
				$has_list = true;
			}
			if ( 'heading' === $b['kind'] && 2 === $b['level'] ) {
				$h2_count++;
				$first_h2 = $first_h2 < 0 ? $i : $first_h2;
			}
			foreach ( $b['links'] as $href ) {
				$link_host = strtolower( (string) wp_parse_url( $href, PHP_URL_HOST ) );
				if ( '' !== $link_host && $link_host !== $host ) {
					$ext_links++;
				}
			}

			// 2. Question headings should be answered in the next block's first sentence.
			if ( 'heading' === $b['kind'] && '?' === substr( rtrim( $b['text'] ), -1 ) ) {
				$next = $blocks[ $i + 1 ] ?? null;
				if ( ! $next || 'paragraph' !== $next['kind'] ) {
					$add(
						'question-unanswered',
						'medium',
						__( 'Answer this question right under the heading', 'jat-search-intel' ),
						__( 'Put a one- or two-sentence answer directly below a question heading. Answer engines pair the question with the paragraph that follows it.', 'jat-search-intel' ),
						$i,
						self::excerpt( $b['text'] )
					);
				} elseif ( self::first_sentence_words( $next['text'] ) > 35 ) {
					$add(
						'question-slow',
						'low',
						__( 'Make the first sentence after this question the answer', 'jat-search-intel' ),
						__( 'The paragraph under this question takes a long first sentence to get going. Answer in under 30 words, then explain.', 'jat-search-intel' ),
						$i + 1,
						self::excerpt( $next['text'] )
					);
				}
			}

			if ( 'paragraph' !== $b['kind'] ) {
				continue;
			}

			// 3. Walls of text.
			if ( $b['words'] > self::LONG_PARAGRAPH_WORDS && $long_found < 3 ) {
				$long_found++;
				$add(
					'long-paragraph',
					'low',
					__( 'Split this paragraph', 'jat-search-intel' ),
					/* translators: %d: word count. */
					sprintf( __( '%d words in one paragraph. Passages of 40–100 words, each making one point, are the ones that get lifted into answers.', 'jat-search-intel' ), $b['words'] ),
					$i,
					self::excerpt( $b['text'] )
				);
			}

			// 4. Figures without a source.
			if ( $stat_found < 3 && empty( $b['links'] ) && preg_match( '/\b\d+(?:[.,]\d+)?\s?(%|percent|per cent|million|billion|times|x\b)/i', $b['text'] ) ) {
				$stat_found++;
				$add(
					'stat-unsourced',
					'medium',
					__( 'Cite a source for this figure', 'jat-search-intel' ),
					__( 'Statistics with a linked source are more trusted and more often cited by AI answers. Link to where the number comes from.', 'jat-search-intel' ),
					$i,
					self::excerpt( $b['text'] )
				);
			}

			// 5. Dated references.
			if ( $stale_found < 2 && preg_match_all( '/\b(?:in|as of|since|for|during)\s+((?:19|20)\d{2})\b/i', $b['text'], $years ) ) {
				foreach ( $years[1] as $year ) {
					if ( (int) $year < $year_now - 1 ) {
						$stale_found++;
						$add(
							'stale-year',
							'low',
							/* translators: %s: a year. */
							sprintf( __( 'Check whether "%s" is still current', 'jat-search-intel' ), $year ),
							__( 'This passage refers to an older year. Update the facts or say clearly that they are historical; AI assistants favour current information.', 'jat-search-intel' ),
							$i,
							self::excerpt( $b['text'] )
						);
						break;
					}
				}
			}
		}

		// 6. Comparison content without a table.
		if ( ! $has_table && preg_match( self::COMPARISON_PATTERN, $all_text ) ) {
			$add(
				'comparison-table',
				'medium',
				__( 'Add a comparison table', 'jat-search-intel' ),
				__( 'This reads like a comparison. A table with one row per option and a column per criterion is the format AI answers most often reuse for "X vs Y" and "best" questions.', 'jat-search-intel' ),
				$first_h2 >= 0 ? $first_h2 : count( $blocks ) - 1,
				'',
				'insert-table'
			);
		}

		// 7. Structure.
		$total_words = array_sum( array_column( $blocks, 'words' ) );
		if ( $total_words > 600 && $h2_count < 2 ) {
			$add(
				'few-headings',
				'medium',
				__( 'Break the article into sections', 'jat-search-intel' ),
				__( 'Long content with fewer than two H2 headings is hard to scan and hard to quote. Add a heading for each sub-question the article answers.', 'jat-search-intel' )
			);
		}
		if ( $total_words > 400 && ! $has_list && ! $has_table ) {
			$add(
				'no-list',
				'low',
				__( 'Turn steps or options into a list', 'jat-search-intel' ),
				__( 'Lists are easy for answer engines to extract. If the article has steps, options or key points, format them as a list.', 'jat-search-intel' )
			);
		}

		// 8. Sources.
		if ( $total_words > 300 && 0 === $ext_links ) {
			$add(
				'no-sources',
				'medium',
				__( 'Link to at least one authoritative source', 'jat-search-intel' ),
				__( 'Pages that cite primary sources are more trusted by search engines and AI assistants. Link to the research, data or official page behind your key claims.', 'jat-search-intel' )
			);
		}

		// 9. AI summary.
		if ( '' === trim( (string) ( $context['ai_summary'] ?? '' ) ) && $total_words > 150 ) {
			$add(
				'ai-summary',
				'low',
				__( 'Add an AI summary', 'jat-search-intel' ),
				__( 'A two- or three-sentence factual summary (in the JAT Search Intel panel) is published for AI agents in llms.txt, the Markdown copy and the page metadata.', 'jat-search-intel' ),
				-1,
				'',
				'open-summary'
			);
		}

		// Classic content is a single block in the editor: point every finding at it.
		if ( false === strpos( $content, '<!-- wp:' ) ) {
			foreach ( $out as &$item ) {
				$item['block'] = $item['block'] >= 0 ? 0 : -1;
			}
			unset( $item );
		}

		$rank = [ 'high' => 0, 'medium' => 1, 'low' => 2 ];
		usort(
			$out,
			static function ( $a, $b ) use ( $rank ) {
				return [ $rank[ $a['severity'] ], $a['block'] ] <=> [ $rank[ $b['severity'] ], $b['block'] ];
			}
		);
		return $out;
	}

	/**
	 * Top-level blocks as the editor sees them (whitespace-only freeform blocks dropped).
	 *
	 * @param string $content Content.
	 * @return array<int,array{kind:string,level:int,text:string,words:int,links:string[]}>
	 */
	public static function top_level_blocks( string $content ): array {
		$parsed = function_exists( 'parse_blocks' ) && false !== strpos( $content, '<!-- wp:' )
			? parse_blocks( $content )
			: [ [ 'blockName' => null, 'innerHTML' => $content, 'attrs' => [] ] ];

		$out = [];
		foreach ( $parsed as $block ) {
			$html = (string) ( $block['innerHTML'] ?? '' ) . self::inner_html( (array) ( $block['innerBlocks'] ?? [] ) );
			$name = (string) ( $block['blockName'] ?? '' );
			if ( '' === $name && '' === trim( $html ) ) {
				continue;
			}

			if ( '' === $name ) {
				// Classic content: one block per top-level element.
				foreach ( self::split_classic( $html ) as $piece ) {
					$out[] = $piece;
				}
				continue;
			}

			$out[] = self::describe( $name, $html, (array) ( $block['attrs'] ?? [] ) );
		}
		return $out;
	}

	/**
	 * Concatenate inner blocks' HTML.
	 *
	 * @param array $blocks Inner blocks.
	 * @return string
	 */
	private static function inner_html( array $blocks ): string {
		$html = '';
		foreach ( $blocks as $b ) {
			$html .= (string) ( $b['innerHTML'] ?? '' ) . self::inner_html( (array) ( $b['innerBlocks'] ?? [] ) );
		}
		return $html;
	}

	/**
	 * Describe one block.
	 *
	 * @param string $name  Block name.
	 * @param string $html  HTML.
	 * @param array  $attrs Attributes.
	 * @return array{kind:string,level:int,text:string,words:int,links:string[]}
	 */
	private static function describe( string $name, string $html, array $attrs ): array {
		$map  = [
			'core/paragraph' => 'paragraph',
			'core/heading'   => 'heading',
			'core/list'      => 'list',
			'core/table'     => 'table',
			'core/quote'     => 'quote',
			'core/image'     => 'media',
		];
		$kind = $map[ $name ] ?? 'other';
		if ( 'other' === $kind && preg_match( '/^<(h[1-6]|p|ul|ol|table)\b/i', ltrim( $html ), $m ) ) {
			$tag  = strtolower( $m[1] );
			$kind = 'p' === $tag ? 'paragraph' : ( 'table' === $tag ? 'table' : ( in_array( $tag, [ 'ul', 'ol' ], true ) ? 'list' : 'heading' ) );
		}
		if ( 'other' === $kind && false !== stripos( $html, '<table' ) ) {
			$kind = 'table';
		}

		$level = 0;
		if ( 'heading' === $kind ) {
			$level = (int) ( $attrs['level'] ?? ( preg_match( '/<h([1-6])/i', $html, $h ) ? $h[1] : 2 ) );
		}

		$text = trim( (string) preg_replace( '/\s+/', ' ', html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) ) );
		preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\']/i', $html, $links );

		return [
			'kind'  => $kind,
			'level' => $level,
			'text'  => $text,
			'words' => '' === $text ? 0 : count( preg_split( '/\s+/u', $text ) ?: [] ),
			'links' => array_values( array_filter( $links[1] ) ),
		];
	}

	/**
	 * Split classic (non-block) HTML into block-like pieces.
	 *
	 * @param string $html HTML.
	 * @return array
	 */
	private static function split_classic( string $html ): array {
		$html = function_exists( 'wpautop' ) ? wpautop( $html ) : $html;
		preg_match_all( '#<(p|h[1-6]|ul|ol|table|blockquote)\b[^>]*>.*?</\1>#is', $html, $m );
		$out = [];
		foreach ( $m[0] as $piece ) {
			$name  = strtolower( (string) preg_replace( '/^<([a-z0-9]+).*/is', '$1', $piece ) );
			$out[] = self::describe( 'classic/' . $name, $piece, [] );
		}
		return $out;
	}

	/**
	 * Words in the first sentence.
	 *
	 * @param string $text Text.
	 * @return int
	 */
	private static function first_sentence_words( string $text ): int {
		$sentence = (string) preg_split( '/(?<=[.!?])\s+/', $text, 2 )[0];
		return count( preg_split( '/\s+/u', trim( $sentence ) ) ?: [] );
	}

	/**
	 * Short excerpt.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function excerpt( string $text ): string {
		return mb_strlen( $text ) > 90 ? rtrim( mb_substr( $text, 0, 87 ) ) . '…' : $text;
	}
}
