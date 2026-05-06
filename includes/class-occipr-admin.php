<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OCCIPR_Admin {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'admin_footer', [ __CLASS__, 'render_footer' ] );
    }

    public static function register_menus() {
        add_menu_page(
            'OCCI Parish Register',
            'Sacramental Records',
            'occipr_view_records',
            'occi-parish-register',
            [ __CLASS__, 'dashboard_page' ],
            'dashicons-book-alt',
            30
        );
        add_submenu_page( 'occi-parish-register', 'Dashboard',          'Dashboard',          'occipr_view_records',   'occi-parish-register', [ __CLASS__, 'dashboard_page' ] );
        add_submenu_page( 'occi-parish-register', 'Baptisms',           'Baptisms',           'occipr_view_records',   'occi-baptisms',            [ 'OCCIPR_Baptism', 'page' ] );
        add_submenu_page( 'occi-parish-register', 'Confirmations',      'Confirmations',      'occipr_view_records',   'occi-confirmations',       [ 'OCCIPR_Confirmation', 'page' ] );
        add_submenu_page( 'occi-parish-register', 'Marriages',          'Marriages',          'occipr_view_records',   'occi-marriages',           [ 'OCCIPR_Marriage', 'page' ] );
        add_submenu_page( 'occi-parish-register', 'Deaths',             'Deaths',             'occipr_view_records',   'occi-deaths',              [ 'OCCIPR_Death', 'page' ] );
        add_submenu_page( 'occi-parish-register', 'First Communions',   'First Communions',   'occipr_view_records',   'occi-communions',          [ 'OCCIPR_Communion', 'page' ] );
        add_submenu_page( 'occi-parish-register', 'Ordinations',        'Ordinations',        'occipr_view_records',   'occi-ordinations',         [ 'OCCIPR_Ordination', 'page' ] );
        add_submenu_page( 'occi-parish-register', 'Import / Export',      'Import / Export',      'occipr_view_records',   'occi-import-export',       [ 'OCCI_ImportExport', 'page' ] );
        add_submenu_page( 'occi-parish-register', 'Person Report',      'Person Report',      'occipr_view_records',   'occi-report',              [ 'OCCIPR_Report', 'page' ] );
        add_submenu_page( 'occi-parish-register', 'Parishes',           'Parishes',           'occipr_manage_records', 'occi-parishes',            [ 'OCCIPR_Parishes', 'page' ] );
        add_submenu_page( 'occi-parish-register', 'Certificate Settings','Certificate Settings','occipr_manage_records','occi-cert-settings',      [ 'OCCIPR_Certificates', 'settings_page' ] );
    }

    public static function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'occi' ) === false ) return;
        wp_enqueue_style( 'occi-admin', OCCI_PR_PLUGIN_URL . 'admin/css/occi-admin.css', [], OCCI_PR_VERSION );
        wp_enqueue_script( 'occi-admin', OCCI_PR_PLUGIN_URL . 'admin/js/occi-admin.js', [ 'jquery' ], OCCI_PR_VERSION, true );
        // Media uploader for certificate settings
        if ( strpos( $hook, 'cert-settings' ) !== false || strpos( $hook, 'occi-parishes' ) !== false ) {
            wp_enqueue_media();
        }
    }

    public static function dashboard_page() {
        global $wpdb;
        if ( ! current_user_can( 'occipr_view_records' ) ) { wp_die( 'Access denied.' ); }
        $counts = [
            'Baptisms'         => [ $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}occipr_baptisms" ),      'occi-baptisms' ],
            'Confirmations'    => [ $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}occipr_confirmations" ), 'occi-confirmations' ],
            'Marriages'        => [ $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}occipr_marriages" ),     'occi-marriages' ],
            'Deaths'           => [ $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}occipr_deaths" ),        'occi-deaths' ],
            'First Communions' => [ $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}occipr_communions" ),   'occi-communions' ],
            'Ordinations'      => [ $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}occipr_ordinations" ),  'occi-ordinations' ],
        ];
        ?>
        <div class="wrap occi-wrap">
            <h1><span class="dashicons dashicons-book-alt"></span> OCCI Parish Register</h1>
            
            <div class="occi-dashboard-grid">
                <?php foreach ( $counts as $label => [ $count, $slug ] ) : ?>
                <div class="occi-stat-card">
                    <div class="occi-stat-number"><?php echo intval( $count ); ?></div>
                    <div class="occi-stat-label"><?php echo esc_html( $label ); ?></div>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>" class="button button-primary">View Register</a>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="occi-dashboard-tools">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-report' ) ); ?>" class="button button-secondary occi-tool-btn">&#128269; Person Report</a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-cert-settings' ) ); ?>" class="button button-secondary occi-tool-btn">&#127881; Certificate Settings</a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=occi-parishes' ) ); ?>" class="button button-secondary occi-tool-btn">&#127776; Manage Parishes</a>
            </div>
            <div class="occi-notice">
                <p><strong>Pax et Bonum.</strong> This database is confidential. Access is restricted to authorized diocesan and parish personnel only. All records are permanent canonical documents.</p>
            </div>
        </div>
        <?php
    }

    public static function render_footer() {
        $screen = get_current_screen();
        if ( ! $screen || strpos( $screen->id, 'occi' ) === false ) return;
        ?>
        <div class="occi-admin-footer">
            <span>Old Catholic Churches International &mdash; Parish Register</span>
            <span>v<?php echo esc_html( OCCI_PR_VERSION ); ?> &mdash; <em>Pax et Bonum</em></span>
        </div>
        <?php
    }

}