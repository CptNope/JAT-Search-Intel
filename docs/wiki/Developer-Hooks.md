# Developer Hooks

All hooks use the `jat_search_intel_` prefix.

## Filters

### `jat_search_intel_singular_schema`

The JSON-LD graph for a single post or page, before it is printed.

```php
add_filter( 'jat_search_intel_singular_schema', function ( array $schema, int $post_id ) {
	$schema['@graph'][] = [
		'@type' => 'Event',
		'name'  => get_the_title( $post_id ),
	];
	return $schema;
}, 10, 2 );
```

### `jat_search_intel_llms_txt`

The full text of `/llms.txt`, or of `/llms-full.txt` when `$full` is true.

```php
add_filter( 'jat_search_intel_llms_txt', function ( string $text, bool $full ) {
	return $text . "\n## Pricing\n\n- [Plans](https://example.com/pricing/)\n";
}, 10, 2 );
```

### `jat_search_intel_llms_contact_email`

Adds a "Contact" section with this address to llms.txt. The default is empty, which leaves the section out.

```php
add_filter( 'jat_search_intel_llms_contact_email', fn () => 'press@example.com' );
```

### `jat_search_intel_markdown`

The Markdown copy of a post served at `/post-slug.md`.

```php
add_filter( 'jat_search_intel_markdown', function ( string $markdown, WP_Post $post ) {
	return $markdown . "\n\n---\nLicense: CC BY 4.0\n";
}, 10, 2 );
```

### `jat_search_intel_admin_menu_groups`

The six admin menu groups, as `key => [ label, page slugs in tab order ]`. Add-on pages can join a group as an extra tab:

```php
add_filter( 'jat_search_intel_admin_menu_groups', function ( array $groups ) {
	$groups['reports'][1][] = 'my-addon-report';
	return $groups;
} );
```

## Actions

### `jat_search_intel_post_analyzed`

Fires after a post is analyzed: on save, from the REST API, from bulk rescoring and from WP-CLI.

```php
add_action( 'jat_search_intel_post_analyzed', function ( int $post_id, array $new_scores, array $old_scores, array $results ) {
	if ( ( $old_scores['seo'] ?? 0 ) - ( $new_scores['seo'] ?? 0 ) >= 20 ) {
		error_log( "SEO score for post {$post_id} dropped sharply." );
	}
}, 10, 4 );
```

### `jat_search_intel_seo_meta_updated`

Fires after SEO meta is changed through an ability. `$fields` lists the keys that changed, and `$source` says where the change came from (`'ability'`).

```php
add_action( 'jat_search_intel_seo_meta_updated', function ( int $post_id, array $fields, string $source ) {
	// Purge a CDN cache, log the change, …
}, 10, 3 );
```

## Data keys

| What | Name |
|---|---|
| Settings option | `jat_search_intel_settings` |
| Post meta | `_jat_search_intel_*` (for example `_jat_search_intel_title`, `_jat_search_intel_description`, `_jat_search_intel_noindex`) |
| REST namespace | `jat-search-intel/v1` |
| Ability category | `jat-search-intel` |
| Text domain | `jat-search-intel` |
| PHP namespace | `JAT_Search_Intel` |
