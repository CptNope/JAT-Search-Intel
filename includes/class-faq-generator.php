<?php
namespace Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI-Assisted FAQ Generation — local pattern matching + optional AI enhancement.
 *
 * Extracts Q&A from headings and paragraphs using pattern matching (no API key
 * needed). Optionally enhances suggestions via a user-provided AI API key.
 * Generated FAQs are editable before saving to post meta.
 *
 * @since 2.1.0
 */
class FAQ_Generator {

	/** @var array */
	private $settings;

	/** @var QA_Detector|null */
	private $detector;

	/**
	 * Constructor.
	 *
	 * @param array            $settings Plugin settings.
	 * @param QA_Detector|null $detector Optional QA_Detector instance.
	 */
	public function __construct( array $settings, ?QA_Detector $detector = null ) {
		$this->settings = $settings;
		$this->detector = $detector;

		if ( ! empty( $settings['enable_faq_generator'] ) ) {
			if ( is_admin() ) {
				add_action( 'add_meta_boxes', [ $this, 'register_metabox' ] );
			}

			add_action( 'wp_ajax_si_generate_faqs', [ $this, 'ajax_generate' ] );
			add_action( 'wp_ajax_si_save_faqs', [ $this, 'ajax_save' ] );
		}
	}

	/**
	 * Generate FAQ suggestions from post content using local pattern matching.
	 *
	 * @param int $post_id Post ID.
	 * @return array List of FAQ suggestions.
	 */
	public function generate( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post || empty( $post->post_content ) ) {
			return [];
		}

		$content = $post->post_content;
		$faqs    = [];

		// 1. Extract Q&A from H2/H3 headings ending with "?".
		$heading_faqs = $this->extract_from_headings( $content );
		foreach ( $heading_faqs as $faq ) {
			$faqs[] = $faq;
		}

		// 2. Extract Q:/A: inline patterns.
		$qa_faqs = $this->extract_qa_patterns( $content );
		foreach ( $qa_faqs as $faq ) {
			$faqs[] = $faq;
		}

		// 3. Generate implicit questions from informational headings.
		$implicit_faqs = $this->generate_from_headings( $content );
		foreach ( $implicit_faqs as $faq ) {
			$faqs[] = $faq;
		}

		// 4. Use QA_Detector if available for additional patterns.
		if ( $this->detector ) {
			$detected = $this->detector->detect_all( $content );
			foreach ( $detected['definitions'] as $def ) {
				$faqs[] = [
					'question' => 'What is ' . $def['term'] . '?',
					'answer'   => $def['definition'],
					'source'   => 'definition',
					'editable' => true,
				];
			}
		}

		// Deduplicate by question similarity.
		$faqs = $this->deduplicate( $faqs );

