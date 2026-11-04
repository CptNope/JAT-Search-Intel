<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- View template included inside a class method; variables are method-scoped.
$settings = get_option( 'search_intel_settings', [] );
$settings = wp_parse_args( $settings, \Search_Intel\Core::get_default_settings() );
?>
<div class="wrap si-settings">
    <h1><span class="dashicons dashicons-microphone"></span> <?php esc_html_e( 'Search Intel — AEO Settings', 'jat-search-intel' ); ?></h1>
    <p class="si-settings-intro"><?php esc_html_e( 'Answer Engine Optimization — optimize for featured snippets, voice assistants, and People Also Ask boxes.', 'jat-search-intel' ); ?></p>

    <form method="post" action="options.php">
        <?php settings_fields( 'search_intel_settings_group' ); ?>
        <input type="hidden" name="search_intel_settings[_si_page]" value="aeo" />

        <?php
        // Preserve all non-AEO settings
        $all = $settings;
        $aeo_keys = [ 'enable_faq_schema', 'enable_howto_schema', 'enable_speakable', 'speakable_selectors' ];
        foreach ( $all as $key => $val ) :
            if ( in_array( $key, $aeo_keys, true ) ) continue;
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
            <h2><?php esc_html_e( 'FAQ Schema', 'jat-search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Enable FAQ Schema', 'jat-search-intel' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="search_intel_settings[enable_faq_schema]" value="1" <?php checked( $settings['enable_faq_schema'] ); ?> />
                            <?php esc_html_e( 'Generate FAQPage schema from post FAQ data and auto-detected question headings', 'jat-search-intel' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'HowTo Schema', 'jat-search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Enable HowTo Schema', 'jat-search-intel' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="search_intel_settings[enable_howto_schema]" value="1" <?php checked( $settings['enable_howto_schema'] ); ?> />
                            <?php esc_html_e( 'Generate HowTo schema from post HowTo data', 'jat-search-intel' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'Speakable (Voice Search)', 'jat-search-intel' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Enable Speakable', 'jat-search-intel' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="search_intel_settings[enable_speakable]" value="1" <?php checked( $settings['enable_speakable'] ); ?> />
                            <?php esc_html_e( 'Add SpeakableSpecification schema for voice assistant results', 'jat-search-intel' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><label for="si_speakable_selectors"><?php esc_html_e( 'Speakable CSS Selectors', 'jat-search-intel' ); ?></label></th>
                    <td>
                        <textarea id="si_speakable_selectors" name="search_intel_settings[speakable_selectors]" rows="4" class="large-text"><?php echo esc_textarea( implode( "\n", $settings['speakable_selectors'] ?? [] ) ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'One CSS selector per line. These identify which parts of your content can be read aloud by voice assistants.', 'jat-search-intel' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="si-settings-card si-info-card">
            <h2><?php esc_html_e( 'AEO Tips', 'jat-search-intel' ); ?></h2>
            <ul>
                <li><?php esc_html_e( '✓ Use question-style H2/H3 headings — they are auto-detected as FAQ candidates.', 'jat-search-intel' ); ?></li>
                <li><?php esc_html_e( '✓ Provide concise, direct answers in the first paragraph after each question heading.', 'jat-search-intel' ); ?></li>
                <li><?php esc_html_e( '✓ Use ordered lists for step-by-step content to trigger list snippets.', 'jat-search-intel' ); ?></li>
                <li><?php esc_html_e( '✓ Use tables for comparison data to trigger table snippets.', 'jat-search-intel' ); ?></li>
                <li><?php esc_html_e( '✓ Keep paragraphs under 50 words for better featured snippet eligibility.', 'jat-search-intel' ); ?></li>
            </ul>
        </div>

        <?php submit_button( __( 'Save AEO Settings', 'jat-search-intel' ) ); ?>
    </form>
</div>
