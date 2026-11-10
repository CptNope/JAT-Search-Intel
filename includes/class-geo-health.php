<?php
/**
 * GEO health score: how ready a site is to be found and cited by AI search.
 *
 * The weights follow what research on AI citations shows matters, not what
 * is easy to switch on:
 *
 *   AI search crawlers allowed   20  An engine can't cite a page it can't fetch.
 *   Indexed in Google and Bing   20  AI answers draw on these indexes.
 *   Freshness                    20  Cited pages are typically 2–5 months old,
 *                                    and 40–60% of citations change each month.
 *   Answer-ready content         15  Direct answers and question/answer structure.
 *   Entity and authors           15  Who is behind the content, and where else
 *                                    they appear.
 *   Structured data              10
 *
 * llms.txt and the custom AI meta tags are listed as optional extras with no
 * points: no major AI engine has said it uses them, and server logs show 97%
 * of llms.txt files are never requested.
 *
 * @package JAT_Search_Intel
 */

namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Evidence-based GEO health score.
 */
class GEO_Health {

	/** Posts sampled for the content factors (most recently published). */
	public const SAMPLE_SIZE = 100;

	/** Days after which content no longer counts as fresh, and as half fresh. */
	public const FRESH_DAYS = 180;
	public const STALE_DAYS = 365;

	/** AEO score at which a post counts as answer-ready. */
	public const ANSWER_SCORE = 70;

	/** Index Status results needed before the measured share replaces the estimate. */
	public const MIN_INDEX_CHECKS = 5;

	/** @var array<string,mixed> */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Score the site.
	 *
	 * @return array{score:int, grade:string, factors:array<string,array{label:string,score:int,max:int,detail:string}>}
	 */
	public function calculate(): array {
		return self::score(
			$this->settings,
			self::sample_posts( $this->settings ),
			[
				'public'      => '0' !== (string) get_option( 'blog_public', '1' ),
				'profiles'    => array_filter( (array) get_option( 'jat_search_intel_social_profiles', [] ) ),
				'now'         => time(),
				'author_bios' => self::author_bio_share(),
			]
		);
	}

	/**
	 * Score from already-loaded data (no database access).
	 *
	 * @param array<string,mixed> $settings Plugin settings.
	 * @param array<int,array{id:int,modified:int,author:int}> $posts Sampled posts.
	 * @param array<string,mixed> $context  public (bool), profiles (array), now (int), author_bios (float 0–1).
	 * @return array{score:int, grade:string, factors:array<string,array{label:string,score:int,max:int,detail:string}>}
	 */
	public static function score( array $settings, array $posts, array $context ): array {
		$factors = [
			'crawler_access'  => self::crawler_access( $settings, ! empty( $context['public'] ) ),
			'indexing'        => self::indexing( $settings, $posts ),
			'freshness'       => self::freshness( $posts, (int) ( $context['now'] ?? time() ) ),
			'answers'         => self::answers( $posts ),
			'entity'          => self::entity( $settings, (array) ( $context['profiles'] ?? [] ), (float) ( $context['author_bios'] ?? 0 ) ),
			'structured_data' => self::structured_data( $settings ),
			'llms_txt'        => self::extra(
				__( 'llms.txt (optional)', 'jat-search-intel' ),
				! empty( $settings['enable_llms_txt'] ),
				__( 'No major AI engine has said it reads llms.txt, so it earns no points. It is cheap to keep on.', 'jat-search-intel' )
			),
			'ai_meta'         => self::extra(
				__( 'AI meta tags (optional)', 'jat-search-intel' ),
				! empty( $settings['enable_ai_meta'] ),
				__( 'Tags like ai-summary are not a standard any AI engine has said it reads, so they earn no points.', 'jat-search-intel' )
			),
		];

		$total = 0;
		$max   = 0;
		foreach ( $factors as $factor ) {
			$total += $factor['score'];
			$max   += $factor['max'];
		}
		$pct = $max > 0 ? (int) round( $total / $max * 100 ) : 0;

		return [
			'score'   => $pct,
			'grade'   => self::grade( $pct ),
			'factors' => $factors,
		];
	}

