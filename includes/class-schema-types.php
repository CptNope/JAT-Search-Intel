<?php
namespace Search_Intel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Additional Schema Types — VideoObject, Recipe, Event, Review, Course, SoftwareApplication, JobPosting.
 *
 * Each schema type is driven by post meta and rendered as JSON-LD on the front end.
 * Meta keys follow the pattern: _search_intel_{type}_{field}
 *
 * @since 2.1.0
 */
class Schema_Types {

	/** @var array */
	private $settings;

	/**
	 * Supported schema types and their meta prefix.
	 */
	private const TYPES = [
		'video'        => 'VideoObject',
		'recipe'       => 'Recipe',
		'event'        => 'Event',
		'review'       => 'Review',
		'course'       => 'Course',
		'software'     => 'SoftwareApplication',
		'job_posting'  => 'JobPosting',
	];

	/**
	 * Constructor.
	 *
	 * @param array $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;

		if ( ! empty( $settings['enable_schema_types'] ) ) {
			add_action( 'wp_head', [ $this, 'render_schemas' ], 15 );
			add_filter( 'search_intel_singular_schema', [ $this, 'inject_schema_type' ], 10, 2 );
		}
	}

	/**
	 * Render additional schema types as standalone JSON-LD blocks.
	 */
	public function render_schemas(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post_id = get_queried_object_id();
		$schemas = [];

		// Auto-detect VideoObject from content iframes.
		$video = $this->get_video_schema( $post_id );
		if ( $video ) {
			$schemas[] = $video;
		}

		// Meta-driven schema types.
		$meta_type = get_post_meta( $post_id, '_search_intel_schema_type_extra', true );
		if ( $meta_type && isset( self::TYPES[ $meta_type ] ) ) {
			$method = 'get_' . $meta_type . '_schema';
			if ( method_exists( $this, $method ) ) {
				$schema = $this->$method( $post_id );
				if ( $schema ) {
					$schemas[] = $schema;
				}
			}
		}

		foreach ( $schemas as $schema ) {
			$output = array_merge( [ '@context' => 'https://schema.org' ], $schema );
			printf(
				"\n" . '<script type="application/ld+json">%s</script>' . "\n",
				wp_json_encode( $output, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT )
			);
		}
	}

	/**
	 * Inject the selected schema type into the singular schema graph.
	 *
	 * @param array $schema Existing schema.
	 * @param int   $post_id Post ID.
	 * @return array
	 */
	public function inject_schema_type( array $schema, int $post_id ): array {
		$extra_type = get_post_meta( $post_id, '_search_intel_schema_type_extra', true );
		if ( $extra_type && isset( self::TYPES[ $extra_type ] ) ) {
			$schema['additionalType'] = 'https://schema.org/' . self::TYPES[ $extra_type ];
		}
		return $schema;
	}

	/**
	 * Auto-detect VideoObject from YouTube/Vimeo iframes in content.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null
	 */
	public function get_video_schema( int $post_id ): ?array {
		$post = get_post( $post_id );
		if ( ! $post || empty( $post->post_content ) ) {
			return null;
		}

		// Check for explicit video meta first.
		$video_url = get_post_meta( $post_id, '_search_intel_video_url', true );
		if ( $video_url ) {
			return $this->build_video_schema( $post_id, $video_url );
		}

		// Auto-detect YouTube iframes.
		if ( preg_match( '/(?:youtube\.com\/embed\/|youtu\.be\/)([a-zA-Z0-9_-]+)/', $post->post_content, $yt ) ) {
			return $this->build_video_schema( $post_id, 'https://www.youtube.com/watch?v=' . $yt[1] );
		}

		// Auto-detect Vimeo iframes.
		if ( preg_match( '/vimeo\.com\/(?:video\/)?(\d+)/', $post->post_content, $vm ) ) {
			return $this->build_video_schema( $post_id, 'https://vimeo.com/' . $vm[1] );
		}

		return null;
	}

