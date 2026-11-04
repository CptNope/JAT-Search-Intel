<?php
namespace JAT_Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\Rest_Api;
use JAT_Search_Intel\Core;

class RestApiTest extends TestCase {

    private $core;
    private $rest_api;

    protected function setUp(): void {
        global $si_test_state;
        $si_test_state = [
            'post_meta'    => [],
            'user_meta'    => [],
            'options'      => [],
            'transients'   => [],
            'actions'      => [],
            'filters'      => [],
            'posts'        => [],
            'current_user' => 1,
        ];

        // Set up default settings.
        $si_test_state['options']['jat_search_intel_settings'] = Core::get_default_settings();

        // Add a test post.
        $si_test_state['posts'][1] = [
            'ID'                => 1,
            'post_title'        => 'Test Post',
            'post_content'      => '<p>This is a test post with some content for analysis.</p>',
            'post_excerpt'      => 'Test excerpt',
            'post_status'       => 'publish',
            'post_type'         => 'post',
            'post_author'       => 1,
            'post_date'         => '2025-01-01 00:00:00',
            'post_date_gmt'     => '2025-01-01 00:00:00',
            'post_modified'     => '2025-01-01 00:00:00',
            'post_modified_gmt' => '2025-01-01 00:00:00',
        ];

        // Set some scores in post meta.
        $si_test_state['post_meta'][1] = [
            '_jat_search_intel_seo_score'         => 75,
            '_jat_search_intel_aeo_score'         => 60,
            '_jat_search_intel_geo_score'         => 50,
            '_jat_search_intel_readability_score'  => 80,
            '_jat_search_intel_focus_keyword'      => 'test keyword',
            '_jat_search_intel_description'        => 'Test meta description',
            '_jat_search_intel_title'              => 'Custom SEO Title',
            '_jat_search_intel_ai_summary'         => 'AI summary of the post.',
            '_jat_search_intel_content_type'        => 'article',
            '_jat_search_intel_content_intent'      => 'informational',
            '_jat_search_intel_audience'            => 'developers',
            '_jat_search_intel_topics'              => [ 'testing', 'seo' ],
            '_jat_search_intel_entities'            => [ 'JAT Search Intel' ],
            '_jat_search_intel_expertise_level'     => 'expert',
            '_jat_search_intel_fact_checked'        => '',
            '_jat_search_intel_local_relevance'     => '',
            '_jat_search_intel_local_location'      => '',
        ];

        $this->core     = Core::get_instance();
        $settings       = $this->core->get_settings();
        $this->rest_api = new Rest_Api( $settings, $this->core );
    }

    // ── GET /scores/{id} ────────────────────────────────────────────────────

    public function test_get_scores_returns_all_four_scores(): void {
        $request = new \WP_REST_Request( 'GET', '/jat-search-intel/v1/scores/1' );
        $request->set_param( 'id', 1 );

        $response = $this->rest_api->get_scores( $request );

        $this->assertInstanceOf( \WP_REST_Response::class, $response );
        $this->assertEquals( 200, $response->get_status() );

        $data = $response->get_data();
        $this->assertEquals( 1, $data['post_id'] );
        $this->assertEquals( 75, $data['seo_score'] );
        $this->assertEquals( 60, $data['aeo_score'] );
        $this->assertEquals( 50, $data['geo_score'] );
        $this->assertEquals( 80, $data['readability'] );
    }

    public function test_get_scores_returns_404_for_missing_post(): void {
        $request = new \WP_REST_Request( 'GET', '/jat-search-intel/v1/scores/999' );
        $request->set_param( 'id', 999 );

        $response = $this->rest_api->get_scores( $request );

        $this->assertEquals( 404, $response->get_status() );
        $this->assertEquals( 'not_found', $response->get_data()['code'] );
    }