	/**
	 * Can AI search engines fetch the site?
	 *
	 * Blocking training crawlers is a choice and costs nothing here; blocking the
	 * search and user-request crawlers keeps pages out of AI answers.
	 *
	 * @param array<string,mixed> $settings Plugin settings.
	 * @param bool                $public   Whether the site allows search engines.
	 * @return array{label:string,score:int,max:int,detail:string}
	 */
	public static function crawler_access( array $settings, bool $public ): array {
		$label = __( 'AI search crawlers allowed', 'jat-search-intel' );
		if ( ! $public ) {
			return self::factor( $label, 0, 20, __( 'The site asks search engines not to index it (Settings → Reading).', 'jat-search-intel' ) );
		}

		$rules   = (array) ( $settings['ai_crawler_rules'] ?? [] );
		$search  = [];
		$blocked = [];
		foreach ( AI_Search::BOTS as $bot => $info ) {
			if ( in_array( $info[1], [ 'search', 'user' ], true ) ) {
				$search[] = $bot;
				if ( 'block' === ( $rules[ $bot ] ?? '' ) ) {
					$blocked[] = $bot;
				}
			}
		}
		$share = $search ? 1 - count( $blocked ) / count( $search ) : 1;
		$score = (int) round( 20 * $share );

		$signals = AI_Search::signals_from_settings( $settings );
		if ( 'no' === ( $signals['ai-input'] ?? '' ) ) {
			$score = (int) floor( $score / 4 );
			return self::factor( $label, $score, 20, __( 'Content-Signal ai-input=no in robots.txt asks AI engines not to use your pages in answers.', 'jat-search-intel' ) );
		}

		if ( $blocked ) {
			/* translators: %s: list of crawler names. */
			return self::factor( $label, $score, 20, sprintf( __( 'Blocked AI search crawlers: %s.', 'jat-search-intel' ), implode( ', ', $blocked ) ) );
		}
		return self::factor( $label, $score, 20, __( 'All AI search and user-request crawlers can fetch the site.', 'jat-search-intel' ) );
	}

	/**
	 * Is the site in the indexes AI answers draw on?
	 *
	 * @param array<string,mixed> $settings Plugin settings.
	 * @param array<int,array{id:int,modified:int,author:int}> $posts Sampled posts.
	 * @return array{label:string,score:int,max:int,detail:string}
	 */
	public static function indexing( array $settings, array $posts ): array {
		$label = __( 'Indexed in Google and Bing', 'jat-search-intel' );
		$score = 0;
		$notes = [];

		if ( ! empty( $settings['enable_sitemap'] ) ) {
			$score += 5;
		} else {
			$notes[] = __( 'the XML sitemap is off', 'jat-search-intel' );
		}
		if ( ! empty( $settings['enable_indexnow'] ) ) {
			$score += 5;
		} else {
			$notes[] = __( 'IndexNow is off (it tells Bing, which ChatGPT search uses, about new pages)', 'jat-search-intel' );
		}

		$checked = 0;
		$indexed = 0;
		foreach ( $posts as $post ) {
			$verdict = Index_Status::get( (int) $post['id'] )['verdict'] ?? '';
			if ( '' !== $verdict ) {
				++$checked;
				$indexed += 'PASS' === $verdict ? 1 : 0;
			}
		}

		if ( $checked >= self::MIN_INDEX_CHECKS ) {
			$score += (int) round( 10 * $indexed / $checked );
			/* translators: 1: pages indexed, 2: pages checked. */
			$detail = sprintf( __( 'Google has indexed %1$d of %2$d checked pages.', 'jat-search-intel' ), $indexed, $checked );
		} else {
			// Not measured yet: neither a pass nor a fail.
			$score += 5;
			$detail = __( 'Index status not measured yet: turn on Index Status to check it.', 'jat-search-intel' );
		}

		if ( $notes ) {
			/* translators: %s: list of problems. */
			$detail .= ' ' . sprintf( __( 'Also: %s.', 'jat-search-intel' ), implode( '; ', $notes ) );
		}
		return self::factor( $label, $score, 20, $detail );
	}

	/**
	 * Share of recent content updated within six months (half credit within a year).
	 *
	 * @param array<int,array{id:int,modified:int,author:int}> $posts Sampled posts.
	 * @param int $now Current timestamp.
	 * @return array{label:string,score:int,max:int,detail:string}
	 */
	public static function freshness( array $posts, int $now ): array {
		$label = __( 'Freshness', 'jat-search-intel' );
		if ( ! $posts ) {
			return self::factor( $label, 0, 20, __( 'No published content yet.', 'jat-search-intel' ) );
		}
		$credit = 0.0;
		$fresh  = 0;
		foreach ( $posts as $post ) {
			$age = ( $now - (int) $post['modified'] ) / DAY_IN_SECONDS;
			if ( $age <= self::FRESH_DAYS ) {
				$credit += 1;
				++$fresh;
			} elseif ( $age <= self::STALE_DAYS ) {
				$credit += 0.5;
			}
		}
		$pct = (int) round( $fresh / count( $posts ) * 100 );
		/* translators: %d: percentage. */
		return self::factor( $label, (int) round( 20 * $credit / count( $posts ) ), 20, sprintf( __( '%d%% of recent content was updated in the last 6 months.', 'jat-search-intel' ), $pct ) );
	}

