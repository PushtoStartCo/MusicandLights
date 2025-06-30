<?php
/**
 * Music & Lights Safeguards Class - DJ Monitoring System
 * 
 * Monitors DJ behavior to prevent circumvention of agency commission
 * and tracks potential direct booking attempts.
 * 
 * @package MusicAndLights
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class ML_Safeguards {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Monitoring settings
     */
    private $enabled;
    private $alert_threshold;
    private $admin_email;
    
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
        $this->enabled = isset($settings['safeguards_enabled']) ? $settings['safeguards_enabled'] : true;
        $this->alert_threshold = isset($settings['safeguards_threshold']) ? intval($settings['safeguards_threshold']) : 3;
        $this->admin_email = isset($settings['admin_email']) ? $settings['admin_email'] : get_option('admin_email');
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        if ($this->enabled) {
            add_action('ml_booking_created', array($this, 'monitor_new_booking'), 10, 1);
            add_action('ml_dj_profile_viewed', array($this, 'log_profile_view'), 10, 2);
            add_action('ml_dj_contact_accessed', array($this, 'monitor_contact_access'), 10, 2);
            add_action('wp_ajax_ml_report_suspicious_activity', array($this, 'report_suspicious_activity'));
            add_action('ml_safeguards_daily_check', array($this, 'daily_monitoring_check'));
            
            // Schedule daily checks
            if (!wp_next_scheduled('ml_safeguards_daily_check')) {
                wp_schedule_event(time(), 'daily', 'ml_safeguards_daily_check');
            }
        }
    }
    
    /**
     * Monitor new booking for suspicious patterns
     */
    public function monitor_new_booking($booking_id) {
        $booking = $this->get_booking($booking_id);
        if (!$booking) {
            return;
        }
        
        // Check for duplicate customer patterns
        $this->check_duplicate_customers($booking);
        
        // Check for suspicious timing patterns
        $this->check_booking_timing($booking);
        
        // Check for unusual communication patterns
        $this->check_communication_patterns($booking);
        
        // Check customer source patterns
        $this->check_customer_source($booking);
    }
    
    /**
     * Check for duplicate customers
     */
    private function check_duplicate_customers($booking) {
        global $wpdb;
        
        // Check for same customer booking multiple DJs
        $duplicate_bookings = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ml_bookings 
                WHERE (email = %s OR phone = %s) 
                AND id != %d 
                AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)",
                $booking->email,
                $booking->phone,
                $booking->id
            )
        );
        
        if (count($duplicate_bookings) > 1) {
            $this->create_alert('duplicate_customer', $booking->id, array(
                'customer_email' => $booking->email,
                'customer_phone' => $booking->phone,
                'duplicate_count' => count($duplicate_bookings),
                'message' => 'Customer has made multiple bookings with different DJs recently'
            ));
        }
    }
    
    /**
     * Check booking timing patterns
     */
    private function check_booking_timing($booking) {
        global $wpdb;
        
        // Check for rapid succession bookings
        $recent_bookings = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ml_bookings 
                WHERE created_at > DATE_SUB(%s, INTERVAL 1 HOUR)
                AND id != %d",
                $booking->created_at,
                $booking->id
            )
        );
        
        if (count($recent_bookings) >= 3) {
            $this->create_alert('rapid_bookings', $booking->id, array(
                'recent_count' => count($recent_bookings),
                'timeframe' => '1 hour',
                'message' => 'Unusually high number of bookings in short timeframe'
            ));
        }
        
        // Check for off-hours booking patterns
        $booking_hour = date('H', strtotime($booking->created_at));
        if ($booking_hour < 6 || $booking_hour > 23) {
            $this->create_alert('unusual_hours', $booking->id, array(
                'booking_hour' => $booking_hour,
                'message' => 'Booking created during unusual hours'
            ));
        }
    }
    
    /**
     * Check communication patterns
     */
    private function check_communication_patterns($booking) {
        // Check for suspicious email domains
        $suspicious_domains = array('tempmail.org', '10minutemail.com', 'guerrillamail.com', 'throwaway.email');
        $email_domain = substr(strrchr($booking->email, '@'), 1);
        
        if (in_array($email_domain, $suspicious_domains)) {
            $this->create_alert('suspicious_email', $booking->id, array(
                'email_domain' => $email_domain,
                'message' => 'Customer using temporary email service'
            ));
        }
        
        // Check for unusual phone patterns
        if (!preg_match('/^(\+44|0)[1-9]\d{8,9}$/', $booking->phone)) {
            $this->create_alert('unusual_phone', $booking->id, array(
                'phone_number' => $booking->phone,
                'message' => 'Phone number doesn\'t match UK format'
            ));
        }
    }
    
    /**
     * Check customer source
     */
    private function check_customer_source($booking) {
        // Check if customer found DJ through direct search rather than agency
        if (isset($booking->how_found) && in_array($booking->how_found, array('direct_search', 'dj_referral', 'social_media_dj'))) {
            $this->create_alert('direct_source', $booking->id, array(
                'source' => $booking->how_found,
                'message' => 'Customer may have found DJ directly rather than through agency'
            ));
        }
    }
    
    /**
     * Log DJ profile views
     */
    public function log_profile_view($dj_id, $customer_info) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'ml_safeguards_log',
            array(
                'dj_id' => $dj_id,
                'event_type' => 'profile_view',
                'customer_info' => json_encode($customer_info),
                'ip_address' => $this->get_client_ip(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Monitor contact access
     */
    public function monitor_contact_access($dj_id, $customer_id) {
        global $wpdb;
        
        // Log the contact access
        $wpdb->insert(
            $wpdb->prefix . 'ml_safeguards_log',
            array(
                'dj_id' => $dj_id,
                'customer_id' => $customer_id,
                'event_type' => 'contact_access',
                'ip_address' => $this->get_client_ip(),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%s')
        );
        
        // Check for excessive contact access
        $recent_access = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ml_safeguards_log 
                WHERE dj_id = %d 
                AND event_type = 'contact_access' 
                AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
                $dj_id
            )
        );
        
        if ($recent_access > 10) {
            $this->create_alert('excessive_contact_access', null, array(
                'dj_id' => $dj_id,
                'access_count' => $recent_access,
                'timeframe' => '24 hours',
                'message' => 'DJ accessing customer contact details excessively'
            ));
        }
    }
    
    /**
     * Report suspicious activity
     */
    public function report_suspicious_activity() {
        if (!wp_verify_nonce($_POST['nonce'], 'ml_safeguards_nonce')) {
            wp_die('Security check failed');
        }
        
        $report_data = array(
            'reporter_email' => sanitize_email($_POST['reporter_email']),
            'dj_id' => intval($_POST['dj_id']),
            'activity_type' => sanitize_text_field($_POST['activity_type']),
            'description' => sanitize_textarea_field($_POST['description']),
            'evidence' => sanitize_textarea_field($_POST['evidence'])
        );
        
        $this->create_alert('user_report', null, $report_data);
        
        wp_send_json_success('Report submitted successfully. We will investigate this matter.');
    }
    
    /**
     * Daily monitoring check
     */
    public function daily_monitoring_check() {
        $this->check_commission_discrepancies();
        $this->check_booking_cancellation_patterns();
        $this->check_dj_performance_metrics();
        $this->send_daily_safeguards_report();
    }
    
    /**
     * Check commission discrepancies
     */
    private function check_commission_discrepancies() {
        global $wpdb;
        
        $discrepancies = $wpdb->get_results(
            "SELECT b.id, b.dj_id, b.total_cost, c.amount as commission_amount
            FROM {$wpdb->prefix}ml_bookings b
            LEFT JOIN {$wpdb->prefix}ml_commissions c ON b.id = c.booking_id
            WHERE b.status = 'completed' 
            AND (c.amount IS NULL OR c.amount != (b.total_cost * 0.75))
            AND b.completed_at > DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        
        foreach ($discrepancies as $discrepancy) {
            $this->create_alert('commission_discrepancy', $discrepancy->id, array(
                'dj_id' => $discrepancy->dj_id,
                'expected_commission' => $discrepancy->total_cost * 0.75,
                'actual_commission' => $discrepancy->commission_amount,
                'message' => 'Commission amount doesn\'t match expected calculation'
            ));
        }
    }
    
    /**
     * Check booking cancellation patterns
     */
    private function check_booking_cancellation_patterns() {
        global $wpdb;
        
        $high_cancellation_djs = $wpdb->get_results(
            "SELECT dj_id, 
            COUNT(*) as total_bookings,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
            (SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) / COUNT(*)) * 100 as cancellation_rate
            FROM {$wpdb->prefix}ml_bookings 
            WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY dj_id
            HAVING cancellation_rate > 30 AND total_bookings >= 5"
        );
        
        foreach ($high_cancellation_djs as $dj) {
            $this->create_alert('high_cancellation_rate', null, array(
                'dj_id' => $dj->dj_id,
                'cancellation_rate' => round($dj->cancellation_rate, 2),
                'total_bookings' => $dj->total_bookings,
                'cancelled_bookings' => $dj->cancelled_bookings,
                'message' => 'DJ has unusually high cancellation rate'
            ));
        }
    }
    
    /**
     * Check DJ performance metrics
     */
    private function check_dj_performance_metrics() {
        global $wpdb;
        
        // Check for DJs with declining performance
        $performance_issues = $wpdb->get_results(
            "SELECT dj_id, AVG(rating) as avg_rating, COUNT(*) as review_count
            FROM {$wpdb->prefix}ml_bookings 
            WHERE status = 'completed' 
            AND rating IS NOT NULL 
            AND completed_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY dj_id 
            HAVING avg_rating < 3.5 AND review_count >= 3"
        );
        
        foreach ($performance_issues as $issue) {
            $this->create_alert('poor_performance', null, array(
                'dj_id' => $issue->dj_id,
                'average_rating' => round($issue->avg_rating, 2),
                'review_count' => $issue->review_count,
                'message' => 'DJ receiving consistently poor ratings'
            ));
        }
    }
    
    /**
     * Create safeguards alert
     */
    private function create_alert($alert_type, $booking_id = null, $data = array()) {
        global $wpdb;
        
        $alert_data = array(
            'alert_type' => $alert_type,
            'booking_id' => $booking_id,
            'alert_data' => json_encode($data),
            'severity' => $this->get_alert_severity($alert_type),
            'status' => 'open',
            'created_at' => current_time('mysql')
        );
        
        $wpdb->insert(
            $wpdb->prefix . 'ml_safeguards_log',
            $alert_data,
            array('%s', '%d', '%s', '%s', '%s', '%s')
        );
        
        // Send immediate alert for high severity issues
        if ($alert_data['severity'] === 'high') {
            $this->send_immediate_alert($alert_type, $data);
        }
    }
    
    /**
     * Get alert severity
     */
    private function get_alert_severity($alert_type) {
        $high_severity = array('commission_discrepancy', 'excessive_contact_access', 'user_report');
        $medium_severity = array('duplicate_customer', 'high_cancellation_rate', 'poor_performance');
        
        if (in_array($alert_type, $high_severity)) {
            return 'high';
        } elseif (in_array($alert_type, $medium_severity)) {
            return 'medium';
        } else {
            return 'low';
        }
    }
    
    /**
     * Send immediate alert
     */
    private function send_immediate_alert($alert_type, $data) {
        $subject = 'Music & Lights Safeguards Alert: ' . ucwords(str_replace('_', ' ', $alert_type));
        
        $message = '<h2>Safeguards Alert</h2>';
        $message .= '<p><strong>Alert Type:</strong> ' . ucwords(str_replace('_', ' ', $alert_type)) . '</p>';
        $message .= '<p><strong>Time:</strong> ' . current_time('mysql') . '</p>';
        
        if (isset($data['message'])) {
            $message .= '<p><strong>Details:</strong> ' . esc_html($data['message']) . '</p>';
        }
        
        $message .= '<h3>Data:</h3>';
        $message .= '<pre>' . print_r($data, true) . '</pre>';
        
        $message .= '<p>Please review this alert in the admin dashboard.</p>';
        
        wp_mail($this->admin_email, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));
    }
    
    /**
     * Send daily safeguards report
     */
    private function send_daily_safeguards_report() {
        global $wpdb;
        
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        $alerts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ml_safeguards_log 
                WHERE DATE(created_at) = %s 
                ORDER BY severity DESC, created_at DESC",
                $yesterday
            )
        );
        
        if (empty($alerts)) {
            return; // No alerts to report
        }
        
        $subject = 'Daily Safeguards Report - ' . date('jS F Y', strtotime($yesterday));
        
        $message = '<h2>Daily Safeguards Report</h2>';
        $message .= '<p>Report for: ' . date('jS F Y', strtotime($yesterday)) . '</p>';
        $message .= '<p>Total Alerts: ' . count($alerts) . '</p>';
        
        $severity_counts = array('high' => 0, 'medium' => 0, 'low' => 0);
        foreach ($alerts as $alert) {
            $severity_counts[$alert->severity]++;
        }
        
        $message .= '<h3>Alert Summary</h3>';
        $message .= '<ul>';
        $message .= '<li>High Severity: ' . $severity_counts['high'] . '</li>';
        $message .= '<li>Medium Severity: ' . $severity_counts['medium'] . '</li>';
        $message .= '<li>Low Severity: ' . $severity_counts['low'] . '</li>';
        $message .= '</ul>';
        
        if ($severity_counts['high'] > 0 || $severity_counts['medium'] > 0) {
            $message .= '<h3>Alerts Requiring Attention</h3>';
            foreach ($alerts as $alert) {
                if ($alert->severity === 'high' || $alert->severity === 'medium') {
                    $message .= '<div style="border: 1px solid #ccc; padding: 10px; margin: 10px 0;">';
                    $message .= '<h4>' . ucwords(str_replace('_', ' ', $alert->alert_type)) . ' (' . ucfirst($alert->severity) . ')</h4>';
                    $message .= '<p>Time: ' . $alert->created_at . '</p>';
                    if ($alert->booking_id) {
                        $message .= '<p>Booking ID: ' . $alert->booking_id . '</p>';
                    }
                    $alert_data = json_decode($alert->alert_data, true);
                    if (isset($alert_data['message'])) {
                        $message .= '<p>' . esc_html($alert_data['message']) . '</p>';
                    }
                    $message .= '</div>';
                }
            }
        }
        
        $message .= '<p>Please review all alerts in the admin dashboard.</p>';
        
        wp_mail($this->admin_email, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));
    }
    
    /**
     * Get safeguards statistics
     */
    public function get_statistics($period = '30') {
        global $wpdb;
        
        $stats = array();
        
        // Total alerts
        $stats['total_alerts'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ml_safeguards_log 
                WHERE created_at > DATE_SUB(NOW(), INTERVAL %d DAY)",
                $period
            )
        );
        
        // Alerts by severity
        $severity_stats = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT severity, COUNT(*) as count 
                FROM {$wpdb->prefix}ml_safeguards_log 
                WHERE created_at > DATE_SUB(NOW(), INTERVAL %d DAY) 
                GROUP BY severity",
                $period
            )
        );
        
        $stats['by_severity'] = array();
        foreach ($severity_stats as $stat) {
            $stats['by_severity'][$stat->severity] = $stat->count;
        }
        
        // Most common alert types
        $type_stats = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT alert_type, COUNT(*) as count 
                FROM {$wpdb->prefix}ml_safeguards_log 
                WHERE created_at > DATE_SUB(NOW(), INTERVAL %d DAY) 
                GROUP BY alert_type 
                ORDER BY count DESC 
                LIMIT 5",
                $period
            )
        );
        
        $stats['top_alert_types'] = $type_stats;
        
        return $stats;
    }
    
    /**
     * Get recent alerts
     */
    public function get_recent_alerts($limit = 20) {
        global $wpdb;
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ml_safeguards_log 
                ORDER BY created_at DESC 
                LIMIT %d",
                $limit
            )
        );
    }
    
    /**
     * Mark alert as resolved
     */
    public function resolve_alert($alert_id, $resolution_notes = '') {
        global $wpdb;
        
        $wpdb->update(
            $wpdb->prefix . 'ml_safeguards_log',
            array(
                'status' => 'resolved',
                'resolution_notes' => $resolution_notes,
                'resolved_at' => current_time('mysql')
            ),
            array('id' => $alert_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }
    
    /**
     * Get booking
     */
    private function get_booking($booking_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ml_bookings WHERE id = %d", $booking_id));
    }
}

// Initialize the safeguards system
ML_Safeguards::get_instance();