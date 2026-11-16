<?php
/**
 * Headline analyzer: scores a post headline for clicks and clarity.
 *
 * Checks length, word count, the balance of common, uncommon, emotional and
 * power words, headline type (list, how-to, question), numbers and how the
 * headline starts and ends. Runs locally with no outside service.
 *
 * @package JAT_Search_Intel
 * @since 3.8.0
 */

namespace JAT_Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Headline scoring.
 */
class Headline_Analyzer {

	/** Very frequent words that make headlines readable. */
	private const COMMON = [ 'a', 'about', 'after', 'all', 'an', 'and', 'any', 'are', 'as', 'at', 'be', 'because', 'before', 'best', 'but', 'by', 'can', 'do', 'does', 'for', 'from', 'get', 'go', 'good', 'great', 'has', 'have', 'how', 'i', 'if', 'in', 'into', 'is', 'it', 'its', 'just', 'know', 'like', 'make', 'more', 'most', 'my', 'need', 'new', 'no', 'not', 'now', 'of', 'on', 'one', 'or', 'our', 'out', 'over', 'should', 'so', 'than', 'that', 'the', 'their', 'them', 'these', 'they', 'things', 'this', 'time', 'to', 'up', 'use', 'want', 'way', 'ways', 'we', 'what', 'when', 'where', 'which', 'who', 'why', 'will', 'with', 'without', 'you', 'your' ];

	/** Words that add emotion and curiosity. */
	private const EMOTIONAL = [ 'amazing', 'awful', 'beautiful', 'brilliant', 'crazy', 'critical', 'dangerous', 'disaster', 'easy', 'effortless', 'essential', 'fail', 'fear', 'free', 'genius', 'hate', 'hidden', 'honest', 'horrible', 'huge', 'incredible', 'insane', 'love', 'mistake', 'mistakes', 'painful', 'perfect', 'powerful', 'proven', 'risk', 'safe', 'scam', 'scary', 'secret', 'secrets', 'shocking', 'simple', 'stop', 'stunning', 'surprising', 'terrible', 'threat', 'truth', 'ultimate', 'unbelievable', 'urgent', 'warning', 'weird', 'worst', 'wrong' ];

	/** Words that trigger action. */
	private const POWER = [ 'avoid', 'boost', 'build', 'check', 'complete', 'discover', 'double', 'exclusive', 'fast', 'fix', 'guaranteed', 'guide', 'hack', 'improve', 'instant', 'launch', 'learn', 'master', 'never', 'protect', 'quick', 'quickly', 'report', 'save', 'skyrocket', 'spot', 'step', 'steps', 'today', 'tips', 'transform', 'tricks', 'unlock', 'win' ];

	/** Words that make weak first or last words. */
	private const WEAK_EDGE = [ 'a', 'an', 'and', 'are', 'as', 'at', 'be', 'but', 'by', 'for', 'from', 'in', 'is', 'it', 'of', 'on', 'or', 'so', 'the', 'to', 'with' ];

