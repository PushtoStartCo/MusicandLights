<?php
/**
 * GoHighLevel Integration Class
 * Handles all communication with GoHighLevel API for contacts, workflows, tasks, and automation
 * 
 * @version 2.0.0
 * @author Your Name
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class GHL_Integration {
    
    private $api_key;
    private $location_id;
    private $base_url = 'https://services.leadconnectorhq.com/';
    private $api_version = '2021-07-28';
    
    /**
     * Cache for API responses to reduce redundant calls
     */
    private $cache = array();
    
    /**
     * Rate limiting properties
     */
    private $last_request_time = 0;
    private $min_request_interval = 0.1; // 100ms between requests
    
    public function __construct() {
        $this->api_key = get_option('dj_hire_ghl_api_key', '');
        $this->location_id = get_option('dj_hire_ghl_location_id', '');
        
        // Initialize hooks
        $this->init_hooks();
        
        // Validate configuration on instantiation
        $this->validate_configuration();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_test_ghl_connection', array($this, 'test_connection'));
        add_action('wp_ajax_sync_booking_to_ghl', array($this, 'sync_booking_to_ghl'));
        add_action('wp_ajax_ghl_get_workflows', array($this, 'ajax_get_workflows'));
        add_action('wp_ajax_ghl_get_pipelines', array($this, 'ajax_get_pipelines'));
    }
    
    /**
     * Validate API configuration
     */
    private function validate_configuration() {
        if (empty($this->api_key)) {
            error_log('GHL Integration: API key not configured');
        }
        
        if (empty($this->location_id)) {
            error_log('GHL Integration: Location ID not configured');
        }
    }
    
    /**
     * Create or update a contact in GoHighLevel
     * 
     * @param array $contact_data Contact information
     * @return string|false Contact ID on success, false on failure
     */
    public function create_or_update_contact($contact_data) {
        if (!$this->is_configured()) {
            error_log('GHL Integration: API not properly configured');
            return false;
        }
        
        // Validate required fields
        if (empty($contact_data['email']) || !is_email($contact_data['email'])) {
            error_log('GHL Integration: Invalid or missing email address');
            return false;
        }
        
        $contact_payload = array(
            'email' => sanitize_email($contact_data['email']),
            'firstName' => sanitize_text_field($contact_data['first_name'] ?? ''),
            'lastName' => sanitize_text_field($contact_data['last_name'] ?? ''),
            'name' => sanitize_text_field($contact_data['name'] ?? ''),
            'phone' => $this->sanitize_phone($contact_data['phone'] ?? ''),
            'locationId' => $this->location_id,
            'customFields' => $this->prepare_custom_fields($contact_data),
            'tags' => array('DJ Booking', 'New Lead')
        );
        
        // Remove empty fields to avoid API errors
        $contact_payload = array_filter($contact_payload, function($value) {
            return !empty($value) || is_numeric($value);
        });
        
        try {
            // Check if contact exists first
            $existing_contact = $this->find_contact_by_email($contact_data['email']);
            
            if ($existing_contact && isset($existing_contact['id'])) {
                // Update existing contact
                $response = $this->api_request('contacts/' . $existing_contact['id'], $contact_payload, 'PUT');
                $contact_id = $existing_contact['id'];
            } else {
                // Create new contact
                $response = $this->api_request('contacts/', $contact_payload, 'POST');
                $contact_id = isset($response['contact']['id']) ? $response['contact']['id'] : false;
            }
            
            if ($contact_id) {
                // Cache the contact for future use
                $this->cache['contact_' . $contact_data['email']] = array(
                    'id' => $contact_id,
                    'timestamp' => time()
                );
                
                return $contact_id;
            }
            
        } catch (Exception $e) {
            error_log('GHL Integration: Error creating/updating contact - ' . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Prepare custom fields data with proper sanitization
     * 
     * @param array $contact_data Raw contact data
     * @return array Sanitized custom fields
     */
    private function prepare_custom_fields($contact_data) {
        $custom_fields = array();
        
        $field_mapping = array(
            'booking_id' => 'sanitize_text_field',
            'event_date' => 'sanitize_text_field',
            'dj_name' => 'sanitize_text_field',
            'total_amount' => 'floatval',
            'booking_status' => 'sanitize_text_field',
            'venue_name' => 'sanitize_text_field',
            'event_type' => 'sanitize_text_field',
            'lead_source' => 'sanitize_text_field'
        );
        
        foreach ($field_mapping as $field => $sanitizer) {
            if (isset($contact_data[$field])) {
                $custom_fields[$field] = call_user_func($sanitizer, $contact_data[$field]);
            }
        }
        
        // Set default lead source if not provided
        if (!isset($custom_fields['lead_source'])) {
            $custom_fields['lead_source'] = 'website';
        }
        
        return $custom_fields;
    }
    
    /**
     * Sanitize phone number
     * 
     * @param string $phone Raw phone number
     * @return string Sanitized phone number
     */
    private function sanitize_phone($phone) {
        // Remove all non-numeric characters except + for international numbers
        $phone = preg_replace('/[^\d\+]/', '', $phone);
        
        // Validate phone number format
        if (preg_match('/^\+?[\d]{10,15}$/', $phone)) {
            return $phone;
        }
        
        return '';
    }
    
    /**
     * Find contact by email address with caching
     * 
     * @param string $email Email address
     * @return array|false Contact data or false if not found
     */
    private function find_contact_by_email($email) {
        $email = sanitize_email($email);
        
        if (empty($email)) {
            return false;
        }
        
        // Check cache first
        $cache_key = 'contact_' . $email;
        if (isset($this->cache[$cache_key])) {
            $cached = $this->cache[$cache_key];
            // Cache for 5 minutes
            if ((time() - $cached['timestamp']) < 300) {
                return array('id' => $cached['id']);
            }
        }
        
        $params = array(
            'email' => $email,
            'locationId' => $this->location_id
        );
        
        try {
            $response = $this->api_request('contacts/search/duplicate?' . http_build_query($params), null, 'GET');
            
            if ($response && isset($response['contact'])) {
                // Cache the result
                $this->cache[$cache_key] = array(
                    'id' => $response['contact']['id'],
                    'timestamp' => time()
                );
                
                return $response['contact'];
            }
        } catch (Exception $e) {
            error_log('GHL Integration: Error finding contact by email - ' . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Trigger a workflow for a contact
     * 
     * @param string $workflow_name Workflow identifier
     * @param string $contact_id Contact ID
     * @param array $additional_data Extra data for workflow
     * @return bool Success status
     */
    public function trigger_workflow($workflow_name, $contact_id, $additional_data = array()) {
        if (!$this->is_configured() || empty($contact_id)) {
            return false;
        }
        
        $workflow_id = $this->get_workflow_id($workflow_name);
        
        if (empty($workflow_id)) {
            error_log('GHL Integration: Workflow ID not found for ' . $workflow_name);
            return false;
        }
        
        $payload = array(
            'contactId' => sanitize_text_field($contact_id),
            'eventData' => is_array($additional_data) ? $additional_data : array()
        );
        
        try {
            $response = $this->api_request('workflows/' . $workflow_id . '/subscribe', $payload, 'POST');
            return $response !== false;
        } catch (Exception $e) {
            error_log('GHL Integration: Error triggering workflow - ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get workflow ID by name
     * 
     * @param string $workflow_name Workflow name
     * @return string Workflow ID
     */
    private function get_workflow_id($workflow_name) {
        $workflow_ids = array(
            'new_booking_workflow' => get_option('dj_hire_ghl_new_booking_workflow_id', ''),
            'international_booking_workflow' => get_option('dj_hire_ghl_international_workflow_id', ''),
            'event_details_completed_workflow' => get_option('dj_hire_ghl_details_completed_workflow_id', ''),
            'deposit_paid_workflow' => get_option('dj_hire_ghl_deposit_paid_workflow_id', ''),
            'final_payment_due_workflow' => get_option('dj_hire_ghl_payment_due_workflow_id', ''),
            'final_payment_paid_workflow' => get_option('dj_hire_ghl_payment_paid_workflow_id', ''),
            'booking_completed_workflow' => get_option('dj_hire_ghl_completed_workflow_id', ''),
            'booking_cancelled_workflow' => get_option('dj_hire_ghl_cancelled_workflow_id', ''),
            'review_workflow' => get_option('dj_hire_ghl_review_workflow_id', '')
        );
        
        return $workflow_ids[$workflow_name] ?? '';
    }
    
    /**
     * Create a task for the DJ to call the client
     * 
     * @param int $booking_id Booking ID
     * @param string $contact_id GHL Contact ID
     * @return bool Success status
     */
    public function create_task_for_dj($booking_id, $contact_id) {
        if (!$this->is_configured() || empty($booking_id) || empty($contact_id)) {
            return false;
        }
        
        $booking_data = $this->get_booking_data($booking_id);
        
        if (!$booking_data) {
            error_log('GHL Integration: Booking data not found for ID ' . $booking_id);
            return false;
        }
        
        $dj_id = $booking_data['assigned_dj'] ?? '';
        $client_name = $booking_data['client_name'] ?? '';
        $event_date = $booking_data['event_date'] ?? '';
        
        if (empty($dj_id)) {
            error_log('GHL Integration: No DJ assigned to booking ' . $booking_id);
            return false;
        }
        
        // Get DJ user details
        $dj_user_id = get_post_meta($dj_id, 'dj_user_id', true);
        $dj_ghl_user_id = $this->get_dj_ghl_user_id($dj_user_id);
        
        $task_payload = array(
            'title' => sprintf('Call Client: %s - Event: %s', 
                sanitize_text_field($client_name), 
                sanitize_text_field($event_date)
            ),
            'body' => 'Please call the client to discuss their event details and requirements. All event information is available in the booking system.',
            'contactId' => sanitize_text_field($contact_id),
            'assignedTo' => $dj_ghl_user_id,
            'dueDate' => gmdate('Y-m-d\TH:i:s\Z', strtotime('+1 day')),
            'completed' => false
        );
        
        try {
            $response = $this->api_request('contacts/' . $contact_id . '/tasks/', $task_payload, 'POST');
            
            if ($response) {
                // Log the task creation
                $this->log_booking_activity($booking_id, 'system', 'DJ call task created in GoHighLevel');
                return true;
            }
        } catch (Exception $e) {
            error_log('GHL Integration: Error creating DJ task - ' . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Get booking data safely
     * 
     * @param int $booking_id Booking ID
     * @return array|false Booking data or false
     */
    private function get_booking_data($booking_id) {
        if (class_exists('DJ_Booking_System')) {
            $booking_system = new DJ_Booking_System();
            return $booking_system->get_booking_data($booking_id);
        }
        
        // Fallback to direct post meta retrieval
        $booking_data = get_post_meta($booking_id);
        if (!empty($booking_data)) {
            // Flatten the meta array
            $flattened = array();
            foreach ($booking_data as $key => $value) {
                $flattened[$key] = is_array($value) ? $value[0] : $value;
            }
            return $flattened;
        }
        
        return false;
    }
    
    /**
     * Get DJ's GoHighLevel user ID with caching
     * 
     * @param int $wp_user_id WordPress user ID
     * @return string GHL user ID
     */
    private function get_dj_ghl_user_id($wp_user_id) {
        if (empty($wp_user_id)) {
            return get_option('dj_hire_ghl_default_user_id', '');
        }
        
        // Check cache first
        $cache_key = 'ghl_user_' . $wp_user_id;
        if (isset($this->cache[$cache_key])) {
            $cached = $this->cache[$cache_key];
            if ((time() - $cached['timestamp']) < 3600) { // Cache for 1 hour
                return $cached['id'];
            }
        }
        
        // First check if we have it stored
        $ghl_user_id = get_user_meta($wp_user_id, 'ghl_user_id', true);
        
        if (!empty($ghl_user_id)) {
            $this->cache[$cache_key] = array('id' => $ghl_user_id, 'timestamp' => time());
            return $ghl_user_id;
        }
        
        // If not, try to find by email
        $user = get_user_by('ID', $wp_user_id);
        if ($user && !empty($user->user_email)) {
            try {
                $users_response = $this->api_request('users/', null, 'GET');
                if ($users_response && isset($users_response['users'])) {
                    foreach ($users_response['users'] as $ghl_user) {
                        if (isset($ghl_user['email']) && 
                            strtolower($ghl_user['email']) === strtolower($user->user_email)) {
                            
                            update_user_meta($wp_user_id, 'ghl_user_id', $ghl_user['id']);
                            $this->cache[$cache_key] = array('id' => $ghl_user['id'], 'timestamp' => time());
                            return $ghl_user['id'];
                        }
                    }
                }
            } catch (Exception $e) {
                error_log('GHL Integration: Error finding GHL user - ' . $e->getMessage());
            }
        }
        
        // Default to admin user if DJ not found
        $default_user_id = get_option('dj_hire_ghl_default_user_id', '');
        $this->cache[$cache_key] = array('id' => $default_user_id, 'timestamp' => time());
        
        return $default_user_id;
    }
    
    /**
     * Update booking status in GoHighLevel
     * 
     * @param string $contact_id GHL contact ID
     * @param int $booking_id Booking ID
     * @param string $status New status
     * @return bool Success status
     */
    public function update_booking_status($contact_id, $booking_id, $status) {
        if (!$this->is_configured() || empty($contact_id) || empty($status)) {
            return false;
        }
        
        $custom_fields = array(
            'booking_status' => sanitize_text_field($status),
            'last_updated' => current_time('mysql')
        );
        
        $payload = array(
            'customFields' => $custom_fields
        );
        
        // Add appropriate tags based on status
        $status_tags = $this->get_status_tags($status);
        if (!empty($status_tags)) {
            $payload['tags'] = $status_tags;
        }
        
        try {
            $response = $this->api_request('contacts/' . $contact_id, $payload, 'PUT');
            
            if ($response) {
                $this->log_booking_activity($booking_id, 'system', 'Booking status updated to: ' . $status);
                return true;
            }
        } catch (Exception $e) {
            error_log('GHL Integration: Error updating booking status - ' . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Get tags for booking status
     * 
     * @param string $status Booking status
     * @return array Tags array
     */
    private function get_status_tags($status) {
        $status_tags = array(
            'deposit_paid' => array('Deposit Paid', 'Active Booking'),
            'final_paid' => array('Paid in Full', 'Ready for Event'),
            'completed' => array('Event Completed', 'Past Client'),
            'cancelled' => array('Cancelled Booking', 'Lost Lead')
        );
        
        return $status_tags[$status] ?? array();
    }
    
    /**
     * Create opportunity in GoHighLevel
     * 
     * @param string $contact_id Contact ID
     * @param array $booking_data Booking information
     * @return array|false Opportunity data or false
     */
    public function create_opportunity($contact_id, $booking_data) {
        if (!$this->is_configured() || empty($contact_id)) {
            return false;
        }
        
        $pipeline_id = get_option('dj_hire_ghl_pipeline_id', '');
        $initial_stage_id = get_option('dj_hire_ghl_initial_stage_id', '');
        
        if (empty($pipeline_id) || empty($initial_stage_id)) {
            error_log('GHL Integration: Pipeline or stage ID not configured');
            return false;
        }
        
        $opportunity_payload = array(
            'pipelineId' => $pipeline_id,
            'stageId' => $initial_stage_id,
            'contactId' => sanitize_text_field($contact_id),
            'name' => sprintf('DJ Booking - %s', sanitize_text_field($booking_data['event_date'] ?? 'TBD')),
            'monetaryValue' => floatval($booking_data['total_amount'] ?? 0),
            'assignedTo' => $this->get_dj_ghl_user_id($booking_data['dj_user_id'] ?? ''),
            'status' => 'open'
        );
        
        try {
            return $this->api_request('opportunities/', $opportunity_payload, 'POST');
        } catch (Exception $e) {
            error_log('GHL Integration: Error creating opportunity - ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send SMS via GoHighLevel
     * 
     * @param string $contact_id Contact ID
     * @param string $message Message content
     * @return bool Success status
     */
    public function send_sms($contact_id, $message) {
        if (!$this->is_configured() || empty($contact_id) || empty($message)) {
            return false;
        }
        
        $payload = array(
            'contactId' => sanitize_text_field($contact_id),
            'message' => sanitize_textarea_field($message),
            'type' => 'SMS'
        );
        
        try {
            $response = $this->api_request('conversations/messages', $payload, 'POST');
            return $response !== false;
        } catch (Exception $e) {
            error_log('GHL Integration: Error sending SMS - ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email via GoHighLevel
     * 
     * @param string $contact_id Contact ID
     * @param string $subject Email subject
     * @param string $html_body Email content
     * @param string|null $template_id Optional template ID
     * @return bool Success status
     */
    public function send_email($contact_id, $subject, $html_body, $template_id = null) {
        if (!$this->is_configured() || empty($contact_id) || empty($subject) || empty($html_body)) {
            return false;
        }
        
        $payload = array(
            'type' => 'Email',
            'contactId' => sanitize_text_field($contact_id),
            'subject' => sanitize_text_field($subject),
            'html' => wp_kses_post($html_body)
        );
        
        if (!empty($template_id)) {
            $payload['templateId'] = sanitize_text_field($template_id);
        }
        
        try {
            $response = $this->api_request('conversations/messages', $payload, 'POST');
            return $response !== false;
        } catch (Exception $e) {
            error_log('GHL Integration: Error sending email - ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all workflows with caching
     * 
     * @return array|false Workflows data or false
     */
    public function get_workflows() {
        $cache_key = 'workflows';
        
        // Check cache first (cache for 1 hour)
        if (isset($this->cache[$cache_key])) {
            $cached = $this->cache[$cache_key];
            if ((time() - $cached['timestamp']) < 3600) {
                return $cached['data'];
            }
        }
        
        try {
            $response = $this->api_request('workflows/', null, 'GET');
            
            if ($response) {
                $this->cache[$cache_key] = array(
                    'data' => $response,
                    'timestamp' => time()
                );
                return $response;
            }
        } catch (Exception $e) {
            error_log('GHL Integration: Error getting workflows - ' . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Get all pipelines with caching
     * 
     * @return array|false Pipelines data or false
     */
    public function get_pipelines() {
        $cache_key = 'pipelines';
        
        // Check cache first (cache for 1 hour)
        if (isset($this->cache[$cache_key])) {
            $cached = $this->cache[$cache_key];
            if ((time() - $cached['timestamp']) < 3600) {
                return $cached['data'];
            }
        }
        
        try {
            $response = $this->api_request('pipelines/', null, 'GET');
            
            if ($response) {
                $this->cache[$cache_key] = array(
                    'data' => $response,
                    'timestamp' => time()
                );
                return $response;
            }
        } catch (Exception $e) {
            error_log('GHL Integration: Error getting pipelines - ' . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Create calendar event
     * 
     * @param int $booking_id Booking ID
     * @param string $contact_id Contact ID
     * @return array|false Calendar event data or false
     */
    public function create_calendar_event($booking_id, $contact_id) {
        if (!$this->is_configured() || empty($booking_id) || empty($contact_id)) {
            return false;
        }
        
        $booking_data = $this->get_booking_data($booking_id);
        
        if (!$booking_data) {
            return false;
        }
        
        $event_date = $booking_data['event_date'] ?? '';
        $event_time = $booking_data['event_time'] ?? '18:00';
        $event_duration = intval($booking_data['event_duration'] ?? 4);
        $client_name = $booking_data['client_name'] ?? '';
        $venue_name = $booking_data['venue_name'] ?? '';
        
        if (empty($event_date)) {
            error_log('GHL Integration: Event date not found for booking ' . $booking_id);
            return false;
        }
        
        // Validate and format datetime
        $start_datetime = $this->format_datetime($event_date, $event_time);
        if (!$start_datetime) {
            error_log('GHL Integration: Invalid event date/time format');
            return false;
        }
        
        $end_datetime = gmdate('Y-m-d\TH:i:s', strtotime($start_datetime . ' +' . $event_duration . ' hours'));
        
        $calendar_id = get_option('dj_hire_ghl_calendar_id', '');
        if (empty($calendar_id)) {
            error_log('GHL Integration: Calendar ID not configured');
            return false;
        }
        
        $event_payload = array(
            'calendarId' => $calendar_id,
            'contactId' => sanitize_text_field($contact_id),
            'title' => sprintf('DJ Event: %s', sanitize_text_field($client_name)),
            'description' => sprintf('DJ booking at %s', sanitize_text_field($venue_name)),
            'startTime' => $start_datetime,
            'endTime' => $end_datetime,
            'eventType' => 'Event'
        );
        
        try {
            $response = $this->api_request('calendars/events/', $event_payload, 'POST');
            
            if ($response) {
                $this->log_booking_activity($booking_id, 'system', 'Calendar event created in GoHighLevel');
                return $response;
            }
        } catch (Exception $e) {
            error_log('GHL Integration: Error creating calendar event - ' . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Format date and time for API
     * 
     * @param string $date Date string
     * @param string $time Time string
     * @return string|false Formatted datetime or false
     */
    private function format_datetime($date, $time) {
        try {
            $datetime = $date . 'T' . $time . ':00';
            
            // Validate the datetime format
            $dt = DateTime::createFromFormat('Y-m-d\TH:i:s', $datetime);
            if ($dt && $dt->format('Y-m-d\TH:i:s') === $datetime) {
                return $datetime;
            }
        } catch (Exception $e) {
            error_log('GHL Integration: DateTime formatting error - ' . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Handle webhook from GoHighLevel
     * 
     * @param array $webhook_data Webhook payload
     */
    public function handle_webhook($webhook_data) {
        if (!is_array($webhook_data)) {
            error_log('GHL Webhook: Invalid webhook data received');
            return;
        }
        
        $event_type = $webhook_data['type'] ?? '';
        
        if (empty($event_type)) {
            error_log('GHL Webhook: No event type specified');
            return;
        }
        
        try {
            switch ($event_type) {
                case 'ContactCreate':
                case 'ContactUpdate':
                    $this->handle_contact_webhook($webhook_data);
                    break;
                    
                case 'TaskComplete':
                    $this->handle_task_complete_webhook($webhook_data);
                    break;
                    
                case 'OpportunityStageChange':
                    $this->handle_opportunity_stage_change($webhook_data);
                    break;
                    
                case 'CalendarEventCreate':
                case 'CalendarEventUpdate':
                    $this->handle_calendar_webhook($webhook_data);
                    break;
                    
                default:
                    error_log('GHL Webhook: Unknown event type - ' . $event_type);
                    break;
            }
        } catch (Exception $e) {
            error_log('GHL Webhook: Error handling webhook - ' . $e->getMessage());
        }
    }
    
    /**
     * Handle contact webhook events
     * 
     * @param array $webhook_data Webhook data
     */
    private function handle_contact_webhook($webhook_data) {
        $contact_data = $webhook_data['contact'] ?? array();
        $booking_id = $contact_data['customFields']['booking_id'] ?? '';
        
        if (!empty($booking_id)) {
            $this->log_booking_activity(
                $booking_id, 
                'system', 
                'Contact updated in GoHighLevel'
            );
        }
    }
    
    /**
     * Handle task completion webhook
     * 
     * @param array $webhook_data Webhook data
     */
    private function handle_task_complete_webhook($webhook_data) {
        $task_data = $webhook_data['task'] ?? array();
        $contact_id = $task_data['contactId'] ?? '';
        
        if (empty($contact_id)) {
            return;
        }
        
        $booking_id = $this->find_booking_by_contact_id($contact_id);
        
        if ($booking_id) {
            // Check if this was a DJ call task
            $task_title = $task_data['title'] ?? '';
            if (strpos($task_title, 'Call Client:') !== false) {
                $this->log_booking_activity(
                    $booking_id, 
                    'phone', 
                    'DJ completed call task: ' . sanitize_text_field($task_title)
                );
                
                // Update booking status to quote sent
                wp_update_post(array(
                    'ID' => $booking_id,
                    'post_status' => 'quote_sent'
                ));
            }
        }
    }
    
    /**
     * Handle opportunity stage changes
     * 
     * @param array $webhook_data Webhook data
     */
    private function handle_opportunity_stage_change($webhook_data) {
        $opportunity_data = $webhook_data['opportunity'] ?? array();
        $contact_id = $opportunity_data['contactId'] ?? '';
        $stage_id = $opportunity_data['stageId'] ?? '';
        
        if (empty($contact_id) || empty($stage_id)) {
            return;
        }
        
        $stage_mapping = $this->get_stage_mapping();
        
        if (isset($stage_mapping[$stage_id])) {
            $new_status = $stage_mapping[$stage_id];
            $booking_id = $this->find_booking_by_contact_id($contact_id);
            
            if ($booking_id) {
                wp_update_post(array(
                    'ID' => $booking_id,
                    'post_status' => $new_status
                ));
                
                $this->log_booking_activity(
                    $booking_id, 
                    'system', 
                    'Booking status updated from GHL stage change: ' . $new_status
                );
            }
        }
    }
    
    /**
     * Get stage to status mapping
     * 
     * @return array Stage mapping
     */
    private function get_stage_mapping() {
        return array(
            get_option('dj_hire_ghl_quote_stage_id', '') => 'quote_sent',
            get_option('dj_hire_ghl_deposit_stage_id', '') => 'deposit_pending',
            get_option('dj_hire_ghl_confirmed_stage_id', '') => 'confirmed',
            get_option('dj_hire_ghl_completed_stage_id', '') => 'completed'
        );
    }
    
    /**
     * Handle calendar webhook events
     * 
     * @param array $webhook_data Webhook data
     */
    private function handle_calendar_webhook($webhook_data) {
        $event_data = $webhook_data['event'] ?? array();
        $contact_id = $event_data['contactId'] ?? '';
        
        if (!empty($contact_id)) {
            $booking_id = $this->find_booking_by_contact_id($contact_id);
            
            if ($booking_id) {
                $event_title = $event_data['title'] ?? 'Unknown Event';
                $this->log_booking_activity(
                    $booking_id, 
                    'system', 
                    'Calendar event updated in GoHighLevel: ' . sanitize_text_field($event_title)
                );
            }
        }
    }
    
    /**
     * Find booking by GHL contact ID
     * 
     * @param string $contact_id GHL contact ID
     * @return int|false Booking ID or false
     */
    private function find_booking_by_contact_id($contact_id) {
        $bookings = get_posts(array(
            'post_type' => 'dj_booking',
            'meta_query' => array(
                array(
                    'key' => 'ghl_contact_id',
                    'value' => sanitize_text_field($contact_id),
                    'compare' => '='
                )
            ),
            'posts_per_page' => 1,
            'post_status' => 'any',
            'fields' => 'ids'
        ));
        
        return !empty($bookings) ? $bookings[0] : false;
    }
    
    /**
     * Log booking activity
     * 
     * @param int $booking_id Booking ID
     * @param string $type Log type
     * @param string $message Log message
     */
    private function log_booking_activity($booking_id, $type, $message) {
        if (class_exists('DJ_Booking_System')) {
            $booking_system = new DJ_Booking_System();
            $booking_system->add_communication_log($booking_id, $type, $message);
        } else {
            // Fallback logging
            error_log(sprintf('Booking %d (%s): %s', $booking_id, $type, $message));
        }
    }
    
    /**
     * Test connection to GoHighLevel API
     */
    public function test_connection() {
        check_ajax_referer('dj_hire_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        if (!$this->is_configured()) {
            wp_send_json_error('API not configured properly');
            return;
        }
        
        try {
            $response = $this->api_request('locations/' . $this->location_id, null, 'GET');
            
            if ($response) {
                wp_send_json_success(array(
                    'message' => 'Connection successful',
                    'location' => $response['location']['name'] ?? 'Unknown Location'
                ));
            } else {
                wp_send_json_error('Connection failed - invalid response');
            }
        } catch (Exception $e) {
            wp_send_json_error('Connection failed: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX handler for getting workflows
     */
    public function ajax_get_workflows() {
        check_ajax_referer('dj_hire_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $workflows = $this->get_workflows();
        
        if ($workflows) {
            wp_send_json_success($workflows);
        } else {
            wp_send_json_error('Failed to fetch workflows');
        }
    }
    
    /**
     * AJAX handler for getting pipelines
     */
    public function ajax_get_pipelines() {
        check_ajax_referer('dj_hire_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $pipelines = $this->get_pipelines();
        
        if ($pipelines) {
            wp_send_json_success($pipelines);
        } else {
            wp_send_json_error('Failed to fetch pipelines');
        }
    }
    
    /**
     * Sync booking data to GoHighLevel opportunity
     */
    public function sync_booking_to_ghl() {
        check_ajax_referer('dj_hire_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $booking_id = intval($_POST['booking_id'] ?? 0);
        
        if (!$booking_id) {
            wp_send_json_error('Invalid booking ID');
            return;
        }
        
        if (!$this->is_configured()) {
            wp_send_json_error('GHL not configured properly');
            return;
        }
        
        $booking_data = $this->get_booking_data($booking_id);
        
        if (!$booking_data) {
            wp_send_json_error('Booking data not found');
            return;
        }
        
        try {
            $ghl_contact_id = $booking_data['ghl_contact_id'] ?? '';
            
            if (empty($ghl_contact_id)) {
                // Create contact first
                $ghl_contact_id = $this->create_or_update_contact($booking_data);
                if ($ghl_contact_id) {
                    update_post_meta($booking_id, 'ghl_contact_id', $ghl_contact_id);
                } else {
                    wp_send_json_error('Failed to create GHL contact');
                    return;
                }
            }
            
            // Create opportunity
            $opportunity = $this->create_opportunity($ghl_contact_id, $booking_data);
            
            // Create calendar event
            $calendar_event = $this->create_calendar_event($booking_id, $ghl_contact_id);
            
            wp_send_json_success(array(
                'contact_id' => $ghl_contact_id,
                'opportunity_created' => $opportunity !== false,
                'calendar_event_created' => $calendar_event !== false
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Sync failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Check if API is properly configured
     * 
     * @return bool Configuration status
     */
    private function is_configured() {
        return !empty($this->api_key) && !empty($this->location_id);
    }
    
    /**
     * Get configuration status
     * 
     * @return array Configuration status details
     */
    public function get_configuration_status() {
        return array(
            'api_key_configured' => !empty($this->api_key),
            'location_id_configured' => !empty($this->location_id),
            'workflows_configured' => $this->check_workflows_configured(),
            'custom_fields_setup' => $this->check_custom_fields_setup(),
            'pipeline_configured' => !empty(get_option('dj_hire_ghl_pipeline_id', '')),
            'calendar_configured' => !empty(get_option('dj_hire_ghl_calendar_id', ''))
        );
    }
    
    /**
     * Check if workflows are configured
     * 
     * @return bool Workflow configuration status
     */
    private function check_workflows_configured() {
        $required_workflows = array(
            'dj_hire_ghl_new_booking_workflow_id',
            'dj_hire_ghl_deposit_paid_workflow_id',
            'dj_hire_ghl_completed_workflow_id'
        );
        
        foreach ($required_workflows as $workflow_option) {
            if (empty(get_option($workflow_option, ''))) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Check if custom fields are set up
     * 
     * @return bool Custom fields setup status
     */
    private function check_custom_fields_setup() {
        return get_option('dj_hire_ghl_setup_completed', false);
    }
    
    /**
     * Setup automation workflows and custom fields
     * 
     * @return array Setup results
     */
    public function setup_automation() {
        $setup_results = array(
            'custom_fields' => array(),
            'errors' => array()
        );
        
        // Create custom fields
        $custom_fields = array(
            'booking_id' => 'TEXT',
            'event_date' => 'DATE',
            'dj_name' => 'TEXT',
            'total_amount' => 'NUMBER',
            'booking_status' => 'TEXT',
            'venue_name' => 'TEXT',
            'event_type' => 'TEXT',
            'deposit_amount' => 'NUMBER',
            'final_payment_amount' => 'NUMBER',
            'lead_source' => 'TEXT'
        );
        
        foreach ($custom_fields as $field_name => $field_type) {
            try {
                $result = $this->create_custom_field($field_name, $field_type);
                $setup_results['custom_fields'][$field_name] = $result !== false;
            } catch (Exception $e) {
                $setup_results['errors'][] = 'Failed to create field ' . $field_name . ': ' . $e->getMessage();
                $setup_results['custom_fields'][$field_name] = false;
            }
        }
        
        // Mark setup as completed if no errors
        if (empty($setup_results['errors'])) {
            update_option('dj_hire_ghl_setup_completed', true);
        }
        
        return $setup_results;
    }
    
    /**
     * Create or update custom field in GoHighLevel
     * 
     * @param string $field_name Field name
     * @param string $field_type Field type
     * @return array|false Field data or false
     */
    public function create_custom_field($field_name, $field_type = 'TEXT') {
        if (!$this->is_configured()) {
            return false;
        }
        
        $payload = array(
            'name' => sanitize_text_field($field_name),
            'fieldKey' => sanitize_key($field_name),
            'dataType' => sanitize_text_field($field_type),
            'position' => 1
        );
        
        try {
            return $this->api_request('custom-fields/', $payload, 'POST');
        } catch (Exception $e) {
            error_log('GHL Integration: Error creating custom field - ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Rate limiting for API requests
     */
    private function enforce_rate_limit() {
        $current_time = microtime(true);
        $time_since_last = $current_time - $this->last_request_time;
        
        if ($time_since_last < $this->min_request_interval) {
            usleep(($this->min_request_interval - $time_since_last) * 1000000);
        }
        
        $this->last_request_time = microtime(true);
    }
    
    /**
     * Make API request to GoHighLevel with improved error handling and rate limiting
     * 
     * @param string $endpoint API endpoint
     * @param array|null $data Request data
     * @param string $method HTTP method
     * @return array|false Response data or false
     * @throws Exception On API errors
     */
    private function api_request($endpoint, $data = null, $method = 'GET') {
        if (!$this->is_configured()) {
            throw new Exception('API not configured properly');
        }
        
        // Enforce rate limiting
        $this->enforce_rate_limit();
        
        $url = $this->base_url . ltrim($endpoint, '/');
        
        $args = array(
            'method' => strtoupper($method),
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
                'Version' => $this->api_version,
                'User-Agent' => 'DJ-Hire-System/1.0'
            ),
            'timeout' => 30,
            'sslverify' => true,
            'httpversion' => '1.1'
        );
        
        if ($data && in_array(strtoupper($method), array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = wp_json_encode($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->log_api_activity($endpoint, $method, $data, 'ERROR: ' . $error_message);
            throw new Exception('API request failed: ' . $error_message);
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // Log API activity
        $this->log_api_activity($endpoint, $method, $data, $response_body);
        
        if ($response_code >= 200 && $response_code < 300) {
            $decoded = json_decode($response_body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON response from API');
            }
            
            return $decoded !== null ? $decoded : array();
        } else {
            // Handle specific HTTP error codes
            $error_message = $this->get_error_message($response_code, $response_body);
            throw new Exception('HTTP ' . $response_code . ': ' . $error_message);
        }
    }
    
    /**
     * Get user-friendly error message based on HTTP status code
     * 
     * @param int $code HTTP status code
     * @param string $body Response body
     * @return string Error message
     */
    private function get_error_message($code, $body) {
        $status_messages = array(
            400 => 'Bad Request - Invalid data sent to API',
            401 => 'Unauthorized - Invalid API key',
            403 => 'Forbidden - Access denied',
            404 => 'Not Found - Resource does not exist',
            422 => 'Unprocessable Entity - Data validation failed',
            429 => 'Too Many Requests - Rate limit exceeded',
            500 => 'Internal Server Error - API server error',
            502 => 'Bad Gateway - API server temporarily unavailable',
            503 => 'Service Unavailable - API maintenance mode'
        );
        
        $default_message = $status_messages[$code] ?? 'Unknown error occurred';
        
        // Try to extract error message from response body
        $decoded = json_decode($body, true);
        if ($decoded && isset($decoded['message'])) {
            return $decoded['message'];
        }
        
        return $default_message;
    }
    
    /**
     * Log API activity for debugging
     * 
     * @param string $endpoint API endpoint
     * @param string $method HTTP method
     * @param mixed $data Request data
     * @param mixed $response Response data
     */
    private function log_api_activity($endpoint, $method, $data, $response) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'method' => strtoupper($method),
            'endpoint' => $endpoint,
            'request_data' => $data,
            'response' => is_string($response) ? $response : wp_json_encode($response)
        );
        
        error_log('GHL API Activity: ' . wp_json_encode($log_entry));
    }
}

// Improved webhook handling
add_action('wp_ajax_nopriv_ghl_webhook', 'handle_ghl_webhook_improved');
add_action('wp_ajax_ghl_webhook', 'handle_ghl_webhook_improved');

/**
 * Improved webhook handler with better security and error handling
 */
function handle_ghl_webhook_improved() {
    // Set proper headers
    header('Content-Type: application/json');
    
    try {
        // Get raw input
        $raw_input = file_get_contents('php://input');
        
        if (empty($raw_input)) {
            throw new Exception('Empty webhook payload');
        }
        
        // Decode JSON
        $webhook_data = json_decode($raw_input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON in webhook payload: ' . json_last_error_msg());
        }
        
        // Verify webhook signature if configured
        $webhook_secret = get_option('dj_hire_ghl_webhook_secret', '');
        if (!empty($webhook_secret)) {
            $signature = $_SERVER['HTTP_X_GHL_SIGNATURE'] ?? '';
            
            if (empty($signature)) {
                throw new Exception('Missing webhook signature');
            }
            
            $expected_signature = hash_hmac('sha256', $raw_input, $webhook_secret);
            
            if (!hash_equals($expected_signature, $signature)) {
                throw new Exception('Invalid webhook signature');
            }
        }
        
        // Log webhook received
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('GHL Webhook received: ' . wp_json_encode($webhook_data));
        }
        
        // Process webhook
        if (class_exists('GHL_Integration')) {
            $ghl_integration = new GHL_Integration();
            $ghl_integration->handle_webhook($webhook_data);
            
            wp_send_json_success(array('message' => 'Webhook processed successfully'));
        } else {
            throw new Exception('GHL_Integration class not found');
        }
        
    } catch (Exception $e) {
        error_log('GHL Webhook Error: ' . $e->getMessage());
        
        http_response_code(400);
        wp_send_json_error(array(
            'message' => 'Webhook processing failed',
            'error' => $e->getMessage()
        ));
    }
}
?>