	/**
	 * Build a VideoObject schema.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $video_url Video URL.
	 * @return array
	 */
	private function build_video_schema( int $post_id, string $video_url ): array {
		$name        = get_post_meta( $post_id, '_search_intel_video_name', true ) ?: get_the_title( $post_id );
		$description = get_post_meta( $post_id, '_search_intel_video_description', true ) ?: wp_trim_words( wp_strip_all_tags( get_post( $post_id )->post_content ?? '' ), 30, '...' );
		$thumbnail   = get_post_meta( $post_id, '_search_intel_video_thumbnail', true ) ?: get_the_post_thumbnail_url( $post_id, 'full' );
		$upload_date = get_post_meta( $post_id, '_search_intel_video_upload_date', true ) ?: get_the_date( 'c', $post_id );
		$duration    = get_post_meta( $post_id, '_search_intel_video_duration', true );

		$schema = [
			'@type'        => 'VideoObject',
			'name'         => sanitize_text_field( $name ),
			'description'  => sanitize_text_field( $description ),
			'contentUrl'   => esc_url( $video_url ),
			'uploadDate'   => $upload_date,
		];

		if ( $thumbnail ) {
			$schema['thumbnailUrl'] = esc_url( $thumbnail );
		}

		if ( $duration ) {
			$schema['duration'] = sanitize_text_field( $duration );
		}

		return $schema;
	}

	/**
	 * Build Recipe schema from post meta.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null
	 */
	public function get_recipe_schema( int $post_id ): ?array {
		$name = get_post_meta( $post_id, '_search_intel_recipe_name', true );
		if ( ! $name ) {
			$name = get_the_title( $post_id );
		}

		$ingredients = get_post_meta( $post_id, '_search_intel_recipe_ingredients', true );
		$steps       = get_post_meta( $post_id, '_search_intel_recipe_steps', true );

		if ( empty( $ingredients ) || empty( $steps ) ) {
			return null;
		}

		$schema = [
			'@type'               => 'Recipe',
			'name'                => sanitize_text_field( $name ),
			'recipeIngredient'    => is_array( $ingredients ) ? array_map( 'sanitize_text_field', $ingredients ) : [],
		];

		// Steps.
		if ( is_array( $steps ) ) {
			$schema['recipeInstructions'] = [];
			foreach ( $steps as $step ) {
				$schema['recipeInstructions'][] = [
					'@type' => 'HowToStep',
					'text'  => sanitize_text_field( $step ),
				];
			}
		}

		// Optional fields.
		$description = get_post_meta( $post_id, '_search_intel_recipe_description', true );
		if ( $description ) {
			$schema['description'] = sanitize_text_field( $description );
		}

		$cook_time = get_post_meta( $post_id, '_search_intel_recipe_cook_time', true );
		if ( $cook_time ) {
			$schema['cookTime'] = sanitize_text_field( $cook_time );
		}

		$prep_time = get_post_meta( $post_id, '_search_intel_recipe_prep_time', true );
		if ( $prep_time ) {
			$schema['prepTime'] = sanitize_text_field( $prep_time );
		}

		$servings = get_post_meta( $post_id, '_search_intel_recipe_servings', true );
		if ( $servings ) {
			$schema['recipeYield'] = sanitize_text_field( $servings );
		}

		$calories = get_post_meta( $post_id, '_search_intel_recipe_calories', true );
		if ( $calories ) {
			$schema['nutrition'] = [
				'@type'    => 'NutritionInformation',
				'calories' => sanitize_text_field( $calories ),
			];
		}

		$image = get_the_post_thumbnail_url( $post_id, 'full' );
		if ( $image ) {
			$schema['image'] = esc_url( $image );
		}

		return $schema;
	}