	/**
	 * Analyze a headline.
	 *
	 * @param string $headline The headline.
	 * @return array{score:int,type:string,characters:int,words:int,checks:array<int,array{id:string,status:string,message:string}>,stats:array<string,int>}
	 */
	public static function analyze( string $headline ): array {
		$headline = trim( wp_strip_all_tags( html_entity_decode( $headline, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
		$chars    = function_exists( 'mb_strlen' ) ? mb_strlen( $headline ) : strlen( $headline );
		$words    = self::words( $headline );
		$count    = count( $words );

		$common    = 0;
		$emotional = 0;
		$power     = 0;
		foreach ( $words as $word ) {
			if ( in_array( $word, self::COMMON, true ) ) {
				$common++;
			}
			if ( in_array( $word, self::EMOTIONAL, true ) ) {
				$emotional++;
			}
			if ( in_array( $word, self::POWER, true ) ) {
				$power++;
			}
		}
		$uncommon = max( 0, $count - $common - $emotional - $power );

		$checks = [];
		$score  = 0;

		// Length in characters (Google shows about 50–60).
		if ( $chars >= 40 && $chars <= 60 ) {
			$score   += 20;
			$checks[] = self::check( 'length', 'good', sprintf( /* translators: %d: characters */ __( '%d characters: a good length for search results.', 'jat-search-intel' ), $chars ) );
		} elseif ( $chars >= 30 && $chars <= 70 ) {
			$score   += 12;
			$checks[] = self::check( 'length', 'ok', sprintf( /* translators: %d: characters */ __( '%d characters. Between 40 and 60 is ideal so the whole headline shows in search results.', 'jat-search-intel' ), $chars ) );
		} else {
			$score   += $chars > 0 ? 4 : 0;
			$checks[] = self::check( 'length', 'improve', $chars > 70
				? sprintf( /* translators: %d: characters */ __( '%d characters: search results will cut it off. Aim for 40 to 60.', 'jat-search-intel' ), $chars )
            : sprintf( /* translators: %d: characters */ __( '%d characters: too short to say much. Aim for 40 to 60.', 'jat-search-intel' ), $chars ) );
		}

		// Word count.
		if ( $count >= 6 && $count <= 12 ) {
			$score   += 15;
			$checks[] = self::check( 'words', 'good', sprintf( /* translators: %d: words */ __( '%d words: easy to scan.', 'jat-search-intel' ), $count ) );
		} elseif ( $count >= 4 && $count <= 14 ) {
			$score   += 9;
			$checks[] = self::check( 'words', 'ok', sprintf( /* translators: %d: words */ __( '%d words. Headlines of 6 to 12 words tend to get the most clicks.', 'jat-search-intel' ), $count ) );
		} else {
			$score   += $count > 0 ? 3 : 0;
			$checks[] = self::check( 'words', 'improve', sprintf( /* translators: %d: words */ __( '%d words. Aim for 6 to 12.', 'jat-search-intel' ), $count ) );
		}

		$pct = static function ( int $n ) use ( $count ): int {
			return $count > 0 ? (int) round( 100 * $n / $count ) : 0;
		};

		// Common words keep it readable.
		$common_pct = $pct( $common );
		if ( $common_pct >= 20 && $common_pct <= 40 ) {
			$score   += 10;
			$checks[] = self::check( 'common', 'good', __( 'A good share of everyday words keeps it easy to read.', 'jat-search-intel' ) );
		} else {
			$score   += 4;
			$checks[] = self::check( 'common', 'improve', $common_pct < 20
				? __( 'Add a few everyday words (how, you, your, why) so it reads naturally.', 'jat-search-intel' )
            : __( 'Too many filler words. Swap some for specific ones.', 'jat-search-intel' ) );
		}

		// Uncommon words add specifics.
		$uncommon_pct = $pct( $uncommon );
		if ( $uncommon_pct >= 10 && $uncommon_pct <= 60 ) {
			$score   += 10;
			$checks[] = self::check( 'uncommon', 'good', __( 'Specific words tell readers exactly what they will get.', 'jat-search-intel' ) );
		} else {
			$score   += 4;
			$checks[] = self::check( 'uncommon', 'improve', __( 'Name the specific thing the post covers (a tool, place, number or result).', 'jat-search-intel' ) );
		}

		// Emotional words.
		if ( $emotional > 0 ) {
			$score   += 10;
			$checks[] = self::check( 'emotional', 'good', __( 'It has an emotional word, which tends to lift clicks.', 'jat-search-intel' ) );
		} else {
			$checks[] = self::check( 'emotional', 'improve', __( 'Consider one emotional word (easy, proven, mistake, warning) if it fits honestly.', 'jat-search-intel' ) );
		}

		// Power words.
		if ( $power > 0 ) {
			$score   += 10;
			$checks[] = self::check( 'power', 'good', __( 'It has an action word that tells readers what they can do.', 'jat-search-intel' ) );
		} else {
			$checks[] = self::check( 'power', 'improve', __( 'Add an action word (fix, check, learn, protect, build) to show the benefit.', 'jat-search-intel' ) );
		}

		// Headline type.
		$type = self::type( $headline, $words );
		if ( 'general' !== $type ) {
			$score   += 10;
			$checks[] = self::check( 'type', 'good', sprintf( /* translators: %s: headline type */ __( 'Recognisable %s headline, a format readers trust.', 'jat-search-intel' ), self::type_label( $type ) ) );
		} else {
			$score   += 4;
			$checks[] = self::check( 'type', 'improve', __( 'List, how-to and question headlines usually earn more clicks.', 'jat-search-intel' ) );
		}

		// Numbers.
		if ( preg_match( '/\d/', $headline ) ) {
			$score   += 5;
			$checks[] = self::check( 'number', 'good', __( 'A number makes the promise concrete.', 'jat-search-intel' ) );
		} else {
			$checks[] = self::check( 'number', 'ok', __( 'A number (steps, signs, year) can make it more specific.', 'jat-search-intel' ) );
		}

		// Strong first and last words.
		$first = $words[0] ?? '';
		$last  = $count ? $words[ $count - 1 ] : '';
		if ( $count && ! in_array( $first, self::WEAK_EDGE, true ) && ! in_array( $last, self::WEAK_EDGE, true ) ) {
			$score   += 10;
			$checks[] = self::check( 'edges', 'good', __( 'It starts and ends on strong words, which readers notice most.', 'jat-search-intel' ) );
		} else {
			$score   += $count ? 4 : 0;
			$checks[] = self::check( 'edges', 'improve', __( 'Readers notice the first and last three words most. Avoid starting or ending on a small word like "the" or "of".', 'jat-search-intel' ) );
		}

		return [
			'score'      => min( 100, $score ),
			'type'       => $type,
			'characters' => $chars,
			'words'      => $count,
			'checks'     => $checks,
			'stats'      => [
				'common'    => $common_pct,
				'uncommon'  => $uncommon_pct,
				'emotional' => $pct( $emotional ),
				'power'     => $pct( $power ),
			],
		];
	}

	/**
	 * Lower-case words in a headline.
	 *
	 * @param string $headline Headline.
	 * @return string[]
	 */
	private static function words( string $headline ): array {
		$lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $headline ) : strtolower( $headline );
		preg_match_all( "/[\\p{L}\\p{N}][\\p{L}\\p{N}'’.-]*/u", $lower, $m );
		return array_map(
			static function ( $w ) {
				return trim( str_replace( '’', "'", $w ), ".-'" );
			},
			$m[0]
		);
	}

	/**
	 * Detect the headline type.
	 *
	 * @param string   $headline Headline.
	 * @param string[] $words    Lower-case words.
	 * @return string list|how-to|question|guide|general
	 */
	private static function type( string $headline, array $words ): string {
		if ( preg_match( '/\?\s*$/', $headline ) || in_array( $words[0] ?? '', [ 'what', 'why', 'when', 'where', 'who', 'which', 'is', 'are', 'can', 'should', 'does', 'do' ], true ) ) {
			return 'question';
		}
		if ( 'how' === ( $words[0] ?? '' ) && 'to' === ( $words[1] ?? '' ) || preg_match( '/\bhow to\b/i', $headline ) ) {
			return 'how-to';
		}
		if ( preg_match( '/^\s*\d+\s/', $headline ) || preg_match( '/\(\d+\s+\w+\)/', $headline ) ) {
			return 'list';
		}
		if ( preg_match( '/\b(guide|checklist|tutorial|explained)\b/i', $headline ) ) {
			return 'guide';
		}
		return 'general';
	}

	/**
	 * Human label for a type.
	 *
	 * @param string $type Type.
	 * @return string
	 */
	private static function type_label( string $type ): string {
		$labels = [
			'question' => __( 'question', 'jat-search-intel' ),
			'how-to'   => __( 'how-to', 'jat-search-intel' ),
			'list'     => __( 'list', 'jat-search-intel' ),
			'guide'    => __( 'guide', 'jat-search-intel' ),
		];
		return $labels[ $type ] ?? $type;
	}

	/**
	 * Build a check row.
	 *
	 * @param string $id      Check ID.
	 * @param string $status  good|ok|improve.
	 * @param string $message Message.
	 * @return array{id:string,status:string,message:string}
	 */
	private static function check( string $id, string $status, string $message ): array {
		return [
			'id'      => $id,
			'status'  => $status,
			'message' => $message,
		];
	}
}
