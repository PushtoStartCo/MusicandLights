<?php
/**
 * Booking management class for Music & Lights plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class ML_Booking {
    
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
        // AJAX handlers for booking operations
        add_action('wp_ajax_ml_create_booking', array($this, 'ajax_create_booking'));
        add_action('wp_ajax_nopriv_ml_create_booking', array($this, 'ajax_create_booking'));
        add_action('wp_ajax_ml_update_booking_status', array($this, 'ajax_update_status'));
        add_action('wp_ajax_ml_get_booking_details', array($this, 'ajax_get_details'));
    }
    
    /**
     * Create a new booking
     */
    public function create_booking($booking_data) {
        global $wpdb;
        
        // Validate required fields
        $required_fields = array(
            'client_name', 'client_email', 'client_phone', 'event_type',
            'event_date', 'event_start_time', 'event_end_time', 'venue_name',
            'venue_address', 'venue_postcode', 'dj_id'
        );
        
        foreach ($required_fields as $field) {
            if (empty($booking_data[$field])) {
                return new WP_Error('missing_field', "Required field missing: $field");
            }
        }
        
        // Generate unique booking reference
        $booking_reference = $this->generate_booking_reference();
        
        // Calculate event duration
        $start_time = strtotime($booking_data['event_start_time']);
        $end_time = strtotime($booking_data['event_end_time']);
        $duration_hours = ceil(($end_time - $start_time) / 3600);
        
        // Get DJ and package information
        $dj = $this->get_dj_by_id($booking_data['dj_id']);
        if (!$dj) {
            return new WP_Error('invalid_dj', 'Invalid DJ selected');
        }
        
        $package = null;
        if (!empty($booking_data['package_id'])) {
            $package = $this->get_package_by_id($booking_data['package_id']);
        }
        
        // Calculate pricing
        $pricing = $this->calculate_booking_pricing($booking_data, $dj, $package);
        
        // Calculate travel cost if needed
        $travel_cost = 0;
        if (!empty($booking_data['venue_postcode'])) {
            $travel_calculator = ML_Travel::get_instance();
            $travel_cost = $travel_calculator->calculate_cost(
                $dj->coverage_areas, // Assuming this contains DJ's base postcode
                $booking_data['venue_postcode'],
                $dj->travel_rate
            );
        }
        
        // Calculate commission
        $commission_rate = !empty($dj->commission_rate) ? $dj->commission_rate : get_option('ml_commission_rate', 25);
        $total_price = $pricing['base_price'] + $travel_cost + $pricing['extras_cost'];
        $agency_commission = ($total_price * $commission_rate) / 100;
        $dj_payout = $total_price - $agency_commission;
        
        // Calculate deposit
        $deposit_percentage = get_option('ml_default_deposit_percentage', 25);
        $deposit_amount = ($total_price * $deposit_percentage) / 100;
        $final_payment_amount = $total_price - $deposit_amount;
        
        // Prepare booking data for database
        $booking_insert_data = array(
            'booking_reference' => $booking_reference,
            'client_name' => sanitize_text_field($booking_data['client_name']),
            'client_email' => sanitize_email($booking_data['client_email']),
            'client_phone' => sanitize_text_field($booking_data['client_phone']),
            'event_type' => sanitize_text_field($booking_data['event_type']),
            'event_date' => $booking_data['event_date'],
            'event_start_time' => $booking_data['event_start_time'],
            'event_end_time' => $booking_data['event_end_time'],
            'event_duration' => $duration_hours,
            'venue_name' => sanitize_text_field($booking_data['venue_name']),
            'venue_address' => sanitize_textarea_field($booking_data['venue_address']),
            'venue_postcode' => sanitize_text_field($booking_data['venue_postcode']),
            'dj_id' => intval($booking_data['dj_id']),
            'package_id' => !empty($booking_data['package_id']) ? intval($booking_data['package_id']) : null,
            'base_price' => $pricing['base_price'],
            'travel_cost' => $travel_cost,
            'extras_cost' => $pricing['extras_cost'],
            'total_price' => $total_price,
            'agency_commission' => $agency_commission,
            'dj_payout' => $dj_payout,
            'deposit_amount' => $deposit_amount,
            'final_payment_amount' => $final_payment_amount,
            'special_requests' => sanitize_textarea_field($booking_data['special_requests'] ?? ''),
            'equipment_requests' => sanitize_textarea_field($booking_data['equipment_requests'] ?? ''),
            'music_preferences' => sanitize_textarea_field($booking_data['music_preferences'] ?? ''),
            'status' => 'pending',
            'payment_status' => 'pending'
        );
        
        $table_name = ML_Database::get_table_name('bookings');
        
        $result = $wpdb->insert($table_name, $booking_insert_data);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to create booking: ' . $wpdb->last_error);
        }
        
        $booking_id = $wpdb->insert_id;
        
        // Create commission record
        $this->create_commission_record($booking_id, $booking_data['dj_id'], $agency_commission, $commission_rate, $total_price);
        
        // Send notifications
        $this->send_booking_notifications($booking_id);
        
        // Sync with GoHighLevel if enabled
        if (get_option('ml_ghl_api_key')) {
            $ghl = ML_GoHighLevel::get_instance();
            $ghl->sync_booking($booking_id);
        }
        
        // Run safeguards check
        $safeguards = ML_Safeguards::get_instance();
        $safeguards->check_booking($booking_id);
        
        return $booking_id;
    }
    
    /**
     * Generate unique booking reference
     */
    private function generate_booking_reference() {
        $prefix = 'ML';
        $date = date('Ymd');
        $random = sprintf('%04d', rand(1, 9999));
        
        $reference = $prefix . $date . $random;
        
        // Ensure uniqueness
        global $wpdb;
        $table_name = ML_Database::get_table_name('bookings');
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE booking_reference = %s",
            $reference
        ));
        
        if ($exists) {
            return $this->generate_booking_reference(); // Recursive call if duplicate
        }
        
        return $reference;
    }
    
    /**
     * Calculate booking pricing
     */
    private function calculate_booking_pricing($booking_data, $dj, $package = null) {
        $base_price = 0;
        $extras_cost = 0;
        
        if ($package) {
            $base_price = $package->price;
        } else {
            // Calculate based on hourly rate
            $start_time = strtotime($booking_data['event_start_time']);
            $end_time = strtotime($booking_data['event_end_time']);
            $duration_hours = ceil(($end_time - $start_time) / 3600);
            $base_price = $dj->hourly_rate * $duration_hours;
        }
        
        // Add extras if specified
        if (!empty($booking_data['extras'])) {
            $extras_cost = $this->calculate_extras_cost($booking_data['extras'], $dj->id);
        }
        
        return array(
            'base_price' => $base_price,
            'extras_cost' => $extras_cost
        );
    }
    
    /**
     * Calculate extras cost
     */
    private function calculate_extras_cost($extras, $dj_id) {
        // This would be implemented based on available extras for each DJ
        // For now, returning 0 as placeholder
        return 0;
    }
    
    /**
     * Create commission record
     */
    private function create_commission_record($booking_id, $dj_id, $commission_amount, $commission_rate, $booking_total) {
        global $wpdb;
        
        $commission_data = array(
            'booking_id' => $booking_id,
            'dj_id' => $dj_id,
            'commission_amount' => $commission_amount,
            'commission_rate' => $commission_rate,
            'booking_total' => $booking_total,
            'status' => 'pending'
        );
        
        $table_name = ML_Database::get_table_name('commissions');
        $wpdb->insert($table_name, $commission_data);
    }
    
    /**
     * Send booking notifications
     */
    private function send_booking_notifications($booking_id) {
        $booking = $this->get_booking_by_id($booking_id);
        if (!$booking) return;
        
        $email_manager = ML_Email::get_instance();
        
        // Send confirmation to client
        $email_manager->send_booking_confirmation($booking);
        
        // Send notification to DJ
        $email_manager->send_dj_booking_notification($booking);
        
        // Send notification to admin
        $email_manager->send_admin_booking_notification($booking);
    }
    
    /**
     * Get booking by ID
     */
    public function get_booking_by_id($booking_id) {
        global $wpdb;
        
        $table_name = ML_Database::get_table_name('bookings');
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $booking_id
        ));
    }
    
    /**
     * Get booking by reference
     */
    public function get_booking_by_reference($reference) {
        global $wpdb;
        
        $table_name = ML_Database::get_table_name('bookings');
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE booking_reference = %s",
            $reference
        ));
    }
    
    /**
     * Get bookings for a DJ
     */
    public function get_dj_bookings($dj_id, $status = null, $limit = 50, $offset = 0) {
        global $wpdb;
        
        $table_name = ML_Database::get_table_name('bookings');
        
        $where_clause = "WHERE dj_id = %d";
        $params = array($dj_id);
        
        if ($status) {
            $where_clause .= " AND status = %s";
            $params[] = $status;
        }
        
        $query = "SELECT * FROM $table_name $where_clause ORDER BY event_date DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;
        
        return $wpdb->get_results($wpdb->prepare($query, $params));
    }
    
    /**
     * Update booking status
     */
    public function update_booking_status($booking_id, $status, $notes = '') {
        global $wpdb;
        
        $valid_statuses = array('pending', 'confirmed', 'completed', 'cancelled', 'refunded');
        if (!in_array($status, $valid_statuses)) {
            return new WP_Error('invalid_status', 'Invalid booking status');
        }
        
        $table_name = ML_Database::get_table_name('bookings');
        
        $update_data = array(
            'status' => $status,
            'updated_at' => current_time('mysql')
        );
        
        if ($notes) {
            $update_data['admin_notes'] = $notes;
        }
        
        $result = $wpdb->update(
            $table_name,
            $update_data,
            array('id' => $booking_id)
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to update booking status');
        }
        
        // Send status update notifications
        $this->send_status_update_notifications($booking_id, $status);
        
        // Update commission status if booking completed
        if ($status === 'completed') {
            $this->mark_commission_ready($booking_id);
        }
        
        return true;
    }
    
    /**
     * Update payment status
     */
    public function update_payment_status($booking_id, $payment_type, $payment_intent_id = null) {
        global $wpdb;
        
        $table_name = ML_Database::get_table_name('bookings');
        
        $update_data = array();
        
        switch ($payment_type) {
            case 'deposit':
                $update_data['deposit_paid'] = 1;
                $update_data['deposit_paid_date'] = current_time('mysql');
                $update_data['payment_status'] = 'deposit_paid';
                break;
                
            case 'final':
                $update_data['final_payment_paid'] = 1;
                $update_data['final_payment_paid_date'] = current_time('mysql');
                $update_data['payment_status'] = 'fully_paid';
                break;
        }
        
        if ($payment_intent_id) {
            $update_data['stripe_payment_intent_id'] = $payment_intent_id;
        }
        
        $result = $wpdb->update(
            $table_name,
            $update_data,
            array('id' => $booking_id)
        );
        
        // Send payment confirmation
        $this->send_payment_confirmation($booking_id, $payment_type);
        
        return $result !== false;
    }
    
    /**
     * Get DJ by ID
     */
    private function get_dj_by_id($dj_id) {
        global $wpdb;
        
        $table_name = ML_Database::get_table_name('djs');
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d AND status = 'active'",
            $dj_id
        ));
    }
    
    /**
     * Get package by ID
     */
    private function get_package_by_id($package_id) {
        global $wpdb;
        
        $table_name = ML_Database::get_table_name('dj_packages');
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d AND is_active = 1",
            $package_id
        ));
    }
    
    /**
     * Send status update notifications
     */
    private function send_status_update_notifications($booking_id, $status) {
        $booking = $this->get_booking_by_id($booking_id);
        if (!$booking) return;
        
        $email_manager = ML_Email::get_instance();
        $email_manager->send_status_update($booking, $status);
    }
    
    /**
     * Send payment confirmation
     */
    private function send_payment_confirmation($booking_id, $payment_type) {
        $booking = $this->get_booking_by_id($booking_id);
        if (!$booking) return;
        
        $email_manager = ML_Email::get_instance();
        $email_manager->send_payment_confirmation($booking, $payment_type);
    }
    
    /**
     * Mark commission as ready for payment
     */
    private function mark_commission_ready($booking_id) {
        global $wpdb;
        
        $table_name = ML_Database::get_table_name('commissions');
        $wpdb->update(
            $table_name,
            array('status' => 'pending'),
            array('booking_id' => $booking_id)
        );
    }
    
    /**
     * AJAX handler for creating booking
     */
    public function ajax_create_booking() {
        check_ajax_referer('ml_nonce', 'nonce');
        
        $booking_data = $_POST['booking_data'];
        $result = $this->create_booking($booking_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'booking_id' => $result,
                'message' => 'Booking created successfully'
            ));
        }
    }
    
    /**
     * AJAX handler for updating booking status
     */
    public function ajax_update_status() {
        check_ajax_referer('ml_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $booking_id = intval($_POST['booking_id']);
        $status = sanitize_text_field($_POST['status']);
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        
        $result = $this->update_booking_status($booking_id, $status, $notes);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success('Booking status updated successfully');
        }
    }
    
    /**
     * AJAX handler for getting booking details
     */
    public function ajax_get_details() {
        check_ajax_referer('ml_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $booking_id = intval($_POST['booking_id']);
        $booking = $this->get_booking_by_id($booking_id);
        
        if (!$booking) {
            wp_send_json_error('Booking not found');
        }
        
        wp_send_json_success($booking);
    }
    
    /**
     * Get booking statistics
     */
    public function get_booking_stats($date_from = null, $date_to = null) {
        global $wpdb;
        
        $table_name = ML_Database::get_table_name('bookings');
        
        $where_clause = "WHERE 1=1";
        $params = array();
        
        if ($date_from) {
            $where_clause .= " AND event_date >= %s";
            $params[] = $date_from;
        }
        
        if ($date_to) {
            $where_clause .= " AND event_date <= %s";
            $params[] = $date_to;
        }
        
        $stats = array();
        
        // Total bookings
        $query = "SELECT COUNT(*) FROM $table_name $where_clause";
        $stats['total_bookings'] = $wpdb->get_var($wpdb->prepare($query, $params));
        
        // Revenue
        $query = "SELECT SUM(total_price) FROM $table_name $where_clause AND status IN ('confirmed', 'completed')";
        $stats['total_revenue'] = $wpdb->get_var($wpdb->prepare($query, $params)) ?: 0;
        
        // Commission
        $query = "SELECT SUM(agency_commission) FROM $table_name $where_clause AND status IN ('confirmed', 'completed')";
        $stats['total_commission'] = $wpdb->get_var($wpdb->prepare($query, $params)) ?: 0;
        
        // Status breakdown
        $query = "SELECT status, COUNT(*) as count FROM $table_name $where_clause GROUP BY status";
        $status_results = $wpdb->get_results($wpdb->prepare($query, $params));
        
        $stats['status_breakdown'] = array();
        foreach ($status_results as $row) {
            $stats['status_breakdown'][$row->status] = $row->count;
        }
        
        return $stats;
    }
}
?>