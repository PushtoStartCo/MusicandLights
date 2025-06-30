<?php
/**
 * Music & Lights Admin Class - Main Admin Controller
 * 
 * Handles the main admin interface, dashboard, and navigation
 * for the Music & Lights DJ management system.
 * 
 * @package MusicAndLights
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class ML_Admin {
    
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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'init_admin'));
        add_action('wp_ajax_ml_dashboard_stats', array($this, 'get_dashboard_stats'));
        add_action('wp_ajax_ml_recent_activity', array($this, 'get_recent_activity'));
    }
    
    /**
     * Initialize admin
     */
    public function init_admin() {
        // Check if user can access ML admin
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Initialize admin components
        $this->init_admin_components();
    }
    
    /**
     * Initialize admin components
     */
    private function init_admin_components() {
        // Include admin classes
        require_once plugin_dir_path(__FILE__) . 'class-ml-settings.php';
        require_once plugin_dir_path(__FILE__) . 'class-ml-dj-admin.php';
        require_once plugin_dir_path(__FILE__) . 'class-ml-booking-admin.php';
        
        // Initialize admin classes
        ML_Settings::get_instance();
        ML_DJ_Admin::get_instance();
        ML_Booking_Admin::get_instance();
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu page
        add_menu_page(
            'Music & Lights',
            'Music & Lights',
            'manage_options',
            'ml-dashboard',
            array($this, 'display_dashboard'),
            'dashicons-controls-volumeon',
            30
        );
        
        // Dashboard (duplicate of main page)
        add_submenu_page(
            'ml-dashboard',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'ml-dashboard',
            array($this, 'display_dashboard')
        );
        
        // Bookings
        add_submenu_page(
            'ml-dashboard',
            'Bookings',
            'Bookings',
            'manage_options',
            'ml-bookings',
            array($this, 'display_bookings')
        );
        
        // DJ Management
        add_submenu_page(
            'ml-dashboard',
            'DJ Management',
            'DJ Management',
            'manage_options',
            'ml-djs',
            array($this, 'display_djs')
        );
        
        // Commissions
        add_submenu_page(
            'ml-dashboard',
            'Commissions',
            'Commissions',
            'manage_options',
            'ml-commissions',
            array($this, 'display_commissions')
        );
        
        // Equipment
        add_submenu_page(
            'ml-dashboard',
            'Equipment',
            'Equipment',
            'manage_options',
            'ml-equipment',
            array($this, 'display_equipment')
        );
        
        // Safeguards
        add_submenu_page(
            'ml-dashboard',
            'Safeguards',
            'Safeguards',
            'manage_options',
            'ml-safeguards',
            array($this, 'display_safeguards')
        );
        
        // Settings
        add_submenu_page(
            'ml-dashboard',
            'Settings',
            'Settings',
            'manage_options',
            'ml-settings',
            array($this, 'display_settings')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on ML admin pages
        if (strpos($hook, 'ml-') === false && $hook !== 'toplevel_page_ml-dashboard') {
            return;
        }
        
        // Enqueue styles
        wp_enqueue_style(
            'ml-admin-style',
            plugin_dir_url(__FILE__) . '../assets/css/admin.css',
            array(),
            ML_VERSION
        );
        
        // Enqueue scripts
        wp_enqueue_script(
            'ml-admin-script',
            plugin_dir_url(__FILE__) . '../assets/js/admin.js',
            array('jquery', 'wp-util'),
            ML_VERSION,
            true
        );
        
        // Chart.js for dashboard
        wp_enqueue_script(
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js',
            array(),
            '3.9.1',
            true
        );
        
        // FullCalendar for calendar views
        wp_enqueue_script(
            'fullcalendar',
            'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js',
            array(),
            '6.1.8',
            true
        );
        
        // Localize script
        wp_localize_script('ml-admin-script', 'ml_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ml_admin_nonce'),
            'current_page' => $hook
        ));
    }
    
    /**
     * Display dashboard
     */
    public function display_dashboard() {
        include plugin_dir_path(__FILE__) . 'views/dashboard.php';
    }
    
    /**
     * Display bookings page
     */
    public function display_bookings() {
        include plugin_dir_path(__FILE__) . 'views/bookings.php';
    }
    
    /**
     * Display DJs page
     */
    public function display_djs() {
        include plugin_dir_path(__FILE__) . 'views/djs.php';
    }
    
    /**
     * Display commissions page
     */
    public function display_commissions() {
        include plugin_dir_path(__FILE__) . 'views/commissions.php';
    }
    
    /**
     * Display equipment page
     */
    public function display_equipment() {
        include plugin_dir_path(__FILE__) . 'views/equipment.php';
    }
    
    /**
     * Display safeguards page
     */
    public function display_safeguards() {
        include plugin_dir_path(__FILE__) . 'views/safeguards.php';
    }
    
    /**
     * Display settings page
     */
    public function display_settings() {
        include plugin_dir_path(__FILE__) . 'views/settings.php';
    }
    
    /**
     * Get dashboard statistics
     */
    public function get_dashboard_stats() {
        if (!wp_verify_nonce($_POST['nonce'], 'ml_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        global $wpdb;
        
        $stats = array();
        
        // Booking statistics
        $stats['bookings'] = array(
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ml_bookings"),
            'pending' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ml_bookings WHERE status = 'pending'"),
            'confirmed' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ml_bookings WHERE status = 'confirmed'"),
            'completed' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ml_bookings WHERE status = 'completed'"),
            'cancelled' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ml_bookings WHERE status = 'cancelled'"),
            'this_month' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ml_bookings WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")
        );
        
        // Revenue statistics
        $stats['revenue'] = array(
            'total' => $wpdb->get_var("SELECT SUM(total_cost) FROM {$wpdb->prefix}ml_bookings WHERE status = 'completed'"),
            'this_month' => $wpdb->get_var("SELECT SUM(total_cost) FROM {$wpdb->prefix}ml_bookings WHERE status = 'completed' AND MONTH(event_date) = MONTH(CURDATE()) AND YEAR(event_date) = YEAR(CURDATE())"),
            'pending_deposits' => $wpdb->get_var("SELECT SUM(deposit_amount) FROM {$wpdb->prefix}ml_bookings WHERE status = 'pending' AND deposit_paid = 0"),
            'commission_owed' => $wpdb->get_var("SELECT SUM(amount) FROM {$wpdb->prefix}ml_commissions WHERE status = 'pending'")
        );
        
        // DJ statistics
        $stats['djs'] = array(
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ml_djs WHERE status = 'active'"),
            'active_this_month' => $wpdb->get_var("SELECT COUNT(DISTINCT dj_id) FROM {$wpdb->prefix}ml_bookings WHERE MONTH(event_date) = MONTH(CURDATE()) AND YEAR(event_date) = YEAR(CURDATE()) AND status IN ('confirmed', 'completed')")
        );
        
        // Equipment statistics
        $stats['equipment'] = array(
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ml_equipment"),
            'available' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ml_equipment WHERE status = 'available'"),
            'rented' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ml_equipment WHERE status = 'rented'"),
            'maintenance' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ml_equipment WHERE status = 'maintenance'")
        );
        
        // Recent bookings for chart
        $stats['recent_bookings'] = $wpdb->get_results(
            "SELECT DATE(created_at) as date, COUNT(*) as count 
            FROM {$wpdb->prefix}ml_bookings 
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC"
        );
        
        // Top DJs by bookings
        $stats['top_djs'] = $wpdb->get_results(
            "SELECT d.first_name, d.last_name, d.stage_name, COUNT(b.id) as booking_count, SUM(b.total_cost) as total_revenue
            FROM {$wpdb->prefix}ml_djs d
            LEFT JOIN {$wpdb->prefix}ml_bookings b ON d.id = b.dj_id AND b.status = 'completed'
            WHERE d.status = 'active'
            GROUP BY d.id
            ORDER BY booking_count DESC
            LIMIT 5"
        );
        
        // Safeguards alerts
        $stats['safeguards'] = array(
            'total_alerts' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ml_safeguards_log WHERE status = 'open'"),
            'high_priority' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ml_safeguards_log WHERE status = 'open' AND severity = 'high'"),
            'recent_alerts' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ml_safeguards_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")
        );
        
        wp_send_json_success($stats);
    }
    
    /**
     * Get recent activity
     */
    public function get_recent_activity() {
        if (!wp_verify_nonce($_POST['nonce'], 'ml_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        global $wpdb;
        
        $activities = array();
        
        // Recent bookings
        $recent_bookings = $wpdb->get_results(
            "SELECT 'booking' as type, id, first_name, last_name, event_date, status, created_at
            FROM {$wpdb->prefix}ml_bookings 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY created_at DESC
            LIMIT 10"
        );
        
        foreach ($recent_bookings as $booking) {
            $activities[] = array(
                'type' => 'booking',
                'title' => 'New booking from ' . $booking->first_name . ' ' . $booking->last_name,
                'description' => 'Event on ' . date('jS F Y', strtotime($booking->event_date)) . ' - Status: ' . ucfirst($booking->status),
                'time' => $booking->created_at,
                'link' => admin_url('admin.php?page=ml-bookings&booking_id=' . $booking->id)
            );
        }
        
        // Recent commission payments
        $recent_commissions = $wpdb->get_results(
            "SELECT c.*, d.first_name, d.last_name, d.stage_name
            FROM {$wpdb->prefix}ml_commissions c
            JOIN {$wpdb->prefix}ml_djs d ON c.dj_id = d.id
            WHERE c.payment_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY c.payment_date DESC
            LIMIT 5"
        );
        
        foreach ($recent_commissions as $commission) {
            $dj_name = $commission->stage_name ?: $commission->first_name . ' ' . $commission->last_name;
            $activities[] = array(
                'type' => 'commission',
                'title' => 'Commission paid to ' . $dj_name,
                'description' => '£' . number_format($commission->amount, 2) . ' for booking #' . $commission->booking_id,
                'time' => $commission->payment_date,
                'link' => admin_url('admin.php?page=ml-commissions&dj_id=' . $commission->dj_id)
            );
        }
        
        // Recent safeguards alerts
        $recent_alerts = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}ml_safeguards_log 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY created_at DESC
            LIMIT 5"
        );
        
        foreach ($recent_alerts as $alert) {
            $activities[] = array(
                'type' => 'alert',
                'title' => 'Safeguards Alert: ' . ucwords(str_replace('_', ' ', $alert->alert_type)),
                'description' => 'Severity: ' . ucfirst($alert->severity),
                'time' => $alert->created_at,
                'link' => admin_url('admin.php?page=ml-safeguards&alert_id=' . $alert->id)
            );
        }
        
        // Sort all activities by time
        usort($activities, function($a, $b) {
            return strtotime($b['time']) - strtotime($a['time']);
        });
        
        wp_send_json_success(array_slice($activities, 0, 15));
    }
    
    /**
     * Get admin notices
     */
    public function get_admin_notices() {
        $notices = array();
        
        // Check for pending actions
        global $wpdb;
        
        // Pending deposits
        $pending_deposits = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ml_bookings 
            WHERE status = 'pending' AND deposit_paid = 0"
        );
        
        if ($pending_deposits > 0) {
            $notices[] = array(
                'type' => 'warning',
                'message' => $pending_deposits . ' bookings are awaiting deposit payment.',
                'action_text' => 'View Bookings',
                'action_link' => admin_url('admin.php?page=ml-bookings&status=pending')
            );
        }
        
        // High priority safeguards alerts
        $high_priority_alerts = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ml_safeguards_log 
            WHERE status = 'open' AND severity = 'high'"
        );
        
        if ($high_priority_alerts > 0) {
            $notices[] = array(
                'type' => 'error',
                'message' => $high_priority_alerts . ' high priority safeguards alerts require attention.',
                'action_text' => 'View Alerts',
                'action_link' => admin_url('admin.php?page=ml-safeguards')
            );
        }
        
        // Commission payments due
        $commissions_due = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ml_commissions 
            WHERE status = 'pending' AND due_date <= CURDATE()"
        );
        
        if ($commissions_due > 0) {
            $notices[] = array(
                'type' => 'info',
                'message' => $commissions_due . ' commission payments are due.',
                'action_text' => 'View Commissions',
                'action_link' => admin_url('admin.php?page=ml-commissions&status=due')
            );
        }
        
        return $notices;
    }
    
    /**
     * Display admin notice
     */
    public function display_admin_notice($notice) {
        $class = 'notice notice-' . $notice['type'];
        echo '<div class="' . esc_attr($class) . '">';
        echo '<p>' . esc_html($notice['message']) . '</p>';
        if (isset($notice['action_link'])) {
            echo '<p><a href="' . esc_url($notice['action_link']) . '" class="button">' . esc_html($notice['action_text']) . '</a></p>';
        }
        echo '</div>';
    }
    
    /**
     * Get summary widget data
     */
    public function get_summary_widgets() {
        global $wpdb;
        
        $widgets = array();
        
        // Today's events
        $todays_events = $wpdb->get_results(
            "SELECT b.*, d.first_name as dj_first_name, d.last_name as dj_last_name, d.stage_name
            FROM {$wpdb->prefix}ml_bookings b
            LEFT JOIN {$wpdb->prefix}ml_djs d ON b.dj_id = d.id
            WHERE b.event_date = CURDATE()
            AND b.status IN ('confirmed', 'completed')
            ORDER BY b.event_time"
        );
        
        $widgets['todays_events'] = array(
            'title' => 'Today\'s Events',
            'count' => count($todays_events),
            'items' => $todays_events
        );
        
        // This week's bookings
        $this_week = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ml_bookings 
            WHERE WEEK(event_date) = WEEK(CURDATE()) 
            AND YEAR(event_date) = YEAR(CURDATE())
            AND status IN ('confirmed', 'completed')"
        );
        
        $widgets['this_week'] = array(
            'title' => 'This Week\'s Bookings',
            'count' => $this_week
        );
        
        // Monthly revenue
        $monthly_revenue = $wpdb->get_var(
            "SELECT SUM(total_cost) FROM {$wpdb->prefix}ml_bookings 
            WHERE MONTH(event_date) = MONTH(CURDATE()) 
            AND YEAR(event_date) = YEAR(CURDATE())
            AND status = 'completed'"
        );
        
        $widgets['monthly_revenue'] = array(
            'title' => 'This Month\'s Revenue',
            'amount' => $monthly_revenue ?: 0
        );
        
        return $widgets;
    }
    
    /**
     * Export data
     */
    public function export_data($type, $filters = array()) {
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        global $wpdb;
        
        switch ($type) {
            case 'bookings':
                return $this->export_bookings($filters);
            case 'commissions':
                return $this->export_commissions($filters);
            case 'equipment':
                return $this->export_equipment($filters);
            default:
                return false;
        }
    }
    
    /**
     * Export bookings data
     */
    private function export_bookings($filters) {
        global $wpdb;
        
        $where_conditions = array();
        $params = array();
        
        if (!empty($filters['start_date'])) {
            $where_conditions[] = "b.event_date >= %s";
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $where_conditions[] = "b.event_date <= %s";
            $params[] = $filters['end_date'];
        }
        
        if (!empty($filters['status'])) {
            $where_conditions[] = "b.status = %s";
            $params[] = $filters['status'];
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $query = "SELECT b.*, d.first_name as dj_first_name, d.last_name as dj_last_name, d.stage_name
                  FROM {$wpdb->prefix}ml_bookings b
                  LEFT JOIN {$wpdb->prefix}ml_djs d ON b.dj_id = d.id
                  $where_clause
                  ORDER BY b.event_date DESC";
        
        if (!empty($params)) {
            $bookings = $wpdb->get_results($wpdb->prepare($query, $params));
        } else {
            $bookings = $wpdb->get_results($query);
        }
        
        return $bookings;
    }
    
    /**
     * Generate CSV from data
     */
    public function generate_csv($data, $filename, $headers = array()) {
        if (empty($data)) {
            return false;
        }
        
        // Set headers for download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // Write headers
        if (!empty($headers)) {
            fputcsv($output, $headers);
        } else {
            // Use first row keys as headers
            $first_row = (array) $data[0];
            fputcsv($output, array_keys($first_row));
        }
        
        // Write data
        foreach ($data as $row) {
            fputcsv($output, (array) $row);
        }
        
        fclose($output);
        exit;
    }
}

// Initialize the admin class
ML_Admin::get_instance(); Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Admin pages
     */
    private $admin_pages = array();
    
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
     *