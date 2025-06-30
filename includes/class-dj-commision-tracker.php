<?php
/**
 * DJ Commission Tracker Class
 * Manages commission calculations, payments, and reporting
 */

class DJ_Commission_Tracker {
    
    private $agency_commission_rate = 0.25; // 25% default
    
    public function __construct() {
        $this->agency_commission_rate = floatval(get_option('musicandlights_agency_commission', '25')) / 100;
        
        add_action('init', array($this, 'init'));
        add_action('dj_booking_confirmed', array($this, 'calculate_commission'), 10, 1);
        add_action('dj_payment_received', array($this, 'update_commission_status'), 10, 2);
        add_action('wp_ajax_process_dj_payment', array($this, 'process_dj_payment'));
        add_action('wp_ajax_generate_commission_report', array($this, 'generate_commission_report'));
        add_action('wp_ajax_export_commission_data', array($this, 'export_commission_data'));
        
        // Schedule monthly commission calculations
        if (!wp_next_scheduled('calculate_monthly_commissions')) {
            wp_schedule_event(time(), 'monthly', 'calculate_monthly_commissions');
        }
        add_action('calculate_monthly_commissions', array($this, 'calculate_monthly_commissions'));
    }
    
    public function init() {
        // Add commission-related user capabilities
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap('view_dj_commissions');
            $admin_role->add_cap('process_dj_payments');
            $admin_role->add_cap('export_commission_data');
        }
    }
    
    /**
     * Calculate commission when booking is confirmed
     */
    public function calculate_commission($booking_id) {
        $booking_data = $this->get_booking_financial_data($booking_id);
        
        if (!$booking_data || empty($booking_data['assigned_dj'])) {
            return false;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_commissions';
        
        // Check if commission already exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE booking_id = %d",
            $booking_id
        ));
        
        if ($existing) {
            // Update existing commission
            return $this->update_commission($existing->id, $booking_data);
        }
        
        // Calculate commission amounts
        $total_amount = floatval($booking_data['total_amount']);
        $agency_commission = $total_amount * $this->agency_commission_rate;
        $dj_earnings = $total_amount - $agency_commission;
        
        // Insert new commission record
        $result = $wpdb->insert(
            $table_name,
            array(
                'booking_id' => $booking_id,
                'dj_id' => $booking_data['assigned_dj'],
                'total_amount' => $total_amount,
                'agency_commission' => $agency_commission,
                'dj_earnings' => $dj_earnings,
                'status' => 'pending',
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%f', '%f', '%f', '%s', '%s')
        );
        
        if ($result) {
            // Update booking meta with commission info
            update_post_meta($booking_id, 'commission_amount', $agency_commission);
            update_post_meta($booking_id, 'dj_earnings', $dj_earnings);
            
            // Send notification to GoHighLevel
            $this->notify_ghl_commission_created($booking_id, $agency_commission, $dj_earnings);
            
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Update commission status when payment is received
     */
    public function update_commission_status($booking_id, $payment_type) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_commissions';
        
        $commission = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE booking_id = %d",
            $booking_id
        ));
        
        if (!$commission) {
            return false;
        }
        
        $update_data = array();
        
        switch ($payment_type) {
            case 'deposit':
                // Commission becomes earned when deposit is paid
                if ($commission->status === 'pending') {
                    $update_data = array(
                        'status' => 'earned',
                        'earned_date' => current_time('mysql')
                    );
                }
                break;
                
            case 'final':
                // Update to completed when final payment received
                if ($commission->status === 'earned') {
                    $update_data = array(
                        'status' => 'completed'
                    );
                }
                break;
        }
        
        if (!empty($update_data)) {
            $wpdb->update(
                $table_name,
                $update_data,
                array('id' => $commission->id),
                array('%s', '%s'),
                array('%d')
            );
            
            // Notify DJ of commission status update
            $this->notify_dj_commission_update($commission->dj_id, $commission->booking_id, $update_data['status']);
        }
        
        return true;
    }
    
    /**
     * Process payment to DJ
     */
    public function process_dj_payment() {
        check_ajax_referer('dj_hire_nonce', 'nonce');
        
        if (!current_user_can('process_dj_payments')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $commission_ids = array_map('intval', $_POST['commission_ids'] ?? array());
        $payment_method = sanitize_text_field($_POST['payment_method']);
        $payment_reference = sanitize_text_field($_POST['payment_reference']);
        $payment_notes = sanitize_textarea_field($_POST['payment_notes'] ?? '');
        
        if (empty($commission_ids)) {
            wp_send_json_error('No commissions selected');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_commissions';
        
        $processed_count = 0;
        $total_paid = 0;
        $dj_payments = array(); // Group by DJ
        
        foreach ($commission_ids as $commission_id) {
            $commission = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d AND status IN ('earned', 'completed')",
                $commission_id
            ));
            
            if (!$commission) continue;
            
            // Update commission record
            $result = $wpdb->update(
                $table_name,
                array(
                    'status' => 'paid',
                    'paid_date' => current_time('mysql'),
                    'payment_method' => $payment_method,
                    'payment_reference' => $payment_reference,
                    'payment_notes' => $payment_notes
                ),
                array('id' => $commission_id),
                array('%s', '%s', '%s', '%s', '%s'),
                array('%d')
            );
            
            if ($result) {
                $processed_count++;
                $total_paid += $commission->dj_earnings;
                
                // Group payments by DJ
                if (!isset($dj_payments[$commission->dj_id])) {
                    $dj_payments[$commission->dj_id] = array(
                        'total' => 0,
                        'bookings' => array()
                    );
                }
                
                $dj_payments[$commission->dj_id]['total'] += $commission->dj_earnings;
                $dj_payments[$commission->dj_id]['bookings'][] = $commission->booking_id;
            }
        }
        
        // Send payment notifications to DJs
        foreach ($dj_payments as $dj_id => $payment_info) {
            $this->send_payment_notification($dj_id, $payment_info['total'], $payment_info['bookings'], $payment_method, $payment_reference);
        }
        
        // Log payment batch
        $this->log_payment_batch($commission_ids, $total_paid, $payment_method, $payment_reference);
        
        wp_send_json_success(array(
            'processed' => $processed_count,
            'total_paid' => $total_paid,
            'message' => sprintf(__('Processed %d payments totaling £%s', 'musicandlights'), $processed_count, number_format($total_paid, 2))
        ));
    }
    
    /**
     * Generate commission report
     */
    public function generate_commission_report() {
        check_ajax_referer('dj_hire_nonce', 'nonce');
        
        if (!current_user_can('view_dj_commissions')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);
        $dj_id = intval($_POST['dj_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? 'all');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_commissions';
        
        // Build query
        $where_conditions = array();
        $where_conditions[] = $wpdb->prepare('c.created_at BETWEEN %s AND %s', $start_date . ' 00:00:00', $end_date . ' 23:59:59');
        
        if ($dj_id > 0) {
            $where_conditions[] = $wpdb->prepare('c.dj_id = %d', $dj_id);
        }
        
        if ($status !== 'all') {
            $where_conditions[] = $wpdb->prepare('c.status = %s', $status);
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Get commission data
        $commissions = $wpdb->get_results("
            SELECT c.*, 
                   p.post_title as dj_name,
                   pm1.meta_value as client_name,
                   pm2.meta_value as event_date,
                   pm3.meta_value as event_type
            FROM $table_name c
            LEFT JOIN {$wpdb->posts} p ON c.dj_id = p.ID
            LEFT JOIN {$wpdb->postmeta} pm1 ON c.booking_id = pm1.post_id AND pm1.meta_key = 'client_name'
            LEFT JOIN {$wpdb->postmeta} pm2 ON c.booking_id = pm2.post_id AND pm2.meta_key = 'event_date'
            LEFT JOIN {$wpdb->postmeta} pm3 ON c.booking_id = pm3.post_id AND pm3.meta_key = 'event_type'
            WHERE $where_clause
            ORDER BY c.created_at DESC
        ");
        
        // Calculate summary statistics
        $summary = $wpdb->get_row("
            SELECT 
                COUNT(*) as total_bookings,
                SUM(total_amount) as total_revenue,
                SUM(agency_commission) as total_commission,
                SUM(dj_earnings) as total_dj_earnings,
                SUM(CASE WHEN status = 'paid' THEN dj_earnings ELSE 0 END) as paid_to_djs,
                SUM(CASE WHEN status IN ('earned', 'completed') THEN dj_earnings ELSE 0 END) as pending_payments
            FROM $table_name c
            WHERE $where_clause
        ");
        
        // Group by DJ for DJ summary
        $dj_summary = $wpdb->get_results("
            SELECT 
                c.dj_id,
                p.post_title as dj_name,
                COUNT(*) as booking_count,
                SUM(c.total_amount) as total_revenue,
                SUM(c.dj_earnings) as total_earnings,
                SUM(CASE WHEN c.status = 'paid' THEN c.dj_earnings ELSE 0 END) as paid_earnings,
                SUM(CASE WHEN c.status IN ('earned', 'completed') THEN c.dj_earnings ELSE 0 END) as pending_earnings
            FROM $table_name c
            LEFT JOIN {$wpdb->posts} p ON c.dj_id = p.ID
            WHERE $where_clause
            GROUP BY c.dj_id
            ORDER BY total_earnings DESC
        ");
        
        wp_send_json_success(array(
            'commissions' => $commissions,
            'summary' => $summary,
            'dj_summary' => $dj_summary,
            'report_period' => array(
                'start' => $start_date,
                'end' => $end_date
            )
        ));
    }
    
    /**
     * Export commission data
     */
    public function export_commission_data() {
        check_ajax_referer('dj_hire_nonce', 'nonce');
        
        if (!current_user_can('export_commission_data')) {
            wp_die('Insufficient permissions');
        }
        
        $format = sanitize_text_field($_POST['format'] ?? 'csv');
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_commissions';
        
        $data = $wpdb->get_results($wpdb->prepare("
            SELECT 
                c.*,
                p.post_title as dj_name,
                pm1.meta_value as client_name,
                pm2.meta_value as event_date,
                pm3.meta_value as venue_name
            FROM $table_name c
            LEFT JOIN {$wpdb->posts} p ON c.dj_id = p.ID
            LEFT JOIN {$wpdb->postmeta} pm1 ON c.booking_id = pm1.post_id AND pm1.meta_key = 'client_name'
            LEFT JOIN {$wpdb->postmeta} pm2 ON c.booking_id = pm2.post_id AND pm2.meta_key = 'event_date'
            LEFT JOIN {$wpdb->postmeta} pm3 ON c.booking_id = pm3.post_id AND pm3.meta_key = 'venue_name'
            WHERE c.created_at BETWEEN %s AND %s
            ORDER BY c.created_at DESC
        ", $start_date . ' 00:00:00', $end_date . ' 23:59:59'), ARRAY_A);
        
        if ($format === 'csv') {
            $this->export_as_csv($data, 'commissions_' . $start_date . '_to_' . $end_date . '.csv');
        } else {
            $this->export_as_pdf($data, $start_date, $end_date);
        }
    }
    
    private function export_as_csv($data, $filename) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Headers
        $headers = array(
            'Booking ID',
            'DJ Name',
            'Client Name',
            'Event Date',
            'Venue',
            'Total Amount',
            'Agency Commission',
            'DJ Earnings',
            'Status',
            'Earned Date',
            'Paid Date',
            'Payment Method',
            'Payment Reference'
        );
        
        fputcsv($output, $headers);
        
        // Data rows
        foreach ($data as $row) {
            fputcsv($output, array(
                $row['booking_id'],
                $row['dj_name'],
                $row['client_name'],
                $row['event_date'],
                $row['venue_name'],
                $row['total_amount'],
                $row['agency_commission'],
                $row['dj_earnings'],
                $row['status'],
                $row['earned_date'],
                $row['paid_date'],
                $row['payment_method'],
                $row['payment_reference']
            ));
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Calculate monthly commissions
     */
    public function calculate_monthly_commissions() {
        global $wpdb;
        
        // Get completed events from last month
        $last_month_start = date('Y-m-01', strtotime('-1 month'));
        $last_month_end = date('Y-m-t', strtotime('-1 month'));
        
        $bookings = $wpdb->get_results($wpdb->prepare("
            SELECT p.ID, pm1.meta_value as event_date
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'event_date'
            WHERE p.post_type = 'dj_booking'
            AND p.post_status = 'completed'
            AND pm1.meta_value BETWEEN %s AND %s
        ", $last_month_start, $last_month_end));
        
        foreach ($bookings as $booking) {
            // Ensure commission is calculated and marked as earned
            $this->calculate_commission($booking->ID);
            $this->mark_commission_as_earned($booking->ID);
        }
        
        // Generate monthly summary report
        $this->generate_monthly_summary($last_month_start, $last_month_end);
    }
    
    private function mark_commission_as_earned($booking_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_commissions';
        
        $wpdb->update(
            $table_name,
            array(
                'status' => 'earned',
                'earned_date' => current_time('mysql')
            ),
            array(
                'booking_id' => $booking_id,
                'status' => 'completed'
            ),
            array('%s', '%s'),
            array('%d', '%s')
        );
    }
    
    /**
     * Get commission summary for a DJ
     */
    public function get_dj_commission_summary($dj_id, $period = 'all') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_commissions';
        
        $where_clause = $wpdb->prepare('dj_id = %d', $dj_id);
        
        switch ($period) {
            case 'this_month':
                $where_clause .= $wpdb->prepare(' AND created_at >= %s', date('Y-m-01'));
                break;
            case 'last_month':
                $where_clause .= $wpdb->prepare(' AND created_at BETWEEN %s AND %s', 
                    date('Y-m-01', strtotime('-1 month')), 
                    date('Y-m-t', strtotime('-1 month'))
                );
                break;
            case 'this_year':
                $where_clause .= $wpdb->prepare(' AND created_at >= %s', date('Y-01-01'));
                break;
        }
        
        return $wpdb->get_row("
            SELECT 
                COUNT(*) as total_bookings,
                SUM(total_amount) as total_revenue,
                SUM(dj_earnings) as total_earnings,
                SUM(CASE WHEN status = 'paid' THEN dj_earnings ELSE 0 END) as paid_earnings,
                SUM(CASE WHEN status IN ('earned', 'completed') THEN dj_earnings ELSE 0 END) as pending_earnings,
                AVG(dj_earnings) as average_earning_per_booking
            FROM $table_name
            WHERE $where_clause
        ");
    }
    
    /**
     * Get pending payments for all DJs
     */
    public function get_pending_payments() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_commissions';
        
        return $wpdb->get_results("
            SELECT 
                c.dj_id,
                p.post_title as dj_name,
                COUNT(*) as pending_count,
                SUM(c.dj_earnings) as pending_amount,
                MIN(c.earned_date) as oldest_payment_date
            FROM $table_name c
            LEFT JOIN {$wpdb->posts} p ON c.dj_id = p.ID
            WHERE c.status IN ('earned', 'completed')
            GROUP BY c.dj_id
            HAVING pending_amount > 0
            ORDER BY pending_amount DESC
        ");
    }
    
    /**
     * Helper functions
     */
    private function get_booking_financial_data($booking_id) {
        return array(
            'assigned_dj' => get_post_meta($booking_id, 'assigned_dj', true),
            'total_amount' => get_post_meta($booking_id, 'total_amount', true),
            'event_date' => get_post_meta($booking_id, 'event_date', true),
            'client_name' => get_post_meta($booking_id, 'client_name', true)
        );
    }
    
    private function update_commission($commission_id, $booking_data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_commissions';
        
        $total_amount = floatval($booking_data['total_amount']);
        $agency_commission = $total_amount * $this->agency_commission_rate;
        $dj_earnings = $total_amount - $agency_commission;
        
        return $wpdb->update(
            $table_name,
            array(
                'total_amount' => $total_amount,
                'agency_commission' => $agency_commission,
                'dj_earnings' => $dj_earnings
            ),
            array('id' => $commission_id),
            array('%f', '%f', '%f'),
            array('%d')
        );
    }
    
    private function notify_ghl_commission_created($booking_id, $agency_commission, $dj_earnings) {
        if (!class_exists('GHL_Integration')) return;
        
        $ghl_contact_id = get_post_meta($booking_id, 'ghl_contact_id', true);
        if (!$ghl_contact_id) return;
        
        $ghl_integration = new GHL_Integration();
        $ghl_integration->update_contact_custom_fields($ghl_contact_id, array(
            'agency_commission' => $agency_commission,
            'dj_earnings' => $dj_earnings,
            'commission_status' => 'calculated'
        ));
    }
    
    private function notify_dj_commission_update($dj_id, $booking_id, $status) {
        $dj_user_id = get_post_meta($dj_id, 'dj_user_id', true);
        if (!$dj_user_id) return;
        
        $user = get_user_by('ID', $dj_user_id);
        if (!$user) return;
        
        $booking_details = $this->get_booking_financial_data($booking_id);
        
        $subject = sprintf(__('Commission Update - Booking #%d', 'musicandlights'), $booking_id);
        $message = sprintf(
            __("Hello %s,\n\nYour commission status has been updated for the event on %s.\n\nClient: %s\nStatus: %s\n\nPlease log in to your dashboard for more details.\n\nBest regards,\n%s", 'musicandlights'),
            $user->display_name,
            $booking_details['event_date'],
            $booking_details['client_name'],
            ucfirst($status),
            get_bloginfo('name')
        );
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    private function send_payment_notification($dj_id, $amount, $booking_ids, $payment_method, $reference) {
        $dj_user_id = get_post_meta($dj_id, 'dj_user_id', true);
        if (!$dj_user_id) return;
        
        $user = get_user_by('ID', $dj_user_id);
        if (!$user) return;
        
        $subject = sprintf(__('Payment Processed - £%s', 'musicandlights'), number_format($amount, 2));
        
        $booking_list = '';
        foreach ($booking_ids as $booking_id) {
            $event_date = get_post_meta($booking_id, 'event_date', true);
            $client_name = get_post_meta($booking_id, 'client_name', true);
            $booking_list .= sprintf("- %s: %s (Booking #%d)\n", $event_date, $client_name, $booking_id);
        }
        
        $message = sprintf(
            __("Hello %s,\n\nWe've processed a payment of £%s to you.\n\nPayment Method: %s\nReference: %s\n\nBookings included:\n%s\n\nThe payment should reach your account within 2-3 business days.\n\nBest regards,\n%s", 'musicandlights'),
            $user->display_name,
            number_format($amount, 2),
            ucwords(str_replace('_', ' ', $payment_method)),
            $reference,
            $booking_list,
            get_bloginfo('name')
        );
        
        wp_mail($user->user_email, $subject, $message);
    }
    
    private function log_payment_batch($commission_ids, $total_amount, $payment_method, $reference) {
        $log_entry = array(
            'date' => current_time('mysql'),
            'commission_ids' => $commission_ids,
            'total_amount' => $total_amount,
            'payment_method' => $payment_method,
            'reference' => $reference,
            'processed_by' => get_current_user_id()
        );
        
        $payment_log = get_option('musicandlights_payment_log', array());
        $payment_log[] = $log_entry;
        
        // Keep only last 100 entries
        if (count($payment_log) > 100) {
            $payment_log = array_slice($payment_log, -100);
        }
        
        update_option('musicandlights_payment_log', $payment_log);
    }
    
    private function generate_monthly_summary($start_date, $end_date) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_commissions';
        
        $summary = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_bookings,
                SUM(total_amount) as total_revenue,
                SUM(agency_commission) as total_commission,
                SUM(dj_earnings) as total_dj_earnings
            FROM $table_name
            WHERE created_at BETWEEN %s AND %s
        ", $start_date . ' 00:00:00', $end_date . ' 23:59:59'));
        
        // Store monthly summary
        $monthly_summaries = get_option('musicandlights_monthly_summaries', array());
        $monthly_summaries[date('Y-m', strtotime($start_date))] = array(
            'bookings' => $summary->total_bookings,
            'revenue' => $summary->total_revenue,
            'commission' => $summary->total_commission,
            'dj_earnings' => $summary->total_dj_earnings,
            'generated_date' => current_time('mysql')
        );
        
        update_option('musicandlights_monthly_summaries', $monthly_summaries);
        
        // Send summary to admin
        $this->send_monthly_summary_email($summary, $start_date, $end_date);
    }
    
    private function send_monthly_summary_email($summary, $start_date, $end_date) {
        $admin_email = get_option('admin_email');
        
        $subject = sprintf(
            __('Monthly Commission Summary - %s', 'musicandlights'),
            date('F Y', strtotime($start_date))
        );
        
        $message = sprintf(
            __("Monthly Commission Summary for %s\n\nTotal Bookings: %d\nTotal Revenue: £%s\nAgency Commission: £%s\nDJ Earnings: £%s\n\nPlease log in to the admin dashboard to process pending payments.\n\nBest regards,\n%s", 'musicandlights'),
            date('F Y', strtotime($start_date)),
            $summary->total_bookings,
            number_format($summary->total_revenue, 2),
            number_format($summary->total_commission, 2),
            number_format($summary->total_dj_earnings, 2),
            get_bloginfo('name')
        );
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Get commission breakdown for invoice generation
     */
    public function get_commission_breakdown($booking_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_commissions';
        
        $commission = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE booking_id = %d",
            $booking_id
        ));
        
        if (!$commission) {
            return false;
        }
        
        return array(
            'subtotal' => $commission->total_amount,
            'agency_commission' => array(
                'rate' => $this->agency_commission_rate * 100,
                'amount' => $commission->agency_commission
            ),
            'dj_earnings' => $commission->dj_earnings,
            'vat' => 0, // Can be expanded for VAT calculations
            'total' => $commission->total_amount
        );
    }
}
?>