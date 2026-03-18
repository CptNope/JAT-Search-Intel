<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
$settings = get_option( 'search_intel_settings', [] );
$settings = wp_parse_args( $settings, \Search_Intel\Core::get_default_settings() );
?>
<div class="wrap si-settings">
    <h1><span class="dashicons dashicons-search"></span> <?php esc_html_e( 'Search Intel — SEO Settings', 'search-intel' ); ?></h1>

    <form method="post" action="options.php">
        <?php settings_fields( 'search_intel_settings_group' ); ?>
        <input type="hidden" name="search_intel_settings[_si_page]" value="seo" />

        <!-- Preserve all other settings as hidden fields -->
        <?php
        $preserve_keys = [
            'site_name', 'separator', 'knowledge_type', 'knowledge_name', 'knowledge_logo',
            'enable_seo', 'enable_schema', 'enable_aeo', 'enable_geo', 'enable_sitemap', 'enable_analyzer',
            'enable_faq_schema', 'enable_howto_schema', 'enable_speakable',
            'enable_llms_txt', 'enable_ai_meta', 'enable_entity_markup', 'content_freshness',
            'author_expertise', 'schema_article_type', 'enable_breadcrumbs', 'enable_sitelinks',
            'sitemap_max_urls', 'target_readability',
        ];
        foreach ( $preserve_keys as $key ) :
            if ( is_bool( $settings[ $key ] ) || $settings[ $key ] === '1' || $settings[ $key ] === '' ) :
                if ( ! empty( $settings[ $key ] ) ) : ?>
                    <input type="hidden" name="search_intel_settings[<?php echo esc_attr( $key ); ?>]" value="1" />
                <?php endif;
            else : ?>
                <input type="hidden" name="search_intel_settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( is_array( $settings[ $key ] ) ? '' : $settings[ $key ] ); ?>" />
            <?php endif;
        endforeach;

        if ( ! empty( $settings['enabled_post_types'] ) ) :
            foreach ( $settings['enabled_post_types'] as $pt ) : ?>
                <input type="hidden" name="search_intel_settings[enabled_post_types][]" value="<?php echo esc_attr( $pt ); ?>" />
            <?php endforeach;
        endif;
        if ( ! empty( $settings['sitemap_post_types'] ) ) :
            foreach ( $settings['sitemap_post_types'] as $pt ) : ?>
                <input type="hidden" name="search_intel_settings[sitemap_post_types][]" value="<?php echo esc_attr( $pt ); ?>" />
            <?php endforeach;
        endif;
        ?>

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'Open Graph', 'search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Enable Open Graph', 'search-intel' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="search_intel_settings[enable_og]" value="1" <?php checked( $settings['enable_og'] ); ?> />
                            <?php esc_html_e( 'Output Open Graph meta tags for Facebook, LinkedIn, etc.', 'search-intel' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'Twitter Cards', 'search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Enable Twitter Cards', 'search-intel' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="search_intel_settings[enable_twitter]" value="1" <?php checked( $settings['enable_twitter'] ); ?> />
                            <?php esc_html_e( 'Output Twitter Card meta tags', 'search-intel' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><label for="si_twitter_card_type"><?php esc_html_e( 'Card Type', 'search-intel' ); ?></label></th>
                    <td>
                        <select id="si_twitter_card_type" name="search_intel_settings[twitter_card_type]">
                            <option value="summary_large_image" <?php selected( $settings['twitter_card_type'], 'summary_large_image' ); ?>><?php esc_html_e( 'Summary with Large Image', 'search-intel' ); ?></option>
                            <option value="summary" <?php selected( $settings['twitter_card_type'], 'summary' ); ?>><?php esc_html_e( 'Summary', 'search-intel' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="si_twitter_site"><?php esc_html_e( 'Twitter @username', 'search-intel' ); ?></label></th>
                    <td>
                        <input type="text" id="si_twitter_site" name="search_intel_settings[twitter_site]" value="<?php echo esc_attr( $settings['twitter_site'] ); ?>" placeholder="@yourbrand" class="regular-text" />
                    </td>
                </tr>
            </table>
        </div>

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'Canonical & Robots', 'search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Canonical URLs', 'search-intel' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="search_intel_settings[enable_canonical]" value="1" <?php checked( $settings['enable_canonical'] ); ?> />
                            <?php esc_html_e( 'Auto-generate canonical URLs', 'search-intel' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Robots Meta', 'search-intel' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="search_intel_settings[enable_robots_meta]" value="1" <?php checked( $settings['enable_robots_meta'] ); ?> />
                            <?php esc_html_e( 'Output robots meta tags', 'search-intel' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Noindex Rules', 'search-intel' ); ?></th>
                    <td>
                        <label style="display:block; margin-bottom:6px;">
                            <input type="checkbox" name="search_intel_settings[noindex_search]" value="1" <?php checked( $settings['noindex_search'] ); ?> />
                            <?php esc_html_e( 'Noindex search results pages', 'search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:6px;">
                            <input type="checkbox" name="search_intel_settings[noindex_archives]" value="1" <?php checked( $settings['noindex_archives'] ); ?> />
                            <?php esc_html_e( 'Noindex date and author archives', 'search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:6px;">
                            <input type="checkbox" name="search_intel_settings[noindex_404]" value="1" <?php checked( $settings['noindex_404'] ); ?> />
                            <?php esc_html_e( 'Noindex 404 pages', 'search-intel' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'Default Open Graph Image', 'search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="si_default_og_image"><?php esc_html_e( 'Fallback OG Image URL', 'search-intel' ); ?></label></th>
                    <td>
                        <input type="url" id="si_default_og_image" name="search_intel_settings[default_og_image]" value="<?php echo esc_url( $settings['default_og_image'] ); ?>" class="regular-text" />
                        <p class="description"><?php esc_html_e( 'Used when a post has no featured image or custom social image.', 'search-intel' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'Meta Description', 'search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="si_description_fallback"><?php esc_html_e( 'Fallback Behavior', 'search-intel' ); ?></label></th>
                    <td>
                        <select id="si_description_fallback" name="search_intel_settings[description_fallback]">
                            <option value="excerpt" <?php selected( $settings['description_fallback'], 'excerpt' ); ?>><?php esc_html_e( 'Use excerpt, then trim content', 'search-intel' ); ?></option>
                            <option value="content" <?php selected( $settings['description_fallback'], 'content' ); ?>><?php esc_html_e( 'Trim content directly', 'search-intel' ); ?></option>
                            <option value="empty" <?php selected( $settings['description_fallback'], 'empty' ); ?>><?php esc_html_e( 'Leave empty if not set', 'search-intel' ); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e( 'How to generate the meta description when none is manually set.', 'search-intel' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="si_description_max_length"><?php esc_html_e( 'Max Length', 'search-intel' ); ?></label></th>
                    <td>
                        <input type="number" id="si_description_max_length" name="search_intel_settings[description_max_length]" value="<?php echo esc_attr( $settings['description_max_length'] ); ?>" min="50" max="320" step="1" class="small-text" />
                        <span class="description"><?php esc_html_e( 'characters (recommended: 150-160)', 'search-intel' ); ?></span>
                    </td>
                </tr>
            </table>
        </div>

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'Robots Defaults', 'search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="si_robots_default"><?php esc_html_e( 'Default for New Posts', 'search-intel' ); ?></label></th>
                    <td>
                        <select id="si_robots_default" name="search_intel_settings[robots_default]">
                            <option value="index" <?php selected( $settings['robots_default'], 'index' ); ?>><?php esc_html_e( 'Index (default)', 'search-intel' ); ?></option>
                            <option value="noindex" <?php selected( $settings['robots_default'], 'noindex' ); ?>><?php esc_html_e( 'Noindex', 'search-intel' ); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e( 'Default robots directive for newly created posts. Can be overridden per-post.', 'search-intel' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'Search Engine Ping', 'search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Ping on Publish', 'search-intel' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="search_intel_settings[ping_search_engines]" value="1" <?php checked( $settings['ping_search_engines'] ); ?> />
                            <?php esc_html_e( 'Notify Google and Bing when new content is published', 'search-intel' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Sends a ping to search engine sitemap submission endpoints.', 'search-intel' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'Title Templates', 'search-intel' ); ?></h2>
            <p class="description" style="margin-bottom:12px;">
                <?php esc_html_e( 'Customize the title format for each page type. Available variables:', 'search-intel' ); ?>
                <code>%title%</code> <code>%sitename%</code> <code>%sep%</code> <code>%category%</code> <code>%tag%</code> <code>%date%</code> <code>%author%</code> <code>%page%</code> <code>%excerpt%</code>
            </p>
            <?php
            $templates = $settings['title_templates'] ?? \Search_Intel\Title_Engine::get_default_templates();
            $contexts  = [
                'single'   => __( 'Single Posts', 'search-intel' ),
                'page'     => __( 'Pages', 'search-intel' ),
                'category' => __( 'Category Archives', 'search-intel' ),
                'tag'      => __( 'Tag Archives', 'search-intel' ),
                'author'   => __( 'Author Archives', 'search-intel' ),
                'search'   => __( 'Search Results', 'search-intel' ),
                '404'      => __( '404 Page', 'search-intel' ),
                'homepage' => __( 'Homepage', 'search-intel' ),
            ];
            ?>
            <table class="form-table">
                <?php foreach ( $contexts as $key => $label ) : ?>
                    <tr>
                        <th><label for="si_title_tpl_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
                        <td>
                            <input type="text" id="si_title_tpl_<?php echo esc_attr( $key ); ?>" name="search_intel_settings[title_templates][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $templates[ $key ] ?? '' ); ?>" class="regular-text" />
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <?php submit_button( __( 'Save SEO Settings', 'search-intel' ) ); ?>
    </form>
</div>
