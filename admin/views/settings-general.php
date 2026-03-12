<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
$settings = get_option( 'search_intel_settings', [] );
$settings = wp_parse_args( $settings, \Search_Intel\Core::get_default_settings() );
?>
<div class="wrap si-settings">
    <h1><span class="dashicons dashicons-chart-area"></span> <?php esc_html_e( 'Search Intel — General Settings', 'search-intel' ); ?></h1>

    <div class="si-settings-header">
        <p><?php esc_html_e( 'Configure your site-wide SEO, AEO, and GEO settings. These defaults apply to all content.', 'search-intel' ); ?></p>
    </div>

    <form method="post" action="options.php">
        <?php settings_fields( 'search_intel_settings_group' ); ?>

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'Site Identity', 'search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="si_site_name"><?php esc_html_e( 'Site Name', 'search-intel' ); ?></label></th>
                    <td>
                        <input type="text" id="si_site_name" name="search_intel_settings[site_name]" value="<?php echo esc_attr( $settings['site_name'] ); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th><label for="si_separator"><?php esc_html_e( 'Title Separator', 'search-intel' ); ?></label></th>
                    <td>
                        <select id="si_separator" name="search_intel_settings[separator]">
                            <?php foreach ( [ '|', '-', '–', '—', '·', '•', '»', '/' ] as $sep ) : ?>
                                <option value="<?php echo esc_attr( $sep ); ?>" <?php selected( $settings['separator'], $sep ); ?>><?php echo esc_html( $sep ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="si_knowledge_type"><?php esc_html_e( 'Entity Type', 'search-intel' ); ?></label></th>
                    <td>
                        <select id="si_knowledge_type" name="search_intel_settings[knowledge_type]">
                            <option value="Organization" <?php selected( $settings['knowledge_type'], 'Organization' ); ?>><?php esc_html_e( 'Organization', 'search-intel' ); ?></option>
                            <option value="Person" <?php selected( $settings['knowledge_type'], 'Person' ); ?>><?php esc_html_e( 'Person', 'search-intel' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="si_knowledge_name"><?php esc_html_e( 'Organization / Person Name', 'search-intel' ); ?></label></th>
                    <td>
                        <input type="text" id="si_knowledge_name" name="search_intel_settings[knowledge_name]" value="<?php echo esc_attr( $settings['knowledge_name'] ); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th><label for="si_knowledge_logo"><?php esc_html_e( 'Logo URL', 'search-intel' ); ?></label></th>
                    <td>
                        <input type="url" id="si_knowledge_logo" name="search_intel_settings[knowledge_logo]" value="<?php echo esc_url( $settings['knowledge_logo'] ); ?>" class="regular-text" />
                        <p class="description"><?php esc_html_e( 'Recommended: 1200×630px or square logo.', 'search-intel' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'Enabled Post Types', 'search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Enable Search Intel on', 'search-intel' ); ?></th>
                    <td>
                        <?php
                        $post_types = get_post_types( [ 'public' => true ], 'objects' );
                        foreach ( $post_types as $pt ) :
                            if ( 'attachment' === $pt->name ) {
                                continue;
                            }
                            ?>
                            <label style="display:block; margin-bottom:6px;">
                                <input type="checkbox" name="search_intel_settings[enabled_post_types][]" value="<?php echo esc_attr( $pt->name ); ?>" <?php checked( in_array( $pt->name, $settings['enabled_post_types'] ?? [], true ) ); ?> />
                                <?php echo esc_html( $pt->label ); ?>
                            </label>
                        <?php endforeach; ?>
                    </td>
                </tr>
            </table>
        </div>

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'Module Toggles', 'search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Core Modules', 'search-intel' ); ?></th>
                    <td>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="search_intel_settings[enable_seo]" value="1" <?php checked( $settings['enable_seo'] ); ?> />
                            <strong><?php esc_html_e( 'SEO', 'search-intel' ); ?></strong> — <?php esc_html_e( 'Meta tags, Open Graph, Twitter Cards, canonical URLs', 'search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="search_intel_settings[enable_schema]" value="1" <?php checked( $settings['enable_schema'] ); ?> />
                            <strong><?php esc_html_e( 'Schema', 'search-intel' ); ?></strong> — <?php esc_html_e( 'JSON-LD structured data', 'search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="search_intel_settings[enable_aeo]" value="1" <?php checked( $settings['enable_aeo'] ); ?> />
                            <strong><?php esc_html_e( 'AEO', 'search-intel' ); ?></strong> — <?php esc_html_e( 'Answer Engine Optimization (FAQ, HowTo, Speakable)', 'search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="search_intel_settings[enable_geo]" value="1" <?php checked( $settings['enable_geo'] ); ?> />
                            <strong><?php esc_html_e( 'GEO', 'search-intel' ); ?></strong> — <?php esc_html_e( 'Generative Engine Optimization (AI meta, llms.txt, E-E-A-T)', 'search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="search_intel_settings[enable_sitemap]" value="1" <?php checked( $settings['enable_sitemap'] ); ?> />
                            <strong><?php esc_html_e( 'Sitemap', 'search-intel' ); ?></strong> — <?php esc_html_e( 'XML sitemap with image support', 'search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:8px;">
                            <input type="checkbox" name="search_intel_settings[enable_analyzer]" value="1" <?php checked( $settings['enable_analyzer'] ); ?> />
                            <strong><?php esc_html_e( 'Content Analyzer', 'search-intel' ); ?></strong> — <?php esc_html_e( 'Real-time scoring and optimization tips', 'search-intel' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button( __( 'Save Settings', 'search-intel' ) ); ?>
    </form>
</div>