    public function test_get_scores_returns_zero_for_unscored_post(): void {
        global $si_test_state;
        $si_test_state['posts'][2] = [
            'ID'            => 2,
            'post_title'    => 'Unscored Post',
            'post_content'  => 'No scores yet.',
            'post_status'   => 'publish',
            'post_type'     => 'post',
            'post_author'   => 1,
        ];

        $request = new \WP_REST_Request( 'GET', '/jat-search-intel/v1/scores/2' );
        $request->set_param( 'id', 2 );

        $response = $this->rest_api->get_scores( $request );
        $data     = $response->get_data();

        $this->assertEquals( 0, $data['seo_score'] );
        $this->assertEquals( 0, $data['aeo_score'] );
        $this->assertEquals( 0, $data['geo_score'] );
        $this->assertEquals( 0, $data['readability'] );
    }

    // ── GET /geo/{id} ───────────────────────────────────────────────────────

    public function test_get_geo_returns_full_metadata(): void {
        $request = new \WP_REST_Request( 'GET', '/jat-search-intel/v1/geo/1' );
        $request->set_param( 'id', 1 );

        $response = $this->rest_api->get_geo( $request );

        $this->assertEquals( 200, $response->get_status() );

        $data = $response->get_data();
        $this->assertEquals( 1, $data['post_id'] );
        $this->assertEquals( 'AI summary of the post.', $data['ai_summary'] );
        $this->assertEquals( [ 'testing', 'seo' ], $data['topics'] );
        $this->assertEquals( [ 'JAT Search Intel' ], $data['entities'] );
        $this->assertEquals( 'article', $data['content_type'] );
        $this->assertEquals( 'informational', $data['content_intent'] );
        $this->assertEquals( 'developers', $data['audience'] );
        $this->assertEquals( 'expert', $data['expertise_level'] );
        $this->assertEquals( 50, $data['geo_score'] );
    }

    public function test_get_geo_returns_404_for_missing_post(): void {
        $request = new \WP_REST_Request( 'GET', '/jat-search-intel/v1/geo/999' );
        $request->set_param( 'id', 999 );

        $response = $this->rest_api->get_geo( $request );
        $this->assertEquals( 404, $response->get_status() );
    }

    // ── GET /meta/{id} ──────────────────────────────────────────────────────

    public function test_get_meta_returns_all_fields(): void {
        $request = new \WP_REST_Request( 'GET', '/jat-search-intel/v1/meta/1' );
        $request->set_param( 'id', 1 );

        $response = $this->rest_api->get_meta( $request );

        $this->assertEquals( 200, $response->get_status() );

        $data = $response->get_data();
        $this->assertEquals( 1, $data['post_id'] );
        $this->assertEquals( 'Custom SEO Title', $data['title'] );
        $this->assertEquals( 'Test meta description', $data['description'] );
        $this->assertEquals( 'test keyword', $data['focus_keyword'] );
        $this->assertEquals( 75, $data['seo_score'] );
    }

    // ── PUT /meta/{id} ──────────────────────────────────────────────────────

    public function test_update_meta_changes_fields(): void {
        $request = new \WP_REST_Request( 'PUT', '/jat-search-intel/v1/meta/1' );
        $request->set_param( 'id', 1 );
        $request->set_json_params( [
            'title'         => 'Updated Title',
            'description'   => 'Updated Description',
            'focus_keyword' => 'new keyword',
        ] );

        $response = $this->rest_api->update_meta( $request );

        $this->assertEquals( 200, $response->get_status() );

        $data = $response->get_data();
        $this->assertArrayHasKey( 'updated', $data );
        $this->assertEquals( 'Updated Title', $data['updated']['title'] );
        $this->assertEquals( 'Updated Description', $data['updated']['description'] );
        $this->assertEquals( 'new keyword', $data['updated']['focus_keyword'] );

        // Verify meta was actually updated.
        global $si_test_state;
        $this->assertEquals( 'Updated Title', $si_test_state['post_meta'][1]['_jat_search_intel_title'] );
    }

