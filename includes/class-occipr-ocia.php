<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OCCIPR_OCIA {

    // -------------------------------------------------------------------------
    // OPTION LISTS
    // -------------------------------------------------------------------------

    public static function status_options(): array {
        return [
            'inquirer'   => 'Inquirer',
            'catechumen' => 'Catechumen',
            'elect'      => 'Elect',
            'completed'  => 'Completed',
            'withdrawn'  => 'Withdrawn',
        ];
    }

    public static function status_badge( string $status ): string {
        $colors = [
            'inquirer'   => 'background:#e8f4f8; color:#1a6a8a;',
            'catechumen' => 'background:#fef9e7; color:#7d6608;',
            'elect'      => 'background:#f0f8e8; color:#2d6a1a;',
            'completed'  => 'background:#eafaf1; color:#1e8449;',
            'withdrawn'  => 'background:#f9f9f9; color:#888;',
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
        add_action( 'admin_post_occipr_save_ocia',   [ __CLASS__, 'save' ] );
        add_action( 'admin_post_occipr_delete_ocia', [ __CLASS__, 'delete' ] );
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
        $status_filter = sanitize_key( $_GET['status'] ?? '' );
        $year_filter   = absint( $_GET['year'] ?? 0 );
        $search        = sanitize_text_field( $_GET['s'] ?? '' );

        $where = 'WHERE 1=1';
        $args  = [];
        if ( $parish_filter ) { $where .= ' AND o.parish_id = %d';              $args[] = $parish_filter; }
        if ( $status_filter ) { $where .= ' AND o.status = %s';                  $args[] = $status_filter; }
        if ( $year_filter )   { $where .= ' AND YEAR(o.enrollment_date) = %d';  $args[] = $year_filter; }
        if ( $search ) {
            $where .= ' AND (o.first_name LIKE %s OR o.last_name LIKE %s OR o.preferred_name LIKE %s OR o.email LIKE %s)';
            $like   = '%' . $wpdb->esc_like( $search ) . '%';
            $args[] = $like; $args[] = $like; $args[] = $like; $args[] = $like;
        }

        $sql = "SELECT o.*, p.name AS parish_name
                FROM {$wpdb->prefix}occipr_ocia o
                LEFT JOIN {$wpdb->prefix}occipr_parishes p ON p.id = o.parish_id
                $where
                ORDER BY
                    FIELD(o.status,'elect','catechumen','inquirer','completed','withdrawn'),
                    o.last_name ASC, o.first_name ASC";
        $records = $args
            ? $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) )
            : $wpdb->get_results( $sql );

        // Quick counts (unfiltered by status for overview)
        $count_sql = "SELECT status, COUNT(*) AS cnt FROM {$wpdb->prefix}occipr_ocia GROUP BY status";
        $raw_counts = $wpdb->get_results( $count_sql );
        $counts = [];
        foreach ( $raw_counts as $rc ) { $counts[ $rc->status ] = $rc->cnt; }
        $active = ( $counts['inquirer'] ?? 0 ) + ( $counts['catechumen'] ?? 0 ) + ( $counts['elect'] ?? 0 );

        $years = $wpdb->get_col(
            "SELECT DISTINCT YEAR(enrollment_date) FROM {$wpdb->prefix}occipr_ocia WHERE enrollment_date IS NOT NULL ORDER BY 1 DESC"
        );

        $notice = '';
        if ( isset( $_GET['saved'] ) )   $notice = 'Record saved.';
        if ( isset( $_GET['deleted'] ) ) $notice = 'Record deleted.';
        ?>
        <div class="wrap occi-wrap">
            <h1 class="wp-heading-inline">
                <span class="dashicons dashicons-welcome-learn-more"></span> OCIA
            </h1>
            <?php if ( current_user_can( 'occipr_manage_records' ) ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-ocia&action=add' ) ); ?>" class="page-title-action">Add Candidate</a>
            <?php endif; ?>
            <hr class="wp-header-end">

            <?php if ( $notice ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
            <?php endif; ?>

            <!-- Overview bar -->
            <div class="occi-attendance-summary" style="margin-bottom:12px;">
                <div class="occi-att-stat">
                    <span class="occi-att-num"><?php echo $active; ?></span>
                    <span class="occi-att-lbl">Currently Active</span>
                </div>
                <div class="occi-att-stat">
                    <span class="occi-att-num"><?php echo $counts['inquirer'] ?? 0; ?></span>
                    <span class="occi-att-lbl">Inquirers</span>
                </div>
                <div class="occi-att-stat">
                    <span class="occi-att-num"><?php echo $counts['catechumen'] ?? 0; ?></span>
                    <span class="occi-att-lbl">Catechumens</span>
                </div>
                <div class="occi-att-stat">
                    <span class="occi-att-num"><?php echo $counts['elect'] ?? 0; ?></span>
                    <span class="occi-att-lbl">Elect</span>
                </div>
                <div class="occi-att-stat">
                    <span class="occi-att-num"><?php echo $counts['completed'] ?? 0; ?></span>
                    <span class="occi-att-lbl">Completed</span>
                </div>
            </div>

            <!-- Filters -->
            <form method="get" class="occi-search-form">
                <input type="hidden" name="page" value="occipr-ocia">
                <input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search by name or email...">
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
                <select name="year">
                    <option value="">All Years</option>
                    <?php foreach ( $years as $y ) : ?>
                    <option value="<?php echo esc_attr( $y ); ?>"<?php selected( $year_filter, $y ); ?>><?php echo esc_html( $y ); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="button">Filter</button>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-ocia' ) ); ?>" class="button">Reset</a>
            </form>

            <?php if ( ! $records ) : ?>
            <p>No candidates found.
                <?php if ( current_user_can( 'occipr_manage_records' ) ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-ocia&action=add' ) ); ?>">Add the first candidate.</a>
                <?php endif; ?>
            </p>
            <?php else : ?>
            <p class="occi-count"><?php echo count( $records ); ?> candidate(s) found.</p>
            <table class="widefat striped occi-register-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Enrolled</th>
                        <th>Completion</th>
                        <th>Sponsor</th>
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
                        <td><?php echo $r->enrollment_date ? esc_html( occipr_format_date( $r->enrollment_date ) ) : '--'; ?></td>
                        <td><?php echo $r->completion_date ? esc_html( occipr_format_date( $r->completion_date ) ) : '--'; ?></td>
                        <td class="occi-small"><?php echo esc_html( $r->sponsor_name ?? '' ); ?></td>
                        <td class="occi-small"><?php echo esc_html( $r->parish_name ?? '' ); ?></td>
                        <td class="occi-actions">
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-ocia&action=view&id=' . $r->id ) ); ?>">View</a>
                            <?php if ( current_user_can( 'occipr_manage_records' ) ) : ?>
                            | <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-ocia&action=edit&id=' . $r->id ) ); ?>">Edit</a>
                            | <a class="occi-delete"
                                 href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=occipr_delete_ocia&id=' . $r->id ), 'occipr_delete_ocia_' . $r->id ) ); ?>"
                                 onclick="return confirm('Delete this OCIA record?')">Delete</a>
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
        $r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}occipr_ocia WHERE id = %d", $id ) );
        if ( ! $r ) { wp_die( 'Record not found.' ); }

        $parish = $r->parish_id
            ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}occipr_parishes WHERE id = %d", $r->parish_id ) )
            : null;

        // Linked sacramental records
        $baptism = $r->baptism_record_id
            ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}occipr_baptisms WHERE id = %d", $r->baptism_record_id ) )
            : null;
        $confirmation = $r->confirmation_record_id
            ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}occipr_confirmations WHERE id = %d", $r->confirmation_record_id ) )
            : null;
        $communion = $r->communion_record_id
            ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}occipr_communions WHERE id = %d", $r->communion_record_id ) )
            : null;

        $display_name = ( $r->preferred_name ?: $r->first_name ) . ' ' . $r->last_name;
        ?>
        <div class="wrap occi-wrap">
            <h1><?php echo esc_html( $display_name ); ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-ocia' ) ); ?>" class="page-title-action">&larr; Back to List</a>
            <?php if ( current_user_can( 'occipr_manage_records' ) ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-ocia&action=edit&id=' . $r->id ) ); ?>" class="page-title-action">Edit Record</a>
            <?php endif; ?>
            <hr class="wp-header-end">

            <div class="occi-view-record" style="max-width:860px;">
                <div class="occi-cert-header">
                    <h2>OCIA Candidate Record</h2>
                    <h3><?php echo esc_html( $display_name ); ?></h3>
                    <div><?php echo self::status_badge( $r->status ); ?></div>
                    <?php if ( $parish ) : ?>
                    <p style="margin-top:8px; color:#555;"><?php echo esc_html( $parish->name ); ?></p>
                    <?php endif; ?>
                </div>

                <h3 class="occi-report-section-title" style="margin-top:0;">Personal Information</h3>
                <table class="occi-view-table">
                    <tr><th>Legal Name</th><td><?php echo esc_html( trim( $r->first_name . ' ' . $r->middle_name . ' ' . $r->last_name ) ); ?></td></tr>
                    <?php if ( $r->preferred_name ) : ?>
                    <tr><th>Preferred Name</th><td><?php echo esc_html( $r->preferred_name ); ?></td></tr>
                    <?php endif; ?>
                    <?php if ( $r->birth_date ) : ?>
                    <tr><th>Date of Birth</th><td><?php echo esc_html( occipr_format_date( $r->birth_date ) ); ?></td></tr>
                    <?php endif; ?>
                    <?php if ( $r->address_street || $r->address_city ) : ?>
                    <tr><th>Address</th><td><?php
                        $parts = array_filter( [ $r->address_street, $r->address_city,
                            trim( $r->address_state . ' ' . $r->address_zip ) ] );
                        echo esc_html( implode( ', ', $parts ) );
                    ?></td></tr>
                    <?php endif; ?>
                    <?php if ( $r->phone ) : ?><tr><th>Phone</th><td><?php echo esc_html( $r->phone ); ?></td></tr><?php endif; ?>
                    <?php if ( $r->email ) : ?><tr><th>Email</th><td><?php echo esc_html( $r->email ); ?></td></tr><?php endif; ?>
                    <?php if ( $r->previous_faith ) : ?><tr><th>Previous Faith</th><td><?php echo esc_html( $r->previous_faith ); ?></td></tr><?php endif; ?>
                </table>

                <h3 class="occi-report-section-title">OCIA Journey</h3>
                <table class="occi-view-table">
                    <?php if ( $r->inquiry_date ) : ?><tr><th>Inquiry Date</th><td><?php echo esc_html( occipr_format_date( $r->inquiry_date ) ); ?></td></tr><?php endif; ?>
                    <?php if ( $r->enrollment_date ) : ?><tr><th>Enrolled / Rite of Acceptance</th><td><?php echo esc_html( occipr_format_date( $r->enrollment_date ) ); ?></td></tr><?php endif; ?>
                    <?php if ( $r->election_date ) : ?><tr><th>Rite of Election</th><td><?php echo esc_html( occipr_format_date( $r->election_date ) ); ?></td></tr><?php endif; ?>
                    <?php if ( $r->completion_date ) : ?><tr><th>Completion Date</th><td><?php echo esc_html( occipr_format_date( $r->completion_date ) ); ?></td></tr><?php endif; ?>
                    <tr><th>Status</th><td><?php echo self::status_badge( $r->status ); ?></td></tr>
                    <?php if ( $r->catechist ) : ?><tr><th>Catechist</th><td><?php echo esc_html( $r->catechist ); ?></td></tr><?php endif; ?>
                    <?php if ( $r->presider ) : ?><tr><th>Presider</th><td><?php echo esc_html( $r->presider ); ?></td></tr><?php endif; ?>
                    <?php if ( $r->sponsor_name ) : ?><tr><th>Sponsor</th><td><?php echo esc_html( $r->sponsor_name ); ?></td></tr><?php endif; ?>
                    <?php if ( $r->sponsor2_name ) : ?><tr><th>Second Sponsor</th><td><?php echo esc_html( $r->sponsor2_name ); ?></td></tr><?php endif; ?>
                </table>

                <?php if ( $r->previously_baptized ) : ?>
                <h3 class="occi-report-section-title">Previous Baptism</h3>
                <table class="occi-view-table">
                    <tr><th>Previously Baptized</th><td>Yes</td></tr>
                    <?php if ( $r->prior_baptism_date ) : ?><tr><th>Baptism Date</th><td><?php echo esc_html( occipr_format_date( $r->prior_baptism_date ) ); ?></td></tr><?php endif; ?>
                    <?php if ( $r->prior_baptism_church ) : ?><tr><th>Church</th><td><?php echo esc_html( $r->prior_baptism_church ); ?></td></tr><?php endif; ?>
                </table>
                <?php endif; ?>

                <?php if ( $baptism || $confirmation || $communion ) : ?>
                <h3 class="occi-report-section-title">Sacramental Records</h3>
                <table class="occi-view-table">
                    <?php if ( $baptism ) : ?>
                    <tr>
                        <th>Baptism</th>
                        <td><?php echo esc_html( occipr_format_date( $baptism->baptism_date ) ); ?>
                            &mdash; <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-baptisms&action=view&id=' . $baptism->id ) ); ?>">View Record</a></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ( $confirmation ) : ?>
                    <tr>
                        <th>Confirmation</th>
                        <td><?php echo esc_html( occipr_format_date( $confirmation->confirmation_date ) ); ?>
                            &mdash; <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-confirmations&action=view&id=' . $confirmation->id ) ); ?>">View Record</a></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ( $communion ) : ?>
                    <tr>
                        <th>First Communion</th>
                        <td><?php echo esc_html( occipr_format_date( $communion->communion_date ) ); ?>
                            &mdash; <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-communions&action=view&id=' . $communion->id ) ); ?>">View Record</a></td>
                    </tr>
                    <?php endif; ?>
                </table>
                <?php endif; ?>

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

        $r       = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}occipr_ocia WHERE id = %d", $id ) ) : null;
        $is_edit = (bool) $r;
        $title   = $is_edit ? 'Edit OCIA Candidate: ' . ( $r->preferred_name ?: $r->first_name ) . ' ' . $r->last_name : 'Add OCIA Candidate';

        // Baptism records for linking
        $baptisms = $wpdb->get_results(
            "SELECT id, first_name, last_name, baptism_date FROM {$wpdb->prefix}occipr_baptisms ORDER BY baptism_date DESC, last_name ASC"
        );
        $confirmations = $wpdb->get_results(
            "SELECT id, first_name, last_name, confirmation_date FROM {$wpdb->prefix}occipr_confirmations ORDER BY confirmation_date DESC, last_name ASC"
        );
        $communions = $wpdb->get_results(
            "SELECT id, first_name, last_name, communion_date FROM {$wpdb->prefix}occipr_communions ORDER BY communion_date DESC, last_name ASC"
        );
        ?>
        <div class="wrap occi-wrap">
            <h1><?php echo esc_html( $title ); ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-ocia' ) ); ?>" class="page-title-action">&larr; Back to List</a>
            <hr class="wp-header-end">

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'occipr_save_ocia', 'occipr_ocia_nonce' ); ?>
                <input type="hidden" name="action"  value="occipr_save_ocia">
                <input type="hidden" name="ocia_id" value="<?php echo esc_attr( $id ); ?>">

                <div class="occi-form-columns">
                    <!-- Main column -->
                    <div class="occi-form-col-main">

                        <div class="occi-section">
                            <h2>Personal Information</h2>
                            <table class="form-table">
                                <tr>
                                    <th><label for="first_name">Legal First Name <span class="required">*</span></label></th>
                                    <td><input type="text" id="first_name" name="first_name" class="regular-text"
                                               value="<?php echo esc_attr( $r->first_name ?? '' ); ?>" required></td>
                                </tr>
                                <tr>
                                    <th><label for="preferred_name">Preferred Name</label></th>
                                    <td>
                                        <input type="text" id="preferred_name" name="preferred_name" class="regular-text"
                                               value="<?php echo esc_attr( $r->preferred_name ?? '' ); ?>">
                                        <p class="description">If different from legal name, displayed throughout the directory.</p>
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
                                    <th><label for="previous_faith">Previous Faith Tradition</label></th>
                                    <td>
                                        <input type="text" id="previous_faith" name="previous_faith" class="regular-text"
                                               value="<?php echo esc_attr( $r->previous_faith ?? '' ); ?>"
                                               placeholder="e.g. Baptist, Roman Catholic, None">
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div class="occi-section">
                            <h2>Contact Information</h2>
                            <table class="form-table">
                                <tr>
                                    <th><label for="address_street">Street Address</label></th>
                                    <td><input type="text" id="address_street" name="address_street" class="regular-text"
                                               value="<?php echo esc_attr( $r->address_street ?? '' ); ?>"></td>
                                </tr>
                                <tr>
                                    <th><label for="address_city">City</label></th>
                                    <td><input type="text" id="address_city" name="address_city" class="regular-text"
                                               value="<?php echo esc_attr( $r->address_city ?? '' ); ?>"></td>
                                </tr>
                                <tr>
                                    <th><label for="address_state">State / Province</label></th>
                                    <td><input type="text" id="address_state" name="address_state" class="small-text"
                                               value="<?php echo esc_attr( $r->address_state ?? '' ); ?>"></td>
                                </tr>
                                <tr>
                                    <th><label for="address_zip">ZIP / Postal Code</label></th>
                                    <td><input type="text" id="address_zip" name="address_zip" class="small-text"
                                               value="<?php echo esc_attr( $r->address_zip ?? '' ); ?>"></td>
                                </tr>
                                <tr>
                                    <th><label for="phone">Phone</label></th>
                                    <td><input type="text" id="phone" name="phone" class="regular-text"
                                               value="<?php echo esc_attr( $r->phone ?? '' ); ?>"></td>
                                </tr>
                                <tr>
                                    <th><label for="email">Email</label></th>
                                    <td><input type="email" id="email" name="email" class="regular-text"
                                               value="<?php echo esc_attr( $r->email ?? '' ); ?>"></td>
                                </tr>
                            </table>
                        </div>

                        <div class="occi-section">
                            <h2>OCIA Journey</h2>
                            <table class="form-table">
                                <tr>
                                    <th><label for="inquiry_date">Inquiry Date</label></th>
                                    <td>
                                        <input type="date" id="inquiry_date" name="inquiry_date"
                                               value="<?php echo esc_attr( $r->inquiry_date ?? '' ); ?>">
                                        <p class="description">Date of initial contact or first inquiry session.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="enrollment_date">Enrollment / Rite of Acceptance</label></th>
                                    <td>
                                        <input type="date" id="enrollment_date" name="enrollment_date"
                                               value="<?php echo esc_attr( $r->enrollment_date ?? '' ); ?>">
                                        <p class="description">Date the candidate formally entered the catechumenate.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="election_date">Rite of Election</label></th>
                                    <td>
                                        <input type="date" id="election_date" name="election_date"
                                               value="<?php echo esc_attr( $r->election_date ?? '' ); ?>">
                                        <p class="description">Date the elect were chosen for initiation at Easter.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="completion_date">Completion Date</label></th>
                                    <td>
                                        <input type="date" id="completion_date" name="completion_date"
                                               value="<?php echo esc_attr( $r->completion_date ?? '' ); ?>">
                                        <p class="description">Date sacraments of initiation were received (typically Easter Vigil).</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="status">Status <span class="required">*</span></label></th>
                                    <td>
                                        <select id="status" name="status" required>
                                            <?php foreach ( self::status_options() as $val => $label ) : ?>
                                            <option value="<?php echo esc_attr( $val ); ?>"<?php selected( $r->status ?? 'inquirer', $val ); ?>><?php echo esc_html( $label ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div class="occi-section">
                            <h2>Previous Baptism</h2>
                            <table class="form-table">
                                <tr>
                                    <th>Previously Baptized</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" id="occipr-prev-bap" name="previously_baptized" value="1"<?php checked( $r->previously_baptized ?? 0 ); ?>>
                                            This candidate was baptized in another faith tradition
                                        </label>
                                    </td>
                                </tr>
                                <tr id="occipr-prior-bap-fields"<?php echo ( $r->previously_baptized ?? 0 ) ? '' : ' style="display:none;"'; ?>>
                                    <th><label for="prior_baptism_date">Prior Baptism Date</label></th>
                                    <td><input type="date" id="prior_baptism_date" name="prior_baptism_date"
                                               value="<?php echo esc_attr( $r->prior_baptism_date ?? '' ); ?>"></td>
                                </tr>
                                <tr id="occipr-prior-church-field"<?php echo ( $r->previously_baptized ?? 0 ) ? '' : ' style="display:none;"'; ?>>
                                    <th><label for="prior_baptism_church">Prior Baptism Church</label></th>
                                    <td><input type="text" id="prior_baptism_church" name="prior_baptism_church" class="regular-text"
                                               value="<?php echo esc_attr( $r->prior_baptism_church ?? '' ); ?>"
                                               placeholder="Church name and location"></td>
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
                            <h2>People</h2>
                            <label>Catechist</label>
                            <input type="text" name="catechist" class="widefat" style="margin-bottom:10px;"
                                   value="<?php echo esc_attr( $r->catechist ?? '' ); ?>">
                            <label>Presider</label>
                            <input type="text" name="presider" class="widefat" style="margin-bottom:10px;"
                                   value="<?php echo esc_attr( $r->presider ?? '' ); ?>">
                            <label>Sponsor</label>
                            <input type="text" name="sponsor_name" class="widefat" style="margin-bottom:10px;"
                                   value="<?php echo esc_attr( $r->sponsor_name ?? '' ); ?>">
                            <label>Second Sponsor</label>
                            <input type="text" name="sponsor2_name" class="widefat"
                                   value="<?php echo esc_attr( $r->sponsor2_name ?? '' ); ?>">
                        </div>

                        <div class="occi-form-box" style="margin-top:12px;">
                            <h2>Sacramental Record Links</h2>
                            <p class="description">Optionally link this candidate to existing sacramental records entered after completion of OCIA.</p>

                            <label style="display:block; margin:8px 0 4px; font-size:0.9em;">Baptism Record</label>
                            <select name="baptism_record_id" class="widefat" style="margin-bottom:10px;">
                                <option value="">-- None --</option>
                                <?php foreach ( $baptisms as $b ) : ?>
                                <option value="<?php echo esc_attr( $b->id ); ?>"<?php selected( (int) ( $r->baptism_record_id ?? 0 ), $b->id ); ?>>
                                    <?php echo esc_html( $b->last_name . ', ' . $b->first_name . ' (' . occipr_format_date( $b->baptism_date ) . ')' ); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>

                            <label style="display:block; margin-bottom:4px; font-size:0.9em;">Confirmation Record</label>
                            <select name="confirmation_record_id" class="widefat" style="margin-bottom:10px;">
                                <option value="">-- None --</option>
                                <?php foreach ( $confirmations as $c ) : ?>
                                <option value="<?php echo esc_attr( $c->id ); ?>"<?php selected( (int) ( $r->confirmation_record_id ?? 0 ), $c->id ); ?>>
                                    <?php echo esc_html( $c->last_name . ', ' . $c->first_name . ' (' . occipr_format_date( $c->confirmation_date ) . ')' ); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>

                            <label style="display:block; margin-bottom:4px; font-size:0.9em;">First Communion Record</label>
                            <select name="communion_record_id" class="widefat">
                                <option value="">-- None --</option>
                                <?php foreach ( $communions as $cm ) : ?>
                                <option value="<?php echo esc_attr( $cm->id ); ?>"<?php selected( (int) ( $r->communion_record_id ?? 0 ), $cm->id ); ?>>
                                    <?php echo esc_html( $cm->last_name . ', ' . $cm->first_name . ' (' . occipr_format_date( $cm->communion_date ) . ')' ); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                    </div><!-- .occi-form-col-side -->
                </div><!-- .occi-form-columns -->

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php echo $is_edit ? 'Update Record' : 'Add Candidate'; ?></button>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-ocia' ) ); ?>" class="button">Cancel</a>
                </p>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($){
            $('#occipr-prev-bap').on('change', function(){
                $('#occipr-prior-bap-fields, #occipr-prior-church-field').toggle( $(this).is(':checked') );
            });
        });
        </script>
        <?php
    }

    // -------------------------------------------------------------------------
    // SAVE
    // -------------------------------------------------------------------------

    public static function save() {
        if ( ! current_user_can( 'occipr_manage_records' ) ) { wp_die( 'Access denied.' ); }
        check_admin_referer( 'occipr_save_ocia', 'occipr_ocia_nonce' );
        global $wpdb;

        $data = [
            'first_name'            => sanitize_text_field( $_POST['first_name'] ?? '' ),
            'preferred_name'        => sanitize_text_field( $_POST['preferred_name'] ?? '' ),
            'middle_name'           => sanitize_text_field( $_POST['middle_name'] ?? '' ),
            'last_name'             => sanitize_text_field( $_POST['last_name'] ?? '' ),
            'birth_date'            => sanitize_text_field( $_POST['birth_date'] ?? '' ) ?: null,
            'previous_faith'        => sanitize_text_field( $_POST['previous_faith'] ?? '' ),
            'address_street'        => sanitize_text_field( $_POST['address_street'] ?? '' ),
            'address_city'          => sanitize_text_field( $_POST['address_city'] ?? '' ),
            'address_state'         => sanitize_text_field( $_POST['address_state'] ?? '' ),
            'address_zip'           => sanitize_text_field( $_POST['address_zip'] ?? '' ),
            'phone'                 => sanitize_text_field( $_POST['phone'] ?? '' ),
            'email'                 => sanitize_email( $_POST['email'] ?? '' ),
            'parish_id'             => absint( $_POST['parish_id'] ?? 0 ) ?: null,
            'inquiry_date'          => sanitize_text_field( $_POST['inquiry_date'] ?? '' ) ?: null,
            'enrollment_date'       => sanitize_text_field( $_POST['enrollment_date'] ?? '' ) ?: null,
            'election_date'         => sanitize_text_field( $_POST['election_date'] ?? '' ) ?: null,
            'completion_date'       => sanitize_text_field( $_POST['completion_date'] ?? '' ) ?: null,
            'status'                => sanitize_key( $_POST['status'] ?? 'inquirer' ),
            'catechist'             => sanitize_text_field( $_POST['catechist'] ?? '' ),
            'presider'              => sanitize_text_field( $_POST['presider'] ?? '' ),
            'sponsor_name'          => sanitize_text_field( $_POST['sponsor_name'] ?? '' ),
            'sponsor2_name'         => sanitize_text_field( $_POST['sponsor2_name'] ?? '' ),
            'previously_baptized'   => isset( $_POST['previously_baptized'] ) ? 1 : 0,
            'prior_baptism_date'    => sanitize_text_field( $_POST['prior_baptism_date'] ?? '' ) ?: null,
            'prior_baptism_church'  => sanitize_text_field( $_POST['prior_baptism_church'] ?? '' ),
            'baptism_record_id'     => absint( $_POST['baptism_record_id'] ?? 0 ) ?: null,
            'confirmation_record_id'=> absint( $_POST['confirmation_record_id'] ?? 0 ) ?: null,
            'communion_record_id'   => absint( $_POST['communion_record_id'] ?? 0 ) ?: null,
            'notes'                 => sanitize_textarea_field( $_POST['notes'] ?? '' ),
        ];

        $id = absint( $_POST['ocia_id'] ?? 0 );
        if ( $id ) {
            $result = $wpdb->update( "{$wpdb->prefix}occipr_ocia", $data, [ 'id' => $id ] );
        } else {
            $result = $wpdb->insert( "{$wpdb->prefix}occipr_ocia", $data );
        }
        if ( false === $result ) { wp_die( 'Database error: ' . esc_html( $wpdb->last_error ) ); }

        wp_redirect( admin_url( 'admin.php?page=occipr-ocia&saved=1' ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // DELETE
    // -------------------------------------------------------------------------

    public static function delete() {
        if ( ! current_user_can( 'occipr_manage_records' ) ) { wp_die( 'Access denied.' ); }
        $id = absint( $_GET['id'] ?? 0 );
        check_admin_referer( 'occipr_delete_ocia_' . $id );
        global $wpdb;
        $wpdb->delete( "{$wpdb->prefix}occipr_ocia", [ 'id' => $id ], [ '%d' ] );
        wp_redirect( admin_url( 'admin.php?page=occipr-ocia&deleted=1' ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // HELPERS (for dashboard)
    // -------------------------------------------------------------------------

    public static function active_count(): int {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}occipr_ocia WHERE status IN ('inquirer','catechumen','elect')"
        );
    }
}
