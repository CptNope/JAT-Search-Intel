<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
$settings = get_option( 'search_intel_settings', [] );
$settings = wp_parse_args( $settings, \Search_Intel\Core::get_default_settings() );
?>
<div class="wrap si-settings">
    <h1><span class="dashicons dashicons-superhero-alt"></span> <?php esc_html_e( 'Search Intel — GEO Settings', 'search-intel' ); ?></h1>
    <p class="si-settings-intro"><?php esc_html_e( 'Generative Engine Optimization — optimize for AI-powered search (ChatGPT, Perplexity, Google AI Overviews, etc.).', 'search-intel' ); ?></p>

    <form method="post" action="options.php">
        <?php settings_fields( 'search_intel_settings_group' ); ?>

        <?php
        // Preserve all non-GEO settings as hidden
        $geo_keys = [ 'enable_llms_txt', 'enable_ai_meta', 'enable_entity_markup', 'content_freshness', 'author_expertise' ];
        foreach ( $settings as $key => $val ) :
            if ( in_array( $key, $geo_keys, true ) ) continue;
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
            <h2><?php esc_html_e( 'AI Meta Tags', 'search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Enable AI Meta', 'search-intel' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="search_intel_settings[enable_ai_meta]" value="1" <?php checked( $settings['enable_ai_meta'] ); ?> />
                            <?php esc_html_e( 'Output AI-specific meta tags (content type, topics, freshness signals)', 'search-intel' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'llms.txt', 'search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Enable llms.txt', 'search-intel' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="search_intel_settings[enable_llms_txt]" value="1" <?php checked( $settings['enable_llms_txt'] ); ?> />
                            <?php esc_html_e( 'Serve a machine-readable site summary at /llms.txt for LLM crawlers', 'search-intel' ); ?>
                        </label>
                        <p class="description">
                            <?php if ( $settings['enable_llms_txt'] ) : ?>
                                <?php esc_html_e( 'Your llms.txt is live at:', 'search-intel' ); ?>
                                <a href="<?php echo esc_url( home_url( '/llms.txt' ) ); ?>" target="_blank"><?php echo esc_url( home_url( '/llms.txt' ) ); ?></a>
                                <br />
                                <?php esc_html_e( 'Full version:', 'search-intel' ); ?>
                                <a href="<?php echo esc_url( home_url( '/llms-full.txt' ) ); ?>" target="_blank"><?php echo esc_url( home_url( '/llms-full.txt' ) ); ?></a>
                            <?php endif; ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'Entity & Semantic Markup', 'search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Enable Entity Markup', 'search-intel' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="search_intel_settings[enable_entity_markup]" value="1" <?php checked( $settings['enable_entity_markup'] ); ?> />
                            <?php esc_html_e( 'Wrap defined entities with Schema.org microdata for AI comprehension', 'search-intel' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'Content Freshness', 'search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Freshness Signals', 'search-intel' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="search_intel_settings[content_freshness]" value="1" <?php checked( $settings['content_freshness'] ); ?> />
                            <?php esc_html_e( 'Output content freshness meta tags (very-fresh, fresh, recent, aging, evergreen)', 'search-intel' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'E-E-A-T (Author Authority)', 'search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="si_author_expertise"><?php esc_html_e( 'Default Expertise Area', 'search-intel' ); ?></label></th>
                    <td>
                        <input type="text" id="si_author_expertise" name="search_intel_settings[author_expertise]" value="<?php echo esc_attr( $settings['author_expertise'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g., Digital Marketing, Software Engineering', 'search-intel' ); ?>" />
                        <p class="description"><?php esc_html_e( 'Fallback expertise area when authors don\'t have individual E-E-A-T data configured. Set per-author data in user profiles.', 'search-intel' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="si-settings-card si-info-card">
            <h2><?php esc_html_e( 'GEO Tips', 'search-intel' ); ?></h2>
            <ul>
                <li><?php esc_html_e( '✓ Write clear definitions — "X is defined as..." helps AI systems extract facts.', 'search-intel' ); ?></li>
                <li><?php esc_html_e( '✓ Cite authoritative sources — AI engines prefer content with verifiable claims.', 'search-intel' ); ?></li>
                <li><?php esc_html_e( '✓ Keep content updated — freshness signals influence AI citation ranking.', 'search-intel' ); ?></li>
                <li><?php esc_html_e( '✓ Use the AI Summary field to provide a concise, factual overview for LLMs.', 'search-intel' ); ?></li>
                <li><?php esc_html_e( '✓ Define entities (people, organizations, products) so AI understands context.', 'search-intel' ); ?></li>
                <li><?php esc_html_e( '✓ Fill out author profiles with credentials and expertise for E-E-A-T signals.', 'search-intel' ); ?></li>
            </ul>
        </div>

        <?php submit_button( __( 'Save GEO Settings', 'search-intel' ) ); ?>
    </form>
</div>
