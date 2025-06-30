<?php
/**
 * Music & Lights GoHighLevel Integration Class
 * 
 * Handles CRM integration with GoHighLevel including contact sync,
 * opportunity creation, and workflow automation.
 * 
 * @package MusicAndLights
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class ML_GoHighLevel {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * GoHighLevel API settings
     */
    private $api_key;
    private $location_id;
    private $api_url = 'https://rest.gohighlevel.com/v1/';
    private $enabled;
    
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
        $this->init();
        $this->init_hooks();
    }
    
    /**
     * Initialize settings
     */
    private function init() {
        $settings = get_option('ml_settings', array());
        $this->api_key = isset($settings['ghl_api_key']) ? $settings['ghl_api_key'] : '';
        $this->location_id = isset($settings['ghl_location_id']) ? $settings['ghl_location_id'] : '';
        $this->enabled = isset($settings['ghl_enabled']) ? $settings['ghl_enabled'] : false;
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        if ($this->enabled && $this->is_configured()) {
            add_action('ml_booking_created', array($this, 'sync_booking_to_ghl'), 10, 1);
            add_action('ml_booking_status_changed', array($this, 'update_opportunity_status'), 10, 2);
            add_action('ml_booking_deposit_paid', array($this, 'trigger_deposit_workflow'), 10, 1);
            add_action('ml_booking_completed', array($this, 'trigger_completion_workflow'), 10, 1);
            add_action('wp_ajax_ml_test_ghl_connection', array($this, 'test_connection'));
            add_action('wp_ajax_ml_sync_all_bookings', array($this, 'sync_all_bookings'));
        }
    }
    
    /**
     * Check if GoHighLevel is configured
     */
    public function is_configured() {
        return !empty($this->api_key) && !empty($this->location_id);
    }
    
    /**
     * Test API connection
     */
    public function test_connection() {
        if (!wp_verify_nonce($_POST['nonce'], 'ml_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        $response = $this->make_request('GET', 'locations/' . $this->location_id);
        
        if (is_wp_error($response)) {
            wp_send_json_error('Connection failed: ' . $response->get_error_message());
        } else {
            wp_send_json_success('Connection successful! Location: ' . $response['name']);
        }
    }
    
    /**
     * Sync booking to GoHighLevel
     */
    public function sync_booking_to_ghl($booking_id) {
        $booking = $this->get_booking($booking_id);
        if (!$booking) {
            return false;
        }
        
        // Create or update contact
        $contact_id = $this->create_or_update_contact($booking);
        if (is_wp_error($contact_id)) {
            $this->log_sync_error($booking_id, 'contact_creation', $contact_id->get_error_message());
            return false;
        }
        
        // Create opportunity
        $opportunity_id = $this->create_opportunity($booking, $contact_id);
        if (is_wp_error($opportunity_id)) {
            $this->log_sync_error($booking_id, 'opportunity_creation', $opportunity_id->get_error_message());
            return false;
        }
        
        // Update booking with GHL IDs
        $this->update_booking_ghl_ids($booking_id, $contact_id, $opportunity_id);
        
        // Trigger workflow
        $this->trigger_workflow('booking_created', $contact_id, array(
            'booking_id' => $booking_id,
            'event_date' => $booking->event_date,
            'total_cost' => $booking->total_cost
        ));
        
        $this->log_sync_success($booking_id, 'booking_synced', array(
            'contact_id' => $contact_id,
            'opportunity_id' => $opportunity_id
        ));
        
        return true;
    }
    
    /**
     * Create or update contact
     */
    private function create_or_update_contact($booking) {
        // Check if contact exists
        $existing_contact = $this->find_contact_by_email($booking->email);
        
        $contact_data = array(
            'firstName' => $booking->first_name,
            'lastName' => $booking->last_name,
            'email' => $booking->email,
            'phone' => $booking->phone,
            'address1' => $booking->address,
            'city' => $booking->city,
            'postalCode' => $booking->postcode,
            'country' => 'UK',
            'source' => 'Music & Lights Website',
            'tags' => array('DJ Booking', 'Website Lead'),
            'customFields' => array(
                array(
                    'key' => 'event_type',
                    'value' => $booking->event_type
                ),
                array(
                    'key' => 'event_date',
                    'value' => $booking->event_date
                ),
                array(
                    'key' => 'venue_name',
                    'value' => $booking->venue_name
                ),
                array(
                    'key' => 'guest_count',
                    'value' => $booking->guest_count
                )
            )
        );
        
        if ($existing_contact) {
            // Update existing contact
            $response = $this->make_request('PUT', 'contacts/' . $existing_contact['id'], $contact_data);
            return is_wp_error($response) ? $response : $existing_contact['id'];
        } else {
            // Create new contact
            $response = $this->make_request('POST', 'contacts/', $contact_data);
            return is_wp_error($response) ? $response : $response['contact']['id'];
        }
    }
    
    /**
     * Find contact by email
     */
    private function find_contact_by_email($email) {
        $response = $this->make_request('GET', 'contacts/', array(
            'email' => $email
        ));
        
        if (is_wp_error($response) || empty($response['contacts'])) {
            return false;
        }
        
        return $response['contacts'][0];
    }
    
    /**
     * Create opportunity
     */
    private function create_opportunity($booking, $contact_id) {
        $opportunity_data = array(
            'title' => 'DJ Booking - ' . $booking->event_date,
            'status' => $this->get_opportunity_status($booking->status),
            'stage' => $this->get_opportunity_stage($booking->status),
            'value' => $booking->total_cost * 100, // Convert to pence
            'currency' => 'GBP',
            'contactId' => $contact_id,
            'source' => 'Website',
            'assignedTo' => $this->get_assigned_user(),
            'customFields' => array(
                array(
                    'key' => 'booking_id',
                    'value' => $booking->id
                ),
                array(
                    'key' => 'event_date',
                    'value' => $booking->event_date
                ),
                array(
                    'key' => 'event_time',
                    'value' => $booking->event_time
                ),
                array(
                    'key' => 'dj_package',
                    'value' => $booking->package_name
                )
            )
        );
        
        $response = $this->make_request('POST', 'opportunities/', $opportunity_data);
        return is_wp_error($response) ? $response : $response['opportunity']['id'];
    }
    
    /**
     * Update opportunity status
     */
    public function update_opportunity_status($booking_id, $new_status) {
        $booking = $this->get_booking($booking_id);
        if (!$booking || !$booking->ghl_opportunity_id) {
            return false;
        }
        
        $update_data = array(
            'status' => $this->get_opportunity_status($new_status),
            'stage' => $this->get_opportunity_stage($new_status)
        );
        
        $response = $this->make_request('PUT', 'opportunities/' . $booking->ghl_opportunity_id, $update_data);
        
        if (!is_wp_error($response)) {
            $this->log_sync_success($booking_id, 'opportunity_updated', array(
                'new_status' => $new_status,
                'opportunity_id' => $booking->ghl_opportunity_id
            ));
        }
        
        return !is_wp_error($response);
    }
    
    /**
     * Trigger workflow
     */
    private function trigger_workflow($workflow_name, $contact_id, $data = array()) {
        $workflow_hooks = array(
            'booking_created' => 'booking-received',
            'deposit_paid' => 'deposit-received',
            'booking_completed' => 'event-completed'
        );
        
        if (!isset($workflow_hooks[$workflow_name])) {
            return false;
        }
        
        $webhook_data = array_merge(array(
            'contact_id' => $contact_id,
            'event' => $workflow_name,
            'timestamp' => current_time('c')
        ), $data);
        
        $response = $this->make_request('POST', 'hooks/' . $workflow_hooks[$workflow_name], $webhook_data);
        
        return !is_wp_error($response);
    }
    
    /**
     * Trigger deposit workflow
     */
    public function trigger_deposit_workflow($booking_id) {
        $booking = $this->get_booking($booking_id);
        if (!$booking || !$booking->ghl_contact_id) {
            return false;
        }
        
        return $this->trigger_workflow('deposit_paid', $booking->ghl_contact_id, array(
            'booking_id' => $booking_id,
            'deposit_amount' => $booking->deposit_amount
        ));
    }
    
    /**
     * Trigger completion workflow
     */
    public function trigger_completion_workflow($booking_id) {
        $booking = $this->get_booking($booking_id);
        if (!$booking || !$booking->ghl_contact_id) {
            return false;
        }
        
        return $this->trigger_workflow('booking_completed', $booking->ghl_contact_id, array(
            'booking_id' => $booking_id,
            'final_amount' => $booking->total_cost
        ));
    }
    
    /**
     * Sync all bookings
     */
    public function sync_all_bookings() {
        if (!wp_verify_nonce($_POST['nonce'], 'ml_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        global $wpdb;
        $bookings = $wpdb->get_results(
            "SELECT id FROM {$wpdb->prefix}ml_bookings WHERE ghl_contact_id IS NULL OR ghl_opportunity_id IS NULL"
        );
        
        $synced_count = 0;
        $failed_count = 0;
        
        foreach ($bookings as $booking) {
            if ($this->sync_booking_to_ghl($booking->id)) {
                $synced_count++;
            } else {
                $failed_count++;
            }
            
            // Add small delay to avoid rate limiting
            usleep(200000); // 0.2 seconds
        }
        
        wp_send_json_success(array(
            'synced' => $synced_count,
            'failed' => $failed_count,
            'message' => "Synced {$synced_count} bookings, {$failed_count} failed."
        ));
    }
    
    /**
     * Make API request
     */
    private function make_request($method, $endpoint, $data = array()) {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', 'GoHighLevel API not configured');
        }
        
        $url = $this->api_url . $endpoint;
        
        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        );
        
        if ($method === 'GET' && !empty($data)) {
            $url = add_query_arg($data, $url);
        } elseif (in_array($method, array('POST', 'PUT', 'PATCH')) && !empty($data)) {
            $args['body'] = json_encode($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded_body = json_decode($body, true);
        
        if ($status_code < 200 || $status_code >= 300) {
            $error_message = isset($decoded_body['message']) ? $decoded_body['message'] : 'API request failed';
            return new WP_Error('api_error', $error_message, array('status_code' => $status_code));
        }
        
        return $decoded_body;
    }
    
    /**
     * Get opportunity status
     */
    private function get_opportunity_status($booking_status) {
        $status_map = array(
            'pending' => 'open',
            'confirmed' => 'won',
            'cancelled' => 'lost',
            'completed' => 'won'
        );
        
        return isset($status_map[$booking_status]) ? $status_map[$booking_status] : 'open';
    }
    
    /**
     * Get opportunity stage
     */
    private function get_opportunity_stage($booking_status) {
        $stage_map = array(
            'pending' => 'New Lead',
            'confirmed' => 'Deposit Paid',
            'cancelled' => 'Lost',
            'completed' => 'Event Completed'
        );
        
        return isset($stage_map[$booking_status]) ? $stage_map[$booking_status] : 'New Lead';
    }
    
    /**
     * Get assigned user
     */
    private function get_assigned_user() {
        $settings = get_option('ml_settings', array());
        return isset($settings['ghl_assigned_user']) ? $settings['ghl_assigned_user'] : null;
    }
    
    /**
     * Update booking with GHL IDs
     */
    private function update_booking_ghl_ids($booking_id, $contact_id, $opportunity_id) {
        global $wpdb;
        
        $wpdb->update(
            $wpdb->prefix . 'ml_bookings',
            array(
                'ghl_contact_id' => $contact_id,
                'ghl_opportunity_id' => $opportunity_id,
                'ghl_synced_at' => current_time('mysql')
            ),
            array('id' => $booking_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * Get booking
     */
    private function get_booking($booking_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ml_bookings WHERE id = %d", $booking_id));
    }
    
    /**
     * Log sync success
     */
    private function log_sync_success($booking_id, $action, $data = array()) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'ml_ghl_sync_log',
            array(
                'booking_id' => $booking_id,
                'action' => $action,
                'status' => 'success',
                'response_data' => json_encode($data),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Log sync error
     */
    private function log_sync_error($booking_id, $action, $error_message) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'ml_ghl_sync_log',
            array(
                'booking_id' => $booking_id,
                'action' => $action,
                'status' => 'error',
                'error_message' => $error_message,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );
    }
}

// Initialize the GoHighLevel integration
ML_GoHighLevel::get_instance();