<?php
/**
 * AI Optimizer — score-driven content improvement suggestions.
 *
 * After a post is analyzed, this module inspects failing checks across
 * SEO / AEO / GEO / Readability and uses the AI Engine to generate
 * actionable fixes the author can preview and apply with one click.
 *
 * @package Search_Intel
 * @since   3.4.0
 */

namespace Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_Optimizer {

	/** @var array Plugin settings. */
	private $settings;

	/** @var Core Plugin core instance. */
	private $core;

	/** @var string Post meta key for cached suggestions. */
	private const SUGGESTIONS_META_KEY = '_search_intel_ai_suggestions';

	/** @var string Post meta key for failing checks snapshot. */
	private const FAILING_CHECKS_META_KEY = '_search_intel_failing_checks';

	/**
	 * Constructor.
	 *
	 * @param array $settings Plugin settings.
	 * @param Core  $core     Plugin core instance.
	 */
	public function __construct( array $settings, Core $core ) {
		$this->settings = $settings;
		$this->core     = $core;

		if ( empty( $this->settings['enable_ai_optimizer'] ?? true ) ) {
			return;
		}

		// Capture failing checks after every analysis.
		add_action( 'search_intel_post_analyzed', [ $this, 'capture_failing_checks' ], 20, 4 );

		if ( is_admin() ) {
			add_action( 'add_meta_boxes', [ $this, 'register_meta_box' ] );
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		}

		// AJAX endpoints.
		add_action( 'wp_ajax_si_ai_get_suggestions', [ $this, 'ajax_get_suggestions' ] );
		add_action( 'wp_ajax_si_ai_apply_suggestion', [ $this, 'ajax_apply_suggestion' ] );
	}

	/*----------------------------------------------------------------------
	 * Failing checks capture
	 *--------------------------------------------------------------------*/

	/**
	 * Store failing checks from the latest analysis for later AI processing.
	 *
	 * @param int   $post_id    Post ID.
	 * @param array $new_scores New scores.
	 * @param array $old_scores Previous scores.
	 * @param array $results    Full analysis results.
	 */
	public function capture_failing_checks( int $post_id, array $new_scores, array $old_scores, array $results ): void {
		$failing = [];

		$modules = [
			'seo' => $results['seo_score']['checks'] ?? [],
			'aeo' => $results['aeo_score']['checks'] ?? [],
			'geo' => $results['geo_score']['checks'] ?? [],
		];

		foreach ( $modules as $module => $checks ) {
			foreach ( $checks as $check ) {
				if ( empty( $check['pass'] ) ) {
					$failing[] = [
						'module' => $module,
						'label'  => $check['label'] ?? '',
					];
				}
			}
		}

		// Readability issues.
		$readability = $results['readability'] ?? [];
		if ( ! empty( $readability['score'] ) && ! ( $readability['meets_target'] ?? true ) ) {
			$failing[] = [
				'module' => 'readability',
				'label'  => sprintf(
					'Readability score %.1f is below target %d — %s',
					$readability['score'],
					$readability['target'] ?? 60,
					$readability['label'] ?? ''
				),
			];
		}

		update_post_meta( $post_id, self::FAILING_CHECKS_META_KEY, $failing );

		// Invalidate cached suggestions since the content changed.
		delete_post_meta( $post_id, self::SUGGESTIONS_META_KEY );
	}

	/*----------------------------------------------------------------------
	 * Suggestion generation
	 *--------------------------------------------------------------------*/

	/**
	 * Get AI-generated improvement suggestions for a post.
	 *
	 * Returns cached suggestions if available, otherwise generates fresh ones.
	 *
	 * @param int  $post_id Post ID.
	 * @param bool $force   Force regeneration of suggestions.
	 * @return array Array of suggestion objects.
	 */
	public function get_suggestions( int $post_id, bool $force = false ): array {
		if ( ! $force ) {
			$cached = get_post_meta( $post_id, self::SUGGESTIONS_META_KEY, true );
			if ( is_array( $cached ) && ! empty( $cached ) ) {
				return $cached;
			}
		}

		$failing = get_post_meta( $post_id, self::FAILING_CHECKS_META_KEY, true );
		if ( ! is_array( $failing ) || empty( $failing ) ) {
			return [];
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return [];
		}

		$suggestions = $this->generate_suggestions( $post, $failing );

		if ( ! empty( $suggestions ) ) {
			update_post_meta( $post_id, self::SUGGESTIONS_META_KEY, $suggestions );
		}

		return $suggestions;
	}

