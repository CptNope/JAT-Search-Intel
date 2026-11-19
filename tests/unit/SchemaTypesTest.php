<?php
namespace JAT_Search_Intel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use JAT_Search_Intel\Schema_Types;

class SchemaTypesTest extends TestCase {

	private Schema_Types $types;

	protected function setUp(): void {
		global $si_test_state;
		$si_test_state['options']   = [];
		$si_test_state['post_meta'] = [];
		$si_test_state['posts']     = [];

		$this->types = new Schema_Types( [
			'enable_schema_types' => true,
		] );
	}

	public function test_get_types_returns_all_seven(): void {
		$types = Schema_Types::get_types();
		$this->assertCount( 7, $types );
		$this->assertArrayHasKey( 'video', $types );
		$this->assertArrayHasKey( 'recipe', $types );
		$this->assertArrayHasKey( 'event', $types );
		$this->assertArrayHasKey( 'review', $types );
		$this->assertArrayHasKey( 'course', $types );
		$this->assertArrayHasKey( 'software', $types );
		$this->assertArrayHasKey( 'job_posting', $types );
	}

	// ── VideoObject ──────────────────────────────────────────────────────────

	public function test_video_schema_from_youtube_iframe(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID'           => 1,
			'post_title'   => 'Video Post',
			'post_content' => '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>',
			'post_status'  => 'publish',
			'post_type'    => 'post',
			'post_author'  => 1,
		];

		$schema = $this->types->get_video_schema( 1 );

