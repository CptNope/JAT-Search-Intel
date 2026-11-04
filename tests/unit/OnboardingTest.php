<?php
namespace JAT_Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\Admin_Menu;
use JAT_Search_Intel\AI_Search;
use JAT_Search_Intel\Core;
use JAT_Search_Intel\Meta_Sync;
use JAT_Search_Intel\Setup_Wizard;

/**
 * Compact menu, setup wizard, crawler presets and sidebar meta sync.
 */
class OnboardingTest extends TestCase {

	protected function setUp(): void {
		global $si_test_state, $submenu;
		$si_test_state['options']   = [];
		$si_test_state['post_meta'] = [];
		$si_test_state['actions']   = [];
		$si_test_state['filters']   = [];
		$submenu                    = [];
	}

	// ── Menu ────────────────────────────────────────────────────────────

	public function test_menu_shows_one_entry_per_group_and_keeps_every_page(): void {
		global $submenu;
		$submenu['jat-search-intel'] = [
			[ 'General', 'manage_options', 'jat-search-intel', 'General Settings' ],
			[ 'SEO', 'manage_options', 'jat-search-intel-seo', 'SEO Settings' ],
			[ 'Redirects', 'manage_options', 'jat-search-intel-redirects', 'Redirects' ],
			[ 'Readiness', 'manage_options', 'jat-search-intel-readiness', 'AI Search Readiness' ],
			[ 'Add-on', 'manage_options', 'someone-elses-page', 'Add-on' ],
		];

		( new Admin_Menu() )->regroup();

		$items   = $submenu['jat-search-intel'];
		$slugs   = array_column( $items, 2 );
		$visible = array_values( array_filter( $items, static function ( $i ) {
			return false === strpos( (string) ( $i[4] ?? '' ), 'jsi-menu-hidden' );
		} ) );

		$this->assertCount( 5, $items, 'No page is dropped, so WordPress still grants access to it' );
		$this->assertSame( 'jat-search-intel-readiness', $slugs[0], 'Readiness comes first, so the top-level link opens it' );
		$this->assertSame( [ 'Readiness', 'Settings', 'Redirects', 'Add-on' ], array_column( $visible, 0 ) );
		$this->assertSame( 'SEO', $items[2][0], 'Grouped pages keep their own label for the tab bar' );
	}

	public function test_group_lookup(): void {
		$this->assertSame( 'settings', Admin_Menu::group_of( 'jat-search-intel-geo' ) );
		$this->assertSame( 'reports', Admin_Menu::group_of( 'jat-search-intel-rank-tracker' ) );
		$this->assertSame( '', Admin_Menu::group_of( 'other' ) );
	}

	// ── Presets ─────────────────────────────────────────────────────────

	public function test_training_preset_blocks_only_training_bots(): void {
		$s = AI_Search::apply_preset( [], 'training' );
		$this->assertSame( 'block', $s['ai_crawler_rules']['GPTBot'] );
		$this->assertSame( 'allow', $s['ai_crawler_rules']['OAI-SearchBot'] );
		$this->assertSame( 'allow', $s['ai_crawler_rules']['ChatGPT-User'] );
		$this->assertSame( 'no', $s['content_signals']['ai-train'] );
		$this->assertSame( 'training', AI_Search::detect_preset( $s ) );
	}

	public function test_detect_preset(): void {
		$this->assertSame( 'allow', AI_Search::detect_preset( [] ) );
		$this->assertSame( 'block', AI_Search::detect_preset( AI_Search::apply_preset( [], 'block' ) ) );
		$this->assertSame( '', AI_Search::detect_preset( [ 'ai_crawler_rules' => [ 'OAI-SearchBot' => 'block' ] ] ) );
		$this->assertSame( [ 'x' => 1 ], AI_Search::apply_preset( [ 'x' => 1 ], 'nonsense' ) );
	}

	// ── Wizard ──────────────────────────────────────────────────────────

