<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- View template included inside a class method; variables are method-scoped.
$settings = get_option( 'jat_search_intel_settings', [] );
$settings = wp_parse_args( $settings, \JAT_Search_Intel\Core::get_default_settings() );
?>
<div class="wrap si-settings">
    <h1><span class="dashicons dashicons-search"></span> <?php esc_html_e( 'JAT Search Intel — SEO Settings', 'jat-search-intel' ); ?></h1>

    <form method="post" action="options.php">
        <?php settings_fields( 'jat_search_intel_settings_group' ); ?>
        <input type="hidden" name="jat_search_intel_settings[_si_page]" value="seo" />

        <?php // Settings from other pages are preserved by the page-scoped sanitizer. ?>

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'Open Graph', 'jat-search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Enable Open Graph', 'jat-search-intel' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="jat_search_intel_settings[enable_og]" value="1" <?php checked( $settings['enable_og'] ); ?> />
                            <?php esc_html_e( 'Output Open Graph meta tags for Facebook, LinkedIn, etc.', 'jat-search-intel' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'Twitter Cards', 'jat-search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Enable Twitter Cards', 'jat-search-intel' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="jat_search_intel_settings[enable_twitter]" value="1" <?php checked( $settings['enable_twitter'] ); ?> />
                            <?php esc_html_e( 'Output Twitter Card meta tags', 'jat-search-intel' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><label for="si_twitter_card_type"><?php esc_html_e( 'Card Type', 'jat-search-intel' ); ?></label></th>
                    <td>
                        <select id="si_twitter_card_type" name="jat_search_intel_settings[twitter_card_type]">
                            <option value="summary_large_image" <?php selected( $settings['twitter_card_type'], 'summary_large_image' ); ?>><?php esc_html_e( 'Summary with Large Image', 'jat-search-intel' ); ?></option>
                            <option value="summary" <?php selected( $settings['twitter_card_type'], 'summary' ); ?>><?php esc_html_e( 'Summary', 'jat-search-intel' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="si_twitter_site"><?php esc_html_e( 'Twitter @username', 'jat-search-intel' ); ?></label></th>
                    <td>
                        <input type="text" id="si_twitter_site" name="jat_search_intel_settings[twitter_site]" value="<?php echo esc_attr( $settings['twitter_site'] ); ?>" placeholder="@yourbrand" class="regular-text" />
                    </td>
                </tr>
            </table>
        </div>

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'Canonical & Robots', 'jat-search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Canonical URLs', 'jat-search-intel' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="jat_search_intel_settings[enable_canonical]" value="1" <?php checked( $settings['enable_canonical'] ); ?> />
                            <?php esc_html_e( 'Auto-generate canonical URLs', 'jat-search-intel' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Robots Meta', 'jat-search-intel' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="jat_search_intel_settings[enable_robots_meta]" value="1" <?php checked( $settings['enable_robots_meta'] ); ?> />
                            <?php esc_html_e( 'Output robots meta tags', 'jat-search-intel' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Noindex Rules', 'jat-search-intel' ); ?></th>
                    <td>
                        <label style="display:block; margin-bottom:6px;">
                            <input type="checkbox" name="jat_search_intel_settings[noindex_search]" value="1" <?php checked( $settings['noindex_search'] ); ?> />
                            <?php esc_html_e( 'Noindex search results pages', 'jat-search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:6px;">
                            <input type="checkbox" name="jat_search_intel_settings[noindex_archives]" value="1" <?php checked( $settings['noindex_archives'] ); ?> />
                            <?php esc_html_e( 'Noindex date and author archives', 'jat-search-intel' ); ?>
                        </label>
                        <label style="display:block; margin-bottom:6px;">
                            <input type="checkbox" name="jat_search_intel_settings[noindex_404]" value="1" <?php checked( $settings['noindex_404'] ); ?> />
                            <?php esc_html_e( 'Noindex 404 pages', 'jat-search-intel' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'Default Open Graph Image', 'jat-search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="si_default_og_image"><?php esc_html_e( 'Fallback OG Image URL', 'jat-search-intel' ); ?></label></th>
                    <td>
                        <input type="url" id="si_default_og_image" name="jat_search_intel_settings[default_og_image]" value="<?php echo esc_url( $settings['default_og_image'] ); ?>" class="regular-text" />
                        <p class="description"><?php esc_html_e( 'Used when a post has no featured image or custom social image.', 'jat-search-intel' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'Meta Description', 'jat-search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="si_description_fallback"><?php esc_html_e( 'Fallback Behavior', 'jat-search-intel' ); ?></label></th>
                    <td>
                        <select id="si_description_fallback" name="jat_search_intel_settings[description_fallback]">
                            <option value="excerpt" <?php selected( $settings['description_fallback'], 'excerpt' ); ?>><?php esc_html_e( 'Use excerpt, then trim content', 'jat-search-intel' ); ?></option>
                            <option value="content" <?php selected( $settings['description_fallback'], 'content' ); ?>><?php esc_html_e( 'Trim content directly', 'jat-search-intel' ); ?></option>
                            <option value="empty" <?php selected( $settings['description_fallback'], 'empty' ); ?>><?php esc_html_e( 'Leave empty if not set', 'jat-search-intel' ); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e( 'How to generate the meta description when none is manually set.', 'jat-search-intel' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="si_description_max_length"><?php esc_html_e( 'Max Length', 'jat-search-intel' ); ?></label></th>
                    <td>
                        <input type="number" id="si_description_max_length" name="jat_search_intel_settings[description_max_length]" value="<?php echo esc_attr( $settings['description_max_length'] ); ?>" min="50" max="320" step="1" class="small-text" />
                        <span class="description"><?php esc_html_e( 'characters (recommended: 150-160)', 'jat-search-intel' ); ?></span>
                    </td>
                </tr>
            </table>
        </div>

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'Robots Defaults', 'jat-search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="si_robots_default"><?php esc_html_e( 'Default for New Posts', 'jat-search-intel' ); ?></label></th>
                    <td>
                        <select id="si_robots_default" name="jat_search_intel_settings[robots_default]">
                            <option value="index" <?php selected( $settings['robots_default'], 'index' ); ?>><?php esc_html_e( 'Index (default)', 'jat-search-intel' ); ?></option>
                            <option value="noindex" <?php selected( $settings['robots_default'], 'noindex' ); ?>><?php esc_html_e( 'Noindex', 'jat-search-intel' ); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e( 'Default robots directive for newly created posts. Can be overridden per-post.', 'jat-search-intel' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>


        <div class="si-settings-card">
            <h2><?php esc_html_e( 'Images & Attachments', 'jat-search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Automatic Alt Text', 'jat-search-intel' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="jat_search_intel_settings[enable_image_alt]" value="1" <?php checked( ! empty( $settings['enable_image_alt'] ) ); ?> />
                            <?php esc_html_e( 'Fill missing image alt text from the image title or file name (on upload and on the front end)', 'jat-search-intel' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Alt text you write yourself is never changed.', 'jat-search-intel' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Attachment Pages', 'jat-search-intel' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="jat_search_intel_settings[redirect_attachments]" value="1" <?php checked( ! empty( $settings['redirect_attachments'] ) ); ?> />
                            <?php esc_html_e( 'Redirect media attachment pages to their parent post (or the file) with a 301', 'jat-search-intel' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Attachment pages are thin content; redirecting them avoids index bloat.', 'jat-search-intel' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'RSS Feed', 'jat-search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Feed Backlink', 'jat-search-intel' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="jat_search_intel_settings[enable_rss_footer]" value="1" <?php checked( ! empty( $settings['enable_rss_footer'] ) ); ?> />
                            <?php esc_html_e( 'Add a line after every feed item linking back to the original post', 'jat-search-intel' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><label for="si_rss_after_content"><?php esc_html_e( 'Text', 'jat-search-intel' ); ?></label></th>
                    <td>
                        <textarea id="si_rss_after_content" name="jat_search_intel_settings[rss_after_content]" rows="2" class="large-text"><?php echo esc_textarea( (string) ( $settings['rss_after_content'] ?? '' ) ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Placeholders: %%POSTLINK%%, %%BLOGLINK%%, %%BLOGDESCLINK%%, %%AUTHORLINK%%. Scrapers that copy your feed will carry these links back to you.', 'jat-search-intel' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'Title Templates', 'jat-search-intel' ); ?></h2>
            <p class="description" style="margin-bottom:12px;">
                <?php esc_html_e( 'Customize the title format for each page type. Available variables:', 'jat-search-intel' ); ?>
                <code>%title%</code> <code>%sitename%</code> <code>%sep%</code> <code>%category%</code> <code>%tag%</code> <code>%date%</code> <code>%author%</code> <code>%page%</code> <code>%excerpt%</code>
            </p>
            <?php
            $templates = $settings['title_templates'] ?? \JAT_Search_Intel\Title_Engine::get_default_templates();
            $contexts  = [
                'single'   => __( 'Single Posts', 'jat-search-intel' ),
                'page'     => __( 'Pages', 'jat-search-intel' ),
                'category' => __( 'Category Archives', 'jat-search-intel' ),
                'tag'      => __( 'Tag Archives', 'jat-search-intel' ),
                'author'   => __( 'Author Archives', 'jat-search-intel' ),
                'search'   => __( 'Search Results', 'jat-search-intel' ),
                '404'      => __( '404 Page', 'jat-search-intel' ),
                'homepage' => __( 'Homepage', 'jat-search-intel' ),
            ];
            ?>
            <table class="form-table">
                <?php foreach ( $contexts as $key => $label ) : ?>
                    <tr>
                        <th><label for="si_title_tpl_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
                        <td>
                            <input type="text" id="si_title_tpl_<?php echo esc_attr( $key ); ?>" name="jat_search_intel_settings[title_templates][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $templates[ $key ] ?? '' ); ?>" class="regular-text" />
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <?php submit_button( __( 'Save SEO Settings', 'jat-search-intel' ) ); ?>
    </form>
</div>