	/**
	 * Share of recent content with a direct answer, FAQs or a good AEO score.
	 *
	 * @param array<int,array{id:int,modified:int,author:int}> $posts Sampled posts.
	 * @return array{label:string,score:int,max:int,detail:string}
	 */
	public static function answers( array $posts ): array {
		$label = __( 'Answer-ready content', 'jat-search-intel' );
		if ( ! $posts ) {
			return self::factor( $label, 0, 15, __( 'No published content yet.', 'jat-search-intel' ) );
		}
		$ready = 0;
		foreach ( $posts as $post ) {
			$id = (int) $post['id'];
			if ( '' !== trim( (string) get_post_meta( $id, '_jat_search_intel_direct_answer', true ) )
				|| ! empty( get_post_meta( $id, '_jat_search_intel_faqs', true ) )
				|| (int) get_post_meta( $id, '_jat_search_intel_aeo_score', true ) >= self::ANSWER_SCORE ) {
				++$ready;
			}
		}
		$pct = (int) round( $ready / count( $posts ) * 100 );
		/* translators: %d: percentage. */
		return self::factor( $label, (int) round( 15 * $ready / count( $posts ) ), 15, sprintf( __( '%d%% of recent content has a direct answer, FAQs or an AEO score of 70+.', 'jat-search-intel' ), $pct ) );
	}

	/**
	 * Who is behind the site, and how to recognise them elsewhere.
	 *
	 * @param array<string,mixed> $settings    Plugin settings.
	 * @param array<mixed>        $profiles    Social profile URLs (sameAs).
	 * @param float               $author_bios Share of recent authors with a biography (0–1).
	 * @return array{label:string,score:int,max:int,detail:string}
	 */
	public static function entity( array $settings, array $profiles, float $author_bios ): array {
		$label   = __( 'Entity and authors', 'jat-search-intel' );
		$score   = 0;
		$missing = [];
		if ( '' !== trim( (string) ( $settings['knowledge_name'] ?? '' ) ) ) {
			$score += 4;
		} else {
			$missing[] = __( 'organization or person name', 'jat-search-intel' );
		}
		if ( '' !== trim( (string) ( $settings['knowledge_logo'] ?? '' ) ) ) {
			$score += 3;
		} else {
			$missing[] = __( 'logo', 'jat-search-intel' );
		}
		if ( $profiles ) {
			$score += 4;
		} else {
			$missing[] = __( 'social profile links (sameAs)', 'jat-search-intel' );
		}
		$score += (int) round( 4 * max( 0.0, min( 1.0, $author_bios ) ) );
		if ( $author_bios < 1 ) {
			$missing[] = __( 'author biographies', 'jat-search-intel' );
		}

		$detail = $missing
			/* translators: %s: list of missing details. */
			? sprintf( __( 'Missing: %s.', 'jat-search-intel' ), implode( ', ', $missing ) )
			: __( 'Name, logo, profiles and author biographies are all set.', 'jat-search-intel' );
		return self::factor( $label, $score, 15, $detail );
	}

	/**
	 * Structured data output.
	 *
	 * @param array<string,mixed> $settings Plugin settings.
	 * @return array{label:string,score:int,max:int,detail:string}
	 */
	public static function structured_data( array $settings ): array {
		$label = __( 'Structured data', 'jat-search-intel' );
		if ( empty( $settings['enable_schema'] ) ) {
			return self::factor( $label, 0, 10, __( 'Schema output is off.', 'jat-search-intel' ) );
		}
		$extra = ! empty( $settings['enable_schema_types'] );
		return self::factor(
			$label,
			$extra ? 10 : 7,
			10,
			$extra ? __( 'Schema graph and extra content types are on.', 'jat-search-intel' ) : __( 'Schema graph is on; extra content types (Product, Event, Recipe…) are off.', 'jat-search-intel' )
		);
	}

