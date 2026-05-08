<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OCCIPR_Registration {

    // -------------------------------------------------------------------------
    // SETTINGS
    // -------------------------------------------------------------------------

    private static function get_settings(): array {
        return wp_parse_args( get_option( 'occi_pr_registration', [] ), [
            'notify_email'     => '',
            'member_enabled'   => 0,
            'psr_enabled'      => 0,
            'ocia_enabled'     => 0,
            'member_parish_id' => 0,
            'psr_parish_id'    => 0,
            'ocia_parish_id'   => 0,
            'member_success'   => 'Thank you for registering! Your information has been submitted and will be reviewed by our parish staff. We will be in touch soon.',
            'psr_success'      => 'Thank you for registering for PSR! Your submission has been received and will be reviewed shortly. We will contact you with next steps.',
            'ocia_success'     => 'Thank you for your interest! Your inquiry has been received. Someone from our parish will be in touch with you soon. Pax et Bonum.',
        ] );
    }

    // -------------------------------------------------------------------------
    // INIT
    // -------------------------------------------------------------------------

    public static function init() {
        add_action( 'init',                                        [ __CLASS__, 'handle_public_submission' ] );
        add_shortcode( 'occipr_member_registration',               [ __CLASS__, 'shortcode_member' ] );
        add_shortcode( 'occipr_psr_registration',                  [ __CLASS__, 'shortcode_psr' ] );
        add_shortcode( 'occipr_ocia_registration',                 [ __CLASS__, 'shortcode_ocia' ] );
        add_action( 'admin_post_occipr_approve_submission',        [ __CLASS__, 'handle_approve' ] );
        add_action( 'admin_post_occipr_reject_submission',         [ __CLASS__, 'handle_reject' ] );
        add_action( 'admin_post_occipr_save_reg_settings',         [ __CLASS__, 'save_settings' ] );
        add_action( 'wp_enqueue_scripts',                          [ __CLASS__, 'enqueue_public_assets' ] );
    }

    public static function enqueue_public_assets() {
        wp_enqueue_style(
            'occipr-public',
            OCCI_PR_PLUGIN_URL . 'public/css/occipr-public.css',
            [],
            OCCI_PR_VERSION
        );
    }

    // -------------------------------------------------------------------------
    // PUBLIC FORM SUBMISSION HANDLER (PRG pattern)
    // -------------------------------------------------------------------------

    public static function handle_public_submission() {
        if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) return;
        if ( empty( $_POST['occipr_reg_type'] ) ) return;

        // Determine return URL before any possible failure redirect
        $return_url = wp_validate_redirect(
            sanitize_url( $_POST['occipr_return_url'] ?? '' ),
            home_url()
        );

        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['_occipr_nonce'] ?? '', 'occipr_public_registration' ) ) {
            wp_safe_redirect( add_query_arg( 'occipr_error', 'nonce', $return_url ) );
            exit;
        }

        $type = sanitize_key( $_POST['occipr_reg_type'] );
        $s    = self::get_settings();

        if ( ! in_array( $type, [ 'member', 'psr', 'ocia' ], true ) || empty( $s[ $type . '_enabled' ] ) ) {
            wp_safe_redirect( add_query_arg( 'occipr_error', 'disabled', $return_url ) );
            exit;
        }

        // Parish assignment: settings default, then submitter-chosen
        $parish_id = absint( $s[ $type . '_parish_id' ] ) ?: ( absint( $_POST['occipr_parish_id'] ?? 0 ) ?: null );

        // Collect data per form type
        $data = [];
        switch ( $type ) {
            case 'member':
                $members = [];
                foreach ( (array) ( $_POST['members'] ?? [] ) as $m ) {
                    $fn = sanitize_text_field( $m['first_name'] ?? '' );
                    $ln = sanitize_text_field( $m['last_name'] ?? '' );
                    if ( $fn || $ln ) {
                        $members[] = [
                            'first_name'   => $fn,
                            'last_name'    => $ln,
                            'relationship' => sanitize_text_field( $m['relationship'] ?? 'other' ),
                            'birth_date'   => sanitize_text_field( $m['birth_date'] ?? '' ),
                        ];
                    }
                }
                $data = [
                    'family_name'    => sanitize_text_field( $_POST['family_name'] ?? '' ),
                    'address_street' => sanitize_text_field( $_POST['address_street'] ?? '' ),
                    'address_city'   => sanitize_text_field( $_POST['address_city'] ?? '' ),
                    'address_state'  => sanitize_text_field( $_POST['address_state'] ?? '' ),
                    'address_zip'    => sanitize_text_field( $_POST['address_zip'] ?? '' ),
                    'phone'          => sanitize_text_field( $_POST['family_phone'] ?? '' ),
                    'email'          => sanitize_email( $_POST['family_email'] ?? '' ),
                    'members'        => $members,
                    'notes'          => sanitize_textarea_field( $_POST['notes'] ?? '' ),
                ];
                $sub_name  = $data['family_name'];
                $sub_email = $data['email'];
                break;

            case 'psr':
                $data = [
                    'first_name'      => sanitize_text_field( $_POST['first_name'] ?? '' ),
                    'last_name'       => sanitize_text_field( $_POST['last_name'] ?? '' ),
                    'preferred_name'  => sanitize_text_field( $_POST['preferred_name'] ?? '' ),
                    'birth_date'      => sanitize_text_field( $_POST['birth_date'] ?? '' ),
                    'grade_level'     => sanitize_text_field( $_POST['grade_level'] ?? '' ),
                    'academic_year'   => sanitize_text_field( $_POST['academic_year'] ?? '' ),
                    'guardian1_name'  => sanitize_text_field( $_POST['guardian1_name'] ?? '' ),
                    'guardian1_phone' => sanitize_text_field( $_POST['guardian1_phone'] ?? '' ),
                    'guardian1_email' => sanitize_email( $_POST['guardian1_email'] ?? '' ),
                    'guardian2_name'  => sanitize_text_field( $_POST['guardian2_name'] ?? '' ),
                    'guardian2_phone' => sanitize_text_field( $_POST['guardian2_phone'] ?? '' ),
                    'guardian2_email' => sanitize_email( $_POST['guardian2_email'] ?? '' ),
                    'home_address'    => sanitize_textarea_field( $_POST['home_address'] ?? '' ),
                    'notes'           => sanitize_textarea_field( $_POST['notes'] ?? '' ),
                ];
                $sub_name  = trim( $data['first_name'] . ' ' . $data['last_name'] );
                $sub_email = $data['guardian1_email'];
                break;

            case 'ocia':
                $data = [
                    'first_name'     => sanitize_text_field( $_POST['first_name'] ?? '' ),
                    'last_name'      => sanitize_text_field( $_POST['last_name'] ?? '' ),
                    'email'          => sanitize_email( $_POST['email'] ?? '' ),
                    'phone'          => sanitize_text_field( $_POST['phone'] ?? '' ),
                    'address_street' => sanitize_text_field( $_POST['address_street'] ?? '' ),
                    'address_city'   => sanitize_text_field( $_POST['address_city'] ?? '' ),
                    'address_state'  => sanitize_text_field( $_POST['address_state'] ?? '' ),
                    'address_zip'    => sanitize_text_field( $_POST['address_zip'] ?? '' ),
                    'previous_faith' => sanitize_text_field( $_POST['previous_faith'] ?? '' ),
                    'message'        => sanitize_textarea_field( $_POST['message'] ?? '' ),
                ];
                $sub_name  = trim( $data['first_name'] . ' ' . $data['last_name'] );
                $sub_email = $data['email'];
                break;

            default:
                wp_safe_redirect( add_query_arg( 'occipr_error', 'type', $return_url ) );
                exit;
        }

        global $wpdb;
        $ok = $wpdb->insert( "{$wpdb->prefix}occipr_submissions", [
            'form_type'    => $type,
            'status'       => 'pending',
            'parish_id'    => $parish_id,
            'data'         => wp_json_encode( $data ),
            'submitter_ip' => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
            'submitter_ua' => sanitize_text_field( substr( $_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500 ) ),
        ] );

        if ( false === $ok ) {
            wp_safe_redirect( add_query_arg( 'occipr_error', 'db', $return_url ) );
            exit;
        }

        self::send_notification( $type, $sub_name ?? '', $sub_email ?? '', (int) $wpdb->insert_id );

        wp_safe_redirect( add_query_arg( 'occipr_submitted', $type, $return_url ) );
        exit;
    }

    private static function send_notification( string $type, string $name, string $email, int $sub_id ): void {
        $s      = self::get_settings();
        $to     = $s['notify_email'] ?: get_option( 'admin_email' );
        $labels = [ 'member' => 'Parish Member Registration', 'psr' => 'PSR / Religious Education', 'ocia' => 'OCIA Inquiry' ];
        $label  = $labels[ $type ] ?? $type;
        $link   = admin_url( 'admin.php?page=occipr-registration-queue&action=view&id=' . $sub_id );
        $subj   = '[Parish Register] New ' . $label . ': ' . $name;
        $body   = "A new {$label} submission has been received.\n\n";
        $body  .= "Name:  {$name}\n";
        if ( $email ) $body .= "Email: {$email}\n";
        $body  .= "\nReview and approve or reject it here:\n{$link}\n";
        wp_mail( $to, $subj, $body );
    }

    // -------------------------------------------------------------------------
    // SHORTCODES
    // -------------------------------------------------------------------------

    public static function shortcode_member( $atts ): string {
        $s = self::get_settings();
        if ( ! $s['member_enabled'] ) return '';
        wp_enqueue_style( 'occipr-public', OCCI_PR_PLUGIN_URL . 'public/css/occipr-public.css', [], OCCI_PR_VERSION );
        if ( ( $_GET['occipr_submitted'] ?? '' ) === 'member' ) {
            return self::render_success( $s['member_success'] );
        }
        ob_start();
        if ( isset( $_GET['occipr_error'] ) ) self::render_error();
        self::render_member_form( $s );
        return ob_get_clean();
    }

    public static function shortcode_psr( $atts ): string {
        $s = self::get_settings();
        if ( ! $s['psr_enabled'] ) return '';
        wp_enqueue_style( 'occipr-public', OCCI_PR_PLUGIN_URL . 'public/css/occipr-public.css', [], OCCI_PR_VERSION );
        if ( ( $_GET['occipr_submitted'] ?? '' ) === 'psr' ) {
            return self::render_success( $s['psr_success'] );
        }
        ob_start();
        if ( isset( $_GET['occipr_error'] ) ) self::render_error();
        self::render_psr_form( $s );
        return ob_get_clean();
    }

    public static function shortcode_ocia( $atts ): string {
        $s = self::get_settings();
        if ( ! $s['ocia_enabled'] ) return '';
        wp_enqueue_style( 'occipr-public', OCCI_PR_PLUGIN_URL . 'public/css/occipr-public.css', [], OCCI_PR_VERSION );
        if ( ( $_GET['occipr_submitted'] ?? '' ) === 'ocia' ) {
            return self::render_success( $s['ocia_success'] );
        }
        ob_start();
        if ( isset( $_GET['occipr_error'] ) ) self::render_error();
        self::render_ocia_form( $s );
        return ob_get_clean();
    }

    private static function render_success( string $msg ): string {
        return '<div class="occipr-message occipr-success">'
             . '<span class="occipr-check">&#10003;</span> '
             . nl2br( esc_html( $msg ) )
             . '</div>';
    }

    private static function render_error(): void {
        echo '<div class="occipr-message occipr-error">There was a problem submitting your form. Please review your entries and try again. If this continues, please contact the parish office directly.</div>';
    }

    // Shared hidden fields: type, nonce, return URL, optional default parish
    private static function form_top( string $type, int $parish_id ): void {
        $nonce  = wp_create_nonce( 'occipr_public_registration' );
        $return = esc_url( get_permalink() ?: home_url() );
        echo '<input type="hidden" name="occipr_reg_type"   value="' . esc_attr( $type )  . '">';
        echo '<input type="hidden" name="_occipr_nonce"     value="' . esc_attr( $nonce ) . '">';
        echo '<input type="hidden" name="occipr_return_url" value="' . $return             . '">';
        if ( $parish_id ) {
            echo '<input type="hidden" name="occipr_parish_id" value="' . esc_attr( $parish_id ) . '">';
        }
    }

    // Shows a parish dropdown only when no default parish is set and multiple parishes exist
    private static function parish_field( int $default_id ): void {
        if ( $default_id ) return;
        $parishes = OCCIPR_Database::get_parishes();
        if ( count( $parishes ) <= 1 ) return;
        echo '<div class="occipr-field">';
        echo '<label class="occipr-label">Parish <span class="occipr-req">*</span></label>';
        echo '<select name="occipr_parish_id" class="occipr-select" required>';
        echo '<option value="">-- Select Parish --</option>';
        foreach ( $parishes as $p ) {
            echo '<option value="' . esc_attr( $p->id ) . '">'
               . esc_html( $p->name . ' -- ' . $p->city . ', ' . $p->state )
               . '</option>';
        }
        echo '</select>';
        echo '</div>';
    }

    // ---- MEMBER REGISTRATION FORM ----

    private static function render_member_form( array $s ): void {
        $parish_id = (int) $s['member_parish_id'];
        ?>
        <div class="occipr-registration-form">
            <form method="post" class="occipr-form" novalidate>
                <?php self::form_top( 'member', $parish_id ); ?>
                <?php self::parish_field( $parish_id ); ?>

                <div class="occipr-section">
                    <h3 class="occipr-section-title">Family Information</h3>
                    <div class="occipr-field">
                        <label class="occipr-label">Family Name <span class="occipr-req">*</span></label>
                        <input type="text" name="family_name" class="occipr-input" required placeholder="e.g. The Smith Family">
                    </div>
                    <div class="occipr-row-2">
                        <div class="occipr-field">
                            <label class="occipr-label">Family Phone</label>
                            <input type="tel" name="family_phone" class="occipr-input" placeholder="555-555-5555">
                        </div>
                        <div class="occipr-field">
                            <label class="occipr-label">Family Email</label>
                            <input type="email" name="family_email" class="occipr-input" placeholder="family@example.com">
                        </div>
                    </div>
                    <div class="occipr-field">
                        <label class="occipr-label">Street Address</label>
                        <input type="text" name="address_street" class="occipr-input" placeholder="123 Main Street">
                    </div>
                    <div class="occipr-row-3">
                        <div class="occipr-field">
                            <label class="occipr-label">City</label>
                            <input type="text" name="address_city" class="occipr-input">
                        </div>
                        <div class="occipr-field">
                            <label class="occipr-label">State</label>
                            <input type="text" name="address_state" class="occipr-input">
                        </div>
                        <div class="occipr-field">
                            <label class="occipr-label">ZIP</label>
                            <input type="text" name="address_zip" class="occipr-input">
                        </div>
                    </div>
                </div>

                <div class="occipr-section">
                    <h3 class="occipr-section-title">Family Members</h3>
                    <p class="occipr-help">Please list each member of your household. Click the button below to add additional members.</p>
                    <div id="occipr-members-wrap">
                        <?php echo self::member_row_html( 0, true ); ?>
                    </div>
                    <button type="button" id="occipr-add-member" class="occipr-btn-add">+ Add Another Family Member</button>
                </div>

                <div class="occipr-section">
                    <h3 class="occipr-section-title">Additional Information</h3>
                    <div class="occipr-field">
                        <label class="occipr-label">Questions or Notes</label>
                        <textarea name="notes" class="occipr-textarea" rows="3" placeholder="Any questions or additional information for the parish office?"></textarea>
                    </div>
                </div>

                <div class="occipr-submit-wrap">
                    <button type="submit" class="occipr-btn-primary">Submit Registration</button>
                    <p class="occipr-submit-note">Your information will be reviewed by parish staff before being added to the directory.</p>
                </div>
            </form>
        </div>
        <template id="occipr-member-tpl"><?php echo self::member_row_html( 'IDX', false ); ?></template>
        <script>
        (function(){
            var idx = 1;
            document.getElementById('occipr-add-member').addEventListener('click', function(){
                var tpl = document.getElementById('occipr-member-tpl').innerHTML.replace(/IDX/g, idx++);
                var div = document.createElement('div');
                div.innerHTML = tpl;
                document.getElementById('occipr-members-wrap').appendChild(div.firstElementChild);
            });
            document.getElementById('occipr-members-wrap').addEventListener('click', function(e){
                if (e.target.classList.contains('occipr-remove-row')) {
                    e.target.closest('.occipr-member-row').remove();
                }
            });
        })();
        </script>
        <?php
    }

    private static function member_row_html( $idx, bool $first ): string {
        $rels = [ 'head' => 'Head of Household', 'spouse' => 'Spouse / Partner', 'child' => 'Child', 'other' => 'Other' ];
        ob_start();
        ?>
        <div class="occipr-member-row">
            <?php if ( ! $first ) : ?>
            <button type="button" class="occipr-remove-row">&times; Remove</button>
            <?php endif; ?>
            <div class="occipr-row-2">
                <div class="occipr-field">
                    <label class="occipr-label">First Name <span class="occipr-req">*</span></label>
                    <input type="text" name="members[<?php echo $idx; ?>][first_name]" class="occipr-input" required>
                </div>
                <div class="occipr-field">
                    <label class="occipr-label">Last Name</label>
                    <input type="text" name="members[<?php echo $idx; ?>][last_name]" class="occipr-input">
                </div>
            </div>
            <div class="occipr-row-2">
                <div class="occipr-field">
                    <label class="occipr-label">Relationship</label>
                    <select name="members[<?php echo $idx; ?>][relationship]" class="occipr-select">
                        <?php foreach ( $rels as $v => $l ) echo '<option value="' . esc_attr( $v ) . '">' . esc_html( $l ) . '</option>'; ?>
                    </select>
                </div>
                <div class="occipr-field">
                    <label class="occipr-label">Date of Birth</label>
                    <input type="date" name="members[<?php echo $idx; ?>][birth_date]" class="occipr-input">
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // ---- PSR REGISTRATION FORM ----

    private static function render_psr_form( array $s ): void {
        $parish_id = (int) $s['psr_parish_id'];
        $grades    = OCCIPR_PSR::grade_options();
        ?>
        <div class="occipr-registration-form">
            <form method="post" class="occipr-form" novalidate>
                <?php self::form_top( 'psr', $parish_id ); ?>
                <?php self::parish_field( $parish_id ); ?>

                <div class="occipr-section">
                    <h3 class="occipr-section-title">Student Information</h3>
                    <div class="occipr-row-2">
                        <div class="occipr-field">
                            <label class="occipr-label">First Name <span class="occipr-req">*</span></label>
                            <input type="text" name="first_name" class="occipr-input" required>
                        </div>
                        <div class="occipr-field">
                            <label class="occipr-label">Last Name <span class="occipr-req">*</span></label>
                            <input type="text" name="last_name" class="occipr-input" required>
                        </div>
                    </div>
                    <div class="occipr-field">
                        <label class="occipr-label">Preferred / Chosen Name</label>
                        <input type="text" name="preferred_name" class="occipr-input" placeholder="If different from legal name">
                    </div>
                    <div class="occipr-row-3">
                        <div class="occipr-field">
                            <label class="occipr-label">Date of Birth</label>
                            <input type="date" name="birth_date" class="occipr-input">
                        </div>
                        <div class="occipr-field">
                            <label class="occipr-label">Grade Level</label>
                            <select name="grade_level" class="occipr-select">
                                <option value="">-- Select --</option>
                                <?php foreach ( $grades as $v => $l ) echo '<option value="' . esc_attr( $v ) . '">' . esc_html( $l ) . '</option>'; ?>
                            </select>
                        </div>
                        <div class="occipr-field">
                            <label class="occipr-label">Academic Year</label>
                            <input type="text" name="academic_year" class="occipr-input" placeholder="e.g. 2025-2026">
                        </div>
                    </div>
                </div>

                <div class="occipr-section">
                    <h3 class="occipr-section-title">Parent / Guardian Information</h3>
                    <div class="occipr-field">
                        <label class="occipr-label">Guardian 1 Name <span class="occipr-req">*</span></label>
                        <input type="text" name="guardian1_name" class="occipr-input" required>
                    </div>
                    <div class="occipr-row-2">
                        <div class="occipr-field">
                            <label class="occipr-label">Guardian 1 Phone</label>
                            <input type="tel" name="guardian1_phone" class="occipr-input" placeholder="555-555-5555">
                        </div>
                        <div class="occipr-field">
                            <label class="occipr-label">Guardian 1 Email</label>
                            <input type="email" name="guardian1_email" class="occipr-input">
                        </div>
                    </div>
                    <div class="occipr-field">
                        <label class="occipr-label">Guardian 2 Name</label>
                        <input type="text" name="guardian2_name" class="occipr-input">
                    </div>
                    <div class="occipr-row-2">
                        <div class="occipr-field">
                            <label class="occipr-label">Guardian 2 Phone</label>
                            <input type="tel" name="guardian2_phone" class="occipr-input">
                        </div>
                        <div class="occipr-field">
                            <label class="occipr-label">Guardian 2 Email</label>
                            <input type="email" name="guardian2_email" class="occipr-input">
                        </div>
                    </div>
                    <div class="occipr-field">
                        <label class="occipr-label">Home Address</label>
                        <textarea name="home_address" class="occipr-textarea" rows="2" placeholder="Street, City, State, ZIP"></textarea>
                    </div>
                </div>

                <div class="occipr-section">
                    <h3 class="occipr-section-title">Additional Notes</h3>
                    <div class="occipr-field">
                        <label class="occipr-label">Questions or Comments</label>
                        <textarea name="notes" class="occipr-textarea" rows="3"></textarea>
                    </div>
                </div>

                <div class="occipr-submit-wrap">
                    <button type="submit" class="occipr-btn-primary">Submit PSR Registration</button>
                    <p class="occipr-submit-note">Your registration will be reviewed by parish staff before being confirmed.</p>
                </div>
            </form>
        </div>
        <?php
    }

    // ---- OCIA INQUIRY FORM ----

    private static function render_ocia_form( array $s ): void {
        $parish_id = (int) $s['ocia_parish_id'];
        ?>
        <div class="occipr-registration-form">
            <form method="post" class="occipr-form" novalidate>
                <?php self::form_top( 'ocia', $parish_id ); ?>
                <?php self::parish_field( $parish_id ); ?>

                <div class="occipr-section">
                    <h3 class="occipr-section-title">Personal Information</h3>
                    <div class="occipr-row-2">
                        <div class="occipr-field">
                            <label class="occipr-label">First Name <span class="occipr-req">*</span></label>
                            <input type="text" name="first_name" class="occipr-input" required>
                        </div>
                        <div class="occipr-field">
                            <label class="occipr-label">Last Name <span class="occipr-req">*</span></label>
                            <input type="text" name="last_name" class="occipr-input" required>
                        </div>
                    </div>
                    <div class="occipr-row-2">
                        <div class="occipr-field">
                            <label class="occipr-label">Email <span class="occipr-req">*</span></label>
                            <input type="email" name="email" class="occipr-input" required placeholder="your@email.com">
                        </div>
                        <div class="occipr-field">
                            <label class="occipr-label">Phone</label>
                            <input type="tel" name="phone" class="occipr-input" placeholder="555-555-5555">
                        </div>
                    </div>
                    <div class="occipr-field">
                        <label class="occipr-label">Street Address</label>
                        <input type="text" name="address_street" class="occipr-input" placeholder="123 Main Street">
                    </div>
                    <div class="occipr-row-3">
                        <div class="occipr-field">
                            <label class="occipr-label">City</label>
                            <input type="text" name="address_city" class="occipr-input">
                        </div>
                        <div class="occipr-field">
                            <label class="occipr-label">State</label>
                            <input type="text" name="address_state" class="occipr-input">
                        </div>
                        <div class="occipr-field">
                            <label class="occipr-label">ZIP</label>
                            <input type="text" name="address_zip" class="occipr-input">
                        </div>
                    </div>
                </div>

                <div class="occipr-section">
                    <h3 class="occipr-section-title">Your Inquiry</h3>
                    <div class="occipr-field">
                        <label class="occipr-label">Previous Faith Tradition</label>
                        <input type="text" name="previous_faith" class="occipr-input" placeholder="e.g. Baptist, Roman Catholic, None">
                    </div>
                    <div class="occipr-field">
                        <label class="occipr-label">Tell us about your interest <span class="occipr-req">*</span></label>
                        <textarea name="message" class="occipr-textarea" rows="5" required
                            placeholder="Please share a little about yourself and what has brought you to inquire about joining our community."></textarea>
                    </div>
                </div>

                <div class="occipr-submit-wrap">
                    <button type="submit" class="occipr-btn-primary">Submit Inquiry</button>
                    <p class="occipr-submit-note">Your inquiry will be received by our parish staff who will contact you soon.</p>
                </div>
            </form>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // ADMIN PAGE ROUTER (submissions queue)
    // -------------------------------------------------------------------------

    public static function queue_page() {
        if ( ! current_user_can( 'occipr_manage_records' ) ) { wp_die( 'Access denied.' ); }
        $action = sanitize_key( $_GET['action'] ?? '' );
        $id     = absint( $_GET['id'] ?? 0 );
        if ( $action === 'view' && $id ) {
            self::view_submission( $id );
        } else {
            self::list_submissions();
        }
    }

    // -------------------------------------------------------------------------
    // ADMIN: SUBMISSIONS LIST
    // -------------------------------------------------------------------------

    private static function list_submissions(): void {
        global $wpdb;

        $type_filter   = sanitize_key( $_GET['form_type'] ?? '' );
        $status_filter = sanitize_key( $_GET['status'] ?? 'pending' );

        $where = 'WHERE 1=1';
        $args  = [];
        if ( $type_filter )   { $where .= ' AND form_type = %s'; $args[] = $type_filter; }
        if ( $status_filter ) { $where .= ' AND status = %s';    $args[] = $status_filter; }

        $sql     = "SELECT * FROM {$wpdb->prefix}occipr_submissions $where ORDER BY created_at DESC";
        $records = $args ? $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) ) : $wpdb->get_results( $sql );

        $pending = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}occipr_submissions WHERE status = 'pending'" );

        $type_labels   = [ 'member' => 'Member Reg.', 'psr' => 'PSR', 'ocia' => 'OCIA' ];
        $status_labels = [ 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected' ];
        $status_styles = [
            'pending'  => 'background:#fef9e7; color:#7d6608;',
            'approved' => 'background:#eafaf1; color:#1e8449;',
            'rejected' => 'background:#f9f9f9; color:#888;',
        ];

        $notice = '';
        if ( isset( $_GET['approved'] ) ) $notice = 'Submission approved and record created in the register.';
        if ( isset( $_GET['rejected'] ) ) $notice = 'Submission rejected.';
        ?>
        <div class="wrap occi-wrap">
            <h1 class="wp-heading-inline"><span class="dashicons dashicons-email-alt"></span> Online Registrations</h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-registration-settings' ) ); ?>" class="page-title-action">Registration Settings</a>
            <hr class="wp-header-end">

            <?php if ( $notice ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
            <?php endif; ?>

            <?php if ( $pending ) : ?>
            <div class="occi-notice">
                <p><strong><?php echo $pending; ?> pending submission<?php echo $pending !== 1 ? 's' : ''; ?></strong> awaiting review.</p>
            </div>
            <?php endif; ?>

            <form method="get" class="occi-search-form" style="margin-top:16px;">
                <input type="hidden" name="page" value="occipr-registration-queue">
                <select name="form_type">
                    <option value="">All Form Types</option>
                    <?php foreach ( $type_labels as $v => $l ) : ?>
                    <option value="<?php echo esc_attr( $v ); ?>"<?php selected( $type_filter, $v ); ?>><?php echo esc_html( $l ); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="status">
                    <option value="">All Statuses</option>
                    <?php foreach ( $status_labels as $v => $l ) : ?>
                    <option value="<?php echo esc_attr( $v ); ?>"<?php selected( $status_filter, $v ); ?>><?php echo esc_html( $l ); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="button">Filter</button>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-registration-queue' ) ); ?>" class="button">Reset</a>
            </form>

            <?php if ( ! $records ) : ?>
            <p style="margin-top:20px; color:#666; font-style:italic;">No submissions found.</p>
            <?php else : ?>
            <p class="occi-count"><?php echo count( $records ); ?> submission(s) found.</p>
            <table class="widefat striped occi-register-table">
                <thead>
                    <tr>
                        <th>Date Submitted</th>
                        <th>Form Type</th>
                        <th>Name / Contact</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $records as $r ) :
                        $d = json_decode( $r->data, true ) ?: [];
                        $name = match( $r->form_type ) {
                            'member' => $d['family_name'] ?? '',
                            default  => trim( ( $d['first_name'] ?? '' ) . ' ' . ( $d['last_name'] ?? '' ) ),
                        };
                        $contact_email = match( $r->form_type ) {
                            'member' => $d['email'] ?? '',
                            'psr'    => $d['guardian1_email'] ?? '',
                            'ocia'   => $d['email'] ?? '',
                            default  => '',
                        };
                    ?>
                    <tr>
                        <td><?php echo esc_html( date( 'M j, Y g:i A', strtotime( $r->created_at ) ) ); ?></td>
                        <td><?php echo esc_html( $type_labels[ $r->form_type ] ?? $r->form_type ); ?></td>
                        <td>
                            <strong><?php echo esc_html( $name ?: '--' ); ?></strong>
                            <?php if ( $contact_email ) echo '<br><span class="occi-small">' . esc_html( $contact_email ) . '</span>'; ?>
                        </td>
                        <td>
                            <span class="occi-rank" style="<?php echo esc_attr( $status_styles[ $r->status ] ?? '' ); ?>">
                                <?php echo esc_html( $status_labels[ $r->status ] ?? $r->status ); ?>
                            </span>
                        </td>
                        <td class="occi-actions">
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-registration-queue&action=view&id=' . $r->id ) ); ?>">Review</a>
                            <?php if ( $r->status === 'pending' ) : ?>
                            | <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=occipr_approve_submission&id=' . $r->id ), 'occipr_approve_' . $r->id ) ); ?>"
                                 style="color:#1e8449; font-weight:600;"
                                 onclick="return confirm('Approve this submission and create the register record now?')">Approve</a>
                            | <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=occipr_reject_submission&id=' . $r->id ), 'occipr_reject_' . $r->id ) ); ?>"
                                 class="occi-delete"
                                 onclick="return confirm('Reject this submission?')">Reject</a>
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
    // ADMIN: SUBMISSION DETAIL / REVIEW
    // -------------------------------------------------------------------------

    private static function view_submission( int $id ): void {
        global $wpdb;
        $r = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}occipr_submissions WHERE id = %d", $id
        ) );
        if ( ! $r ) { wp_die( 'Submission not found.' ); }

        $d = json_decode( $r->data, true ) ?: [];
        $type_labels   = [ 'member' => 'Parish Member Registration', 'psr' => 'PSR / Religious Education', 'ocia' => 'OCIA Inquiry' ];
        $status_labels = [ 'pending' => 'Pending Review', 'approved' => 'Approved', 'rejected' => 'Rejected' ];
        $status_styles = [
            'pending'  => 'background:#fef9e7; color:#7d6608;',
            'approved' => 'background:#eafaf1; color:#1e8449;',
            'rejected' => 'background:#f9f9f9; color:#888;',
        ];

        $parish_name = '';
        if ( $r->parish_id ) {
            $p = $wpdb->get_row( $wpdb->prepare( "SELECT name, city, state FROM {$wpdb->prefix}occipr_parishes WHERE id = %d", $r->parish_id ) );
            if ( $p ) $parish_name = $p->name . ', ' . $p->city . ', ' . $p->state;
        }

        $notice = '';
        if ( isset( $_GET['approved'] ) ) $notice = 'Submission approved and record created.';
        if ( isset( $_GET['rejected'] ) ) $notice = 'Submission rejected.';
        ?>
        <div class="wrap occi-wrap">
            <h1>Submission Review</h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-registration-queue' ) ); ?>" class="page-title-action">&larr; Back to Submissions</a>
            <hr class="wp-header-end">

            <?php if ( $notice ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
            <?php endif; ?>

            <div class="occi-view-record" style="max-width:860px;">
                <div class="occi-cert-header">
                    <h2><?php echo esc_html( $type_labels[ $r->form_type ] ?? $r->form_type ); ?></h2>
                    <div style="margin:8px 0;">
                        <span class="occi-rank" style="<?php echo esc_attr( $status_styles[ $r->status ] ?? '' ); ?>">
                            <?php echo esc_html( $status_labels[ $r->status ] ?? $r->status ); ?>
                        </span>
                    </div>
                    <p style="color:#555; margin:4px 0;">Submitted <?php echo esc_html( date( 'F j, Y \a\t g:i A', strtotime( $r->created_at ) ) ); ?></p>
                    <?php if ( $parish_name ) : ?>
                    <p style="color:#555; margin:4px 0;"><?php echo esc_html( $parish_name ); ?></p>
                    <?php endif; ?>
                </div>

                <?php self::render_data_table( $r->form_type, $d ); ?>

                <?php if ( $r->staff_notes ) : ?>
                <h3 class="occi-report-section-title">Staff Notes</h3>
                <p><?php echo nl2br( esc_html( $r->staff_notes ) ); ?></p>
                <?php endif; ?>

                <?php if ( $r->status === 'approved' && $r->result_ids ) : ?>
                <h3 class="occi-report-section-title">Created Records</h3>
                <p>
                <?php
                $slugs = [ 'household' => 'occipr-directory', 'psr' => 'occipr-psr', 'ocia' => 'occipr-ocia' ];
                foreach ( explode( ',', $r->result_ids ) as $pair ) {
                    [ $rtype, $rid ] = array_pad( explode( ':', trim( $pair ) ), 2, '' );
                    $slug = $slugs[ $rtype ] ?? null;
                    if ( $slug && $rid ) {
                        $btn_label = ucfirst( $rtype === 'household' ? 'Household' : $rtype ) . ' Record';
                        echo '<a href="' . esc_url( admin_url( 'admin.php?page=' . $slug . '&action=view&id=' . absint( $rid ) ) ) . '" class="button button-secondary" style="margin-right:8px;">'
                           . 'View ' . esc_html( $btn_label ) . '</a>';
                    }
                }
                ?>
                </p>
                <?php endif; ?>

                <?php if ( $r->status === 'pending' ) : ?>
                <div style="margin-top:28px; padding-top:20px; border-top:2px solid #e0d0a0;">

                    <h3 style="margin-top:0; color:#2d6a1a;">Approve Submission</h3>
                    <p class="description">Approving will immediately create a record in the <?php echo esc_html( $type_labels[ $r->form_type ] ?? '' ); ?> module. You can edit the created record afterward to fill in any additional details.</p>
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=occipr_approve_submission&id=' . $r->id . '&ref=view' ), 'occipr_approve_' . $r->id ) ); ?>"
                       class="button button-primary" style="background:#1e8449; border-color:#186039;"
                       onclick="return confirm('Approve this submission and create the register record now?')">
                       Approve Submission
                    </a>

                    <h3 style="margin-top:28px; color:#c0392b;">Reject Submission</h3>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex; gap:10px; align-items:flex-start; flex-wrap:wrap;">
                        <?php wp_nonce_field( 'occipr_reject_' . $r->id ); ?>
                        <input type="hidden" name="action" value="occipr_reject_submission">
                        <input type="hidden" name="id"     value="<?php echo esc_attr( $r->id ); ?>">
                        <input type="hidden" name="ref"    value="view">
                        <textarea name="staff_notes" rows="2" class="regular-text" placeholder="Optional: reason for rejection (visible only to staff)..."></textarea>
                        <button type="submit" class="button" style="color:#c0392b; border-color:#c0392b; margin-top:0;"
                                onclick="return confirm('Reject this submission?')">Reject</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private static function render_data_table( string $type, array $d ): void {
        $row = function( string $label, string $value ): void {
            if ( $value === '' ) return;
            echo '<tr><th>' . esc_html( $label ) . '</th><td>' . nl2br( esc_html( $value ) ) . '</td></tr>';
        };

        switch ( $type ) {
            case 'member':
                echo '<h3 class="occi-report-section-title" style="margin-top:0;">Family Information</h3>';
                echo '<table class="occi-view-table">';
                $row( 'Family Name',    $d['family_name'] ?? '' );
                $row( 'Address',        implode( ', ', array_filter( [
                    $d['address_street'] ?? '', $d['address_city'] ?? '',
                    trim( ( $d['address_state'] ?? '' ) . ' ' . ( $d['address_zip'] ?? '' ) ),
                ] ) ) );
                $row( 'Phone',  $d['phone'] ?? '' );
                $row( 'Email',  $d['email'] ?? '' );
                $row( 'Notes',  $d['notes'] ?? '' );
                echo '</table>';
                if ( ! empty( $d['members'] ) ) {
                    $rels = [ 'head' => 'Head of Household', 'spouse' => 'Spouse / Partner', 'child' => 'Child', 'other' => 'Other' ];
                    echo '<h3 class="occi-report-section-title">Family Members</h3>';
                    echo '<table class="widefat striped occi-report-table"><thead><tr><th>First Name</th><th>Last Name</th><th>Relationship</th><th>Date of Birth</th></tr></thead><tbody>';
                    foreach ( $d['members'] as $m ) {
                        echo '<tr>'
                           . '<td>' . esc_html( $m['first_name'] ?? '' ) . '</td>'
                           . '<td>' . esc_html( $m['last_name']  ?? '' ) . '</td>'
                           . '<td>' . esc_html( $rels[ $m['relationship'] ?? '' ] ?? ( $m['relationship'] ?? '' ) ) . '</td>'
                           . '<td>' . ( ! empty( $m['birth_date'] ) ? esc_html( occipr_format_date( $m['birth_date'] ) ) : '' ) . '</td>'
                           . '</tr>';
                    }
                    echo '</tbody></table>';
                }
                break;

            case 'psr':
                $grades = OCCIPR_PSR::grade_options();
                echo '<h3 class="occi-report-section-title" style="margin-top:0;">Student Information</h3>';
                echo '<table class="occi-view-table">';
                $row( 'First Name',     $d['first_name'] ?? '' );
                $row( 'Preferred Name', $d['preferred_name'] ?? '' );
                $row( 'Last Name',      $d['last_name'] ?? '' );
                $row( 'Date of Birth',  ! empty( $d['birth_date'] ) ? occipr_format_date( $d['birth_date'] ) : '' );
                $row( 'Grade Level',    $grades[ $d['grade_level'] ?? '' ] ?? ( $d['grade_level'] ?? '' ) );
                $row( 'Academic Year',  $d['academic_year'] ?? '' );
                echo '</table>';
                echo '<h3 class="occi-report-section-title">Guardian Information</h3>';
                echo '<table class="occi-view-table">';
                $row( 'Guardian 1',       $d['guardian1_name']  ?? '' );
                $row( 'Guardian 1 Phone', $d['guardian1_phone'] ?? '' );
                $row( 'Guardian 1 Email', $d['guardian1_email'] ?? '' );
                $row( 'Guardian 2',       $d['guardian2_name']  ?? '' );
                $row( 'Guardian 2 Phone', $d['guardian2_phone'] ?? '' );
                $row( 'Guardian 2 Email', $d['guardian2_email'] ?? '' );
                $row( 'Home Address',     $d['home_address']    ?? '' );
                $row( 'Notes',            $d['notes']           ?? '' );
                echo '</table>';
                break;

            case 'ocia':
                echo '<h3 class="occi-report-section-title" style="margin-top:0;">Inquiry Information</h3>';
                echo '<table class="occi-view-table">';
                $row( 'First Name',      $d['first_name']     ?? '' );
                $row( 'Last Name',       $d['last_name']      ?? '' );
                $row( 'Email',           $d['email']          ?? '' );
                $row( 'Phone',           $d['phone']          ?? '' );
                $row( 'Address',         implode( ', ', array_filter( [
                    $d['address_street'] ?? '', $d['address_city'] ?? '',
                    trim( ( $d['address_state'] ?? '' ) . ' ' . ( $d['address_zip'] ?? '' ) ),
                ] ) ) );
                $row( 'Previous Faith',  $d['previous_faith'] ?? '' );
                $row( 'Message',         $d['message']        ?? '' );
                echo '</table>';
                break;
        }
    }

    // -------------------------------------------------------------------------
    // APPROVE
    // -------------------------------------------------------------------------

    public static function handle_approve() {
        if ( ! current_user_can( 'occipr_manage_records' ) ) { wp_die( 'Access denied.' ); }
        $id = absint( $_GET['id'] ?? 0 );
        check_admin_referer( 'occipr_approve_' . $id );
        global $wpdb;

        $r = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}occipr_submissions WHERE id = %d AND status = 'pending'", $id
        ) );
        if ( ! $r ) { wp_die( 'Submission not found or already processed.' ); }

        $d          = json_decode( $r->data, true ) ?: [];
        $parish_id  = $r->parish_id ? (int) $r->parish_id : null;
        $result_ids = '';

        switch ( $r->form_type ) {
            case 'member': $result_ids = self::approve_member( $d, $parish_id ); break;
            case 'psr':    $result_ids = self::approve_psr( $d, $parish_id );    break;
            case 'ocia':   $result_ids = self::approve_ocia( $d, $parish_id );   break;
        }

        $wpdb->update( "{$wpdb->prefix}occipr_submissions", [
            'status'      => 'approved',
            'reviewed_at' => current_time( 'mysql' ),
            'reviewed_by' => get_current_user_id(),
            'result_ids'  => $result_ids,
        ], [ 'id' => $id ] );

        $ref = sanitize_key( $_GET['ref'] ?? '' );
        $redirect = $ref === 'view'
            ? admin_url( 'admin.php?page=occipr-registration-queue&action=view&id=' . $id . '&approved=1' )
            : admin_url( 'admin.php?page=occipr-registration-queue&approved=1' );
        wp_redirect( $redirect );
        exit;
    }

    private static function approve_member( array $d, ?int $parish_id ): string {
        global $wpdb;
        $wpdb->query( 'START TRANSACTION' );

        $ok = $wpdb->insert( "{$wpdb->prefix}occipr_households", [
            'parish_id'      => $parish_id,
            'family_name'    => $d['family_name']    ?? '',
            'address_street' => $d['address_street'] ?? '',
            'address_city'   => $d['address_city']   ?? '',
            'address_state'  => $d['address_state']  ?? '',
            'address_zip'    => $d['address_zip']    ?? '',
            'phone'          => $d['phone']          ?? '',
            'email'          => $d['email']          ?? '',
            'notes'          => $d['notes']          ?? '',
            'status'         => 'active',
        ] );
        if ( false === $ok ) { $wpdb->query( 'ROLLBACK' ); return ''; }
        $household_id = (int) $wpdb->insert_id;

        foreach ( (array) ( $d['members'] ?? [] ) as $m ) {
            if ( empty( $m['first_name'] ) ) continue;
            $mok = $wpdb->insert( "{$wpdb->prefix}occipr_members", [
                'household_id' => $household_id,
                'first_name'   => $m['first_name'],
                'last_name'    => $m['last_name'] ?: ( $d['family_name'] ?? '' ),
                'relationship' => $m['relationship'] ?: 'other',
                'birth_date'   => $m['birth_date'] ?: null,
                'status'       => 'active',
            ] );
            if ( false === $mok ) { $wpdb->query( 'ROLLBACK' ); return ''; }
        }

        $wpdb->query( 'COMMIT' );
        return 'household:' . $household_id;
    }

    private static function approve_psr( array $d, ?int $parish_id ): string {
        global $wpdb;
        $ok = $wpdb->insert( "{$wpdb->prefix}occipr_psr", [
            'first_name'      => $d['first_name']      ?? '',
            'last_name'       => $d['last_name']       ?? '',
            'preferred_name'  => $d['preferred_name']  ?? '',
            'birth_date'      => $d['birth_date']      ?: null,
            'grade_level'     => $d['grade_level']     ?? '',
            'academic_year'   => $d['academic_year']   ?? '',
            'guardian1_name'  => $d['guardian1_name']  ?? '',
            'guardian1_phone' => $d['guardian1_phone'] ?? '',
            'guardian1_email' => $d['guardian1_email'] ?? '',
            'guardian2_name'  => $d['guardian2_name']  ?? '',
            'guardian2_phone' => $d['guardian2_phone'] ?? '',
            'guardian2_email' => $d['guardian2_email'] ?? '',
            'home_address'    => $d['home_address']    ?? '',
            'notes'           => $d['notes']           ?? '',
            'parish_id'       => $parish_id,
            'status'          => 'active',
        ] );
        return false === $ok ? '' : 'psr:' . (int) $wpdb->insert_id;
    }

    private static function approve_ocia( array $d, ?int $parish_id ): string {
        global $wpdb;
        $ok = $wpdb->insert( "{$wpdb->prefix}occipr_ocia", [
            'first_name'     => $d['first_name']     ?? '',
            'last_name'      => $d['last_name']      ?? '',
            'email'          => $d['email']          ?? '',
            'phone'          => $d['phone']          ?? '',
            'address_street' => $d['address_street'] ?? '',
            'address_city'   => $d['address_city']   ?? '',
            'address_state'  => $d['address_state']  ?? '',
            'address_zip'    => $d['address_zip']    ?? '',
            'previous_faith' => $d['previous_faith'] ?? '',
            'notes'          => $d['message']        ?? '',
            'parish_id'      => $parish_id,
            'status'         => 'inquirer',
            'inquiry_date'   => current_time( 'Y-m-d' ),
        ] );
        return false === $ok ? '' : 'ocia:' . (int) $wpdb->insert_id;
    }

    // -------------------------------------------------------------------------
    // REJECT
    // -------------------------------------------------------------------------

    public static function handle_reject() {
        if ( ! current_user_can( 'occipr_manage_records' ) ) { wp_die( 'Access denied.' ); }
        $id = absint( ( $_POST['id'] ?? $_GET['id'] ) ?? 0 );
        check_admin_referer( 'occipr_reject_' . $id );
        global $wpdb;

        $wpdb->update( "{$wpdb->prefix}occipr_submissions", [
            'status'      => 'rejected',
            'reviewed_at' => current_time( 'mysql' ),
            'reviewed_by' => get_current_user_id(),
            'staff_notes' => sanitize_textarea_field( $_POST['staff_notes'] ?? '' ),
        ], [ 'id' => $id ] );

        $ref = sanitize_key( ( $_POST['ref'] ?? $_GET['ref'] ) ?? '' );
        $redirect = $ref === 'view'
            ? admin_url( 'admin.php?page=occipr-registration-queue&action=view&id=' . $id . '&rejected=1' )
            : admin_url( 'admin.php?page=occipr-registration-queue&rejected=1' );
        wp_redirect( $redirect );
        exit;
    }

    // -------------------------------------------------------------------------
    // SETTINGS PAGE
    // -------------------------------------------------------------------------

    public static function settings_page() {
        if ( ! current_user_can( 'occipr_manage_records' ) ) { wp_die( 'Access denied.' ); }
        $s        = self::get_settings();
        $parishes = OCCIPR_Database::get_parishes();
        $notice   = isset( $_GET['saved'] ) ? 'Settings saved.' : '';
        ?>
        <div class="wrap occi-wrap">
            <h1>Online Registration Settings</h1>
            <?php if ( $notice ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
            <?php endif; ?>

            <div class="occi-notice" style="margin-bottom:20px;">
                <p>Enable the forms you need and paste the corresponding shortcode into any WordPress page. New submissions are held in <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-registration-queue' ) ); ?>">the submission queue</a> until a staff member reviews and approves or rejects them.</p>
            </div>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'occipr_save_reg_settings', 'occipr_reg_nonce' ); ?>
                <input type="hidden" name="action" value="occipr_save_reg_settings">

                <div class="occi-section">
                    <h2>Notifications</h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="notify_email">Send New Submission Alerts To</label></th>
                            <td>
                                <input type="email" id="notify_email" name="notify_email" class="regular-text"
                                       value="<?php echo esc_attr( $s['notify_email'] ?: get_option( 'admin_email' ) ); ?>">
                                <p class="description">An email notification is sent here each time a new registration is submitted. Defaults to the WordPress site admin email.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php
                self::settings_form_section(
                    'member', 'Parish Member Registration', '[occipr_member_registration]',
                    'Allows families to register with the parish online. On approval, a Household record and individual Member records are created in the Parish Directory.',
                    $s, $parishes
                );
                self::settings_form_section(
                    'psr', 'PSR / Religious Education', '[occipr_psr_registration]',
                    'Accepts PSR student registrations online. On approval, a record is created in the PSR module.',
                    $s, $parishes
                );
                self::settings_form_section(
                    'ocia', 'OCIA Inquiry Form', '[occipr_ocia_registration]',
                    'Receives OCIA inquiries from prospective candidates. On approval, a Candidate record is created in the OCIA module with status set to Inquirer.',
                    $s, $parishes
                );
                ?>

                <p class="submit">
                    <button type="submit" class="button button-primary">Save Settings</button>
                </p>
            </form>
        </div>
        <?php
    }

    private static function settings_form_section( string $type, string $title, string $shortcode, string $desc, array $s, array $parishes ): void {
        $ek = $type . '_enabled';
        $pk = $type . '_parish_id';
        $sk = $type . '_success';
        ?>
        <div class="occi-section">
            <h2><?php echo esc_html( $title ); ?></h2>
            <table class="form-table">
                <tr>
                    <th>Enable Form</th>
                    <td>
                        <label>
                            <input type="checkbox" name="<?php echo esc_attr( $ek ); ?>" value="1"<?php checked( $s[ $ek ] ?? 0 ); ?>>
                            Enable this public registration form
                        </label>
                        <p class="description"><?php echo esc_html( $desc ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th>Shortcode</th>
                    <td>
                        <code style="font-size:1.05em; user-select:all; padding:4px 8px;"><?php echo esc_html( $shortcode ); ?></code>
                        <p class="description">Add this shortcode to any WordPress page to embed the registration form.</p>
                    </td>
                </tr>
                <?php if ( count( $parishes ) > 1 ) : ?>
                <tr>
                    <th><label for="<?php echo esc_attr( $pk ); ?>">Assign Submissions To Parish</label></th>
                    <td>
                        <select id="<?php echo esc_attr( $pk ); ?>" name="<?php echo esc_attr( $pk ); ?>">
                            <option value="0">-- Let registrant choose --</option>
                            <?php foreach ( $parishes as $p ) : ?>
                            <option value="<?php echo esc_attr( $p->id ); ?>"<?php selected( $s[ $pk ] ?? 0, $p->id ); ?>>
                                <?php echo esc_html( $p->name . ' -- ' . $p->city . ', ' . $p->state ); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">For single-parish sites, select your parish here to pre-assign all submissions. Otherwise a parish dropdown appears on the public form.</p>
                    </td>
                </tr>
                <?php else :
                    // Single parish: auto-assign silently
                    $auto_parish = $parishes[0]->id ?? ( $s[ $pk ] ?? 0 );
                ?>
                <input type="hidden" name="<?php echo esc_attr( $pk ); ?>" value="<?php echo esc_attr( $auto_parish ); ?>">
                <?php endif; ?>
                <tr>
                    <th><label for="<?php echo esc_attr( $sk ); ?>">Confirmation Message</label></th>
                    <td>
                        <textarea id="<?php echo esc_attr( $sk ); ?>" name="<?php echo esc_attr( $sk ); ?>"
                                  rows="3" class="large-text"><?php echo esc_textarea( $s[ $sk ] ); ?></textarea>
                        <p class="description">Displayed to the visitor immediately after a successful form submission.</p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    public static function save_settings() {
        if ( ! current_user_can( 'occipr_manage_records' ) ) { wp_die( 'Access denied.' ); }
        check_admin_referer( 'occipr_save_reg_settings', 'occipr_reg_nonce' );

        $defaults = self::get_settings();
        update_option( 'occi_pr_registration', [
            'notify_email'     => sanitize_email( $_POST['notify_email'] ?? '' ),
            'member_enabled'   => isset( $_POST['member_enabled'] ) ? 1 : 0,
            'psr_enabled'      => isset( $_POST['psr_enabled'] )    ? 1 : 0,
            'ocia_enabled'     => isset( $_POST['ocia_enabled'] )   ? 1 : 0,
            'member_parish_id' => absint( $_POST['member_parish_id'] ?? 0 ),
            'psr_parish_id'    => absint( $_POST['psr_parish_id']    ?? 0 ),
            'ocia_parish_id'   => absint( $_POST['ocia_parish_id']   ?? 0 ),
            'member_success'   => sanitize_textarea_field( $_POST['member_success'] ?? $defaults['member_success'] ),
            'psr_success'      => sanitize_textarea_field( $_POST['psr_success']    ?? $defaults['psr_success'] ),
            'ocia_success'     => sanitize_textarea_field( $_POST['ocia_success']   ?? $defaults['ocia_success'] ),
        ] );

        wp_redirect( admin_url( 'admin.php?page=occipr-registration-settings&saved=1' ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // HELPER
    // -------------------------------------------------------------------------

    public static function pending_count(): int {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}occipr_submissions WHERE status = 'pending'"
        );
    }
}
