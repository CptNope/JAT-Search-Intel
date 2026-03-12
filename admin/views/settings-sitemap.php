<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
$settings = get_option( 'search_intel_settings', [] );
$settings = wp_parse_args( $settings, \Search_Intel\Core::get_default_settings() );
?>
<div class="wrap si-settings">
    <h1><span class="dashicons dashicons-networking"></span> <?php esc_html_e( 'Search Intel — Sitemap Settings', 'search-intel' ); ?></h1>

    <form method="post" action="options.php">
        <?php settings_fields( 'search_intel_settings_group' ); ?>

        <?php
        $sitemap_keys = [ 'sitemap_post_types', 'sitemap_max_urls' ];
        foreach ( $settings as $key => $val ) :
            if ( in_array( $key, $sitemap_keys, true ) ) continue;
            if ( is_array( $val ) ) :
                foreach ( $val as $v ) : ?>
                    <input type="hidden" name="search_intel_settings[<?php echo esc_attr( $key ); ?>][]" value="<?php echo esc_attr( $v ); ?>" />
                <?php endforeach;
            elseif ( is_bool( $val ) || $val === true ) :
                if ( $val ) : ?>
                    <input type="hidden" name="search_intel_settings[<?php echo esc_attr( $key ); ?>]" value="1" />
                <?php endif;
            else : ?>
                <input type="hidden" name="search_intel_settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $val ); ?>" />
            <?php endif;
        endforeach;
        ?>

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'Sitemap Configuration', 'search-intel' ); ?></h2>

            <?php if ( ! empty( $settings['enable_sitemap'] ) ) : ?>
                <div class="si-notice si-notice-info">
                    <p>
                        <?php esc_html_e( 'Your sitemap is live at:', 'search-intel' ); ?>
                        <a href="<?php echo esc_url( home_url( '/sitemap.xml' ) ); ?>" target="_blank"><strong><?php echo esc_url( home_url( '/sitemap.xml' ) ); ?></strong></a>
                    </p>
                </div>
            <?php endif; ?>

            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Include Post Types', 'search-intel' ); ?></th>
                    <td>
                        <?php
                        $post_types = get_post_types( [ 'public' => true ], 'objects' );
                        foreach ( $post_types as $pt ) :
                            if ( 'attachment' === $pt->name ) continue;
                            ?>
                            <label style="display:block; margin-bottom:6px;">
                                <input type="checkbox" name="search_intel_settings[sitemap_post_types][]" value="<?php echo esc_attr( $pt->name ); ?>" <?php checked( in_array( $pt->name, $settings['sitemap_post_types'] ?? [], true ) ); ?> />
                                <?php echo esc_html( $pt->label ); ?>
                                <?php
                                $count = wp_count_posts( $pt->name );
                                if ( isset( $count->publish ) ) :
                                    printf( '<span class="description">(%d %s)</span>', $count->publish, esc_html__( 'published', 'search-intel' ) );
                                endif;
                                ?>
                            </label>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <tr>
                    <th><label for="si_sitemap_max_urls"><?php esc_html_e( 'Max URLs per Sitemap', 'search-intel' ); ?></label></th>
                    <td>
                        <input type="number" id="si_sitemap_max_urls" name="search_intel_settings[sitemap_max_urls]" value="<?php echo esc_attr( $settings['sitemap_max_urls'] ); ?>" min="100" max="50000" step="100" class="small-text" />
                        <p class="description"><?php esc_html_e( 'Sitemaps will be paginated if the number of URLs exceeds this limit. Google recommends max 50,000.', 'search-intel' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="si-settings-card si-info-card">
            <h2><?php esc_html_e( 'Sitemap Features', 'search-intel' ); ?></h2>
            <ul>
                <li><?php esc_html_e( '✓ Sitemap index with pagination for large sites', 'search-intel' ); ?></li>
                <li><?php esc_html_e( '✓ Image sitemap support for better image indexing', 'search-intel' ); ?></li>
                <li><?php esc_html_e( '✓ Taxonomy sitemaps (categories, tags)', 'search-intel' ); ?></li>
                <li><?php esc_html_e( '✓ Automatic search engine pinging on publish', 'search-intel' ); ?></li>
                <li><?php esc_html_e( '✓ Posts marked as noindex are automatically excluded', 'search-intel' ); ?></li>
            </ul>
        </div>

        <?php submit_button( __( 'Save Sitemap Settings', 'search-intel' ) ); ?>
    </form>
</div>