    public function test_update_meta_ignores_unknown_fields(): void {
        $request = new \WP_REST_Request( 'PUT', '/jat-search-intel/v1/meta/1' );
        $request->set_param( 'id', 1 );
        $request->set_json_params( [
            'unknown_field' => 'should be ignored',
            'title'         => 'Valid Update',
        ] );

        $response = $this->rest_api->update_meta( $request );
        $data     = $response->get_data();

        $this->assertArrayNotHasKey( 'unknown_field', $data['updated'] );
        $this->assertEquals( 'Valid Update', $data['updated']['title'] );
    }

    public function test_update_meta_returns_404_for_missing_post(): void {
        $request = new \WP_REST_Request( 'PUT', '/jat-search-intel/v1/meta/999' );
        $request->set_param( 'id', 999 );
        $request->set_json_params( [ 'title' => 'Test' ] );

        $response = $this->rest_api->update_meta( $request );
        $this->assertEquals( 404, $response->get_status() );
    }

    // ── POST /analyze/{id} ──────────────────────────────────────────────────

    public function test_analyze_post_returns_results(): void {
        $request = new \WP_REST_Request( 'POST', '/jat-search-intel/v1/analyze/1' );
        $request->set_param( 'id', 1 );

        $response = $this->rest_api->analyze_post( $request );

        $this->assertEquals( 200, $response->get_status() );

        $data = $response->get_data();
        $this->assertEquals( 1, $data['post_id'] );
        $this->assertArrayHasKey( 'results', $data );
    }

    public function test_analyze_post_saves_scores(): void {
        $request = new \WP_REST_Request( 'POST', '/jat-search-intel/v1/analyze/1' );
        $request->set_param( 'id', 1 );

        $this->rest_api->analyze_post( $request );

        global $si_test_state;
        // All four scores are re-stored (fixture ints become fresh floats), matching the editor sidebar.
        foreach ( [ 'seo', 'aeo', 'geo', 'readability' ] as $type ) {
            $this->assertIsFloat( $si_test_state['post_meta'][1][ '_jat_search_intel_' . $type . '_score' ] ?? null, $type );
        }
    }

    // ── GET /settings ───────────────────────────────────────────────────────

    public function test_get_settings_returns_all_settings(): void {
        $response = $this->rest_api->get_settings();

        $this->assertEquals( 200, $response->get_status() );

        $data = $response->get_data();
        $this->assertArrayHasKey( 'separator', $data );
        $this->assertArrayHasKey( 'enable_seo', $data );
        $this->assertArrayHasKey( 'enable_rest_api', $data );
        $this->assertTrue( $data['enable_rest_api'] );
    }

    // ── PUT /settings ───────────────────────────────────────────────────────

    public function test_update_settings_changes_values(): void {
        $request = new \WP_REST_Request( 'PUT', '/jat-search-intel/v1/settings' );
        $request->set_json_params( [
            'separator'  => '-',
            'enable_seo' => false,
        ] );

        $response = $this->rest_api->update_settings( $request );

        $this->assertEquals( 200, $response->get_status() );

        $data = $response->get_data();
        $this->assertEquals( '-', $data['updated']['separator'] );
        $this->assertFalse( $data['updated']['enable_seo'] );

        // Verify persisted.
        global $si_test_state;
        $saved = $si_test_state['options']['jat_search_intel_settings'];
        $this->assertEquals( '-', $saved['separator'] );
        $this->assertFalse( $saved['enable_seo'] );
    }

    public function test_update_settings_rejects_unknown_keys(): void {
        $request = new \WP_REST_Request( 'PUT', '/jat-search-intel/v1/settings' );
        $request->set_json_params( [
            'evil_key'  => 'should be ignored',
            'separator' => '~',
        ] );

        $response = $this->rest_api->update_settings( $request );
        $data     = $response->get_data();

        $this->assertArrayNotHasKey( 'evil_key', $data['updated'] );
        $this->assertEquals( '~', $data['updated']['separator'] );
    }

    // ── POST /sitemap/invalidate ────────────────────────────────────────────

    public function test_invalidate_sitemap_returns_success(): void {
        $response = $this->rest_api->invalidate_sitemap();

        $this->assertEquals( 200, $response->get_status() );
        $this->assertTrue( $response->get_data()['success'] );
    }

