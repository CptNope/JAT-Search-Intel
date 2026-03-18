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

	/** @var string Post meta key for pre-optimization snapshot (rollback). */
	private const SNAPSHOT_META_KEY = '_search_intel_ai_snapshot';

	/** @var array Meta keys we snapshot and can restore. */
	private const TRACKED_META_KEYS = [
		'_search_intel_title',
		'_search_intel_description',
		'_search_intel_focus_keyword',
		'_search_intel_faqs',
		'_search_intel_ai_summary',
		'_search_intel_entities',
	];

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
		add_action( 'wp_ajax_si_ai_auto_optimize', [ $this, 'ajax_auto_optimize' ] );
		add_action( 'wp_ajax_si_ai_rollback', [ $this, 'ajax_rollback' ] );
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
	 * Auto-Optimize with rollback
	 *--------------------------------------------------------------------*/

	/**
	 * Create a full snapshot of the post state for rollback.
	 *
	 * @param int $post_id Post ID.
	 */
	private function create_snapshot( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$snapshot = [
			'ts'           => time(),
			'date'         => gmdate( 'Y-m-d H:i:s' ),
			'user_id'      => get_current_user_id(),
			'post_title'   => $post->post_title,
			'post_content' => $post->post_content,
			'meta'         => [],
			'scores'       => [
				'seo'         => (float) get_post_meta( $post_id, '_search_intel_seo_score', true ),
				'aeo'         => (float) get_post_meta( $post_id, '_search_intel_aeo_score', true ),
				'geo'         => (float) get_post_meta( $post_id, '_search_intel_geo_score', true ),
				'readability' => (float) get_post_meta( $post_id, '_search_intel_readability_score', true ),
			],
		];

		foreach ( self::TRACKED_META_KEYS as $key ) {
			$snapshot['meta'][ $key ] = get_post_meta( $post_id, $key, true );
		}

		// Stack snapshots (max 5 rollback points).
		$stack = get_post_meta( $post_id, self::SNAPSHOT_META_KEY, true );
		if ( ! is_array( $stack ) ) {
			$stack = [];
		}
		$stack[] = $snapshot;
		if ( count( $stack ) > 5 ) {
			$stack = array_slice( $stack, -5 );
		}
		update_post_meta( $post_id, self::SNAPSHOT_META_KEY, $stack );
	}

	/**
	 * AJAX: Auto-optimize post content using AI based on failing checks.
	 */
	public function ajax_auto_optimize(): void {
		check_ajax_referer( 'search_intel_nonce', 'nonce' );

		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( [ 'message' => 'Post not found.' ] );
		}

		$ai = $this->core->ai_engine ?? null;
		if ( ! $ai || ! $ai->is_configured() ) {
			wp_send_json_error( [ 'message' => 'AI provider not configured. Go to Settings → AI to set up.' ] );
		}

		$failing = get_post_meta( $post_id, self::FAILING_CHECKS_META_KEY, true );
		if ( ! is_array( $failing ) || empty( $failing ) ) {
			wp_send_json_error( [ 'message' => 'No failing checks to optimize. Run analysis first.' ] );
		}

		// Create rollback snapshot before making any changes.
		$this->create_snapshot( $post_id );

		$title       = $post->post_title;
		$content     = $post->post_content;
		$description = get_post_meta( $post_id, '_search_intel_description', true ) ?: '';
		$keyword     = get_post_meta( $post_id, '_search_intel_focus_keyword', true ) ?: '';

		// Build the issue list for AI.
		$issue_lines = [];
		foreach ( $failing as $check ) {
			$module = strtoupper( $check['module'] ?? 'SEO' );
			$label  = $check['label'] ?? '';
			$issue_lines[] = "[{$module}] {$label}";
		}
		$issues_text = implode( "\n", $issue_lines );

		$changes_made = [];

		// --- Phase 1: Content enhancement ---
		$content_result = $ai->chat(
			'You are an expert SEO content optimizer. You will receive a WordPress post and a list of '
			. 'failing checks. Rewrite and enhance the content to fix as many issues as possible. '
			. 'Rules: '
			. '1) Preserve the original meaning, voice, and key points. '
			. '2) Use the focus keyword naturally throughout. '
			. '3) Add internal link placeholders as [internal link: anchor text]. '
			. '4) Add image placeholders as [image: description]. '
			. '5) Add comparison/data tables where appropriate using HTML <table> markup. '
			. '6) Add source citations as [Source: description]. '
			. '7) Ensure content is 800+ words with good subheadings. '
			. '8) Make paragraphs concise and scannable. '
			. '9) Return ONLY the improved HTML content — no explanations, no markdown fences.',
			"Focus Keyword: {$keyword}\n"
			. "Current Title: {$title}\n"
			. "Current Meta Description: {$description}\n\n"
			. "FAILING CHECKS:\n{$issues_text}\n\n"
			. "CURRENT CONTENT:\n{$content}",
			[ 'max_tokens' => 4000, 'temperature' => 0.3 ]
		);

		if ( $content_result['success'] && ! empty( $content_result['text'] ) ) {
			$new_content = $content_result['text'];
			// Strip any markdown fences the AI might add.
			$new_content = preg_replace( '/^```(?:html)?\s*/i', '', $new_content );
			$new_content = preg_replace( '/\s*```\s*$/', '', $new_content );

			wp_update_post( [
				'ID'           => $post_id,
				'post_content' => wp_kses_post( $new_content ),
			] );
			$changes_made[] = 'Content enhanced and expanded';
		}

		// --- Phase 2: Title optimization (if failing) ---
		if ( $this->checks_contain( $failing, 'title' ) ) {
			$title_result = $ai->chat(
				'You are an SEO title specialist. Generate an optimized title that is 30-60 characters, '
				. 'includes the focus keyword, and is compelling. Return ONLY the title text, nothing else.',
				"Focus Keyword: {$keyword}\nCurrent Title: {$title}\nContent excerpt: "
				. wp_trim_words( $content, 100 ),
				[ 'max_tokens' => 60, 'temperature' => 0.5 ]
			);
			if ( $title_result['success'] && ! empty( $title_result['text'] ) ) {
				$new_title = sanitize_text_field( trim( $title_result['text'], "\" \n\r\t" ) );
				if ( strlen( $new_title ) >= 20 && strlen( $new_title ) <= 70 ) {
					wp_update_post( [ 'ID' => $post_id, 'post_title' => $new_title ] );
					$changes_made[] = "Title: \"{$title}\" → \"{$new_title}\"";
				}
			}
		}

		// --- Phase 3: Meta description (if failing) ---
		if ( $this->checks_contain( $failing, 'meta description' ) && $keyword ) {
			$meta_result = $ai->generate_meta_description(
				wp_trim_words( $content, 150 ),
				$keyword,
				$title
			);
			if ( $meta_result['success'] && ! empty( $meta_result['text'] ) ) {
				$new_desc = sanitize_text_field( trim( $meta_result['text'], "\" \n\r\t" ) );
				update_post_meta( $post_id, '_search_intel_description', $new_desc );
				$changes_made[] = 'Meta description generated';
			}
		}

		// --- Phase 4: FAQ schema (if failing) ---
		if ( $this->checks_contain( $failing, 'faq' ) ) {
			$faq_result = $ai->chat(
				'Generate 3-5 FAQ entries as a JSON array: [{"question":"...","answer":"..."}]. '
				. 'Base them on the content below. Return ONLY valid JSON, nothing else.',
				"Focus Keyword: {$keyword}\nContent: " . wp_trim_words( $content, 200 ),
				[ 'max_tokens' => 500, 'temperature' => 0.3 ]
			);
			if ( $faq_result['success'] && ! empty( $faq_result['text'] ) ) {
				$faq_text = trim( $faq_result['text'] );
				$faq_text = preg_replace( '/^```(?:json)?\s*/i', '', $faq_text );
				$faq_text = preg_replace( '/\s*```\s*$/', '', $faq_text );
				$faqs     = json_decode( $faq_text, true );
				if ( is_array( $faqs ) && ! empty( $faqs ) ) {
					update_post_meta( $post_id, '_search_intel_faqs', $faqs );
					$changes_made[] = count( $faqs ) . ' FAQ entries added';
				}
			}
		}

		// --- Phase 5: AI summary for GEO (if failing) ---
		if ( $this->checks_contain( $failing, 'ai summary' ) ) {
			$summary_result = $ai->chat(
				'Write a 2-3 sentence AI summary of this content suitable for LLM indexing. '
				. 'Be factual and concise. Return ONLY the summary text.',
				wp_trim_words( $content, 200 ),
				[ 'max_tokens' => 150, 'temperature' => 0.3 ]
			);
			if ( $summary_result['success'] && ! empty( $summary_result['text'] ) ) {
				update_post_meta( $post_id, '_search_intel_ai_summary', sanitize_textarea_field( $summary_result['text'] ) );
				$changes_made[] = 'AI summary generated';
			}
		}

		if ( empty( $changes_made ) ) {
			wp_send_json_error( [ 'message' => 'AI optimization could not apply any changes. Try again or check your AI provider.' ] );
		}

		// Re-run analysis to get new scores.
		$analyzer   = $this->core->content_analyzer ?? null;
		$new_scores = [];
		if ( $analyzer ) {
			$refreshed = get_post( $post_id );
			if ( $refreshed ) {
				$results    = $analyzer->analyze( $refreshed );
				$new_scores = [
					'seo'         => (float) ( $results['seo_score']['score'] ?? 0 ),
					'aeo'         => (float) ( $results['aeo_score']['score'] ?? 0 ),
					'geo'         => (float) ( $results['geo_score']['score'] ?? 0 ),
					'readability' => (float) ( $results['readability_score']['score'] ?? 0 ),
				];
				update_post_meta( $post_id, '_search_intel_seo_score', $new_scores['seo'] );
				update_post_meta( $post_id, '_search_intel_aeo_score', $new_scores['aeo'] );
				update_post_meta( $post_id, '_search_intel_geo_score', $new_scores['geo'] );
				update_post_meta( $post_id, '_search_intel_readability_score', $new_scores['readability'] );
			}
		}

		// Get snapshot scores for comparison.
		$stack    = get_post_meta( $post_id, self::SNAPSHOT_META_KEY, true );
		$snapshot = is_array( $stack ) ? end( $stack ) : [];
		$old_scores = $snapshot['scores'] ?? [];

		wp_send_json_success( [
			'message'      => count( $changes_made ) . ' optimization(s) applied.',
			'changes'      => $changes_made,
			'old_scores'   => $old_scores,
			'new_scores'   => $new_scores,
			'can_rollback' => true,
		] );
	}

	/**
	 * AJAX: Rollback to the previous snapshot.
	 */
	public function ajax_rollback(): void {
		check_ajax_referer( 'search_intel_nonce', 'nonce' );

		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
		}

		$stack = get_post_meta( $post_id, self::SNAPSHOT_META_KEY, true );
		if ( ! is_array( $stack ) || empty( $stack ) ) {
			wp_send_json_error( [ 'message' => 'No rollback snapshot available.' ] );
		}

		// Pop the latest snapshot.
		$snapshot = array_pop( $stack );
		update_post_meta( $post_id, self::SNAPSHOT_META_KEY, $stack );

		// Restore post title and content.
		wp_update_post( [
			'ID'           => $post_id,
			'post_title'   => $snapshot['post_title'] ?? '',
			'post_content' => $snapshot['post_content'] ?? '',
		] );

		// Restore tracked meta values.
		foreach ( self::TRACKED_META_KEYS as $key ) {
			if ( array_key_exists( $key, $snapshot['meta'] ?? [] ) ) {
				$val = $snapshot['meta'][ $key ];
				if ( $val === '' || $val === false || $val === null ) {
					delete_post_meta( $post_id, $key );
				} else {
					update_post_meta( $post_id, $key, $val );
				}
			}
		}

		// Restore scores.
		if ( ! empty( $snapshot['scores'] ) ) {
			update_post_meta( $post_id, '_search_intel_seo_score', $snapshot['scores']['seo'] ?? 0 );
			update_post_meta( $post_id, '_search_intel_aeo_score', $snapshot['scores']['aeo'] ?? 0 );
			update_post_meta( $post_id, '_search_intel_geo_score', $snapshot['scores']['geo'] ?? 0 );
			update_post_meta( $post_id, '_search_intel_readability_score', $snapshot['scores']['readability'] ?? 0 );
		}

		wp_send_json_success( [
			'message'        => 'Rolled back to previous version (' . ( $snapshot['date'] ?? 'unknown' ) . ').',
			'scores'         => $snapshot['scores'] ?? [],
			'remaining'      => count( $stack ),
			'snapshot_date'  => $snapshot['date'] ?? '',
		] );
	}

	/**
	 * Check if any failing check label contains a keyword.
	 *
	 * @param array  $failing Array of failing checks.
	 * @param string $keyword Keyword to search for.
	 * @return bool
	 */
	private function checks_contain( array $failing, string $keyword ): bool {
		$keyword = strtolower( $keyword );
		foreach ( $failing as $check ) {
			if ( stripos( $check['label'] ?? '', $keyword ) !== false ) {
				return true;
			}
		}
		return false;
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

		$snapshots     = get_post_meta( $post->ID, self::SNAPSHOT_META_KEY, true );
		$has_snapshots = is_array( $snapshots ) && ! empty( $snapshots );
		$snap_count    = $has_snapshots ? count( $snapshots ) : 0;
		$last_snap     = $has_snapshots ? end( $snapshots ) : null;
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

			<div class="si-ai-actions" style="margin-bottom:10px;">
				<button type="button" class="button button-primary" id="si-ai-auto-optimize-btn" <?php echo $fail_count === 0 ? 'disabled' : ''; ?>
					title="AI will rewrite content, title, meta, FAQ, and summary to fix all failing checks. A rollback snapshot is created first.">
					<?php esc_html_e( 'Auto-Optimize', 'search-intel' ); ?>
				</button>

				<button type="button" class="button" id="si-ai-rollback-btn" <?php echo $has_snapshots ? '' : 'disabled'; ?>
					title="Undo the last AI optimization and restore the previous version.">
					<?php esc_html_e( 'Rollback', 'search-intel' ); ?>
					<?php if ( $snap_count > 0 ) : ?>
						<span class="si-rollback-count">(<?php echo esc_html( (string) $snap_count ); ?>)</span>
					<?php endif; ?>
				</button>
			</div>

			<?php if ( $has_snapshots && $last_snap ) : ?>
				<div id="si-ai-snapshot-info" class="si-snapshot-info">
					<?php /* translators: %s: snapshot date */ ?>
					<?php printf( esc_html__( 'Last snapshot: %s', 'search-intel' ), esc_html( $last_snap['date'] ?? '' ) ); ?>
				</div>
			<?php else : ?>
				<div id="si-ai-snapshot-info" class="si-snapshot-info" style="display:none;"></div>
			<?php endif; ?>

			<div id="si-ai-optimize-result" style="display:none;margin-bottom:10px;"></div>

			<hr style="margin:10px 0;">

			<button type="button" class="button" id="si-ai-generate-btn" <?php echo $fail_count === 0 ? 'disabled' : ''; ?> style="width:100%;">
				<?php echo $has_cached ? esc_html__( 'Regenerate AI Suggestions', 'search-intel' ) : esc_html__( 'Generate AI Suggestions', 'search-intel' ); ?>
			</button>

			<div id="si-ai-suggestions-list" style="margin-top:12px;">
				<?php if ( $has_cached ) : ?>
					<?php $this->render_suggestions( $cached ); ?>
				<?php endif; ?>
			</div>

			<div id="si-ai-loading" style="display:none;margin-top:10px;">
				<span class="spinner is-active" style="float:none;margin:0 5px 0 0;"></span>
				<span id="si-ai-loading-text"><?php esc_html_e( 'Generating AI suggestions…', 'search-intel' ); ?></span>
			</div>
		</div>

		<style>
			.si-score-badge { display:inline-block; padding:2px 8px; border-radius:3px; color:#fff; font-size:11px; font-weight:600; margin:0 2px 4px 0; }
			.si-ai-actions { display:flex; gap:6px; }
			.si-ai-actions .button { flex:1; text-align:center; }
			.si-snapshot-info { font-size:11px; color:#646970; margin-bottom:8px; }
			.si-rollback-count { font-size:10px; opacity:.75; }
			.si-optimize-result { padding:8px 10px; border-radius:3px; font-size:12px; }
			.si-optimize-result--success { background:#f0f6e8; border-left:3px solid #00a32a; }
			.si-optimize-result--error { background:#fcf0f1; border-left:3px solid #d63638; }
			.si-optimize-result .si-score-delta { display:inline-block; margin:0 6px 2px 0; font-size:11px; font-weight:600; }
			.si-optimize-result .si-score-delta .up { color:#00a32a; }
			.si-optimize-result .si-score-delta .down { color:#d63638; }
			.si-optimize-result .si-score-delta .same { color:#646970; }
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
			SEARCH_INTEL_URL . 'admin/js/ai-optimizer.js',
			[ 'jquery' ],
			SEARCH_INTEL_VERSION,
			true
		);

		wp_localize_script( 'si-ai-optimizer', 'siAiOptimizer', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'search_intel_nonce' ),
		] );
	}
}
