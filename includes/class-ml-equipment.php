<?php
/**
 * Music & Lights Equipment Class - Equipment Tracking
 * 
 * Handles equipment inventory management, assignment to bookings,
 * and tracking of equipment usage and availability.
 * 
 * @package MusicAndLights
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class ML_Equipment {
    
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
        add_action('wp_ajax_ml_add_equipment', array($this, 'add_equipment'));
        add_action('wp_ajax_ml_update_equipment', array($this, 'update_equipment'));
        add_action('wp_ajax_ml_delete_equipment', array($this, 'delete_equipment'));
        add_action('wp_ajax_ml_assign_equipment', array($this, 'assign_equipment_to_booking'));
        add_action('wp_ajax_ml_check_equipment_availability', array($this, 'check_equipment_availability'));
        add_action('wp_ajax_ml_get_equipment_schedule', array($this, 'get_equipment_schedule'));
        add_action('ml_booking_completed', array($this, 'return_equipment'), 10, 1);
        add_action('ml_booking_cancelled', array($this, 'release_equipment'), 10, 1);
    }
    
    /**
     * Add new equipment
     */
    public function add_equipment() {
        if (!wp_verify_nonce($_POST['nonce'], 'ml_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $equipment_data = array(
            'name' => sanitize_text_field($_POST['name']),
            'type' => sanitize_text_field($_POST['type']),
            'brand' => sanitize_text_field($_POST['brand']),
            'model' => sanitize_text_field($_POST['model']),
            'serial_number' => sanitize_text_field($_POST['serial_number']),
            'condition' => sanitize_text_field($_POST['condition']),
            'purchase_date' => sanitize_text_field($_POST['purchase_date']),
            'purchase_cost' => floatval($_POST['purchase_cost']),
            'rental_cost_per_day' => floatval($_POST['rental_cost_per_day']),
            'description' => sanitize_textarea_field($_POST['description']),
            'status' => 'available'
        );
        
        $equipment_id = $this->create_equipment($equipment_data);
        
        if ($equipment_id) {
            wp_send_json_success(array(
                'message' => 'Equipment added successfully',
                'equipment_id' => $equipment_id
            ));
        } else {
            wp_send_json_error('Failed to add equipment');
        }
    }
    
    /**
     * Create equipment record
     */
    public function create_equipment($equipment_data) {
        global $wpdb;
        
        $equipment_data['created_at'] = current_time('mysql');
        $equipment_data['updated_at'] = current_time('mysql');
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'ml_equipment',
            $equipment_data,
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s')
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Update equipment
     */
    public function update_equipment() {
        if (!wp_verify_nonce($_POST['nonce'], 'ml_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $equipment_id = intval($_POST['equipment_id']);
        $equipment_data = array(
            'name' => sanitize_text_field($_POST['name']),
            'type' => sanitize_text_field($_POST['type']),
            'brand' => sanitize_text_field($_POST['brand']),
            'model' => sanitize_text_field($_POST['model']),
            'serial_number' => sanitize_text_field($_POST['serial_number']),
            'condition' => sanitize_text_field($_POST['condition']),
            'purchase_date' => sanitize_text_field($_POST['purchase_date']),
            'purchase_cost' => floatval($_POST['purchase_cost']),
            'rental_cost_per_day' => floatval($_POST['rental_cost_per_day']),
            'description' => sanitize_textarea_field($_POST['description']),
            'status' => sanitize_text_field($_POST['status']),
            'updated_at' => current_time('mysql')
        );
        
        global $wpdb;
        $result = $wpdb->update(
            $wpdb->prefix . 'ml_equipment',
            $equipment_data,
            array('id' => $equipment_id),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success('Equipment updated successfully');
        } else {
            wp_send_json_error('Failed to update equipment');
        }
    }
    
    /**
     * Delete equipment
     */
    public function delete_equipment() {
        if (!wp_verify_nonce($_POST['nonce'], 'ml_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $equipment_id = intval($_POST['equipment_id']);
        
        // Check if equipment is currently assigned
        if ($this->is_equipment_assigned($equipment_id)) {
            wp_send_json_error('Cannot delete equipment that is currently assigned to bookings');
            return;
        }
        
        global $wpdb;
        $result = $wpdb->delete(
            $wpdb->prefix . 'ml_equipment',
            array('id' => $equipment_id),
            array('%d')
        );
        
        if ($result) {
            wp_send_json_success('Equipment deleted successfully');
        } else {
            wp_send_json_error('Failed to delete equipment');
        }
    }
    
    /**
     * Assign equipment to booking
     */
    public function assign_equipment_to_booking() {
        if (!wp_verify_nonce($_POST['nonce'], 'ml_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        $booking_id = intval($_POST['booking_id']);
        $equipment_ids = array_map('intval', $_POST['equipment_ids']);
        
        if (!$booking_id || empty($equipment_ids)) {
            wp_send_json_error('Missing parameters');
            return;
        }
        
        $booking = $this->get_booking($booking_id);
        if (!$booking) {
            wp_send_json_error('Booking not found');
            return;
        }
        
        // Check availability for all equipment
        $unavailable_equipment = array();
        foreach ($equipment_ids as $equipment_id) {
            if (!$this->is_equipment_available($equipment_id, $booking->event_date)) {
                $equipment = $this->get_equipment($equipment_id);
                $unavailable_equipment[] = $equipment->name;
            }
        }
        
        if (!empty($unavailable_equipment)) {
            wp_send_json_error('The following equipment is not available: ' . implode(', ', $unavailable_equipment));
            return;
        }
        
        // Assign equipment
        $assigned_count = 0;
        foreach ($equipment_ids as $equipment_id) {
            if ($this->assign_equipment($booking_id, $equipment_id)) {
                $assigned_count++;
            }
        }
        
        if ($assigned_count > 0) {
            // Update booking total if equipment has rental costs
            $this->update_booking_equipment_costs($booking_id);
            
            wp_send_json_success(array(
                'message' => "Successfully assigned {$assigned_count} equipment items",
                'assigned_count' => $assigned_count
            ));
        } else {
            wp_send_json_error('Failed to assign equipment');
        }
    }
    
    /**
     * Assign single equipment item
     */
    private function assign_equipment($booking_id, $equipment_id) {
        global $wpdb;
        
        $equipment = $this->get_equipment($equipment_id);
        if (!$equipment) {
            return false;
        }
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'ml_booking_equipment',
            array(
                'booking_id' => $booking_id,
                'equipment_id' => $equipment_id,
                'rental_cost' => $equipment->rental_cost_per_day,
                'status' => 'assigned',
                'assigned_at' => current_time('mysql')
            ),
            array('%d', '%d', '%f', '%s', '%s')
        );
        
        if ($result) {
            // Update equipment status
            $wpdb->update(
                $wpdb->prefix . 'ml_equipment',
                array('status' => 'rented'),
                array('id' => $equipment_id),
                array('%s'),
                array('%d')
            );
        }
        
        return $result !== false;
    }
    
    /**
     * Check equipment availability
     */
    public function check_equipment_availability() {
        $equipment_id = intval($_POST['equipment_id']);
        $date = sanitize_text_field($_POST['date']);
        
        if (!$equipment_id || !$date) {
            wp_send_json_error('Missing parameters');
            return;
        }
        
        $available = $this->is_equipment_available($equipment_id, $date);
        
        wp_send_json_success(array(
            'available' => $available,
            'equipment_id' => $equipment_id,
            'date' => $date
        ));
    }
    
    /**
     * Check if equipment is available on specific date
     */
    public function is_equipment_available($equipment_id, $date) {
        global $wpdb;
        
        // Check if equipment exists and is available
        $equipment = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ml_equipment 
                WHERE id = %d AND status IN ('available', 'rented')",
                $equipment_id
            )
        );
        
        if (!$equipment) {
            return false;
        }
        
        // Check for existing bookings on that date
        $existing_assignment = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ml_booking_equipment be
                JOIN {$wpdb->prefix}ml_bookings b ON be.booking_id = b.id
                WHERE be.equipment_id = %d 
                AND b.event_date = %s 
                AND be.status IN ('assigned', 'out')
                AND b.status IN ('confirmed', 'pending')",
                $equipment_id,
                $date
            )
        );
        
        return $existing_assignment == 0;
    }
    
    /**
     * Get equipment schedule
     */
    public function get_equipment_schedule() {
        $equipment_id = intval($_POST['equipment_id']);
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);
        
        if (!$equipment_id) {
            wp_send_json_error('Missing equipment ID');
            return;
        }
        
        global $wpdb;
        $schedule = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT b.id as booking_id, b.event_date, b.event_time, b.first_name, b.last_name, 
                b.venue_name, be.status as equipment_status, be.rental_cost
                FROM {$wpdb->prefix}ml_booking_equipment be
                JOIN {$wpdb->prefix}ml_bookings b ON be.booking_id = b.id
                WHERE be.equipment_id = %d 
                AND b.event_date >= %s 
                AND b.event_date <= %s
                AND b.status IN ('confirmed', 'pending', 'completed')
                ORDER BY b.event_date, b.event_time",
                $equipment_id,
                $start_date ?: date('Y-m-d'),
                $end_date ?: date('Y-m-d', strtotime('+3 months'))
            )
        );
        
        wp_send_json_success($schedule);
    }
    
    /**
     * Return equipment after booking completion
     */
    public function return_equipment($booking_id) {
        global $wpdb;
        
        // Update equipment assignments
        $wpdb->update(
            $wpdb->prefix . 'ml_booking_equipment',
            array(
                'status' => 'returned',
                'returned_at' => current_time('mysql')
            ),
            array('booking_id' => $booking_id),
            array('%s', '%s'),
            array('%d')
        );
        
        // Get equipment IDs for this booking
        $equipment_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT equipment_id FROM {$wpdb->prefix}ml_booking_equipment 
                WHERE booking_id = %d",
                $booking_id
            )
        );
        
        // Update equipment status back to available
        foreach ($equipment_ids as $equipment_id) {
            $wpdb->update(
                $wpdb->prefix . 'ml_equipment',
                array('status' => 'available'),
                array('id' => $equipment_id),
                array('%s'),
                array('%d')
            );
        }
    }
    
    /**
     * Release equipment when booking is cancelled
     */
    public function release_equipment($booking_id) {
        global $wpdb;
        
        // Remove equipment assignments
        $equipment_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT equipment_id FROM {$wpdb->prefix}ml_booking_equipment 
                WHERE booking_id = %d",
                $booking_id
            )
        );
        
        $wpdb->delete(
            $wpdb->prefix . 'ml_booking_equipment',
            array('booking_id' => $booking_id),
            array('%d')
        );
        
        // Update equipment status back to available
        foreach ($equipment_ids as $equipment_id) {
            $wpdb->update(
                $wpdb->prefix . 'ml_equipment',
                array('status' => 'available'),
                array('id' => $equipment_id),
                array('%s'),
                array('%d')
            );
        }
    }
    
    /**
     * Get all equipment
     */
    public function get_all_equipment($status = null) {
        global $wpdb;
        
        $where_clause = '';
        $params = array();
        
        if ($status) {
            $where_clause = 'WHERE status = %s';
            $params[] = $status;
        }
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ml_equipment 
                $where_clause 
                ORDER BY name ASC",
                $params
            )
        );
    }
    
    /**
     * Get equipment by type
     */
    public function get_equipment_by_type($type) {
        global $wpdb;
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ml_equipment 
                WHERE type = %s 
                ORDER BY name ASC",
                $type
            )
        );
    }
    
    /**
     * Get equipment for booking
     */
    public function get_booking_equipment($booking_id) {
        global $wpdb;
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT e.*, be.rental_cost, be.status as assignment_status, be.assigned_at, be.returned_at
                FROM {$wpdb->prefix}ml_equipment e
                JOIN {$wpdb->prefix}ml_booking_equipment be ON e.id = be.equipment_id
                WHERE be.booking_id = %d
                ORDER BY e.name",
                $booking_id
            )
        );
    }
    
    /**
     * Update booking equipment costs
     */
    private function update_booking_equipment_costs($booking_id) {
        global $wpdb;
        
        $total_equipment_cost = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(rental_cost) FROM {$wpdb->prefix}ml_booking_equipment 
                WHERE booking_id = %d",
                $booking_id
            )
        );
        
        if ($total_equipment_cost > 0) {
            $booking = $this->get_booking($booking_id);
            $new_total = $booking->package_cost + $booking->travel_cost + $total_equipment_cost;
            
            $wpdb->update(
                $wpdb->prefix . 'ml_bookings',
                array(
                    'equipment_cost' => $total_equipment_cost,
                    'total_cost' => $new_total
                ),
                array('id' => $booking_id),
                array('%f', '%f'),
                array('%d')
            );
        }
    }
    
    /**
     * Get equipment statistics
     */
    public function get_equipment_statistics() {
        global $wpdb;
        
        $stats = array();
        
        // Total equipment count
        $stats['total_equipment'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ml_equipment"
        );
        
        // Equipment by status
        $status_stats = $wpdb->get_results(
            "SELECT status, COUNT(*) as count 
            FROM {$wpdb->prefix}ml_equipment 
            GROUP BY status"
        );
        
        $stats['by_status'] = array();
        foreach ($status_stats as $stat) {
            $stats['by_status'][$stat->status] = $stat->count;
        }
        
        // Equipment by type
        $type_stats = $wpdb->get_results(
            "SELECT type, COUNT(*) as count 
            FROM {$wpdb->prefix}ml_equipment 
            GROUP BY type 
            ORDER BY count DESC"
        );
        
        $stats['by_type'] = $type_stats;
        
        // Most used equipment
        $usage_stats = $wpdb->get_results(
            "SELECT e.name, e.type, COUNT(be.id) as usage_count
            FROM {$wpdb->prefix}ml_equipment e
            LEFT JOIN {$wpdb->prefix}ml_booking_equipment be ON e.id = be.equipment_id
            GROUP BY e.id
            ORDER BY usage_count DESC
            LIMIT 10"
        );
        
        $stats['most_used'] = $usage_stats;
        
        // Revenue from equipment rentals
        $stats['total_rental_revenue'] = $wpdb->get_var(
            "SELECT SUM(rental_cost) FROM {$wpdb->prefix}ml_booking_equipment 
            WHERE status = 'returned'"
        );
        
        return $stats;
    }
    
    /**
     * Check if equipment is currently assigned
     */
    private function is_equipment_assigned($equipment_id) {
        global $wpdb;
        
        $assigned_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ml_booking_equipment be
                JOIN {$wpdb->prefix}ml_bookings b ON be.booking_id = b.id
                WHERE be.equipment_id = %d 
                AND be.status IN ('assigned', 'out')
                AND b.status IN ('confirmed', 'pending')",
                $equipment_id
            )
        );
        
        return $assigned_count > 0;
    }
    
    /**
     * Get single equipment item
     */
    private function get_equipment($equipment_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ml_equipment WHERE id = %d", $equipment_id));
    }
    
    /**
     * Get booking details
     */
    private function get_booking($booking_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ml_bookings WHERE id = %d", $booking_id));
    }
    
    /**
     * Get available equipment for date range
     */
    public function get_available_equipment($start_date, $end_date = null) {
        global $wpdb;
        
        if (!$end_date) {
            $end_date = $start_date;
        }
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT e.* FROM {$wpdb->prefix}ml_equipment e
                WHERE e.status = 'available'
                AND e.id NOT IN (
                    SELECT DISTINCT be.equipment_id 
                    FROM {$wpdb->prefix}ml_booking_equipment be
                    JOIN {$wpdb->prefix}ml_bookings b ON be.booking_id = b.id
                    WHERE b.event_date >= %s 
                    AND b.event_date <= %s
                    AND be.status IN ('assigned', 'out')
                    AND b.status IN ('confirmed', 'pending')
                )
                ORDER BY e.type, e.name",
                $start_date,
                $end_date
            )
        );
    }
}

// Initialize the equipment class
ML_Equipment::get_instance();