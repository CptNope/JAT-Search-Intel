<?php
/**
 * AI citation tracking: does ChatGPT, Claude, Gemini or Perplexity cite this
 * site when asked the questions that matter to it?
 *
 * You list the questions your customers ask. Each week (or when you click
 * Check now) every question goes to each AI engine you have a key for, with
 * web search switched on. The answer's citations are compared with this site:
 *
 *   cited      a link to this site is among the answer's citations
 *   source     the engine read this site but didn't cite it
 *   mentioned  the answer names the site or brand
 *
 * Results are kept per question and engine (last 12 checks), with the
 * domains cited most often instead, so you can see who you are losing to.
 * It uses your own API keys; nothing goes through any other service.
 *
 * @package JAT_Search_Intel
 */

namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scheduled AI citation checks.
 */
class Citation_Tracker {

	public const QUESTIONS_OPTION = 'jat_search_intel_citation_questions';
	public const RESULTS_OPTION   = 'jat_search_intel_citation_results';
	public const ENGINES_OPTION   = 'jat_search_intel_citation_engines';
	public const QUEUE_OPTION     = 'jat_search_intel_citation_queue';
	public const CRON_HOOK        = 'jat_search_intel_citation_check';
	public const BATCH_HOOK       = 'jat_search_intel_citation_batch';
	public const PAGE_SLUG        = 'jat-search-intel-citations';

	/** Questions you can track (each costs one request per engine per check). */
	public const MAX_QUESTIONS = 25;

	/** Checks kept per question and engine. */
	public const HISTORY = 12;

	/** Checks run per background batch, so no request runs into PHP time limits. */
	public const BATCH_SIZE = 6;

	/** Engine => label, API key provider, default model. */
	public const ENGINES = [
		'openai'     => [ 'label' => 'ChatGPT', 'model' => 'gpt-5-mini' ],
		'anthropic'  => [ 'label' => 'Claude', 'model' => 'claude-haiku-5-5' ],
		'gemini'     => [ 'label' => 'Gemini', 'model' => 'gemini-2.5-flash' ],
		'perplexity' => [ 'label' => 'Perplexity', 'model' => 'sonar' ],
	];

	/** @var array<string,mixed> */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;