	/**
	 * Generate AI suggestions from failing checks.
	 *
	 * @param \WP_Post $post    Post object.
	 * @param array    $failing Array of failing check items.
	 * @return array Suggestions.
	 */
	private function generate_suggestions( \WP_Post $post, array $failing ): array {
		$ai = $this->core->ai_engine;
		if ( ! $ai->is_configured() ) {
			return [ $this->make_suggestion( 'config', 'AI provider not configured', 'Configure an AI provider in Search Intel → Settings to enable AI suggestions.', 'info' ) ];
		}

		$content     = wp_strip_all_tags( $post->post_content );
		$title       = $post->post_title;
		$keyword     = get_post_meta( $post->ID, '_search_intel_focus_keyword', true ) ?: '';
		$description = get_post_meta( $post->ID, '_search_intel_description', true ) ?: '';
		$excerpt     = mb_substr( $content, 0, 2000 );

		$suggestions = [];

		// Group failing checks by module.
		$by_module = [];
		foreach ( $failing as $f ) {
			$by_module[ $f['module'] ][] = $f['label'];
		}

		// --- SEO suggestions ---
		if ( ! empty( $by_module['seo'] ) ) {
			$suggestions = array_merge( $suggestions, $this->generate_seo_suggestions( $ai, $post, $excerpt, $title, $keyword, $description, $by_module['seo'] ) );
		}

		// --- AEO suggestions ---
		if ( ! empty( $by_module['aeo'] ) ) {
			$suggestions = array_merge( $suggestions, $this->generate_aeo_suggestions( $ai, $post, $excerpt, $title, $by_module['aeo'] ) );
		}

		// --- GEO suggestions ---
		if ( ! empty( $by_module['geo'] ) ) {
			$suggestions = array_merge( $suggestions, $this->generate_geo_suggestions( $ai, $post, $excerpt, $title, $keyword, $by_module['geo'] ) );
		}

		// --- Readability suggestions ---
		if ( ! empty( $by_module['readability'] ) ) {
			$suggestions = array_merge( $suggestions, $this->generate_readability_suggestions( $ai, $post, $excerpt, $by_module['readability'] ) );
		}

		return $suggestions;
	}

