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
        add_submenu_page( 'occi-parish-register', 'Baptisms',           'Baptisms',           'occipr_view_records',   'occipr-baptisms',            [ 'OCCIPR_Baptism', 'page' ] );
        add_submenu_page( 'occi-parish-register', 'Confirmations',      'Confirmations',      'occipr_view_records',   'occipr-confirmations',       [ 'OCCIPR_Confirmation', 'page' ] );
        add_submenu_page( 'occi-parish-register', 'Marriages',          'Marriages',          'occipr_view_records',   'occipr-marriages',           [ 'OCCIPR_Marriage', 'page' ] );
        add_submenu_page( 'occi-parish-register', 'Deaths',             'Deaths',             'occipr_view_records',   'occipr-deaths',              [ 'OCCIPR_Death', 'page' ] );
        add_submenu_page( 'occi-parish-register', 'First Communions',   'First Communions',   'occipr_view_records',   'occipr-communions',          [ 'OCCIPR_Communion', 'page' ] );
        add_submenu_page( 'occi-parish-register', 'Ordinations',        'Ordinations',        'occipr_view_records',   'occipr-ordinations',         [ 'OCCIPR_Ordination', 'page' ] );
        add_submenu_page( 'occi-parish-register', 'Parish Directory',    'Parish Directory',    'occipr_view_records',   'occipr-directory',           [ 'OCCIPR_Directory', 'page' ] );
        add_submenu_page( 'occi-parish-register', 'Mass Attendance',     'Mass Attendance',     'occipr_view_records',   'occipr-attendance',          [ 'OCCIPR_Attendance', 'page' ] );
        add_submenu_page( 'occi-parish-register', 'Donations',           'Donations',           'occipr_view_records',   'occipr-donations',           [ 'OCCIPR_Donations', 'page' ] );
        add_submenu_page( 'occi-parish-register', 'OCIA',                'OCIA',                'occipr_view_records',   'occipr-ocia',                [ 'OCCIPR_OCIA', 'page' ] );
        add_submenu_page( 'occi-parish-register', 'PSR / Rel. Ed.',       'PSR / Rel. Ed.',       'occipr_view_records',   'occipr-psr',                 [ 'OCCIPR_PSR', 'page' ] );
        add_submenu_page( 'occi-parish-register', 'Parish Reports',      'Parish Reports',      'occipr_view_records',   'occipr-parish-reports',      [ 'OCCIPR_ParishReports', 'page' ] );
        add_submenu_page( 'occi-parish-register', 'Import / Export',      'Import / Export',      'occipr_view_records',   'occipr-import-export',       [ 'OCCIPR_ImportExport', 'page' ] );
        add_submenu_page( 'occi-parish-register', 'Person Report',      'Person Report',      'occipr_view_records',   'occipr-report',              [ 'OCCIPR_Report', 'page' ] );
        add_submenu_page( 'occi-parish-register', 'Parishes',           'Parishes',           'occipr_manage_records', 'occipr-parishes',          [ 'OCCIPR_Parishes', 'page' ] );
        add_submenu_page( 'occi-parish-register', 'Certificate Settings','Certificate Settings','occipr_manage_records','occipr-cert-settings',      [ 'OCCIPR_Certificates', 'settings_page' ] );
    }

    public static function enqueue_assets( $hook ) {
        $page     = sanitize_key( $_GET['page'] ?? '' );
        $pr_pages = [
            'occi-parish-register', 'occipr-baptisms', 'occipr-confirmations',
            'occipr-marriages', 'occipr-deaths', 'occipr-communions', 'occipr-ordinations',
            'occipr-directory', 'occipr-attendance', 'occipr-donations', 'occipr-ocia', 'occipr-psr', 'occipr-parish-reports', 'occipr-import-export', 'occipr-report',
            'occipr-parishes', 'occipr-cert-settings',
        ];
        if ( ! in_array( $page, $pr_pages, true ) ) return;
        wp_enqueue_style( 'occi-admin', OCCI_PR_PLUGIN_URL . 'admin/css/occi-admin.css', [], OCCI_PR_VERSION );
        wp_enqueue_script( 'occi-admin', OCCI_PR_PLUGIN_URL . 'admin/js/occi-admin.js', [ 'jquery' ], OCCI_PR_VERSION, true );
        // Media uploader for certificate settings and parish directory
        if ( strpos( $hook, 'cert-settings' ) !== false
          || strpos( $hook, 'occipr-parishes' ) !== false
          || strpos( $hook, 'occipr-directory' ) !== false ) {
            wp_enqueue_media();
        }
    }

    public static function dashboard_page() {
        global $wpdb;
        if ( ! current_user_can( 'occipr_view_records' ) ) { wp_die( 'Access denied.' ); }
        $year   = date( 'Y' );
        $counts = [
            'Households'       => [ $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}occipr_households" ),   'occipr-directory' ],
            'Baptisms'         => [ $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}occipr_baptisms" ),      'occipr-baptisms' ],
            'Confirmations'    => [ $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}occipr_confirmations" ), 'occipr-confirmations' ],
            'Marriages'        => [ $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}occipr_marriages" ),     'occipr-marriages' ],
            'Deaths'           => [ $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}occipr_deaths" ),        'occipr-deaths' ],
            'First Communions' => [ $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}occipr_communions" ),   'occipr-communions' ],
            'Ordinations'      => [ $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}occipr_ordinations" ),  'occipr-ordinations' ],
        ];
        $att_services = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}occipr_attendance WHERE YEAR(service_date) = %d", $year
        ) );
        $att_avg = (int) round( (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT AVG(headcount) FROM {$wpdb->prefix}occipr_attendance WHERE YEAR(service_date) = %d", $year
        ) ) );
        $don_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}occipr_donations WHERE YEAR(donation_date) = %d", $year
        ) );
        $don_total = (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(amount) FROM {$wpdb->prefix}occipr_donations WHERE YEAR(donation_date) = %d", $year
        ) );
        $ocia_active = OCCIPR_OCIA::active_count();
        $psr_active  = OCCIPR_PSR::active_count();
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

                <!-- Attendance stat card -->
                <div class="occi-stat-card">
                    <div class="occi-stat-number"><?php echo $att_services; ?></div>
                    <div class="occi-stat-label">Services (<?php echo $year; ?>)</div>
                    <?php if ( $att_avg ) : ?>
                    <div class="occi-small" style="margin-bottom:8px;">Avg. <?php echo $att_avg; ?> per service</div>
                    <?php endif; ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-attendance' ) ); ?>" class="button button-primary">View Attendance</a>
                </div>

                <!-- Donations stat card -->
                <div class="occi-stat-card">
                    <div class="occi-stat-number">$<?php echo number_format( $don_total, 0 ); ?></div>
                    <div class="occi-stat-label">Donations (<?php echo $year; ?>)</div>
                    <?php if ( $don_count ) : ?>
                    <div class="occi-small" style="margin-bottom:8px;"><?php echo $don_count; ?> records</div>
                    <?php endif; ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-donations' ) ); ?>" class="button button-primary">View Donations</a>
                </div>

                <!-- OCIA stat card -->
                <div class="occi-stat-card">
                    <div class="occi-stat-number"><?php echo $ocia_active; ?></div>
                    <div class="occi-stat-label">OCIA Active</div>
                    <div class="occi-small" style="margin-bottom:8px;">Inquirers, Catechumens &amp; Elect</div>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-ocia' ) ); ?>" class="button button-primary">View OCIA</a>
                </div>

                <!-- PSR stat card -->
                <div class="occi-stat-card">
                    <div class="occi-stat-number"><?php echo $psr_active; ?></div>
                    <div class="occi-stat-label">PSR Students</div>
                    <div class="occi-small" style="margin-bottom:8px;">Currently Active</div>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-psr' ) ); ?>" class="button button-primary">View PSR</a>
                </div>
            </div>
            <div class="occi-dashboard-tools">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-report' ) ); ?>" class="button button-secondary occi-tool-btn">&#128269; Person Report</a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-cert-settings' ) ); ?>" class="button button-secondary occi-tool-btn">&#127881; Certificate Settings</a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-parishes' ) ); ?>" class="button button-secondary occi-tool-btn">&#127776; Manage Parishes</a>
            </div>
            <div class="occi-notice">
                <p><strong>Pax et Bonum.</strong> This database is confidential. Access is restricted to authorized diocesan and parish personnel only. All records are permanent canonical documents.</p>
            </div>
        </div>
        <?php
    }

    public static function render_footer() {
        $page     = sanitize_key( $_GET['page'] ?? '' );
        $pr_pages = [
            'occi-parish-register', 'occipr-baptisms', 'occipr-confirmations',
            'occipr-marriages', 'occipr-deaths', 'occipr-communions', 'occipr-ordinations',
            'occipr-directory', 'occipr-attendance', 'occipr-donations', 'occipr-ocia', 'occipr-psr', 'occipr-parish-reports', 'occipr-import-export', 'occipr-report',
            'occipr-parishes', 'occipr-cert-settings',
        ];
        if ( ! in_array( $page, $pr_pages, true ) ) return;
        ?>
        <div class="occipr-admin-footer">
            <span>Old Catholic Churches International &mdash; Parish Register</span>
            <span>v<?php echo esc_html( OCCI_PR_VERSION ); ?> &mdash; <em>Pax et Bonum</em></span>
        </div>
        <?php
    }

}