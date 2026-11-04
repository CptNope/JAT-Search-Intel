<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- View template included inside a class method; variables are method-scoped.
$profiles = get_option( 'search_intel_social_profiles', [] );
?>
<div class="wrap si-settings">
    <h1><span class="dashicons dashicons-share"></span> <?php esc_html_e( 'Search Intel — Social Profiles', 'jat-search-intel' ); ?></h1>
    <p class="si-settings-intro"><?php esc_html_e( 'Social profile URLs are added to your Organization/Person schema as sameAs properties, strengthening your knowledge graph presence.', 'jat-search-intel' ); ?></p>

    <form method="post" action="options.php">
        <?php settings_fields( 'search_intel_social_group' ); ?>

        <div class="si-settings-card">
            <h2><?php esc_html_e( 'Social Profile URLs', 'jat-search-intel' ); ?></h2>
            <table class="form-table">
                <?php
                $platforms = [
                    'facebook'  => [ 'Facebook', 'https://facebook.com/yourpage' ],
                    'twitter'   => [ 'X (Twitter)', 'https://x.com/youraccount' ],
                    'instagram' => [ 'Instagram', 'https://instagram.com/youraccount' ],
                    'linkedin'  => [ 'LinkedIn', 'https://linkedin.com/company/yourcompany' ],
                    'youtube'   => [ 'YouTube', 'https://youtube.com/@yourchannel' ],
                    'pinterest' => [ 'Pinterest', 'https://pinterest.com/youraccount' ],
                    'tiktok'    => [ 'TikTok', 'https://tiktok.com/@youraccount' ],
                    'github'    => [ 'GitHub', 'https://github.com/youraccount' ],
                ];
                foreach ( $platforms as $key => $info ) :
                    ?>
                    <tr>
                        <th><label for="si_social_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $info[0] ); ?></label></th>
                        <td>
                            <input type="url" id="si_social_<?php echo esc_attr( $key ); ?>" name="search_intel_social_profiles[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_url( $profiles[ $key ] ?? '' ); ?>" class="regular-text" placeholder="<?php echo esc_attr( $info[1] ); ?>" />
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <?php submit_button( __( 'Save Social Profiles', 'jat-search-intel' ) ); ?>
    </form>
</div>
