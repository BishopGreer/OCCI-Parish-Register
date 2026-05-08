<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OCCIPR_Database {

    public static function install() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        self::update_capabilities();

        // Parishes — includes contact info and per-parish certificate template URL
        dbDelta( "CREATE TABLE {$wpdb->prefix}occipr_parishes (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            address_street varchar(255) DEFAULT NULL,
            city varchar(100) NOT NULL,
            state varchar(50) NOT NULL,
            address_zip varchar(20) DEFAULT NULL,
            country varchar(100) DEFAULT NULL,
            phone varchar(30) DEFAULT NULL,
            email varchar(150) DEFAULT NULL,
            website varchar(255) DEFAULT NULL,
            facebook varchar(255) DEFAULT NULL,
            instagram varchar(255) DEFAULT NULL,
            cert_template_url varchar(500) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY name (name(100))
        ) $charset;" );

        // Baptisms
        dbDelta( "CREATE TABLE {$wpdb->prefix}occipr_baptisms (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            baptism_date date NOT NULL,
            birth_date date DEFAULT NULL,
            birth_place varchar(255) DEFAULT NULL,
            first_name varchar(100) NOT NULL,
            middle_name varchar(100) DEFAULT NULL,
            last_name varchar(100) NOT NULL,
            father_first_name varchar(100) DEFAULT NULL,
            father_middle_name varchar(100) DEFAULT NULL,
            father_last_name varchar(100) DEFAULT NULL,
            mother_first_name varchar(100) DEFAULT NULL,
            mother_middle_name varchar(100) DEFAULT NULL,
            mother_last_name varchar(100) DEFAULT NULL,
            mother_maiden_name varchar(100) DEFAULT NULL,
            sponsor1_name varchar(255) DEFAULT NULL,
            sponsor1_gender varchar(1) DEFAULT NULL,
            sponsor1_is_proxy tinyint(1) DEFAULT 0,
            sponsor1_proxy_for varchar(255) DEFAULT NULL,
            sponsor2_name varchar(255) DEFAULT NULL,
            sponsor2_gender varchar(1) DEFAULT NULL,
            sponsor2_is_proxy tinyint(1) DEFAULT 0,
            sponsor2_proxy_for varchar(255) DEFAULT NULL,
            minister_name varchar(255) NOT NULL,
            minister_type varchar(50) DEFAULT NULL,
            parish_id bigint(20) UNSIGNED DEFAULT NULL,
            alt_location varchar(255) DEFAULT NULL,
            notations text DEFAULT NULL,
            is_confidential tinyint(1) DEFAULT 0,
            record_book varchar(100) DEFAULT NULL,
            page_number varchar(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by bigint(20) UNSIGNED DEFAULT NULL,
            PRIMARY KEY (id),
            KEY baptism_date (baptism_date),
            KEY last_name (last_name(50))
        ) $charset;" );

        // Confirmations (flat per-person model)
        dbDelta( "CREATE TABLE {$wpdb->prefix}occipr_confirmations (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            confirmation_date date NOT NULL,
            bishop_name varchar(255) NOT NULL,
            first_name varchar(100) NOT NULL,
            middle_name varchar(100) DEFAULT NULL,
            last_name varchar(100) NOT NULL,
            saints_name varchar(100) DEFAULT NULL,
            parish_id bigint(20) UNSIGNED DEFAULT NULL,
            alt_location varchar(255) DEFAULT NULL,
            notations text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY confirmation_date (confirmation_date),
            KEY last_name (last_name(50))
        ) $charset;" );

        // Marriages
        dbDelta( "CREATE TABLE {$wpdb->prefix}occipr_marriages (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            marriage_date date NOT NULL,
            party1_first_name varchar(100) NOT NULL,
            party1_middle_name varchar(100) DEFAULT NULL,
            party1_last_name varchar(100) NOT NULL,
            party1_maiden_name varchar(100) DEFAULT NULL,
            party1_birth_date date DEFAULT NULL,
            party2_first_name varchar(100) NOT NULL,
            party2_middle_name varchar(100) DEFAULT NULL,
            party2_last_name varchar(100) NOT NULL,
            party2_maiden_name varchar(100) DEFAULT NULL,
            party2_birth_date date DEFAULT NULL,
            witness1_name varchar(255) NOT NULL,
            witness2_name varchar(255) NOT NULL,
            minister_name varchar(255) NOT NULL,
            parish_id bigint(20) UNSIGNED DEFAULT NULL,
            alt_location varchar(255) DEFAULT NULL,
            notations text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by bigint(20) UNSIGNED DEFAULT NULL,
            PRIMARY KEY (id),
            KEY marriage_date (marriage_date),
            KEY party1_last_name (party1_last_name(50)),
            KEY party2_last_name (party2_last_name(50))
        ) $charset;" );

        // Deaths
        dbDelta( "CREATE TABLE {$wpdb->prefix}occipr_deaths (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            death_date date NOT NULL,
            first_name varchar(100) NOT NULL,
            middle_name varchar(100) DEFAULT NULL,
            last_name varchar(100) NOT NULL,
            burial_location varchar(255) DEFAULT NULL,
            burial_city varchar(100) DEFAULT NULL,
            burial_state varchar(50) DEFAULT NULL,
            funeral_date date DEFAULT NULL,
            funeral_presider varchar(255) DEFAULT NULL,
            parish_id bigint(20) UNSIGNED DEFAULT NULL,
            is_graveside tinyint(1) DEFAULT 0,
            cemetery_name varchar(255) DEFAULT NULL,
            cemetery_city varchar(100) DEFAULT NULL,
            cemetery_state varchar(50) DEFAULT NULL,
            is_cremated tinyint(1) DEFAULT 0,
            ashes_interment_date date DEFAULT NULL,
            ashes_interment_place varchar(255) DEFAULT NULL,
            notations text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY death_date (death_date),
            KEY last_name (last_name(50))
        ) $charset;" );

        // First Communions
        dbDelta( "CREATE TABLE {$wpdb->prefix}occipr_communions (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            communion_date date NOT NULL,
            first_name varchar(100) NOT NULL,
            middle_name varchar(100) DEFAULT NULL,
            last_name varchar(100) NOT NULL,
            baptism_date date DEFAULT NULL,
            baptism_church varchar(255) DEFAULT NULL,
            baptism_city varchar(100) DEFAULT NULL,
            baptism_state varchar(50) DEFAULT NULL,
            presider varchar(255) NOT NULL,
            parish_id bigint(20) UNSIGNED DEFAULT NULL,
            notations text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY communion_date (communion_date),
            KEY last_name (last_name(50))
        ) $charset;" );

        // Households (parish directory)
        dbDelta( "CREATE TABLE {$wpdb->prefix}occipr_households (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            parish_id bigint(20) UNSIGNED DEFAULT NULL,
            family_name varchar(150) NOT NULL,
            address_street varchar(255) DEFAULT NULL,
            address_city varchar(100) DEFAULT NULL,
            address_state varchar(100) DEFAULT NULL,
            address_zip varchar(20) DEFAULT NULL,
            address_country varchar(100) DEFAULT NULL,
            phone varchar(30) DEFAULT NULL,
            email varchar(150) DEFAULT NULL,
            photo_id bigint(20) UNSIGNED DEFAULT NULL,
            envelope_number varchar(20) DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            notes text DEFAULT NULL,
            show_address tinyint(1) NOT NULL DEFAULT 1,
            show_phone tinyint(1) NOT NULL DEFAULT 1,
            show_email tinyint(1) NOT NULL DEFAULT 1,
            show_photo tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY family_name (family_name(50)),
            KEY parish_id (parish_id),
            KEY status (status)
        ) $charset;" );

        // Members (individuals within a household)
        dbDelta( "CREATE TABLE {$wpdb->prefix}occipr_members (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            household_id bigint(20) UNSIGNED NOT NULL,
            first_name varchar(100) NOT NULL,
            last_name varchar(100) NOT NULL,
            preferred_name varchar(100) DEFAULT NULL,
            middle_name varchar(100) DEFAULT NULL,
            relationship varchar(50) NOT NULL DEFAULT 'other',
            birth_date date DEFAULT NULL,
            member_since date DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            gender varchar(100) DEFAULT NULL,
            gender_other varchar(100) DEFAULT NULL,
            pronouns varchar(100) DEFAULT NULL,
            phone varchar(30) DEFAULT NULL,
            email varchar(150) DEFAULT NULL,
            facebook varchar(255) DEFAULT NULL,
            instagram varchar(255) DEFAULT NULL,
            website varchar(255) DEFAULT NULL,
            photo_id bigint(20) UNSIGNED DEFAULT NULL,
            notes text DEFAULT NULL,
            show_phone tinyint(1) NOT NULL DEFAULT 1,
            show_email tinyint(1) NOT NULL DEFAULT 1,
            show_social tinyint(1) NOT NULL DEFAULT 1,
            show_birthday tinyint(1) NOT NULL DEFAULT 1,
            show_photo tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY household_id (household_id),
            KEY last_name (last_name(50)),
            KEY status (status)
        ) $charset;" );

        // Ordinations
        dbDelta( "CREATE TABLE {$wpdb->prefix}occipr_ordinations (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ordination_date date NOT NULL,
            first_name varchar(100) NOT NULL,
            middle_name varchar(100) DEFAULT NULL,
            last_name varchar(100) NOT NULL,
            ordination_rank varchar(50) NOT NULL,
            presiding_bishop varchar(255) NOT NULL,
            co_consecrator1 varchar(255) DEFAULT NULL,
            co_consecrator2 varchar(255) DEFAULT NULL,
            co_consecrator3 varchar(255) DEFAULT NULL,
            parish_id bigint(20) UNSIGNED DEFAULT NULL,
            alt_location varchar(255) DEFAULT NULL,
            notations text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ordination_date (ordination_date),
            KEY last_name (last_name(50))
        ) $charset;" );

        // Donation funds
        dbDelta( "CREATE TABLE {$wpdb->prefix}occipr_donation_funds (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(150) NOT NULL,
            description text DEFAULT NULL,
            sort_order int NOT NULL DEFAULT 0,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY name (name(50))
        ) $charset;" );

        // Donations
        dbDelta( "CREATE TABLE {$wpdb->prefix}occipr_donations (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            donation_date date NOT NULL,
            fund_id bigint(20) UNSIGNED DEFAULT NULL,
            amount decimal(10,2) NOT NULL DEFAULT 0.00,
            payment_method varchar(20) NOT NULL DEFAULT 'cash',
            check_number varchar(50) DEFAULT NULL,
            parish_id bigint(20) UNSIGNED DEFAULT NULL,
            is_anonymous tinyint(1) NOT NULL DEFAULT 0,
            donor_name varchar(200) DEFAULT NULL,
            envelope_number varchar(20) DEFAULT NULL,
            household_id bigint(20) UNSIGNED DEFAULT NULL,
            notes text DEFAULT NULL,
            source varchar(20) NOT NULL DEFAULT 'manual',
            external_id varchar(100) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by bigint(20) UNSIGNED DEFAULT NULL,
            PRIMARY KEY (id),
            KEY donation_date (donation_date),
            KEY fund_id (fund_id),
            KEY parish_id (parish_id),
            KEY household_id (household_id)
        ) $charset;" );

        // Mass Attendance
        dbDelta( "CREATE TABLE {$wpdb->prefix}occipr_attendance (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            service_date date NOT NULL,
            service_type varchar(20) NOT NULL DEFAULT 'sunday',
            service_label varchar(255) DEFAULT NULL,
            service_time time DEFAULT NULL,
            parish_id bigint(20) UNSIGNED DEFAULT NULL,
            headcount int UNSIGNED NOT NULL DEFAULT 0,
            communion_count int UNSIGNED DEFAULT NULL,
            notes text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY service_date (service_date),
            KEY parish_id (parish_id)
        ) $charset;" );

        // PSR (Parish School of Religion / Religious Education)
        dbDelta( "CREATE TABLE {$wpdb->prefix}occipr_psr (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            first_name varchar(100) NOT NULL,
            preferred_name varchar(100) DEFAULT NULL,
            middle_name varchar(100) DEFAULT NULL,
            last_name varchar(100) NOT NULL,
            birth_date date DEFAULT NULL,
            grade_level varchar(20) DEFAULT NULL,
            academic_year varchar(10) DEFAULT NULL,
            enrollment_date date DEFAULT NULL,
            catechist varchar(255) DEFAULT NULL,
            class_group varchar(100) DEFAULT NULL,
            parish_id bigint(20) UNSIGNED DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            guardian1_name varchar(200) DEFAULT NULL,
            guardian1_phone varchar(30) DEFAULT NULL,
            guardian1_email varchar(150) DEFAULT NULL,
            guardian2_name varchar(200) DEFAULT NULL,
            guardian2_phone varchar(30) DEFAULT NULL,
            guardian2_email varchar(150) DEFAULT NULL,
            home_address text DEFAULT NULL,
            is_baptized tinyint(1) NOT NULL DEFAULT 0,
            baptism_date date DEFAULT NULL,
            baptism_church varchar(255) DEFAULT NULL,
            baptism_record_id bigint(20) UNSIGNED DEFAULT NULL,
            received_first_communion tinyint(1) NOT NULL DEFAULT 0,
            communion_date date DEFAULT NULL,
            communion_church varchar(255) DEFAULT NULL,
            communion_record_id bigint(20) UNSIGNED DEFAULT NULL,
            is_confirmed tinyint(1) NOT NULL DEFAULT 0,
            confirmation_date date DEFAULT NULL,
            confirmation_church varchar(255) DEFAULT NULL,
            confirmation_record_id bigint(20) UNSIGNED DEFAULT NULL,
            notes text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY last_name (last_name(50)),
            KEY status (status),
            KEY parish_id (parish_id),
            KEY academic_year (academic_year)
        ) $charset;" );

        // OCIA (Order of Christian Initiation of Adults)
        dbDelta( "CREATE TABLE {$wpdb->prefix}occipr_ocia (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            first_name varchar(100) NOT NULL,
            preferred_name varchar(100) DEFAULT NULL,
            middle_name varchar(100) DEFAULT NULL,
            last_name varchar(100) NOT NULL,
            birth_date date DEFAULT NULL,
            previous_faith varchar(150) DEFAULT NULL,
            address_street varchar(255) DEFAULT NULL,
            address_city varchar(100) DEFAULT NULL,
            address_state varchar(100) DEFAULT NULL,
            address_zip varchar(20) DEFAULT NULL,
            phone varchar(30) DEFAULT NULL,
            email varchar(150) DEFAULT NULL,
            parish_id bigint(20) UNSIGNED DEFAULT NULL,
            inquiry_date date DEFAULT NULL,
            enrollment_date date DEFAULT NULL,
            election_date date DEFAULT NULL,
            completion_date date DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'inquirer',
            catechist varchar(255) DEFAULT NULL,
            presider varchar(255) DEFAULT NULL,
            sponsor_name varchar(255) DEFAULT NULL,
            sponsor2_name varchar(255) DEFAULT NULL,
            previously_baptized tinyint(1) NOT NULL DEFAULT 0,
            prior_baptism_date date DEFAULT NULL,
            prior_baptism_church varchar(255) DEFAULT NULL,
            baptism_record_id bigint(20) UNSIGNED DEFAULT NULL,
            confirmation_record_id bigint(20) UNSIGNED DEFAULT NULL,
            communion_record_id bigint(20) UNSIGNED DEFAULT NULL,
            notes text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY last_name (last_name(50)),
            KEY status (status),
            KEY parish_id (parish_id),
            KEY enrollment_date (enrollment_date)
        ) $charset;" );

        // Online Registration Submissions queue
        dbDelta( "CREATE TABLE {$wpdb->prefix}occipr_submissions (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            form_type varchar(20) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            parish_id bigint(20) UNSIGNED DEFAULT NULL,
            submitter_ip varchar(45) DEFAULT NULL,
            submitter_ua varchar(500) DEFAULT NULL,
            data longtext DEFAULT NULL,
            staff_notes text DEFAULT NULL,
            reviewed_by bigint(20) UNSIGNED DEFAULT NULL,
            reviewed_at datetime DEFAULT NULL,
            result_ids varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY form_type (form_type),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset;" );

        update_option( 'occi_pr_db_version', OCCI_PR_VERSION );
    }

    /**
     * Assign capabilities to all roles at Contributor level and above.
     * Both occipr_view_records and occipr_manage_records are granted to
     * Contributor, Author, Editor, and Administrator.
     * Subscribers receive no access.
     */
    public static function update_capabilities() {
        $roles_with_access = [ 'contributor', 'author', 'editor', 'administrator' ];
        foreach ( $roles_with_access as $role_slug ) {
            $role = get_role( $role_slug );
            if ( $role ) {
                $role->add_cap( 'occipr_view_records' );
                $role->add_cap( 'occipr_manage_records' );
            }
        }
        // Explicitly revoke from Subscriber (safety measure)
        $subscriber = get_role( 'subscriber' );
        if ( $subscriber ) {
            $subscriber->remove_cap( 'occipr_view_records' );
            $subscriber->remove_cap( 'occipr_manage_records' );
        }
    }

    public static function deactivate() {
        // Capabilities and data persist on deactivation.
    }

    public static function get_parishes() {
        global $wpdb;
        return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}occipr_parishes ORDER BY name ASC" );
    }

    public static function parish_dropdown( $selected = 0 ) {
        $parishes = self::get_parishes();
        $html = '<option value="">-- Select Parish --</option>';
        foreach ( $parishes as $p ) {
            $sel  = selected( $selected, $p->id, false );
            $html .= '<option value="' . esc_attr( $p->id ) . '"' . $sel . '>'
                   . esc_html( $p->name . ', ' . $p->city . ', ' . $p->state )
                   . '</option>';
        }
        return $html;
    }
}
