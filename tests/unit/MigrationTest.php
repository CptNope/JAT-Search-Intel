<?php
namespace JAT_Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\Migration;

class MigrationTest extends TestCase {

	private Migration $migration;

	protected function setUp(): void {
		global $si_test_state;
		$si_test_state = [
			'options'    => [],
			'post_meta'  => [],
			'posts'      => [],
			'transients' => [],
			'actions'    => [],
		];

		$this->migration = new Migration( [] );
	}

	// ── Detection ────────────────────────────────────────────────────────────

	public function test_detect_sources_empty_when_no_data(): void {
		$this->assertEmpty( $this->migration->detect_sources() );
	}

	public function test_detect_sources_finds_yoast(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Post', 'post_content' => 'Content.',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];
		$si_test_state['post_meta'][1]['_yoast_wpseo_title'] = 'Yoast Title';

		$detected = $this->migration->detect_sources();
		$this->assertArrayHasKey( 'yoast', $detected );
		$this->assertEquals( 1, $detected['yoast']['posts'] );
		$this->assertEquals( 'Yoast SEO', $detected['yoast']['label'] );
	}

	public function test_detect_sources_finds_rankmath(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Post', 'post_content' => 'Content.',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];
		$si_test_state['post_meta'][1]['rank_math_title'] = 'RM Title';

		$detected = $this->migration->detect_sources();
		$this->assertArrayHasKey( 'rankmath', $detected );
	}

	// ── Dry Run ──────────────────────────────────────────────────────────────

	public function test_dry_run_unknown_source(): void {
		$result = $this->migration->dry_run( 'unknown' );
		$this->assertArrayHasKey( 'error', $result );
	}

	public function test_dry_run_yoast_returns_preview(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'My Post', 'post_content' => 'Content.',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];
		$si_test_state['post_meta'][1]['_yoast_wpseo_title'] = 'Yoast Title';
		$si_test_state['post_meta'][1]['_yoast_wpseo_metadesc'] = 'Yoast desc';

		$result = $this->migration->dry_run( 'yoast' );