	/**
	 * Recommendation for a factor that scored low.
	 *
	 * @param string $key Factor key.
	 * @return string
	 */
	public static function recommendation( string $key ): string {
		$messages = [
			'crawler_access'  => __( 'Let AI search crawlers (OAI-SearchBot, Claude-SearchBot, PerplexityBot and the user-request bots) fetch the site under GEO → AI Crawlers. You can still block training crawlers.', 'jat-search-intel' ),
			'indexing'        => __( 'Keep the sitemap on, turn on IndexNow, and turn on Index Status to see which pages Google has indexed.', 'jat-search-intel' ),
			'freshness'       => __( 'Update your most important older pages; see the Refresh Queue for where to start.', 'jat-search-intel' ),
			'answers'         => __( 'Add a direct answer or FAQs to your key pages so AI engines can quote them.', 'jat-search-intel' ),
			'entity'          => __( 'Fill in the organization or person name, logo and social profiles, and add author biographies under Users.', 'jat-search-intel' ),
			'structured_data' => __( 'Turn on schema output, and the extra content types if you publish products, events or recipes.', 'jat-search-intel' ),
		];
		return $messages[ $key ] ?? '';
	}

	/**
	 * Letter grade.
	 *
	 * @param int $pct Score 0–100.
	 * @return string
	 */
	public static function grade( int $pct ): string {
		if ( $pct >= 90 ) {
			return 'A';
		}
		if ( $pct >= 80 ) {
			return 'B';
		}
		if ( $pct >= 70 ) {
			return 'C';
		}
		if ( $pct >= 60 ) {
			return 'D';
		}
		return 'F';
	}

	/**
	 * Most recently published content of the enabled post types.
	 * Two queries: the posts, and their meta (primed for the checks above).
	 *
	 * @param array<string,mixed> $settings Plugin settings.
	 * @return array<int,array{id:int,modified:int,author:int}>
	 */
	public static function sample_posts( array $settings ): array {
		$types = array_values( array_filter( (array) ( $settings['enabled_post_types'] ?? [ 'post', 'page' ] ), 'post_type_exists' ) );
		if ( ! $types ) {
			return [];
		}
		$posts = get_posts(
			[
				'post_type'        => $types,
				'post_status'      => 'publish',
				'numberposts'      => self::SAMPLE_SIZE,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'has_password'     => false,
				'no_found_rows'    => true,
				'suppress_filters' => true,
			]
		);
		$out = [];
		foreach ( $posts as $post ) {
			if ( ! is_object( $post ) ) {
				continue;
			}
			$out[] = [
				'id'       => (int) $post->ID,
				'modified' => (int) strtotime( (string) $post->post_modified_gmt . ' UTC' ),
				'author'   => (int) $post->post_author,
			];
		}
		return $out;
	}

	/**
	 * Share of the site's recent authors who have a biography.
	 *
	 * @return float 0–1.
	 */
	private static function author_bio_share(): float {
		$users = get_users(
			[
				'has_published_posts' => true,
				'number'              => 50,
				'fields'              => [ 'ID' ],
			]
		);
		if ( ! $users ) {
			return 0.0;
		}
		$with = 0;
		foreach ( $users as $user ) {
			if ( '' !== trim( (string) get_the_author_meta( 'description', (int) $user->ID ) ) ) {
				++$with;
			}
		}
		return $with / count( $users );
	}

	/**
	 * Factor array.
	 *
	 * @param string $label  Label.
	 * @param int    $score  Points.
	 * @param int    $max    Maximum points.
	 * @param string $detail Explanation.
	 * @return array{label:string,score:int,max:int,detail:string}
	 */
	private static function factor( string $label, int $score, int $max, string $detail ): array {
		return [
			'label'  => $label,
			'score'  => max( 0, min( $max, $score ) ),
			'max'    => $max,
			'detail' => $detail,
		];
	}

	/**
	 * An optional extra that earns no points.
	 *
	 * @param string $label  Label.
	 * @param bool   $on     Whether it is switched on.
	 * @param string $detail Explanation.
	 * @return array{label:string,score:int,max:int,detail:string}
	 */
	private static function extra( string $label, bool $on, string $detail ): array {
		return self::factor( $label, 0, 0, ( $on ? __( 'On.', 'jat-search-intel' ) : __( 'Off.', 'jat-search-intel' ) ) . ' ' . $detail );
	}
}
