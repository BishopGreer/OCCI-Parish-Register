<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class OCCIPR_ParishReports {

    // -------------------------------------------------------------------------
    // PAGE ROUTER
    // -------------------------------------------------------------------------

    public static function page() {
        if ( ! current_user_can( 'occipr_view_records' ) ) { wp_die( 'Access denied.' ); }
        $report = sanitize_key( $_GET['report'] ?? 'attendance' );
        switch ( $report ) {
            case 'donations':
                self::donations_report();
                break;
            case 'ocia':
                self::ocia_report();
                break;
            default:
                self::attendance_report();
        }
    }

    // -------------------------------------------------------------------------
    // SHARED: TAB NAV
    // -------------------------------------------------------------------------

    private static function tab_nav( string $active ): void {
        $tabs = [
            'attendance' => 'Sunday Attendance',
            'donations'  => 'Donations',
            'ocia'       => 'OCIA',
        ];
        echo '<nav class="nav-tab-wrapper occi-report-tabs">';
        foreach ( $tabs as $key => $label ) {
            $url   = admin_url( 'admin.php?page=occipr-parish-reports&report=' . $key );
            $class = ( $active === $key ) ? 'nav-tab nav-tab-active' : 'nav-tab';
            echo '<a href="' . esc_url( $url ) . '" class="' . $class . '">' . esc_html( $label ) . '</a>';
        }
        echo '</nav>';
    }

    // -------------------------------------------------------------------------
    // ATTENDANCE REPORT
    // -------------------------------------------------------------------------

    private static function attendance_report(): void {
        global $wpdb;

        $parishes = OCCIPR_Database::get_parishes();
        $types    = [
            'sunday'   => 'Sunday Mass',
            'holy_day' => 'Holy Day of Obligation',
            'special'  => 'Special Mass',
            'other'    => 'Other Service',
        ];

        // Available years
        $years = $wpdb->get_col(
            "SELECT DISTINCT YEAR(service_date) AS y FROM {$wpdb->prefix}occipr_attendance ORDER BY y DESC"
        );
        $cur_year = date( 'Y' );

        // Filters
        $parish_filter = absint( $_GET['parish_id'] ?? 0 );
        $type_filter   = sanitize_key( $_GET['service_type'] ?? '' );
        $date_from     = sanitize_text_field( $_GET['date_from'] ?? '' );
        $date_to       = sanitize_text_field( $_GET['date_to'] ?? '' );
        $year_filter   = absint( $_GET['year'] ?? 0 );
        $generated     = isset( $_GET['run'] );

        // If year selected and no custom dates, derive date range
        if ( $year_filter && ! $date_from && ! $date_to ) {
            $date_from = $year_filter . '-01-01';
            $date_to   = $year_filter . '-12-31';
        }

        $where = 'WHERE 1=1';
        $args  = [];
        if ( $parish_filter ) { $where .= ' AND parish_id = %d';      $args[] = $parish_filter; }
        if ( $type_filter )   { $where .= ' AND service_type = %s';   $args[] = $type_filter; }
        if ( $date_from )     { $where .= ' AND service_date >= %s';  $args[] = $date_from; }
        if ( $date_to )       { $where .= ' AND service_date <= %s';  $args[] = $date_to; }

        ?>
        <div class="wrap occi-wrap">
            <h1><span class="dashicons dashicons-chart-bar"></span> Parish Reports</h1>
            <?php self::tab_nav( 'attendance' ); ?>
            <hr class="wp-header-end">

            <!-- Filters -->
            <form method="get" class="occi-search-form" style="margin-top:16px;">
                <input type="hidden" name="page"   value="occipr-parish-reports">
                <input type="hidden" name="report" value="attendance">
                <?php if ( count( $parishes ) > 1 ) : ?>
                <select name="parish_id">
                    <option value="">All Parishes</option>
                    <?php foreach ( $parishes as $p ) : ?>
                    <option value="<?php echo esc_attr( $p->id ); ?>"<?php selected( $parish_filter, $p->id ); ?>><?php echo esc_html( $p->name ); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <select name="year">
                    <option value="">Custom Range</option>
                    <?php foreach ( $years as $y ) : ?>
                    <option value="<?php echo esc_attr( $y ); ?>"<?php selected( $year_filter, $y ); ?>><?php echo esc_html( $y ); ?></option>
                    <?php endforeach; ?>
                    <?php if ( ! in_array( $cur_year, $years ) ) : ?>
                    <option value="<?php echo $cur_year; ?>"<?php selected( $year_filter, $cur_year ); ?>><?php echo $cur_year; ?></option>
                    <?php endif; ?>
                </select>
                <select name="service_type">
                    <option value="">All Service Types</option>
                    <?php foreach ( $types as $val => $label ) : ?>
                    <option value="<?php echo esc_attr( $val ); ?>"<?php selected( $type_filter, $val ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" title="From">
                <input type="date" name="date_to"   value="<?php echo esc_attr( $date_to ); ?>"   title="To">
                <input type="hidden" name="run" value="1">
                <button type="submit" class="button button-primary">Generate Report</button>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-parish-reports&report=attendance' ) ); ?>" class="button">Reset</a>
            </form>

            <?php if ( ! $generated ) : ?>
            <p style="margin-top:20px; color:#666; font-style:italic;">Select filters above and click Generate Report.</p>
            <?php else :

                // --- Fetch data ---
                $records = $args
                    ? $wpdb->get_results( $wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}occipr_attendance $where ORDER BY service_date ASC", ...$args ) )
                    : $wpdb->get_results(
                        "SELECT * FROM {$wpdb->prefix}occipr_attendance $where ORDER BY service_date ASC" );

                if ( ! $records ) : ?>
                <p style="margin-top:20px;">No attendance records found for the selected filters.</p>
                <?php else :

                    // Summary
                    $total_services  = count( $records );
                    $total_headcount = array_sum( array_column( $records, 'headcount' ) );
                    $avg_headcount   = $total_services ? round( $total_headcount / $total_services ) : 0;
                    $counts          = array_column( $records, 'headcount' );
                    $max_headcount   = max( $counts );
                    $min_headcount   = min( $counts );

                    // Monthly breakdown
                    $monthly = [];
                    foreach ( $records as $r ) {
                        $mo = date( 'Y-m', strtotime( $r->service_date ) );
                        if ( ! isset( $monthly[ $mo ] ) ) {
                            $monthly[ $mo ] = [ 'services' => 0, 'total' => 0, 'high' => 0, 'low' => PHP_INT_MAX ];
                        }
                        $monthly[ $mo ]['services']++;
                        $monthly[ $mo ]['total'] += $r->headcount;
                        $monthly[ $mo ]['high']   = max( $monthly[ $mo ]['high'], $r->headcount );
                        $monthly[ $mo ]['low']    = min( $monthly[ $mo ]['low'],  $r->headcount );
                    }

                    // Service type breakdown
                    $by_type = [];
                    foreach ( $records as $r ) {
                        $t = $r->service_type;
                        if ( ! isset( $by_type[ $t ] ) ) { $by_type[ $t ] = [ 'services' => 0, 'total' => 0 ]; }
                        $by_type[ $t ]['services']++;
                        $by_type[ $t ]['total'] += $r->headcount;
                    }

                    // Report title
                    $parish_name = '';
                    if ( $parish_filter ) {
                        foreach ( $parishes as $p ) {
                            if ( $p->id == $parish_filter ) { $parish_name = $p->name; break; }
                        }
                    }
                    $period = $date_from
                        ? occipr_format_date( $date_from ) . ' -- ' . occipr_format_date( $date_to )
                        : 'All Dates';
                ?>
                <!-- Print button -->
                <div style="margin-top:16px; margin-bottom:8px;">
                    <button class="button button-secondary" onclick="window.print()">&#128438; Print Report</button>
                </div>

                <div class="occi-report-output">
                    <div class="occi-report-header">
                        <h2>Attendance Report</h2>
                        <?php if ( $parish_name ) : ?><p><?php echo esc_html( $parish_name ); ?></p><?php endif; ?>
                        <?php if ( $type_filter && isset( $types[ $type_filter ] ) ) : ?><p><?php echo esc_html( $types[ $type_filter ] ); ?></p><?php endif; ?>
                        <p class="occi-report-period"><?php echo esc_html( $period ); ?></p>
                    </div>

                    <!-- Summary bar -->
                    <div class="occi-attendance-summary occi-report-summary">
                        <div class="occi-att-stat">
                            <span class="occi-att-num"><?php echo $total_services; ?></span>
                            <span class="occi-att-lbl">Total Services</span>
                        </div>
                        <div class="occi-att-stat">
                            <span class="occi-att-num"><?php echo number_format( $total_headcount ); ?></span>
                            <span class="occi-att-lbl">Total Attendance</span>
                        </div>
                        <div class="occi-att-stat">
                            <span class="occi-att-num"><?php echo $avg_headcount; ?></span>
                            <span class="occi-att-lbl">Average per Service</span>
                        </div>
                        <div class="occi-att-stat">
                            <span class="occi-att-num"><?php echo $max_headcount; ?></span>
                            <span class="occi-att-lbl">Highest Service</span>
                        </div>
                        <div class="occi-att-stat">
                            <span class="occi-att-num"><?php echo $min_headcount; ?></span>
                            <span class="occi-att-lbl">Lowest Service</span>
                        </div>
                    </div>

                    <!-- Monthly breakdown -->
                    <h3 class="occi-report-section-title">Monthly Breakdown</h3>
                    <table class="widefat striped occi-report-table">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th class="num">Services</th>
                                <th class="num">Total Attendance</th>
                                <th class="num">Average</th>
                                <th class="num">Highest</th>
                                <th class="num">Lowest</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $monthly as $mo => $m ) : ?>
                            <tr>
                                <td><strong><?php echo esc_html( date( 'F Y', strtotime( $mo . '-01' ) ) ); ?></strong></td>
                                <td class="num"><?php echo $m['services']; ?></td>
                                <td class="num"><?php echo number_format( $m['total'] ); ?></td>
                                <td class="num"><?php echo $m['services'] ? round( $m['total'] / $m['services'] ) : '--'; ?></td>
                                <td class="num"><?php echo $m['high']; ?></td>
                                <td class="num"><?php echo $m['low'] === PHP_INT_MAX ? '--' : $m['low']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="occi-report-total-row">
                                <th>Total</th>
                                <th class="num"><?php echo $total_services; ?></th>
                                <th class="num"><?php echo number_format( $total_headcount ); ?></th>
                                <th class="num"><?php echo $avg_headcount; ?></th>
                                <th class="num"><?php echo $max_headcount; ?></th>
                                <th class="num"><?php echo $min_headcount; ?></th>
                            </tr>
                        </tfoot>
                    </table>

                    <!-- Service type breakdown -->
                    <?php if ( ! $type_filter ) : ?>
                    <h3 class="occi-report-section-title">Breakdown by Service Type</h3>
                    <table class="widefat striped occi-report-table">
                        <thead>
                            <tr>
                                <th>Service Type</th>
                                <th class="num">Services</th>
                                <th class="num">Total Attendance</th>
                                <th class="num">Average</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $by_type as $t => $bt ) : ?>
                            <tr>
                                <td><?php echo esc_html( $types[ $t ] ?? ucfirst( $t ) ); ?></td>
                                <td class="num"><?php echo $bt['services']; ?></td>
                                <td class="num"><?php echo number_format( $bt['total'] ); ?></td>
                                <td class="num"><?php echo $bt['services'] ? round( $bt['total'] / $bt['services'] ) : '--'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>

                    <!-- Individual service log -->
                    <h3 class="occi-report-section-title">Service Log</h3>
                    <table class="widefat striped occi-report-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Service</th>
                                <th>Time</th>
                                <th class="num">Headcount</th>
                                <th class="num">Communion</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $records as $r ) : ?>
                            <tr>
                                <td><?php echo esc_html( occipr_format_date( $r->service_date ) ); ?></td>
                                <td>
                                    <?php echo esc_html( $types[ $r->service_type ] ?? ucfirst( $r->service_type ) ); ?>
                                    <?php if ( $r->service_label ) echo '<br><small>' . esc_html( $r->service_label ) . '</small>'; ?>
                                </td>
                                <td><?php echo $r->service_time ? esc_html( date( 'g:i A', strtotime( $r->service_time ) ) ) : ''; ?></td>
                                <td class="num"><strong><?php echo intval( $r->headcount ); ?></strong></td>
                                <td class="num"><?php echo $r->communion_count !== null ? intval( $r->communion_count ) : ''; ?></td>
                                <td class="occi-small"><?php echo esc_html( $r->notes ?? '' ); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <p class="occi-report-generated">Report generated <?php echo date( 'F j, Y \a\t g:i A' ); ?></p>
                </div><!-- .occi-report-output -->

                <?php endif; // records
            endif; // generated ?>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // DONATIONS REPORT
    // -------------------------------------------------------------------------

    private static function donations_report(): void {
        global $wpdb;

        $parishes = OCCIPR_Database::get_parishes();
        $funds    = OCCIPR_Donations::get_funds();
        $methods  = OCCIPR_Donations::payment_methods();

        // Available years
        $years = $wpdb->get_col(
            "SELECT DISTINCT YEAR(donation_date) AS y FROM {$wpdb->prefix}occipr_donations ORDER BY y DESC"
        );
        $cur_year = date( 'Y' );

        // Filters
        $parish_filter = absint( $_GET['parish_id'] ?? 0 );
        $fund_filter   = absint( $_GET['fund_id'] ?? 0 );
        $method_filter = sanitize_key( $_GET['payment_method'] ?? '' );
        $date_from     = sanitize_text_field( $_GET['date_from'] ?? '' );
        $date_to       = sanitize_text_field( $_GET['date_to'] ?? '' );
        $year_filter   = absint( $_GET['year'] ?? 0 );
        $generated     = isset( $_GET['run'] );

        if ( $year_filter && ! $date_from && ! $date_to ) {
            $date_from = $year_filter . '-01-01';
            $date_to   = $year_filter . '-12-31';
        }

        $where = 'WHERE 1=1';
        $args  = [];
        if ( $parish_filter ) { $where .= ' AND parish_id = %d';       $args[] = $parish_filter; }
        if ( $fund_filter )   { $where .= ' AND fund_id = %d';          $args[] = $fund_filter; }
        if ( $method_filter ) { $where .= ' AND payment_method = %s';   $args[] = $method_filter; }
        if ( $date_from )     { $where .= ' AND donation_date >= %s';   $args[] = $date_from; }
        if ( $date_to )       { $where .= ' AND donation_date <= %s';   $args[] = $date_to; }

        ?>
        <div class="wrap occi-wrap">
            <h1><span class="dashicons dashicons-chart-bar"></span> Parish Reports</h1>
            <?php self::tab_nav( 'donations' ); ?>
            <hr class="wp-header-end">

            <!-- Filters -->
            <form method="get" class="occi-search-form" style="margin-top:16px;">
                <input type="hidden" name="page"   value="occipr-parish-reports">
                <input type="hidden" name="report" value="donations">
                <?php if ( count( $parishes ) > 1 ) : ?>
                <select name="parish_id">
                    <option value="">All Parishes</option>
                    <?php foreach ( $parishes as $p ) : ?>
                    <option value="<?php echo esc_attr( $p->id ); ?>"<?php selected( $parish_filter, $p->id ); ?>><?php echo esc_html( $p->name ); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <select name="year">
                    <option value="">Custom Range</option>
                    <?php foreach ( $years as $y ) : ?>
                    <option value="<?php echo esc_attr( $y ); ?>"<?php selected( $year_filter, $y ); ?>><?php echo esc_html( $y ); ?></option>
                    <?php endforeach; ?>
                    <?php if ( ! in_array( $cur_year, $years ) ) : ?>
                    <option value="<?php echo $cur_year; ?>"<?php selected( $year_filter, $cur_year ); ?>><?php echo $cur_year; ?></option>
                    <?php endif; ?>
                </select>
                <select name="fund_id">
                    <option value="">All Funds</option>
                    <?php foreach ( $funds as $f ) : ?>
                    <option value="<?php echo esc_attr( $f->id ); ?>"<?php selected( $fund_filter, $f->id ); ?>><?php echo esc_html( $f->name ); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="payment_method">
                    <option value="">All Sources</option>
                    <?php foreach ( $methods as $val => $label ) : ?>
                    <option value="<?php echo esc_attr( $val ); ?>"<?php selected( $method_filter, $val ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" title="From">
                <input type="date" name="date_to"   value="<?php echo esc_attr( $date_to ); ?>"   title="To">
                <input type="hidden" name="run" value="1">
                <button type="submit" class="button button-primary">Generate Report</button>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-parish-reports&report=donations' ) ); ?>" class="button">Reset</a>
            </form>

            <?php if ( ! $generated ) : ?>
            <p style="margin-top:20px; color:#666; font-style:italic;">Select filters above and click Generate Report.</p>
            <?php else :

                $sql = "SELECT d.*,
                            f.name AS fund_name,
                            p.name AS parish_name
                        FROM {$wpdb->prefix}occipr_donations d
                        LEFT JOIN {$wpdb->prefix}occipr_donation_funds f ON f.id = d.fund_id
                        LEFT JOIN {$wpdb->prefix}occipr_parishes p ON p.id = d.parish_id
                        $where
                        ORDER BY d.donation_date ASC, d.id ASC";
                $records = $args
                    ? $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) )
                    : $wpdb->get_results( $sql );

                if ( ! $records ) : ?>
                <p style="margin-top:20px;">No donation records found for the selected filters.</p>
                <?php else :

                    // Summary
                    $total_count  = count( $records );
                    $total_amount = array_sum( array_column( $records, 'amount' ) );
                    $avg_amount   = $total_count ? $total_amount / $total_count : 0;

                    // By fund
                    $by_fund = [];
                    foreach ( $records as $r ) {
                        $key = $r->fund_name ?? 'Unassigned';
                        if ( ! isset( $by_fund[ $key ] ) ) { $by_fund[ $key ] = [ 'count' => 0, 'total' => 0.0 ]; }
                        $by_fund[ $key ]['count']++;
                        $by_fund[ $key ]['total'] += (float) $r->amount;
                    }
                    arsort( $by_fund );  // sort by key won't work; sort by total instead
                    uasort( $by_fund, fn( $a, $b ) => $b['total'] <=> $a['total'] );

                    // By payment source
                    $by_source = [];
                    foreach ( $records as $r ) {
                        $key = $r->payment_method;
                        if ( ! isset( $by_source[ $key ] ) ) { $by_source[ $key ] = [ 'count' => 0, 'total' => 0.0 ]; }
                        $by_source[ $key ]['count']++;
                        $by_source[ $key ]['total'] += (float) $r->amount;
                    }
                    uasort( $by_source, fn( $a, $b ) => $b['total'] <=> $a['total'] );

                    // Monthly totals
                    $monthly = [];
                    foreach ( $records as $r ) {
                        $mo = date( 'Y-m', strtotime( $r->donation_date ) );
                        if ( ! isset( $monthly[ $mo ] ) ) { $monthly[ $mo ] = [ 'count' => 0, 'total' => 0.0 ]; }
                        $monthly[ $mo ]['count']++;
                        $monthly[ $mo ]['total'] += (float) $r->amount;
                    }

                    // Report header text
                    $parish_name = '';
                    if ( $parish_filter ) {
                        foreach ( $parishes as $p ) {
                            if ( $p->id == $parish_filter ) { $parish_name = $p->name; break; }
                        }
                    }
                    $period = $date_from
                        ? occipr_format_date( $date_from ) . ' -- ' . occipr_format_date( $date_to )
                        : 'All Dates';
                ?>
                <!-- Print button -->
                <div style="margin-top:16px; margin-bottom:8px;">
                    <button class="button button-secondary" onclick="window.print()">&#128438; Print Report</button>
                </div>

                <div class="occi-report-output">
                    <div class="occi-report-header">
                        <h2>Donations Report</h2>
                        <?php if ( $parish_name ) : ?><p><?php echo esc_html( $parish_name ); ?></p><?php endif; ?>
                        <p class="occi-report-period"><?php echo esc_html( $period ); ?></p>
                    </div>

                    <!-- Summary bar -->
                    <div class="occi-attendance-summary occi-report-summary">
                        <div class="occi-att-stat">
                            <span class="occi-att-num"><?php echo number_format( $total_count ); ?></span>
                            <span class="occi-att-lbl">Total Donations</span>
                        </div>
                        <div class="occi-att-stat">
                            <span class="occi-att-num">$<?php echo number_format( $total_amount, 2 ); ?></span>
                            <span class="occi-att-lbl">Total Amount</span>
                        </div>
                        <div class="occi-att-stat">
                            <span class="occi-att-num">$<?php echo number_format( $avg_amount, 2 ); ?></span>
                            <span class="occi-att-lbl">Average Donation</span>
                        </div>
                    </div>

                    <!-- By fund -->
                    <?php if ( ! $fund_filter ) : ?>
                    <h3 class="occi-report-section-title">Breakdown by Fund</h3>
                    <table class="widefat striped occi-report-table">
                        <thead>
                            <tr>
                                <th>Fund</th>
                                <th class="num">Donations</th>
                                <th class="num">Total Amount</th>
                                <th class="num">% of Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $by_fund as $fund_name => $bf ) : ?>
                            <tr>
                                <td><strong><?php echo esc_html( $fund_name ); ?></strong></td>
                                <td class="num"><?php echo number_format( $bf['count'] ); ?></td>
                                <td class="num">$<?php echo number_format( $bf['total'], 2 ); ?></td>
                                <td class="num"><?php echo $total_amount ? round( $bf['total'] / $total_amount * 100, 1 ) . '%' : '--'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="occi-report-total-row">
                                <th>Total</th>
                                <th class="num"><?php echo number_format( $total_count ); ?></th>
                                <th class="num">$<?php echo number_format( $total_amount, 2 ); ?></th>
                                <th class="num">100%</th>
                            </tr>
                        </tfoot>
                    </table>
                    <?php endif; ?>

                    <!-- By payment source -->
                    <?php if ( ! $method_filter ) : ?>
                    <h3 class="occi-report-section-title">Breakdown by Payment Source</h3>
                    <table class="widefat striped occi-report-table">
                        <thead>
                            <tr>
                                <th>Source</th>
                                <th class="num">Donations</th>
                                <th class="num">Total Amount</th>
                                <th class="num">% of Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $by_source as $method_key => $bs ) : ?>
                            <tr>
                                <td><strong><?php echo esc_html( $methods[ $method_key ] ?? ucfirst( $method_key ) ); ?></strong></td>
                                <td class="num"><?php echo number_format( $bs['count'] ); ?></td>
                                <td class="num">$<?php echo number_format( $bs['total'], 2 ); ?></td>
                                <td class="num"><?php echo $total_amount ? round( $bs['total'] / $total_amount * 100, 1 ) . '%' : '--'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="occi-report-total-row">
                                <th>Total</th>
                                <th class="num"><?php echo number_format( $total_count ); ?></th>
                                <th class="num">$<?php echo number_format( $total_amount, 2 ); ?></th>
                                <th class="num">100%</th>
                            </tr>
                        </tfoot>
                    </table>
                    <?php endif; ?>

                    <!-- Monthly totals -->
                    <h3 class="occi-report-section-title">Monthly Totals</h3>
                    <table class="widefat striped occi-report-table">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th class="num">Donations</th>
                                <th class="num">Total Amount</th>
                                <th class="num">Running Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $running = 0.0; ?>
                            <?php foreach ( $monthly as $mo => $m ) : ?>
                            <?php $running += $m['total']; ?>
                            <tr>
                                <td><strong><?php echo esc_html( date( 'F Y', strtotime( $mo . '-01' ) ) ); ?></strong></td>
                                <td class="num"><?php echo number_format( $m['count'] ); ?></td>
                                <td class="num">$<?php echo number_format( $m['total'], 2 ); ?></td>
                                <td class="num">$<?php echo number_format( $running, 2 ); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="occi-report-total-row">
                                <th>Total</th>
                                <th class="num"><?php echo number_format( $total_count ); ?></th>
                                <th class="num">$<?php echo number_format( $total_amount, 2 ); ?></th>
                                <th class="num"></th>
                            </tr>
                        </tfoot>
                    </table>

                    <!-- Detail ledger -->
                    <h3 class="occi-report-section-title">Donation Ledger</h3>
                    <table class="widefat striped occi-report-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Donor</th>
                                <th>Fund</th>
                                <th>Source</th>
                                <th class="num">Amount</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $records as $r ) : ?>
                            <tr>
                                <td><?php echo esc_html( occipr_format_date( $r->donation_date ) ); ?></td>
                                <td>
                                    <?php if ( $r->is_anonymous ) : ?>
                                    <em style="color:#888;">Anonymous</em>
                                    <?php else : ?>
                                    <?php echo esc_html( $r->donor_name ?: ( $r->envelope_number ? 'Env. #' . $r->envelope_number : '--' ) ); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html( $r->fund_name ?? 'Unassigned' ); ?></td>
                                <td><?php echo esc_html( $methods[ $r->payment_method ] ?? ucfirst( $r->payment_method ) ); ?></td>
                                <td class="num"><strong>$<?php echo number_format( (float) $r->amount, 2 ); ?></strong></td>
                                <td class="occi-small"><?php echo esc_html( $r->notes ?? '' ); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="occi-report-total-row">
                                <th colspan="4">Total</th>
                                <th class="num">$<?php echo number_format( $total_amount, 2 ); ?></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>

                    <p class="occi-report-generated">Report generated <?php echo date( 'F j, Y \a\t g:i A' ); ?></p>
                </div><!-- .occi-report-output -->

                <?php endif; // records
            endif; // generated ?>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // OCIA REPORT
    // -------------------------------------------------------------------------

    private static function ocia_report(): void {
        global $wpdb;

        $parishes = OCCIPR_Database::get_parishes();
        $statuses = OCCIPR_OCIA::status_options();

        // Available cohort years (by enrollment_date)
        $years = $wpdb->get_col(
            "SELECT DISTINCT YEAR(enrollment_date) AS y FROM {$wpdb->prefix}occipr_ocia WHERE enrollment_date IS NOT NULL ORDER BY y DESC"
        );
        $cur_year = date( 'Y' );

        // Filters
        $parish_filter = absint( $_GET['parish_id'] ?? 0 );
        $status_filter = sanitize_key( $_GET['status'] ?? '' );
        $year_filter   = absint( $_GET['year'] ?? 0 );
        $generated     = isset( $_GET['run'] );

        $where = 'WHERE 1=1';
        $args  = [];
        if ( $parish_filter ) { $where .= ' AND o.parish_id = %d';             $args[] = $parish_filter; }
        if ( $status_filter ) { $where .= ' AND o.status = %s';                 $args[] = $status_filter; }
        if ( $year_filter )   { $where .= ' AND YEAR(o.enrollment_date) = %d'; $args[] = $year_filter; }

        ?>
        <div class="wrap occi-wrap">
            <h1><span class="dashicons dashicons-chart-bar"></span> Parish Reports</h1>
            <?php self::tab_nav( 'ocia' ); ?>
            <hr class="wp-header-end">

            <!-- Filters -->
            <form method="get" class="occi-search-form" style="margin-top:16px;">
                <input type="hidden" name="page"   value="occipr-parish-reports">
                <input type="hidden" name="report" value="ocia">
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
                    <option value="">All Cohort Years</option>
                    <?php foreach ( $years as $y ) : ?>
                    <option value="<?php echo esc_attr( $y ); ?>"<?php selected( $year_filter, $y ); ?>><?php echo esc_html( $y ); ?></option>
                    <?php endforeach; ?>
                    <?php if ( ! in_array( $cur_year, $years ) ) : ?>
                    <option value="<?php echo $cur_year; ?>"<?php selected( $year_filter, $cur_year ); ?>><?php echo $cur_year; ?></option>
                    <?php endif; ?>
                </select>
                <input type="hidden" name="run" value="1">
                <button type="submit" class="button button-primary">Generate Report</button>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=occipr-parish-reports&report=ocia' ) ); ?>" class="button">Reset</a>
            </form>

            <?php if ( ! $generated ) : ?>
            <p style="margin-top:20px; color:#666; font-style:italic;">Select filters above and click Generate Report.</p>
            <?php else :

                $sql = "SELECT o.*, p.name AS parish_name
                        FROM {$wpdb->prefix}occipr_ocia o
                        LEFT JOIN {$wpdb->prefix}occipr_parishes p ON p.id = o.parish_id
                        $where
                        ORDER BY FIELD(o.status,'elect','catechumen','inquirer','completed','withdrawn'),
                                 o.last_name ASC, o.first_name ASC";
                $records = $args
                    ? $wpdb->get_results( $wpdb->prepare( $sql, ...$args ) )
                    : $wpdb->get_results( $sql );

                if ( ! $records ) : ?>
                <p style="margin-top:20px;">No OCIA records found for the selected filters.</p>
                <?php else :

                    // Status counts across the result set
                    $status_counts = [];
                    foreach ( $records as $r ) {
                        $status_counts[ $r->status ] = ( $status_counts[ $r->status ] ?? 0 ) + 1;
                    }
                    $active_total = ( $status_counts['inquirer'] ?? 0 )
                                  + ( $status_counts['catechumen'] ?? 0 )
                                  + ( $status_counts['elect'] ?? 0 );

                    // Cohort grouping (by enrollment year)
                    $cohorts = [];
                    foreach ( $records as $r ) {
                        $yr = $r->enrollment_date ? date( 'Y', strtotime( $r->enrollment_date ) ) : 'Unknown';
                        if ( ! isset( $cohorts[ $yr ] ) ) { $cohorts[ $yr ] = []; }
                        $cohorts[ $yr ][] = $r;
                    }
                    krsort( $cohorts );

                    // Completed candidates (for separate list)
                    $completions = array_values( array_filter( $records, fn( $r ) => $r->status === 'completed' ) );

                    // Report title
                    $parish_name = '';
                    if ( $parish_filter ) {
                        foreach ( $parishes as $p ) {
                            if ( $p->id == $parish_filter ) { $parish_name = $p->name; break; }
                        }
                    }
                ?>
                <!-- Print button -->
                <div style="margin-top:16px; margin-bottom:8px;">
                    <button class="button button-secondary" onclick="window.print()">&#128438; Print Report</button>
                </div>

                <div class="occi-report-output">
                    <div class="occi-report-header">
                        <h2>OCIA Report</h2>
                        <?php if ( $parish_name ) : ?><p><?php echo esc_html( $parish_name ); ?></p><?php endif; ?>
                        <?php if ( $year_filter ) : ?>
                        <p class="occi-report-period">Cohort Year: <?php echo esc_html( $year_filter ); ?></p>
                        <?php endif; ?>
                        <?php if ( $status_filter ) : ?>
                        <p class="occi-report-period">Status: <?php echo esc_html( $statuses[ $status_filter ] ?? $status_filter ); ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Summary bar -->
                    <div class="occi-attendance-summary occi-report-summary">
                        <div class="occi-att-stat">
                            <span class="occi-att-num"><?php echo count( $records ); ?></span>
                            <span class="occi-att-lbl">Total Records</span>
                        </div>
                        <div class="occi-att-stat">
                            <span class="occi-att-num"><?php echo $active_total; ?></span>
                            <span class="occi-att-lbl">Currently Active</span>
                        </div>
                        <div class="occi-att-stat">
                            <span class="occi-att-num"><?php echo $status_counts['completed'] ?? 0; ?></span>
                            <span class="occi-att-lbl">Completed</span>
                        </div>
                        <div class="occi-att-stat">
                            <span class="occi-att-num"><?php echo $status_counts['withdrawn'] ?? 0; ?></span>
                            <span class="occi-att-lbl">Withdrawn</span>
                        </div>
                    </div>

                    <!-- Active Roster -->
                    <h3 class="occi-report-section-title">Active Roster</h3>
                    <?php
                    $active_records = array_values( array_filter( $records,
                        fn( $r ) => in_array( $r->status, ['inquirer','catechumen','elect'], true ) ) );
                    if ( $active_records ) : ?>
                    <table class="widefat striped occi-report-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Status</th>
                                <th>Inquiry Date</th>
                                <th>Enrolled</th>
                                <th>Sponsor</th>
                                <th>Catechist</th>
                                <?php if ( ! $parish_filter && count( $parishes ) > 1 ) : ?><th>Parish</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $active_records as $r ) : ?>
                            <tr>
                                <td><strong><?php echo esc_html( $r->last_name . ', ' . ( $r->preferred_name ?: $r->first_name ) ); ?></strong></td>
                                <td><?php echo OCCIPR_OCIA::status_badge( $r->status ); ?></td>
                                <td><?php echo $r->inquiry_date    ? esc_html( occipr_format_date( $r->inquiry_date ) )    : '--'; ?></td>
                                <td><?php echo $r->enrollment_date ? esc_html( occipr_format_date( $r->enrollment_date ) ) : '--'; ?></td>
                                <td class="occi-small"><?php echo esc_html( $r->sponsor_name ?? '' ); ?></td>
                                <td class="occi-small"><?php echo esc_html( $r->catechist ?? '' ); ?></td>
                                <?php if ( ! $parish_filter && count( $parishes ) > 1 ) : ?>
                                <td class="occi-small"><?php echo esc_html( $r->parish_name ?? '' ); ?></td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else : ?>
                    <p style="color:#888; font-style:italic;">No active candidates in the selected filters.</p>
                    <?php endif; ?>

                    <!-- Cohort breakdown -->
                    <?php if ( ! $year_filter ) : ?>
                    <h3 class="occi-report-section-title">Enrollment by Cohort Year</h3>
                    <table class="widefat striped occi-report-table">
                        <thead>
                            <tr>
                                <th>Year</th>
                                <th class="num">Enrolled</th>
                                <th class="num">Completed</th>
                                <th class="num">Withdrawn</th>
                                <th class="num">Active</th>
                                <th class="num">Completion Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $cohorts as $yr => $cohort ) :
                                $c_completed = count( array_filter( $cohort, fn( $r ) => $r->status === 'completed' ) );
                                $c_withdrawn = count( array_filter( $cohort, fn( $r ) => $r->status === 'withdrawn' ) );
                                $c_active    = count( array_filter( $cohort, fn( $r ) => in_array( $r->status, ['inquirer','catechumen','elect'], true ) ) );
                                $c_total     = count( $cohort );
                                $rate        = $c_total ? round( $c_completed / $c_total * 100 ) . '%' : '--';
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html( $yr ); ?></strong></td>
                                <td class="num"><?php echo $c_total; ?></td>
                                <td class="num"><?php echo $c_completed; ?></td>
                                <td class="num"><?php echo $c_withdrawn; ?></td>
                                <td class="num"><?php echo $c_active; ?></td>
                                <td class="num"><?php echo $rate; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="occi-report-total-row">
                                <th>Total</th>
                                <th class="num"><?php echo count( $records ); ?></th>
                                <th class="num"><?php echo $status_counts['completed'] ?? 0; ?></th>
                                <th class="num"><?php echo $status_counts['withdrawn'] ?? 0; ?></th>
                                <th class="num"><?php echo $active_total; ?></th>
                                <th class="num"><?php
                                    $tot = count( $records );
                                    echo $tot ? round( ( $status_counts['completed'] ?? 0 ) / $tot * 100 ) . '%' : '--';
                                ?></th>
                            </tr>
                        </tfoot>
                    </table>
                    <?php endif; ?>

                    <!-- Completions list -->
                    <?php if ( $completions ) : ?>
                    <h3 class="occi-report-section-title">Completed Candidates</h3>
                    <table class="widefat striped occi-report-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Enrolled</th>
                                <th>Completed</th>
                                <th>Sponsor</th>
                                <th>Presider</th>
                                <?php if ( ! $parish_filter && count( $parishes ) > 1 ) : ?><th>Parish</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $completions as $r ) : ?>
                            <tr>
                                <td><strong><?php echo esc_html( $r->last_name . ', ' . ( $r->preferred_name ?: $r->first_name ) ); ?></strong></td>
                                <td><?php echo $r->enrollment_date ? esc_html( occipr_format_date( $r->enrollment_date ) ) : '--'; ?></td>
                                <td><?php echo $r->completion_date ? esc_html( occipr_format_date( $r->completion_date ) ) : '--'; ?></td>
                                <td class="occi-small"><?php echo esc_html( $r->sponsor_name ?? '' ); ?></td>
                                <td class="occi-small"><?php echo esc_html( $r->presider ?? '' ); ?></td>
                                <?php if ( ! $parish_filter && count( $parishes ) > 1 ) : ?>
                                <td class="occi-small"><?php echo esc_html( $r->parish_name ?? '' ); ?></td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>

                    <p class="occi-report-generated">Report generated <?php echo date( 'F j, Y \a\t g:i A' ); ?></p>
                </div><!-- .occi-report-output -->

                <?php endif; // records
            endif; // generated ?>
        </div>
        <?php
    }
}