		$this->assertEquals( 'Yoast SEO', $result['source'] );
		$this->assertEquals( 1, $result['post_count'] );
		$this->assertArrayHasKey( 'title', $result['fields'] );
		$this->assertArrayHasKey( 'description', $result['fields'] );
		$this->assertEquals( 1, $result['fields']['title']['count'] );
	}

	public function test_dry_run_has_samples(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Post', 'post_content' => 'Content.',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];
		$si_test_state['post_meta'][1]['_yoast_wpseo_title'] = 'Title';

		$result = $this->migration->dry_run( 'yoast' );
		$this->assertNotEmpty( $result['fields']['title']['samples'] );
		$this->assertEquals( 1, $result['fields']['title']['samples'][0]['post_id'] );
	}

	// ── Migration ────────────────────────────────────────────────────────────

	public function test_migrate_unknown_source(): void {
		$result = $this->migration->migrate( 'unknown' );
		$this->assertArrayHasKey( 'error', $result );
	}

	public function test_migrate_yoast_title_and_description(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Post', 'post_content' => 'Content.',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];
		$si_test_state['post_meta'][1]['_yoast_wpseo_title'] = 'Yoast Title';
		$si_test_state['post_meta'][1]['_yoast_wpseo_metadesc'] = 'Yoast description';

		$result = $this->migration->migrate( 'yoast' );

		$this->assertEquals( 1, $result['migrated'] );
		$this->assertEquals( 'Yoast Title', $si_test_state['post_meta'][1]['_jat_search_intel_title'] );
		$this->assertEquals( 'Yoast description', $si_test_state['post_meta'][1]['_jat_search_intel_description'] );
	}

	public function test_migrate_covers_the_post_types_that_had_data(): void {
		global $si_test_state;
		$si_test_state['options']['jat_search_intel_settings'] = [ 'sitemap_post_types' => [ 'post' ], 'enabled_post_types' => [ 'post' ] ];
		$si_test_state['posts'][3] = [
			'ID' => 3, 'post_title' => 'About', 'post_content' => 'Content.',
			'post_status' => 'publish', 'post_type' => 'page', 'post_author' => 1,
		];
		$si_test_state['post_meta'][3]['_yoast_wpseo_title'] = 'About title';

		$result   = $this->migration->migrate( 'yoast' );
		$settings = $si_test_state['options']['jat_search_intel_settings'];

		$this->assertSame( [ 'page' ], $result['post_types'] );
		$this->assertSame( [ 'post', 'page' ], $settings['sitemap_post_types'] );
		$this->assertSame( [ 'post', 'page' ], $settings['enabled_post_types'] );
	}

	public function test_migrate_does_not_overwrite_by_default(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Post', 'post_content' => 'Content.',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];
		$si_test_state['post_meta'][1]['_yoast_wpseo_title'] = 'Yoast Title';
		$si_test_state['post_meta'][1]['_jat_search_intel_title'] = 'Existing Title';

		$result = $this->migration->migrate( 'yoast', false );

		$this->assertEquals( 0, $result['migrated'] );
		$this->assertEquals( 1, $result['skipped'] );
		$this->assertEquals( 'Existing Title', $si_test_state['post_meta'][1]['_jat_search_intel_title'] );
	}

	public function test_migrate_overwrite_replaces_existing(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Post', 'post_content' => 'Content.',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];
		$si_test_state['post_meta'][1]['_yoast_wpseo_title'] = 'Yoast Title';
		$si_test_state['post_meta'][1]['_jat_search_intel_title'] = 'Existing Title';

		$result = $this->migration->migrate( 'yoast', true );

		$this->assertEquals( 1, $result['migrated'] );
		$this->assertEquals( 'Yoast Title', $si_test_state['post_meta'][1]['_jat_search_intel_title'] );
	}

	public function test_migrate_canonical_sanitized_as_url(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Post', 'post_content' => 'Content.',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];
		$si_test_state['post_meta'][1]['_yoast_wpseo_canonical'] = 'https://example.com/page';

		$this->migration->migrate( 'yoast' );

		$this->assertEquals( 'https://example.com/page', $si_test_state['post_meta'][1]['_jat_search_intel_canonical'] );
	}

	public function test_migrate_multiple_sources(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Post', 'post_content' => 'Content.',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];
		$si_test_state['post_meta'][1]['rank_math_title'] = 'RM Title';
		$si_test_state['post_meta'][1]['rank_math_description'] = 'RM Desc';

		$result = $this->migration->migrate( 'rankmath' );
		$this->assertEquals( 1, $result['migrated'] );
		$this->assertEquals( 'RM Title', $si_test_state['post_meta'][1]['_jat_search_intel_title'] );
	}

	// ── Redirect Migration ───────────────────────────────────────────────────

	public function test_migrate_yoast_redirects(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Post', 'post_content' => 'Content.',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];
		$si_test_state['post_meta'][1]['_yoast_wpseo_title'] = 'Title';
		$si_test_state['options']['wpseo-premium-redirects-base'] = [
			[ 'origin' => 'old-page', 'url' => '/new-page', 'type' => 301 ],
		];

		$store     = new FakeRedirectStore();
		$migration = new TestableMigration( [], $store );
		$result    = $migration->migrate( 'yoast' );

		$this->assertEquals( 1, $result['redirects'] );
		$this->assertCount( 1, $store->saved );
		$this->assertEquals( '/old-page', $store->saved[0]['source_url'] );
		$this->assertEquals( '/new-page', $store->saved[0]['target_url'] );
		$this->assertEquals( 301, $store->saved[0]['status_code'] );
	}

	public function test_redirects_already_present_are_skipped(): void {
		global $si_test_state;
		$si_test_state['options']['wpseo-premium-redirects-base'] = [
			[ 'origin' => '/old-page', 'url' => '/new-page', 'type' => 301 ],
		];
		$store            = new FakeRedirectStore();
		$store->existing  = [ (object) [ 'source_url' => '/old-page' ] ];
		$migration        = new TestableMigration( [], $store );

		$this->assertEquals( 0, $migration->migrate( 'yoast' )['redirects'] );
	}

	// ── Robots ───────────────────────────────────────────────────────────────

	public function test_yoast_noindex_maps_to_noindex_meta(): void {
		global $si_test_state;
		foreach ( [ 1 => '1', 2 => '2' ] as $id => $flag ) {
			$si_test_state['posts'][ $id ] = [ 'ID' => $id, 'post_title' => 'P', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1 ];
			$si_test_state['post_meta'][ $id ]['_yoast_wpseo_meta-robots-noindex'] = $flag;
			$si_test_state['post_meta'][ $id ]['_yoast_wpseo_title'] = 'T';
		}

		$this->migration->migrate( 'yoast' );

		$this->assertEquals( '1', $si_test_state['post_meta'][1]['_jat_search_intel_noindex'] );
		$this->assertArrayNotHasKey( '_jat_search_intel_noindex', $si_test_state['post_meta'][2] );
	}

	public function test_rankmath_robots_array_maps_to_flags(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [ 'ID' => 1, 'post_title' => 'P', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1 ];
		$si_test_state['post_meta'][1]['rank_math_robots'] = [ 'noindex', 'nofollow' ];
		$si_test_state['post_meta'][1]['rank_math_focus_keyword'] = 'main phrase, second phrase';

		$this->migration->migrate( 'rankmath' );

		$this->assertEquals( '1', $si_test_state['post_meta'][1]['_jat_search_intel_noindex'] );
		$this->assertEquals( '1', $si_test_state['post_meta'][1]['_jat_search_intel_nofollow'] );
		$this->assertEquals( 'main phrase', $si_test_state['post_meta'][1]['_jat_search_intel_focus_keyword'] );
		$this->assertEquals( 'second phrase', $si_test_state['post_meta'][1]['_jat_search_intel_secondary_keywords'] );
	}

	// ── Variables ────────────────────────────────────────────────────────────

	/**
	 * @dataProvider variable_cases
	 */
	public function test_convert_variables( string $source, string $in, string $out ): void {
		$this->assertSame( $out, Migration::convert_variables( $in, $source ) );
	}

	public function variable_cases(): array {
		return [
			'aioseo site title'   => [ 'aioseo', 'Free PHP2Shell Scanner #separator_sa #site_title', 'Free PHP2Shell Scanner %sep% %sitename%' ],
			'aioseo post title'   => [ 'aioseo', '#post_title #separator_sa #site_title', '%title% %sep% %sitename%' ],
			'aioseo unknown tag'  => [ 'aioseo', '#post_title #custom_field-price #separator_sa #site_title', '%title% %sep% %sitename%' ],
			'aioseo date'         => [ 'aioseo', 'Updated #post_date', 'Updated %date%' ],
			'aioseo excerpt only' => [ 'aioseo', '#post_excerpt_only', '%excerpt%' ],
			'aioseo entity'       => [ 'aioseo', 'Tips &amp; tricks', 'Tips & tricks' ],
			'aioseo hashtag kept' => [ 'aioseo', 'Plain title with no tags', 'Plain title with no tags' ],
			'yoast'               => [ 'yoast', '%%title%% %%sep%% %%sitename%%', '%title% %sep% %sitename%' ],
			'yoast unknown'       => [ 'yoast', '%%title%% %%sep%% %%cf_price%% %%sep%% %%sitename%%', '%title% %sep% %sitename%' ],
			'rankmath'            => [ 'rankmath', '%title% %sep% %sitedesc%', '%title% %sep% %tagline%' ],
			'seopress'            => [ 'seopress', '%%post_title%% %%sep%% %%sitetitle%%', '%title% %sep% %sitename%' ],
			'dangling separator'  => [ 'yoast', '%%title%% %%sep%% %%unknown%%', '%title%' ],
		];
	}

	// ── All in One SEO tables ────────────────────────────────────────────────

	public function test_aioseo_row_mapping(): void {
		$row = [
			'post_id'                  => 5,
			'title'                    => 'Free PHP2Shell Scanner #separator_sa #site_title',
			'description'              => 'Check your site.',
			'focus_keyword'            => 'PHP2Shell',
			'keyphrases'               => '{"focus":{"keyphrase":"old phrase"},"additional":[{"keyphrase":"wp2shell"}]}',
			'canonical_url'            => 'https://example.com/canonical/',
			'robots_default'           => 0,
			'robots_noindex'           => 1,
			'robots_nofollow'          => 0,
			'og_title'                 => 'OG title',
			'og_image_custom_url'      => 'https://example.com/og.png',
			'twitter_use_og'           => 1,
			'twitter_image_custom_url' => 'https://example.com/ignored.png',
			'primary_term'             => '{"category":12}',
			'schema'                   => '{"customGraphs":[{"schema":"{\"@context\":\"https://schema.org\",\"@type\":\"Event\",\"name\":\"Launch\"}"}]}',
		];

		$fields = $this->migration->map_aioseo_post_row( $row );

		$this->assertSame( 'PHP2Shell', $fields['keyword'], 'AIOSEO 5 focus_keyword column wins over the old JSON' );
		$this->assertSame( 'wp2shell', $fields['secondary_keywords'] );
		$this->assertSame( '1', $fields['noindex'] );
		$this->assertArrayNotHasKey( 'nofollow', $fields );
		$this->assertSame( 'https://example.com/og.png', $fields['social_image'] );
		$this->assertArrayNotHasKey( 'twitter_image', $fields, 'Twitter uses the OG image when twitter_use_og is on' );
		$this->assertSame( '12', $fields['primary_category'] );
		$this->assertStringContainsString( '"Event"', $fields['custom_schema'] );
	}

	public function test_aioseo_robots_default_means_no_override(): void {
		$fields = $this->migration->map_aioseo_post_row( [ 'post_id' => 1, 'robots_default' => 1, 'robots_noindex' => 1 ] );
		$this->assertArrayNotHasKey( 'noindex', $fields );
	}

	public function test_aioseo_keyphrase_from_json_when_no_column(): void {
		$fields = $this->migration->map_aioseo_post_row( [ 'post_id' => 1, 'keyphrases' => '{"focus":{"keyphrase":"tRPC"},"additional":[]}' ] );
		$this->assertSame( 'tRPC', $fields['keyword'] );
	}

	public function test_migrate_aioseo_from_tables(): void {
		global $si_test_state;
		$si_test_state['posts'][5] = [ 'ID' => 5, 'post_title' => 'P', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1 ];
		$migration = new TestableMigration( [], new FakeRedirectStore(), [
			'aioseo_posts'     => [ [ 'id' => 1, 'post_id' => 5, 'title' => 'Scanner #separator_sa #site_title', 'description' => 'Desc', 'focus_keyword' => 'PHP2Shell' ] ],
			'aioseo_terms'     => [ [ 'id' => 1, 'term_id' => 3, 'title' => '#taxonomy_title #separator_sa #site_title', 'robots_default' => 0, 'robots_noindex' => 1 ] ],
			'aioseo_redirects' => [ [ 'id' => 1, 'source_url' => '/old/', 'target_url' => '/new/', 'type' => 301, 'regex' => 0, 'enabled' => 1 ] ],
		] );

		$detected = $migration->detect_sources();
		$this->assertSame( 1, $detected['aioseo']['posts'] );

		$result = $migration->migrate( 'aioseo' );

		$this->assertSame( 1, $result['migrated'] );
		$this->assertSame( 'Scanner %sep% %sitename%', $si_test_state['post_meta'][5]['_jat_search_intel_title'] );
		$this->assertSame( 'PHP2Shell', $si_test_state['post_meta'][5]['_jat_search_intel_focus_keyword'] );
		$this->assertSame( 1, $result['terms'] );
		$this->assertSame( '%category% %sep% %sitename%', get_term_meta( 3, '_jat_search_intel_title', true ) );
		$this->assertSame( '1', get_term_meta( 3, '_jat_search_intel_noindex', true ) );
		$this->assertSame( 1, $result['redirects'] );
	}

	public function test_variables_survive_sanitizing(): void {
		global $si_test_state;
		$si_test_state['posts'][6] = [ 'ID' => 6, 'post_title' => 'P', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1 ];
		$migration = new TestableMigration( [], new FakeRedirectStore(), [
			'aioseo_posts' => [ [ 'id' => 1, 'post_id' => 6, 'title' => '#category_title #separator_sa #post_date #separator_sa #site_title' ] ],
		] );

		$migration->migrate( 'aioseo' );

		$this->assertSame( '%category% %sep% %date% %sep% %sitename%', $si_test_state['post_meta'][6]['_jat_search_intel_title'] );
	}

	public function test_existing_values_kept_without_overwrite(): void {
		global $si_test_state;
		$si_test_state['posts'][5] = [ 'ID' => 5, 'post_title' => 'P', 'post_content' => '', 'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1 ];
		$si_test_state['post_meta'][5]['_jat_search_intel_title'] = 'Mine';
		$migration = new TestableMigration( [], new FakeRedirectStore(), [
			'aioseo_posts' => [ [ 'id' => 1, 'post_id' => 5, 'title' => 'Theirs' ] ],
		] );

		$migration->migrate( 'aioseo' );
		$this->assertSame( 'Mine', $si_test_state['post_meta'][5]['_jat_search_intel_title'] );

		$migration->migrate( 'aioseo', true );
		$this->assertSame( 'Theirs', $si_test_state['post_meta'][5]['_jat_search_intel_title'] );
	}

	public function test_aioseo_settings_import(): void {
		global $si_test_state;
		$si_test_state['options']['aioseo_options'] = wp_json_encode( [
			'searchAppearance' => [
				'global'    => [
					'separator' => '&#45;',
					'siteTitle' => '#site_title #separator_sa #tagline',
					'schema'    => [ 'siteRepresents' => 'organization', 'organizationName' => 'Jeremy Anderson Tech', 'organizationLogo' => 'https://example.com/logo.png' ],
				],
				'postTypes' => [ 'post' => [ 'title' => '#post_title #separator_sa #site_title' ] ],
			],
			'webmasterTools' => [ 'google' => 'abc123', 'bing' => 'def456' ],
			'social'         => [ 'profiles' => [ 'urls' => [ 'twitterUrl' => 'https://x.com/JAndersonTech', 'linkedinUrl' => 'https://www.linkedin.com/in/example' ] ] ],
		] );

		$result = $this->migration->migrate( 'aioseo' );
		$saved  = $si_test_state['options']['jat_search_intel_settings'];

		$this->assertSame( '-', $saved['separator'] );
		$this->assertSame( '%sitename% %sep% %tagline%', $saved['title_templates']['homepage'] );
		$this->assertSame( '%title% %sep% %sitename%', $saved['title_templates']['single'] );
		$this->assertSame( 'Jeremy Anderson Tech', $saved['knowledge_name'] );
		$this->assertSame( 'abc123', $saved['verify_google'] );
		$this->assertSame( '@JAndersonTech', $saved['twitter_site'] );
		$this->assertSame( 'https://x.com/JAndersonTech', $si_test_state['options']['jat_search_intel_social_profiles']['twitter'] );
		$this->assertContains( 'verify_bing', $result['settings'] );
	}

	public function test_settings_not_overwritten_without_flag(): void {
		global $si_test_state;
		$si_test_state['options']['jat_search_intel_settings'] = [ 'verify_google' => 'mine' ];
		$si_test_state['options']['aioseo_options'] = wp_json_encode( [ 'webmasterTools' => [ 'google' => 'theirs' ] ] );

		$this->migration->migrate( 'aioseo' );
		$this->assertSame( 'mine', $si_test_state['options']['jat_search_intel_settings']['verify_google'] );
	}
}

/**
 * Migration with injectable source tables and redirect store.
 */
class TestableMigration extends Migration {
	/** @var array<string,array> */
	private $tables;
	/** @var FakeRedirectStore */
	private $store;

	public function __construct( array $settings, $store, array $tables = [] ) {
		parent::__construct( $settings );
		$this->store  = $store;
		$this->tables = $tables;
	}

	protected function table_exists( string $name ): bool {
		return isset( $this->tables[ $name ] );
	}

	protected function read_table( string $name, int $offset ): array {
		return array_slice( $this->tables[ $name ] ?? [], $offset, 500 );
	}

	protected function redirect_store() {
		return $this->store;
	}
}

/**
 * Records redirects instead of writing a table.
 */
class FakeRedirectStore extends \JAT_Search_Intel\Redirect_Manager {
	/** @var array */
	public $saved = [];
	/** @var array */
	public $existing = [];

	public function __construct() {}

	public function get_redirects( int $page = 1, int $per_page = 25, string $search = '', string $orderby = 'id', string $order = 'DESC' ): array {
		return [ 'items' => $this->existing, 'total' => count( $this->existing ) ];
	}

	public function save_redirect( array $data ) {
		$this->saved[] = $data;
		return count( $this->saved );
	}
}