		$this->assertNotNull( $schema );
		$this->assertEquals( 'VideoObject', $schema['@type'] );
		$this->assertStringContainsString( 'dQw4w9WgXcQ', $schema['contentUrl'] );
	}

	public function test_video_schema_from_vimeo_iframe(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID'           => 1,
			'post_title'   => 'Vimeo Post',
			'post_content' => '<iframe src="https://player.vimeo.com/video/123456789"></iframe>',
			'post_status'  => 'publish',
			'post_type'    => 'post',
			'post_author'  => 1,
		];

		$schema = $this->types->get_video_schema( 1 );

		$this->assertNotNull( $schema );
		$this->assertEquals( 'VideoObject', $schema['@type'] );
		$this->assertStringContainsString( '123456789', $schema['contentUrl'] );
	}

	public function test_video_schema_from_explicit_meta(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID'           => 1,
			'post_title'   => 'Meta Video',
			'post_content' => 'No iframe here.',
			'post_status'  => 'publish',
			'post_type'    => 'post',
			'post_author'  => 1,
		];
		$si_test_state['post_meta'][1]['_jat_search_intel_video_url'] = 'https://example.com/video.mp4';
		$si_test_state['post_meta'][1]['_jat_search_intel_video_name'] = 'My Video';

		$schema = $this->types->get_video_schema( 1 );

		$this->assertNotNull( $schema );
		$this->assertEquals( 'My Video', $schema['name'] );
		$this->assertEquals( 'https://example.com/video.mp4', $schema['contentUrl'] );
	}

	public function test_video_schema_null_without_video(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID'           => 1,
			'post_title'   => 'No Video',
			'post_content' => '<p>Just text.</p>',
			'post_status'  => 'publish',
			'post_type'    => 'post',
			'post_author'  => 1,
		];

		$this->assertNull( $this->types->get_video_schema( 1 ) );
	}

	public function test_video_schema_null_for_missing_post(): void {
		$this->assertNull( $this->types->get_video_schema( 999 ) );
	}

	// ── Recipe ───────────────────────────────────────────────────────────────

	public function test_recipe_schema_full(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Recipe Post', 'post_content' => '',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];
		$si_test_state['post_meta'][1]['_jat_search_intel_recipe_name']        = 'Chocolate Cake';
		$si_test_state['post_meta'][1]['_jat_search_intel_recipe_ingredients'] = [ 'flour', 'sugar', 'cocoa' ];
		$si_test_state['post_meta'][1]['_jat_search_intel_recipe_steps']       = [ 'Mix dry', 'Add wet', 'Bake' ];
		$si_test_state['post_meta'][1]['_jat_search_intel_recipe_cook_time']   = 'PT30M';
		$si_test_state['post_meta'][1]['_jat_search_intel_recipe_calories']    = '350 calories';

		$schema = $this->types->get_recipe_schema( 1 );

		$this->assertNotNull( $schema );
		$this->assertEquals( 'Recipe', $schema['@type'] );
		$this->assertEquals( 'Chocolate Cake', $schema['name'] );
		$this->assertCount( 3, $schema['recipeIngredient'] );
		$this->assertCount( 3, $schema['recipeInstructions'] );
		$this->assertEquals( 'PT30M', $schema['cookTime'] );
		$this->assertEquals( '350 calories', $schema['nutrition']['calories'] );
	}

	public function test_recipe_schema_null_without_ingredients(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Test', 'post_content' => '',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];
		$si_test_state['post_meta'][1]['_jat_search_intel_recipe_name'] = 'Cake';

		$this->assertNull( $this->types->get_recipe_schema( 1 ) );
	}

	// ── Event ────────────────────────────────────────────────────────────────

	public function test_event_schema_full(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Event Post', 'post_content' => '',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];
		$si_test_state['post_meta'][1]['_jat_search_intel_event_name']       = 'Tech Conference';
		$si_test_state['post_meta'][1]['_jat_search_intel_event_start_date'] = '2026-06-15T09:00:00';
		$si_test_state['post_meta'][1]['_jat_search_intel_event_location']   = 'Convention Center';
		$si_test_state['post_meta'][1]['_jat_search_intel_event_performer']  = 'Jane Doe';
		$si_test_state['post_meta'][1]['_jat_search_intel_event_organizer']  = 'Tech Corp';
		$si_test_state['post_meta'][1]['_jat_search_intel_event_price']      = '99';

		$schema = $this->types->get_event_schema( 1 );

		$this->assertNotNull( $schema );
		$this->assertEquals( 'Event', $schema['@type'] );
		$this->assertEquals( 'Tech Conference', $schema['name'] );
		$this->assertEquals( 'Convention Center', $schema['location']['name'] );
		$this->assertEquals( 'Jane Doe', $schema['performer']['name'] );
		$this->assertEquals( 'Tech Corp', $schema['organizer']['name'] );
		$this->assertEquals( '99', $schema['offers']['price'] );
	}

	public function test_event_schema_null_without_name(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Test', 'post_content' => '',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];
		$si_test_state['post_meta'][1]['_jat_search_intel_event_start_date'] = '2026-06-15';

		$this->assertNull( $this->types->get_event_schema( 1 ) );
	}

	public function test_event_schema_null_without_date(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Test', 'post_content' => '',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];
		$si_test_state['post_meta'][1]['_jat_search_intel_event_name'] = 'Conference';

		$this->assertNull( $this->types->get_event_schema( 1 ) );
	}

	// ── Review ───────────────────────────────────────────────────────────────

	public function test_review_schema_full(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Review Post', 'post_content' => '',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];
		$si_test_state['post_meta'][1]['_jat_search_intel_review_item_name'] = 'iPhone 15';
		$si_test_state['post_meta'][1]['_jat_search_intel_review_item_type'] = 'Product';
		$si_test_state['post_meta'][1]['_jat_search_intel_review_rating']    = '4.5';
		$si_test_state['post_meta'][1]['_jat_search_intel_review_pros']      = [ 'Great camera', 'Fast' ];
		$si_test_state['post_meta'][1]['_jat_search_intel_review_cons']      = [ 'Expensive' ];

		$schema = $this->types->get_review_schema( 1 );

		$this->assertNotNull( $schema );
		$this->assertEquals( 'Review', $schema['@type'] );
		$this->assertEquals( 'iPhone 15', $schema['itemReviewed']['name'] );
		$this->assertEquals( 'Product', $schema['itemReviewed']['@type'] );
		$this->assertEquals( '4.5', $schema['reviewRating']['ratingValue'] );
		$this->assertCount( 2, $schema['positiveNotes']['itemListElement'] );
		$this->assertCount( 1, $schema['negativeNotes']['itemListElement'] );
	}

	public function test_review_schema_null_without_item(): void {
		$this->assertNull( $this->types->get_review_schema( 999 ) );
	}

	// ── Course ───────────────────────────────────────────────────────────────

	public function test_course_schema_full(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Course Post', 'post_content' => '',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];
		$si_test_state['post_meta'][1]['_jat_search_intel_course_name']     = 'PHP Masterclass';
		$si_test_state['post_meta'][1]['_jat_search_intel_course_provider'] = 'Udemy';
		$si_test_state['post_meta'][1]['_jat_search_intel_course_duration'] = 'PT20H';
		$si_test_state['post_meta'][1]['_jat_search_intel_course_price']    = '49.99';

		$schema = $this->types->get_course_schema( 1 );

		$this->assertNotNull( $schema );
		$this->assertEquals( 'Course', $schema['@type'] );
		$this->assertEquals( 'PHP Masterclass', $schema['name'] );
		$this->assertEquals( 'Udemy', $schema['provider']['name'] );
		$this->assertEquals( 'PT20H', $schema['timeRequired'] );
		$this->assertEquals( '49.99', $schema['offers']['price'] );
	}

	public function test_course_schema_null_without_name(): void {
		$this->assertNull( $this->types->get_course_schema( 999 ) );
	}

	// ── SoftwareApplication ──────────────────────────────────────────────────

	public function test_software_schema_full(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Software Post', 'post_content' => '',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];
		$si_test_state['post_meta'][1]['_jat_search_intel_software_name']     = 'MyApp';
		$si_test_state['post_meta'][1]['_jat_search_intel_software_os']       = 'Windows, macOS';
		$si_test_state['post_meta'][1]['_jat_search_intel_software_category'] = 'DeveloperApplication';
		$si_test_state['post_meta'][1]['_jat_search_intel_software_price']    = '0';
		$si_test_state['post_meta'][1]['_jat_search_intel_software_rating']   = '4.8';

		$schema = $this->types->get_software_schema( 1 );

		$this->assertNotNull( $schema );
		$this->assertEquals( 'SoftwareApplication', $schema['@type'] );
		$this->assertEquals( 'MyApp', $schema['name'] );
		$this->assertEquals( 'Windows, macOS', $schema['operatingSystem'] );
		$this->assertEquals( 'DeveloperApplication', $schema['applicationCategory'] );
		$this->assertEquals( '0', $schema['offers']['price'] );
		$this->assertEquals( '4.8', $schema['aggregateRating']['ratingValue'] );
	}

	public function test_software_schema_falls_back_to_title_with_url_and_links(): void {
		global $si_test_state;
		$si_test_state['posts'][2] = [
			'ID' => 2, 'post_title' => 'Hearthdeep', 'post_content' => '',
			'post_status' => 'publish', 'post_type' => 'project', 'post_author' => 1,
		];
		$si_test_state['post_meta'][2]['_jat_search_intel_software_category'] = 'GameApplication';
		$si_test_state['post_meta'][2]['_jat_search_intel_software_links']    = "https://cptnope.itch.io/hearthdeep\n\njavascript:alert(1)\nhttps://cptnope.itch.io/hearthdeep\nhttps://github.com/CptNope/hearthdeep";

		$schema = $this->types->get_software_schema( 2 );

		$this->assertSame( 'Hearthdeep', $schema['name'] );
		$this->assertSame( 'https://example.com/?p=2', $schema['url'] );
		$this->assertSame( [ 'https://cptnope.itch.io/hearthdeep', 'https://github.com/CptNope/hearthdeep' ], $schema['sameAs'] );
		$this->assertArrayNotHasKey( 'offers', $schema );
	}

	public function test_software_links_field_is_registered(): void {
		$this->assertContains( '_jat_search_intel_software_links', Schema_Types::meta_keys() );
	}

	public function test_software_schema_null_without_name(): void {
		$this->assertNull( $this->types->get_software_schema( 999 ) );
	}

	// ── JobPosting ───────────────────────────────────────────────────────────

	public function test_job_posting_schema_full(): void {
		global $si_test_state;
		$si_test_state['posts'][1] = [
			'ID' => 1, 'post_title' => 'Job Post', 'post_content' => '',
			'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1,
		];
		$si_test_state['post_meta'][1]['_jat_search_intel_job_title']           = 'Senior PHP Developer';
		$si_test_state['post_meta'][1]['_jat_search_intel_job_company']         = 'Acme Inc';
		$si_test_state['post_meta'][1]['_jat_search_intel_job_location']        = 'New York, NY';
		$si_test_state['post_meta'][1]['_jat_search_intel_job_employment_type'] = 'FULL_TIME';
		$si_test_state['post_meta'][1]['_jat_search_intel_job_salary']          = '120000';
		$si_test_state['post_meta'][1]['_jat_search_intel_job_salary_unit']     = 'YEAR';

		$schema = $this->types->get_job_posting_schema( 1 );

		$this->assertNotNull( $schema );
		$this->assertEquals( 'JobPosting', $schema['@type'] );
		$this->assertEquals( 'Senior PHP Developer', $schema['title'] );
		$this->assertEquals( 'Acme Inc', $schema['hiringOrganization']['name'] );
		$this->assertEquals( 'New York, NY', $schema['jobLocation']['address'] );
		$this->assertEquals( 'FULL_TIME', $schema['employmentType'] );
		$this->assertEquals( '120000', $schema['baseSalary']['value']['value'] );
	}

	public function test_job_posting_schema_null_without_title(): void {
		$this->assertNull( $this->types->get_job_posting_schema( 999 ) );
	}

	// ── inject_schema_type ───────────────────────────────────────────────────

	public function test_inject_schema_type_adds_additional_type(): void {
		global $si_test_state;
		$si_test_state['post_meta'][1]['_jat_search_intel_schema_type_extra'] = 'recipe';

		$schema = $this->types->inject_schema_type( [ '@type' => 'Article' ], 1 );

		$this->assertArrayHasKey( 'additionalType', $schema );
		$this->assertEquals( 'https://schema.org/Recipe', $schema['additionalType'] );
	}

	public function test_inject_schema_type_no_extra_type(): void {
		$schema = $this->types->inject_schema_type( [ '@type' => 'Article' ], 1 );
		$this->assertArrayNotHasKey( 'additionalType', $schema );
	}

	public function test_disabled_does_not_register(): void {
		global $si_test_state;
		$si_test_state['actions'] = [];
		$si_test_state['filters'] = [];

		$types = new Schema_Types( [ 'enable_schema_types' => false ] );

		$this->assertArrayNotHasKey( 'wp_head', $si_test_state['actions'] ?? [] );
	}

	// ── Editor fields ────────────────────────────────────────────────────

	public function test_lines_accepts_text_and_arrays(): void {
		$this->assertSame( [ 'Flour', 'Eggs' ], Schema_Types::lines( "Flour\r\n\n Eggs \n" ) );
		$this->assertSame( [ 'A', 'B' ], Schema_Types::lines( [ 'A', '', 'B' ] ) );
		$this->assertSame( [], Schema_Types::lines( null ) );
	}

	public function test_duration_from_minutes(): void {
		$this->assertSame( 'PT30M', Schema_Types::duration( '30' ) );
		$this->assertSame( 'PT1H30M', Schema_Types::duration( 90 ) );
		$this->assertSame( 'PT2H', Schema_Types::duration( '120' ) );
		$this->assertSame( 'PT0M', Schema_Types::duration( '0' ) );
		$this->assertSame( 'PT45M', Schema_Types::duration( 'PT45M' ) );
	}

	public function test_recipe_from_text_fields(): void {
		global $si_test_state;
		$si_test_state['posts'][20] = [ 'ID' => 20, 'post_title' => 'Pancakes', 'post_content' => 'x', 'post_status' => 'publish', 'post_type' => 'post', 'post_author' => 1 ];
		$si_test_state['post_meta'][20]['_jat_search_intel_recipe_ingredients'] = "Flour\nMilk";
		$si_test_state['post_meta'][20]['_jat_search_intel_recipe_steps']       = "Mix\nCook";
		$si_test_state['post_meta'][20]['_jat_search_intel_recipe_prep_time']   = '10';

		$schema = $this->types->get_recipe_schema( 20 );

		$this->assertSame( [ 'Flour', 'Milk' ], $schema['recipeIngredient'] );
		$this->assertCount( 2, $schema['recipeInstructions'] );
		$this->assertSame( 'PT10M', $schema['prepTime'] );
	}

	public function test_every_type_has_fields_and_meta_keys(): void {
		$fields = Schema_Types::get_fields();
		foreach ( array_keys( Schema_Types::get_types() ) as $type ) {
			$this->assertArrayHasKey( $type, $fields, $type );
		}
		$keys = Schema_Types::meta_keys();
		$this->assertContains( '_jat_search_intel_schema_type_extra', $keys );
		$this->assertContains( '_jat_search_intel_event_start_date', $keys );
	}
}
