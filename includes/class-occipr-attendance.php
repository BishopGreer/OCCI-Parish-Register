<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OCCIPR_Attendance {

    // -------------------------------------------------------------------------
    // OPTION LISTS
    // -------------------------------------------------------------------------

    private static function service_types(): array {
        return [
            'sunday'   => 'Sunday Mass',
            'holy_day' => 'Holy Day of Obligation',
            'special'  => 'Special Mass',
            'other'    => 'Other Service',
        ];
    }

    // -------------------------------------------------------------------------
    // INIT
    // -------------------------------------------------------------------------

    public static function init() {
        add_action( 'admin_post_occipr_save_attendance',   [ __CLASS__, 'save' ] );
        add_action( 'admin_post_occipr_delete_attendance', [ __CLASS__, 'delete' ] );
    }

    // -------------------------------------------------------------------------
    // PAGE ROUTER
    // -------------------------------------------------------------------------

    public static function page() {
        if ( ! current_user_can( 'occipr_view_records' ) ) { wp_die( 'Access denied.' ); }
        $action = sanitize_key( $_GET['action'] ?? '' );

        if ( $action === 'add' || $action === 'edit' ) {
            self::form_page( absint( $_GET['id'] ?? 0 ) );
        } else {
            self::list_page();
        }
    }

    // -------------------------------------------------------------------------
    // LIST PAGE
    // -------------------------------------------------------------------------

    private static function list_page() {
        global $wpdb;

        // --- filters ---
        $parish_filter = absint( $_GET['parish_id'] ?? 0 );
        $type_filter   = sanitize_key( $_GET['service_type'] ?? '' );
        $date_from     = sanitize_text_field( $_GET['date_from'] ?? '' );
        $date_to       = sanitize_text_field( $_GET['date_to'] ?? '' );

        $where = 'WHERE 1=1';
        $args  = [];
        if ( $parish_filter ) {
            $where .= ' AND a.parish_id = %d';
            $args[] = $parish_filter;
        }
        if ( $type_filter ) {
            $where .= ' AND a.service_type = %s';
            $args[] = $type_filter;
        }
        if ( $date_from ) {
            $where .= ' AND a.service_date >= %s';
            $args[] = $date_from;
        }
        if ( $date_to ) {
            $where .= ' AND a.service_date <= %s';
            $args[] = $date_to;
        }

        $sql     = "SELECT a.*, p.name AS parish_name
                    FROM {$wpdb->prefix}occipr_attendance a
                    LEFT JOIN {$wpdb->prefix}occipr_parishes p ON p.id = a.parish_id
                    $where
                    ORDER BY a.service_date DESC, a.service_time DESC";
        $records = $args
            ? $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) )
            : $wpdb->get_results( $sql );

        // --- summary stats (same filters) ---
        $stats_sql  = "SELECT
                            COUNT(*)         AS total_services,
                            SUM(headcount)   AS total_headcount,
                            AVG(headcount)   AS avg_headcount
                       FROM {$wpdb->prefix}occipr_attendance a
                       $where";
        $stats = $args
            ? $wpdb->get_row( $wpdb->prepare( $stats_sql, ...$args ) )
            : $wpdb->get_row( $stats_sql );

        // --- year-to-date totals (unfiltered except parish) ---
        $year       = date( 'Y' );
        $ytd_args   = $parish_filter ? [ $parish_filter ] : [];
        $ytd_where  = $parish_filter ? 'WHERE parish_id = %d' : '';
        $ytd_sql    = "SELECT COUNT(*) AS services, SUM(headcount) AS headcount
                       FROM {$wpdb->prefix}occipr_attendance
                       $ytd_where
                       AND YEAR(service_date) = $year";
        // Rebuild properly
        $ytd_where2 = "WHERE YEAR(service_date) = $year";
        if ( $parish_filter ) { $ytd_where2 .= ' AND parish_id = %d'; }
        $ytd_sql2 = "SELECT COUNT(*) AS services, SUM(headcount) AS headcount
                     FROM {$wpdb->prefix}occipr_attendance $ytd_where2";
        $ytd = $parish_filter
            ? $wpdb->get_row( $wpdb->prepare( $ytd_sql2, $parish_filter ) )
            : $wpdb->get_row( $ytd_sql2 );

        $parishes   = OCCIPR_Database::get_parishes();
        $types      = self::service_types();

        $notice = '';
        if ( isset( $_GET['saved'] ) )   $notice = 'Attendance record saved.';
        if ( isset( $_GET['deleted'] ) ) $notice = 'Record deleted.';
        ?>
        <div class="wrap occi-wrap">
            <h1 class="wp-heading-inline">
                <span class="dashicons dashicons-groups"></span> Mass Attendance
            </h1>
            <?php if ( current_user_can( 'occipr_manage_records' ) ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-attendance&action=add' ) ); ?>" class="page-title-action">Add Entry</a>
            <?php endif; ?>
            <hr class="wp-header-end">

            <?php if ( $notice ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
            <?php endif; ?>

            <!-- Summary bar -->
            <div class="occi-attendance-summary">
                <div class="occi-att-stat">
                    <span class="occi-att-num"><?php echo intval( $ytd->services ?? 0 ); ?></span>
                    <span class="occi-att-lbl">Services (<?php echo $year; ?>)</span>
                </div>
                <div class="occi-att-stat">
                    <span class="occi-att-num"><?php echo intval( $ytd->headcount ?? 0 ); ?></span>
                    <span class="occi-att-lbl">Total Attended (<?php echo $year; ?>)</span>
                </div>
                <div class="occi-att-stat">
                    <span class="occi-att-num"><?php echo $stats->total_services ? round( $stats->avg_headcount ) : '--'; ?></span>
                    <span class="occi-att-lbl">Avg. per Service<?php echo ( $date_from || $date_to || $type_filter ) ? ' (filtered)' : ''; ?></span>
                </div>
                <div class="occi-att-stat">
                    <span class="occi-att-num"><?php echo intval( $stats->total_services ?? 0 ); ?></span>
                    <span class="occi-att-lbl">Services (filtered)</span>
                </div>
            </div>

            <!-- Filters -->
            <form method="get" class="occi-search-form">
                <input type="hidden" name="page" value="occipr-attendance">
                <?php if ( count( $parishes ) > 1 ) : ?>
                <select name="parish_id">
                    <option value="">All Parishes</option>
                    <?php foreach ( $parishes as $p ) : ?>
                    <option value="<?php echo esc_attr( $p->id ); ?>"<?php selected( $parish_filter, $p->id ); ?>><?php echo esc_html( $p->name ); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <select name="service_type">
                    <option value="">All Service Types</option>
                    <?php foreach ( $types as $val => $label ) : ?>
                    <option value="<?php echo esc_attr( $val ); ?>"<?php selected( $type_filter, $val ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" title="From date">
                <input type="date" name="date_to"   value="<?php echo esc_attr( $date_to ); ?>"   title="To date">
                <button type="submit" class="button">Filter</button>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-attendance' ) ); ?>" class="button">Reset</a>
            </form>

            <?php if ( ! $records ) : ?>
            <p>No attendance records found.
                <?php if ( current_user_can( 'occipr_manage_records' ) ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-attendance&action=add' ) ); ?>">Add the first entry.</a>
                <?php endif; ?>
            </p>
            <?php else : ?>
            <p class="occi-count"><?php echo count( $records ); ?> record(s) found.</p>
            <table class="widefat striped occi-register-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Service</th>
                        <th>Time</th>
                        <th>Parish</th>
                        <th style="text-align:center;">Headcount</th>
                        <th style="text-align:center;">Communion</th>
                        <th>Notes</th>
                        <?php if ( current_user_can( 'occipr_manage_records' ) ) : ?>
                        <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $records as $r ) : ?>
                    <tr>
                        <td><strong><?php echo esc_html( occipr_format_date( $r->service_date ) ); ?></strong></td>
                        <td>
                            <?php echo esc_html( $types[ $r->service_type ] ?? ucfirst( $r->service_type ) ); ?>
                            <?php if ( $r->service_label ) : ?>
                            <br><span class="occi-small"><?php echo esc_html( $r->service_label ); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $r->service_time ? esc_html( self::format_time( $r->service_time ) ) : '--'; ?></td>
                        <td><?php echo esc_html( $r->parish_name ?? 'N/A' ); ?></td>
                        <td style="text-align:center; font-weight:700; font-size:1.1em;"><?php echo intval( $r->headcount ); ?></td>
                        <td style="text-align:center;"><?php echo $r->communion_count !== null ? intval( $r->communion_count ) : '--'; ?></td>
                        <td class="occi-small"><?php echo esc_html( $r->notes ?? '' ); ?></td>
                        <?php if ( current_user_can( 'occipr_manage_records' ) ) : ?>
                        <td class="occi-actions">
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-attendance&action=edit&id=' . $r->id ) ); ?>">Edit</a>
                            | <a class="occi-delete"
                                 href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=occipr_delete_attendance&id=' . $r->id ), 'occipr_delete_attendance_' . $r->id ) ); ?>"
                                 onclick="return confirm('Delete this attendance record?')">Delete</a>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // ADD / EDIT FORM
    // -------------------------------------------------------------------------

    private static function form_page( int $id ) {
        if ( ! current_user_can( 'occipr_manage_records' ) ) { wp_die( 'Access denied.' ); }
        global $wpdb;

        $r      = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}occipr_attendance WHERE id = %d", $id ) ) : null;
        $is_edit = (bool) $r;
        $title   = $is_edit ? 'Edit Attendance Record' : 'Add Attendance Record';
        $types   = self::service_types();
        ?>
        <div class="wrap occi-wrap">
            <h1><?php echo esc_html( $title ); ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-attendance' ) ); ?>" class="page-title-action">&larr; Back to List</a>
            <hr class="wp-header-end">

            <div class="occi-form" style="max-width:700px;">
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'occipr_save_attendance', 'occipr_attendance_nonce' ); ?>
                    <input type="hidden" name="action"        value="occipr_save_attendance">
                    <input type="hidden" name="attendance_id" value="<?php echo esc_attr( $id ); ?>">

                    <div class="occi-section">
                        <h2>Service Details</h2>
                        <table class="form-table">
                            <tr>
                                <th><label for="service_date">Date <span class="required">*</span></label></th>
                                <td><input type="date" id="service_date" name="service_date"
                                           value="<?php echo esc_attr( $r->service_date ?? date( 'Y-m-d' ) ); ?>" required></td>
                            </tr>
                            <tr>
                                <th><label for="service_type">Service Type <span class="required">*</span></label></th>
                                <td>
                                    <select id="service_type" name="service_type" required>
                                        <?php foreach ( $types as $val => $label ) : ?>
                                        <option value="<?php echo esc_attr( $val ); ?>"<?php selected( $r->service_type ?? 'sunday', $val ); ?>><?php echo esc_html( $label ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="service_label">Service Label</label></th>
                                <td>
                                    <input type="text" id="service_label" name="service_label" class="regular-text"
                                           value="<?php echo esc_attr( $r->service_label ?? '' ); ?>"
                                           placeholder="e.g. Easter Vigil, Feast of St. Francis">
                                    <p class="description">Optional. Displayed beneath the service type for special occasions.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="service_time">Time</label></th>
                                <td>
                                    <input type="time" id="service_time" name="service_time"
                                           value="<?php echo esc_attr( $r->service_time ?? '' ); ?>">
                                    <p class="description">Optional. Useful when a parish holds multiple Masses on the same day.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="parish_id">Parish <span class="required">*</span></label></th>
                                <td>
                                    <select id="parish_id" name="parish_id" required>
                                        <?php echo OCCIPR_Database::parish_dropdown( (int) ( $r->parish_id ?? 0 ) ); ?>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="occi-section">
                        <h2>Counts</h2>
                        <table class="form-table">
                            <tr>
                                <th><label for="headcount">Headcount <span class="required">*</span></label></th>
                                <td>
                                    <input type="number" id="headcount" name="headcount" min="0" class="small-text"
                                           value="<?php echo esc_attr( $r->headcount ?? '' ); ?>" required>
                                    <p class="description">Total number of people present.</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="communion_count">Communion Count</label></th>
                                <td>
                                    <input type="number" id="communion_count" name="communion_count" min="0" class="small-text"
                                           value="<?php echo esc_attr( $r->communion_count ?? '' ); ?>">
                                    <p class="description">Optional. Number of communicants.</p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="occi-section">
                        <h2>Notes</h2>
                        <table class="form-table">
                            <tr>
                                <th><label for="notes">Notes</label></th>
                                <td>
                                    <textarea id="notes" name="notes" rows="3" class="large-text"><?php echo esc_textarea( $r->notes ?? '' ); ?></textarea>
                                    <p class="description">Optional. Inclement weather, special circumstances, etc.</p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php echo $is_edit ? 'Update Record' : 'Save Record'; ?></button>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-attendance' ) ); ?>" class="button">Cancel</a>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // SAVE
    // -------------------------------------------------------------------------

    public static function save() {
        if ( ! current_user_can( 'occipr_manage_records' ) ) { wp_die( 'Access denied.' ); }
        check_admin_referer( 'occipr_save_attendance', 'occipr_attendance_nonce' );
        global $wpdb;

        $communion = sanitize_text_field( $_POST['communion_count'] ?? '' );
        $time      = sanitize_text_field( $_POST['service_time'] ?? '' );

        $data = [
            'service_date'    => sanitize_text_field( $_POST['service_date'] ?? '' ),
            'service_type'    => sanitize_key( $_POST['service_type'] ?? 'sunday' ),
            'service_label'   => sanitize_text_field( $_POST['service_label'] ?? '' ),
            'service_time'    => $time ?: null,
            'parish_id'       => absint( $_POST['parish_id'] ?? 0 ) ?: null,
            'headcount'       => absint( $_POST['headcount'] ?? 0 ),
            'communion_count' => $communion !== '' ? absint( $communion ) : null,
            'notes'           => sanitize_textarea_field( $_POST['notes'] ?? '' ),
        ];
        $fmt = [ '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s' ];

        $id = absint( $_POST['attendance_id'] ?? 0 );
        if ( $id ) {
            $result = $wpdb->update( "{$wpdb->prefix}occipr_attendance", $data, [ 'id' => $id ], $fmt, [ '%d' ] );
            if ( false === $result ) {
                wp_die( 'Database error: ' . esc_html( $wpdb->last_error ) );
            }
        } else {
            $result = $wpdb->insert( "{$wpdb->prefix}occipr_attendance", $data, $fmt );
            if ( false === $result ) {
                wp_die( 'Database error: ' . esc_html( $wpdb->last_error ) );
            }
        }

        wp_redirect( admin_url( 'admin.php?page=occipr-attendance&saved=1' ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // DELETE
    // -------------------------------------------------------------------------

    public static function delete() {
        if ( ! current_user_can( 'occipr_manage_records' ) ) { wp_die( 'Access denied.' ); }
        $id = absint( $_GET['id'] ?? 0 );
        check_admin_referer( 'occipr_delete_attendance_' . $id );
        global $wpdb;
        $wpdb->delete( "{$wpdb->prefix}occipr_attendance", [ 'id' => $id ], [ '%d' ] );
        wp_redirect( admin_url( 'admin.php?page=occipr-attendance&deleted=1' ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // HELPERS
    // -------------------------------------------------------------------------

    private static function format_time( string $time ): string {
        $ts = strtotime( $time );
        return $ts ? date( 'g:i A', $ts ) : $time;
    }

    /** Count of services logged this year -- used by dashboard. */
    public static function year_service_count(): int {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}occipr_attendance WHERE YEAR(service_date) = " . date( 'Y' )
        );
    }

    /** Most recent headcount -- used by dashboard. */
    public static function latest_headcount(): int {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT headcount FROM {$wpdb->prefix}occipr_attendance ORDER BY service_date DESC, service_time DESC LIMIT 1"
        );
    }
}
