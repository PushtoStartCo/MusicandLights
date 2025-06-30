<?php
/**
 * Commission tracking class for Music & Lights plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class ML_Commission {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        // AJAX handlers
        add_action('wp_ajax_ml_mark_commission_paid', array($this, 'ajax_mark_paid'));
        add_action('wp_ajax_ml_dispute_commission', array($this, 'ajax_dispute_commission'));
        add_action('wp_ajax_ml_get_commission_report', array($this, 'ajax_get_report'));
        add_action('wp_ajax_ml_export_commission_data', array($this, 'ajax_export_data'));
    }
    
    /**
     * Get commission records
     */
    public function get_commissions($filters = array()) {
        global $wpdb;
        
        $table_name = ML_Database::get_table_name('commissions');
        $booking_table = ML_Database::get_table_name('bookings');
        $dj_table = ML_Database::get_table_name('djs');
        
        $where_clauses = array('1=1');
        $params = array();
        
        // Apply filters
        if (!empty($filters['dj_id'])) {
            $where_clauses[] = 'c.dj_id = %d';
            $params[] = intval($filters['dj_id']);
        }
        
        if (!empty($filters['status'])) {
            $where_clauses[] = 'c.status = %s';
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['date_from'])) {
            $where_clauses[] = 'b.event_date >= %s';
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_clauses[] = 'b.event_date <= %s';
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['payment_date_from'])) {
            $where_clauses[] = 'c.paid_date >= %s';
            $params[] = $filters['payment_date_from'];
        }
        
        if (!empty($filters['payment_date_to'])) {
            $where_clauses[] = 'c.paid_date <= %s';
            $params[] = $filters['payment_date_to'];
        }
        
        $where_clause = implode(' AND ', $where_clauses);
        
        $limit = isset($filters['limit']) ? intval($filters['limit']) : 50;
        $offset = isset($filters['offset']) ? intval($filters['offset']) : 0;
        
        $order_by = isset($filters['order_by']) ? sanitize_sql_orderby($filters['order_by']) : 'c.created_at DESC';
        
        $query = "
            SELECT 
                c.*,
                b.booking_reference,
                b.client_name,
                b.event_date,
                b.event_type,
                b.total_price as booking_total_price,
                d.stage_name,
                d.real_name,
                d.email as dj_email
            FROM $table_name c
            LEFT JOIN $booking_table b ON c.booking_id = b.id
            LEFT JOIN $dj_table d ON c.dj_id = d.id
            WHERE $where_clause
            ORDER BY $order_by
            LIMIT %d OFFSET %d
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        
        return $wpdb->get_results($wpdb->prepare($query, $params));
    }
    
    /**
     * Get commission statistics
     */
    public function get_commission_stats($filters = array()) {
        global $wpdb;
        
        $table_name = ML_Database::get_table_name('commissions');
        $booking_table = ML_Database::get_table_name('bookings');
        
        $where_clauses = array('1=1');
        $params = array();
        
        // Apply same filters as get_commissions
        if (!empty($filters['dj_id'])) {
            $where_clauses[] = 'c.dj_id = %d';
            $params[] = intval($filters['dj_id']);
        }
        
        if (!empty($filters['date_from'])) {
            $where_clauses[] = 'b.event_date >= %s';
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_clauses[] = 'b.event_date <= %s';
            $params[] = $filters['date_to'];
        }
        
        $where_clause = implode(' AND ', $where_clauses);
        
        $stats = array();
        
        // Total commission amount
        $query = "
            SELECT SUM(c.commission_amount) 
            FROM $table_name c
            LEFT JOIN $booking_table b ON c.booking_id = b.id
            WHERE $where_clause
        ";
        $stats['total_commission'] = $wpdb->get_var($wpdb->prepare($query, $params)) ?: 0;
        
        // Paid commission amount
        $paid_where = $where_clause . " AND c.status = 'paid'";
        $query = "
            SELECT SUM(c.commission_amount) 
            FROM $table_name c
            LEFT JOIN $booking_table b ON c.booking_id = b.id
            WHERE $paid_where
        ";
        $stats['paid_commission'] = $wpdb->get_var($wpdb->prepare($query, $params)) ?: 0;
        
        // Pending commission amount
        $pending_where = $where_clause . " AND c.status = 'pending'";
        $query = "
            SELECT SUM(c.commission_amount) 
            FROM $table_name c
            LEFT JOIN $booking_table b ON c.booking_id = b.id
            WHERE $pending_where
        ";
        $stats['pending_commission'] = $wpdb->get_var($wpdb->prepare($query, $params)) ?: 0;
        
        // Disputed commission amount
        $disputed_where = $where_clause . " AND c.status = 'disputed'";
        $query = "
            SELECT SUM(c.commission_amount) 
            FROM $table_name c
            LEFT JOIN $booking_table b ON c.booking_id = b.id
            WHERE $disputed_where
        ";
        $stats['disputed_commission'] = $wpdb->get_var($wpdb->prepare($query, $params)) ?: 0;
        
        // Commission by status
        $query = "
            SELECT c.status, COUNT(*) as count, SUM(c.commission_amount) as amount
            FROM $table_name c
            LEFT JOIN $booking_table b ON c.booking_id = b.id
            WHERE $where_clause
            GROUP BY c.status
        ";
        $status_breakdown = $wpdb->get_results($wpdb->prepare($query, $params));
        
        $stats['status_breakdown'] = array();
        foreach ($status_breakdown as $row) {
            $stats['status_breakdown'][$row->status] = array(
                'count' => $row->count,
                'amount' => $row->amount
            );
        }
        
        return $stats;
    }
    
    /**
     * Mark commission as paid
     */
    public function mark_commission_paid($commission_id, $payment_data) {
        global $wpdb;
        
        $required_fields = array('payment_method', 'payment_reference');
        
        foreach ($required_fields as $field) {
            if (empty($payment_data[$field])) {
                return new WP_Error('missing_field', "Required field missing: $field");
            }
        }
        
        $update_data = array(
            'status' => 'paid',
            'paid_date' => current_time('mysql'),
            'payment_method' => sanitize_text_field($payment_data['payment_method']),
            'payment_reference' => sanitize_text_field($payment_data['payment_reference']),
            'notes' => sanitize_textarea_field($payment_data['notes'] ?? ''),
            'updated_at' => current_time('mysql')
        );
        
        $table_name = ML_Database::get_table_name('commissions');
        $result = $wpdb->update(
            $table_name,
            $update_data,
            array('id' => $commission_id)
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to update commission status');
        }
        
        // Send payment notification to DJ
        $commission = $this->get_commission_by_id($commission_id);
        if ($commission) {
            $this->send_payment_notification($commission);
        }
        
        return true;
    }
    
    /**
     * Mark commission as disputed
     */
    public function dispute_commission($commission_id, $dispute_reason) {
        global $wpdb;
        
        $update_data = array(
            'status' => 'disputed',
            'notes' => sanitize_textarea_field($dispute_reason),
            'updated_at' => current_time('mysql')
        );
        
        $table_name = ML_Database::get_table_name('commissions');
        $result = $wpdb->update(
            $table_name,
            $update_data,
            array('id' => $commission_id)
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to dispute commission');
        }
        
        // Send dispute notification to admin
        $commission = $this->get_commission_by_id($commission_id);
        if ($commission) {
            $this->send_dispute_notification($commission);
        }
        
        return true;
    }
    
    /**
     * Get commission by ID
     */
    public function get_commission_by_id($commission_id) {
        global $wpdb;
        
        $table_name = ML_Database::get_table_name('commissions');
        $booking_table = ML_Database::get_table_name('bookings');
        $dj_table = ML_Database::get_table_name('djs');
        
        $query = "
            SELECT 
                c.*,
                b.booking_reference,
                b.client_name,
                b.event_date,
                b.event_type,
                d.stage_name,
                d.real_name,
                d.email as dj_email
            FROM $table_name c
            LEFT JOIN $booking_table b ON c.booking_id = b.id
            LEFT JOIN $dj_table d ON c.dj_id = d.id
            WHERE c.id = %d
        ";
        
        return $wpdb->get_row($wpdb->prepare($query, $commission_id));
    }
    
    /**
     * Get DJ commission summary
     */
    public function get_dj_commission_summary($dj_id, $date_from = null, $date_to = null) {
        global $wpdb;
        
        $table_name = ML_Database::get_table_name('commissions');
        $booking_table = ML_Database::get_table_name('bookings');
        
        $where_clauses = array('c.dj_id = %d');
        $params = array($dj_id);
        
        if ($date_from) {
            $where_clauses[] = 'b.event_date >= %s';
            $params[] = $date_from;
        }
        
        if ($date_to) {
            $where_clauses[] = 'b.event_date <= %s';
            $params[] = $date_to;
        }
        
        $where_clause = implode(' AND ', $where_clauses);
        
        $summary = array();
        
        // Total bookings value
        $query = "
            SELECT SUM(c.booking_total)
            FROM $table_name c
            LEFT JOIN $booking_table b ON c.booking_id = b.id
            WHERE $where_clause
        ";
        $summary['total_booking_value'] = $wpdb->get_var($wpdb->prepare($query, $params)) ?: 0;
        
        // Total commission owed
        $query = "
            SELECT SUM(c.commission_amount)
            FROM $table_name c
            LEFT JOIN $booking_table b ON c.booking_id = b.id
            WHERE $where_clause
        ";
        $summary['total_commission_owed'] = $wpdb->get_var($wpdb->prepare($query, $params)) ?: 0;
        
        // Paid commission
        $paid_where = $where_clause . " AND c.status = 'paid'";
        $query = "
            SELECT SUM(c.commission_amount)
            FROM $table_name c
            LEFT JOIN $booking_table b ON c.booking_id = b.id
            WHERE $paid_where
        ";
        $summary['commission_paid'] = $wpdb->get_var($wpdb->prepare($query, $params)) ?: 0;
        
        // Outstanding commission
        $summary['commission_outstanding'] = $summary['total_commission_owed'] - $summary['commission_paid'];
        
        // DJ earnings (booking total minus commission)
        $summary['dj_earnings'] = $summary['total_booking_value'] - $summary['total_commission_owed'];
        
        return $summary;
    }
    
    /**
     * Generate commission report
     */
    public function generate_commission_report($period = 'monthly', $date_from = null, $date_to = null) {
        if (!$date_from || !$date_to) {
            // Set default date range based on period
            switch ($period) {
                case 'weekly':
                    $date_from = date('Y-m-d', strtotime('-1 week'));
                    $date_to = date('Y-m-d');
                    break;
                case 'monthly':
                    $date_from = date('Y-m-01');
                    $date_to = date('Y-m-t');
                    break;
                case 'quarterly':
                    $quarter_start = date('Y-m-01', strtotime('first day of -2 month'));
                    $date_from = $quarter_start;
                    $date_to = date('Y-m-t');
                    break;
                case 'yearly':
                    $date_from = date('Y-01-01');
                    $date_to = date('Y-12-31');
                    break;
            }
        }
        
        $filters = array(
            'date_from' => $date_from,
            'date_to' => $date_to
        );
        
        $report = array(
            'period' => $period,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'generated_at' => current_time('mysql'),
            'stats' => $this->get_commission_stats($filters),
            'commissions' => $this->get_commissions($filters),
            'dj_summaries' => $this->get_all_dj_summaries($date_from, $date_to)
        );
        
        return $report;
    }
    
    /**
     * Get commission summaries for all DJs
     */
    private function get_all_dj_summaries($date_from, $date_to) {
        global $wpdb;
        
        $dj_table = ML_Database::get_table_name('djs');
        $active_djs = $wpdb->get_results("SELECT id, stage_name, real_name FROM $dj_table WHERE status = 'active'");
        
        $summaries = array();
        foreach ($active_djs as $dj) {
            $summaries[$dj->id] = array(
                'dj_info' => $dj,
                'summary' => $this->get_dj_commission_summary($dj->id, $date_from, $date_to)
            );
        }
        
        return $summaries;
    }
    
    /**
     * Send payment notification to DJ
     */
    private function send_payment_notification($commission) {
        $email_manager = ML_Email::get_instance();
        $email_manager->send_commission_payment_notification($commission);
    }
    
    /**
     * Send dispute notification to admin
     */
    private function send_dispute_notification($commission) {
        $email_manager = ML_Email::get_instance();
        $email_manager->send_commission_dispute_notification($commission);
    }
    
    /**
     * Weekly commission report (cron job)
     */
    public function weekly_report() {
        $report = $this->generate_commission_report('weekly');
        
        // Send report to admin
        $admin_email = get_option('admin_email');
        $subject = 'Weekly Commission Report - ' . get_option('ml_company_name', 'Music & Lights');
        
        $email_manager = ML_Email::get_instance();
        $email_manager->send_commission_report($admin_email, $subject, $report);
        
        // Log the report generation
        error_log('ML Plugin: Weekly commission report generated and sent');
    }
    
    /**
     * Export commission data to CSV
     */
    public function export_to_csv($filters = array()) {
        $commissions = $this->get_commissions($filters);
        
        $filename = 'commission_export_' . date('Y-m-d_H-i-s') . '.csv';
        $file_path = wp_upload_dir()['path'] . '/' . $filename;
        
        $fp = fopen($file_path, 'w');
        
        // CSV headers
        $headers = array(
            'Commission ID',
            'Booking Reference',
            'DJ Name',
            'Client Name',
            'Event Date',
            'Event Type',
            'Booking Total',
            'Commission Rate (%)',
            'Commission Amount',
            'Status',
            'Paid Date',
            'Payment Method',
            'Payment Reference',
            'Created Date'
        );
        
        fputcsv($fp, $headers);
        
        // CSV data
        foreach ($commissions as $commission) {
            $row = array(
                $commission->id,
                $commission->booking_reference,
                $commission->stage_name,
                $commission->client_name,
                $commission->event_date,
                $commission->event_type,
                number_format($commission->booking_total, 2),
                $commission->commission_rate,
                number_format($commission->commission_amount, 2),
                ucfirst($commission->status),
                $commission->paid_date,
                $commission->payment_method,
                $commission->payment_reference,
                $commission->created_at
            );
            
            fputcsv($fp, $row);
        }
        
        fclose($fp);
        
        return array(
            'filename' => $filename,
            'file_path' => $file_path,
            'download_url' => wp_upload_dir()['url'] . '/' . $filename
        );
    }
    
    /**
     * AJAX: Mark commission as paid
     */
    public function ajax_mark_paid() {
        check_ajax_referer('ml_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $commission_id = intval($_POST['commission_id']);
        $payment_data = $_POST['payment_data'];
        
        $result = $this->mark_commission_paid($commission_id, $payment_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success('Commission marked as paid successfully');
        }
    }
    
    /**
     * AJAX: Dispute commission
     */
    public function ajax_dispute_commission() {
        check_ajax_referer('ml_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $commission_id = intval($_POST['commission_id']);
        $dispute_reason = sanitize_textarea_field($_POST['dispute_reason']);
        
        $result = $this->dispute_commission($commission_id, $dispute_reason);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success('Commission disputed successfully');
        }
    }
    
    /**
     * AJAX: Get commission report
     */
    public function ajax_get_report() {
        check_ajax_referer('ml_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $period = sanitize_text_field($_POST['period'] ?? 'monthly');
        $date_from = sanitize_text_field($_POST['date_from'] ?? '');
        $date_to = sanitize_text_field($_POST['date_to'] ?? '');
        
        $report = $this->generate_commission_report($period, $date_from ?: null, $date_to ?: null);
        
        wp_send_json_success($report);
    }
    
    /**
     * AJAX: Export commission data
     */
    public function ajax_export_data() {
        check_ajax_referer('ml_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $filters = $_POST['filters'] ?? array();
        $export_result = $this->export_to_csv($filters);
        
        wp_send_json_success($export_result);
    }
}
?>