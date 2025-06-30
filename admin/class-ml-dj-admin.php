<?php
/**
 * Music & Lights DJ Admin Class
 * 
 * Handles DJ-specific admin functionality and AJAX requests
 * 
 * @package MusicAndLights
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class ML_DJ_Admin {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // AJAX handlers for DJ management
        add_action('wp_ajax_ml_add_dj', array($this, 'add_dj'));
        add_action('wp_ajax_ml_update_dj', array($this, 'update_dj'));
        add_action('wp_ajax_ml_delete_dj', array($this, 'delete_dj'));
        add_action('wp_ajax_ml_activate_dj', array($this, 'activate_dj'));
        add_action('wp_ajax_ml_deactivate_dj', array($this, 'deactivate_dj'));
        add_action('wp_ajax_ml_get_dj_data', array($this, 'get_dj_data'));
        add_action('wp_ajax_ml_get_dj_packages', array($this, 'get_dj_packages'));
        add_action('wp_ajax_ml_get_dj_recent_bookings', array($this, 'get_dj_recent_bookings'));
        add_action('wp_ajax_ml_get_dj_commission_summary', array($this, 'get_dj_commission_summary'));
        add_action('wp_ajax_ml_upload_dj_image', array($this, 'upload_dj_image'));
        add_action('wp_ajax_ml_manage_dj_packages', array($this, 'manage_dj_packages'));
        add_action('wp_ajax_ml_set_dj_availability', array($this, 'set_dj_availability'));
        add_action('wp_ajax_ml_get_dj_availability', array($this, 'get_dj_availability'));
        add_action('wp_ajax_ml_block_dj_dates', array($this, 'block_dj_dates'));
        add_action('wp_ajax_ml_unblock_dj_dates', array($this, 'unblock_dj_dates'));
        
        // Frontend AJAX handlers
        add_action('wp_ajax_nopriv_ml_get_public_dj_profile', array($this, 'get_public_dj_profile'));
        add_action('wp_ajax_ml_get_public_dj_profile', array($this, 'get_public_dj_profile'));
        add_action('wp_ajax_nopriv_ml_send_dj_inquiry', array($this, 'send_dj_inquiry'));
        add_action('wp_ajax_ml_send_dj_inquiry', array($this, 'send_dj_inquiry'));
        
        // DJ dashboard AJAX handlers
        add_action('wp_ajax_ml_update_dj_profile', array($this, 'update_dj_profile_frontend'));
        add_action('wp_ajax_ml_get_dj_dashboard_data', array($this, 'get_dj_dashboard_data'));
        add_action('wp_ajax_ml_update_dj_availability', array($this, 'update_dj_availability'));
        add_action('wp_ajax_ml_block_dj_date', array($this, 'block_dj_date'));
        add_action('wp_ajax_ml_unblock_dj_date', array($this, 'unblock_dj_date'));
        add_action('wp_ajax_ml_get_blocked_dates', array($this, 'get_blocked_dates'));
        add_action('wp_ajax_ml_get_dj_earnings', array($this, 'get_dj_earnings'));
        add_action('wp_ajax_ml_get_dj_bookings', array($this, 'get_dj_bookings'));
        add_action('wp_ajax_ml_get_dj_upcoming_events', array($this, 'get_dj_upcoming_events'));
    }
    
    /**
     * Add new DJ
     */
    public function add_dj() {
        if (!wp_verify_nonce($_POST['nonce'], 'ml_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        // Validate required fields
        $required_fields = array('first_name', 'last_name', 'email', 'phone');
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                wp_send_json_error("Missing required field: {$field}");
                return;
            }
        }
        
        // Check if email already exists
        global $wpdb;
        $existing_dj = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ml_djs WHERE email = %s",
                sanitize_email($_POST['email'])
            )
        );
        
        if ($existing_dj) {
            wp_send_json_error('A DJ with this email address already exists');
            return;
        }
        
        // Prepare DJ data
        $dj_data = array(
            'first_name' => sanitize_text_field($_POST['first_name']),
            'last_name' => sanitize_text_field($_POST['last_name']),
            'stage_name' => sanitize_text_field($_POST['stage_name']),
            'email' => sanitize_email($_POST['email']),
            'phone' => sanitize_text_field($_POST['phone']),
            'date_of_birth' => sanitize_text_field($_POST['date_of_birth']),
            'address' => sanitize_text_field($_POST['address']),
            'city' => sanitize_text_field($_POST['city']),
            'postcode' => sanitize_text_field($_POST['postcode']),
            'experience_years' => intval($_POST['experience_years']),
            'commission_rate' => floatval($_POST['commission_rate'] ?: 25),
            'travel_rate' => floatval($_POST['travel_rate'] ?: 0.45),
            'max_travel_distance' => intval($_POST['max_travel_distance'] ?: 50),
            'specialities' => sanitize_text_field($_POST['specialities']),
            'bio' => sanitize_textarea_field($_POST['bio']),
            'has_own_equipment' => isset($_POST['has_own_equipment']) ? 1 : 0,
            'has_own_transport' => isset($_POST['has_own_transport']) ? 1 : 0,
            'equipment_description' => sanitize_textarea_field($_POST['equipment_description']),
            'status' => 'active',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        // Process coverage areas
        if (!empty($_POST['coverage_areas'])) {
            $areas = array_map('trim', explode("\n", $_POST['coverage_areas']));
            $areas = array_filter($areas); // Remove empty lines
            $dj_data['coverage_areas'] = json_encode($areas);
        }
        
        // Insert DJ
        $result = $wpdb->insert(
            $wpdb->prefix . 'ml_djs',
            $dj_data,
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%f', '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            wp_send_json_error('Failed to create DJ profile');
            return;
        }
        
        $dj_id = $wpdb->insert_id;
        
        // Create WordPress user account (optional)
        $this->create_dj_user_account($dj_id, $dj_data);
        
        // Send welcome email
        do_action('ml_dj_created', $dj_id);
        
        wp_send_json_success(array(
            'message' => 'DJ added successfully',
            'dj_id' => $dj_id
        ));
    }
    
    /**
     * Update DJ
     */
    public function update_dj() {
        if (!wp_verify_nonce($_POST['nonce'], 'ml_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $dj_id = intval($_POST['dj_id']);
        if (!$dj_id) {
            wp_send_json_error('Invalid DJ ID');
            return;
        }
        
        // Prepare update data
        $dj_data = array(
            'first_name' => sanitize_text_field($_POST['first_name']),
            'last_name' => sanitize_text_field($_POST['last_name']),
            'stage_name' => sanitize_text_field($_POST['stage_name']),
            'email' => sanitize_email($_POST['email']),
            'phone' => sanitize_text_field($_POST['phone']),
            'date_of_birth' => sanitize_text_field($_POST['date_of_birth']),
            'address' => sanitize_text_field($_POST['address']),
            'city' => sanitize_text_field($_POST['city']),
            'postcode' => sanitize_text_field($_POST['postcode']),
            'experience_years' => intval($_POST['experience_years']),
            'commission_rate' => floatval($_POST['commission_rate']),
            'travel_rate' => floatval($_POST['travel_rate']),
            'max_travel_distance' => intval($_POST['max_travel_distance']),
            'specialities' => sanitize_text_field($_POST['specialities']),
            'bio' => sanitize_textarea_field($_POST['bio']),
            'has_own_equipment' => isset($_POST['has_own_equipment']) ? 1 : 0,
            'has_own_transport' => isset($_POST['has_own_transport']) ? 1 : 0,
            'equipment_description' => sanitize_textarea_field($_POST['equipment_description']),
            'updated_at' => current_time('mysql')
        );
        
        // Process coverage areas
        if (!empty($_POST['coverage_areas'])) {
            $areas = array_map('trim', explode("\n", $_POST['coverage_areas']));
            $areas = array_filter($areas);
            $dj_data['coverage_areas'] = json_encode($areas);
        }
        
        global $wpdb;
        $result = $wpdb->update(
            $wpdb->prefix . 'ml_djs',
            $dj_data,
            array('id' => $dj_id),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%f', '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error('Failed to update DJ profile');
            return;
        }
        
        do_action('ml_dj_updated', $dj_id);
        
        wp_send_json_success('DJ profile updated successfully');
    }
    
    /**
     * Delete DJ
     */
    public function delete_dj() {
        if (!wp_verify_nonce($_POST['nonce'], 'ml_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $dj_id = intval($_POST['dj_id']);
        if (!$dj_id) {
            wp_send_json_error('Invalid DJ ID');
            return;
        }
        
        // Check for active bookings
        global $wpdb;
        $active_bookings = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ml_bookings 
                WHERE dj_id = %d AND status IN ('pending', 'confirmed')",
                $dj_id
            )
        );
        
        if ($active_bookings > 0) {
            wp_send_json_error('Cannot delete DJ with active bookings. Please complete or cancel all bookings first.');
            return;
        }
        
        // Soft delete - change status to deleted
        $result = $wpdb->update(
            $wpdb->prefix . 'ml_djs',
            array('status' => 'deleted', 'updated_at' => current_time('mysql')),
            array('id' => $dj_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error('Failed to delete DJ');
            return;
        }
        
        do_action('ml_dj_deleted', $dj_id);
        
        wp_send_json_success('DJ deleted successfully');
    }
    
    /**
     * Activate DJ
     */
    public function activate_dj() {
        if (!wp_verify_nonce($_POST['nonce'], 'ml_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        $dj_id = intval($_POST['dj_id']);
        $this->update_dj_status($dj_id, 'active');
        
        wp_send_json_success('DJ activated successfully');
    }
    
    /**
     * Deactivate DJ
     */
    public function deactivate_dj() {
        if (!wp_verify_nonce($_POST['nonce'], 'ml_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        $dj_id = intval($_POST['dj_id']);
        $this->update_dj_status($dj_id, 'inactive');
        
        wp_send_json_success('DJ deactivated successfully');
    }
    
    /**
     * Get DJ data for editing
     */
    public function get_dj_data() {
        if (!wp_verify_nonce($_POST['nonce'], 'ml_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        $dj_id = intval($_POST['dj_id']);
        $dj = $this->get_dj($dj_id);
        
        if (!$dj) {
            wp_send_json_error('DJ not found');
            return;
        }
        
        // Decode coverage areas for editing
        if ($dj->coverage_areas) {
            $dj->coverage_areas_array = json_decode($dj->coverage_areas, true);
            $dj->coverage_areas_text = implode("\n", $dj->coverage_areas_array);
        }
        
        wp_send_json_success($dj);
    }
    
    /**
     * Get DJ packages
     */
    public function get_dj_packages() {
        $dj_id = intval($_POST['dj_id']);
        
        global $wpdb;
        $packages = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ml_dj_packages WHERE dj_id = %d ORDER BY price ASC",
                $dj_id
            )
        );
        
        wp_send_json_success($packages);
    }
    
    /**
     * Get DJ recent bookings
     */
    public function get_dj_recent_bookings() {
        if (!wp_verify_nonce($_POST['nonce'], 'ml_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        $dj_id = intval($_POST['dj_id']);
        
        global $wpdb;
        $bookings = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ml_bookings 
                WHERE dj_id = %d 
                ORDER BY event_date DESC, created_at DESC 
                LIMIT 10",
                $dj_id
            )
        );
        
        wp_send_json_success($bookings);
    }
    
    /**
     * Get DJ commission summary
     */
    public function get_dj_commission_summary() {
        if (!wp_verify_nonce($_POST['nonce'], 'ml_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        $dj_id = intval($_POST['dj_id']);
        
        global $wpdb;
        $summary = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT 
                SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as total_earned,
                SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_payment,
                SUM(CASE WHEN status = 'paid' AND MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE()) THEN amount ELSE 0 END) as this_month
                FROM {$wpdb->prefix}ml_commissions 
                WHERE dj_id = %d",
                $dj_id
            )
        );
        
        wp_send_json_success($summary);
    }
    
    /**
     * Get public DJ profile for frontend
     */
    public function get_public_dj_profile() {
        $dj_id = intval($_POST['dj_id']);
        $dj = $this->get_dj($dj_id);
        
        if (!$dj || $dj->status !== 'active') {
            wp_send_json_error('DJ not found');
            return;
        }
        
        // Get packages
        global $wpdb;
        $packages = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ml_dj_packages WHERE dj_id = %d ORDER BY price ASC",
                $dj_id
            )
        );
        
        // Get recent reviews
        $reviews = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT rating, review, first_name, last_name, event_date, event_type
                FROM {$wpdb->prefix}ml_bookings 
                WHERE dj_id = %d AND rating IS NOT NULL 
                ORDER BY event_date DESC 
                LIMIT 3",
                $dj_id
            )
        );
        
        $dj_name = $dj->stage_name ?: $dj->first_name . ' ' . $dj->last_name;
        $coverage_areas = json_decode($dj->coverage_areas, true) ?: array();
        
        ob_start();
        include plugin_dir_path(__FILE__) . 'partials/public-dj-profile.php';
        $html = ob_get_clean();
        
        wp_send_json_success($html);
    }
    
    /**
     * Send DJ inquiry
     */
    public function send_dj_inquiry() {
        if (!wp_verify_nonce($_POST['nonce'], 'ml_ajax_nonce')) {
            wp_die('Security check failed');
        }
        
        $dj_id = intval($_POST['dj_id']);
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $event_date = sanitize_text_field($_POST['event_date']);
        $message = sanitize_textarea_field($_POST['message']);
        
        if (!$name || !$email || !$message) {
            wp_send_json_error('Please fill in all required fields');
            return;
        }
        
        $dj = $this->get_dj($dj_id);
        if (!$dj) {
            wp_send_json_error('DJ not found');
            return;
        }
        
        // Send inquiry email
        $subject = 'New DJ Inquiry - ' . $name;
        $email_message = "New inquiry for {$dj->stage_name}\n\n";
        $email_message .= "Name: {$name}\n";
        $email_message .= "Email: {$email}\n";
        $email_message .= "Phone: {$phone}\n";
        if ($event_date) {
            $email_message .= "Event Date: {$event_date}\n";
        }
        $email_message .= "Message:\n{$message}";
        
        $sent = wp_mail($dj->email, $subject, $email_message);
        
        if ($sent) {
            wp_send_json_success('Message sent successfully');
        } else {
            wp_send_json_error('Failed to send message');
        }
    }
    
    /**
     * Frontend DJ profile update
     */
    public function update_dj_profile_frontend() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Please log in');
            return;
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'ml_ajax_nonce')) {
            wp_die('Security check failed');
        }
        
        $user_id = get_current_user_id();
        global $wpdb;
        $dj = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ml_djs WHERE user_id = %d",
                $user_id
            )
        );
        
        if (!$dj) {
            wp_send_json_error('DJ profile not found');
            return;
        }
        
        // Update allowed fields
        $update_data = array(
            'stage_name' => sanitize_text_field($_POST['stage_name']),
            'phone' => sanitize_text_field($_POST['phone']),
            'experience_years' => intval($_POST['experience_years']),
            'bio' => sanitize_textarea_field($_POST['bio']),
            'specialities' => sanitize_text_field($_POST['specialities']),
            'equipment_description' => sanitize_textarea_field($_POST['equipment_description']),
            'updated_at' => current_time('mysql')
        );
        
        // Handle profile image upload
        if (!empty($_FILES['profile_image']['name'])) {
            $upload_result = $this->handle_image_upload($_FILES['profile_image']);
            if (!is_wp_error($upload_result)) {
                $update_data['profile_image'] = $upload_result['url'];
            }
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . 'ml_djs',
            $update_data,
            array('id' => $dj->id),
            array('%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success('Profile updated successfully');
        } else {
            wp_send_json_error('Failed to update profile');
        }
    }
    
    /**
     * Get DJ dashboard data
     */
    public function get_dj_dashboard_data() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Please log in');
            return;
        }
        
        $user_id = get_current_user_id();
        $dj = $this->get_dj_by_user_id($user_id);
        
        if (!$dj) {
            wp_send_json_error('DJ profile not found');
            return;
        }
        
        // Get dashboard statistics
        global $wpdb;
        $stats = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT 
                COUNT(*) as total_bookings,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
                SUM(CASE WHEN status = 'completed' THEN total_cost * 0.75 ELSE 0 END) as total_earnings,
                AVG(CASE WHEN rating IS NOT NULL THEN rating ELSE NULL END) as average_rating
                FROM {$wpdb->prefix}ml_bookings 
                WHERE dj_id = %d",
                $dj->id
            )
        );
        
        wp_send_json_success($stats);
    }
    
    /**
     * Update DJ availability
     */
    public function update_dj_availability() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Please log in');
            return;
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'ml_ajax_nonce')) {
            wp_die('Security check failed');
        }
        
        $user_id = get_current_user_id();
        $dj = $this->get_dj_by_user_id($user_id);
        
        if (!$dj) {
            wp_send_json_error('DJ profile not found');
            return;
        }
        
        $availability = $_POST['availability'];
        
        global $wpdb;
        $result = $wpdb->update(
            $wpdb->prefix . 'ml_djs',
            array(
                'availability_settings' => json_encode($availability),
                'updated_at' => current_time('mysql')
            ),
            array('id' => $dj->id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success('Availability updated successfully');
        } else {
            wp_send_json_error('Failed to update availability');
        }
    }
    
    /**
     * Block DJ date
     */
    public function block_dj_date() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Please log in');
            return;
        }
        
        $user_id = get_current_user_id();
        $dj = $this->get_dj_by_user_id($user_id);
        
        if (!$dj) {
            wp_send_json_error('DJ profile not found');
            return;
        }
        
        $date = sanitize_text_field($_POST['date']);
        $reason = sanitize_text_field($_POST['reason']);
        
        global $wpdb;
        $result = $wpdb->replace(
            $wpdb->prefix . 'ml_dj_availability',
            array(
                'dj_id' => $dj->id,
                'date_blocked' => $date,
                'status' => 'blocked',
                'reason' => $reason,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );
        
        if ($result) {
            wp_send_json_success('Date blocked successfully');
        } else {
            wp_send_json_error('Failed to block date');
        }
    }
    
    /**
     * Unblock DJ date
     */
    public function unblock_dj_date() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Please log in');
            return;
        }
        
        $user_id = get_current_user_id();
        $dj = $this->get_dj_by_user_id($user_id);
        
        if (!$dj) {
            wp_send_json_error('DJ profile not found');
            return;
        }
        
        $date = sanitize_text_field($_POST['date']);
        
        global $wpdb;
        $result = $wpdb->delete(
            $wpdb->prefix . 'ml_dj_availability',
            array(
                'dj_id' => $dj->id,
                'date_blocked' => $date
            ),
            array('%d', '%s')
        );
        
        if ($result) {
            wp_send_json_success('Date unblocked successfully');
        } else {
            wp_send_json_error('Failed to unblock date');
        }
    }
    
    /**
     * Get blocked dates
     */
    public function get_blocked_dates() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Please log in');
            return;
        }
        
        $user_id = get_current_user_id();
        $dj = $this->get_dj_by_user_id($user_id);
        
        if (!$dj) {
            wp_send_json_error('DJ profile not found');
            return;
        }
        
        global $wpdb;
        $blocked_dates = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ml_dj_availability 
                WHERE dj_id = %d AND status = 'blocked' AND date_blocked >= CURDATE()
                ORDER BY date_blocked ASC",
                $dj->id
            )
        );
        
        wp_send_json_success($blocked_dates);
    }
    
    /**
     * Get DJ earnings
     */
    public function get_dj_earnings() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Please log in');
            return;
        }
        
        $user_id = get_current_user_id();
        $dj = $this->get_dj_by_user_id($user_id);
        
        if (!$dj) {
            wp_send_json_error('DJ profile not found');
            return;
        }
        
        $period = sanitize_text_field($_POST['period']);
        
        // Calculate date range based on period
        $where_clause = '';
        switch ($period) {
            case 'current-month':
                $where_clause = "AND MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())";
                break;
            case 'last-month':
                $where_clause = "AND MONTH(payment_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(payment_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
                break;
            case 'last-3-months':
                $where_clause = "AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
                break;
            case 'year-to-date':
                $where_clause = "AND YEAR(payment_date) = YEAR(CURDATE())";
                break;
        }
        
        global $wpdb;
        $earnings = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT 
                SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as total_earned,
                SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_payment,
                SUM(CASE WHEN status = 'paid' AND MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE()) THEN amount ELSE 0 END) as this_month
                FROM {$wpdb->prefix}ml_commissions 
                WHERE dj_id = %d {$where_clause}",
                $dj->id
            )
        );
        
        // Get commission history
        $commission_history = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ml_commissions 
                WHERE dj_id = %d 
                ORDER BY created_at DESC 
                LIMIT 20",
                $dj->id
            )
        );
        
        $earnings->commission_history = $commission_history;
        
        wp_send_json_success($earnings);
    }
    
    /**
     * Get DJ bookings
     */
    public function get_dj_bookings() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Please log in');
            return;
        }
        
        $user_id = get_current_user_id();
        $dj = $this->get_dj_by_user_id($user_id);
        
        if (!$dj) {
            wp_send_json_error('DJ profile not found');
            return;
        }
        
        $status = sanitize_text_field($_POST['status']);
        $date = sanitize_text_field($_POST['date']);
        
        $where_conditions = array("dj_id = %d");
        $params = array($dj->id);
        
        if ($status) {
            $where_conditions[] = "status = %s";
            $params[] = $status;
        }
        
        if ($date) {
            $where_conditions[] = "event_date = %s";
            $params[] = $date;
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        global $wpdb;
        $query = "SELECT * FROM {$wpdb->prefix}ml_bookings {$where_clause} ORDER BY event_date DESC, created_at DESC LIMIT 50";
        
        $bookings = $wpdb->get_results($wpdb->prepare($query, $params));
        
        wp_send_json_success($bookings);
    }
    
    /**
     * Get DJ upcoming events
     */
    public function get_dj_upcoming_events() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Please log in');
            return;
        }
        
        $user_id = get_current_user_id();
        $dj = $this->get_dj_by_user_id($user_id);
        
        if (!$dj) {
            wp_send_json_error('DJ profile not found');
            return;
        }
        
        global $wpdb;
        $events = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ml_bookings 
                WHERE dj_id = %d 
                AND event_date >= CURDATE() 
                AND status IN ('confirmed', 'pending')
                ORDER BY event_date ASC, event_time ASC 
                LIMIT 10",
                $dj->id
            )
        );
        
        wp_send_json_success($events);
    }
    
    /**
     * Helper methods
     */
    
    private function update_dj_status($dj_id, $status) {
        global $wpdb;
        
        $result = $wpdb->update(
            $wpdb->prefix . 'ml_djs',
            array('status' => $status, 'updated_at' => current_time('mysql')),
            array('id' => $dj_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            do_action('ml_dj_status_changed', $dj_id, $status);
        }
        
        return $result;
    }
    
    private function get_dj($dj_id) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ml_djs WHERE id = %d",
                $dj_id
            )
        );
    }
    
    private function get_dj_by_user_id($user_id) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ml_djs WHERE user_id = %d",
                $user_id
            )
        );
    }
    
    private function create_dj_user_account($dj_id, $dj_data) {
        $username = sanitize_user($dj_data['email']);
        $password = wp_generate_password(12, false);
        
        $user_id = wp_create_user($username, $password, $dj_data['email']);
        
        if (!is_wp_error($user_id)) {
            // Update DJ record with user ID
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'ml_djs',
                array('user_id' => $user_id),
                array('id' => $dj_id),
                array('%d'),
                array('%d')
            );
            
            // Add DJ role
            $user = new WP_User($user_id);
            $user->set_role('ml_dj');
            
            // Send welcome email with login details
            $this->send_dj_welcome_email($dj_data['email'], $username, $password);
        }
        
        return $user_id;
    }
    
    private function send_dj_welcome_email($email, $username, $password) {
        $subject = 'Welcome to Music & Lights DJ Portal';
        $message = "Welcome to the Music & Lights DJ Portal!\n\n";
        $message .= "Your login details:\n";
        $message .= "Username: {$username}\n";
        $message .= "Password: {$password}\n\n";
        $message .= "Login URL: " . home_url('/dj-dashboard/') . "\n\n";
        $message .= "Please change your password after your first login.";
        
        wp_mail($email, $subject, $message);
    }
    
    private function handle_image_upload($file) {
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        $upload_overrides = array('test_form' => false);
        $uploaded_file = wp_handle_upload($file, $upload_overrides);
        
        if (isset($uploaded_file['error'])) {
            return new WP_Error('upload_error', $uploaded_file['error']);
        }
        
        return $uploaded_file;
    }
}

// Initialize the DJ admin class
ML_DJ_Admin::get_instance();