	/**
	 * Build Event schema from post meta.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null
	 */
	public function get_event_schema( int $post_id ): ?array {
		$name       = get_post_meta( $post_id, '_search_intel_event_name', true );
		$start_date = get_post_meta( $post_id, '_search_intel_event_start_date', true );

		if ( ! $name || ! $start_date ) {
			return null;
		}

		$schema = [
			'@type'     => 'Event',
			'name'      => sanitize_text_field( $name ),
			'startDate' => sanitize_text_field( $start_date ),
		];

		$end_date = get_post_meta( $post_id, '_search_intel_event_end_date', true );
		if ( $end_date ) {
			$schema['endDate'] = sanitize_text_field( $end_date );
		}

		$description = get_post_meta( $post_id, '_search_intel_event_description', true );
		if ( $description ) {
			$schema['description'] = sanitize_text_field( $description );
		}

		// Location.
		$location_name = get_post_meta( $post_id, '_search_intel_event_location', true );
		$location_addr = get_post_meta( $post_id, '_search_intel_event_address', true );
		if ( $location_name ) {
			$location = [
				'@type' => 'Place',
				'name'  => sanitize_text_field( $location_name ),
			];
			if ( $location_addr ) {
				$location['address'] = sanitize_text_field( $location_addr );
			}
			$schema['location'] = $location;
		}

		// Performer.
		$performer = get_post_meta( $post_id, '_search_intel_event_performer', true );
		if ( $performer ) {
			$schema['performer'] = [
				'@type' => 'Person',
				'name'  => sanitize_text_field( $performer ),
			];
		}

		// Organizer.
		$organizer = get_post_meta( $post_id, '_search_intel_event_organizer', true );
		if ( $organizer ) {
			$schema['organizer'] = [
				'@type' => 'Organization',
				'name'  => sanitize_text_field( $organizer ),
			];
		}

		// Offers (price).
		$price    = get_post_meta( $post_id, '_search_intel_event_price', true );
		$currency = get_post_meta( $post_id, '_search_intel_event_currency', true );
		if ( $price ) {
			$schema['offers'] = [
				'@type'         => 'Offer',
				'price'         => sanitize_text_field( $price ),
				'priceCurrency' => sanitize_text_field( $currency ?: 'USD' ),
				'availability'  => 'https://schema.org/InStock',
			];
		}

		$image = get_the_post_thumbnail_url( $post_id, 'full' );
		if ( $image ) {
			$schema['image'] = esc_url( $image );
		}

		return $schema;
	}

	/**
	 * Build Review schema from post meta.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null
	 */
	public function get_review_schema( int $post_id ): ?array {
		$item_name = get_post_meta( $post_id, '_search_intel_review_item_name', true );
		if ( ! $item_name ) {
			return null;
		}

		$schema = [
			'@type'        => 'Review',
			'itemReviewed' => [
				'@type' => sanitize_text_field( get_post_meta( $post_id, '_search_intel_review_item_type', true ) ?: 'Thing' ),
				'name'  => sanitize_text_field( $item_name ),
			],
			'author'       => [
				'@type' => 'Person',
				'name'  => get_the_author_meta( 'display_name', get_post( $post_id )->post_author ?? 0 ),
			],
		];

		// Rating.
		$rating = get_post_meta( $post_id, '_search_intel_review_rating', true );
		if ( $rating ) {
			$best = get_post_meta( $post_id, '_search_intel_review_best_rating', true );
			$schema['reviewRating'] = [
				'@type'       => 'Rating',
				'ratingValue' => sanitize_text_field( $rating ),
				'bestRating'  => sanitize_text_field( $best ?: '5' ),
			];
		}

		// Pros / Cons.
		$pros = get_post_meta( $post_id, '_search_intel_review_pros', true );
		if ( ! empty( $pros ) && is_array( $pros ) ) {
			$schema['positiveNotes'] = [
				'@type'           => 'ItemList',
				'itemListElement' => array_map( function ( $pro ) {
					return [
						'@type' => 'ListItem',
						'name'  => sanitize_text_field( $pro ),
					];
				}, $pros ),
			];
		}

		$cons = get_post_meta( $post_id, '_search_intel_review_cons', true );
		if ( ! empty( $cons ) && is_array( $cons ) ) {
			$schema['negativeNotes'] = [
				'@type'           => 'ItemList',
				'itemListElement' => array_map( function ( $con ) {
					return [
						'@type' => 'ListItem',
						'name'  => sanitize_text_field( $con ),
					];
				}, $cons ),
			];
		}

		// Body.
		$body = get_post_meta( $post_id, '_search_intel_review_body', true );
		if ( $body ) {
			$schema['reviewBody'] = sanitize_text_field( $body );
		}

		return $schema;
	}

	/**
	 * Build Course schema from post meta.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null
	 */
	public function get_course_schema( int $post_id ): ?array {
		$name = get_post_meta( $post_id, '_search_intel_course_name', true );
		if ( ! $name ) {
			return null;
		}

		$schema = [
			'@type' => 'Course',
			'name'  => sanitize_text_field( $name ),
		];

		$description = get_post_meta( $post_id, '_search_intel_course_description', true );
		if ( $description ) {
			$schema['description'] = sanitize_text_field( $description );
		}

		$provider = get_post_meta( $post_id, '_search_intel_course_provider', true );
		if ( $provider ) {
			$schema['provider'] = [
				'@type' => 'Organization',
				'name'  => sanitize_text_field( $provider ),
			];
		}

		$duration = get_post_meta( $post_id, '_search_intel_course_duration', true );
		if ( $duration ) {
			$schema['timeRequired'] = sanitize_text_field( $duration );
		}

		$price    = get_post_meta( $post_id, '_search_intel_course_price', true );
		$currency = get_post_meta( $post_id, '_search_intel_course_currency', true );
		if ( $price ) {
			$schema['offers'] = [
				'@type'         => 'Offer',
				'price'         => sanitize_text_field( $price ),
				'priceCurrency' => sanitize_text_field( $currency ?: 'USD' ),
			];
		}

		return $schema;
	}

