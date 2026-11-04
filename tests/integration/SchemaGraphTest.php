<?php
/**
 * Tests for the connected JSON-LD @graph.
 */

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\Schema_Manager;
use JAT_Search_Intel\AEO_Manager;
use JAT_Search_Intel\Core;

class SchemaGraphTest extends TestCase {

    private function make_schema( array $overrides = [] ): Schema_Manager {
        return new Schema_Manager( array_merge( Core::get_default_settings(), $overrides ) );
    }

    protected function setUp(): void {
        global $si_test_state, $wp;
        $si_test_state['post_meta']  = [];
        $si_test_state['user_meta']  = [];
        $si_test_state['options']    = [];
        $si_test_state['actions']    = [];
        $si_test_state['filters']    = [];
        $si_test_state['transients'] = [];
        $si_test_state['posts']      = [
            1 => [
                'ID'                => 1,
                'post_title'        => 'Graph Post',
                'post_content'      => '<p>Content about connected structured data.</p>',
                'post_excerpt'      => '',
                'post_author'       => 1,
                'post_type'         => 'post',
                'post_status'       => 'publish',
                'post_date_gmt'     => '2026-01-15 10:00:00',
                'post_modified_gmt' => '2026-02-20 14:30:00',
            ],
        ];
        $wp = (object) [ 'request' => '' ];
    }

    /**
     * @param array $graph Graph nodes.
     * @return array<string,array> Nodes keyed by @id.
     */
    private function by_id( array $graph ): array {
        $out = [];
        foreach ( $graph as $node ) {
            $out[ $node['@id'] ?? spl_object_hash( (object) $node ) ] = $node;
        }
        return $out;
    }

    public function test_singular_graph_is_connected_by_ids(): void {
        $graph = $this->by_id( $this->make_schema()->build_graph() );
        $page  = 'https://example.com/?p=1';

        $this->assertArrayHasKey( 'https://example.com/#website', $graph );
        $this->assertArrayHasKey( $page, $graph );
        $this->assertArrayHasKey( $page . '#article', $graph );
        $this->assertArrayHasKey( 'https://example.com/author/1#author', $graph );

        $article = $graph[ $page . '#article' ];
        $this->assertSame( [ '@id' => $page ], $article['isPartOf'] );
        $this->assertSame( $page, $article['mainEntityOfPage']['@id'] );
        $this->assertSame( [ '@id' => 'https://example.com/author/1#author' ], $article['author'] );

        $this->assertSame( [ '@id' => 'https://example.com/#website' ], $graph[ $page ]['isPartOf'] );
        $this->assertSame( 'Person', $graph['https://example.com/author/1#author']['@type'] );
    }

    public function test_every_referenced_id_exists_in_graph(): void {
        $graph = $this->make_schema()->build_graph();
        $ids   = array_column( $graph, '@id' );

        $refs = [];
        array_walk_recursive( $graph, static function ( $value, $key ) use ( &$refs ) {
            if ( '@id' === $key ) {
                $refs[] = $value;
            }
        } );

        foreach ( array_unique( $refs ) as $ref ) {
            $this->assertContains( $ref, $ids, "Dangling @id reference: {$ref}" );
        }
    }

    public function test_page_type_is_merged_into_webpage_node(): void {
        global $si_test_state;
        $si_test_state['post_meta'][1]['_jat_search_intel_schema_type'] = 'AboutPage';

        $graph = $this->by_id( $this->make_schema()->build_graph() );
        $page  = $graph['https://example.com/?p=1'];

        $this->assertContains( 'AboutPage', (array) $page['@type'] );
        $this->assertArrayNotHasKey( 'https://example.com/?p=1#aboutpage', $graph );
    }

    public function test_speakable_merges_into_webpage(): void {
        $settings = Core::get_default_settings();
        $aeo      = new AEO_Manager( $settings );
        $schema   = new Schema_Manager( $settings );

        $graph = $this->by_id( $schema->build_graph() );
        $page  = $graph['https://example.com/?p=1'];

        $this->assertSame( 'SpeakableSpecification', $page['speakable']['@type'] );
        unset( $aeo );
    }

    public function test_merge_nodes_by_id_combines_types_and_keeps_order(): void {
        $merged = $this->make_schema()->merge_nodes_by_id( [
            [ '@type' => 'WebPage', '@id' => 'x', 'name' => 'A' ],
            [ '@type' => 'Thing', '@id' => 'y' ],
            [ '@type' => 'FAQPage', '@id' => 'x', 'name' => 'B', 'mainEntity' => [ 1 ] ],
        ] );

        $this->assertCount( 2, $merged );
        $this->assertSame( [ 'WebPage', 'FAQPage' ], $merged[0]['@type'] );
        $this->assertSame( 'A', $merged[0]['name'] );
        $this->assertSame( [ 1 ], $merged[0]['mainEntity'] );
    }

    public function test_single_script_tag_is_printed(): void {
        $mgr = $this->make_schema();
        ob_start();
        $mgr->render_schema();
        $output = ob_get_clean();

        $this->assertSame( 1, substr_count( $output, 'application/ld+json' ) );
    }
}