	/**
	 * Generate SEO-focused AI suggestions.
	 */
	private function generate_seo_suggestions( AI_Engine $ai, \WP_Post $post, string $excerpt, string $title, string $keyword, string $description, array $issues ): array {
		$suggestions = [];
		$labels      = implode( "\n", $issues );

		// Missing or bad meta description.
		if ( $this->issues_contain( $issues, 'meta description' ) ) {
			if ( $keyword ) {
				$result = $ai->generate_meta_description( $excerpt, $keyword, $title );
				if ( $result['success'] ) {
					$suggestions[] = $this->make_suggestion(
						'meta_description',
						'AI-generated meta description',
						$result['text'],
						'apply',
						[ 'meta_key' => '_search_intel_description', 'value' => $result['text'] ]
					);
				}
			} else {
				$suggestions[] = $this->make_suggestion( 'meta_description', 'Meta description needs a focus keyword', 'Set a focus keyword first, then regenerate suggestions.', 'info' );
			}
		}

		// Missing or bad title.
		if ( $this->issues_contain( $issues, 'title' ) && $keyword ) {
			$result = $ai->generate_title_suggestions( $excerpt, $keyword, $title );
			if ( $result['success'] ) {
				$suggestions[] = $this->make_suggestion(
					'title_suggestions',
					'SEO title alternatives',
					$result['text'],
					'review'
				);
			}
		}

		// Missing focus keyword.
		if ( $this->issues_contain( $issues, 'focus keyword' ) && empty( $keyword ) ) {
			$result = $ai->chat(
				'You are an SEO expert. Analyze the content and suggest the single best focus keyword or keyphrase (2-4 words). Return ONLY the keyword, nothing else.',
				"Title: {$title}\n\nContent:\n{$excerpt}",
				[ 'max_tokens' => 30, 'temperature' => 0.3 ]
			);
			if ( $result['success'] ) {
				$kw = trim( $result['text'], '"\'.' );
				$suggestions[] = $this->make_suggestion(
					'focus_keyword',
					'Suggested focus keyword',
					$kw,
					'apply',
					[ 'meta_key' => '_search_intel_focus_keyword', 'value' => $kw ]
				);
			}
		}

		// Thin content.
		if ( $this->issues_contain( $issues, 'words' ) || $this->issues_contain( $issues, 'content' ) ) {
			$result = $ai->chat(
				'You are a content strategist. The following article is too short. Suggest 3-5 specific sections '
				. '(with H2 headings) that should be added to make it comprehensive. Format as a numbered list '
				. 'with the heading and a one-sentence description of what to cover.',
				"Title: {$title}\nKeyword: {$keyword}\n\nCurrent content:\n{$excerpt}",
				[ 'max_tokens' => 400, 'temperature' => 0.5 ]
			);
			if ( $result['success'] ) {
				$suggestions[] = $this->make_suggestion(
					'content_expansion',
					'Content sections to add',
					$result['text'],
					'review'
				);
			}
		}

		// Missing internal links.
		if ( $this->issues_contain( $issues, 'internal link' ) ) {
			$suggestions[] = $this->make_suggestion(
				'internal_links',
				'Add internal links',
				'Link to at least 2 related posts. Check the Internal Linker module for automated suggestions.',
				'info'
			);
		}

		// Missing images / alt text.
		if ( $this->issues_contain( $issues, 'image' ) || $this->issues_contain( $issues, 'alt text' ) ) {
			$suggestions[] = $this->make_suggestion(
				'images',
				'Add images with descriptive alt text',
				'Include at least one relevant image with an alt attribute containing your focus keyword.',
				'info'
			);
		}

		return $suggestions;
	}

	/**
	 * Generate AEO-focused AI suggestions.
	 */
	private function generate_aeo_suggestions( AI_Engine $ai, \WP_Post $post, string $excerpt, string $title, array $issues ): array {
		$suggestions = [];

		// Missing FAQ schema.
		if ( $this->issues_contain( $issues, 'faq' ) ) {
			$result = $ai->chat(
				'You are an SEO expert specializing in featured snippets and answer engines. '
				. 'Generate 3-5 FAQ items (question and answer pairs) relevant to this article. '
				. 'Format as JSON array: [{"question":"...","answer":"..."}]. Return ONLY valid JSON.',
				"Title: {$title}\n\nContent:\n{$excerpt}",
				[ 'max_tokens' => 600, 'temperature' => 0.4 ]
			);
			if ( $result['success'] ) {
				$suggestions[] = $this->make_suggestion(
					'faq_schema',
					'AI-generated FAQ schema',
					$result['text'],
					'apply',
					[ 'meta_key' => '_search_intel_faqs', 'value' => $result['text'], 'type' => 'json' ]
				);
			}
		}

		// Missing question-style headings.
		if ( $this->issues_contain( $issues, 'question' ) || $this->issues_contain( $issues, 'heading' ) ) {
			$result = $ai->chat(
				'You are an AEO expert. Suggest 3-5 question-style H2 headings that would make this content '
				. 'more likely to appear in featured snippets and AI answer engines. Return a numbered list.',
				"Title: {$title}\n\nContent:\n{$excerpt}",
				[ 'max_tokens' => 250, 'temperature' => 0.5 ]
			);
			if ( $result['success'] ) {
				$suggestions[] = $this->make_suggestion(
					'question_headings',
					'Question-style headings to add',
					$result['text'],
					'review'
				);
			}
		}

		// Missing structured lists / tables.
		if ( $this->issues_contain( $issues, 'list' ) || $this->issues_contain( $issues, 'table' ) ) {
			$suggestions[] = $this->make_suggestion(
				'structured_content',
				'Add structured content',
				'Include ordered/unordered lists and comparison tables to improve scannability and snippet eligibility.',
				'info'
			);
		}

		// First paragraph not providing a direct answer.
		if ( $this->issues_contain( $issues, 'first paragraph' ) || $this->issues_contain( $issues, 'direct answer' ) ) {
			$result = $ai->chat(
				'You are an AEO expert. Write a concise opening paragraph (2-3 sentences, 50-150 words) '
				. 'that directly answers the main question this article addresses. The paragraph should work '
				. 'as a standalone featured snippet. Return ONLY the paragraph text.',
				"Title: {$title}\n\nContent:\n{$excerpt}",
				[ 'max_tokens' => 200, 'temperature' => 0.4 ]
			);
			if ( $result['success'] ) {
				$suggestions[] = $this->make_suggestion(
					'direct_answer',
					'Opening paragraph rewrite',
					$result['text'],
					'review'
				);
			}
		}

		return $suggestions;
	}

