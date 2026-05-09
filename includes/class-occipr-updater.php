<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OCCIPR_Updater {

    const GITHUB_REPO   = 'BishopGreer/OCCI-Parish-Register';
    const TRANSIENT_KEY = 'occi_pr_update_check';
    const CACHE_HOURS   = 12;
    const PLUGIN_SLUG   = 'occi-parish-register/occi-parish-register.php';
    const PLUGIN_BASE   = 'occi-parish-register';

    public static function init() {
        $instance = new self();

        // Hook both the WRITE filter (when WP refreshes its cache) and the READ filter
        // (when WP reads the stored transient to display the updates page). Without the
        // read filter, updates only show immediately after a cache refresh -- not on
        // subsequent page loads that hit the cached transient.
        add_filter( 'pre_set_site_transient_update_plugins', [ $instance, 'inject_update' ] );
        add_filter( 'site_transient_update_plugins',         [ $instance, 'inject_update' ] );

        add_filter( 'plugins_api',           [ $instance, 'plugin_info' ], 20, 3 );
        add_filter( 'upgrader_source_selection', [ $instance, 'fix_directory_name' ], 10, 4 );
        add_action( 'admin_init',            [ __CLASS__, 'maybe_clear_cache' ] );
    }

    // -------------------------------------------------------------------------
    // Core update injection -- runs on both read and write of the update transient
    // -------------------------------------------------------------------------

    public function inject_update( $transient ) {
        if ( ! is_object( $transient ) ) return $transient;

        $remote = $this->get_remote_info();

        if ( $remote && ! empty( $remote->version )
             && version_compare( OCCI_PR_VERSION, $remote->version, '<' ) ) {

            // Clear any "no update" entry WordPress.org may have written for our slug.
            unset( $transient->no_update[ self::PLUGIN_SLUG ] );

            $transient->response[ self::PLUGIN_SLUG ] = (object) [
                'slug'         => self::PLUGIN_BASE,
                'plugin'       => self::PLUGIN_SLUG,
                'new_version'  => $remote->version,
                'url'          => $remote->details_url,
                'package'      => $remote->download_url,
                'icons'        => self::plugin_icons(),
                'banners'      => self::plugin_banners(),
                'tested'       => $remote->tested,
                'requires'     => '6.0',
                'requires_php' => '8.0',
            ];

        } else {
            // No update available -- ensure our slug is not stuck in response.
            if ( ! isset( $transient->response[ self::PLUGIN_SLUG ] ) ) {
                $transient->no_update[ self::PLUGIN_SLUG ] = (object) [
                    'slug'        => self::PLUGIN_BASE,
                    'plugin'      => self::PLUGIN_SLUG,
                    'new_version' => OCCI_PR_VERSION,
                    'url'         => '',
                    'package'     => '',
                ];
            }
        }

        return $transient;
    }

    // -------------------------------------------------------------------------
    // Plugin information popup (View Details link)
    // -------------------------------------------------------------------------

    public function plugin_info( $result, $action, $args ) {
        if ( $action !== 'plugin_information' ) return $result;
        if ( ! isset( $args->slug ) || $args->slug !== self::PLUGIN_BASE ) return $result;

        $remote = $this->get_remote_info();
        if ( ! $remote ) return $result;

        return (object) [
            'name'           => 'OCCI Parish Register',
            'slug'           => self::PLUGIN_BASE,
            'version'        => $remote->version,
            'author'         => 'Old Catholic Churches International',
            'author_profile' => 'https://myocci.org',
            'homepage'       => 'https://github.com/' . self::GITHUB_REPO,
            'requires'       => '6.0',
            'requires_php'   => '8.0',
            'tested'         => $remote->tested,
            'last_updated'   => $remote->last_updated,
            'download_link'  => $remote->download_url,
            'icons'          => self::plugin_icons(),
            'banners'        => self::plugin_banners(),
            'sections'       => [
                'description' => 'Parish-level sacramental record database for Old Catholic Churches International.',
                'changelog'   => $remote->changelog,
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Ensure extracted folder is named occi-parish-register/ after install/update
    // -------------------------------------------------------------------------

    public function fix_directory_name( $source, $remote_source, $upgrader, $hook_extra = [] ) {
        $is_our_update  = isset( $hook_extra['plugin'] ) && $hook_extra['plugin'] === self::PLUGIN_SLUG;
        $is_our_install = isset( $hook_extra['action'] ) && $hook_extra['action'] === 'install';

        if ( ! $is_our_update && ! $is_our_install ) {
            return $source;
        }

        global $wp_filesystem;

        $correct = trailingslashit( $remote_source ) . self::PLUGIN_BASE . '/';

        if ( $source === $correct ) {
            return $source;
        }

        // Only rename if our main plugin file is inside the extracted folder.
        if ( ! $wp_filesystem->exists( trailingslashit( $source ) . 'occi-parish-register.php' ) ) {
            return $source;
        }

        if ( $wp_filesystem->is_dir( $source ) && ! $wp_filesystem->is_dir( $correct ) ) {
            if ( $wp_filesystem->move( $source, $correct ) ) {
                return $correct;
            }
        }

        return $source;
    }

    // -------------------------------------------------------------------------
    // Plugin icon and banner URLs (served from the installed plugin directory)
    // Drop the appropriately named files into assets/images/ and they appear
    // automatically on the plugin list and update detail popup.
    // -------------------------------------------------------------------------

    private static function plugin_icons(): array {
        $base = OCCI_PR_PLUGIN_URL . 'assets/images/';
        $dir  = OCCI_PR_PLUGIN_DIR . 'assets/images/';
        $icons = [];
        if ( file_exists( $dir . 'icon-128x128.png' ) ) $icons['1x'] = $base . 'icon-128x128.png';
        if ( file_exists( $dir . 'icon-256x256.png' ) ) $icons['2x'] = $base . 'icon-256x256.png';
        return $icons;
    }

    private static function plugin_banners(): array {
        $base    = OCCI_PR_PLUGIN_URL . 'assets/images/';
        $dir     = OCCI_PR_PLUGIN_DIR . 'assets/images/';
        $banners = [];
        if ( file_exists( $dir . 'banner-772x250.jpg' ) )   $banners['low']  = $base . 'banner-772x250.jpg';
        if ( file_exists( $dir . 'banner-1544x500.jpg' ) )  $banners['high'] = $base . 'banner-1544x500.jpg';
        return $banners;
    }

    // -------------------------------------------------------------------------
    // Fetch release info from GitHub (cached 12 hours)
    // -------------------------------------------------------------------------

    private function get_remote_info(): ?object {
        $cached = get_transient( self::TRANSIENT_KEY );
        if ( $cached !== false ) return $cached;

        $api_url  = 'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest';
        $response = wp_remote_get( $api_url, [
            'timeout'    => 10,
            'headers'    => [
                'Accept'     => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/OCCI-Parish-Register-' . OCCI_PR_VERSION,
            ],
        ] );

        if ( is_wp_error( $response ) ) return null;
        if ( wp_remote_retrieve_response_code( $response ) !== 200 ) return null;

        $release = json_decode( wp_remote_retrieve_body( $response ) );
        if ( ! $release || empty( $release->tag_name ) ) return null;

        $version      = ltrim( $release->tag_name, 'v' );
        $download_url = $release->zipball_url ?? '';

        // Prefer the named release asset ZIP over the raw zipball (better folder structure).
        if ( ! empty( $release->assets ) ) {
            foreach ( $release->assets as $asset ) {
                if ( isset( $asset->name ) && str_ends_with( $asset->name, '.zip' ) ) {
                    $download_url = $asset->browser_download_url;
                    break;
                }
            }
        }

        $info = (object) [
            'version'      => $version,
            'download_url' => $download_url,
            'details_url'  => $release->html_url ?? ( 'https://github.com/' . self::GITHUB_REPO . '/releases' ),
            'last_updated' => isset( $release->published_at ) ? date( 'Y-m-d', strtotime( $release->published_at ) ) : '',
            'changelog'    => '<p>' . nl2br( esc_html( $release->body ?? '' ) ) . '</p>',
            'tested'       => '6.8',
        ];

        set_transient( self::TRANSIENT_KEY, $info, HOUR_IN_SECONDS * self::CACHE_HOURS );
        return $info;
    }

    // -------------------------------------------------------------------------
    // Settings section displayed on Certificate Settings page
    // -------------------------------------------------------------------------

    public static function render_settings_section() {
        $remote = ( new self() )->get_remote_info();
        ?>
        <div class="occi-section" style="margin-top:24px;">
            <h2>Automatic Updates</h2>
            <p>This plugin checks for updates automatically via its GitHub repository. No configuration is required.</p>
            <table class="form-table">
                <tr><th>Update Source</th><td><a href="https://github.com/<?php echo esc_html( self::GITHUB_REPO ); ?>/releases" target="_blank">github.com/<?php echo esc_html( self::GITHUB_REPO ); ?></a></td></tr>
                <tr><th>Installed Version</th><td><?php echo esc_html( OCCI_PR_VERSION ); ?></td></tr>
                <tr><th>Latest Available</th><td><?php echo $remote ? esc_html( $remote->version ) : '<em style="color:#999;">Unable to reach GitHub</em>'; ?></td></tr>
                <tr><th>Check Interval</th><td>Every <?php echo self::CACHE_HOURS; ?> hours</td></tr>
            </table>
            <?php if ( current_user_can( 'update_plugins' ) ) : ?>
            <p style="margin-top:12px;">
                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=occipr-cert-settings&occi_clear_update_cache=1' ), 'occi_clear_update_cache' ) ); ?>"
                   class="button">Force Update Check Now</a>
            </p>
            <?php endif; ?>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Clear both our transient and WordPress's update_plugins transient
    // -------------------------------------------------------------------------

    public static function maybe_clear_cache() {
        if ( ! isset( $_GET['occi_clear_update_cache'] ) ) return;
        if ( ! check_admin_referer( 'occi_clear_update_cache' ) ) return;
        if ( ! current_user_can( 'update_plugins' ) ) return;
        delete_transient( self::TRANSIENT_KEY );
        delete_site_transient( 'update_plugins' );
        wp_safe_redirect( admin_url( 'admin.php?page=occipr-cert-settings&cache_cleared=1' ) );
        exit;
    }
}