		add_action( self::BATCH_HOOK, [ $this, 'run_batch' ] );
		if ( ! empty( $settings['enable_citation_tracking'] ) ) {
			add_action( self::CRON_HOOK, [ $this, 'queue_all' ] );
			if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'weekly', self::CRON_HOOK );
			}
		}

		if ( is_admin() ) {
			add_action( 'admin_menu', [ $this, 'register_admin_page' ], 33 );
			add_action( 'admin_post_jat_search_intel_citations', [ $this, 'handle_form' ] );
		}
	}

	// ── Questions and engines ───────────────────────────────────────────────

	/**
	 * Tracked questions.
	 *
	 * @return array<string,array{q:string,added:int}> id => question.
	 */
	public static function questions(): array {
		$out = [];
		foreach ( (array) get_option( self::QUESTIONS_OPTION, [] ) as $id => $row ) {
			if ( is_array( $row ) && '' !== (string) ( $row['q'] ?? '' ) ) {
				$out[ (string) $id ] = [ 'q' => (string) $row['q'], 'added' => (int) ( $row['added'] ?? 0 ) ];
			}
		}
		return $out;
	}

	/**
	 * Add questions (one per line). Duplicates and blanks are skipped.
	 *
	 * @param string $text Lines of questions.
	 * @return int Number added.
	 */
	public static function add_questions( string $text ): int {
		$questions = self::questions();
		$existing  = array_map( 'strtolower', array_column( $questions, 'q' ) );
		$added     = 0;
		foreach ( preg_split( '/\R/', $text ) ?: [] as $line ) {
			$line = trim( sanitize_text_field( $line ) );
			if ( '' === $line || mb_strlen( $line ) > 300 || in_array( strtolower( $line ), $existing, true ) ) {
				continue;
			}
			if ( count( $questions ) >= self::MAX_QUESTIONS ) {
				break;
			}
			$questions[ substr( md5( $line ), 0, 10 ) ] = [ 'q' => $line, 'added' => time() ];
			$existing[]                                = strtolower( $line );
			++$added;
		}
		update_option( self::QUESTIONS_OPTION, $questions, false );
		return $added;
	}

	/**
	 * Stop tracking a question and drop its results.
	 *
	 * @param string $id Question ID.
	 */
	public static function remove_question( string $id ): void {
		$questions = self::questions();
		unset( $questions[ $id ] );
		update_option( self::QUESTIONS_OPTION, $questions, false );
		$results = self::results();
		unset( $results[ $id ] );
		update_option( self::RESULTS_OPTION, $results, false );
	}

	/**
	 * Engines switched on that have an API key.
	 *
	 * @param AI_Engine|null $ai AI engine (for keys).
	 * @return string[]
	 */
	public function active_engines( ?AI_Engine $ai = null ): array {
		$ai      = $ai ?? new AI_Engine( $this->settings );
		$chosen  = (array) get_option( self::ENGINES_OPTION, array_keys( self::ENGINES ) );
		$engines = [];
		foreach ( array_keys( self::ENGINES ) as $engine ) {
			if ( in_array( $engine, $chosen, true ) && '' !== $ai->get_api_key( $engine ) ) {
				$engines[] = $engine;
			}
		}
		return $engines;
	}

	/**
	 * Model used for an engine.
	 *
	 * @param string $engine Engine ID.
	 * @return string
	 */
	public static function model( string $engine ): string {
		/**
		 * Filters the models used for citation checks.
		 *
		 * @param array<string,string> $models Engine => model.
		 */
		$models = (array) apply_filters( 'jat_search_intel_citation_models', array_map( static fn( $e ) => $e['model'], self::ENGINES ) );
		return (string) ( $models[ $engine ] ?? self::ENGINES[ $engine ]['model'] ?? '' );
	}

	// ── Asking an engine ────────────────────────────────────────────────────

	/**
	 * The HTTP request for one question to one engine.
	 *
	 * @param string $engine   Engine ID.
	 * @param string $question Question.
	 * @param string $key      API key.
	 * @return array{url:string,headers:array<string,string>,body:array<string,mixed>}
	 */
	public static function request( string $engine, string $question, string $key ): array {
		$model = self::model( $engine );
		switch ( $engine ) {
			case 'openai':
				return [
					'url'     => 'https://api.openai.com/v1/responses',
					'headers' => [ 'Authorization' => 'Bearer ' . $key ],
					'body'    => [
						'model'   => $model,
						'input'   => $question,
						'tools'   => [ [ 'type' => 'web_search' ] ],
						'include' => [ 'web_search_call.action.sources' ],
					],
				];
			case 'anthropic':
				return [
					'url'     => 'https://api.anthropic.com/v1/messages',
					'headers' => [ 'x-api-key' => $key, 'anthropic-version' => '2023-06-01' ],
					'body'    => [
						'model'      => $model,
						'max_tokens' => 1500,
						'messages'   => [ [ 'role' => 'user', 'content' => $question ] ],
						'tools'      => [ [ 'type' => 'web_search_20250305', 'name' => 'web_search', 'max_uses' => 3 ] ],
					],
				];
			case 'gemini':
				return [
					'url'     => 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent',
					'headers' => [ 'x-goog-api-key' => $key ],
					'body'    => [
						'contents' => [ [ 'parts' => [ [ 'text' => $question ] ] ] ],
						'tools'    => [ [ 'google_search' => new \stdClass() ] ],
					],
				];
			default: // Perplexity. Older accounts may only answer on the legacy path (see ask()).
				return [
					'url'     => 'https://api.perplexity.ai/v1/sonar',
					'headers' => [ 'Authorization' => 'Bearer ' . $key ],
					'body'    => [
						'model'    => $model,
						'messages' => [ [ 'role' => 'user', 'content' => $question ] ],
					],
				];
		}
	}

	/**
	 * Ask one engine one question.
	 *
	 * @param string    $engine   Engine ID.
	 * @param string    $question Question.
	 * @param AI_Engine $ai       AI engine (for keys).
	 * @return array{text:string,cited:string[],sources:string[]}|\WP_Error
	 */
	public static function ask( string $engine, string $question, AI_Engine $ai ) {
		$key = $ai->get_api_key( $engine );
		if ( '' === $key ) {
			return new \WP_Error( 'jsi_citation_no_key', __( 'No API key for this engine.', 'jat-search-intel' ) );
		}
		$request = self::request( $engine, $question, $key );
		$args    = [
			'timeout' => 90,
			'headers' => $request['headers'] + [ 'Content-Type' => 'application/json' ],
			'body'    => (string) wp_json_encode( $request['body'] ),
		];
		$response = wp_remote_post( $request['url'], $args );
		if ( 'perplexity' === $engine && ! is_wp_error( $response ) && 404 === (int) wp_remote_retrieve_response_code( $response ) ) {
			$response = wp_remote_post( 'https://api.perplexity.ai/chat/completions', $args );
		}
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$json = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $code || ! is_array( $json ) ) {
			$message = is_array( $json ) ? ( $json['error']['message'] ?? '' ) : '';
			/* translators: 1: engine, 2: HTTP status code. */
			return new \WP_Error( 'jsi_citation_http', '' !== (string) $message ? (string) $message : sprintf( __( '%1$s returned HTTP %2$d.', 'jat-search-intel' ), self::ENGINES[ $engine ]['label'] ?? $engine, $code ), [ 'status' => $code ] );
		}
		return self::parse( $engine, $json );
	}

	/**
	 * Answer text, cited URLs and consulted URLs from an engine's response.
	 *
	 * @param string              $engine Engine ID.
	 * @param array<string,mixed> $json   Decoded response.
	 * @return array{text:string,cited:string[],sources:string[]}
	 */
	public static function parse( string $engine, array $json ): array {
		$text    = [];
		$cited   = [];
		$sources = [];

		switch ( $engine ) {
			case 'openai':
				foreach ( (array) ( $json['output'] ?? [] ) as $item ) {
					if ( 'web_search_call' === ( $item['type'] ?? '' ) ) {
						foreach ( (array) ( $item['action']['sources'] ?? [] ) as $source ) {
							$sources[] = (string) ( $source['url'] ?? '' );
						}
					}
					if ( 'message' === ( $item['type'] ?? '' ) ) {
						foreach ( (array) ( $item['content'] ?? [] ) as $part ) {
							$text[] = (string) ( $part['text'] ?? '' );
							foreach ( (array) ( $part['annotations'] ?? [] ) as $note ) {
								if ( 'url_citation' === ( $note['type'] ?? '' ) ) {
									$cited[] = (string) ( $note['url'] ?? '' );
								}
							}
						}
					}
				}
				break;

			case 'anthropic':
				foreach ( (array) ( $json['content'] ?? [] ) as $block ) {
					if ( 'text' === ( $block['type'] ?? '' ) ) {
						$text[] = (string) ( $block['text'] ?? '' );
						foreach ( (array) ( $block['citations'] ?? [] ) as $citation ) {
							$cited[] = (string) ( $citation['url'] ?? '' );
						}
					}
					if ( 'web_search_tool_result' === ( $block['type'] ?? '' ) && isset( $block['content'][0] ) ) {
						foreach ( (array) $block['content'] as $result ) {
							$sources[] = (string) ( $result['url'] ?? '' );
						}
					}
				}
				break;

			case 'gemini':
				$candidate = (array) ( $json['candidates'][0] ?? [] );
				foreach ( (array) ( $candidate['content']['parts'] ?? [] ) as $part ) {
					$text[] = (string) ( $part['text'] ?? '' );
				}
				// Grounding links are Google redirect URLs; the title holds the site's domain.
				foreach ( (array) ( $candidate['groundingMetadata']['groundingChunks'] ?? [] ) as $chunk ) {
					$uri   = (string) ( $chunk['web']['uri'] ?? '' );
					$title = (string) ( $chunk['web']['title'] ?? '' );
					$host  = (string) wp_parse_url( $uri, PHP_URL_HOST );
					$cited[] = ( '' === $host || false !== strpos( $host, 'vertexaisearch' ) ) && '' !== $title ? 'https://' . $title . '/' : $uri;
				}
				break;

			default: // Perplexity.
				$text[] = (string) ( $json['choices'][0]['message']['content'] ?? '' );
				foreach ( (array) ( $json['citations'] ?? [] ) as $url ) {
					$cited[] = is_array( $url ) ? (string) ( $url['url'] ?? '' ) : (string) $url;
				}
				foreach ( (array) ( $json['search_results'] ?? [] ) as $result ) {
					$sources[] = (string) ( $result['url'] ?? '' );
				}
		}

		$clean = static fn( array $urls ): array => array_values( array_unique( array_filter( array_map( 'trim', $urls ) ) ) );
		$cited = $clean( $cited );
		return [
			'text'    => trim( implode( "\n", $text ) ),
			'cited'   => $cited,
			'sources' => $clean( array_merge( $sources, $cited ) ),
		];
	}

	// ── Comparing an answer with this site ──────────────────────────────────

	/**
	 * This site's host without www.
	 *
	 * @return string
	 */
	public static function site_host(): string {
		return self::host( home_url( '/' ) );
	}

	/**
	 * Host of a URL, lowercased, without www.
	 *
	 * @param string $url URL or bare domain.
	 * @return string
	 */
	public static function host( string $url ): string {
		$host = (string) wp_parse_url( false === strpos( $url, '//' ) ? 'https://' . $url : $url, PHP_URL_HOST );
		return (string) preg_replace( '/^www\./', '', strtolower( $host ) );
	}

	/**
	 * Whether a URL belongs to a site (the host or a subdomain of it).
	 *
	 * @param string $url  URL.
	 * @param string $site Site host.
	 * @return bool
	 */
	public static function is_site_url( string $url, string $site ): bool {
		$host = self::host( $url );
		return '' !== $site && ( $host === $site || str_ends_with( $host, '.' . $site ) );
	}

	/**
	 * Compare an answer with this site.
	 *
	 * @param array{text:string,cited:string[],sources:string[]} $answer Parsed answer.
	 * @param string                                             $site   Site host.
	 * @param string[]                                           $names  Brand names to look for in the text.
	 * @return array{cited:bool,source:bool,mentioned:bool,urls:string[],top:string[]}
	 */
	public static function evaluate( array $answer, string $site, array $names ): array {
		$ours   = [];
		$others = [];
		foreach ( $answer['cited'] as $url ) {
			if ( self::is_site_url( $url, $site ) ) {
				$ours[] = $url;
			} else {
				$host = self::host( $url );
				if ( '' !== $host ) {
					$others[ $host ] = ( $others[ $host ] ?? 0 ) + 1;
				}
			}
		}
		arsort( $others );

		$source = false;
		foreach ( $answer['sources'] as $url ) {
			if ( self::is_site_url( $url, $site ) ) {
				$source = true;
				break;
			}
		}

		$mentioned = false;
		$haystack  = strtolower( $answer['text'] );
		foreach ( array_merge( $names, [ $site ] ) as $name ) {
			$name = strtolower( trim( (string) $name ) );
			if ( mb_strlen( $name ) >= 3 && false !== strpos( $haystack, $name ) ) {
				$mentioned = true;
				break;
			}
		}

		return [
			'cited'     => (bool) $ours,
			'source'    => $source,
			'mentioned' => $mentioned,
			'urls'      => array_slice( array_values( array_unique( $ours ) ), 0, 5 ),
			'top'       => array_slice( array_keys( $others ), 0, 5 ),
		];
	}

	/**
	 * Names the answer might use for this site.
	 *
	 * @return string[]
	 */
	public function brand_names(): array {
		return array_values( array_unique( array_filter( [ (string) ( $this->settings['knowledge_name'] ?? '' ), (string) get_bloginfo( 'name' ) ] ) ) );
	}

	// ── Running checks ──────────────────────────────────────────────────────

	/**
	 * Check one question on one engine and store the result.
	 *
	 * @param string         $id     Question ID.
	 * @param string         $engine Engine ID.
	 * @param AI_Engine|null $ai     AI engine (for keys).
	 * @return array<string,mixed> The stored check.
	 */
	public function check( string $id, string $engine, ?AI_Engine $ai = null ): array {
		$questions = self::questions();
		if ( ! isset( $questions[ $id ], self::ENGINES[ $engine ] ) ) {
			return [];
		}
		$answer = self::ask( $engine, $questions[ $id ]['q'], $ai ?? new AI_Engine( $this->settings ) );
		if ( is_wp_error( $answer ) ) {
			$data  = $answer->get_error_data();
			$entry = [
				't'      => time(),
				'error'  => $answer->get_error_message(),
				'status' => is_array( $data ) ? (int) ( $data['status'] ?? 0 ) : 0,
			];
		} else {
			$entry = [ 't' => time() ] + self::evaluate( $answer, self::site_host(), $this->brand_names() );
		}
		self::store( $id, $engine, $entry );
		return $entry;
	}

	/**
	 * Add a check to the history (newest first).
	 *
	 * @param string              $id     Question ID.
	 * @param string              $engine Engine ID.
	 * @param array<string,mixed> $entry  Check.
	 */
	public static function store( string $id, string $engine, array $entry ): void {
		$results = self::results();
		$history = (array) ( $results[ $id ][ $engine ] ?? [] );
		array_unshift( $history, $entry );
		$results[ $id ][ $engine ] = array_slice( $history, 0, self::HISTORY );
		update_option( self::RESULTS_OPTION, $results, false );
	}

	/**
	 * All stored results.
	 *
	 * @return array<string,array<string,array<int,array<string,mixed>>>> question => engine => checks.
	 */
	public static function results(): array {
		return (array) get_option( self::RESULTS_OPTION, [] );
	}

	/**
	 * Queue every question on every active engine and start working through it.
	 *
	 * @return int Checks queued.
	 */
	public function queue_all(): int {
		$queue = [];
		foreach ( array_keys( self::questions() ) as $id ) {
			foreach ( $this->active_engines() as $engine ) {
				$queue[] = $id . '|' . $engine;
			}
		}
		update_option( self::QUEUE_OPTION, $queue, false );
		if ( $queue && ! wp_next_scheduled( self::BATCH_HOOK ) ) {
			wp_schedule_single_event( time(), self::BATCH_HOOK );
		}
		return count( $queue );
	}

	/**
	 * Run the next batch of queued checks; schedule another batch if any remain.
	 *
	 * @return int Checks run.
	 */
	public function run_batch(): int {
		$queue = (array) get_option( self::QUEUE_OPTION, [] );
		$batch = array_splice( $queue, 0, self::BATCH_SIZE );
		update_option( self::QUEUE_OPTION, $queue, false );

		$ai      = new AI_Engine( $this->settings );
		$ran     = 0;
		$stopped = [];
		foreach ( $batch as $item ) {
			[ $id, $engine ] = array_pad( explode( '|', (string) $item, 2 ), 2, '' );
			if ( isset( $stopped[ $engine ] ) ) {
				continue;
			}
			$entry = $this->check( $id, $engine, $ai );
			++$ran;
			// A rejected key or exhausted quota fails every request: stop that engine for this run.
			if ( in_array( (int) ( $entry['status'] ?? 0 ), [ 401, 403, 429 ], true ) ) {
				$stopped[ $engine ] = true;
				$queue              = array_values( array_filter( $queue, static fn( $q ) => ! str_ends_with( (string) $q, '|' . $engine ) ) );
				update_option( self::QUEUE_OPTION, $queue, false );
			}
		}
		if ( $queue ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::BATCH_HOOK );
		}
		return $ran;
	}

	// ── Summaries ───────────────────────────────────────────────────────────

	/**
	 * Latest result per question and engine, plus totals.
	 *
	 * @return array{rows:array<string,array{q:string,engines:array<string,array<string,mixed>>}>,checked:int,cited:int,mentioned:int,lost:array<int,array{question:string,engine:string,urls:string[]}>,competitors:array<string,int>}
	 */
	public static function summary(): array {
		$results     = self::results();
		$rows        = [];
		$checked     = 0;
		$cited       = 0;
		$mentioned   = 0;
		$lost        = [];
		$competitors = [];
		foreach ( self::questions() as $id => $question ) {
			$engines = [];
			foreach ( (array) ( $results[ $id ] ?? [] ) as $engine => $history ) {
				$latest = self::latest_ok( (array) $history );
				if ( null === $latest ) {
					$engines[ $engine ] = (array) ( $history[0] ?? [] );
					continue;
				}
				$engines[ $engine ] = $latest;
				++$checked;
				$cited     += ! empty( $latest['cited'] ) ? 1 : 0;
				$mentioned += ! empty( $latest['mentioned'] ) ? 1 : 0;
				foreach ( (array) ( $latest['top'] ?? [] ) as $domain ) {
					$competitors[ $domain ] = ( $competitors[ $domain ] ?? 0 ) + 1;
				}
				$previous = self::latest_ok( array_slice( (array) $history, (int) array_search( $latest, (array) $history, true ) + 1 ) );
				if ( empty( $latest['cited'] ) && $previous && ! empty( $previous['cited'] ) ) {
					$lost[] = [ 'question' => $question['q'], 'engine' => (string) $engine, 'urls' => (array) ( $previous['urls'] ?? [] ) ];
				}
			}
			$rows[ $id ] = [ 'q' => $question['q'], 'engines' => $engines ];
		}
		arsort( $competitors );
		return [
			'rows'        => $rows,
			'checked'     => $checked,
			'cited'       => $cited,
			'mentioned'   => $mentioned,
			'lost'        => $lost,
			'competitors' => array_slice( $competitors, 0, 10, true ),
		];
	}

	/**
	 * Newest check that didn't fail.
	 *
	 * @param array<int,array<string,mixed>> $history Checks, newest first.
	 * @return array<string,mixed>|null
	 */
	private static function latest_ok( array $history ): ?array {
		foreach ( $history as $entry ) {
			if ( is_array( $entry ) && ! isset( $entry['error'] ) ) {
				return $entry;
			}
		}
		return null;
	}

	/**
	 * Posts whose citations were lost since the previous check (for the refresh queue).
	 *
	 * @return array<int,string[]> post ID => questions it stopped being cited for.
	 */
	public static function lost_citation_posts(): array {
		$posts = [];
		foreach ( self::summary()['lost'] as $lost ) {
			foreach ( $lost['urls'] as $url ) {
				$post_id = url_to_postid( $url );
				if ( $post_id > 0 ) {
					$posts[ $post_id ][] = $lost['question'];
				}
			}
		}
		return array_map( static fn( $q ) => array_values( array_unique( $q ) ), $posts );
	}

	// ── Admin ───────────────────────────────────────────────────────────────

	/**
	 * Register the report page.
	 */
	public function register_admin_page(): void {
		add_submenu_page(
			'jat-search-intel',
			__( 'AI Citations', 'jat-search-intel' ),
			__( 'AI Citations', 'jat-search-intel' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_admin_page' ]
		);
	}

	/**
	 * Form handler: add or remove questions, save engines and the Perplexity key, check now.
	 */
	public function handle_form(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'jat-search-intel' ), 403 );
		}
		check_admin_referer( 'jat_search_intel_citations' );

		$do     = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$notice = '';
		switch ( $do ) {
			case 'add':
				$text   = isset( $_POST['questions'] ) ? sanitize_textarea_field( wp_unslash( $_POST['questions'] ) ) : '';
				$notice = 'added:' . self::add_questions( $text );
				break;
			case 'remove':
				self::remove_question( isset( $_POST['id'] ) ? sanitize_key( wp_unslash( $_POST['id'] ) ) : '' );
				$notice = 'removed';
				break;
			case 'engines':
				$engines = isset( $_POST['engines'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['engines'] ) ) : [];
				update_option( self::ENGINES_OPTION, array_values( array_intersect( array_keys( self::ENGINES ), $engines ) ), false );
				$key = isset( $_POST['perplexity_key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['perplexity_key'] ) ) ) : '';
				if ( '' !== $key ) {
					( new AI_Engine( $this->settings ) )->save_api_key( 'perplexity', $key );
				}
				$notice = 'saved';
				break;
			case 'check':
				$notice = 'queued:' . $this->queue_all();
				break;
		}
		wp_safe_redirect( add_query_arg( [ 'page' => self::PAGE_SLUG, 'jsi_notice' => rawurlencode( $notice ) ], admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Hidden fields every form on the page needs.
	 *
	 * @param string $do Action.
	 * @return string
	 */
	private static function form_fields( string $do ): string {
		return wp_nonce_field( 'jat_search_intel_citations', '_wpnonce', true, false )
			. '<input type="hidden" name="action" value="jat_search_intel_citations" />'
			. '<input type="hidden" name="do" value="' . esc_attr( $do ) . '" />';
	}

	/**
	 * The report page.
	 */
	public function render_admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$post_url = admin_url( 'admin-post.php' );
		$ai       = new AI_Engine( $this->settings );
		$active   = $this->active_engines( $ai );
		$summary  = self::summary();
		$queue    = (array) get_option( self::QUEUE_OPTION, [] );

		echo '<div class="wrap si-settings"><h1>' . esc_html__( 'AI Citations', 'jat-search-intel' ) . '</h1>';
		echo '<p>' . esc_html__( 'Whether AI assistants cite this site when asked the questions your customers ask. Each question goes to every engine you have an API key for, with web search on, using your own keys.', 'jat-search-intel' ) . '</p>';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only notice after a nonce-checked redirect.
		$notice = isset( $_GET['jsi_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['jsi_notice'] ) ) : '';
		$parts  = explode( ':', $notice, 2 );
		$count  = (int) ( $parts[1] ?? 0 );
		$notes  = [
			/* translators: %d: number of questions. */
			'added'   => sprintf( _n( 'Added %d question.', 'Added %d questions.', $count, 'jat-search-intel' ), $count ),
			'removed' => __( 'Question removed.', 'jat-search-intel' ),
			'saved'   => __( 'Settings saved.', 'jat-search-intel' ),
			/* translators: %d: number of checks. */
			'queued'  => sprintf( _n( '%d check queued. Results appear here over the next few minutes.', '%d checks queued. Results appear here over the next few minutes.', $count, 'jat-search-intel' ), $count ),
		];
		if ( isset( $notes[ $parts[0] ] ) ) {
			echo '<div class="notice notice-success"><p>' . esc_html( $notes[ $parts[0] ] ) . '</p></div>';
		}

		if ( ! $active ) {
			echo '<div class="notice notice-warning inline"><p>' . wp_kses_post(
				sprintf(
					/* translators: %s: link to AI settings. */
					__( 'Add an OpenAI, Anthropic or Google Gemini key under <a href="%s">Settings → AI</a>, or a Perplexity key below, to start checking.', 'jat-search-intel' ),
					esc_url( admin_url( 'admin.php?page=jat-search-intel-ai' ) )
				)
			) . '</p></div>';
		}

		// Totals.
		$share = $summary['checked'] ? (int) round( $summary['cited'] / $summary['checked'] * 100 ) : 0;
		echo '<div class="si-settings-card" style="display:flex;gap:28px;flex-wrap:wrap;align-items:center">';
		echo '<div><strong style="font-size:22px;display:block">' . esc_html( $summary['checked'] ? $share . '%' : '—' ) . '</strong>' . esc_html__( 'Answers citing you', 'jat-search-intel' ) . '</div>';
		echo '<div><strong style="font-size:22px;display:block">' . esc_html( number_format_i18n( $summary['mentioned'] ) ) . '</strong>' . esc_html__( 'Answers naming you', 'jat-search-intel' ) . '</div>';
		echo '<div><strong style="font-size:22px;display:block">' . esc_html( number_format_i18n( count( $summary['lost'] ) ) ) . '</strong>' . esc_html__( 'Citations lost since last check', 'jat-search-intel' ) . '</div>';
		echo '<form method="post" action="' . esc_url( $post_url ) . '" style="margin-left:auto">' . self::form_fields( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in form_fields().
		submit_button( $queue ? __( 'Checking…', 'jat-search-intel' ) : __( 'Check now', 'jat-search-intel' ), 'primary', 'submit', false, ( $queue || ! $active || ! $summary['rows'] ) ? [ 'disabled' => 'disabled' ] : null );
		echo '</form></div>';
		if ( empty( $this->settings['enable_citation_tracking'] ) ) {
			echo '<p class="description">' . esc_html__( 'Tip: switch on weekly checks under Settings → GEO to keep this report up to date.', 'jat-search-intel' ) . '</p>';
		}

		// Results table.
		$engines = array_keys( self::ENGINES );
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Question', 'jat-search-intel' ) . '</th>';
		foreach ( $engines as $engine ) {
			echo '<th>' . esc_html( self::ENGINES[ $engine ]['label'] ) . '</th>';
		}
		echo '<th>' . esc_html__( 'Cited instead', 'jat-search-intel' ) . '</th><th></th></tr></thead><tbody>';
		if ( ! $summary['rows'] ) {
			echo '<tr><td colspan="' . esc_attr( (string) ( count( $engines ) + 3 ) ) . '">' . esc_html__( 'Add the questions you want to be the answer to.', 'jat-search-intel' ) . '</td></tr>';
		}
		foreach ( $summary['rows'] as $id => $row ) {
			echo '<tr><td><strong>' . esc_html( $row['q'] ) . '</strong></td>';
			$others = [];
			foreach ( $engines as $engine ) {
				$entry = $row['engines'][ $engine ] ?? [];
				echo '<td>' . self::status_html( $entry ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in status_html().
				$others = array_merge( $others, (array) ( $entry['top'] ?? [] ) );
			}
			echo '<td><small>' . esc_html( implode( ', ', array_slice( array_unique( $others ), 0, 3 ) ) ?: '—' ) . '</small></td>';
			echo '<td><form method="post" action="' . esc_url( $post_url ) . '">' . self::form_fields( 'remove' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in form_fields().
			echo '<input type="hidden" name="id" value="' . esc_attr( (string) $id ) . '" /><button type="submit" class="button-link">' . esc_html__( 'Remove', 'jat-search-intel' ) . '</button></form></td></tr>';
		}
		echo '</tbody></table>';

		if ( $summary['competitors'] ) {
			echo '<h2>' . esc_html__( 'Sites cited most often for your questions', 'jat-search-intel' ) . '</h2><ol>';
			foreach ( $summary['competitors'] as $domain => $times ) {
				/* translators: 1: domain, 2: number of answers. */
				echo '<li>' . esc_html( sprintf( _n( '%1$s (%2$d answer)', '%1$s (%2$d answers)', $times, 'jat-search-intel' ), $domain, $times ) ) . '</li>';
			}
			echo '</ol>';
		}

		// Add questions.
		$room = self::MAX_QUESTIONS - count( $summary['rows'] );
		echo '<div class="si-settings-card"><h2>' . esc_html__( 'Questions to track', 'jat-search-intel' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( $post_url ) . '">' . self::form_fields( 'add' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in form_fields().
		/* translators: %d: number of questions that can still be added. */
		echo '<p><label for="jsi-questions">' . esc_html( sprintf( __( 'One question per line, written the way a customer would ask it. Room for %d more.', 'jat-search-intel' ), max( 0, $room ) ) ) . '</label></p>';
		echo '<textarea id="jsi-questions" name="questions" rows="4" class="large-text" placeholder="' . esc_attr__( 'What is the best accounting software for a small bakery?', 'jat-search-intel' ) . '"' . ( $room > 0 ? '' : ' disabled' ) . '></textarea>';
		submit_button( __( 'Add questions', 'jat-search-intel' ), 'secondary', 'submit', true, $room > 0 ? null : [ 'disabled' => 'disabled' ] );
		echo '</form></div>';

		// Engines and the Perplexity key.
		$chosen = (array) get_option( self::ENGINES_OPTION, $engines );
		echo '<div class="si-settings-card"><h2>' . esc_html__( 'Engines', 'jat-search-intel' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( $post_url ) . '">' . self::form_fields( 'engines' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in form_fields().
		foreach ( $engines as $engine ) {
			$has_key = '' !== $ai->get_api_key( $engine );
			echo '<label style="display:block;margin:4px 0"><input type="checkbox" name="engines[]" value="' . esc_attr( $engine ) . '" ' . checked( in_array( $engine, $chosen, true ), true, false ) . ' /> ' . esc_html( self::ENGINES[ $engine ]['label'] );
			echo ' <span class="description">(' . esc_html( self::model( $engine ) ) . ', ' . ( $has_key ? esc_html__( 'key saved', 'jat-search-intel' ) : esc_html__( 'no key', 'jat-search-intel' ) ) . ')</span></label>';
		}
		echo '<p><label for="jsi-perplexity-key">' . esc_html__( 'Perplexity API key', 'jat-search-intel' ) . '</label><br />';
		echo '<input type="password" id="jsi-perplexity-key" name="perplexity_key" class="regular-text" autocomplete="off" placeholder="' . esc_attr( '' !== $ai->get_api_key( 'perplexity' ) ? __( 'Saved. Enter a new key to replace it.', 'jat-search-intel' ) : 'pplx-…' ) . '" /> ';
		echo '<a href="https://www.perplexity.ai/account/api/keys" target="_blank" rel="noopener">' . esc_html__( 'Get a key', 'jat-search-intel' ) . '</a></p>';
		echo '<p class="description">' . esc_html__( 'Each question costs one web-search request per engine per check, billed by the provider to your account (roughly 1–3 US cents each at current prices).', 'jat-search-intel' ) . '</p>';
		submit_button( __( 'Save engines', 'jat-search-intel' ), 'secondary' );
		echo '</form></div></div>';
	}

	/**
	 * One result cell.
	 *
	 * @param array<string,mixed> $entry Latest check for a question and engine.
	 * @return string Escaped HTML.
	 */
	private static function status_html( array $entry ): string {
		if ( ! $entry ) {
			return '<span style="color:#8c8f94">' . esc_html__( 'Not checked', 'jat-search-intel' ) . '</span>';
		}
		if ( isset( $entry['error'] ) ) {
			return '<span style="color:#d63638" title="' . esc_attr( (string) $entry['error'] ) . '">' . esc_html__( 'Error', 'jat-search-intel' ) . '</span>';
		}
		if ( ! empty( $entry['cited'] ) ) {
			$link = (string) ( $entry['urls'][0] ?? '' );
			$html = '<span style="color:#00a32a">● ' . esc_html__( 'Cited', 'jat-search-intel' ) . '</span>';
			if ( '' !== $link ) {
				$html .= '<br /><small><a href="' . esc_url( $link ) . '">' . esc_html( (string) wp_make_link_relative( $link ) ) . '</a></small>';
			}
			return $html;
		}
		$label = ! empty( $entry['source'] ) ? __( 'Read, not cited', 'jat-search-intel' ) : __( 'Not cited', 'jat-search-intel' );
		$color = ! empty( $entry['source'] ) ? '#dba617' : '#8c8f94';
		$html  = '<span style="color:' . esc_attr( $color ) . '">○ ' . esc_html( $label ) . '</span>';
		if ( ! empty( $entry['mentioned'] ) ) {
			$html .= '<br /><small>' . esc_html__( 'Named in the answer', 'jat-search-intel' ) . '</small>';
		}
		return $html;
	}
}