	/**
	 * Generate GEO-focused AI suggestions.
	 */
	private function generate_geo_suggestions( AI_Engine $ai, \WP_Post $post, string $excerpt, string $title, string $keyword, array $issues ): array {
		$suggestions = [];

		// Missing AI summary.
		if ( $this->issues_contain( $issues, 'ai summary' ) || $this->issues_contain( $issues, 'llm' ) ) {
			$result = $ai->chat(
				'You are a GEO (Generative Engine Optimization) expert. Write a concise AI summary (3-5 sentences) '
				. 'that would help LLMs like ChatGPT understand and cite this content accurately. Include key facts, '
				. 'definitions, and the main takeaway. Return ONLY the summary text.',
				"Title: {$title}\nKeyword: {$keyword}\n\nContent:\n{$excerpt}",
				[ 'max_tokens' => 250, 'temperature' => 0.3 ]
			);
			if ( $result['success'] ) {
				$suggestions[] = $this->make_suggestion(
					'ai_summary',
					'AI summary for LLM indexing',
					$result['text'],
					'apply',
					[ 'meta_key' => '_search_intel_ai_summary', 'value' => $result['text'] ]
				);
			}
		}

		// Missing source citations.
		if ( $this->issues_contain( $issues, 'citation' ) || $this->issues_contain( $issues, 'source' ) ) {
			$result = $ai->chat(
				'You are a content editor. Suggest 3-5 types of authoritative sources that should be cited '
				. 'in this article to improve E-E-A-T and AI credibility. For each, give the type of source '
				. 'and an example of how to phrase the citation in the content. Format as a numbered list.',
				"Title: {$title}\n\nContent:\n{$excerpt}",
				[ 'max_tokens' => 300, 'temperature' => 0.4 ]
			);
			if ( $result['success'] ) {
				$suggestions[] = $this->make_suggestion(
					'source_citations',
					'Source citations to add',
					$result['text'],
					'review'
				);
			}
		}

		// Missing definitions.
		if ( $this->issues_contain( $issues, 'definition' ) ) {
			$result = $ai->chat(
				'You are a content editor. Identify 3-5 key terms in this content that should have clear '
				. 'definitions added. For each term, write a brief definition sentence using the pattern '
				. '"[Term] is defined as..." or "[Term] refers to...". Format as a numbered list.',
				"Title: {$title}\n\nContent:\n{$excerpt}",
				[ 'max_tokens' => 300, 'temperature' => 0.3 ]
			);
			if ( $result['success'] ) {
				$suggestions[] = $this->make_suggestion(
					'definitions',
					'Key term definitions to add',
					$result['text'],
					'review'
				);
			}
		}

		// Missing entities.
		if ( $this->issues_contain( $issues, 'entit' ) ) {
			$result = $ai->chat(
				'You are a semantic SEO expert. Extract the key entities (people, organizations, places, concepts) '
				. 'mentioned or implied in this content. Return as JSON array of objects: '
				. '[{"name":"...","type":"Person|Organization|Place|Concept","description":"one sentence"}]. '
				. 'Return ONLY valid JSON, max 10 entities.',
				"Title: {$title}\n\nContent:\n{$excerpt}",
				[ 'max_tokens' => 500, 'temperature' => 0.2 ]
			);
			if ( $result['success'] ) {
				$suggestions[] = $this->make_suggestion(
					'entities',
					'Entities to define for semantic clarity',
					$result['text'],
					'apply',
					[ 'meta_key' => '_search_intel_entities', 'value' => $result['text'], 'type' => 'json' ]
				);
			}
		}

		// Content freshness.
		if ( $this->issues_contain( $issues, 'freshness' ) || $this->issues_contain( $issues, 'update' ) ) {
			$suggestions[] = $this->make_suggestion(
				'freshness',
				'Content freshness needed',
				'This content has not been updated in over 90 days. Review and update facts, statistics, and references to maintain freshness signals.',
				'info'
			);
		}

		return $suggestions;
	}

