<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OCCIPR_PSR {

    // -------------------------------------------------------------------------
    // OPTION LISTS
    // -------------------------------------------------------------------------

    public static function status_options(): array {
        return [
            'active'    => 'Active',
            'completed' => 'Completed',
            'withdrawn' => 'Withdrawn',
        ];
    }

    public static function grade_options(): array {
        return [
            'Pre-K'  => 'Pre-K',
            'K'      => 'Kindergarten',
            '1'      => '1st Grade',
            '2'      => '2nd Grade',
            '3'      => '3rd Grade',
            '4'      => '4th Grade',
            '5'      => '5th Grade',
            '6'      => '6th Grade',
            '7'      => '7th Grade',
            '8'      => '8th Grade',
            '9'      => '9th Grade',
            '10'     => '10th Grade',
            '11'     => '11th Grade',
            '12'     => '12th Grade',
            'Adult'  => 'Adult',
            'Other'  => 'Other',
        ];
    }

    public static function status_badge( string $status ): string {
        $colors = [
            'active'    => 'background:#f0f8e8; color:#2d6a1a;',
            'completed' => 'background:#eafaf1; color:#1e8449;',
            'withdrawn' => 'background:#f9f9f9; color:#888;',
        ];
        $labels = self::status_options();
        $style  = $colors[ $status ] ?? '';
        $label  = $labels[ $status ] ?? ucfirst( $status );
        return '<span class="occi-rank" style="' . esc_attr( $style ) . '">' . esc_html( $label ) . '</span>';
    }

    // -------------------------------------------------------------------------
    // INIT
    // -------------------------------------------------------------------------

    public static function init() {
        add_action( 'admin_post_occipr_save_psr',   [ __CLASS__, 'save' ] );
        add_action( 'admin_post_occipr_delete_psr', [ __CLASS__, 'delete' ] );
    }

    // -------------------------------------------------------------------------
    // PAGE ROUTER
    // -------------------------------------------------------------------------

    public static function page() {
        if ( ! current_user_can( 'occipr_view_records' ) ) { wp_die( 'Access denied.' ); }
        $action = sanitize_key( $_GET['action'] ?? '' );

        switch ( $action ) {
            case 'add':
                self::form_page( 0 );
                break;
            case 'edit':
                self::form_page( absint( $_GET['id'] ?? 0 ) );
                break;
            case 'view':
                self::detail_page( absint( $_GET['id'] ?? 0 ) );
                break;
            default:
                self::list_page();
        }
    }

    // -------------------------------------------------------------------------
    // LIST PAGE
    // -------------------------------------------------------------------------

    private static function list_page() {
        global $wpdb;

        $parishes      = OCCIPR_Database::get_parishes();
        $statuses      = self::status_options();
        $parish_filter = absint( $_GET['parish_id'] ?? 0 );
        $status_filter = sanitize_key( $_GET['status'] ?? 'active' );
        $year_filter   = sanitize_text_field( $_GET['academic_year'] ?? '' );
        $grade_filter  = sanitize_text_field( $_GET['grade_level'] ?? '' );
        $search        = sanitize_text_field( $_GET['s'] ?? '' );

        $where = 'WHERE 1=1';
        $args  = [];
        if ( $parish_filter ) { $where .= ' AND s.parish_id = %d';      $args[] = $parish_filter; }
        if ( $status_filter ) { $where .= ' AND s.status = %s';          $args[] = $status_filter; }
        if ( $year_filter )   { $where .= ' AND s.academic_year = %s';  $args[] = $year_filter; }
        if ( $grade_filter )  { $where .= ' AND s.grade_level = %s';    $args[] = $grade_filter; }
        if ( $search ) {
            $where .= ' AND (s.first_name LIKE %s OR s.last_name LIKE %s OR s.preferred_name LIKE %s OR s.guardian1_name LIKE %s)';
            $like   = '%' . $wpdb->esc_like( $search ) . '%';
            $args[] = $like; $args[] = $like; $args[] = $like; $args[] = $like;
        }

        $sql = "SELECT s.*, p.name AS parish_name
                FROM {$wpdb->prefix}occipr_psr s
                LEFT JOIN {$wpdb->prefix}occipr_parishes p ON p.id = s.parish_id
                $where
                ORDER BY s.last_name ASC, s.first_name ASC";
        $records = $args
            ? $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) )
            : $wpdb->get_results( $sql );

        // Quick counts (unfiltered)
        $count_sql  = "SELECT status, COUNT(*) AS cnt FROM {$wpdb->prefix}occipr_psr GROUP BY status";
        $raw_counts = $wpdb->get_results( $count_sql );
        $counts     = [];
        foreach ( $raw_counts as $rc ) { $counts[ $rc->status ] = $rc->cnt; }

        // Available years
        $years = $wpdb->get_col(
            "SELECT DISTINCT academic_year FROM {$wpdb->prefix}occipr_psr WHERE academic_year IS NOT NULL AND academic_year != '' ORDER BY academic_year DESC"
        );

        $notice = '';
        if ( isset( $_GET['saved'] ) )   $notice = 'Record saved.';
        if ( isset( $_GET['deleted'] ) ) $notice = 'Record deleted.';
        ?>
        <div class="wrap occi-wrap">
            <h1 class="wp-heading-inline">
                <span class="dashicons dashicons-welcome-learn-more"></span> PSR / Religious Education
            </h1>
            <?php if ( current_user_can( 'occipr_manage_records' ) ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-psr&action=add' ) ); ?>" class="page-title-action">Add Student</a>
            <?php endif; ?>
            <hr class="wp-header-end">

            <?php if ( $notice ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
            <?php endif; ?>

            <!-- Overview bar -->
            <div class="occi-attendance-summary" style="margin-bottom:12px;">
                <div class="occi-att-stat">
                    <span class="occi-att-num"><?php echo $counts['active'] ?? 0; ?></span>
                    <span class="occi-att-lbl">Active Students</span>
                </div>
                <div class="occi-att-stat">
                    <span class="occi-att-num"><?php echo $counts['completed'] ?? 0; ?></span>
                    <span class="occi-att-lbl">Completed</span>
                </div>
                <div class="occi-att-stat">
                    <span class="occi-att-num"><?php echo $counts['withdrawn'] ?? 0; ?></span>
                    <span class="occi-att-lbl">Withdrawn</span>
                </div>
                <div class="occi-att-stat">
                    <span class="occi-att-num"><?php echo array_sum( $counts ); ?></span>
                    <span class="occi-att-lbl">Total Records</span>
                </div>
            </div>

            <!-- Filters -->
            <form method="get" class="occi-search-form">
                <input type="hidden" name="page" value="occipr-psr">
                <input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search by name or guardian...">
                <?php if ( count( $parishes ) > 1 ) : ?>
                <select name="parish_id">
                    <option value="">All Parishes</option>
                    <?php foreach ( $parishes as $p ) : ?>
                    <option value="<?php echo esc_attr( $p->id ); ?>"<?php selected( $parish_filter, $p->id ); ?>><?php echo esc_html( $p->name ); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <select name="status">
                    <option value="">All Statuses</option>
                    <?php foreach ( $statuses as $val => $label ) : ?>
                    <option value="<?php echo esc_attr( $val ); ?>"<?php selected( $status_filter, $val ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="academic_year">
                    <option value="">All Years</option>
                    <?php foreach ( $years as $y ) : ?>
                    <option value="<?php echo esc_attr( $y ); ?>"<?php selected( $year_filter, $y ); ?>><?php echo esc_html( $y ); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="grade_level">
                    <option value="">All Grades</option>
                    <?php foreach ( self::grade_options() as $val => $label ) : ?>
                    <option value="<?php echo esc_attr( $val ); ?>"<?php selected( $grade_filter, $val ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="button">Filter</button>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-psr' ) ); ?>" class="button">Reset</a>
            </form>

            <?php if ( ! $records ) : ?>
            <p>No students found.
                <?php if ( current_user_can( 'occipr_manage_records' ) ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-psr&action=add' ) ); ?>">Add the first student.</a>
                <?php endif; ?>
            </p>
            <?php else : ?>
            <p class="occi-count"><?php echo count( $records ); ?> student(s) found.</p>
            <table class="widefat striped occi-register-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Grade</th>
                        <th>Year</th>
                        <th>Guardian</th>
                        <th>Catechist</th>
                        <th>Parish</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $records as $r ) : ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html( $r->last_name . ', ' . ( $r->preferred_name ?: $r->first_name ) ); ?></strong>
                            <?php if ( $r->preferred_name && $r->preferred_name !== $r->first_name ) : ?>
                            <br><span class="occi-small">Legal: <?php echo esc_html( $r->first_name ); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo self::status_badge( $r->status ); ?></td>
                        <td><?php echo esc_html( $r->grade_level ?? '' ); ?></td>
                        <td class="occi-small"><?php echo esc_html( $r->academic_year ?? '' ); ?></td>
                        <td class="occi-small"><?php echo esc_html( $r->guardian1_name ?? '' ); ?></td>
                        <td class="occi-small"><?php echo esc_html( $r->catechist ?? '' ); ?></td>
                        <td class="occi-small"><?php echo esc_html( $r->parish_name ?? '' ); ?></td>
                        <td class="occi-actions">
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-psr&action=view&id=' . $r->id ) ); ?>">View</a>
                            <?php if ( current_user_can( 'occipr_manage_records' ) ) : ?>
                            | <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-psr&action=edit&id=' . $r->id ) ); ?>">Edit</a>
                            | <a class="occi-delete"
                                 href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=occipr_delete_psr&id=' . $r->id ), 'occipr_delete_psr_' . $r->id ) ); ?>"
                                 onclick="return confirm('Delete this PSR record?')">Delete</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // DETAIL / VIEW PAGE
    // -------------------------------------------------------------------------

    private static function detail_page( int $id ) {
        global $wpdb;
        $r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}occipr_psr WHERE id = %d", $id ) );
        if ( ! $r ) { wp_die( 'Record not found.' ); }

        $parish = $r->parish_id
            ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}occipr_parishes WHERE id = %d", $r->parish_id ) )
            : null;

        $baptism = $r->baptism_record_id
            ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}occipr_baptisms WHERE id = %d", $r->baptism_record_id ) )
            : null;
        $communion = $r->communion_record_id
            ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}occipr_communions WHERE id = %d", $r->communion_record_id ) )
            : null;
        $confirmation = $r->confirmation_record_id
            ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}occipr_confirmations WHERE id = %d", $r->confirmation_record_id ) )
            : null;

        $display_name = ( $r->preferred_name ?: $r->first_name ) . ' ' . $r->last_name;
        ?>
        <div class="wrap occi-wrap">
            <h1><?php echo esc_html( $display_name ); ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-psr' ) ); ?>" class="page-title-action">&larr; Back to List</a>
            <?php if ( current_user_can( 'occipr_manage_records' ) ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-psr&action=edit&id=' . $r->id ) ); ?>" class="page-title-action">Edit Record</a>
            <?php endif; ?>
            <hr class="wp-header-end">

            <div class="occi-view-record" style="max-width:860px;">
                <div class="occi-cert-header">
                    <h2>PSR Student Record</h2>
                    <h3><?php echo esc_html( $display_name ); ?></h3>
                    <div><?php echo self::status_badge( $r->status ); ?></div>
                    <?php if ( $parish ) : ?>
                    <p style="margin-top:8px; color:#555;"><?php echo esc_html( $parish->name ); ?></p>
                    <?php endif; ?>
                </div>

                <h3 class="occi-report-section-title" style="margin-top:0;">Student Information</h3>
                <table class="occi-view-table">
                    <tr><th>Legal Name</th><td><?php echo esc_html( trim( $r->first_name . ' ' . $r->middle_name . ' ' . $r->last_name ) ); ?></td></tr>
                    <?php if ( $r->preferred_name ) : ?>
                    <tr><th>Preferred Name</th><td><?php echo esc_html( $r->preferred_name ); ?></td></tr>
                    <?php endif; ?>
                    <?php if ( $r->birth_date ) : ?>
                    <tr><th>Date of Birth</th><td><?php echo esc_html( occipr_format_date( $r->birth_date ) ); ?></td></tr>
                    <?php endif; ?>
                    <tr><th>Grade / Level</th><td><?php echo esc_html( $r->grade_level ?? '' ); ?></td></tr>
                    <tr><th>Academic Year</th><td><?php echo esc_html( $r->academic_year ?? '' ); ?></td></tr>
                    <?php if ( $r->catechist ) : ?><tr><th>Catechist</th><td><?php echo esc_html( $r->catechist ); ?></td></tr><?php endif; ?>
                    <?php if ( $r->class_group ) : ?><tr><th>Class / Group</th><td><?php echo esc_html( $r->class_group ); ?></td></tr><?php endif; ?>
                    <?php if ( $r->enrollment_date ) : ?><tr><th>Enrolled</th><td><?php echo esc_html( occipr_format_date( $r->enrollment_date ) ); ?></td></tr><?php endif; ?>
                </table>

                <h3 class="occi-report-section-title">Parent / Guardian Information</h3>
                <table class="occi-view-table">
                    <?php if ( $r->guardian1_name ) : ?>
                    <tr><th>Guardian 1</th><td>
                        <?php echo esc_html( $r->guardian1_name ); ?>
                        <?php if ( $r->guardian1_phone ) echo '<br>' . esc_html( $r->guardian1_phone ); ?>
                        <?php if ( $r->guardian1_email ) echo '<br>' . esc_html( $r->guardian1_email ); ?>
                    </td></tr>
                    <?php endif; ?>
                    <?php if ( $r->guardian2_name ) : ?>
                    <tr><th>Guardian 2</th><td>
                        <?php echo esc_html( $r->guardian2_name ); ?>
                        <?php if ( $r->guardian2_phone ) echo '<br>' . esc_html( $r->guardian2_phone ); ?>
                        <?php if ( $r->guardian2_email ) echo '<br>' . esc_html( $r->guardian2_email ); ?>
                    </td></tr>
                    <?php endif; ?>
                    <?php if ( $r->home_address ) : ?><tr><th>Home Address</th><td><?php echo nl2br( esc_html( $r->home_address ) ); ?></td></tr><?php endif; ?>
                </table>

                <h3 class="occi-report-section-title">Sacramental Status</h3>
                <table class="occi-view-table">
                    <tr>
                        <th>Baptized</th>
                        <td>
                            <?php if ( $r->is_baptized ) : ?>
                            Yes
                            <?php if ( $r->baptism_date ) echo ' &mdash; ' . esc_html( occipr_format_date( $r->baptism_date ) ); ?>
                            <?php if ( $r->baptism_church ) echo '<br>' . esc_html( $r->baptism_church ); ?>
                            <?php if ( $baptism ) echo ' &mdash; <a href="' . esc_url( admin_url( 'admin.php?page=occipr-baptisms&action=view&id=' . $baptism->id ) ) . '">View Record</a>'; ?>
                            <?php else : ?>
                            No
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>First Communion</th>
                        <td>
                            <?php if ( $r->received_first_communion ) : ?>
                            Yes
                            <?php if ( $r->communion_date ) echo ' &mdash; ' . esc_html( occipr_format_date( $r->communion_date ) ); ?>
                            <?php if ( $r->communion_church ) echo '<br>' . esc_html( $r->communion_church ); ?>
                            <?php if ( $communion ) echo ' &mdash; <a href="' . esc_url( admin_url( 'admin.php?page=occipr-communions&action=view&id=' . $communion->id ) ) . '">View Record</a>'; ?>
                            <?php else : ?>
                            No
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Confirmed</th>
                        <td>
                            <?php if ( $r->is_confirmed ) : ?>
                            Yes
                            <?php if ( $r->confirmation_date ) echo ' &mdash; ' . esc_html( occipr_format_date( $r->confirmation_date ) ); ?>
                            <?php if ( $r->confirmation_church ) echo '<br>' . esc_html( $r->confirmation_church ); ?>
                            <?php if ( $confirmation ) echo ' &mdash; <a href="' . esc_url( admin_url( 'admin.php?page=occipr-confirmations&action=view&id=' . $confirmation->id ) ) . '">View Record</a>'; ?>
                            <?php else : ?>
                            No
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <?php if ( $r->notes ) : ?>
                <h3 class="occi-report-section-title">Notes</h3>
                <p><?php echo nl2br( esc_html( $r->notes ) ); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // ADD / EDIT FORM
    // -------------------------------------------------------------------------

    private static function form_page( int $id ) {
        if ( ! current_user_can( 'occipr_manage_records' ) ) { wp_die( 'Access denied.' ); }
        global $wpdb;

        $r       = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}occipr_psr WHERE id = %d", $id ) ) : null;
        $is_edit = (bool) $r;
        $title   = $is_edit
            ? 'Edit PSR Student: ' . ( $r->preferred_name ?: $r->first_name ) . ' ' . $r->last_name
            : 'Add PSR Student';

        $baptisms = $wpdb->get_results(
            "SELECT id, first_name, last_name, baptism_date FROM {$wpdb->prefix}occipr_baptisms ORDER BY baptism_date DESC, last_name ASC"
        );
        $communions = $wpdb->get_results(
            "SELECT id, first_name, last_name, communion_date FROM {$wpdb->prefix}occipr_communions ORDER BY communion_date DESC, last_name ASC"
        );
        $confirmations = $wpdb->get_results(
            "SELECT id, first_name, last_name, confirmation_date FROM {$wpdb->prefix}occipr_confirmations ORDER BY confirmation_date DESC, last_name ASC"
        );
        ?>
        <div class="wrap occi-wrap">
            <h1><?php echo esc_html( $title ); ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-psr' ) ); ?>" class="page-title-action">&larr; Back to List</a>
            <hr class="wp-header-end">

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'occipr_save_psr', 'occipr_psr_nonce' ); ?>
                <input type="hidden" name="action" value="occipr_save_psr">
                <input type="hidden" name="psr_id" value="<?php echo esc_attr( $id ); ?>">

                <div class="occi-form-columns">
                    <!-- Main column -->
                    <div class="occi-form-col-main">

                        <div class="occi-section">
                            <h2>Student Information</h2>
                            <table class="form-table">
                                <tr>
                                    <th><label for="first_name">Legal First Name <span class="required">*</span></label></th>
                                    <td><input type="text" id="first_name" name="first_name" class="regular-text"
                                               value="<?php echo esc_attr( $r->first_name ?? '' ); ?>" required></td>
                                </tr>
                                <tr>
                                    <th><label for="preferred_name">Preferred / Chosen Name</label></th>
                                    <td>
                                        <input type="text" id="preferred_name" name="preferred_name" class="regular-text"
                                               value="<?php echo esc_attr( $r->preferred_name ?? '' ); ?>">
                                        <p class="description">If different from legal name.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="middle_name">Middle Name</label></th>
                                    <td><input type="text" id="middle_name" name="middle_name" class="regular-text"
                                               value="<?php echo esc_attr( $r->middle_name ?? '' ); ?>"></td>
                                </tr>
                                <tr>
                                    <th><label for="last_name">Last Name <span class="required">*</span></label></th>
                                    <td><input type="text" id="last_name" name="last_name" class="regular-text"
                                               value="<?php echo esc_attr( $r->last_name ?? '' ); ?>" required></td>
                                </tr>
                                <tr>
                                    <th><label for="birth_date">Date of Birth</label></th>
                                    <td><input type="date" id="birth_date" name="birth_date"
                                               value="<?php echo esc_attr( $r->birth_date ?? '' ); ?>"></td>
                                </tr>
                                <tr>
                                    <th><label for="grade_level">Grade / Level</label></th>
                                    <td>
                                        <select id="grade_level" name="grade_level">
                                            <option value="">-- Select --</option>
                                            <?php foreach ( self::grade_options() as $val => $label ) : ?>
                                            <option value="<?php echo esc_attr( $val ); ?>"<?php selected( $r->grade_level ?? '', $val ); ?>><?php echo esc_html( $label ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="academic_year">Academic Year</label></th>
                                    <td>
                                        <input type="text" id="academic_year" name="academic_year" class="small-text"
                                               value="<?php echo esc_attr( $r->academic_year ?? '' ); ?>"
                                               placeholder="e.g. 2025-2026">
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="enrollment_date">Enrollment Date</label></th>
                                    <td><input type="date" id="enrollment_date" name="enrollment_date"
                                               value="<?php echo esc_attr( $r->enrollment_date ?? '' ); ?>"></td>
                                </tr>
                                <tr>
                                    <th><label for="catechist">Catechist / Teacher</label></th>
                                    <td><input type="text" id="catechist" name="catechist" class="regular-text"
                                               value="<?php echo esc_attr( $r->catechist ?? '' ); ?>"></td>
                                </tr>
                                <tr>
                                    <th><label for="class_group">Class / Group</label></th>
                                    <td>
                                        <input type="text" id="class_group" name="class_group" class="regular-text"
                                               value="<?php echo esc_attr( $r->class_group ?? '' ); ?>"
                                               placeholder="e.g. Wednesday 6pm, Group B">
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div class="occi-section">
                            <h2>Parent / Guardian Information</h2>
                            <table class="form-table">
                                <tr>
                                    <th><label for="guardian1_name">Guardian 1 Name</label></th>
                                    <td><input type="text" id="guardian1_name" name="guardian1_name" class="regular-text"
                                               value="<?php echo esc_attr( $r->guardian1_name ?? '' ); ?>"></td>
                                </tr>
                                <tr>
                                    <th><label for="guardian1_phone">Guardian 1 Phone</label></th>
                                    <td><input type="text" id="guardian1_phone" name="guardian1_phone" class="regular-text"
                                               value="<?php echo esc_attr( $r->guardian1_phone ?? '' ); ?>"></td>
                                </tr>
                                <tr>
                                    <th><label for="guardian1_email">Guardian 1 Email</label></th>
                                    <td><input type="email" id="guardian1_email" name="guardian1_email" class="regular-text"
                                               value="<?php echo esc_attr( $r->guardian1_email ?? '' ); ?>"></td>
                                </tr>
                                <tr>
                                    <th><label for="guardian2_name">Guardian 2 Name</label></th>
                                    <td><input type="text" id="guardian2_name" name="guardian2_name" class="regular-text"
                                               value="<?php echo esc_attr( $r->guardian2_name ?? '' ); ?>"></td>
                                </tr>
                                <tr>
                                    <th><label for="guardian2_phone">Guardian 2 Phone</label></th>
                                    <td><input type="text" id="guardian2_phone" name="guardian2_phone" class="regular-text"
                                               value="<?php echo esc_attr( $r->guardian2_phone ?? '' ); ?>"></td>
                                </tr>
                                <tr>
                                    <th><label for="guardian2_email">Guardian 2 Email</label></th>
                                    <td><input type="email" id="guardian2_email" name="guardian2_email" class="regular-text"
                                               value="<?php echo esc_attr( $r->guardian2_email ?? '' ); ?>"></td>
                                </tr>
                                <tr>
                                    <th><label for="home_address">Home Address</label></th>
                                    <td><textarea id="home_address" name="home_address" rows="3" class="regular-text"><?php echo esc_textarea( $r->home_address ?? '' ); ?></textarea>
                                        <p class="description">Street, City, State, ZIP</p>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div class="occi-section">
                            <h2>Sacramental Status</h2>
                            <p class="description" style="margin:0 0 12px;">Record the student's current sacramental status. Link to a register record if one exists in this system.</p>
                            <table class="form-table">
                                <!-- Baptism -->
                                <tr>
                                    <th>Baptized</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" id="occipr-psr-baptized" name="is_baptized" value="1"<?php checked( $r->is_baptized ?? 0 ); ?>>
                                            This student has been baptized
                                        </label>
                                    </td>
                                </tr>
                                <tr id="occipr-psr-bap-fields"<?php echo ( $r->is_baptized ?? 0 ) ? '' : ' style="display:none;"'; ?>>
                                    <th><label for="baptism_date">Baptism Date</label></th>
                                    <td><input type="date" id="baptism_date" name="baptism_date"
                                               value="<?php echo esc_attr( $r->baptism_date ?? '' ); ?>"></td>
                                </tr>
                                <tr id="occipr-psr-bap-church"<?php echo ( $r->is_baptized ?? 0 ) ? '' : ' style="display:none;"'; ?>>
                                    <th><label for="baptism_church">Baptism Church</label></th>
                                    <td><input type="text" id="baptism_church" name="baptism_church" class="regular-text"
                                               value="<?php echo esc_attr( $r->baptism_church ?? '' ); ?>"
                                               placeholder="Leave blank if baptized at this parish"></td>
                                </tr>
                                <tr id="occipr-psr-bap-link"<?php echo ( $r->is_baptized ?? 0 ) ? '' : ' style="display:none;"'; ?>>
                                    <th><label for="baptism_record_id">Baptism Record Link</label></th>
                                    <td>
                                        <select id="baptism_record_id" name="baptism_record_id" class="regular-text">
                                            <option value="">-- None --</option>
                                            <?php foreach ( $baptisms as $b ) : ?>
                                            <option value="<?php echo esc_attr( $b->id ); ?>"<?php selected( (int) ( $r->baptism_record_id ?? 0 ), $b->id ); ?>>
                                                <?php echo esc_html( $b->last_name . ', ' . $b->first_name . ' (' . occipr_format_date( $b->baptism_date ) . ')' ); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>

                                <!-- First Communion -->
                                <tr><td colspan="2"><hr style="border:none; border-top:1px solid #eee; margin:4px 0;"></td></tr>
                                <tr>
                                    <th>First Communion</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" id="occipr-psr-communion" name="received_first_communion" value="1"<?php checked( $r->received_first_communion ?? 0 ); ?>>
                                            This student has received First Communion
                                        </label>
                                    </td>
                                </tr>
                                <tr id="occipr-psr-com-date"<?php echo ( $r->received_first_communion ?? 0 ) ? '' : ' style="display:none;"'; ?>>
                                    <th><label for="communion_date">Communion Date</label></th>
                                    <td><input type="date" id="communion_date" name="communion_date"
                                               value="<?php echo esc_attr( $r->communion_date ?? '' ); ?>"></td>
                                </tr>
                                <tr id="occipr-psr-com-church"<?php echo ( $r->received_first_communion ?? 0 ) ? '' : ' style="display:none;"'; ?>>
                                    <th><label for="communion_church">Communion Church</label></th>
                                    <td><input type="text" id="communion_church" name="communion_church" class="regular-text"
                                               value="<?php echo esc_attr( $r->communion_church ?? '' ); ?>"
                                               placeholder="Leave blank if received at this parish"></td>
                                </tr>
                                <tr id="occipr-psr-com-link"<?php echo ( $r->received_first_communion ?? 0 ) ? '' : ' style="display:none;"'; ?>>
                                    <th><label for="communion_record_id">Communion Record Link</label></th>
                                    <td>
                                        <select id="communion_record_id" name="communion_record_id" class="regular-text">
                                            <option value="">-- None --</option>
                                            <?php foreach ( $communions as $cm ) : ?>
                                            <option value="<?php echo esc_attr( $cm->id ); ?>"<?php selected( (int) ( $r->communion_record_id ?? 0 ), $cm->id ); ?>>
                                                <?php echo esc_html( $cm->last_name . ', ' . $cm->first_name . ' (' . occipr_format_date( $cm->communion_date ) . ')' ); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>

                                <!-- Confirmation -->
                                <tr><td colspan="2"><hr style="border:none; border-top:1px solid #eee; margin:4px 0;"></td></tr>
                                <tr>
                                    <th>Confirmed</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" id="occipr-psr-confirmed" name="is_confirmed" value="1"<?php checked( $r->is_confirmed ?? 0 ); ?>>
                                            This student has been confirmed
                                        </label>
                                    </td>
                                </tr>
                                <tr id="occipr-psr-conf-date"<?php echo ( $r->is_confirmed ?? 0 ) ? '' : ' style="display:none;"'; ?>>
                                    <th><label for="confirmation_date">Confirmation Date</label></th>
                                    <td><input type="date" id="confirmation_date" name="confirmation_date"
                                               value="<?php echo esc_attr( $r->confirmation_date ?? '' ); ?>"></td>
                                </tr>
                                <tr id="occipr-psr-conf-church"<?php echo ( $r->is_confirmed ?? 0 ) ? '' : ' style="display:none;"'; ?>>
                                    <th><label for="confirmation_church">Confirmation Church</label></th>
                                    <td><input type="text" id="confirmation_church" name="confirmation_church" class="regular-text"
                                               value="<?php echo esc_attr( $r->confirmation_church ?? '' ); ?>"
                                               placeholder="Leave blank if confirmed at this parish"></td>
                                </tr>
                                <tr id="occipr-psr-conf-link"<?php echo ( $r->is_confirmed ?? 0 ) ? '' : ' style="display:none;"'; ?>>
                                    <th><label for="confirmation_record_id">Confirmation Record Link</label></th>
                                    <td>
                                        <select id="confirmation_record_id" name="confirmation_record_id" class="regular-text">
                                            <option value="">-- None --</option>
                                            <?php foreach ( $confirmations as $c ) : ?>
                                            <option value="<?php echo esc_attr( $c->id ); ?>"<?php selected( (int) ( $r->confirmation_record_id ?? 0 ), $c->id ); ?>>
                                                <?php echo esc_html( $c->last_name . ', ' . $c->first_name . ' (' . occipr_format_date( $c->confirmation_date ) . ')' ); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div class="occi-section">
                            <h2>Notes</h2>
                            <table class="form-table">
                                <tr>
                                    <th><label for="notes">Notes</label></th>
                                    <td><textarea id="notes" name="notes" rows="4" class="large-text"><?php echo esc_textarea( $r->notes ?? '' ); ?></textarea></td>
                                </tr>
                            </table>
                        </div>

                    </div><!-- .occi-form-col-main -->

                    <!-- Side column -->
                    <div class="occi-form-col-side">

                        <div class="occi-form-box">
                            <h2>Parish</h2>
                            <select name="parish_id" class="widefat">
                                <?php echo OCCIPR_Database::parish_dropdown( (int) ( $r->parish_id ?? 0 ) ); ?>
                            </select>
                        </div>

                        <div class="occi-form-box" style="margin-top:12px;">
                            <h2>Status</h2>
                            <select name="status" class="widefat">
                                <?php foreach ( self::status_options() as $val => $label ) : ?>
                                <option value="<?php echo esc_attr( $val ); ?>"<?php selected( $r->status ?? 'active', $val ); ?>><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                    </div><!-- .occi-form-col-side -->
                </div><!-- .occi-form-columns -->

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php echo $is_edit ? 'Update Record' : 'Add Student'; ?></button>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-psr' ) ); ?>" class="button">Cancel</a>
                </p>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($){
            function toggleRows( checkbox, rowIds ) {
                $(checkbox).on('change', function(){
                    var show = $(this).is(':checked');
                    $.each(rowIds, function(i, id){ $(id).toggle(show); });
                });
            }
            toggleRows('#occipr-psr-baptized',  ['#occipr-psr-bap-fields','#occipr-psr-bap-church','#occipr-psr-bap-link']);
            toggleRows('#occipr-psr-communion',  ['#occipr-psr-com-date','#occipr-psr-com-church','#occipr-psr-com-link']);
            toggleRows('#occipr-psr-confirmed',  ['#occipr-psr-conf-date','#occipr-psr-conf-church','#occipr-psr-conf-link']);
        });
        </script>
        <?php
    }

    // -------------------------------------------------------------------------
    // SAVE
    // -------------------------------------------------------------------------

    public static function save() {
        if ( ! current_user_can( 'occipr_manage_records' ) ) { wp_die( 'Access denied.' ); }
        check_admin_referer( 'occipr_save_psr', 'occipr_psr_nonce' );
        global $wpdb;

        $data = [
            'first_name'             => sanitize_text_field( $_POST['first_name'] ?? '' ),
            'preferred_name'         => sanitize_text_field( $_POST['preferred_name'] ?? '' ),
            'middle_name'            => sanitize_text_field( $_POST['middle_name'] ?? '' ),
            'last_name'              => sanitize_text_field( $_POST['last_name'] ?? '' ),
            'birth_date'             => sanitize_text_field( $_POST['birth_date'] ?? '' ) ?: null,
            'grade_level'            => sanitize_text_field( $_POST['grade_level'] ?? '' ),
            'academic_year'          => sanitize_text_field( $_POST['academic_year'] ?? '' ),
            'enrollment_date'        => sanitize_text_field( $_POST['enrollment_date'] ?? '' ) ?: null,
            'catechist'              => sanitize_text_field( $_POST['catechist'] ?? '' ),
            'class_group'            => sanitize_text_field( $_POST['class_group'] ?? '' ),
            'parish_id'              => absint( $_POST['parish_id'] ?? 0 ) ?: null,
            'status'                 => sanitize_key( $_POST['status'] ?? 'active' ),
            'guardian1_name'         => sanitize_text_field( $_POST['guardian1_name'] ?? '' ),
            'guardian1_phone'        => sanitize_text_field( $_POST['guardian1_phone'] ?? '' ),
            'guardian1_email'        => sanitize_email( $_POST['guardian1_email'] ?? '' ),
            'guardian2_name'         => sanitize_text_field( $_POST['guardian2_name'] ?? '' ),
            'guardian2_phone'        => sanitize_text_field( $_POST['guardian2_phone'] ?? '' ),
            'guardian2_email'        => sanitize_email( $_POST['guardian2_email'] ?? '' ),
            'home_address'           => sanitize_textarea_field( $_POST['home_address'] ?? '' ),
            'is_baptized'            => isset( $_POST['is_baptized'] ) ? 1 : 0,
            'baptism_date'           => sanitize_text_field( $_POST['baptism_date'] ?? '' ) ?: null,
            'baptism_church'         => sanitize_text_field( $_POST['baptism_church'] ?? '' ),
            'baptism_record_id'      => absint( $_POST['baptism_record_id'] ?? 0 ) ?: null,
            'received_first_communion' => isset( $_POST['received_first_communion'] ) ? 1 : 0,
            'communion_date'         => sanitize_text_field( $_POST['communion_date'] ?? '' ) ?: null,
            'communion_church'       => sanitize_text_field( $_POST['communion_church'] ?? '' ),
            'communion_record_id'    => absint( $_POST['communion_record_id'] ?? 0 ) ?: null,
            'is_confirmed'           => isset( $_POST['is_confirmed'] ) ? 1 : 0,
            'confirmation_date'      => sanitize_text_field( $_POST['confirmation_date'] ?? '' ) ?: null,
            'confirmation_church'    => sanitize_text_field( $_POST['confirmation_church'] ?? '' ),
            'confirmation_record_id' => absint( $_POST['confirmation_record_id'] ?? 0 ) ?: null,
            'notes'                  => sanitize_textarea_field( $_POST['notes'] ?? '' ),
        ];

        $id = absint( $_POST['psr_id'] ?? 0 );
        if ( $id ) {
            $result = $wpdb->update( "{$wpdb->prefix}occipr_psr", $data, [ 'id' => $id ] );
        } else {
            $result = $wpdb->insert( "{$wpdb->prefix}occipr_psr", $data );
        }
        if ( false === $result ) { wp_die( 'Database error: ' . esc_html( $wpdb->last_error ) ); }

        wp_redirect( admin_url( 'admin.php?page=occipr-psr&saved=1' ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // DELETE
    // -------------------------------------------------------------------------

    public static function delete() {
        if ( ! current_user_can( 'occipr_manage_records' ) ) { wp_die( 'Access denied.' ); }
        $id = absint( $_GET['id'] ?? 0 );
        check_admin_referer( 'occipr_delete_psr_' . $id );
        global $wpdb;
        $wpdb->delete( "{$wpdb->prefix}occipr_psr", [ 'id' => $id ], [ '%d' ] );
        wp_redirect( admin_url( 'admin.php?page=occipr-psr&deleted=1' ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // HELPERS (for dashboard)
    // -------------------------------------------------------------------------

    public static function active_count(): int {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}occipr_psr WHERE status = 'active'"
        );
    }
}