		return $faqs;
	}

	/**
	 * Extract Q&A from headings ending with "?".
	 *
	 * @param string $content HTML content.
	 * @return array
	 */
	public function extract_from_headings( string $content ): array {
		$faqs = [];

		$pattern = '/<h([2-3])[^>]*>\s*(.*?\?)\s*<\/h\1>\s*([\s\S]*?)(?=<h[2-3]|$)/i';

		if ( preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$question = wp_strip_all_tags( trim( $match[2] ) );
				$answer   = $this->extract_answer( $match[3] );

				if ( $question && strlen( $answer ) > 20 ) {
					$faqs[] = [
						'question' => $question,
						'answer'   => $answer,
						'source'   => 'heading',
						'editable' => true,
					];
				}
			}
		}

		return $faqs;
	}

	/**
	 * Extract Q:/A: inline patterns.
	 *
	 * @param string $content HTML content.
	 * @return array
	 */
	public function extract_qa_patterns( string $content ): array {
		$faqs = [];
		$text = wp_strip_all_tags( $content );

		if ( preg_match_all( '/(?:Q|Question)\s*:\s*(.*?\?)\s*(?:A|Answer)\s*:\s*(.*?)(?=(?:Q|Question)\s*:|$)/is', $text, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$question = trim( $match[1] );
				$answer   = trim( $match[2] );

				if ( $question && strlen( $answer ) > 20 ) {
					$faqs[] = [
						'question' => $question,
						'answer'   => $answer,
						'source'   => 'qa_pattern',
						'editable' => true,
					];
				}
			}
		}

		return $faqs;
	}

	/**
	 * Generate implicit questions from non-question headings.
	 *
	 * Transforms headings like "Benefits of SEO" → "What are the benefits of SEO?"
	 *
	 * @param string $content HTML content.
	 * @return array
	 */
	public function generate_from_headings( string $content ): array {
		$faqs = [];

		// Match H2/H3 headings that DON'T end with "?" (informational headings).
		$pattern = '/<h([2-3])[^>]*>\s*((?:(?!\?).)+)\s*<\/h\1>\s*([\s\S]*?)(?=<h[2-3]|$)/i';

		if ( preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$heading = wp_strip_all_tags( trim( $match[2] ) );
				$answer  = $this->extract_answer( $match[3] );

				if ( strlen( $heading ) < 5 || strlen( $answer ) < 30 ) {
					continue;
				}

				$question = $this->heading_to_question( $heading );
				if ( $question ) {
					$faqs[] = [
						'question' => $question,
						'answer'   => $answer,
						'source'   => 'implicit',
						'editable' => true,
					];
				}
			}
		}

		return $faqs;
	}

	/**
	 * Transform an informational heading into a question.
	 *
	 * @param string $heading The heading text.
	 * @return string The generated question, or empty.
	 */
	public function heading_to_question( string $heading ): string {
		$lower = strtolower( $heading );

		// "Benefits of X" → "What are the benefits of X?"
		if ( preg_match( '/^benefits?\s+of\s+/i', $heading ) ) {
			return 'What are the ' . strtolower( $heading ) . '?';
		}

		// "How to X" → "How to X?" (already a question form)
		if ( preg_match( '/^how\s+to\s+/i', $heading ) ) {
			return $heading . '?';
		}

		// "Why X" → "Why X?"
		if ( preg_match( '/^why\s+/i', $heading ) ) {
			return $heading . '?';
		}

		// "Types of X" → "What are the types of X?"
		if ( preg_match( '/^types?\s+of\s+/i', $heading ) ) {
			return 'What are the ' . strtolower( $heading ) . '?';
		}

		// "X vs Y" → "What is the difference between X and Y?"
		if ( preg_match( '/^(.+?)\s+(?:vs\.?|versus)\s+(.+)$/i', $heading, $m ) ) {
			return 'What is the difference between ' . trim( $m[1] ) . ' and ' . trim( $m[2] ) . '?';
		}

		// Generic: "Topic Name" → "What is Topic Name?"
		if ( str_word_count( $heading ) <= 5 && ! preg_match( '/^(introduction|conclusion|summary|overview|table of contents)/i', $lower ) ) {
			return 'What is ' . $heading . '?';
		}

		return '';
	}

	/**
	 * Enhance FAQ suggestions using an AI API (optional).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $api_key User-provided API key.
	 * @return array Enhanced FAQ suggestions.
	 */
	public function enhance_with_ai( int $post_id, string $api_key ): array {
		$post = get_post( $post_id );
		if ( ! $post || ! $api_key ) {
			return [];
		}

		$content = wp_strip_all_tags( $post->post_content );
		$title   = $post->post_title;

		// Build prompt.
		$prompt = sprintf(
			"Based on this article titled \"%s\", generate 5 frequently asked questions with concise answers.\n\nContent:\n%s\n\nReturn JSON array: [{\"question\": \"...\", \"answer\": \"...\"}]",
			$title,
			wp_trim_words( $content, 500, '...' )
		);

		$response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
			'headers' => [
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			],
			'body'    => wp_json_encode( [
				'model'       => 'gpt-3.5-turbo',
				'messages'    => [
					[ 'role' => 'user', 'content' => $prompt ],
				],
				'temperature' => 0.7,
				'max_tokens'  => 1000,
			] ),
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			return [];
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( empty( $data['choices'][0]['message']['content'] ) ) {
			return [];
		}

		$ai_content = $data['choices'][0]['message']['content'];
		$faqs       = json_decode( $ai_content, true );

		if ( ! is_array( $faqs ) ) {
			return [];
		}

		$result = [];
		foreach ( $faqs as $faq ) {
			if ( ! empty( $faq['question'] ) && ! empty( $faq['answer'] ) ) {
				$result[] = [
					'question' => sanitize_text_field( $faq['question'] ),
					'answer'   => sanitize_text_field( $faq['answer'] ),
					'source'   => 'ai',
					'editable' => true,
				];
			}
		}

		return $result;
	}

	/**
	 * AJAX handler: generate FAQ suggestions.
	 */
	public function ajax_generate(): void {
		check_ajax_referer( 'si_faq_nonce', 'nonce' );

		$post_id = absint( $_POST['post_id'] ?? 0 );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => 'Invalid post.' ] );
			return;
		}

		$faqs = $this->generate( $post_id );

		// Optionally enhance with AI if key is provided.
		$api_key = $this->settings['faq_ai_api_key'] ?? '';
		if ( $api_key && ! empty( $_POST['use_ai'] ) ) {
			$ai_faqs = $this->enhance_with_ai( $post_id, $api_key );
			$faqs    = array_merge( $faqs, $ai_faqs );
			$faqs    = $this->deduplicate( $faqs );
		}

		wp_send_json_success( [ 'faqs' => $faqs ] );
	}

	/**
	 * AJAX handler: save edited FAQs to post meta.
	 */
	public function ajax_save(): void {
		check_ajax_referer( 'si_faq_nonce', 'nonce' );

		$post_id = absint( $_POST['post_id'] ?? 0 );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => 'Invalid post.' ] );
			return;
		}

		$faqs = $_POST['faqs'] ?? [];
		if ( ! is_array( $faqs ) ) {
			wp_send_json_error( [ 'message' => 'Invalid FAQ data.' ] );
			return;
		}

		$sanitized = [];
		foreach ( $faqs as $faq ) {
			if ( ! empty( $faq['question'] ) && ! empty( $faq['answer'] ) ) {
				$sanitized[] = [
					'question' => sanitize_text_field( $faq['question'] ),
					'answer'   => wp_kses_post( $faq['answer'] ),
				];
			}
		}

		update_post_meta( $post_id, '_search_intel_faqs', $sanitized );

		wp_send_json_success( [ 'message' => 'FAQs saved.', 'count' => count( $sanitized ) ] );
	}

	/**
	 * Register the FAQ generator metabox.
	 */
	public function register_metabox(): void {
		$post_types = $this->settings['enabled_post_types'] ?? [ 'post', 'page' ];
		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'si-faq-generator',
				__( 'FAQ Generator', 'search-intel' ),
				[ $this, 'render_metabox' ],
				$post_type,
				'normal',
				'default'
			);
		}
	}

	/**
	 * Render the FAQ generator metabox.
	 *
	 * @param object $post Current post object.
	 */
	public function render_metabox( object $post ): void {
		$saved_faqs = get_post_meta( $post->ID, '_search_intel_faqs', true );
		$has_ai_key = ! empty( $this->settings['faq_ai_api_key'] );

		wp_nonce_field( 'si_faq_nonce', 'si_faq_nonce' );
		?>
		<div id="si-faq-generator">
			<p>
				<button type="button" class="button button-primary" id="si-generate-faqs">
					<?php esc_html_e( 'Generate FAQs', 'search-intel' ); ?>
				</button>
				<?php if ( $has_ai_key ) : ?>
					<label style="margin-left:12px;">
						<input type="checkbox" id="si-use-ai" value="1" />
						<?php esc_html_e( 'Enhance with AI', 'search-intel' ); ?>
					</label>
				<?php endif; ?>
			</p>

			<div id="si-faq-list">
				<?php if ( ! empty( $saved_faqs ) && is_array( $saved_faqs ) ) : ?>
					<?php foreach ( $saved_faqs as $i => $faq ) : ?>
						<div class="si-faq-item" style="margin-bottom:12px; padding:10px; background:#f9f9f9; border:1px solid #ddd; border-radius:4px;">
							<label><strong><?php esc_html_e( 'Q:', 'search-intel' ); ?></strong></label>
							<input type="text" name="si_faqs[<?php echo (int) $i; ?>][question]" value="<?php echo esc_attr( $faq['question'] ?? '' ); ?>" style="width:100%; margin:4px 0;" />
							<label><strong><?php esc_html_e( 'A:', 'search-intel' ); ?></strong></label>
							<textarea name="si_faqs[<?php echo (int) $i; ?>][answer]" rows="3" style="width:100%;"><?php echo esc_textarea( $faq['answer'] ?? '' ); ?></textarea>
						</div>
					<?php endforeach; ?>
				<?php else : ?>
					<p class="description"><?php esc_html_e( 'Click "Generate FAQs" to extract Q&A from your content.', 'search-intel' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Extract the first meaningful paragraph as answer text from HTML.
	 *
	 * @param string $html HTML content following a heading.
	 * @return string Plain text answer.
	 */
	private function extract_answer( string $html ): string {
		// Collect all paragraphs up to the first 2.
		$texts = [];
		if ( preg_match_all( '/<p[^>]*>(.*?)<\/p>/is', $html, $matches ) ) {
			foreach ( $matches[1] as $p ) {
				$text = wp_strip_all_tags( trim( $p ) );
				if ( strlen( $text ) > 10 ) {
					$texts[] = $text;
					if ( count( $texts ) >= 2 ) {
						break;
					}
				}
			}
		}

		if ( ! empty( $texts ) ) {
			return implode( ' ', $texts );
		}

		// Fallback.
		$text = wp_strip_all_tags( trim( $html ) );
		return strlen( $text ) > 500 ? substr( $text, 0, 500 ) : $text;
	}

	/**
	 * Deduplicate FAQ entries by question similarity.
	 *
	 * @param array $faqs List of FAQ items.
	 * @return array Deduplicated list.
	 */
	private function deduplicate( array $faqs ): array {
		$seen   = [];
		$unique = [];

		foreach ( $faqs as $faq ) {
			$key = strtolower( preg_replace( '/\W+/', '', $faq['question'] ?? '' ) );
			if ( ! isset( $seen[ $key ] ) ) {
				$seen[ $key ] = true;
				$unique[]     = $faq;
			}
		}

		return $unique;
	}
}