	/**
	 * Generate readability AI suggestions.
	 */
	private function generate_readability_suggestions( AI_Engine $ai, \WP_Post $post, string $excerpt, array $issues ): array {
		$result = $ai->chat(
			'You are an editor focused on readability. Analyze this content and provide 3-5 specific, '
			. 'actionable suggestions to improve readability. Focus on: sentence length, passive voice, '
			. 'complex words, paragraph structure, and transition words. Format as a numbered list.',
			"Content:\n{$excerpt}",
			[ 'max_tokens' => 350, 'temperature' => 0.4 ]
		);

		if ( ! $result['success'] ) {
			return [];
		}

		return [
			$this->make_suggestion(
				'readability',
				'Readability improvements',
				$result['text'],
				'review'
			),
		];
	}

	/*----------------------------------------------------------------------
	 * Suggestion helpers
	 *--------------------------------------------------------------------*/

	/**
	 * Build a suggestion array.
	 *
	 * @param string $id     Unique suggestion ID.
	 * @param string $title  Short title.
	 * @param string $text   Suggestion content.
	 * @param string $action Action type: 'apply', 'review', or 'info'.
	 * @param array  $apply  Optional apply data (meta_key, value, type).
	 * @return array
	 */
	private function make_suggestion( string $id, string $title, string $text, string $action = 'info', array $apply = [] ): array {
		return [
			'id'         => $id,
			'title'      => $title,
			'text'       => $text,
			'action'     => $action,
			'apply_data' => $apply,
			'generated'  => gmdate( 'Y-m-d H:i:s' ),
		];
	}

	/**
	 * Check if any issue label contains a keyword.
	 *
	 * @param array  $issues  Array of issue label strings.
	 * @param string $keyword Keyword to search for (case-insensitive).
	 * @return bool
	 */
	private function issues_contain( array $issues, string $keyword ): bool {
		foreach ( $issues as $label ) {
			if ( stripos( $label, $keyword ) !== false ) {
				return true;
			}
		}
		return false;
	}

	/*----------------------------------------------------------------------
	 * AJAX endpoints
	 *--------------------------------------------------------------------*/

	/**
	 * AJAX: Get AI suggestions for a post.
	 */
	public function ajax_get_suggestions(): void {
		check_ajax_referer( 'search_intel_nonce', 'nonce' );

		$post_id = absint( $_POST['post_id'] ?? 0 );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
		}

		$force       = ! empty( $_POST['force'] );
		$suggestions = $this->get_suggestions( $post_id, $force );

