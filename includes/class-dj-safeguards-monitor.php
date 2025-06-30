<?php
/**
 * DJ Safeguards Monitor Class
 * Monitors DJ behavior to protect agency from commission circumvention
 */

class DJ_Safeguards_Monitor {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_ajax_review_safeguard_alert', array($this, 'review_alert'));
        add_action('wp_ajax_dismiss_safeguard_alert', array($this, 'dismiss_alert'));
        
        // Schedule daily monitoring
        if (!wp_next_scheduled('dj_safeguards_daily_check')) {
            wp_schedule_event(time(), 'daily', 'dj_safeguards_daily_check');
        }
        add_action('dj_safeguards_daily_check', array($this, 'run_daily_checks'));
    }
    
    public function init() {
        // Hook into calendar updates to monitor availability changes
        add_action('dj_availability_updated', array($this, 'monitor_availability_change'), 10, 4);
        
        // Monitor direct client contact attempts
        add_action('dj_client_contact_attempted', array($this, 'monitor_client_contact'), 10, 3);
        
        // Monitor external booking attempts
        add_action('dj_external_booking_detected', array($this, 'monitor_external_booking'), 10, 2);
    }
    
    /**
     * Log an enquiry for monitoring
     */
    public function log_enquiry($dj_id, $enquiry_date, $booking_id = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_safeguards_log';
        
        // Check if enquiry already logged
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE dj_id = %d AND enquiry_date = %s AND booking_id = %d",
            $dj_id, $enquiry_date, $booking_id
        ));
        
        if (!$existing) {
            $wpdb->insert(
                $table_name,
                array(
                    'dj_id' => $dj_id,
                    'enquiry_date' => $enquiry_date,
                    'status_change_date' => current_time('mysql'),
                    'old_status' => 'available',
                    'new_status' => 'enquiry_logged',
                    'alert_level' => 'info',
                    'notes' => 'Enquiry logged for monitoring',
                    'booking_id' => $booking_id
                ),
                array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d')
            );
        }
        
        // Start monitoring this date
        $this->start_date_monitoring($dj_id, $enquiry_date, $booking_id);
    }
    
    /**
     * Start monitoring a specific date for a DJ
     */
    private function start_date_monitoring($dj_id, $date, $booking_id) {
        // Set up a scheduled check for this specific date
        $hook_name = 'dj_date_monitor_' . $dj_id . '_' . str_replace('-', '', $date);
        
        if (!wp_next_scheduled($hook_name)) {
            // Schedule checks for 24 hours, 48 hours, and 7 days
            wp_schedule_single_event(time() + (24 * 3600), $hook_name, array($dj_id, $date, $booking_id));
            wp_schedule_single_event(time() + (48 * 3600), $hook_name, array($dj_id, $date, $booking_id));
            wp_schedule_single_event(time() + (7 * 24 * 3600), $hook_name, array($dj_id, $date, $booking_id));
        }
        
        add_action($hook_name, array($this, 'check_date_availability'), 10, 3);
    }
    
    /**
     * Check if a DJ's availability has changed for a monitored date
     */
    public function check_date_availability($dj_id, $date, $booking_id) {
        $calendar_manager = new DJ_Calendar_Manager();
        $current_availability = $calendar_manager->get_availability_status($dj_id, $date);
        
        // If DJ is now unavailable but we don't have a booking, this is suspicious
        if ($current_availability === 'unavailable' && empty($booking_id)) {
            $this->flag_suspicious_activity($dj_id, $date, 'date_became_unavailable', array(
                'message' => 'DJ became unavailable for date after enquiry without booking through agency',
                'booking_id' => $booking_id,
                'check_time' => current_time('mysql')
            ));
        }
        
        // Check for external calendar conflicts
        $this->check_external_calendar_conflicts($dj_id, $date);
    }
    
    /**
     * Monitor availability changes
     */
    public function monitor_availability_change($dj_id, $date, $old_status, $new_status) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_safeguards_log';
        
        // Check if this date was part of an enquiry
        $enquiry_exists = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE dj_id = %d AND enquiry_date = %s AND old_status = 'available'",
            $dj_id, $date
        ));
        
        if ($enquiry_exists && $new_status === 'unavailable') {
            // DJ became unavailable for a date they had an enquiry for
            $alert_level = 'medium';
            $notes = 'DJ became unavailable for enquiry date';
            
            // Check if this happened quickly after enquiry
            $enquiry_time = strtotime($enquiry_exists->created_at);
            $change_time = time();
            $hours_difference = ($change_time - $enquiry_time) / 3600;
            
            if ($hours_difference < 48) {
                $alert_level = 'high';
                $notes .= ' within 48 hours of enquiry';
            }
            
            $this->flag_suspicious_activity($dj_id, $date, 'availability_change_after_enquiry', array(
                'old_status' => $old_status,
                'new_status' => $new_status,
                'hours_after_enquiry' => $hours_difference,
                'enquiry_id' => $enquiry_exists->id
            ), $alert_level);
        }
        
        // Log all availability changes
        $wpdb->insert(
            $table_name,
            array(
                'dj_id' => $dj_id,
                'enquiry_date' => $date,
                'status_change_date' => current_time('mysql'),
                'old_status' => $old_status,
                'new_status' => $new_status,
                'alert_level' => 'low',
                'notes' => 'Availability change logged'
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Monitor client contact attempts
     */
    public function monitor_client_contact($dj_id, $client_email, $booking_id) {
        // Check if DJ is trying to contact client directly before authorized
        $booking_status = get_post_status($booking_id);
        
        if (!in_array($booking_status, array('pending_call', 'quote_sent', 'confirmed', 'deposit_paid'))) {
            $this->flag_suspicious_activity($dj_id, null, 'unauthorized_client_contact', array(
                'client_email' => $client_email,
                'booking_id' => $booking_id,
                'booking_status' => $booking_status
            ), 'high');
        }
    }
    
    /**
     * Monitor external booking attempts
     */
    public function monitor_external_booking($dj_id, $suspicious_data) {
        $this->flag_suspicious_activity($dj_id, null, 'external_booking_detected', $suspicious_data, 'high');
    }
    
    /**
     * Check for external calendar conflicts
     */
    private function check_external_calendar_conflicts($dj_id, $date) {
        // This would integrate with external calendar APIs if available
        // For now, we'll check for patterns that suggest external bookings
        
        $dj_profile = get_post_meta($dj_id);
        $social_links = json_decode($dj_profile['dj_social_links'][0] ?? '{}', true);
        
        // Check social media for event announcements on monitored dates
        if (!empty($social_links)) {
            $this->check_social_media_announcements($dj_id, $date, $social_links);
        }
    }
    
    /**
     * Check social media for event announcements
     */
    private function check_social_media_announcements($dj_id, $date, $social_links) {
        // This would require API integrations with social platforms
        // For now, we'll flag for manual review
        
        $this->flag_suspicious_activity($dj_id, $date, 'social_media_check_required', array(
            'social_links' => $social_links,
            'check_reason' => 'Manual review required for social media activity'
        ), 'low');
    }
    
    /**
     * Flag suspicious activity
     */
    private function flag_suspicious_activity($dj_id, $date, $activity_type, $details, $alert_level = 'medium') {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_safeguards_log';
        
        $wpdb->insert(
            $table_name,
            array(
                'dj_id' => $dj_id,
                'enquiry_date' => $date,
                'status_change_date' => current_time('mysql'),
                'old_status' => 'monitored',
                'new_status' => 'flagged_' . $activity_type,
                'alert_level' => $alert_level,
                'notes' => json_encode($details)
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        // Send alert to admin
        $this->send_admin_alert($dj_id, $activity_type, $details, $alert_level);
        
        // Create GoHighLevel task if configured
        $this->create_ghl_investigation_task($dj_id, $activity_type, $details);
    }
    
    /**
     * Send admin alert
     */
    private function send_admin_alert($dj_id, $activity_type, $details, $alert_level) {
        $dj_name = get_the_title($dj_id);
        $admin_email = get_option('admin_email');
        
        $subject = sprintf('[DJ Safeguards Alert - %s] %s - %s', 
            strtoupper($alert_level), 
            $dj_name, 
            ucwords(str_replace('_', ' ', $activity_type))
        );
        
        $message = "A safeguards alert has been triggered:\n\n";
        $message .= "DJ: " . $dj_name . "\n";
        $message .= "Activity Type: " . $activity_type . "\n";
        $message .= "Alert Level: " . $alert_level . "\n";
        $message .= "Details: " . json_encode($details, JSON_PRETTY_PRINT) . "\n\n";
        $message .= "Please review this activity in the admin dashboard.\n";
        $message .= admin_url('admin.php?page=dj-safeguards');
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Create GoHighLevel investigation task
     */
    private function create_ghl_investigation_task($dj_id, $activity_type, $details) {
        $ghl_integration = new GHL_Integration();
        
        // Find admin contact in GHL
        $admin_email = get_option('admin_email');
        $admin_contact = $ghl_integration->find_contact_by_email($admin_email);
        
        if ($admin_contact) {
            $dj_name = get_the_title($dj_id);
            
            $task_title = sprintf('Investigate: %s - %s', 
                $dj_name, 
                ucwords(str_replace('_', ' ', $activity_type))
            );
            
            $task_body = "Safeguards alert requires investigation:\n\n";
            $task_body .= "DJ: " . $dj_name . "\n";
            $task_body .= "Activity: " . $activity_type . "\n";
            $task_body .= "Details: " . json_encode($details, JSON_PRETTY_PRINT);
            
            $ghl_integration->create_task($admin_contact['id'], $task_title, $task_body, 'high');
        }
    }
    
    /**
     * Run daily monitoring checks
     */
    public function run_daily_checks() {
        $this->check_pattern_violations();
        $this->check_booking_frequency();
        $this->check_client_source_tracking();
        $this->cleanup_old_logs();
    }
    
    /**
     * Check for pattern violations
     */
    private function check_pattern_violations() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_safeguards_log';
        
        // Find DJs with multiple high-level alerts
        $pattern_violators = $wpdb->get_results("
            SELECT dj_id, COUNT(*) as alert_count 
            FROM $table_name 
            WHERE alert_level IN ('high', 'medium') 
            AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY dj_id 
            HAVING alert_count >= 3
        ");
        
        foreach ($pattern_violators as $violator) {
            $this->flag_suspicious_activity(
                $violator->dj_id, 
                null, 
                'pattern_violation', 
                array(
                    'alert_count' => $violator->alert_count,
                    'period' => '30 days'
                ), 
                'high'
            );
        }
    }
    
    /**
     * Check booking frequency patterns
     */
    private function check_booking_frequency() {
        // Compare DJ's agency bookings vs expected activity level
        $djs = get_posts(array(
            'post_type' => 'dj_profile',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        foreach ($djs as $dj) {
            $this->analyze_dj_booking_frequency($dj->ID);
        }
    }
    
    /**
     * Analyze individual DJ booking frequency
     */
    private function analyze_dj_booking_frequency($dj_id) {
        global $wpdb;
        
        // Get agency bookings for last 3 months
        $agency_bookings = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'dj_booking'
            AND pm.meta_key = 'assigned_dj'
            AND pm.meta_value = %d
            AND p.post_date > DATE_SUB(NOW(), INTERVAL 3 MONTH)
        ", $dj_id));
        
        // Get total unavailable days in last 3 months
        $unavailable_days = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT date) 
            FROM {$wpdb->prefix}dj_availability 
            WHERE dj_id = %d 
            AND status = 'unavailable'
            AND date > DATE_SUB(NOW(), INTERVAL 3 MONTH)
        ", $dj_id));
        
        // If DJ has many unavailable days but few agency bookings, flag for review
        if ($unavailable_days > 10 && $agency_bookings < 3) {
            $ratio = $unavailable_days / max($agency_bookings, 1);
            
            if ($ratio > 5) { // More than 5 unavailable days per agency booking
                $this->flag_suspicious_activity(
                    $dj_id, 
                    null, 
                    'suspicious_booking_ratio', 
                    array(
                        'agency_bookings' => $agency_bookings,
                        'unavailable_days' => $unavailable_days,
                        'ratio' => $ratio,
                        'period' => '3 months'
                    ), 
                    'medium'
                );
            }
        }
    }
    
    /**
     * Check client source tracking
     */
    private function check_client_source_tracking() {
        // Monitor for clients who contact DJs directly vs through agency
        global $wpdb;
        
        // This would require tracking client interactions across multiple channels
        // For now, flag DJs who have many direct enquiries vs agency referrals
        
        $direct_enquiry_pattern = $wpdb->get_results("
            SELECT pm.meta_value as dj_id, COUNT(*) as direct_count
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id
            WHERE p.post_type = 'dj_booking'
            AND pm.meta_key = 'assigned_dj'
            AND pm2.meta_key = 'booking_source'
            AND pm2.meta_value = 'direct'
            AND p.post_date > DATE_SUB(NOW(), INTERVAL 1 MONTH)
            GROUP BY pm.meta_value
            HAVING direct_count > 3
        ");
        
        foreach ($direct_enquiry_pattern as $pattern) {
            $this->flag_suspicious_activity(
                $pattern->dj_id, 
                null, 
                'high_direct_enquiry_rate', 
                array(
                    'direct_bookings' => $pattern->direct_count,
                    'period' => '1 month'
                ), 
                'medium'
            );
        }
    }
    
    /**
     * Clean up old monitoring logs
     */
    private function cleanup_old_logs() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_safeguards_log';
        
        // Remove logs older than 6 months (except high-level alerts)
        $wpdb->query("
            DELETE FROM $table_name 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH)
            AND alert_level = 'low'
        ");
        
        // Remove medium-level alerts older than 1 year
        $wpdb->query("
            DELETE FROM $table_name 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)
            AND alert_level = 'medium'
        ");
    }
    
    /**
     * Get safeguards dashboard data
     */
    public function get_dashboard_data() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_safeguards_log';
        
        // Get recent alerts
        $recent_alerts = $wpdb->get_results("
            SELECT sl.*, p.post_title as dj_name
            FROM $table_name sl
            INNER JOIN {$wpdb->posts} p ON sl.dj_id = p.ID
            WHERE sl.alert_level IN ('high', 'medium')
            AND sl.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY sl.created_at DESC
            LIMIT 20
        ");
        
        // Get alert statistics
        $alert_stats = $wpdb->get_results("
            SELECT alert_level, COUNT(*) as count
            FROM $table_name
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY alert_level
        ");
        
        // Get top flagged DJs
        $top_flagged = $wpdb->get_results("
            SELECT sl.dj_id, p.post_title as dj_name, COUNT(*) as alert_count
            FROM $table_name sl
            INNER JOIN {$wpdb->posts} p ON sl.dj_id = p.ID
            WHERE sl.alert_level IN ('high', 'medium')
            AND sl.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY sl.dj_id
            ORDER BY alert_count DESC
            LIMIT 10
        ");
        
        return array(
            'recent_alerts' => $recent_alerts,
            'alert_stats' => $alert_stats,
            'top_flagged_djs' => $top_flagged,
            'total_monitored_dates' => $this->get_monitored_dates_count(),
            'active_investigations' => $this->get_active_investigations_count()
        );
    }
    
    /**
     * Get count of monitored dates
     */
    private function get_monitored_dates_count() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_safeguards_log';
        
        return $wpdb->get_var("
            SELECT COUNT(DISTINCT CONCAT(dj_id, '_', enquiry_date))
            FROM $table_name
            WHERE enquiry_date IS NOT NULL
            AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
    }
    
    /**
     * Get count of active investigations
     */
    private function get_active_investigations_count() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_safeguards_log';
        
        return $wpdb->get_var("
            SELECT COUNT(*)
            FROM $table_name
            WHERE alert_level = 'high'
            AND new_status LIKE 'flagged_%'
            AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
    }
    
    /**
     * Review and update alert status
     */
    public function review_alert() {
        check_ajax_referer('dj_hire_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $alert_id = intval($_POST['alert_id']);
        $action = sanitize_text_field($_POST['action']);
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_safeguards_log';
        
        $update_data = array(
            'notes' => $notes,
            'reviewed_by' => get_current_user_id(),
            'reviewed_at' => current_time('mysql')
        );
        
        switch ($action) {
            case 'resolved':
                $update_data['new_status'] = 'resolved';
                $update_data['alert_level'] = 'resolved';
                break;
                
            case 'escalate':
                $update_data['alert_level'] = 'high';
                $update_data['new_status'] = 'escalated';
                // Create investigation task
                $this->create_investigation_task($alert_id);
                break;
                
            case 'false_positive':
                $update_data['new_status'] = 'false_positive';
                $update_data['alert_level'] = 'dismissed';
                break;
        }
        
        $result = $wpdb->update(
            $table_name,
            $update_data,
            array('id' => $alert_id),
            array('%s', '%d', '%s', '%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success('Alert updated successfully');
        } else {
            wp_send_json_error('Failed to update alert');
        }
    }
    
    /**
     * Dismiss alert
     */
    public function dismiss_alert() {
        check_ajax_referer('dj_hire_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $alert_id = intval($_POST['alert_id']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_safeguards_log';
        
        $result = $wpdb->update(
            $table_name,
            array(
                'alert_level' => 'dismissed',
                'new_status' => 'dismissed',
                'reviewed_by' => get_current_user_id(),
                'reviewed_at' => current_time('mysql')
            ),
            array('id' => $alert_id),
            array('%s', '%s', '%d', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success('Alert dismissed');
        } else {
            wp_send_json_error('Failed to dismiss alert');
        }
    }
    
    /**
     * Create investigation task
     */
    private function create_investigation_task($alert_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_safeguards_log';
        
        $alert = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $alert_id));
        
        if ($alert) {
            $dj_name = get_the_title($alert->dj_id);
            
            // Create WordPress task/reminder
            wp_schedule_single_event(time() + 3600, 'dj_investigation_reminder', array($alert_id));
            
            // Create GoHighLevel task if configured
            $ghl_integration = new GHL_Integration();
            $admin_email = get_option('admin_email');
            $admin_contact = $ghl_integration->find_contact_by_email($admin_email);
            
            if ($admin_contact) {
                $task_title = "URGENT: DJ Investigation Required - " . $dj_name;
                $task_body = "High-priority safeguards alert requires immediate investigation:\n\n";
                $task_body .= "DJ: " . $dj_name . "\n";
                $task_body .= "Alert Details: " . $alert->notes . "\n";
                $task_body .= "Created: " . $alert->created_at . "\n\n";
                $task_body .= "Please review and take appropriate action.";
                
                $ghl_integration->create_task($admin_contact['id'], $task_title, $task_body, 'urgent');
            }
        }
    }
    
    /**
     * Block suspected circumvention attempt
     */
    public function block_circumvention_attempt($dj_id, $client_data, $reason) {
        // Log the attempt
        $this->flag_suspicious_activity(
            $dj_id, 
            null, 
            'circumvention_attempt_blocked', 
            array(
                'client_email' => $client_data['email'] ?? '',
                'client_phone' => $client_data['phone'] ?? '',
                'reason' => $reason,
                'blocked_at' => current_time('mysql')
            ), 
            'high'
        );
        
        // Temporarily suspend DJ's profile if multiple attempts
        $this->check_suspension_threshold($dj_id);
        
        // Send immediate alert
        $this->send_immediate_alert($dj_id, 'circumvention_attempt', $reason);
    }
    
    /**
     * Check if DJ should be suspended
     */
    private function check_suspension_threshold($dj_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_safeguards_log';
        
        // Count high-level alerts in last 7 days
        $recent_high_alerts = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM $table_name
            WHERE dj_id = %d
            AND alert_level = 'high'
            AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
        ", $dj_id));
        
        if ($recent_high_alerts >= 3) {
            // Suspend DJ profile
            wp_update_post(array(
                'ID' => $dj_id,
                'post_status' => 'draft'
            ));
            
            // Log suspension
            $this->flag_suspicious_activity(
                $dj_id, 
                null, 
                'profile_suspended', 
                array(
                    'reason' => 'Multiple high-level alerts',
                    'alert_count' => $recent_high_alerts,
                    'suspension_date' => current_time('mysql')
                ), 
                'high'
            );
            
            // Notify admin
            $this->send_immediate_alert($dj_id, 'profile_suspended', 'Automatic suspension due to multiple violations');
        }
    }
    
    /**
     * Send immediate alert
     */
    private function send_immediate_alert($dj_id, $alert_type, $reason) {
        $dj_name = get_the_title($dj_id);
        $admin_email = get_option('admin_email');
        
        $subject = "[URGENT] DJ Safeguards Alert - " . $dj_name;
        $message = "URGENT: Immediate attention required\n\n";
        $message .= "DJ: " . $dj_name . "\n";
        $message .= "Alert Type: " . $alert_type . "\n";
        $message .= "Reason: " . $reason . "\n";
        $message .= "Time: " . current_time('mysql') . "\n\n";
        $message .= "Please review immediately in the admin dashboard:\n";
        $message .= admin_url('admin.php?page=dj-safeguards');
        
        // Send email
        wp_mail($admin_email, $subject, $message, array(), array(), true); // High priority
        
        // Send SMS if configured
        $admin_phone = get_option('dj_hire_admin_phone');
        if (!empty($admin_phone)) {
            $this->send_admin_sms($admin_phone, "URGENT DJ Alert: " . $dj_name . " - " . $alert_type . ". Check admin dashboard immediately.");
        }
    }
    
    /**
     * Send SMS alert to admin
     */
    private function send_admin_sms($phone, $message) {
        // This would integrate with SMS provider
        // For now, we'll use GoHighLevel if available
        $ghl_integration = new GHL_Integration();
        $admin_contact = $ghl_integration->find_contact_by_phone($phone);
        
        if ($admin_contact) {
            $ghl_integration->send_sms($admin_contact['id'], $message);
        }
    }
    
    /**
     * Generate safeguards report
     */
    public function generate_report($start_date, $end_date) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_safeguards_log';
        
        $report_data = array();
        
        // Alert summary
        $report_data['alert_summary'] = $wpdb->get_results($wpdb->prepare("
            SELECT alert_level, COUNT(*) as count
            FROM $table_name
            WHERE created_at BETWEEN %s AND %s
            GROUP BY alert_level
        ", $start_date, $end_date));
        
        // DJ violation summary
        $report_data['dj_violations'] = $wpdb->get_results($wpdb->prepare("
            SELECT sl.dj_id, p.post_title as dj_name, 
                   COUNT(*) as total_alerts,
                   SUM(CASE WHEN sl.alert_level = 'high' THEN 1 ELSE 0 END) as high_alerts,
                   SUM(CASE WHEN sl.alert_level = 'medium' THEN 1 ELSE 0 END) as medium_alerts
            FROM $table_name sl
            INNER JOIN {$wpdb->posts} p ON sl.dj_id = p.ID
            WHERE sl.created_at BETWEEN %s AND %s
            AND sl.alert_level IN ('high', 'medium')
            GROUP BY sl.dj_id
            ORDER BY high_alerts DESC, total_alerts DESC
        ", $start_date, $end_date));
        
        // Activity type breakdown
        $report_data['activity_types'] = $wpdb->get_results($wpdb->prepare("
            SELECT 
                CASE 
                    WHEN new_status LIKE 'flagged_%' THEN SUBSTRING(new_status, 9)
                    ELSE new_status
                END as activity_type,
                COUNT(*) as count
            FROM $table_name
            WHERE created_at BETWEEN %s AND %s
            AND alert_level IN ('high', 'medium')
            GROUP BY activity_type
            ORDER BY count DESC
        ", $start_date, $end_date));
        
        // Resolution status
        $report_data['resolution_status'] = $wpdb->get_results($wpdb->prepare("
            SELECT 
                CASE 
                    WHEN reviewed_at IS NOT NULL THEN 'reviewed'
                    ELSE 'pending'
                END as status,
                COUNT(*) as count
            FROM $table_name
            WHERE created_at BETWEEN %s AND %s
            AND alert_level IN ('high', 'medium')
            GROUP BY status
        ", $start_date, $end_date));
        
        return $report_data;
    }
}

// Hook for investigation reminders
add_action('dj_investigation_reminder', 'dj_investigation_reminder_callback');
function dj_investigation_reminder_callback($alert_id) {
    $admin_email = get_option('admin_email');
    $subject = "Reminder: DJ Investigation Required";
    $message = "This is a reminder that alert ID " . $alert_id . " requires investigation.\n\n";
    $message .= "Please review in the admin dashboard: " . admin_url('admin.php?page=dj-safeguards');
    
    wp_mail($admin_email, $subject, $message);
}
?>