	public function test_wizard_step_one_saves_identity_and_profiles(): void {
		global $si_test_state;
		$si_test_state['options']['jat_search_intel_social_profiles'] = [ 'facebook' => 'https://facebook.com/old' ];

		$w = new Setup_Wizard( Core::get_default_settings() );
		$s = $w->apply_step( 1, [
			'knowledge_type' => 'Person',
			'knowledge_name' => '<b>Jo</b>',
			'knowledge_logo' => 'https://x.test/l.png',
			'profiles'       => [ 'facebook' => '', 'github' => 'https://github.com/jo', 'bogus' => 'https://x' ],
		], Core::get_default_settings() );

		$this->assertSame( 'Person', $s['knowledge_type'] );
		$this->assertSame( 'Jo', $s['knowledge_name'] );
		$this->assertSame( [ 'github' => 'https://github.com/jo' ], $si_test_state['options']['jat_search_intel_social_profiles'] );
	}

	public function test_wizard_rejects_unknown_entity_type(): void {
		$s = ( new Setup_Wizard( [] ) )->apply_step( 1, [ 'knowledge_type' => 'Robot' ], [] );
		$this->assertSame( 'Organization', $s['knowledge_type'] );
	}

	public function test_wizard_step_two_generates_indexnow_key(): void {
		$s = ( new Setup_Wizard( [] ) )->apply_step( 2, [ 'enable_sitemap' => '1', 'enable_indexnow' => '1' ], [ 'indexnow_api_key' => '' ] );
		$this->assertTrue( $s['enable_indexnow'] );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{32}$/', $s['indexnow_api_key'] );
	}

	public function test_wizard_step_three_applies_preset(): void {
		$s = ( new Setup_Wizard( [] ) )->apply_step( 3, [ 'ai_preset' => 'block', 'enable_llms_txt' => '1' ], [ 'enable_geo' => false ] );
		$this->assertSame( 'block', $s['ai_crawler_rules']['ClaudeBot'] );
		$this->assertTrue( $s['enable_geo'] );
		$this->assertTrue( $s['enable_llms_txt'] );
		$this->assertFalse( $s['enable_markdown_endpoints'] );
	}

	public function test_activation_marks_wizard_pending_once(): void {
		global $si_test_state;
		Setup_Wizard::on_activation();
		$this->assertSame( 'pending', $si_test_state['options'][ Setup_Wizard::STATE_OPTION ] );

		$si_test_state['options'][ Setup_Wizard::STATE_OPTION ] = 'done';
		Setup_Wizard::on_activation();
		$this->assertSame( 'done', $si_test_state['options'][ Setup_Wizard::STATE_OPTION ] );
	}

	// ── Meta sync ───────────────────────────────────────────────────────

	public function test_sidebar_canonical_reaches_front_end_key_and_back(): void {
		global $si_test_state;
		$sync = new Meta_Sync();

		$sync->on_write( 1, 5, '_jat_search_intel_canonical_url', 'https://x.test/a' );
		$this->assertSame( 'https://x.test/a', $si_test_state['post_meta'][5]['_jat_search_intel_canonical'] );

		$sync->on_write( 2, 5, '_jat_search_intel_audience', 'developers' );
		$this->assertSame( 'developers', $si_test_state['post_meta'][5]['_jat_search_intel_target_audience'] );

		$sync->on_delete( [ 1 ], 5, '_jat_search_intel_canonical' );
		$this->assertArrayNotHasKey( '_jat_search_intel_canonical_url', $si_test_state['post_meta'][5] );
	}

	public function test_topics_convert_between_text_and_list(): void {
		global $si_test_state;
		$sync = new Meta_Sync();

		$sync->on_write( 1, 6, '_jat_search_intel_key_topics', 'seo, ai search ,  llms' );
		$this->assertSame( [ 'seo', 'ai search', 'llms' ], $si_test_state['post_meta'][6]['_jat_search_intel_topics'] );

		$sync->on_write( 2, 6, '_jat_search_intel_topics', [ 'a', 'b' ] );
		$this->assertSame( 'a, b', $si_test_state['post_meta'][6]['_jat_search_intel_key_topics'] );
	}

	public function test_unrelated_keys_are_ignored(): void {
		global $si_test_state;
		( new Meta_Sync() )->on_write( 1, 7, '_jat_search_intel_title', 'T' );
		$this->assertArrayNotHasKey( 7, $si_test_state['post_meta'] );
	}
}
