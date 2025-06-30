<?php
/**
 * DJ management class for Music & Lights plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class ML_DJ {
    
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
        add_action('wp_ajax_ml_save_dj_profile', array($this, 'ajax_save_profile'));
        add_action('wp_ajax_ml_get_dj_packages', array($this, 'ajax_get_packages'));
        add_action('wp_ajax_ml_save_dj_package', array($this, 'ajax_save_package'));
        add_action('wp_ajax_ml_delete_dj_package', array($this, 'ajax_delete_package'));
        add_action('wp_ajax_ml_get_available_djs', array($this, 'ajax_get_available_djs'));
        add_action('wp_ajax_nopriv_ml_get_available_djs', array($this, 'ajax_get_available_djs'));
        
        // DJ dashboard handlers
        add_action('wp_ajax_ml_dj_update_availability', array($this, 'ajax_update_availability'));
        add_action('wp_ajax_ml_dj_get_bookings', array($this, 'ajax_get_dj_bookings'));
    }
    
    /**
     * Create a new DJ profile
     */
    public function create_dj_profile($dj_data) {
        global $wpdb;
        
        // Validate required fields
        $required_fields = array(
            'user_id', 'stage_name', 'real_name', 'email', 'phone', 'base_rate', 'hourly_rate'
        );
        
        foreach ($required_fields as $field) {
            if (empty($dj_data[$field])) {
                return new WP_Error('missing_field', "Required field missing: $field");
            }
        }
        
        // Check if DJ already exists for this user
        $existing_dj = $this->get_dj_by_user_id($dj_data['user_id']);
        if ($existing_dj) {
            return new WP_Error('dj_exists', 'DJ profile already exists for this user');
        }
        
        // Validate email format
        if (!is_email($dj_data['email'])) {
            return new WP_Error('invalid_email', 'Invalid email address');
        }
        
        // Prepare data for insertion
        $insert_data = array(
            'user_id' => intval($dj_data['user_id']),
            'stage_name' => sanitize_text_field($dj_data['stage_name']),
            'real_name' => sanitize_text_field($dj_data['real_name']),
            'email' => sanitize_email($dj_data['email']),
            'phone' => sanitize_text_field($dj_data['phone']),
            'bio' => sanitize_textarea_field($dj_data['bio'] ?? ''),
            'profile_image' => sanitize_url($dj_data['profile_image'] ?? ''),
            'experience_years' => intval($dj_data['experience_years'] ?? 0),
            'specialties' => sanitize_textarea_field($dj_data['specialties'] ?? ''),
            'equipment_owned' => sanitize_textarea_field($dj_data['equipment_owned'] ?? ''),
            'travel_radius' => intval($dj_data['travel_radius'] ?? 50),
            'travel_rate' => floatval($dj_data['travel_rate'] ?? 0.45),
            'base_rate' => floatval($dj_data['base_rate']),
            'hourly_rate' => floatval($dj_data['hourly_rate']),
            'coverage_areas' => sanitize_textarea_field($dj_data['coverage_areas'] ?? ''),
            'commission_rate' => floatval($dj_data['commission_rate'] ?? get_option('ml_commission_rate', 25)),
            'status' => 'active'
        );
        
        $table_name = ML_Database::get_table_name('djs');
        $result = $wpdb->insert($table_name, $insert_data);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to create DJ profile: ' . $wpdb->last_error);
        }
        
        $dj_id = $wpdb->insert_id;
        
        // Create default packages if provided
        if (!empty($dj_data['packages'])) {
            foreach ($dj_data['packages'] as $package_data) {
                $package_data['dj_id'] = $dj_id;
                $this->create_dj_package($package_data);
            }
        }
        
        // Update user capabilities
        $user = get_user_by('id', $dj_data['user_id']);
        if ($user) {
            $user->add_cap('ml_dj_access');
        }
        
        return $dj_id;
    }
    
    /**
     * Update DJ profile
     */
    public function update_dj_profile($dj_id, $dj_data) {
        global $wpdb;
        
        // Validate DJ exists
        $existing_dj = $this->get_dj_by_id($dj_id);
        if (!$existing_dj) {
            return new WP_Error('dj_not_found', 'DJ profile not found');
        }
        
        // Prepare update data
        $update_data = array();
        
        $updatable_fields = array(
            'stage_name', 'real_name', 'email', 'phone', 'bio', 'profile_image',
            'experience_years', 'specialties', 'equipment_owned', 'travel_radius',
            'travel_rate', 'base_rate', 'hourly_rate', 'coverage_areas', 'commission_rate'
        );
        
        foreach ($updatable_fields as $field) {
            if (isset($dj_data[$field])) {
                switch ($field) {
                    case 'stage_name':
                    case 'real_name':
                    case 'phone':
                        $update_data[$field] = sanitize_text_field($dj_data[$field]);
                        break;
                    case 'email':
                        if (is_email($dj_data[$field])) {
                            $update_data[$field] = sanitize_email($dj_data[$field]);
                        }
                        break;
                    case 'bio':
                    case 'specialties':
                    case 'equipment_owned':
                    case 'coverage_areas':
                        $update_data[$field] = sanitize_textarea_field($dj_data[$field]);
                        break;
                    case 'profile_image':
                        $update_data[$field] = sanitize_url($dj_data[$field]);
                        break;
                    case 'experience_years':
                    case 'travel_radius':
                        $update_data[$field] = intval($dj_data[$field]);
                        break;
                    case 'travel_rate':
                    case 'base_rate':
                    case 'hourly_rate':
                    case 'commission_rate':
                        $update_data[$field] = floatval($dj_data[$field]);
                        break;
                }
            }
        }
        
        if (empty($update_data)) {
            return new WP_Error('no_data', 'No valid data to update');
        }
        
        $update_data['updated_at'] = current_time('mysql');
        
        $table_name = ML_Database::get_table_name('djs');
        $result = $wpdb->update(
            $table_name,
            $update_data,
            array('id' => $dj_id)
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to update DJ profile: ' . $wpdb->last_error);
        }
        
        return true;
    }
    
    /**
     * Get DJ by ID
     */
    public function get_dj_by_id($dj_id) {
        global $wpdb;
        
        $table_name = ML_Database::get_table_name('djs');
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $dj_id
        ));
    }
    
    /**
     * Get DJ by user ID
     */
    public function get_dj_by_user_id($user_id) {
        global $wpdb;
        
        $table_name = ML_Database::get_table_name('djs');
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d",
            $user_id
        ));
    }
    
    /**
     * Get all active DJs
     */
    public function get_all_djs($status = 'active', $limit = 50, $offset = 0) {
        global $wpdb;
        
        $table_name = ML_Database::get_table_name('djs');
        
        $where_clause = '';
        $params = array();
        
        if ($status) {
            $where_clause = 'WHERE status = %s';
            $params[] = $status;
        }
        
        $query = "SELECT * FROM $table_name $where_clause ORDER BY stage_name ASC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;
        
        return $wpdb->get_results($wpdb->prepare($query, $params));
    }
    
    /**
     * Get available DJs for a specific date and location
     */
    public function get_available_djs($event_date, $event_start_time, $event_end_time, $postcode = null) {
        global $wpdb;
        
        $dj_table = ML_Database::get_table_name('djs');
        $booking_table = ML_Database::get_table_name('bookings');
        
        // Base query for active DJs
        $query = "SELECT d.* FROM $dj_table d 
                  WHERE d.status = 'active'
                  AND d.id NOT IN (
                      SELECT b.dj_id FROM $booking_table b 
                      WHERE b.event_date = %s 
                      AND b.status IN ('confirmed', 'pending')
                      AND (
                          (b.event_start_time < %s AND b.event_end_time > %s) OR
                          (b.event_start_time < %s AND b.event_end_time > %s) OR
                          (b.event_start_time >= %s AND b.event_end_time <= %s)
                      )
                  )";
        
        $params = array(
            $event_date,
            $event_end_time, $event_start_time,
            $event_start_time, $event_end_time,
            $event_start_time, $event_end_time
        );
        
        // Add location filtering if postcode provided
        if ($postcode) {
            // This would integrate with the travel calculator
            // For now, we'll just return all available DJs
        }
        
        $query .= " ORDER BY d.stage_name ASC";
        
        return $wpdb->get_results($wpdb->prepare($query, $params));
    }
    
    /**
     * Create DJ package
     */
    public function create_dj_package($package_data) {
        global $wpdb;
        
        $required_fields = array('dj_id', 'package_name', 'duration_hours', 'price');
        
        foreach ($required_fields as $field) {
            if (empty($package_data[$field])) {
                return new WP_Error('missing_field', "Required field missing: $field");
            }
        }
        
        $insert_data = array(
            'dj_id' => intval($package_data['dj_id']),
            'package_name' => sanitize_text_field($package_data['package_name']),
            'description' => sanitize_textarea_field($package_data['description'] ?? ''),
            'duration_hours' => intval($package_data['duration_hours']),
            'price' => floatval($package_data['price']),
            'equipment_included' => sanitize_textarea_field($package_data['equipment_included'] ?? ''),
            'extras_available' => sanitize_textarea_field($package_data['extras_available'] ?? ''),
            'sort_order' => intval($package_data['sort_order'] ?? 0)
        );
        
        $table_name = ML_Database::get_table_name('dj_packages');
        $result = $wpdb->insert($table_name, $insert_data);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to create package: ' . $wpdb->last_error);
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get DJ packages
     */
    public function get_dj_packages($dj_id, $active_only = true) {
        global $wpdb;
        
        $table_name = ML_Database::get_table_name('dj_packages');
        
        $where_clause = 'WHERE dj_id = %d';
        $params = array($dj_id);
        
        if ($active_only) {
            $where_clause .= ' AND is_active = 1';
        }
        
        $query = "SELECT * FROM $table_name $where_clause ORDER BY sort_order ASC, price ASC";
        
        return $wpdb->get_results($wpdb->prepare($query, $params));
    }
    
    /**
     * Update DJ package
     */
    public function update_dj_package($package_id, $package_data) {
        global $wpdb;
        
        $update_data = array();
        
        $updatable_fields = array(
            'package_name', 'description', 'duration_hours', 'price',
            'equipment_included', 'extras_available', 'sort_order', 'is_active'
        );
        
        foreach ($updatable_fields as $field) {
            if (isset($package_data[$field])) {
                switch ($field) {
                    case 'package_name':
                        $update_data[$field] = sanitize_text_field($package_data[$field]);
                        break;
                    case 'description':
                    case 'equipment_included':
                    case 'extras_available':
                        $update_data[$field] = sanitize_textarea_field($package_data[$field]);
                        break;
                    case 'duration_hours':
                    case 'sort_order':
                    case 'is_active':
                        $update_data[$field] = intval($package_data[$field]);
                        break;
                    case 'price':
                        $update_data[$field] = floatval($package_data[$field]);
                        break;
                }
            }
        }
        
        if (empty($update_data)) {
            return new WP_Error('no_data', 'No valid data to update');
        }
        
        $update_data['updated_at'] = current_time('mysql');
        
        $table_name = ML_Database::get_table_name('dj_packages');
        $result = $wpdb->update(
            $table_name,
            $update_data,
            array('id' => $package_id)
        );
        
        return $result !== false;
    }
    
    /**
     * Delete DJ package
     */
    public function delete_dj_package($package_id) {
        global $wpdb;
        
        // Check if package is used in any bookings
        $booking_table = ML_Database::get_table_name('bookings');
        $used_in_bookings = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $booking_table WHERE package_id = %d",
            $package_id
        ));
        
        if ($used_in_bookings > 0) {
            // Don't delete, just deactivate
            return $this->update_dj_package($package_id, array('is_active' => 0));
        }
        
        $table_name = ML_Database::get_table_name('dj_packages');
        $result = $wpdb->delete($table_name, array('id' => $package_id));
        
        return $result !== false;
    }
    
    /**
     * Update DJ availability
     */
    public function update_availability($dj_id, $available_dates, $unavailable_dates) {
        global $wpdb;
        
        $update_data = array(
            'available_dates' => is_array($available_dates) ? json_encode($available_dates) : $available_dates,
            'unavailable_dates' => is_array($unavailable_dates) ? json_encode($unavailable_dates) : $unavailable_dates,
            'updated_at' => current_time('mysql')
        );
        
        $table_name = ML_Database::get_table_name('djs');
        $result = $wpdb->update(
            $table_name,
            $update_data,
            array('id' => $dj_id)
        );
        
        return $result !== false;
    }
    
    /**
     * Get DJ statistics
     */
    public function get_dj_stats($dj_id, $date_from = null, $date_to = null) {
        global $wpdb;
        
        $booking_table = ML_Database::get_table_name('bookings');
        
        $where_clause = "WHERE dj_id = %d";
        $params = array($dj_id);
        
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
        $query = "SELECT COUNT(*) FROM $booking_table $where_clause";
        $stats['total_bookings'] = $wpdb->get_var($wpdb->prepare($query, $params));
        
        // Total earnings (DJ payout)
        $query = "SELECT SUM(dj_payout) FROM $booking_table $where_clause AND status IN ('confirmed', 'completed')";
        $stats['total_earnings'] = $wpdb->get_var($wpdb->prepare($query, $params)) ?: 0;
        
        // Total commission paid to agency
        $query = "SELECT SUM(agency_commission) FROM $booking_table $where_clause AND status IN ('confirmed', 'completed')";
        $stats['total_commission'] = $wpdb->get_var($wpdb->prepare($query, $params)) ?: 0;
        
        // Upcoming bookings
        $query = "SELECT COUNT(*) FROM $booking_table WHERE dj_id = %d AND event_date >= %s AND status IN ('confirmed', 'pending')";
        $stats['upcoming_bookings'] = $wpdb->get_var($wpdb->prepare($query, $dj_id, current_time('Y-m-d')));
        
        return $stats;
    }
    
    /**
     * AJAX: Save DJ profile
     */
    public function ajax_save_profile() {
        check_ajax_referer('ml_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $dj_data = $_POST['dj_data'];
        $dj_id = intval($_POST['dj_id'] ?? 0);
        
        if ($dj_id) {
            $result = $this->update_dj_profile($dj_id, $dj_data);
        } else {
            $result = $this->create_dj_profile($dj_data);
        }
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'dj_id' => $dj_id ?: $result,
                'message' => $dj_id ? 'DJ profile updated successfully' : 'DJ profile created successfully'
            ));
        }
    }
    
    /**
     * AJAX: Get DJ packages
     */
    public function ajax_get_packages() {
        check_ajax_referer('ml_nonce', 'nonce');
        
        $dj_id = intval($_POST['dj_id']);
        $packages = $this->get_dj_packages($dj_id);
        
        wp_send_json_success($packages);
    }
    
    /**
     * AJAX: Save DJ package
     */
    public function ajax_save_package() {
        check_ajax_referer('ml_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $package_data = $_POST['package_data'];
        $package_id = intval($_POST['package_id'] ?? 0);
        
        if ($package_id) {
            $result = $this->update_dj_package($package_id, $package_data);
        } else {
            $result = $this->create_dj_package($package_data);
        }
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(array(
                'package_id' => $package_id ?: $result,
                'message' => $package_id ? 'Package updated successfully' : 'Package created successfully'
            ));
        }
    }
    
    /**
     * AJAX: Delete DJ package
     */
    public function ajax_delete_package() {
        check_ajax_referer('ml_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $package_id = intval($_POST['package_id']);
        $result = $this->delete_dj_package($package_id);
        
        if ($result) {
            wp_send_json_success('Package deleted successfully');
        } else {
            wp_send_json_error('Failed to delete package');
        }
    }
    
    /**
     * AJAX: Get available DJs
     */
    public function ajax_get_available_djs() {
        check_ajax_referer('ml_nonce', 'nonce');
        
        $event_date = sanitize_text_field($_POST['event_date']);
        $event_start_time = sanitize_text_field($_POST['event_start_time']);
        $event_end_time = sanitize_text_field($_POST['event_end_time']);
        $postcode = sanitize_text_field($_POST['postcode'] ?? '');
        
        $available_djs = $this->get_available_djs($event_date, $event_start_time, $event_end_time, $postcode);
        
        // Add packages to each DJ
        foreach ($available_djs as &$dj) {
            $dj->packages = $this->get_dj_packages($dj->id);
        }
        
        wp_send_json_success($available_djs);
    }
    
    /**
     * AJAX: Update DJ availability (for DJ dashboard)
     */
    public function ajax_update_availability() {
        check_ajax_referer('ml_nonce', 'nonce');
        
        // Verify DJ access
        if (!current_user_can('ml_dj_access') && !current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $dj_id = intval($_POST['dj_id']);
        $available_dates = $_POST['available_dates'];
        $unavailable_dates = $_POST['unavailable_dates'];
        
        // Verify DJ owns this profile or user is admin
        if (!current_user_can('manage_options')) {
            $user_dj = $this->get_dj_by_user_id(get_current_user_id());
            if (!$user_dj || $user_dj->id != $dj_id) {
                wp_send_json_error('Access denied');
            }
        }
        
        $result = $this->update_availability($dj_id, $available_dates, $unavailable_dates);
        
        if ($result) {
            wp_send_json_success('Availability updated successfully');
        } else {
            wp_send_json_error('Failed to update availability');
        }
    }
    
    /**
     * AJAX: Get DJ bookings (for DJ dashboard)
     */
    public function ajax_get_dj_bookings() {
        check_ajax_referer('ml_nonce', 'nonce');
        
        // Verify DJ access
        if (!current_user_can('ml_dj_access') && !current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $dj_id = intval($_POST['dj_id']);
        $status = sanitize_text_field($_POST['status'] ?? '');
        
        // Verify DJ owns this profile or user is admin
        if (!current_user_can('manage_options')) {
            $user_dj = $this->get_dj_by_user_id(get_current_user_id());
            if (!$user_dj || $user_dj->id != $dj_id) {
                wp_send_json_error('Access denied');
            }
        }
        
        $booking_manager = ML_Booking::get_instance();
        $bookings = $booking_manager->get_dj_bookings($dj_id, $status);
        
        wp_send_json_success($bookings);
    }
}