<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OCCIPR_Donations {

    // -------------------------------------------------------------------------
    // OPTION LISTS
    // -------------------------------------------------------------------------

    public static function payment_methods(): array {
        return [
            'cash'       => 'Cash',
            'check'      => 'Check',
            'venmo'      => 'Venmo',
            'paypal'     => 'PayPal',
            'cashapp'    => 'Cash App',
            'liberapay'  => 'Liberapay',
            'tithely'    => 'Tithe.ly',
            'other'      => 'Other',
        ];
    }

    // -------------------------------------------------------------------------
    // INIT
    // -------------------------------------------------------------------------

    public static function init() {
        add_action( 'admin_post_occipr_save_donation',        [ __CLASS__, 'save_donation' ] );
        add_action( 'admin_post_occipr_delete_donation',      [ __CLASS__, 'delete_donation' ] );
        add_action( 'admin_post_occipr_save_donation_fund',   [ __CLASS__, 'save_fund' ] );
        add_action( 'admin_post_occipr_delete_donation_fund', [ __CLASS__, 'delete_fund' ] );
    }

    // -------------------------------------------------------------------------
    // PAGE ROUTER
    // -------------------------------------------------------------------------

    public static function page() {
        if ( ! current_user_can( 'occipr_view_records' ) ) { wp_die( 'Access denied.' ); }
        $action = sanitize_key( $_GET['action'] ?? '' );

        switch ( $action ) {
            case 'add':
            case 'edit':
                self::donation_form_page( absint( $_GET['id'] ?? 0 ) );
                break;
            case 'funds':
                self::funds_page();
                break;
            default:
                self::list_page();
        }
    }

    // -------------------------------------------------------------------------
    // DONATION LIST PAGE
    // -------------------------------------------------------------------------

    private static function list_page() {
        global $wpdb;

        // Filters
        $parish_filter = absint( $_GET['parish_id'] ?? 0 );
        $fund_filter   = absint( $_GET['fund_id'] ?? 0 );
        $method_filter = sanitize_key( $_GET['payment_method'] ?? '' );
        $date_from     = sanitize_text_field( $_GET['date_from'] ?? '' );
        $date_to       = sanitize_text_field( $_GET['date_to'] ?? '' );
        $search        = sanitize_text_field( $_GET['s'] ?? '' );

        $where = 'WHERE 1=1';
        $args  = [];
        if ( $parish_filter ) { $where .= ' AND d.parish_id = %d';       $args[] = $parish_filter; }
        if ( $fund_filter )   { $where .= ' AND d.fund_id = %d';          $args[] = $fund_filter; }
        if ( $method_filter ) { $where .= ' AND d.payment_method = %s';   $args[] = $method_filter; }
        if ( $date_from )     { $where .= ' AND d.donation_date >= %s';   $args[] = $date_from; }
        if ( $date_to )       { $where .= ' AND d.donation_date <= %s';   $args[] = $date_to; }
        if ( $search ) {
            $where .= ' AND (d.donor_name LIKE %s OR d.envelope_number LIKE %s OR d.notes LIKE %s)';
            $like   = '%' . $wpdb->esc_like( $search ) . '%';
            $args[] = $like; $args[] = $like; $args[] = $like;
        }

        $sql = "SELECT d.*,
                    f.name AS fund_name,
                    p.name AS parish_name
                FROM {$wpdb->prefix}occipr_donations d
                LEFT JOIN {$wpdb->prefix}occipr_donation_funds f ON f.id = d.fund_id
                LEFT JOIN {$wpdb->prefix}occipr_parishes p ON p.id = d.parish_id
                $where
                ORDER BY d.donation_date DESC, d.id DESC";
        $records = $args
            ? $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) )
            : $wpdb->get_results( $sql );

        // Summary stats (filtered)
        $sum_sql = "SELECT COUNT(*) AS total_count, SUM(d.amount) AS total_amount
                    FROM {$wpdb->prefix}occipr_donations d $where";
        $stats = $args
            ? $wpdb->get_row( $wpdb->prepare( $sum_sql, ...$args ) )
            : $wpdb->get_row( $sum_sql );

        // Year-to-date (parish filter only)
        $year      = date( 'Y' );
        $ytd_where = "WHERE YEAR(donation_date) = $year";
        $ytd_args  = [];
        if ( $parish_filter ) { $ytd_where .= ' AND parish_id = %d'; $ytd_args[] = $parish_filter; }
        $ytd_sql = "SELECT COUNT(*) AS cnt, SUM(amount) AS total FROM {$wpdb->prefix}occipr_donations $ytd_where";
        $ytd = $ytd_args
            ? $wpdb->get_row( $wpdb->prepare( $ytd_sql, ...$ytd_args ) )
            : $wpdb->get_row( $ytd_sql );

        $parishes = OCCIPR_Database::get_parishes();
        $funds    = self::get_funds();
        $methods  = self::payment_methods();

        $notice = '';
        if ( isset( $_GET['saved'] ) )   $notice = 'Donation saved.';
        if ( isset( $_GET['deleted'] ) ) $notice = 'Donation deleted.';
        ?>
        <div class="wrap occi-wrap">
            <h1 class="wp-heading-inline">
                <span class="dashicons dashicons-money-alt"></span> Donations
            </h1>
            <?php if ( current_user_can( 'occipr_manage_records' ) ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-donations&action=add' ) ); ?>" class="page-title-action">Add Donation</a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-donations&action=funds' ) ); ?>" class="page-title-action">Manage Funds</a>
            <?php endif; ?>
            <hr class="wp-header-end">

            <?php if ( $notice ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
            <?php endif; ?>

            <!-- Summary bar -->
            <div class="occi-attendance-summary">
                <div class="occi-att-stat">
                    <span class="occi-att-num"><?php echo intval( $ytd->cnt ?? 0 ); ?></span>
                    <span class="occi-att-lbl">Donations (<?php echo $year; ?>)</span>
                </div>
                <div class="occi-att-stat">
                    <span class="occi-att-num">$<?php echo number_format( (float) ( $ytd->total ?? 0 ), 2 ); ?></span>
                    <span class="occi-att-lbl">Total (<?php echo $year; ?>)</span>
                </div>
                <div class="occi-att-stat">
                    <span class="occi-att-num"><?php echo intval( $stats->total_count ?? 0 ); ?></span>
                    <span class="occi-att-lbl">Donations (filtered)</span>
                </div>
                <div class="occi-att-stat">
                    <span class="occi-att-num">$<?php echo number_format( (float) ( $stats->total_amount ?? 0 ), 2 ); ?></span>
                    <span class="occi-att-lbl">Amount (filtered)</span>
                </div>
            </div>

            <!-- Filters -->
            <form method="get" class="occi-search-form">
                <input type="hidden" name="page" value="occipr-donations">
                <input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search donor, envelope, notes...">
                <?php if ( count( $parishes ) > 1 ) : ?>
                <select name="parish_id">
                    <option value="">All Parishes</option>
                    <?php foreach ( $parishes as $p ) : ?>
                    <option value="<?php echo esc_attr( $p->id ); ?>"<?php selected( $parish_filter, $p->id ); ?>><?php echo esc_html( $p->name ); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <select name="fund_id">
                    <option value="">All Funds</option>
                    <?php foreach ( $funds as $f ) : ?>
                    <option value="<?php echo esc_attr( $f->id ); ?>"<?php selected( $fund_filter, $f->id ); ?>><?php echo esc_html( $f->name ); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="payment_method">
                    <option value="">All Methods</option>
                    <?php foreach ( $methods as $val => $label ) : ?>
                    <option value="<?php echo esc_attr( $val ); ?>"<?php selected( $method_filter, $val ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" title="From">
                <input type="date" name="date_to"   value="<?php echo esc_attr( $date_to ); ?>"   title="To">
                <button type="submit" class="button">Filter</button>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-donations' ) ); ?>" class="button">Reset</a>
            </form>

            <?php if ( ! $records ) : ?>
            <p>No donations found.
                <?php if ( current_user_can( 'occipr_manage_records' ) ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-donations&action=add' ) ); ?>">Add the first donation.</a>
                <?php endif; ?>
            </p>
            <?php else : ?>
            <p class="occi-count"><?php echo count( $records ); ?> record(s) found.</p>
            <table class="widefat striped occi-register-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Donor</th>
                        <th>Fund</th>
                        <th>Method</th>
                        <th style="text-align:right;">Amount</th>
                        <th>Parish</th>
                        <th>Notes</th>
                        <?php if ( current_user_can( 'occipr_manage_records' ) ) : ?>
                        <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $records as $r ) : ?>
                    <tr>
                        <td><strong><?php echo esc_html( occipr_format_date( $r->donation_date ) ); ?></strong></td>
                        <td>
                            <?php if ( $r->is_anonymous ) : ?>
                            <em style="color:#888;">Anonymous</em>
                            <?php elseif ( $r->donor_name ) : ?>
                            <?php echo esc_html( $r->donor_name ); ?>
                            <?php if ( $r->envelope_number ) : ?>
                            <br><span class="occi-small">Env. #<?php echo esc_html( $r->envelope_number ); ?></span>
                            <?php endif; ?>
                            <?php else : ?>
                            <?php if ( $r->envelope_number ) : ?>
                            <span class="occi-small">Env. #<?php echo esc_html( $r->envelope_number ); ?></span>
                            <?php else : ?>
                            --
                            <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $r->fund_name ?? 'Unassigned' ); ?></td>
                        <td>
                            <?php echo esc_html( $methods[ $r->payment_method ] ?? ucfirst( $r->payment_method ) ); ?>
                            <?php if ( $r->check_number ) : ?>
                            <br><span class="occi-small">#<?php echo esc_html( $r->check_number ); ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right; font-weight:700;">$<?php echo number_format( (float) $r->amount, 2 ); ?></td>
                        <td><?php echo esc_html( $r->parish_name ?? '' ); ?></td>
                        <td class="occi-small"><?php echo esc_html( $r->notes ?? '' ); ?></td>
                        <?php if ( current_user_can( 'occipr_manage_records' ) ) : ?>
                        <td class="occi-actions">
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-donations&action=edit&id=' . $r->id ) ); ?>">Edit</a>
                            | <a class="occi-delete"
                                 href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=occipr_delete_donation&id=' . $r->id ), 'occipr_delete_donation_' . $r->id ) ); ?>"
                                 onclick="return confirm('Delete this donation record?')">Delete</a>
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
    // ADD / EDIT DONATION FORM
    // -------------------------------------------------------------------------

    private static function donation_form_page( int $id ) {
        if ( ! current_user_can( 'occipr_manage_records' ) ) { wp_die( 'Access denied.' ); }
        global $wpdb;

        $r       = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}occipr_donations WHERE id = %d", $id ) ) : null;
        $is_edit = (bool) $r;
        $title   = $is_edit ? 'Edit Donation' : 'Add Donation';
        $methods = self::payment_methods();
        $funds   = self::get_funds();

        if ( ! $funds ) {
            echo '<div class="wrap occi-wrap"><div class="notice notice-warning"><p>'
                . 'No donation funds have been set up yet. '
                . '<a href="' . esc_url( admin_url( 'admin.php?page=occipr-donations&action=funds' ) ) . '">Add at least one fund</a> before recording donations.'
                . '</p></div></div>';
            return;
        }

        $households = $wpdb->get_results( "SELECT id, family_name, address_city FROM {$wpdb->prefix}occipr_households ORDER BY family_name ASC" );
        $is_anon    = (bool) ( $r->is_anonymous ?? false );
        ?>
        <div class="wrap occi-wrap">
            <h1><?php echo esc_html( $title ); ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-donations' ) ); ?>" class="page-title-action">&larr; Back to List</a>
            <hr class="wp-header-end">

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'occipr_save_donation', 'occipr_donation_nonce' ); ?>
                <input type="hidden" name="action"      value="occipr_save_donation">
                <input type="hidden" name="donation_id" value="<?php echo esc_attr( $id ); ?>">

                <div class="occi-form-columns">
                    <!-- Main column -->
                    <div class="occi-form-col-main">

                        <div class="occi-section">
                            <h2>Donation Details</h2>
                            <table class="form-table">
                                <tr>
                                    <th><label for="donation_date">Date <span class="required">*</span></label></th>
                                    <td><input type="date" id="donation_date" name="donation_date"
                                               value="<?php echo esc_attr( $r->donation_date ?? date( 'Y-m-d' ) ); ?>" required></td>
                                </tr>
                                <tr>
                                    <th><label for="fund_id">Fund <span class="required">*</span></label></th>
                                    <td>
                                        <select id="fund_id" name="fund_id" required>
                                            <option value="">-- Select Fund --</option>
                                            <?php foreach ( $funds as $f ) : ?>
                                            <option value="<?php echo esc_attr( $f->id ); ?>"<?php selected( (int) ( $r->fund_id ?? 0 ), $f->id ); ?>><?php echo esc_html( $f->name ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-donations&action=funds' ) ); ?>" class="description" style="margin-left:8px;">Manage Funds</a>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="amount">Amount <span class="required">*</span></label></th>
                                    <td>
                                        <span style="font-size:1.1em; vertical-align:middle;">$</span>
                                        <input type="number" id="amount" name="amount" min="0" step="0.01" class="regular-text"
                                               style="width:140px;"
                                               value="<?php echo esc_attr( $r ? number_format( (float) $r->amount, 2 ) : '' ); ?>" required>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="payment_method">Payment Method</label></th>
                                    <td>
                                        <select id="payment_method" name="payment_method">
                                            <?php foreach ( $methods as $val => $label ) : ?>
                                            <option value="<?php echo esc_attr( $val ); ?>"<?php selected( $r->payment_method ?? 'cash', $val ); ?>><?php echo esc_html( $label ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr id="occipr-check-row" style="<?php echo ( ( $r->payment_method ?? '' ) !== 'check' ) ? 'display:none;' : ''; ?>">
                                    <th><label for="check_number">Check Number</label></th>
                                    <td><input type="text" id="check_number" name="check_number" class="regular-text"
                                               value="<?php echo esc_attr( $r->check_number ?? '' ); ?>"></td>
                                </tr>
                                <tr>
                                    <th><label for="parish_id">Parish</label></th>
                                    <td>
                                        <select id="parish_id" name="parish_id">
                                            <?php echo OCCIPR_Database::parish_dropdown( (int) ( $r->parish_id ?? 0 ) ); ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="don_notes">Notes</label></th>
                                    <td><textarea id="don_notes" name="notes" rows="3" class="large-text"><?php echo esc_textarea( $r->notes ?? '' ); ?></textarea></td>
                                </tr>
                            </table>
                        </div>

                    </div><!-- .occi-form-col-main -->

                    <!-- Side column -->
                    <div class="occi-form-col-side">

                        <div class="occi-form-box">
                            <h2>Donor</h2>
                            <label style="display:block; margin-bottom:12px;">
                                <input type="checkbox" id="occipr-anon-check" name="is_anonymous" value="1"<?php checked( $is_anon ); ?>>
                                Anonymous Donation
                            </label>

                            <div id="occipr-donor-fields"<?php echo $is_anon ? ' style="display:none;"' : ''; ?>>
                                <label style="display:block; margin-bottom:4px; font-size:0.9em;">Donor Name</label>
                                <input type="text" name="donor_name" class="widefat" style="margin-bottom:10px;"
                                       value="<?php echo esc_attr( $r->donor_name ?? '' ); ?>"
                                       placeholder="Full name">

                                <label style="display:block; margin-bottom:4px; font-size:0.9em;">Envelope Number</label>
                                <input type="text" name="envelope_number" class="widefat" style="margin-bottom:10px;"
                                       value="<?php echo esc_attr( $r->envelope_number ?? '' ); ?>"
                                       placeholder="e.g. 042">

                                <label style="display:block; margin-bottom:4px; font-size:0.9em;">Link to Household</label>
                                <select name="household_id" class="widefat" style="margin-bottom:4px;">
                                    <option value="">-- None --</option>
                                    <?php foreach ( $households as $hh ) : ?>
                                    <option value="<?php echo esc_attr( $hh->id ); ?>"<?php selected( (int) ( $r->household_id ?? 0 ), $hh->id ); ?>>
                                        <?php echo esc_html( $hh->family_name . ( $hh->address_city ? ', ' . $hh->address_city : '' ) ); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Optional. Links this donation to a directory household for reporting.</p>
                            </div>
                        </div>

                        <?php if ( $r && $r->source && $r->source !== 'manual' ) : ?>
                        <div class="occi-form-box" style="margin-top:12px;">
                            <h2>Import Info</h2>
                            <p class="description">Source: <strong><?php echo esc_html( ucfirst( $r->source ) ); ?></strong></p>
                            <?php if ( $r->external_id ) : ?>
                            <p class="description">External ID: <code><?php echo esc_html( $r->external_id ); ?></code></p>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                    </div><!-- .occi-form-col-side -->
                </div><!-- .occi-form-columns -->

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php echo $is_edit ? 'Update Donation' : 'Save Donation'; ?></button>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-donations' ) ); ?>" class="button">Cancel</a>
                </p>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($){
            // Toggle check number row
            $('#payment_method').on('change', function(){
                $('#occipr-check-row').toggle( $(this).val() === 'check' );
            });
            // Toggle donor fields
            $('#occipr-anon-check').on('change', function(){
                $('#occipr-donor-fields').toggle( ! $(this).is(':checked') );
            });
        });
        </script>
        <?php
    }

    // -------------------------------------------------------------------------
    // FUND MANAGEMENT PAGE
    // -------------------------------------------------------------------------

    private static function funds_page() {
        if ( ! current_user_can( 'occipr_manage_records' ) ) { wp_die( 'Access denied.' ); }
        global $wpdb;

        $message = '';
        if ( isset( $_GET['saved'] ) )   $message = '<div class="notice notice-success is-dismissible"><p>Fund saved.</p></div>';
        if ( isset( $_GET['deleted'] ) ) $message = '<div class="notice notice-success is-dismissible"><p>Fund deleted.</p></div>';

        $editing = null;
        if ( isset( $_GET['edit_fund'] ) ) {
            $editing = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}occipr_donation_funds WHERE id = %d",
                intval( $_GET['edit_fund'] )
            ) );
        }

        $funds = $wpdb->get_results( "SELECT *, (SELECT COUNT(*) FROM {$wpdb->prefix}occipr_donations WHERE fund_id = f.id) AS donation_count FROM {$wpdb->prefix}occipr_donation_funds f ORDER BY sort_order ASC, name ASC" );
        ?>
        <div class="wrap occi-wrap">
            <h1>Donation Funds</h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-donations' ) ); ?>" class="page-title-action">&larr; Back to Donations</a>
            <hr class="wp-header-end">
            <?php echo $message; ?>

            <div class="occi-two-col">

                <!-- Fund list -->
                <div class="occi-col-main">
                    <table class="wp-list-table widefat fixed striped">
                        <thead><tr>
                            <th>Fund Name</th>
                            <th>Description</th>
                            <th style="text-align:center;">Donations</th>
                            <th style="text-align:center;">Active</th>
                            <th>Actions</th>
                        </tr></thead>
                        <tbody>
                        <?php if ( $funds ) : foreach ( $funds as $f ) : ?>
                            <tr>
                                <td><strong><?php echo esc_html( $f->name ); ?></strong></td>
                                <td class="occi-small"><?php echo esc_html( $f->description ?? '' ); ?></td>
                                <td style="text-align:center;"><?php echo intval( $f->donation_count ); ?></td>
                                <td style="text-align:center;"><?php echo $f->is_active ? '&#10003;' : '--'; ?></td>
                                <td>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-donations&action=funds&edit_fund=' . $f->id ) ); ?>">Edit</a>
                                    <?php if ( ! $f->donation_count ) : ?>
                                    | <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=occipr_delete_donation_fund&id=' . $f->id ), 'occipr_delete_fund_' . $f->id ) ); ?>"
                                          onclick="return confirm('Delete this fund?')">Delete</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; else : ?>
                            <tr><td colspan="5">No funds yet. Add one using the form.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                    <p class="description" style="margin-top:8px;">Funds with existing donation records cannot be deleted. Mark them inactive instead.</p>
                </div>

                <!-- Add / Edit form -->
                <div class="occi-col-side">
                    <div class="occi-form-box">
                        <h2><?php echo $editing ? 'Edit Fund' : 'Add Fund'; ?></h2>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <?php wp_nonce_field( 'occipr_save_donation_fund', 'occipr_fund_nonce' ); ?>
                            <input type="hidden" name="action"  value="occipr_save_donation_fund">
                            <?php if ( $editing ) : ?>
                            <input type="hidden" name="fund_id" value="<?php echo esc_attr( $editing->id ); ?>">
                            <?php endif; ?>

                            <table class="form-table">
                                <tr>
                                    <th><label>Fund Name <span class="required">*</span></label></th>
                                    <td><input type="text" name="name" class="regular-text"
                                               value="<?php echo esc_attr( $editing->name ?? '' ); ?>" required></td>
                                </tr>
                                <tr>
                                    <th><label>Description</label></th>
                                    <td><textarea name="description" rows="2" class="regular-text"><?php echo esc_textarea( $editing->description ?? '' ); ?></textarea></td>
                                </tr>
                                <tr>
                                    <th><label>Sort Order</label></th>
                                    <td>
                                        <input type="number" name="sort_order" min="0" class="small-text"
                                               value="<?php echo esc_attr( $editing->sort_order ?? 0 ); ?>">
                                        <p class="description">Lower numbers appear first.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label>Active</label></th>
                                    <td><label><input type="checkbox" name="is_active" value="1"<?php checked( $editing ? $editing->is_active : 1 ); ?>> Show in dropdown when adding donations</label></td>
                                </tr>
                            </table>

                            <p>
                                <button type="submit" class="button button-primary"><?php echo $editing ? 'Update Fund' : 'Add Fund'; ?></button>
                                <?php if ( $editing ) : ?>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-donations&action=funds' ) ); ?>" class="button">Cancel</a>
                                <?php endif; ?>
                            </p>
                        </form>
                    </div>
                </div>

            </div>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // SAVE DONATION
    // -------------------------------------------------------------------------

    public static function save_donation() {
        if ( ! current_user_can( 'occipr_manage_records' ) ) { wp_die( 'Access denied.' ); }
        check_admin_referer( 'occipr_save_donation', 'occipr_donation_nonce' );
        global $wpdb;

        $is_anon = isset( $_POST['is_anonymous'] );
        $data = [
            'donation_date'  => sanitize_text_field( $_POST['donation_date'] ?? '' ),
            'fund_id'        => absint( $_POST['fund_id'] ?? 0 ) ?: null,
            'amount'         => round( (float) ( $_POST['amount'] ?? 0 ), 2 ),
            'payment_method' => sanitize_key( $_POST['payment_method'] ?? 'cash' ),
            'check_number'   => sanitize_text_field( $_POST['check_number'] ?? '' ),
            'parish_id'      => absint( $_POST['parish_id'] ?? 0 ) ?: null,
            'is_anonymous'   => $is_anon ? 1 : 0,
            'donor_name'     => $is_anon ? '' : sanitize_text_field( $_POST['donor_name'] ?? '' ),
            'envelope_number'=> $is_anon ? '' : sanitize_text_field( $_POST['envelope_number'] ?? '' ),
            'household_id'   => ( ! $is_anon && absint( $_POST['household_id'] ?? 0 ) ) ? absint( $_POST['household_id'] ) : null,
            'notes'          => sanitize_textarea_field( $_POST['notes'] ?? '' ),
            'source'         => 'manual',
        ];
        $fmt = [ '%s','%d','%f','%s','%s','%d','%d','%s','%s','%d','%s','%s' ];

        $id = absint( $_POST['donation_id'] ?? 0 );
        if ( $id ) {
            // Preserve source/external_id on edits
            unset( $data['source'] );
            array_pop( $fmt );
            $result = $wpdb->update( "{$wpdb->prefix}occipr_donations", $data, [ 'id' => $id ], $fmt, [ '%d' ] );
            if ( false === $result ) { wp_die( 'Database error: ' . esc_html( $wpdb->last_error ) ); }
        } else {
            $result = $wpdb->insert( "{$wpdb->prefix}occipr_donations", $data, $fmt );
            if ( false === $result ) { wp_die( 'Database error: ' . esc_html( $wpdb->last_error ) ); }
        }

        wp_redirect( admin_url( 'admin.php?page=occipr-donations&saved=1' ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // DELETE DONATION
    // -------------------------------------------------------------------------

    public static function delete_donation() {
        if ( ! current_user_can( 'occipr_manage_records' ) ) { wp_die( 'Access denied.' ); }
        $id = absint( $_GET['id'] ?? 0 );
        check_admin_referer( 'occipr_delete_donation_' . $id );
        global $wpdb;
        $wpdb->delete( "{$wpdb->prefix}occipr_donations", [ 'id' => $id ], [ '%d' ] );
        wp_redirect( admin_url( 'admin.php?page=occipr-donations&deleted=1' ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // SAVE FUND
    // -------------------------------------------------------------------------

    public static function save_fund() {
        if ( ! current_user_can( 'occipr_manage_records' ) ) { wp_die( 'Access denied.' ); }
        check_admin_referer( 'occipr_save_donation_fund', 'occipr_fund_nonce' );
        global $wpdb;

        $data = [
            'name'        => sanitize_text_field( $_POST['name'] ?? '' ),
            'description' => sanitize_textarea_field( $_POST['description'] ?? '' ),
            'sort_order'  => absint( $_POST['sort_order'] ?? 0 ),
            'is_active'   => isset( $_POST['is_active'] ) ? 1 : 0,
        ];
        $fmt = [ '%s', '%s', '%d', '%d' ];

        $id = absint( $_POST['fund_id'] ?? 0 );
        if ( $id ) {
            $result = $wpdb->update( "{$wpdb->prefix}occipr_donation_funds", $data, [ 'id' => $id ], $fmt, [ '%d' ] );
        } else {
            $result = $wpdb->insert( "{$wpdb->prefix}occipr_donation_funds", $data, $fmt );
        }
        if ( false === $result ) { wp_die( 'Database error: ' . esc_html( $wpdb->last_error ) ); }

        wp_redirect( admin_url( 'admin.php?page=occipr-donations&action=funds&saved=1' ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // DELETE FUND
    // -------------------------------------------------------------------------

    public static function delete_fund() {
        if ( ! current_user_can( 'occipr_manage_records' ) ) { wp_die( 'Access denied.' ); }
        $id = absint( $_GET['id'] ?? 0 );
        check_admin_referer( 'occipr_delete_fund_' . $id );
        global $wpdb;
        // Safety: only delete if no donations reference this fund
        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}occipr_donations WHERE fund_id = %d", $id
        ) );
        if ( $count ) { wp_die( 'Cannot delete a fund that has donation records.' ); }
        $wpdb->delete( "{$wpdb->prefix}occipr_donation_funds", [ 'id' => $id ], [ '%d' ] );
        wp_redirect( admin_url( 'admin.php?page=occipr-donations&action=funds&deleted=1' ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // HELPERS
    // -------------------------------------------------------------------------

    public static function get_funds( bool $active_only = false ): array {
        global $wpdb;
        $where = $active_only ? 'WHERE is_active = 1' : '';
        return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}occipr_donation_funds $where ORDER BY sort_order ASC, name ASC" ) ?: [];
    }

    /** Year-to-date donation total -- used by dashboard. */
    public static function ytd_total(): float {
        global $wpdb;
        return (float) $wpdb->get_var(
            "SELECT SUM(amount) FROM {$wpdb->prefix}occipr_donations WHERE YEAR(donation_date) = " . date( 'Y' )
        );
    }

    /** Year-to-date donation count -- used by dashboard. */
    public static function ytd_count(): int {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}occipr_donations WHERE YEAR(donation_date) = " . date( 'Y' )
        );
    }
}
