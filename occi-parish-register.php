<?php
/**
 * Plugin Name:       OCCI Parish Register
 * Plugin URI:        https://myocci.org
 * Description:       Parish-level sacramental record database for Old Catholic Churches International. Manages Baptism, Confirmation, Marriage, Death, First Communion, and Ordination registers.
 * Version:           2.0.2
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Old Catholic Churches International
 * Author URI:        https://myocci.org
 * License:           GPL-2.0-or-later
 * Text Domain:       occi-parish-register
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'OCCI_PR_VERSION',    '2.0.2' );
define( 'OCCI_PR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OCCI_PR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once OCCI_PR_PLUGIN_DIR . 'includes/functions.php';
require_once OCCI_PR_PLUGIN_DIR . 'includes/class-occipr-database.php';
require_once OCCI_PR_PLUGIN_DIR . 'includes/class-occipr-admin.php';
require_once OCCI_PR_PLUGIN_DIR . 'includes/class-occipr-parishes.php';
require_once OCCI_PR_PLUGIN_DIR . 'includes/class-occipr-baptism.php';
require_once OCCI_PR_PLUGIN_DIR . 'includes/class-occipr-confirmation.php';
require_once OCCI_PR_PLUGIN_DIR . 'includes/class-occipr-marriage.php';
require_once OCCI_PR_PLUGIN_DIR . 'includes/class-occipr-death.php';
require_once OCCI_PR_PLUGIN_DIR . 'includes/class-occipr-communion.php';
require_once OCCI_PR_PLUGIN_DIR . 'includes/class-occipr-ordination.php';
require_once OCCI_PR_PLUGIN_DIR . 'includes/class-occipr-certificates.php';
require_once OCCI_PR_PLUGIN_DIR . 'includes/class-occipr-report.php';
require_once OCCI_PR_PLUGIN_DIR . 'includes/class-occipr-import-export.php';
require_once OCCI_PR_PLUGIN_DIR . 'includes/class-occipr-directory.php';
require_once OCCI_PR_PLUGIN_DIR . 'includes/class-occipr-attendance.php';
require_once OCCI_PR_PLUGIN_DIR . 'includes/class-occipr-donations.php';
require_once OCCI_PR_PLUGIN_DIR . 'includes/class-occipr-psr.php';
require_once OCCI_PR_PLUGIN_DIR . 'includes/class-occipr-ocia.php';
require_once OCCI_PR_PLUGIN_DIR . 'includes/class-occipr-registration.php';
require_once OCCI_PR_PLUGIN_DIR . 'includes/class-occipr-parish-reports.php';
require_once OCCI_PR_PLUGIN_DIR . 'includes/class-occipr-updater.php';

register_activation_hook( __FILE__, [ 'OCCIPR_Database', 'install' ] );
register_deactivation_hook( __FILE__, [ 'OCCIPR_Database', 'deactivate' ] );

add_action( 'plugins_loaded', function () {
    global $wpdb;
    $needs_install = get_option( 'occi_pr_db_version' ) !== OCCI_PR_VERSION
        || ! $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}occipr_households'" );
    if ( $needs_install ) {
        OCCIPR_Database::install();
    }
    OCCIPR_Admin::init();
    OCCIPR_Parishes::init();
    OCCIPR_Baptism::init();
    OCCIPR_Confirmation::init();
    OCCIPR_Marriage::init();
    OCCIPR_Death::init();
    OCCIPR_Communion::init();
    OCCIPR_Ordination::init();
    OCCIPR_Certificates::init();
    OCCIPR_Report::init();
    OCCIPR_ImportExport::init();
    OCCIPR_Directory::init();
    OCCIPR_Attendance::init();
    OCCIPR_Donations::init();
    OCCIPR_PSR::init();
    OCCIPR_OCIA::init();
    OCCIPR_Registration::init();
    OCCIPR_Updater::init();
} );