	/**
	 * Build SoftwareApplication schema from post meta.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null
	 */
	public function get_software_schema( int $post_id ): ?array {
		$name = get_post_meta( $post_id, '_search_intel_software_name', true );
		if ( ! $name ) {
			return null;
		}

		$schema = [
			'@type' => 'SoftwareApplication',
			'name'  => sanitize_text_field( $name ),
		];

		$os = get_post_meta( $post_id, '_search_intel_software_os', true );
		if ( $os ) {
			$schema['operatingSystem'] = sanitize_text_field( $os );
		}

		$category = get_post_meta( $post_id, '_search_intel_software_category', true );
		if ( $category ) {
			$schema['applicationCategory'] = sanitize_text_field( $category );
		}

		$price    = get_post_meta( $post_id, '_search_intel_software_price', true );
		$currency = get_post_meta( $post_id, '_search_intel_software_currency', true );
		if ( $price !== false && $price !== '' ) {
			$schema['offers'] = [
				'@type'         => 'Offer',
				'price'         => sanitize_text_field( (string) $price ),
				'priceCurrency' => sanitize_text_field( $currency ?: 'USD' ),
			];
		}

		$rating      = get_post_meta( $post_id, '_search_intel_software_rating', true );
		$rating_count = get_post_meta( $post_id, '_search_intel_software_rating_count', true );
		if ( $rating ) {
			$schema['aggregateRating'] = [
				'@type'       => 'AggregateRating',
				'ratingValue' => sanitize_text_field( $rating ),
				'ratingCount' => sanitize_text_field( $rating_count ?: '1' ),
			];
		}

		return $schema;
	}

	/**
	 * Build JobPosting schema from post meta.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null
	 */
	public function get_job_posting_schema( int $post_id ): ?array {
		$title = get_post_meta( $post_id, '_search_intel_job_title', true );
		if ( ! $title ) {
			return null;
		}

		$schema = [
			'@type'       => 'JobPosting',
			'title'       => sanitize_text_field( $title ),
			'datePosted'  => get_the_date( 'c', $post_id ),
		];

		$description = get_post_meta( $post_id, '_search_intel_job_description', true );
		if ( $description ) {
			$schema['description'] = wp_kses_post( $description );
		}

		$company = get_post_meta( $post_id, '_search_intel_job_company', true );
		if ( $company ) {
			$schema['hiringOrganization'] = [
				'@type' => 'Organization',
				'name'  => sanitize_text_field( $company ),
			];
		}

		$location = get_post_meta( $post_id, '_search_intel_job_location', true );
		if ( $location ) {
			$schema['jobLocation'] = [
				'@type'   => 'Place',
				'address' => sanitize_text_field( $location ),
			];
		}

		$employment_type = get_post_meta( $post_id, '_search_intel_job_employment_type', true );
		if ( $employment_type ) {
			$schema['employmentType'] = sanitize_text_field( $employment_type );
		}

		// Salary.
		$salary_value    = get_post_meta( $post_id, '_search_intel_job_salary', true );
		$salary_currency = get_post_meta( $post_id, '_search_intel_job_salary_currency', true );
		$salary_unit     = get_post_meta( $post_id, '_search_intel_job_salary_unit', true );
		if ( $salary_value ) {
			$schema['baseSalary'] = [
				'@type'    => 'MonetaryAmount',
				'currency' => sanitize_text_field( $salary_currency ?: 'USD' ),
				'value'    => [
					'@type'    => 'QuantitativeValue',
					'value'    => sanitize_text_field( $salary_value ),
					'unitText' => sanitize_text_field( $salary_unit ?: 'YEAR' ),
				],
			];
		}

		$valid_through = get_post_meta( $post_id, '_search_intel_job_valid_through', true );
		if ( $valid_through ) {
			$schema['validThrough'] = sanitize_text_field( $valid_through );
		}

		return $schema;
	}

	/**
	 * Get supported schema types.
	 *
	 * @return array
	 */
	public static function get_types(): array {
		return self::TYPES;
	}
}
