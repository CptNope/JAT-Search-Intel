<?php
namespace Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Q&A Detection Engine — identifies question-answer patterns in content.
 *
 * Detects heading questions, Q:/A: patterns, definitions, numbered steps,
 * and comparisons, then classifies content as eligible for FAQPage, HowTo,
 * Q&A, or Definition schema.
 *
 * @since 2.1.0
 */
class QA_Detector {

	/**
	 * Content classification types.
	 */
	private const TYPE_FAQ        = 'FAQPage';
	private const TYPE_HOWTO      = 'HowTo';
	private const TYPE_QA         = 'QAPage';
	private const TYPE_DEFINITION = 'DefinedTerm';

	/** @var array */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;

		if ( ! empty( $settings['enable_qa_detector'] ) ) {
			add_filter( 'search_intel_singular_schema', [ $this, 'auto_inject_schema' ], 20, 2 );
		}
	}

	/**
	 * Run all detectors on content and return combined results.
	 *
	 * @param string $content HTML content.
	 * @return array{questions: array, qa_pairs: array, definitions: array, steps: array, comparisons: array}
	 */
	public function detect_all( string $content ): array {
		return [
			'questions'   => $this->detect_heading_questions( $content ),
			'qa_pairs'    => $this->detect_qa_patterns( $content ),
			'definitions' => $this->detect_definitions( $content ),
			'steps'       => $this->detect_steps( $content ),
			'comparisons' => $this->detect_comparisons( $content ),
		];
	}

	/**
	 * Detect H2/H3 headings ending with "?".
	 *
	 * @param string $content HTML content.
	 * @return array List of question-answer pairs.
	 */
	public function detect_heading_questions( string $content ): array {
		$results = [];

		$pattern = '/<h([2-3])[^>]*>\s*(.*?\?)\s*<\/h\1>\s*([\s\S]*?)(?=<h[2-3]|$)/i';

		if ( preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$question = wp_strip_all_tags( trim( $match[2] ) );
				$answer   = $this->extract_answer_text( $match[3] );

				if ( $question && strlen( $answer ) > 20 ) {
					$results[] = [
						'question' => $question,
						'answer'   => $answer,
						'source'   => 'heading_question',
						'heading'  => 'h' . $match[1],
					];
				}
			}
		}

		return $results;
	}

	/**
	 * Detect bold "Q:" / "A:" inline patterns.
	 *
	 * @param string $content HTML content.
	 * @return array List of question-answer pairs.
	 */
	public function detect_qa_patterns( string $content ): array {
		$results = [];
		$text    = wp_strip_all_tags( $content );

		// Pattern: Q: ... ? A: ...
		$pattern = '/(?:Q|Question)\s*:\s*(.*?\?)\s*(?:A|Answer)\s*:\s*(.*?)(?=(?:Q|Question)\s*:|$)/is';

		if ( preg_match_all( $pattern, $text, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$question = trim( $match[1] );
				$answer   = trim( $match[2] );

				if ( $question && strlen( $answer ) > 20 ) {
					$results[] = [
						'question' => $question,
						'answer'   => $answer,
						'source'   => 'qa_prefix',
					];
				}
			}
		}

		// Pattern: <strong>Question?</strong> Answer text
		if ( preg_match_all( '/<strong>(.*?\?)<\/strong>\s*(.*?)(?=<strong>|<h[2-6]|<\/div|$)/is', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$question = wp_strip_all_tags( trim( $match[1] ) );
				$answer   = wp_strip_all_tags( trim( $match[2] ) );

				if ( $question && strlen( $answer ) > 20 ) {
					$results[] = [
						'question' => $question,
						'answer'   => $answer,
						'source'   => 'bold_question',
					];
				}
			}
		}

		return $results;
	}

	/**
	 * Detect definition patterns: "X is defined as...", "X refers to...", etc.
	 *
	 * @param string $content HTML content.
	 * @return array List of definitions.
	 */
	public function detect_definitions( string $content ): array {
		$results = [];
		$text    = wp_strip_all_tags( $content );

		$patterns = [
			'/([A-Z][a-zA-Z\s]{2,40})\s+is\s+defined\s+as\s+([^.]+\.)/i',
			'/([A-Z][a-zA-Z\s]{2,40})\s+refers\s+to\s+([^.]+\.)/i',
			'/([A-Z][a-zA-Z\s]{2,40})\s+is\s+a\s+type\s+of\s+([^.]+\.)/i',
			'/([A-Z][a-zA-Z\s]{2,40})\s+is\s+the\s+process\s+of\s+([^.]+\.)/i',
			'/([A-Z][a-zA-Z\s]{2,40})\s+means\s+that\s+([^.]+\.)/i',
			'/([A-Z][a-zA-Z\s]{2,40})\s+can\s+be\s+described\s+as\s+([^.]+\.)/i',
		];

		foreach ( $patterns as $pattern ) {
			if ( preg_match_all( $pattern, $text, $matches, PREG_SET_ORDER ) ) {
				foreach ( $matches as $match ) {
					$term       = trim( $match[1] );
					$definition = trim( $match[2] );

					if ( strlen( $definition ) > 15 ) {
						$results[] = [
							'term'       => $term,
							'definition' => $term . ' ' . $this->get_definition_verb( $match[0] ) . ' ' . $definition,
							'source'     => 'definition_pattern',
						];
					}
				}
			}
		}

		return $results;
	}

	/**
	 * Detect numbered step patterns: "Step 1:", "1.", "First,".
	 *
	 * @param string $content HTML content.
	 * @return array List of steps.
	 */
	public function detect_steps( string $content ): array {
		$results = [];

		// Pattern 1: "Step N:" in text.
		$text = wp_strip_all_tags( $content );
		if ( preg_match_all( '/Step\s+(\d+)\s*[:.]\s*([^\n.]+)/i', $text, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$results[] = [
					'number' => (int) $match[1],
					'text'   => trim( $match[2] ),
					'source' => 'step_prefix',
				];
			}
		}

		// Pattern 2: Ordered list items.
		if ( preg_match_all( '/<li[^>]*>(.*?)<\/li>/is', $content, $matches ) ) {
			// Check if inside an <ol>.
			if ( preg_match( '/<ol[^>]*>(.*?)<\/ol>/is', $content ) ) {
				foreach ( $matches[1] as $i => $li ) {
					$text_content = wp_strip_all_tags( trim( $li ) );
					if ( strlen( $text_content ) > 10 ) {
						$results[] = [
							'number' => $i + 1,
							'text'   => $text_content,
							'source' => 'ordered_list',
						];
					}
				}
			}
		}

		return $results;
	}

	/**
	 * Detect comparison patterns: "X vs Y", "difference between X and Y".
	 *
	 * @param string $content HTML content.
	 * @return array List of comparisons.
	 */
	public function detect_comparisons( string $content ): array {
		$results = [];
		$text    = wp_strip_all_tags( $content );

		// "X vs Y" or "X versus Y".
		if ( preg_match_all( '/(\w+)\s+(?:vs\.?|versus)\s+(\w+)/i', $text, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$results[] = [
					'item_a' => trim( $match[1] ),
					'item_b' => trim( $match[2] ),
					'source' => 'vs_pattern',
				];
			}
		}

		// "difference between X and Y".
		if ( preg_match_all( '/difference(?:s)?\s+between\s+(\w+)\s+and\s+(\w+)/i', $text, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$results[] = [
					'item_a' => trim( $match[1] ),
					'item_b' => trim( $match[2] ),
					'source' => 'difference_pattern',
				];
			}
		}

		return $results;
	}

	/**
	 * Classify content into eligible schema types based on detected patterns.
	 *
	 * @param string $content HTML content.
	 * @return array List of eligible schema types with confidence scores.
	 */
	public function classify( string $content ): array {
		$detected    = $this->detect_all( $content );
		$eligible    = [];

		// FAQPage — needs 2+ question-answer pairs.
		$total_qa = count( $detected['questions'] ) + count( $detected['qa_pairs'] );
		if ( $total_qa >= 2 ) {
			$eligible[] = [
				'type'       => self::TYPE_FAQ,
				'confidence' => min( 100, $total_qa * 20 ),
				'count'      => $total_qa,
				'reason'     => sprintf( '%d question-answer pairs detected', $total_qa ),
			];
		}

		// HowTo — needs 3+ steps.
		$step_count = count( $detected['steps'] );
		if ( $step_count >= 3 ) {
			$eligible[] = [
				'type'       => self::TYPE_HOWTO,
				'confidence' => min( 100, $step_count * 15 ),
				'count'      => $step_count,
				'reason'     => sprintf( '%d steps detected', $step_count ),
			];
		}

		// QAPage — needs at least 1 clear Q&A pair.
		if ( $total_qa >= 1 ) {
			$eligible[] = [
				'type'       => self::TYPE_QA,
				'confidence' => min( 100, $total_qa * 25 ),
				'count'      => $total_qa,
				'reason'     => sprintf( '%d Q&A patterns detected', $total_qa ),
			];
		}

		// DefinedTerm — needs at least 1 definition.
		$def_count = count( $detected['definitions'] );
		if ( $def_count >= 1 ) {
			$eligible[] = [
				'type'       => self::TYPE_DEFINITION,
				'confidence' => min( 100, $def_count * 30 ),
				'count'      => $def_count,
				'reason'     => sprintf( '%d definitions detected', $def_count ),
			];
		}

		// Sort by confidence descending.
		usort( $eligible, function ( $a, $b ) {
			return $b['confidence'] <=> $a['confidence'];
		} );

		return $eligible;
	}

	/**
	 * Auto-inject detected Q&A as FAQ schema into the singular schema.
	 *
	 * @param array $schema  Existing schema.
	 * @param int   $post_id Post ID.
	 * @return array
	 */
	public function auto_inject_schema( array $schema, int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post || empty( $post->post_content ) ) {
			return $schema;
		}

		// Only inject if no manual FAQ meta is set.
		$manual_faqs = get_post_meta( $post_id, '_search_intel_faqs', true );
		if ( ! empty( $manual_faqs ) ) {
			return $schema;
		}

		$detected = $this->detect_all( $post->post_content );
		$qa_pairs = array_merge( $detected['questions'], $detected['qa_pairs'] );

		if ( count( $qa_pairs ) >= 2 ) {
			// Store auto-detected FAQ for the AEO_Manager to pick up.
			$faqs = [];
			foreach ( $qa_pairs as $pair ) {
				$faqs[] = [
					'question' => $pair['question'],
					'answer'   => $pair['answer'],
				];
			}
			// Save as transient so AEO_Manager's auto_detect_faqs can use it.
			set_transient( 'si_auto_faq_' . $post_id, $faqs, DAY_IN_SECONDS );
		}

		return $schema;
	}

	/**
	 * Extract plain text answer from HTML content following a heading.
	 *
	 * @param string $html HTML content.
	 * @return string Plain text answer.
	 */
	private function extract_answer_text( string $html ): string {
		// Take content up to the next heading or end, prioritize first paragraph.
		if ( preg_match( '/<p[^>]*>(.*?)<\/p>/is', $html, $match ) ) {
			return wp_strip_all_tags( trim( $match[1] ) );
		}

		// Fallback: strip tags and take first 500 chars.
		$text = wp_strip_all_tags( trim( $html ) );
		if ( strlen( $text ) > 500 ) {
			$text = substr( $text, 0, 500 );
		}

		return $text;
	}

	/**
	 * Extract the definition verb phrase from a matched pattern.
	 *
	 * @param string $matched Full matched string.
	 * @return string
	 */
	private function get_definition_verb( string $matched ): string {
		$verbs = [
			'is defined as',
			'refers to',
			'is a type of',
			'is the process of',
			'means that',
			'can be described as',
		];

		foreach ( $verbs as $verb ) {
			if ( stripos( $matched, $verb ) !== false ) {
				return $verb;
			}
		}

		return 'is';
	}
}