    // ── Permission callbacks ────────────────────────────────────────────────

    public function test_can_read_post_returns_true_for_published(): void {
        $request = new \WP_REST_Request( 'GET' );
        $request->set_param( 'id', 1 );

        $this->assertTrue( $this->rest_api->can_read_post( $request ) );
    }

    public function test_visitors_cannot_read_protected_or_draft_posts(): void {
        global $si_test_state;
        $si_test_state['denied_caps'] = [ 'edit_post' ];
        $si_test_state['posts'][40]   = [ 'ID' => 40, 'post_status' => 'publish', 'post_password' => 'secret', 'post_type' => 'post' ];
        $si_test_state['posts'][41]   = [ 'ID' => 41, 'post_status' => 'draft', 'post_password' => '', 'post_type' => 'post' ];

        foreach ( [ 40, 41 ] as $id ) {
            $request = new \WP_REST_Request( 'GET' );
            $request->set_param( 'id', $id );
            $this->assertFalse( $this->rest_api->can_read_post( $request ), "post {$id}" );
        }
        $si_test_state['denied_caps'] = [];
    }

    public function test_visitors_do_not_get_editorial_fields(): void {
        global $si_test_state;
        $si_test_state['denied_caps'] = [ 'edit_post' ];
        $request = new \WP_REST_Request( 'GET', '/jat-search-intel/v1/meta/1' );
        $request->set_param( 'id', 1 );

        $data = $this->rest_api->get_meta( $request )->get_data();
        $si_test_state['denied_caps'] = [];

        $this->assertArrayHasKey( 'description', $data );
        $this->assertArrayNotHasKey( 'focus_keyword', $data );
        $this->assertArrayNotHasKey( 'seo_score', $data );
    }

    public function test_can_read_post_returns_false_for_missing(): void {
        $request = new \WP_REST_Request( 'GET' );
        $request->set_param( 'id', 999 );

        $this->assertFalse( $this->rest_api->can_read_post( $request ) );
    }

    public function test_can_edit_post_returns_false_for_missing(): void {
        $request = new \WP_REST_Request( 'PUT' );
        $request->set_param( 'id', 999 );

        $this->assertFalse( $this->rest_api->can_edit_post( $request ) );
    }

    public function test_can_manage_options_returns_true(): void {
        // current_user_can is stubbed to return true.
        $this->assertTrue( $this->rest_api->can_manage_options() );
    }

    // ── POST /bulk/rescore ──────────────────────────────────────────────────

    public function test_bulk_rescore_processes_batch(): void {
        $request = new \WP_REST_Request( 'POST', '/jat-search-intel/v1/bulk/rescore' );
        $request->set_param( 'post_type', 'post' );
        $request->set_param( 'batch_size', 10 );
        $request->set_param( 'offset', 0 );

        $response = $this->rest_api->bulk_rescore( $request );

        $this->assertEquals( 200, $response->get_status() );

        $data = $response->get_data();
        $this->assertArrayHasKey( 'processed', $data );
        $this->assertArrayHasKey( 'total', $data );
        $this->assertIsInt( $data['processed'] );
    }

    // ── Route registration ──────────────────────────────────────────────────

    public function test_routes_registered_when_enabled(): void {
        global $si_test_state;
        $si_test_state['actions'] = [];

        $settings = $this->core->get_settings();
        $settings['enable_rest_api'] = true;
        $api = new Rest_Api( $settings, $this->core );

        $this->assertNotEmpty( $si_test_state['actions']['rest_api_init'] );
    }

    public function test_register_routes_skips_when_disabled(): void {
        global $si_test_state;

        $settings = $this->core->get_settings();
        $settings['enable_rest_api'] = false;
        $api = new Rest_Api( $settings, $this->core );

        // The action is still registered, but register_routes() will early-return.
        // We can verify by calling it directly.
        // This just verifies no errors are thrown.
        $api->register_routes();
        $this->assertTrue( true ); // No exception = pass.
    }
}