		wp_send_json_success( [
			'suggestions' => $suggestions,
			'post_id'     => $post_id,
			'scores'      => [
				'seo'         => (float) get_post_meta( $post_id, '_search_intel_seo_score', true ),
				'aeo'         => (float) get_post_meta( $post_id, '_search_intel_aeo_score', true ),
				'geo'         => (float) get_post_meta( $post_id, '_search_intel_geo_score', true ),
				'readability' => (float) get_post_meta( $post_id, '_search_intel_readability_score', true ),
			],
		] );
	}

	/**
	 * AJAX: Apply a specific AI suggestion.
	 */
	public function ajax_apply_suggestion(): void {
		check_ajax_referer( 'search_intel_nonce', 'nonce' );

		$post_id       = absint( $_POST['post_id'] ?? 0 );
		$suggestion_id = sanitize_text_field( $_POST['suggestion_id'] ?? '' );

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
		}

		$suggestions = get_post_meta( $post_id, self::SUGGESTIONS_META_KEY, true );
		if ( ! is_array( $suggestions ) ) {
			wp_send_json_error( [ 'message' => 'No suggestions found. Generate suggestions first.' ] );
		}

		$target = null;
		foreach ( $suggestions as $s ) {
			if ( ( $s['id'] ?? '' ) === $suggestion_id ) {
				$target = $s;
				break;
			}
		}

		if ( ! $target || $target['action'] !== 'apply' || empty( $target['apply_data'] ) ) {
			wp_send_json_error( [ 'message' => 'Suggestion not found or not auto-applicable.' ] );
		}

		$meta_key = $target['apply_data']['meta_key'] ?? '';
		$value    = $target['apply_data']['value'] ?? '';
		$type     = $target['apply_data']['type'] ?? 'string';

		if ( empty( $meta_key ) ) {
			wp_send_json_error( [ 'message' => 'Invalid suggestion data.' ] );
		}

		// Decode JSON values if applicable.
		if ( $type === 'json' ) {
			$decoded = json_decode( $value, true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				$value = $decoded;
			}
		}

		update_post_meta( $post_id, $meta_key, $value );

		// Mark suggestion as applied in cache.
		$updated = array_map( function ( $s ) use ( $suggestion_id ) {
			if ( ( $s['id'] ?? '' ) === $suggestion_id ) {
				$s['action']  = 'applied';
				$s['applied'] = gmdate( 'Y-m-d H:i:s' );
			}
			return $s;
		}, $suggestions );
		update_post_meta( $post_id, self::SUGGESTIONS_META_KEY, $updated );

		wp_send_json_success( [
			'message'       => 'Suggestion applied successfully.',
			'suggestion_id' => $suggestion_id,
			'meta_key'      => $meta_key,
		] );
	}

	/*----------------------------------------------------------------------
	 * Admin UI
	 *--------------------------------------------------------------------*/

	/**
	 * Register the AI Optimizer meta box.
	 */
	public function register_meta_box(): void {
		$post_types = $this->settings['enabled_post_types'] ?? [ 'post', 'page' ];
		foreach ( $post_types as $pt ) {
			add_meta_box(
				'search-intel-ai-optimizer',
				__( 'Search Intel — AI Optimizer', 'search-intel' ),
				[ $this, 'render_meta_box' ],
				$pt,
				'side',
				'default'
			);
		}
	}

	/**
	 * Render the AI Optimizer meta box.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render_meta_box( \WP_Post $post ): void {
		$scores = [
			'seo'         => (float) get_post_meta( $post->ID, '_search_intel_seo_score', true ),
			'aeo'         => (float) get_post_meta( $post->ID, '_search_intel_aeo_score', true ),
			'geo'         => (float) get_post_meta( $post->ID, '_search_intel_geo_score', true ),
			'readability' => (float) get_post_meta( $post->ID, '_search_intel_readability_score', true ),
		];

		$failing = get_post_meta( $post->ID, self::FAILING_CHECKS_META_KEY, true );
		$fail_count = is_array( $failing ) ? count( $failing ) : 0;

		$cached = get_post_meta( $post->ID, self::SUGGESTIONS_META_KEY, true );
		$has_cached = is_array( $cached ) && ! empty( $cached );
		?>
		<div id="si-ai-optimizer" data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>">
			<div class="si-ai-scores" style="margin-bottom:12px;">
				<span class="si-score-badge" style="background:<?php echo $this->score_color( $scores['seo'] ); ?>">SEO <?php echo esc_html( (string) $scores['seo'] ); ?></span>
				<span class="si-score-badge" style="background:<?php echo $this->score_color( $scores['aeo'] ); ?>">AEO <?php echo esc_html( (string) $scores['aeo'] ); ?></span>
				<span class="si-score-badge" style="background:<?php echo $this->score_color( $scores['geo'] ); ?>">GEO <?php echo esc_html( (string) $scores['geo'] ); ?></span>
				<span class="si-score-badge" style="background:<?php echo $this->score_color( $scores['readability'] ); ?>">READ <?php echo esc_html( (string) $scores['readability'] ); ?></span>
			</div>

			<?php if ( $fail_count > 0 ) : ?>
				<p style="color:#d63638;margin:0 0 8px;">
					<strong><?php echo esc_html( (string) $fail_count ); ?></strong> <?php esc_html_e( 'issues found', 'search-intel' ); ?>
				</p>
			<?php else : ?>
				<p style="color:#00a32a;margin:0 0 8px;"><?php esc_html_e( 'All checks passing!', 'search-intel' ); ?></p>
			<?php endif; ?>

			<button type="button" class="button button-primary" id="si-ai-generate-btn" <?php echo $fail_count === 0 ? 'disabled' : ''; ?>>
				<?php echo $has_cached ? esc_html__( 'Regenerate AI Suggestions', 'search-intel' ) : esc_html__( 'Generate AI Suggestions', 'search-intel' ); ?>
			</button>

			<div id="si-ai-suggestions-list" style="margin-top:12px;">
				<?php if ( $has_cached ) : ?>
					<?php $this->render_suggestions( $cached ); ?>
				<?php endif; ?>
			</div>

			<div id="si-ai-loading" style="display:none;margin-top:10px;">
				<span class="spinner is-active" style="float:none;margin:0 5px 0 0;"></span>
				<?php esc_html_e( 'Generating AI suggestions…', 'search-intel' ); ?>
			</div>
		</div>

		<style>
			.si-score-badge { display:inline-block; padding:2px 8px; border-radius:3px; color:#fff; font-size:11px; font-weight:600; margin:0 2px 4px 0; }
			.si-suggestion { background:#f0f6fc; border-left:3px solid #2271b1; padding:8px 10px; margin-bottom:8px; border-radius:2px; }
			.si-suggestion.si-suggestion--applied { border-left-color:#00a32a; background:#f0f6e8; }
			.si-suggestion h4 { margin:0 0 4px; font-size:12px; }
			.si-suggestion p { margin:0 0 6px; font-size:12px; white-space:pre-wrap; }
			.si-suggestion .button { font-size:11px; }
		</style>
		<?php
	}

	/**
	 * Render suggestion items HTML.
	 *
	 * @param array $suggestions Array of suggestion objects.
	 */
	private function render_suggestions( array $suggestions ): void {
		if ( empty( $suggestions ) ) {
			echo '<p>' . esc_html__( 'No suggestions at this time.', 'search-intel' ) . '</p>';
			return;
		}

		foreach ( $suggestions as $s ) {
			$applied = ( $s['action'] ?? '' ) === 'applied';
			$class   = $applied ? 'si-suggestion si-suggestion--applied' : 'si-suggestion';
			?>
			<div class="<?php echo esc_attr( $class ); ?>" data-suggestion-id="<?php echo esc_attr( $s['id'] ?? '' ); ?>">
				<h4>
					<?php echo esc_html( $s['title'] ?? '' ); ?>
					<?php if ( $applied ) : ?>
						<span style="color:#00a32a;">✓ Applied</span>
					<?php endif; ?>
				</h4>
				<p><?php echo esc_html( $s['text'] ?? '' ); ?></p>
				<?php if ( ( $s['action'] ?? '' ) === 'apply' && ! empty( $s['apply_data'] ) ) : ?>
					<button type="button" class="button si-ai-apply-btn" data-suggestion-id="<?php echo esc_attr( $s['id'] ?? '' ); ?>">
						<?php esc_html_e( 'Apply', 'search-intel' ); ?>
					</button>
				<?php elseif ( ( $s['action'] ?? '' ) === 'review' ) : ?>
					<em style="font-size:11px;color:#646970;"><?php esc_html_e( 'Review and apply manually', 'search-intel' ); ?></em>
				<?php endif; ?>
			</div>
			<?php
		}
	}

	/**
	 * Get score color based on value.
	 *
	 * @param float $score Score value 0-100.
	 * @return string Hex color.
	 */
	private function score_color( float $score ): string {
		if ( $score >= 70 ) {
			return '#00a32a';
		}
		if ( $score >= 40 ) {
			return '#dba617';
		}
		return '#d63638';
	}

	/**
	 * Enqueue meta box assets.
	 *
	 * @param string $hook_suffix Admin page hook.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		wp_enqueue_script(
			'si-ai-optimizer',
			plugins_url( 'admin/js/ai-optimizer.js', dirname( __FILE__ ) ),
			[ 'jquery' ],
			defined( 'SEARCH_INTEL_VERSION' ) ? SEARCH_INTEL_VERSION : '3.4.0',
			true
		);

		wp_localize_script( 'si-ai-optimizer', 'siAiOptimizer', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'search_intel_nonce' ),
		] );
	}
}
