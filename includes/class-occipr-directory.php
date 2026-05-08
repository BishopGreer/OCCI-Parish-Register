<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OCCIPR_Directory {

    // -------------------------------------------------------------------------
    // OPTION LISTS
    // -------------------------------------------------------------------------

    private static function gender_options(): array {
        return [
            ''              => '-- Select --',
            'male'          => 'Male',
            'female'        => 'Female',
            'nonbinary'     => 'Nonbinary',
            'trans_female'  => 'Transgender Female',
            'trans_male'    => 'Transgender Male',
            'genderqueer'   => 'Genderqueer',
            'genderfluid'   => 'Genderfluid',
            'two_spirit'    => 'Two-Spirit',
            'agender'       => 'Agender',
            'prefer_not'    => 'Prefer Not to Say',
            'self_describe' => 'Self-Describe',
        ];
    }

    private static function relationship_options(): array {
        return [
            'head'         => 'Head of Household',
            'spouse'       => 'Spouse',
            'partner'      => 'Partner',
            'child'        => 'Child',
            'grandparent'  => 'Grandparent',
            'other_family' => 'Other Family Member',
            'other'        => 'Other',
        ];
    }

    private static function status_options(): array {
        return [
            'active'   => 'Active',
            'inactive' => 'Inactive',
            'moved'    => 'Moved',
            'deceased' => 'Deceased',
        ];
    }

    // -------------------------------------------------------------------------
    // INIT
    // -------------------------------------------------------------------------

    public static function init() {
        add_action( 'admin_post_occipr_save_household',   [ __CLASS__, 'save_household' ] );
        add_action( 'admin_post_occipr_save_member',      [ __CLASS__, 'save_member' ] );
        add_action( 'admin_post_occipr_delete_household', [ __CLASS__, 'handle_delete_household' ] );
        add_action( 'admin_post_occipr_delete_member',    [ __CLASS__, 'handle_delete_member' ] );
    }

    // -------------------------------------------------------------------------
    // PAGE ROUTER
    // -------------------------------------------------------------------------

    public static function page() {
        if ( ! current_user_can( 'occipr_view_records' ) ) { wp_die( 'Access denied.' ); }
        $action = sanitize_key( $_GET['action'] ?? '' );

        switch ( $action ) {
            case 'add_household':
                self::household_form_page( 0 );
                break;
            case 'edit_household':
                self::household_form_page( absint( $_GET['id'] ?? 0 ) );
                break;
            case 'view_household':
                self::household_detail_page( absint( $_GET['id'] ?? 0 ) );
                break;
            case 'add_member':
                self::member_form_page( 0, absint( $_GET['household_id'] ?? 0 ) );
                break;
            case 'edit_member':
                self::member_form_page( absint( $_GET['id'] ?? 0 ), 0 );
                break;
            case 'print_directory':
                self::print_directory( false, absint( $_GET['parish_id'] ?? 0 ) );
                break;
            case 'print_admin_directory':
                self::print_directory( true, absint( $_GET['parish_id'] ?? 0 ) );
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

        $search = sanitize_text_field( $_GET['s'] ?? '' );
        $where  = 'WHERE 1=1';
        $args   = [];

        if ( $search ) {
            $where .= " AND (h.family_name LIKE %s OR h.address_city LIKE %s
                          OR m.first_name LIKE %s OR m.last_name LIKE %s
                          OR m.preferred_name LIKE %s)";
            $like   = '%' . $wpdb->esc_like( $search ) . '%';
            $args   = [ $like, $like, $like, $like, $like ];
        }

        $sql = "SELECT DISTINCT h.*,
                    (SELECT COUNT(*) FROM {$wpdb->prefix}occipr_members WHERE household_id = h.id) AS member_count
                FROM {$wpdb->prefix}occipr_households h
                LEFT JOIN {$wpdb->prefix}occipr_members m ON m.household_id = h.id
                $where
                ORDER BY h.family_name ASC";

        $households = $args
            ? $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) )
            : $wpdb->get_results( $sql );

        $statuses   = self::status_options();
        $notice     = '';
        if ( isset( $_GET['added'] ) )   $notice = 'Household added.';
        if ( isset( $_GET['updated'] ) ) $notice = 'Household updated.';
        if ( isset( $_GET['deleted'] ) ) $notice = 'Record deleted.';
        if ( isset( $_GET['saved'] ) )   $notice = 'Member saved.';
        ?>
        <div class="wrap occi-wrap">
            <h1 class="wp-heading-inline">
                <span class="dashicons dashicons-groups"></span> Parish Directory
            </h1>
            <?php if ( current_user_can( 'occipr_manage_records' ) ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-directory&action=add_household' ) ); ?>" class="page-title-action">Add Household</a>
            <?php endif; ?>
            <?php
            $parishes_for_print = OCCIPR_Database::get_parishes();
            if ( count( $parishes_for_print ) === 1 ) :
                $pid = $parishes_for_print[0]->id;
            ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-directory&action=print_directory&parish_id=' . $pid ) ); ?>" class="page-title-action" target="_blank">Print Directory</a>
            <?php if ( current_user_can( 'occipr_manage_records' ) ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-directory&action=print_admin_directory&parish_id=' . $pid ) ); ?>" class="page-title-action" target="_blank">Print Admin Directory</a>
            <?php endif; ?>
            <?php elseif ( count( $parishes_for_print ) > 1 ) : ?>
            <span class="page-title-action" style="cursor:default;">Print Directory:</span>
            <?php foreach ( $parishes_for_print as $pp ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-directory&action=print_directory&parish_id=' . $pp->id ) ); ?>" class="page-title-action" target="_blank"><?php echo esc_html( $pp->name ); ?></a>
            <?php endforeach; ?>
            <?php if ( current_user_can( 'occipr_manage_records' ) ) : ?>
            <span class="page-title-action" style="cursor:default;">Admin Directory:</span>
            <?php foreach ( $parishes_for_print as $pp ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-directory&action=print_admin_directory&parish_id=' . $pp->id ) ); ?>" class="page-title-action" target="_blank"><?php echo esc_html( $pp->name ); ?></a>
            <?php endforeach; ?>
            <?php endif; ?>
            <?php else : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-directory&action=print_directory' ) ); ?>" class="page-title-action" target="_blank">Print Directory</a>
            <?php if ( current_user_can( 'occipr_manage_records' ) ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-directory&action=print_admin_directory' ) ); ?>" class="page-title-action" target="_blank">Print Admin Directory</a>
            <?php endif; ?>
            <?php endif; ?>
            <hr class="wp-header-end">

            <?php if ( $notice ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
            <?php endif; ?>

            <form method="get">
                <input type="hidden" name="page" value="occipr-directory">
                <p class="search-box">
                    <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Search by family name, city, or member name...">
                    <button type="submit" class="button">Search</button>
                    <?php if ( $search ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-directory' ) ); ?>" class="button">Clear</a>
                    <?php endif; ?>
                </p>
            </form>

            <?php if ( ! $households ) : ?>
            <p>No households found.
                <?php if ( current_user_can( 'occipr_manage_records' ) ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-directory&action=add_household' ) ); ?>">Add the first household.</a>
                <?php endif; ?>
            </p>
            <?php else : ?>
            <p class="occi-count"><?php echo count( $households ); ?> household(s) found.</p>
            <table class="widefat striped occi-table occi-register-table">
                <thead>
                    <tr>
                        <th>Family Name</th>
                        <th>City / State</th>
                        <th>Phone</th>
                        <th>Members</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $households as $h ) : ?>
                    <tr>
                        <td><strong><?php echo esc_html( $h->family_name ); ?></strong></td>
                        <td><?php echo esc_html( trim( $h->address_city . ( $h->address_state ? ', ' . $h->address_state : '' ) ) ); ?></td>
                        <td><?php echo esc_html( $h->phone ); ?></td>
                        <td><?php echo intval( $h->member_count ); ?></td>
                        <td><?php echo esc_html( $statuses[ $h->status ] ?? ucfirst( $h->status ) ); ?></td>
                        <td class="occi-actions">
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-directory&action=view_household&id=' . $h->id ) ); ?>">View</a>
                            <?php if ( current_user_can( 'occipr_manage_records' ) ) : ?>
                            | <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-directory&action=edit_household&id=' . $h->id ) ); ?>">Edit</a>
                            | <a class="occi-delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=occipr_delete_household&id=' . $h->id ), 'occipr_delete_household_' . $h->id ) ); ?>">Delete</a>
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
    // HOUSEHOLD DETAIL PAGE
    // -------------------------------------------------------------------------

    private static function household_detail_page( int $id ) {
        $h = self::get_household( $id );
        if ( ! $h ) { wp_die( 'Household not found.' ); }

        $members  = self::get_members( $id );
        $statuses = self::status_options();
        $rels     = self::relationship_options();
        $genders  = self::gender_options();
        $photo    = $h->photo_id ? wp_get_attachment_image_url( (int) $h->photo_id, 'medium' ) : '';

        $notice = '';
        if ( isset( $_GET['saved'] ) )   $notice = 'Member saved.';
        if ( isset( $_GET['deleted'] ) ) $notice = 'Member removed.';
        if ( isset( $_GET['updated'] ) ) $notice = 'Household updated.';
        if ( isset( $_GET['added'] ) )   $notice = 'Household added.';
        ?>
        <div class="wrap occi-wrap">
            <h1>
                <span class="dashicons dashicons-groups"></span>
                <?php echo esc_html( $h->family_name ); ?>
            </h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-directory' ) ); ?>" class="page-title-action">&larr; All Households</a>
            <?php if ( current_user_can( 'occipr_manage_records' ) ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-directory&action=edit_household&id=' . $h->id ) ); ?>" class="page-title-action">Edit Household</a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-directory&action=add_member&household_id=' . $h->id ) ); ?>" class="page-title-action">Add Member</a>
            <?php endif; ?>
            <hr class="wp-header-end">

            <?php if ( $notice ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
            <?php endif; ?>

            <div class="occi-household-detail">
                <?php if ( $photo ) : ?>
                <div class="occi-household-photo">
                    <img src="<?php echo esc_url( $photo ); ?>" alt="Family photo for <?php echo esc_attr( $h->family_name ); ?>">
                </div>
                <?php endif; ?>

                <table class="occi-view-table" style="max-width:700px;margin-bottom:24px;">
                    <tr>
                        <th>Address</th>
                        <td>
                            <?php
                            $parts = array_filter( [
                                $h->address_street,
                                trim( $h->address_city . ( $h->address_state ? ', ' . $h->address_state : '' ) . ' ' . $h->address_zip ),
                                $h->address_country !== 'USA' ? $h->address_country : '',
                            ] );
                            echo nl2br( esc_html( implode( "\n", $parts ) ) );
                            ?>
                        </td>
                    </tr>
                    <tr><th>Phone</th><td><?php echo esc_html( $h->phone ); ?></td></tr>
                    <tr><th>Email</th><td><?php echo esc_html( $h->email ); ?></td></tr>
                    <tr><th>Status</th><td><?php echo esc_html( $statuses[ $h->status ] ?? ucfirst( $h->status ) ); ?></td></tr>
                    <tr><th>Envelope #</th><td><?php echo esc_html( $h->envelope_number ); ?></td></tr>
                    <tr>
                        <th>Directory Privacy</th>
                        <td>
                            <?php
                            $hidden = [];
                            if ( ! $h->show_address ) $hidden[] = 'Address';
                            if ( ! $h->show_phone )   $hidden[] = 'Phone';
                            if ( ! $h->show_email )   $hidden[] = 'Email';
                            if ( ! $h->show_photo )   $hidden[] = 'Photo';
                            echo $hidden
                                ? '<span style="color:#c0392b;">Hidden from public directory: ' . esc_html( implode( ', ', $hidden ) ) . '</span>'
                                : 'All fields visible in public directory';
                            ?>
                        </td>
                    </tr>
                    <?php if ( $h->notes ) : ?>
                    <tr><th>Notes</th><td><?php echo nl2br( esc_html( $h->notes ) ); ?></td></tr>
                    <?php endif; ?>
                </table>
            </div>

            <h2>Members</h2>
            <?php if ( ! $members ) : ?>
            <p>No members yet.
                <?php if ( current_user_can( 'occipr_manage_records' ) ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-directory&action=add_member&household_id=' . $h->id ) ); ?>">Add the first member.</a>
                <?php endif; ?>
            </p>
            <?php else : ?>
            <div class="occi-members-grid">
                <?php foreach ( $members as $m ) :
                    $m_photo     = $m->photo_id ? wp_get_attachment_image_url( (int) $m->photo_id, 'thumbnail' ) : '';
                    $display     = $m->preferred_name ?: $m->first_name;
                    $legal_diff  = $m->preferred_name && $m->preferred_name !== $m->first_name;
                    $gender_disp = $m->gender === 'self_describe' ? $m->gender_other : ( $genders[ $m->gender ] ?? $m->gender );
                    $hidden_m    = [];
                    if ( ! $m->show_phone )   $hidden_m[] = 'Phone';
                    if ( ! $m->show_email )   $hidden_m[] = 'Email';
                    if ( ! $m->show_social )  $hidden_m[] = 'Social';
                    if ( ! $m->show_birthday )$hidden_m[] = 'Birthday';
                    if ( ! $m->show_photo )   $hidden_m[] = 'Photo';
                ?>
                <div class="occi-member-card">
                    <?php if ( $m_photo ) : ?>
                    <div class="occi-member-photo">
                        <img src="<?php echo esc_url( $m_photo ); ?>" alt="">
                    </div>
                    <?php endif; ?>
                    <div class="occi-member-body">
                        <div class="occi-member-name">
                            <?php echo esc_html( $display . ' ' . $m->last_name ); ?>
                            <?php if ( $legal_diff ) : ?>
                            <span class="occi-legal-name">(Legal: <?php echo esc_html( $m->first_name ); ?>)</span>
                            <?php endif; ?>
                        </div>
                        <div class="occi-member-role">
                            <?php echo esc_html( $rels[ $m->relationship ] ?? ucfirst( $m->relationship ) ); ?>
                            <?php if ( $m->pronouns ) : ?>
                            &bull; <em><?php echo esc_html( $m->pronouns ); ?></em>
                            <?php endif; ?>
                        </div>
                        <?php if ( $gender_disp ) : ?>
                        <div class="occi-small"><?php echo esc_html( $gender_disp ); ?></div>
                        <?php endif; ?>
                        <?php if ( $m->birth_date ) : ?>
                        <div class="occi-small">Born: <?php echo esc_html( date( 'F j, Y', strtotime( $m->birth_date ) ) ); ?></div>
                        <?php endif; ?>
                        <?php if ( $m->phone ) : ?>
                        <div class="occi-small"><?php echo esc_html( $m->phone ); ?></div>
                        <?php endif; ?>
                        <?php if ( $m->email ) : ?>
                        <div class="occi-small"><?php echo esc_html( $m->email ); ?></div>
                        <?php endif; ?>
                        <?php if ( $hidden_m ) : ?>
                        <div class="occi-small" style="color:#c0392b;margin-top:4px;">Hidden: <?php echo esc_html( implode( ', ', $hidden_m ) ); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if ( current_user_can( 'occipr_manage_records' ) ) : ?>
                    <div class="occi-member-actions">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-directory&action=edit_member&id=' . $m->id ) ); ?>" class="button button-small">Edit</a>
                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=occipr_delete_member&id=' . $m->id . '&household_id=' . $h->id ), 'occipr_delete_member_' . $m->id ) ); ?>" class="button button-small occi-delete">Remove</a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // HOUSEHOLD FORM (ADD / EDIT)
    // -------------------------------------------------------------------------

    private static function household_form_page( int $id ) {
        if ( ! current_user_can( 'occipr_manage_records' ) ) { wp_die( 'Access denied.' ); }

        $h       = $id ? self::get_household( $id ) : null;
        $is_edit = (bool) $h;
        $title   = $is_edit ? 'Edit Household: ' . $h->family_name : 'Add Household';
        $back    = $is_edit
            ? admin_url( 'admin.php?page=occipr-directory&action=view_household&id=' . $id )
            : admin_url( 'admin.php?page=occipr-directory' );
        ?>
        <div class="wrap occi-wrap">
            <h1><?php echo esc_html( $title ); ?></h1>
            <a href="<?php echo esc_url( $back ); ?>" class="page-title-action">&larr; Back</a>
            <hr class="wp-header-end">

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'occipr_save_household', 'occipr_household_nonce' ); ?>
                <input type="hidden" name="action" value="occipr_save_household">
                <input type="hidden" name="household_id" value="<?php echo esc_attr( $id ); ?>">

                <div class="occi-form-columns">
                    <div class="occi-form-col-main">

                        <div class="occi-section">
                            <h2>Household Information</h2>
                            <table class="form-table">
                                <tr>
                                    <th><label for="family_name">Family Name <span class="required">*</span></label></th>
                                    <td><input type="text" id="family_name" name="family_name" value="<?php echo esc_attr( $h->family_name ?? '' ); ?>" class="regular-text" required></td>
                                </tr>
                                <tr>
                                    <th><label for="address_street">Street Address</label></th>
                                    <td><input type="text" id="address_street" name="address_street" value="<?php echo esc_attr( $h->address_street ?? '' ); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th><label for="address_city">City</label></th>
                                    <td><input type="text" id="address_city" name="address_city" value="<?php echo esc_attr( $h->address_city ?? '' ); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th><label for="address_state">State / Province</label></th>
                                    <td><input type="text" id="address_state" name="address_state" value="<?php echo esc_attr( $h->address_state ?? '' ); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th><label for="address_zip">ZIP / Postal Code</label></th>
                                    <td><input type="text" id="address_zip" name="address_zip" value="<?php echo esc_attr( $h->address_zip ?? '' ); ?>" class="small-text"></td>
                                </tr>
                                <tr>
                                    <th><label for="address_country">Country</label></th>
                                    <td><input type="text" id="address_country" name="address_country" value="<?php echo esc_attr( $h->address_country ?? 'USA' ); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th><label for="phone">Household Phone</label></th>
                                    <td><input type="text" id="phone" name="phone" value="<?php echo esc_attr( $h->phone ?? '' ); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th><label for="email">Household Email</label></th>
                                    <td><input type="email" id="email" name="email" value="<?php echo esc_attr( $h->email ?? '' ); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th><label for="envelope_number">Envelope Number</label></th>
                                    <td><input type="text" id="envelope_number" name="envelope_number" value="<?php echo esc_attr( $h->envelope_number ?? '' ); ?>" class="small-text" placeholder="e.g. 042"></td>
                                </tr>
                                <tr>
                                    <th><label for="hh_status">Status</label></th>
                                    <td>
                                        <select id="hh_status" name="status">
                                            <?php foreach ( self::status_options() as $val => $label ) : ?>
                                            <option value="<?php echo esc_attr( $val ); ?>"<?php selected( $h->status ?? 'active', $val ); ?>><?php echo esc_html( $label ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="notes">Admin Notes</label></th>
                                    <td><textarea id="notes" name="notes" rows="3" class="regular-text"><?php echo esc_textarea( $h->notes ?? '' ); ?></textarea></td>
                                </tr>
                            </table>
                        </div>

                        <div class="occi-section">
                            <h2>Family Photo</h2>
                            <?php self::photo_uploader( (int) ( $h->photo_id ?? 0 ), 'photo_id', 'hh_photo' ); ?>
                        </div>

                    </div><!-- .occi-form-col-main -->

                    <div class="occi-form-col-side">

                        <div class="occi-form-box">
                            <h2>Directory Privacy</h2>
                            <p class="description">Uncheck to hide a field from the public printed directory. The administrative directory always shows everything.</p>
                            <p>
                                <label><input type="checkbox" name="show_address" value="1" <?php checked( $h->show_address ?? 1 ); ?>> Show Address</label><br>
                                <label><input type="checkbox" name="show_phone" value="1" <?php checked( $h->show_phone ?? 1 ); ?>> Show Phone</label><br>
                                <label><input type="checkbox" name="show_email" value="1" <?php checked( $h->show_email ?? 1 ); ?>> Show Email</label><br>
                                <label><input type="checkbox" name="show_photo" value="1" <?php checked( $h->show_photo ?? 1 ); ?>> Show Photo</label>
                            </p>
                        </div>

                        <div class="occi-form-box" style="margin-top:16px;">
                            <h2>Parish</h2>
                            <select name="parish_id" style="width:100%;">
                                <?php echo OCCIPR_Database::parish_dropdown( (int) ( $h->parish_id ?? 0 ) ); ?>
                            </select>
                        </div>

                    </div><!-- .occi-form-col-side -->
                </div><!-- .occi-form-columns -->

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php echo $is_edit ? 'Update Household' : 'Add Household'; ?></button>
                    <a href="<?php echo esc_url( $back ); ?>" class="button">Cancel</a>
                </p>
            </form>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // MEMBER FORM (ADD / EDIT)
    // -------------------------------------------------------------------------

    private static function member_form_page( int $member_id, int $household_id ) {
        if ( ! current_user_can( 'occipr_manage_records' ) ) { wp_die( 'Access denied.' ); }
        global $wpdb;

        $m = $member_id
            ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}occipr_members WHERE id = %d", $member_id ) )
            : null;
        if ( ! $m && $member_id ) { wp_die( 'Member not found.' ); }

        $h_id = $m ? (int) $m->household_id : $household_id;
        $h    = self::get_household( $h_id );
        if ( ! $h ) { wp_die( 'Household not found.' ); }

        $is_edit       = (bool) $m;
        $display       = $m ? ( $m->preferred_name ?: $m->first_name ) . ' ' . $m->last_name : '';
        $title         = $is_edit ? 'Edit Member: ' . $display : 'Add Member to ' . $h->family_name;
        $current_gender = $m->gender ?? '';
        $back           = admin_url( 'admin.php?page=occipr-directory&action=view_household&id=' . $h_id );
        ?>
        <div class="wrap occi-wrap">
            <h1><?php echo esc_html( $title ); ?></h1>
            <a href="<?php echo esc_url( $back ); ?>" class="page-title-action">&larr; Back to Household</a>
            <hr class="wp-header-end">

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'occipr_save_member', 'occipr_member_nonce' ); ?>
                <input type="hidden" name="action" value="occipr_save_member">
                <input type="hidden" name="member_id" value="<?php echo esc_attr( $member_id ); ?>">
                <input type="hidden" name="household_id" value="<?php echo esc_attr( $h_id ); ?>">

                <div class="occi-form-columns">
                    <div class="occi-form-col-main">

                        <div class="occi-section">
                            <h2>Personal Information</h2>
                            <table class="form-table">
                                <tr>
                                    <th><label for="first_name">Legal First Name <span class="required">*</span></label></th>
                                    <td><input type="text" id="first_name" name="first_name" value="<?php echo esc_attr( $m->first_name ?? '' ); ?>" class="regular-text" required></td>
                                </tr>
                                <tr>
                                    <th><label for="preferred_name">Preferred / Chosen Name</label></th>
                                    <td>
                                        <input type="text" id="preferred_name" name="preferred_name" value="<?php echo esc_attr( $m->preferred_name ?? '' ); ?>" class="regular-text">
                                        <p class="description">If different from legal name, this is displayed in the directory.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="middle_name">Middle Name</label></th>
                                    <td><input type="text" id="middle_name" name="middle_name" value="<?php echo esc_attr( $m->middle_name ?? '' ); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th><label for="last_name">Last Name <span class="required">*</span></label></th>
                                    <td><input type="text" id="last_name" name="last_name" value="<?php echo esc_attr( $m->last_name ?? '' ); ?>" class="regular-text" required></td>
                                </tr>
                                <tr>
                                    <th><label for="relationship">Role in Household</label></th>
                                    <td>
                                        <select id="relationship" name="relationship">
                                            <?php foreach ( self::relationship_options() as $val => $label ) : ?>
                                            <option value="<?php echo esc_attr( $val ); ?>"<?php selected( $m->relationship ?? 'other', $val ); ?>><?php echo esc_html( $label ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="birth_date">Date of Birth</label></th>
                                    <td><input type="date" id="birth_date" name="birth_date" value="<?php echo esc_attr( $m->birth_date ?? '' ); ?>"></td>
                                </tr>
                                <tr>
                                    <th><label for="member_since">Member Since</label></th>
                                    <td><input type="date" id="member_since" name="member_since" value="<?php echo esc_attr( $m->member_since ?? '' ); ?>"></td>
                                </tr>
                                <tr>
                                    <th><label for="m_status">Status</label></th>
                                    <td>
                                        <select id="m_status" name="status">
                                            <?php foreach ( self::status_options() as $val => $label ) : ?>
                                            <option value="<?php echo esc_attr( $val ); ?>"<?php selected( $m->status ?? 'active', $val ); ?>><?php echo esc_html( $label ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div class="occi-section">
                            <h2>Gender and Identity</h2>
                            <table class="form-table">
                                <tr>
                                    <th><label for="gender">Gender</label></th>
                                    <td>
                                        <select id="gender" name="gender" onchange="occiprToggleGenderOther(this)">
                                            <?php foreach ( self::gender_options() as $val => $label ) : ?>
                                            <option value="<?php echo esc_attr( $val ); ?>"<?php selected( $current_gender, $val ); ?>><?php echo esc_html( $label ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr id="gender_other_row" style="<?php echo $current_gender === 'self_describe' ? '' : 'display:none;'; ?>">
                                    <th><label for="gender_other">Self-Described Gender</label></th>
                                    <td><input type="text" id="gender_other" name="gender_other" value="<?php echo esc_attr( $m->gender_other ?? '' ); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th><label for="pronouns">Pronouns</label></th>
                                    <td>
                                        <input type="text" id="pronouns" name="pronouns" value="<?php echo esc_attr( $m->pronouns ?? '' ); ?>" class="regular-text" placeholder="e.g. they/them, she/her, he/him, ze/zir">
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div class="occi-section">
                            <h2>Contact Information</h2>
                            <table class="form-table">
                                <tr>
                                    <th><label for="m_phone">Phone</label></th>
                                    <td><input type="text" id="m_phone" name="phone" value="<?php echo esc_attr( $m->phone ?? '' ); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th><label for="m_email">Email</label></th>
                                    <td><input type="email" id="m_email" name="email" value="<?php echo esc_attr( $m->email ?? '' ); ?>" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th><label for="facebook">Facebook</label></th>
                                    <td><input type="text" id="facebook" name="facebook" value="<?php echo esc_attr( $m->facebook ?? '' ); ?>" class="regular-text" placeholder="Profile name or full URL"></td>
                                </tr>
                                <tr>
                                    <th><label for="instagram">Instagram</label></th>
                                    <td><input type="text" id="instagram" name="instagram" value="<?php echo esc_attr( $m->instagram ?? '' ); ?>" class="regular-text" placeholder="@handle"></td>
                                </tr>
                                <tr>
                                    <th><label for="website">Website / Other</label></th>
                                    <td><input type="url" id="website" name="website" value="<?php echo esc_attr( $m->website ?? '' ); ?>" class="regular-text" placeholder="https://"></td>
                                </tr>
                            </table>
                        </div>

                        <div class="occi-section">
                            <h2>Admin Notes</h2>
                            <table class="form-table">
                                <tr>
                                    <th><label for="m_notes">Notes</label></th>
                                    <td><textarea id="m_notes" name="notes" rows="3" class="regular-text"><?php echo esc_textarea( $m->notes ?? '' ); ?></textarea></td>
                                </tr>
                            </table>
                        </div>

                    </div><!-- .occi-form-col-main -->

                    <div class="occi-form-col-side">

                        <div class="occi-form-box">
                            <h2>Member Photo</h2>
                            <?php self::photo_uploader( (int) ( $m->photo_id ?? 0 ), 'photo_id', 'm_photo' ); ?>
                        </div>

                        <div class="occi-form-box" style="margin-top:16px;">
                            <h2>Directory Privacy</h2>
                            <p class="description">Uncheck to hide from the public printed directory.</p>
                            <p>
                                <label><input type="checkbox" name="show_phone" value="1" <?php checked( $m->show_phone ?? 1 ); ?>> Show Phone</label><br>
                                <label><input type="checkbox" name="show_email" value="1" <?php checked( $m->show_email ?? 1 ); ?>> Show Email</label><br>
                                <label><input type="checkbox" name="show_social" value="1" <?php checked( $m->show_social ?? 1 ); ?>> Show Social Media</label><br>
                                <label><input type="checkbox" name="show_birthday" value="1" <?php checked( $m->show_birthday ?? 1 ); ?>> Show Birthday</label><br>
                                <label><input type="checkbox" name="show_photo" value="1" <?php checked( $m->show_photo ?? 1 ); ?>> Show Photo</label>
                            </p>
                        </div>

                    </div><!-- .occi-form-col-side -->
                </div><!-- .occi-form-columns -->

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php echo $is_edit ? 'Update Member' : 'Add Member'; ?></button>
                    <a href="<?php echo esc_url( $back ); ?>" class="button">Cancel</a>
                </p>
            </form>
        </div>
        <script>
        function occiprToggleGenderOther(sel) {
            document.getElementById('gender_other_row').style.display =
                sel.value === 'self_describe' ? '' : 'none';
        }
        </script>
        <?php
    }

    // -------------------------------------------------------------------------
    // SAVE HOUSEHOLD
    // -------------------------------------------------------------------------

    public static function save_household() {
        if ( ! current_user_can( 'occipr_manage_records' ) ) { wp_die( 'Access denied.' ); }
        check_admin_referer( 'occipr_save_household', 'occipr_household_nonce' );
        global $wpdb;

        $id   = absint( $_POST['household_id'] ?? 0 );
        $data = [
            'family_name'     => sanitize_text_field( $_POST['family_name'] ?? '' ),
            'parish_id'       => absint( $_POST['parish_id'] ?? 0 ) ?: null,
            'address_street'  => sanitize_text_field( $_POST['address_street'] ?? '' ),
            'address_city'    => sanitize_text_field( $_POST['address_city'] ?? '' ),
            'address_state'   => sanitize_text_field( $_POST['address_state'] ?? '' ),
            'address_zip'     => sanitize_text_field( $_POST['address_zip'] ?? '' ),
            'address_country' => sanitize_text_field( $_POST['address_country'] ?? '' ),
            'phone'           => sanitize_text_field( $_POST['phone'] ?? '' ),
            'email'           => sanitize_email( $_POST['email'] ?? '' ),
            'photo_id'        => absint( $_POST['photo_id'] ?? 0 ) ?: null,
            'envelope_number' => sanitize_text_field( $_POST['envelope_number'] ?? '' ),
            'status'          => sanitize_key( $_POST['status'] ?? 'active' ),
            'notes'           => sanitize_textarea_field( $_POST['notes'] ?? '' ),
            'show_address'    => isset( $_POST['show_address'] ) ? 1 : 0,
            'show_phone'      => isset( $_POST['show_phone'] )   ? 1 : 0,
            'show_email'      => isset( $_POST['show_email'] )   ? 1 : 0,
            'show_photo'      => isset( $_POST['show_photo'] )   ? 1 : 0,
        ];
        $fmt = [ '%s','%d','%s','%s','%s','%s','%s','%s','%s','%d','%s','%s','%s','%d','%d','%d','%d' ];

        if ( $id ) {
            $result = $wpdb->update( "{$wpdb->prefix}occipr_households", $data, [ 'id' => $id ], $fmt, [ '%d' ] );
            if ( false === $result ) {
                wp_die( 'Database error saving household: ' . esc_html( $wpdb->last_error ) );
            }
            wp_redirect( admin_url( 'admin.php?page=occipr-directory&action=view_household&id=' . $id . '&updated=1' ) );
        } else {
            $result = $wpdb->insert( "{$wpdb->prefix}occipr_households", $data, $fmt );
            if ( false === $result ) {
                wp_die( 'Database error saving household: ' . esc_html( $wpdb->last_error ) );
            }
            wp_redirect( admin_url( 'admin.php?page=occipr-directory&action=view_household&id=' . $wpdb->insert_id . '&added=1' ) );
        }
        exit;
    }

    // -------------------------------------------------------------------------
    // SAVE MEMBER
    // -------------------------------------------------------------------------

    public static function save_member() {
        if ( ! current_user_can( 'occipr_manage_records' ) ) { wp_die( 'Access denied.' ); }
        check_admin_referer( 'occipr_save_member', 'occipr_member_nonce' );
        global $wpdb;

        $id           = absint( $_POST['member_id'] ?? 0 );
        $household_id = absint( $_POST['household_id'] ?? 0 );
        $gender       = sanitize_text_field( $_POST['gender'] ?? '' );

        $data = [
            'household_id'   => $household_id,
            'first_name'     => sanitize_text_field( $_POST['first_name'] ?? '' ),
            'last_name'      => sanitize_text_field( $_POST['last_name'] ?? '' ),
            'preferred_name' => sanitize_text_field( $_POST['preferred_name'] ?? '' ),
            'middle_name'    => sanitize_text_field( $_POST['middle_name'] ?? '' ),
            'relationship'   => sanitize_key( $_POST['relationship'] ?? 'other' ),
            'birth_date'     => sanitize_text_field( $_POST['birth_date'] ?? '' ) ?: null,
            'member_since'   => sanitize_text_field( $_POST['member_since'] ?? '' ) ?: null,
            'status'         => sanitize_key( $_POST['status'] ?? 'active' ),
            'gender'         => $gender,
            'gender_other'   => $gender === 'self_describe' ? sanitize_text_field( $_POST['gender_other'] ?? '' ) : '',
            'pronouns'       => sanitize_text_field( $_POST['pronouns'] ?? '' ),
            'phone'          => sanitize_text_field( $_POST['phone'] ?? '' ),
            'email'          => sanitize_email( $_POST['email'] ?? '' ),
            'facebook'       => sanitize_text_field( $_POST['facebook'] ?? '' ),
            'instagram'      => sanitize_text_field( $_POST['instagram'] ?? '' ),
            'website'        => esc_url_raw( $_POST['website'] ?? '' ),
            'photo_id'       => absint( $_POST['photo_id'] ?? 0 ) ?: null,
            'notes'          => sanitize_textarea_field( $_POST['notes'] ?? '' ),
            'show_phone'     => isset( $_POST['show_phone'] )    ? 1 : 0,
            'show_email'     => isset( $_POST['show_email'] )    ? 1 : 0,
            'show_social'    => isset( $_POST['show_social'] )   ? 1 : 0,
            'show_birthday'  => isset( $_POST['show_birthday'] ) ? 1 : 0,
            'show_photo'     => isset( $_POST['show_photo'] )    ? 1 : 0,
        ];
        $fmt = [ '%d','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%d','%s','%d','%d','%d','%d','%d' ];

        if ( $id ) {
            $result = $wpdb->update( "{$wpdb->prefix}occipr_members", $data, [ 'id' => $id ], $fmt, [ '%d' ] );
            if ( false === $result ) {
                wp_die( 'Database error saving member: ' . esc_html( $wpdb->last_error ) );
            }
        } else {
            $result = $wpdb->insert( "{$wpdb->prefix}occipr_members", $data, $fmt );
            if ( false === $result ) {
                wp_die( 'Database error saving member: ' . esc_html( $wpdb->last_error ) );
            }
        }

        wp_redirect( admin_url( 'admin.php?page=occipr-directory&action=view_household&id=' . $household_id . '&saved=1' ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // DELETE HANDLERS
    // -------------------------------------------------------------------------

    public static function handle_delete_household() {
        if ( ! current_user_can( 'occipr_manage_records' ) ) { wp_die( 'Access denied.' ); }
        $id = absint( $_GET['id'] ?? 0 );
        check_admin_referer( 'occipr_delete_household_' . $id );
        global $wpdb;
        $wpdb->delete( "{$wpdb->prefix}occipr_members",    [ 'household_id' => $id ], [ '%d' ] );
        $wpdb->delete( "{$wpdb->prefix}occipr_households", [ 'id' => $id ],           [ '%d' ] );
        wp_redirect( admin_url( 'admin.php?page=occipr-directory&deleted=1' ) );
        exit;
    }

    public static function handle_delete_member() {
        if ( ! current_user_can( 'occipr_manage_records' ) ) { wp_die( 'Access denied.' ); }
        $id           = absint( $_GET['id'] ?? 0 );
        $household_id = absint( $_GET['household_id'] ?? 0 );
        check_admin_referer( 'occipr_delete_member_' . $id );
        global $wpdb;
        $wpdb->delete( "{$wpdb->prefix}occipr_members", [ 'id' => $id ], [ '%d' ] );
        wp_redirect( admin_url( 'admin.php?page=occipr-directory&action=view_household&id=' . $household_id . '&deleted=1' ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // PRINT DIRECTORY
    // -------------------------------------------------------------------------

    private static function print_directory( bool $admin_version, int $parish_id = 0 ) {
        if ( $admin_version && ! current_user_can( 'occipr_manage_records' ) ) {
            wp_die( 'Access denied.' );
        }
        global $wpdb;

        // Resolve parish name for the header.
        $parish = $parish_id
            ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}occipr_parishes WHERE id = %d", $parish_id ) )
            : null;
        $parish_name = $parish ? $parish->name : 'Parish Directory';

        // Filter households by parish if one is selected.
        if ( $parish_id ) {
            $households = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}occipr_households WHERE status = 'active' AND parish_id = %d ORDER BY family_name ASC",
                $parish_id
            ) );
        } else {
            $households = $wpdb->get_results(
                "SELECT * FROM {$wpdb->prefix}occipr_households WHERE status = 'active' ORDER BY family_name ASC"
            );
        }

        $rels    = self::relationship_options();
        $genders = self::gender_options();
        $title   = $admin_version
            ? $parish_name . ' -- Directory (Administrative -- Confidential)'
            : $parish_name . ' -- Parish Directory';

        // Render standalone printable HTML, then exit.
        ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo esc_html( $title ); ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Georgia, "Times New Roman", serif; padding: 24px; color: #1a1a1a; font-size: 11pt; line-height: 1.4; }
        h1 { text-align: center; font-size: 20pt; margin-bottom: 4px; color: #6B1A2A; }
        .subtitle { text-align: center; font-size: 9pt; color: #555; margin-bottom: 8px; }
        .parish-contact { font-size: 10pt; color: #333; margin-bottom: 4px; }
        .admin-badge { background: #6B1A2A; color: #fff; padding: 1px 8px; border-radius: 3px; font-size: 8pt; letter-spacing: 0.05em; }
        .household { display: flex; gap: 14px; margin-bottom: 18px; padding-bottom: 16px; border-bottom: 1px solid #ccc; page-break-inside: avoid; }
        .hh-photo { flex-shrink: 0; width: 80px; }
        .hh-photo img { width: 80px; height: 80px; object-fit: cover; border-radius: 4px; display: block; }
        .hh-body { flex: 1; }
        .family-name { font-size: 13pt; font-weight: bold; color: #6B1A2A; margin-bottom: 3px; }
        .hh-contact { font-size: 9pt; color: #333; margin-bottom: 8px; }
        .hh-contact span { margin-right: 12px; }
        .env-badge { color: #6B1A2A; font-weight: bold; }
        .members { display: flex; flex-wrap: wrap; gap: 8px; }
        .member { display: flex; gap: 7px; width: calc(50% - 4px); align-items: flex-start; }
        .m-photo img { width: 44px; height: 44px; object-fit: cover; border-radius: 3px; display: block; }
        .m-name { font-weight: bold; font-size: 10pt; }
        .m-role { font-size: 8pt; color: #555; }
        .m-detail { font-size: 8.5pt; color: #333; }
        .footer-note { text-align: center; font-size: 8pt; color: #999; margin-top: 32px; font-style: italic; }
        .no-print { text-align: right; margin-bottom: 16px; }
        @media print {
            .no-print { display: none; }
            body { padding: 12px; }
            .household { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <p class="no-print">
        <button onclick="window.print()" style="padding:6px 16px;font-size:10pt;cursor:pointer;">Print / Save as PDF</button>
    </p>
    <h1><?php echo esc_html( $parish_name ); ?></h1>
    <?php if ( $parish ) : ?>
    <p class="subtitle parish-contact">
        <?php
        $contact_parts = array_filter( [
            trim( ( $parish->address_street ? $parish->address_street . ', ' : '' )
                . $parish->city . ( $parish->state ? ', ' . $parish->state : '' )
                . ( $parish->address_zip ? ' ' . $parish->address_zip : '' ) ),
            $parish->phone,
            $parish->email,
            $parish->website,
        ] );
        echo esc_html( implode( ' | ', $contact_parts ) );
        ?>
    </p>
    <?php endif; ?>
    <p class="subtitle">
        <?php echo $admin_version ? '<span class="admin-badge">ADMINISTRATIVE USE ONLY</span> &nbsp;&bull;&nbsp; ' : ''; ?>
        Parish Directory &nbsp;&bull;&nbsp; Generated <?php echo esc_html( date( 'F j, Y' ) ); ?>
    </p>

    <?php foreach ( $households as $h ) :
        $members   = self::get_members( (int) $h->id );
        $show_hh_photo = $admin_version || $h->show_photo;
        $hh_photo  = ( $show_hh_photo && $h->photo_id )
            ? wp_get_attachment_image_url( (int) $h->photo_id, 'thumbnail' ) : '';
    ?>
    <div class="household">
        <?php if ( $hh_photo ) : ?>
        <div class="hh-photo"><img src="<?php echo esc_url( $hh_photo ); ?>" alt=""></div>
        <?php endif; ?>
        <div class="hh-body">
            <div class="family-name"><?php echo esc_html( $h->family_name ); ?></div>
            <div class="hh-contact">
                <?php if ( ( $admin_version || $h->show_address ) && $h->address_street ) : ?>
                <span><?php echo esc_html( trim( $h->address_street . ', ' . $h->address_city . ( $h->address_state ? ', ' . $h->address_state : '' ) . ' ' . $h->address_zip, ', ' ) ); ?></span>
                <?php endif; ?>
                <?php if ( ( $admin_version || $h->show_phone ) && $h->phone ) : ?>
                <span><?php echo esc_html( $h->phone ); ?></span>
                <?php endif; ?>
                <?php if ( ( $admin_version || $h->show_email ) && $h->email ) : ?>
                <span><?php echo esc_html( $h->email ); ?></span>
                <?php endif; ?>
                <?php if ( $admin_version && $h->envelope_number ) : ?>
                <span class="env-badge">Env # <?php echo esc_html( $h->envelope_number ); ?></span>
                <?php endif; ?>
            </div>
            <div class="members">
                <?php foreach ( $members as $m ) :
                    $show_m_photo = $admin_version || $m->show_photo;
                    $m_photo = ( $show_m_photo && $m->photo_id )
                        ? wp_get_attachment_image_url( (int) $m->photo_id, 'thumbnail' ) : '';
                    $display = $m->preferred_name ?: $m->first_name;
                    $g_label = $m->gender === 'self_describe'
                        ? $m->gender_other
                        : ( $genders[ $m->gender ] ?? '' );
                ?>
                <div class="member">
                    <?php if ( $m_photo ) : ?>
                    <div class="m-photo"><img src="<?php echo esc_url( $m_photo ); ?>" alt=""></div>
                    <?php endif; ?>
                    <div>
                        <div class="m-name"><?php echo esc_html( $display . ' ' . $m->last_name ); ?></div>
                        <div class="m-role">
                            <?php echo esc_html( $rels[ $m->relationship ] ?? ucfirst( $m->relationship ) ); ?>
                            <?php if ( $m->pronouns ) : ?>&bull; <?php echo esc_html( $m->pronouns ); ?><?php endif; ?>
                        </div>
                        <div class="m-detail">
                            <?php if ( $admin_version && $g_label ) : ?><span><?php echo esc_html( $g_label ); ?></span><br><?php endif; ?>
                            <?php if ( ( $admin_version || $m->show_birthday ) && $m->birth_date ) : ?>
                            <span>b. <?php echo esc_html( date( 'M j, Y', strtotime( $m->birth_date ) ) ); ?></span><br>
                            <?php endif; ?>
                            <?php if ( ( $admin_version || $m->show_phone ) && $m->phone ) : ?>
                            <span><?php echo esc_html( $m->phone ); ?></span><br>
                            <?php endif; ?>
                            <?php if ( ( $admin_version || $m->show_email ) && $m->email ) : ?>
                            <span><?php echo esc_html( $m->email ); ?></span><br>
                            <?php endif; ?>
                            <?php if ( $admin_version || $m->show_social ) : ?>
                                <?php if ( $m->facebook )  : ?>Facebook: <?php echo esc_html( $m->facebook );  ?><br><?php endif; ?>
                                <?php if ( $m->instagram ) : ?>Instagram: <?php echo esc_html( $m->instagram ); ?><br><?php endif; ?>
                                <?php if ( $m->website )   : ?>Web: <?php echo esc_html( $m->website );   ?><br><?php endif; ?>
                            <?php endif; ?>
                            <?php if ( $admin_version && $m->notes ) : ?><em style="color:#6B1A2A;"><?php echo esc_html( $m->notes ); ?></em><?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <p class="footer-note">Pax et Bonum &mdash; <?php echo esc_html( $parish_name ); ?></p>
</body>
</html>
        <?php
        exit;
    }

    // -------------------------------------------------------------------------
    // HELPERS
    // -------------------------------------------------------------------------

    private static function get_household( int $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}occipr_households WHERE id = %d", $id
        ) );
    }

    private static function get_members( int $household_id ): array {
        global $wpdb;
        $order = "FIELD(relationship,'head','spouse','partner','child','grandparent','other_family','other')";
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}occipr_members WHERE household_id = %d ORDER BY $order, first_name ASC",
            $household_id
        ) ) ?: [];
    }

    private static function photo_uploader( int $attachment_id, string $field_name, string $uid ) {
        $preview = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'medium' ) : '';
        ?>
        <div class="occi-photo-uploader" data-uid="<?php echo esc_attr( $uid ); ?>">
            <input type="hidden" name="<?php echo esc_attr( $field_name ); ?>"
                   id="<?php echo esc_attr( $uid ); ?>_id"
                   value="<?php echo esc_attr( $attachment_id ); ?>">
            <div class="occi-photo-preview" id="<?php echo esc_attr( $uid ); ?>_preview"
                 style="<?php echo $preview ? '' : 'display:none;'; ?>margin-bottom:10px;">
                <img src="<?php echo esc_url( $preview ); ?>"
                     id="<?php echo esc_attr( $uid ); ?>_img"
                     style="max-width:160px;max-height:160px;display:block;border-radius:4px;border:1px solid #ddd;" alt="">
            </div>
            <button type="button" class="button occi-select-photo" data-uid="<?php echo esc_attr( $uid ); ?>">
                <?php echo $attachment_id ? 'Change Photo' : 'Select Photo'; ?>
            </button>
            <button type="button" class="button occi-remove-photo" data-uid="<?php echo esc_attr( $uid ); ?>"
                    style="<?php echo $attachment_id ? '' : 'display:none;'; ?>margin-left:6px;">
                Remove Photo
            </button>
        </div>
        <?php
    }

    // Public accessor used by the dashboard.
    public static function household_count(): int {
        global $wpdb;
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}occipr_households" );
    }
}
