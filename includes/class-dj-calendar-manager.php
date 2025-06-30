<?php
/**
 * DJ Calendar Manager Class
 * Handles DJ availability, bookings, and calendar synchronization
 */

class DJ_Calendar_Manager {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_ajax_update_dj_availability', array($this, 'update_availability'));
        add_action('wp_ajax_get_dj_calendar', array($this, 'get_calendar'));
        add_action('wp_ajax_bulk_update_availability', array($this, 'bulk_update_availability'));
        add_action('wp_ajax_import_external_calendar', array($this, 'import_external_calendar'));
    }
    
    public function init() {
        // Hook into availability changes for monitoring
        add_action('dj_availability_changed', array($this, 'log_availability_change'), 10, 4);
        
        // Schedule daily availability sync
        if (!wp_next_scheduled('dj_daily_availability_sync')) {
            wp_schedule_event(time(), 'daily', 'dj_daily_availability_sync');
        }
        add_action('dj_daily_availability_sync', array($this, 'sync_all_calendars'));
        
        // Clean up old availability records
        if (!wp_next_scheduled('dj_cleanup_old_availability')) {
            wp_schedule_event(time(), 'weekly', 'dj_cleanup_old_availability');
        }
        add_action('dj_cleanup_old_availability', array($this, 'cleanup_old_availability'));
    }
    
    /**
     * Check if DJ is available on a specific date
     */
    public function check_availability($dj_id, $date) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_availability';
        
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM $table_name WHERE dj_id = %d AND date = %s ORDER BY created_at DESC LIMIT 1",
            $dj_id, $date
        ));
        
        // If no record exists, assume available
        return $status !== 'unavailable' && $status !== 'booked';
    }
    
    /**
     * Get availability status for a specific date
     */
    public function get_availability_status($dj_id, $date) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_availability';
        
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM $table_name WHERE dj_id = %d AND date = %s ORDER BY created_at DESC LIMIT 1",
            $dj_id, $date
        ));
        
        return $status ?: 'available';
    }
    
    /**
     * Reserve a date for a booking
     */
    public function reserve_date($dj_id, $date, $booking_id = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_availability';
        
        $old_status = $this->get_availability_status($dj_id, $date);
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'dj_id' => $dj_id,
                'date' => $date,
                'status' => 'booked',
                'booking_id' => $booking_id,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%d', '%s')
        );
        
        if ($result) {
            // Trigger availability change hook for monitoring
            do_action('dj_availability_changed', $dj_id, $date, $old_status, 'booked');
            
            // Update GoHighLevel calendar if integrated
            $this->sync_to_ghl_calendar($dj_id, $date, 'booked', $booking_id);
        }
        
        return $result !== false;
    }
    
    /**
     * Release a reserved date
     */
    public function release_date($dj_id, $date, $booking_id = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_availability';
        
        $old_status = $this->get_availability_status($dj_id, $date);
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'dj_id' => $dj_id,
                'date' => $date,
                'status' => 'available',
                'booking_id' => $booking_id,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%d', '%s')
        );
        
        if ($result) {
            do_action('dj_availability_changed', $dj_id, $date, $old_status, 'available');
            $this->sync_to_ghl_calendar($dj_id, $date, 'available', $booking_id);
        }
        
        return $result !== false;
    }
    
    /**
     * Set DJ as unavailable for a specific date
     */
    public function set_unavailable($dj_id, $date, $reason = '') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_availability';
        
        $old_status = $this->get_availability_status($dj_id, $date);
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'dj_id' => $dj_id,
                'date' => $date,
                'status' => 'unavailable',
                'notes' => $reason,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );
        
        if ($result) {
            do_action('dj_availability_changed', $dj_id, $date, $old_status, 'unavailable');
            $this->sync_to_ghl_calendar($dj_id, $date, 'unavailable');
            
            // Check for safeguards monitoring
            $this->check_for_suspicious_unavailability($dj_id, $date);
        }
        
        return $result !== false;
    }
    
    /**
     * Get DJ calendar for a date range
     */
    public function get_dj_calendar($dj_id, $start_date, $end_date) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_availability';
        
        // Get availability records
        $availability_records = $wpdb->get_results($wpdb->prepare("
            SELECT date, status, booking_id, notes, created_at
            FROM $table_name 
            WHERE dj_id = %d 
            AND date BETWEEN %s AND %s
            ORDER BY date ASC, created_at DESC
        ", $dj_id, $start_date, $end_date));
        
        // Get confirmed bookings
        $bookings = $wpdb->get_results($wpdb->prepare("
            SELECT pm1.meta_value as event_date, p.ID as booking_id, p.post_title, pm2.meta_value as client_name
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'event_date'
            INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'client_name'  
            INNER JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = 'assigned_dj'
            WHERE p.post_type = 'dj_booking'
            AND pm3.meta_value = %d
            AND pm1.meta_value BETWEEN %s AND %s
            AND p.post_status IN ('confirmed', 'deposit_paid', 'paid_in_full')
        ", $dj_id, $start_date, $end_date));
        
        // Build calendar array
        $calendar = array();
        $current_date = new DateTime($start_date);
        $end_date_obj = new DateTime($end_date);
        
        while ($current_date <= $end_date_obj) {
            $date_string = $current_date->format('Y-m-d');
            
            // Default to available
            $calendar[$date_string] = array(
                'status' => 'available',
                'booking_id' => null,
                'client_name' => '',
                'notes' => '',
                'is_past' => $current_date < new DateTime(),
                'is_weekend' => in_array($current_date->format('w'), array(0, 6))
            );
            
            // Check for availability records
            foreach ($availability_records as $record) {
                if ($record->date === $date_string) {
                    $calendar[$date_string]['status'] = $record->status;
                    $calendar[$date_string]['booking_id'] = $record->booking_id;
                    $calendar[$date_string]['notes'] = $record->notes;
                    break; // Take the most recent record
                }
            }
            
            // Check for confirmed bookings
            foreach ($bookings as $booking) {
                if ($booking->event_date === $date_string) {
                    $calendar[$date_string]['status'] = 'booked';
                    $calendar[$date_string]['booking_id'] = $booking->booking_id;
                    $calendar[$date_string]['client_name'] = $booking->client_name;
                    break;
                }
            }
            
            $current_date->add(new DateInterval('P1D'));
        }
        
        return $calendar;
    }
    
    /**
     * Get master calendar showing all DJ availability
     */
    public function get_master_calendar($start_date, $end_date) {
        $djs = get_posts(array(
            'post_type' => 'dj_profile',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        $master_calendar = array();
        
        foreach ($djs as $dj) {
            $dj_calendar = $this->get_dj_calendar($dj->ID, $start_date, $end_date);
            $master_calendar[$dj->ID] = array(
                'name' => $dj->post_title,
                'calendar' => $dj_calendar,
                'total_bookings' => $this->count_bookings_in_period($dj->ID, $start_date, $end_date),
                'total_unavailable' => $this->count_unavailable_in_period($dj->ID, $start_date, $end_date)
            );
        }
        
        return $master_calendar;
    }
    
    /**
     * Count bookings in a period
     */
    private function count_bookings_in_period($dj_id, $start_date, $end_date) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'event_date'
            INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'assigned_dj'
            WHERE p.post_type = 'dj_booking'
            AND pm2.meta_value = %d
            AND pm1.meta_value BETWEEN %s AND %s
            AND p.post_status IN ('confirmed', 'deposit_paid', 'paid_in_full')
        ", $dj_id, $start_date, $end_date));
    }
    
    /**
     * Count unavailable days in a period
     */
    private function count_unavailable_in_period($dj_id, $start_date, $end_date) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_availability';
        
        return $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT date)
            FROM $table_name
            WHERE dj_id = %d
            AND date BETWEEN %s AND %s
            AND status = 'unavailable'
        ", $dj_id, $start_date, $end_date));
    }
    
    /**
     * Update availability via AJAX
     */
    public function update_availability() {
        check_ajax_referer('dj_hire_nonce', 'nonce');
        
        if (!current_user_can('manage_dj_calendar')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $dj_id = intval($_POST['dj_id']);
        $date = sanitize_text_field($_POST['date']);
        $status = sanitize_text_field($_POST['status']);
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        
        // Validate inputs
        if (!$dj_id || !$date || !in_array($status, array('available', 'unavailable'))) {
            wp_send_json_error('Invalid input data');
        }
        
        // Check if user can edit this DJ
        if (!$this->can_user_edit_dj_calendar($dj_id)) {
            wp_send_json_error('Cannot edit this DJ\'s calendar');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_availability';
        
        $old_status = $this->get_availability_status($dj_id, $date);
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'dj_id' => $dj_id,
                'date' => $date,
                'status' => $status,
                'notes' => $notes,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );
        
        if ($result) {
            do_action('dj_availability_changed', $dj_id, $date, $old_status, $status);
            
            wp_send_json_success(array(
                'message' => 'Availability updated successfully',
                'old_status' => $old_status,
                'new_status' => $status
            ));
        } else {
            wp_send_json_error('Failed to update availability');
        }
    }
    
    /**
     * Bulk update availability
     */
    public function bulk_update_availability() {
        check_ajax_referer('dj_hire_nonce', 'nonce');
        
        if (!current_user_can('manage_dj_calendar')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $dj_id = intval($_POST['dj_id']);
        $dates = $_POST['dates'] ?? array();
        $status = sanitize_text_field($_POST['status']);
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        
        if (!$dj_id || empty($dates) || !in_array($status, array('available', 'unavailable'))) {
            wp_send_json_error('Invalid input data');
        }
        
        if (!$this->can_user_edit_dj_calendar($dj_id)) {
            wp_send_json_error('Cannot edit this DJ\'s calendar');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_availability';
        
        $updated_count = 0;
        $errors = array();
        
        foreach ($dates as $date) {
            $date = sanitize_text_field($date);
            
            // Skip if date is invalid
            if (!$this->validate_date($date)) {
                $errors[] = "Invalid date: $date";
                continue;
            }
            
            $old_status = $this->get_availability_status($dj_id, $date);
            
            $result = $wpdb->insert(
                $table_name,
                array(
                    'dj_id' => $dj_id,
                    'date' => $date,
                    'status' => $status,
                    'notes' => $notes,
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%s', '%s')
            );
            
            if ($result) {
                do_action('dj_availability_changed', $dj_id, $date, $old_status, $status);
                $updated_count++;
            } else {
                $errors[] = "Failed to update: $date";
            }
        }
        
        wp_send_json_success(array(
            'updated_count' => $updated_count,
            'errors' => $errors,
            'message' => "Updated $updated_count dates successfully"
        ));
    }
    
    /**
     * Get calendar data via AJAX
     */
    public function get_calendar() {
        check_ajax_referer('dj_hire_nonce', 'nonce');
        
        $dj_id = intval($_POST['dj_id'] ?? 0);
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);
        $view_type = sanitize_text_field($_POST['view_type'] ?? 'single');
        
        if (!$this->validate_date($start_date) || !$this->validate_date($end_date)) {
            wp_send_json_error('Invalid date range');
        }
        
        if ($view_type === 'master') {
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions for master calendar');
            }
            
            $calendar_data = $this->get_master_calendar($start_date, $end_date);
        } else {
            if ($dj_id && !$this->can_user_view_dj_calendar($dj_id)) {
                wp_send_json_error('Cannot view this DJ\'s calendar');
            }
            
            $calendar_data = $this->get_dj_calendar($dj_id, $start_date, $end_date);
        }
        
        wp_send_json_success($calendar_data);
    }
    
    /**
     * Import external calendar
     */
    public function import_external_calendar() {
        check_ajax_referer('dj_hire_nonce', 'nonce');
        
        if (!current_user_can('manage_dj_calendar')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $dj_id = intval($_POST['dj_id']);
        $calendar_url = esc_url_raw($_POST['calendar_url']);
        $calendar_type = sanitize_text_field($_POST['calendar_type']); // 'ical', 'google', 'outlook'
        
        if (!$dj_id || !$calendar_url) {
            wp_send_json_error('Missing required data');
        }
        
        if (!$this->can_user_edit_dj_calendar($dj_id)) {
            wp_send_json_error('Cannot edit this DJ\'s calendar');
        }
        
        try {
            $imported_events = $this->parse_external_calendar($calendar_url, $calendar_type);
            $updated_count = $this->apply_imported_events($dj_id, $imported_events);
            
            wp_send_json_success(array(
                'message' => "Imported $updated_count events successfully",
                'imported_count' => $updated_count
            ));
        } catch (Exception $e) {
            wp_send_json_error('Failed to import calendar: ' . $e->getMessage());
        }
    }
    
    /**
     * Parse external calendar
     */
    private function parse_external_calendar($url, $type) {
        $response = wp_remote_get($url, array('timeout' => 30));
        
        if (is_wp_error($response)) {
            throw new Exception('Failed to fetch calendar: ' . $response->get_error_message());
        }
        
        $calendar_data = wp_remote_retrieve_body($response);
        $events = array();
        
        switch ($type) {
            case 'ical':
                $events = $this->parse_ical($calendar_data);
                break;
                
            case 'google':
                $events = $this->parse_google_calendar($calendar_data);
                break;
                
            default:
                throw new Exception('Unsupported calendar type');
        }
        
        return $events;
    }
    
    /**
     * Parse iCal format
     */
    private function parse_ical($ical_data) {
        $events = array();
        $lines = explode("\n", $ical_data);
        $event = array();
        $in_event = false;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if ($line === 'BEGIN:VEVENT') {
                $in_event = true;
                $event = array();
            } elseif ($line === 'END:VEVENT') {
                if ($in_event && !empty($event['date'])) {
                    $events[] = $event;
                }
                $in_event = false;
            } elseif ($in_event) {
                if (strpos($line, 'DTSTART') === 0) {
                    $date_value = explode(':', $line, 2)[1];
                    $event['date'] = $this->parse_ical_date($date_value);
                } elseif (strpos($line, 'DTEND') === 0) {
                    $date_value = explode(':', $line, 2)[1];
                    $event['end_date'] = $this->parse_ical_date($date_value);
                } elseif (strpos($line, 'SUMMARY') === 0) {
                    $event['title'] = explode(':', $line, 2)[1];
                }
            }
        }
        
        return $events;
    }
    
    /**
     * Parse iCal date format
     */
    private function parse_ical_date($date_string) {
        // Remove timezone info if present
        $date_string = preg_replace('/;.*$/', '', $date_string);
        
        if (strlen($date_string) === 8) {
            // YYYYMMDD format
            return date('Y-m-d', strtotime($date_string));
        } elseif (strlen($date_string) === 15) {
            // YYYYMMDDTHHMMSSZ format
            return date('Y-m-d', strtotime($date_string));
        }
        
        return null;
    }
    
    /**
     * Parse Google Calendar JSON
     */
    private function parse_google_calendar($json_data) {
        $data = json_decode($json_data, true);
        $events = array();
        
        if (isset($data['items'])) {
            foreach ($data['items'] as $item) {
                $start_date = $item['start']['date'] ?? $item['start']['dateTime'] ?? null;
                
                if ($start_date) {
                    $events[] = array(
                        'date' => date('Y-m-d', strtotime($start_date)),
                        'title' => $item['summary'] ?? 'Busy'
                    );
                }
            }
        }
        
        return $events;
    }
    
    /**
     * Apply imported events to DJ calendar
     */
    private function apply_imported_events($dj_id, $events) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_availability';
        $updated_count = 0;
        
        foreach ($events as $event) {
            if (empty($event['date'])) continue;
            
            $old_status = $this->get_availability_status($dj_id, $event['date']);
            
            // Only update if currently available
            if ($old_status === 'available') {
                $result = $wpdb->insert(
                    $table_name,
                    array(
                        'dj_id' => $dj_id,
                        'date' => $event['date'],
                        'status' => 'unavailable',
                        'notes' => 'Imported: ' . ($event['title'] ?? 'External booking'),
                        'created_at' => current_time('mysql')
                    ),
                    array('%d', '%s', '%s', '%s', '%s')
                );
                
                if ($result) {
                    do_action('dj_availability_changed', $dj_id, $event['date'], $old_status, 'unavailable');
                    $updated_count++;
                }
            }
        }
        
        return $updated_count;
    }
    
    /**
     * Sync to GoHighLevel calendar
     */
    private function sync_to_ghl_calendar($dj_id, $date, $status, $booking_id = null) {
        if (!class_exists('GHL_Integration')) return;
        
        $ghl_integration = new GHL_Integration();
        
        // Get DJ user details
        $dj_user_id = get_post_meta($dj_id, 'dj_user_id', true);
        
        if ($status === 'booked' && $booking_id) {
            // Create calendar event for booking
            $ghl_integration->create_calendar_event($booking_id, null);
        } elseif ($status === 'unavailable') {
            // Create blocked time event
            $this->create_ghl_blocked_time($dj_id, $date, $ghl_integration);
        }
    }
    
    /**
     * Create blocked time in GoHighLevel
     */
    private function create_ghl_blocked_time($dj_id, $date, $ghl_integration) {
        $dj_name = get_the_title($dj_id);
        
        $event_data = array(
            'title' => 'Unavailable - ' . $dj_name,
            'start_time' => $date . 'T00:00:00',
            'end_time' => $date . 'T23:59:59',
            'all_day' => true,
            'description' => 'DJ marked as unavailable'
        );
        
        // This would create the event via GHL API
        // Implementation depends on GHL calendar API
    }
    
    /**
     * Sync all DJ calendars
     */
    public function sync_all_calendars() {
        $djs = get_posts(array(
            'post_type' => 'dj_profile',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        foreach ($djs as $dj) {
            $this->sync_dj_calendar($dj->ID);
        }
    }
    
    /**
     * Sync individual DJ calendar
     */
    public function sync_dj_calendar($dj_id) {
        // Check for external calendar URLs
        $external_calendars = get_post_meta($dj_id, 'external_calendar_urls', true);
        
        if (!empty($external_calendars)) {
            $external_calendars = json_decode($external_calendars, true);
            
            foreach ($external_calendars as $calendar) {
                try {
                    $events = $this->parse_external_calendar($calendar['url'], $calendar['type']);
                    $this->apply_imported_events($dj_id, $events);
                } catch (Exception $e) {
                    error_log('Calendar sync error for DJ ' . $dj_id . ': ' . $e->getMessage());
                }
            }
        }
    }
    
    /**
     * Check for suspicious unavailability patterns
     */
    private function check_for_suspicious_unavailability($dj_id, $date) {
        if (!class_exists('DJ_Safeguards_Monitor')) return;
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_safeguards_log';
        
        // Check if this date had an enquiry
        $enquiry_exists = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE dj_id = %d AND enquiry_date = %s",
            $dj_id, $date
        ));
        
        if ($enquiry_exists) {
            $safeguards = new DJ_Safeguards_Monitor();
            $safeguards->monitor_availability_change($dj_id, $date, 'available', 'unavailable');
        }
    }
    
    /**
     * Check if user can edit DJ calendar
     */
    private function can_user_edit_dj_calendar($dj_id) {
        if (current_user_can('manage_options')) {
            return true;
        }
        
        // Check if current user is the DJ
        $dj_user_id = get_post_meta($dj_id, 'dj_user_id', true);
        return get_current_user_id() == $dj_user_id;
    }
    
    /**
     * Check if user can view DJ calendar
     */
    private function can_user_view_dj_calendar($dj_id) {
        if (current_user_can('view_dj_bookings')) {
            return true;
        }
        
        return $this->can_user_edit_dj_calendar($dj_id);
    }
    
    /**
     * Validate date format
     */
    private function validate_date($date) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
    
    /**
     * Log availability change for monitoring
     */
    public function log_availability_change($dj_id, $date, $old_status, $new_status) {
        // Log to WordPress
        error_log("DJ Calendar: DJ $dj_id availability changed on $date from $old_status to $new_status");
        
        // Trigger safeguards monitoring if applicable
        if (class_exists('DJ_Safeguards_Monitor')) {
            do_action('dj_availability_updated', $dj_id, $date, $old_status, $new_status);
        }
    }
    
    /**
     * Cleanup old availability records
     */
    public function cleanup_old_availability() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_availability';
        
        // Remove records older than 2 years
        $wpdb->query("
            DELETE FROM $table_name 
            WHERE date < DATE_SUB(CURDATE(), INTERVAL 2 YEAR)
        ");
        
        // Remove duplicate records, keeping only the most recent
        $wpdb->query("
            DELETE t1 FROM $table_name t1
            INNER JOIN $table_name t2 
            WHERE t1.dj_id = t2.dj_id 
            AND t1.date = t2.date 
            AND t1.created_at < t2.created_at
        ");
    }
    
    /**
     * Get availability statistics
     */
    public function get_availability_stats($dj_id, $start_date, $end_date) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_availability';
        
        $total_days = (new DateTime($end_date))->diff(new DateTime($start_date))->days + 1;
        
        $unavailable_days = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT date)
            FROM $table_name
            WHERE dj_id = %d
            AND date BETWEEN %s AND %s
            AND status = 'unavailable'
        ", $dj_id, $start_date, $end_date));
        
        $booked_days = $this->count_bookings_in_period($dj_id, $start_date, $end_date);
        
        $available_days = $total_days - $unavailable_days - $booked_days;
        
        return array(
            'total_days' => $total_days,
            'available_days' => max(0, $available_days),
            'unavailable_days' => $unavailable_days,
            'booked_days' => $booked_days,
            'availability_percentage' => $total_days > 0 ? round(($available_days / $total_days) * 100, 1) : 0
        );
    }
    
    /**
     * Get conflicting dates for multiple DJs
     */
    public function find_available_djs($date, $excluded_dj_ids = array()) {
        $djs = get_posts(array(
            'post_type' => 'dj_profile',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'exclude' => $excluded_dj_ids
        ));
        
        $available_djs = array();
        
        foreach ($djs as $dj) {
            if ($this->check_availability($dj->ID, $date)) {
                $available_djs[] = array(
                    'id' => $dj->ID,
                    'name' => $dj->post_title,
                    'specialisations' => json_decode(get_post_meta($dj->ID, 'dj_specialisations', true) ?: '[]', true),
                    'base_location' => get_post_meta($dj->ID, 'dj_base_location', true)
                );
            }
        }
        
        return $available_djs;
    }
}
?>