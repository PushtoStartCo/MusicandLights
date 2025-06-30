<?php
/**
 * Music & Lights Calendar Class - Booking Calendar Management
 * 
 * Handles DJ availability tracking, booking calendar display,
 * and schedule management functionality.
 * 
 * @package MusicAndLights
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class ML_Calendar {
    
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
        add_action('wp_ajax_ml_get_availability', array($this, 'get_availability'));
        add_action('wp_ajax_nopriv_ml_get_availability', array($this, 'get_availability'));
        add_action('wp_ajax_ml_set_dj_availability', array($this, 'set_dj_availability'));
        add_action('wp_ajax_ml_get_calendar_events', array($this, 'get_calendar_events'));
        add_action('wp_ajax_ml_update_booking_schedule', array($this, 'update_booking_schedule'));
        add_shortcode('ml_calendar', array($this, 'calendar_shortcode'));
        add_shortcode('ml_dj_calendar', array($this, 'dj_calendar_shortcode'));
    }
    
    /**
     * Get DJ availability for specific date
     */
    public function get_availability() {
        $dj_id = intval($_POST['dj_id']);
        $date = sanitize_text_field($_POST['date']);
        
        if (!$dj_id || !$date) {
            wp_send_json_error('Missing parameters');
            return;
        }
        
        $availability = $this->check_dj_availability($dj_id, $date);
        
        wp_send_json_success(array(
            'available' => $availability['available'],
            'reason' => $availability['reason'],
            'available_times' => $availability['available_times']
        ));
    }
    
    /**
     * Check DJ availability for specific date
     */
    public function check_dj_availability($dj_id, $date) {
        global $wpdb;
        
        // Check if date is in the past
        if (strtotime($date) < strtotime('today')) {
            return array(
                'available' => false,
                'reason' => 'Date is in the past',
                'available_times' => array()
            );
        }
        
        // Check if DJ has any bookings on this date
        $existing_bookings = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ml_bookings 
                WHERE dj_id = %d 
                AND event_date = %s 
                AND status IN ('confirmed', 'pending')",
                $dj_id,
                $date
            )
        );
        
        // Check DJ's availability settings
        $dj_availability = $this->get_dj_availability_settings($dj_id);
        $day_of_week = strtolower(date('l', strtotime($date)));
        
        if (!isset($dj_availability[$day_of_week]) || !$dj_availability[$day_of_week]['available']) {
            return array(
                'available' => false,
                'reason' => 'DJ not available on ' . ucfirst($day_of_week) . 's',
                'available_times' => array()
            );
        }
        
        // Check for blocked dates
        $blocked_dates = $this->get_dj_blocked_dates($dj_id);
        if (in_array($date, $blocked_dates)) {
            return array(
                'available' => false,
                'reason' => 'DJ has blocked this date',
                'available_times' => array()
            );
        }
        
        // Calculate available time slots
        $available_times = $this->calculate_available_times($dj_id, $date, $existing_bookings, $dj_availability[$day_of_week]);
        
        return array(
            'available' => !empty($available_times),
            'reason' => empty($available_times) ? 'No time slots available' : 'Available',
            'available_times' => $available_times
        );
    }
    
    /**
     * Get DJ availability settings
     */
    private function get_dj_availability_settings($dj_id) {
        global $wpdb;
        
        $settings = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT availability_settings FROM {$wpdb->prefix}ml_djs WHERE id = %d",
                $dj_id
            )
        );
        
        if ($settings) {
            return json_decode($settings, true);
        }
        
        // Default availability (available every day 12:00-23:00)
        return array(
            'monday' => array('available' => true, 'start_time' => '12:00', 'end_time' => '23:00'),
            'tuesday' => array('available' => true, 'start_time' => '12:00', 'end_time' => '23:00'),
            'wednesday' => array('available' => true, 'start_time' => '12:00', 'end_time' => '23:00'),
            'thursday' => array('available' => true, 'start_time' => '12:00', 'end_time' => '23:00'),
            'friday' => array('available' => true, 'start_time' => '12:00', 'end_time' => '23:00'),
            'saturday' => array('available' => true, 'start_time' => '12:00', 'end_time' => '23:00'),
            'sunday' => array('available' => true, 'start_time' => '12:00', 'end_time' => '23:00')
        );
    }
    
    /**
     * Get DJ blocked dates
     */
    private function get_dj_blocked_dates($dj_id) {
        global $wpdb;
        
        $blocked_dates = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT date_blocked FROM {$wpdb->prefix}ml_dj_availability 
                WHERE dj_id = %d 
                AND date_blocked >= CURDATE() 
                AND status = 'blocked'",
                $dj_id
            ),
            ARRAY_A
        );
        
        return array_column($blocked_dates, 'date_blocked');
    }
    
    /**
     * Calculate available time slots
     */
    private function calculate_available_times($dj_id, $date, $existing_bookings, $day_settings) {
        $available_times = array();
        
        $start_time = strtotime($date . ' ' . $day_settings['start_time']);
        $end_time = strtotime($date . ' ' . $day_settings['end_time']);
        
        // Generate hourly slots
        for ($time = $start_time; $time < $end_time; $time += 3600) {
            $slot_start = date('H:i', $time);
            $slot_end = date('H:i', $time + 3600);
            
            $slot_available = true;
            
            // Check against existing bookings
            foreach ($existing_bookings as $booking) {
                $booking_start = strtotime($date . ' ' . $booking->event_time);
                $booking_end = $booking_start + (intval($booking->duration) * 3600);
                
                if (($time >= $booking_start && $time < $booking_end) ||
                    ($time + 3600 > $booking_start && $time + 3600 <= $booking_end)) {
                    $slot_available = false;
                    break;
                }
            }
            
            if ($slot_available) {
                $available_times[] = array(
                    'start' => $slot_start,
                    'end' => $slot_end,
                    'display' => $slot_start . ' - ' . $slot_end
                );
            }
        }
        
        return $available_times;
    }
    
    /**
     * Set DJ availability
     */
    public function set_dj_availability() {
        if (!wp_verify_nonce($_POST['nonce'], 'ml_dj_nonce')) {
            wp_die('Security check failed');
        }
        
        $dj_id = intval($_POST['dj_id']);
        $availability_data = $_POST['availability'];
        
        if (!$dj_id || !$availability_data) {
            wp_send_json_error('Missing parameters');
            return;
        }
        
        // Validate and sanitize availability data
        $sanitized_availability = array();
        $days = array('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday');
        
        foreach ($days as $day) {
            if (isset($availability_data[$day])) {
                $sanitized_availability[$day] = array(
                    'available' => (bool) $availability_data[$day]['available'],
                    'start_time' => sanitize_text_field($availability_data[$day]['start_time']),
                    'end_time' => sanitize_text_field($availability_data[$day]['end_time'])
                );
            }
        }
        
        global $wpdb;
        $result = $wpdb->update(
            $wpdb->prefix . 'ml_djs',
            array('availability_settings' => json_encode($sanitized_availability)),
            array('id' => $dj_id),
            array('%s'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success('Availability updated successfully');
        } else {
            wp_send_json_error('Failed to update availability');
        }
    }
    
    /**
     * Block specific dates
     */
    public function block_dates($dj_id, $dates, $reason = '') {
        global $wpdb;
        
        $success_count = 0;
        
        foreach ($dates as $date) {
            $result = $wpdb->replace(
                $wpdb->prefix . 'ml_dj_availability',
                array(
                    'dj_id' => $dj_id,
                    'date_blocked' => $date,
                    'status' => 'blocked',
                    'reason' => $reason,
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%s', '%s')
            );
            
            if ($result) {
                $success_count++;
            }
        }
        
        return $success_count;
    }
    
    /**
     * Unblock specific dates
     */
    public function unblock_dates($dj_id, $dates) {
        global $wpdb;
        
        $date_placeholders = implode(',', array_fill(0, count($dates), '%s'));
        $params = array_merge(array($dj_id), $dates);
        
        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}ml_dj_availability 
                WHERE dj_id = %d AND date_blocked IN ($date_placeholders)",
                $params
            )
        );
    }
    
    /**
     * Get calendar events for admin
     */
    public function get_calendar_events() {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $start_date = sanitize_text_field($_POST['start']);
        $end_date = sanitize_text_field($_POST['end']);
        
        global $wpdb;
        $bookings = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT b.*, d.first_name as dj_first_name, d.last_name as dj_last_name, d.stage_name
                FROM {$wpdb->prefix}ml_bookings b
                LEFT JOIN {$wpdb->prefix}ml_djs d ON b.dj_id = d.id
                WHERE b.event_date >= %s AND b.event_date <= %s
                AND b.status IN ('confirmed', 'pending', 'completed')",
                $start_date,
                $end_date
            )
        );
        
        $events = array();
        foreach ($bookings as $booking) {
            $dj_name = $booking->stage_name ?: $booking->dj_first_name . ' ' . $booking->dj_last_name;
            
            $events[] = array(
                'id' => $booking->id,
                'title' => $booking->first_name . ' ' . $booking->last_name . ' (' . $dj_name . ')',
                'start' => $booking->event_date . 'T' . $booking->event_time,
                'end' => $booking->event_date . 'T' . date('H:i:s', strtotime($booking->event_time . ' +' . $booking->duration . ' hours')),
                'backgroundColor' => $this->get_status_color($booking->status),
                'borderColor' => $this->get_status_color($booking->status),
                'url' => admin_url('admin.php?page=ml-bookings&booking_id=' . $booking->id),
                'extendedProps' => array(
                    'status' => $booking->status,
                    'venue' => $booking->venue_name,
                    'customer' => $booking->first_name . ' ' . $booking->last_name,
                    'dj' => $dj_name,
                    'total_cost' => $booking->total_cost
                )
            );
        }
        
        wp_send_json($events);
    }
    
    /**
     * Get status color for calendar events
     */
    private function get_status_color($status) {
        $colors = array(
            'pending' => '#f39c12',
            'confirmed' => '#27ae60',
            'completed' => '#3498db',
            'cancelled' => '#e74c3c'
        );
        
        return isset($colors[$status]) ? $colors[$status] : '#95a5a6';
    }
    
    /**
     * Update booking schedule
     */
    public function update_booking_schedule() {
        if (!wp_verify_nonce($_POST['nonce'], 'ml_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        $booking_id = intval($_POST['booking_id']);
        $new_date = sanitize_text_field($_POST['new_date']);
        $new_time = sanitize_text_field($_POST['new_time']);
        
        if (!$booking_id || !$new_date || !$new_time) {
            wp_send_json_error('Missing parameters');
            return;
        }
        
        // Check if new slot is available
        $booking = $this->get_booking($booking_id);
        if (!$booking) {
            wp_send_json_error('Booking not found');
            return;
        }
        
        $availability = $this->check_dj_availability($booking->dj_id, $new_date);
        if (!$availability['available']) {
            wp_send_json_error('DJ not available on selected date: ' . $availability['reason']);
            return;
        }
        
        // Update booking
        global $wpdb;
        $result = $wpdb->update(
            $wpdb->prefix . 'ml_bookings',
            array(
                'event_date' => $new_date,
                'event_time' => $new_time,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $booking_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            // Send notification emails
            do_action('ml_booking_rescheduled', $booking_id, $new_date, $new_time);
            
            wp_send_json_success('Booking rescheduled successfully');
        } else {
            wp_send_json_error('Failed to update booking');
        }
    }
    
    /**
     * Calendar shortcode for public display
     */
    public function calendar_shortcode($atts) {
        $atts = shortcode_atts(array(
            'dj_id' => '',
            'view' => 'month',
            'height' => '600'
        ), $atts);
        
        wp_enqueue_script('fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js', array(), '6.1.8', true);
        
        $calendar_id = 'ml-calendar-' . uniqid();
        
        ob_start();
        ?>
        <div id="<?php echo esc_attr($calendar_id); ?>" style="height: <?php echo esc_attr($atts['height']); ?>px;"></div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('<?php echo esc_js($calendar_id); ?>');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: '<?php echo esc_js($atts['view']); ?>',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: function(info, successCallback, failureCallback) {
                    jQuery.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'ml_get_public_events',
                            dj_id: '<?php echo esc_js($atts['dj_id']); ?>',
                            start: info.startStr,
                            end: info.endStr
                        },
                        success: function(data) {
                            successCallback(data);
                        },
                        error: function() {
                            failureCallback();
                        }
                    });
                },
                eventClick: function(info) {
                    alert('Event: ' + info.event.title);
                    info.jsEvent.preventDefault();
                }
            });
            calendar.render();
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * DJ calendar shortcode
     */
    public function dj_calendar_shortcode($atts) {
        // Check if user is logged in DJ
        if (!is_user_logged_in()) {
            return '<p>Please log in to view your calendar.</p>';
        }
        
        $dj_id = $this->get_current_dj_id();
        if (!$dj_id) {
            return '<p>DJ profile not found.</p>';
        }
        
        $atts = shortcode_atts(array(
            'view' => 'month',
            'height' => '600'
        ), $atts);
        
        $atts['dj_id'] = $dj_id;
        
        return $this->calendar_shortcode($atts);
    }
    
    /**
     * Get current DJ ID from logged in user
     */
    private function get_current_dj_id() {
        global $wpdb;
        
        $user_id = get_current_user_id();
        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ml_djs WHERE user_id = %d",
                $user_id
            )
        );
    }
    
    /**
     * Get booking details
     */
    private function get_booking($booking_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ml_bookings WHERE id = %d", $booking_id));
    }
    
    /**
     * Get DJ's upcoming events
     */
    public function get_dj_upcoming_events($dj_id, $limit = 10) {
        global $wpdb;
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ml_bookings 
                WHERE dj_id = %d 
                AND event_date >= CURDATE() 
                AND status IN ('confirmed', 'pending')
                ORDER BY event_date ASC, event_time ASC 
                LIMIT %d",
                $dj_id,
                $limit
            )
        );
    }
    
    /**
     * Get monthly booking statistics
     */
    public function get_monthly_stats($dj_id = null, $year = null, $month = null) {
        global $wpdb;
        
        if (!$year) $year = date('Y');
        if (!$month) $month = date('m');
        
        $where_clause = "WHERE YEAR(event_date) = %d AND MONTH(event_date) = %d";
        $params = array($year, $month);
        
        if ($dj_id) {
            $where_clause .= " AND dj_id = %d";
            $params[] = $dj_id;
        }
        
        $stats = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT 
                COUNT(*) as total_bookings,
                SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
                SUM(CASE WHEN status = 'completed' THEN total_cost ELSE 0 END) as total_revenue
                FROM {$wpdb->prefix}ml_bookings 
                $where_clause",
                $params
            )
        );
        
        return $stats;
    }
}

// Initialize the calendar class
ML_Calendar::get_instance();