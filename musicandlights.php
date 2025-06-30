<?php
/**
 * Plugin Name: Music & Lights DJ Hire Management System
 * Description: Complete DJ hire booking system with GoHighLevel integration
 * Version: 1.0.0
 * Author: Push to Start
 * Author URL: https://pushtostart.co
 * Text Domain: musicandlights
 * Requires PHP: 8.0
 * Requires at least: 5.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MUSICANDLIGHTS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MUSICANDLIGHTS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('MUSICANDLIGHTS_VERSION', '1.0.0');

class MusicAndLightsSystem {
    
    public function __construct() {
        add_action('init', [$this, 'init']);
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }
    
    public function init(): void {
        // Load required files - Check if files exist before including
        $this->load_dependencies();
        
        // Initialize components only if classes exist
        if (class_exists('DJ_Profile_Manager')) {
            new DJ_Profile_Manager();
        }
        if (class_exists('DJ_Equipment_Manager')) {
            new DJ_Equipment_Manager();
        }
        if (class_exists('DJ_Booking_System')) {
            new DJ_Booking_System();
        }
        if (class_exists('DJ_Calendar_Manager')) {
            new DJ_Calendar_Manager();
        }
        if (class_exists('DJ_Commission_Tracker')) {
            new DJ_Commission_Tracker();
        }
        if (class_exists('DJ_Safeguards_Monitor')) {
            new DJ_Safeguards_Monitor();
        }
        if (class_exists('GHL_Integration')) {
            new GHL_Integration();
        }
        if (class_exists('Distance_Calculator')) {
            new Distance_Calculator();
        }
        if (class_exists('Email_Templates')) {
            new Email_Templates();
        }
        
        // Add admin menus
        add_action('admin_menu', [$this, 'add_admin_menus']);
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // Create custom post types
        add_action('init', [$this, 'create_custom_post_types']);
        
        // Add shortcodes
        add_shortcode('musicandlights_profiles', [$this, 'display_dj_profiles']);
        add_shortcode('musicandlights_booking_form', [$this, 'display_booking_form']);
        add_shortcode('musicandlights_dashboard', [$this, 'display_dj_dashboard']);
        
        // Load text domain
        add_action('plugins_loaded', [$this, 'load_textdomain']);
    }
    
    public function load_textdomain(): void {
        load_plugin_textdomain('musicandlights', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    private function load_dependencies(): void {
        $dependencies = [
            'includes/class-dj-profile-manager.php',
            'includes/class-dj-equipment-manager.php',
            'includes/class-dj-booking-system.php',
            'includes/class-dj-calendar-manager.php',
            'includes/class-dj-commission-tracker.php',
            'includes/class-dj-safeguards-monitor.php',
            'includes/class-ghl-integration.php',
            'includes/class-distance-calculator.php',
            'includes/class-email-templates.php'
        ];
        
        foreach ($dependencies as $file) {
            $file_path = MUSICANDLIGHTS_PLUGIN_PATH . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            } else {
                error_log("Music & Lights Plugin: Missing file - {$file}");
            }
        }
    }
    
    public function create_custom_post_types(): void {
        // DJ Profile Post Type
        register_post_type('dj_profile', [
            'labels' => [
                'name' => __('DJ Profiles', 'musicandlights'),
                'singular_name' => __('DJ Profile', 'musicandlights'),
                'add_new' => __('Add New DJ', 'musicandlights'),
                'add_new_item' => __('Add New DJ Profile', 'musicandlights'),
                'edit_item' => __('Edit DJ Profile', 'musicandlights'),
                'new_item' => __('New DJ Profile', 'musicandlights'),
                'view_item' => __('View DJ Profile', 'musicandlights'),
                'search_items' => __('Search DJs', 'musicandlights'),
                'not_found' => __('No DJs found', 'musicandlights'),
                'not_found_in_trash' => __('No DJs found in Trash', 'musicandlights')
            ],
            'public' => true,
            'has_archive' => true,
            'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'],
            'menu_icon' => 'dashicons-admin-users',
            'rewrite' => ['slug' => 'djs'],
            'show_in_rest' => true
        ]);
        
        // Booking Post Type
        register_post_type('dj_booking', [
            'labels' => [
                'name' => __('Bookings', 'musicandlights'),
                'singular_name' => __('Booking', 'musicandlights'),
                'add_new' => __('Add New Booking', 'musicandlights'),
                'add_new_item' => __('Add New Booking', 'musicandlights'),
                'edit_item' => __('Edit Booking', 'musicandlights'),
                'new_item' => __('New Booking', 'musicandlights'),
                'view_item' => __('View Booking', 'musicandlights'),
                'search_items' => __('Search Bookings', 'musicandlights'),
                'not_found' => __('No bookings found', 'musicandlights'),
                'not_found_in_trash' => __('No bookings found in Trash', 'musicandlights')
            ],
            'public' => false,
            'show_ui' => true,
            'supports' => ['title', 'custom-fields'],
            'menu_icon' => 'dashicons-calendar-alt',
            'capability_type' => 'post',
            'capabilities' => [
                'create_posts' => 'manage_options',
            ],
            'map_meta_cap' => true,
            'show_in_rest' => true
        ]);
        
        // Equipment Post Type
        register_post_type('dj_equipment', [
            'labels' => [
                'name' => __('Equipment', 'musicandlights'),
                'singular_name' => __('Equipment Item', 'musicandlights'),
                'add_new' => __('Add Equipment', 'musicandlights'),
                'add_new_item' => __('Add New Equipment', 'musicandlights'),
                'edit_item' => __('Edit Equipment', 'musicandlights'),
                'new_item' => __('New Equipment', 'musicandlights'),
                'view_item' => __('View Equipment', 'musicandlights'),
                'search_items' => __('Search Equipment', 'musicandlights'),
                'not_found' => __('No equipment found', 'musicandlights'),
                'not_found_in_trash' => __('No equipment found in Trash', 'musicandlights')
            ],
            'public' => false,
            'show_ui' => true,
            'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'],
            'menu_icon' => 'dashicons-admin-tools',
            'show_in_rest' => true
        ]);
    }
    
    public function add_admin_menus(): void {
        add_menu_page(
            __('Music & Lights', 'musicandlights'),
            __('Music & Lights', 'musicandlights'),
            'manage_options',
            'musicandlights-system',
            [$this, 'admin_dashboard'],
            'dashicons-businessman',
            30
        );
        
        add_submenu_page(
            'musicandlights-system',
            __('Bookings Overview', 'musicandlights'),
            __('Bookings', 'musicandlights'),
            'manage_options',
            'musicandlights-bookings',
            [$this, 'bookings_page']
        );
        
        add_submenu_page(
            'musicandlights-system',
            __('Commission Tracking', 'musicandlights'),
            __('Commissions', 'musicandlights'),
            'manage_options',
            'musicandlights-commissions',
            [$this, 'commissions_page']
        );
        
        add_submenu_page(
            'musicandlights-system',
            __('Safeguards Monitor', 'musicandlights'),
            __('Safeguards', 'musicandlights'),
            'manage_options',
            'musicandlights-safeguards',
            [$this, 'safeguards_page']
        );
        
        add_submenu_page(
            'musicandlights-system',
            __('GoHighLevel Settings', 'musicandlights'),
            __('GHL Integration', 'musicandlights'),
            'manage_options',
            'musicandlights-ghl-settings',
            [$this, 'ghl_settings_page']
        );
        
        add_submenu_page(
            'musicandlights-system',
            __('Settings', 'musicandlights'),
            __('Settings', 'musicandlights'),
            'manage_options',
            'musicandlights-settings',
            [$this, 'settings_page']
        );
    }
    
    public function enqueue_frontend_assets(): void {
        $js_file = MUSICANDLIGHTS_PLUGIN_PATH . 'assets/js/frontend.js';
        $css_file = MUSICANDLIGHTS_PLUGIN_PATH . 'assets/css/frontend.css';
        
        if (file_exists($js_file)) {
            wp_enqueue_script('musicandlights-frontend', MUSICANDLIGHTS_PLUGIN_URL . 'assets/js/frontend.js', ['jquery'], MUSICANDLIGHTS_VERSION, true);
            
            // Localize script for AJAX
            wp_localize_script('musicandlights-frontend', 'musicandlights', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('musicandlights_nonce'),
                'stripe_public_key' => get_option('musicandlights_stripe_public_key', ''),
            ]);
        }
        
        if (file_exists($css_file)) {
            wp_enqueue_style('musicandlights-frontend', MUSICANDLIGHTS_PLUGIN_URL . 'assets/css/frontend.css', [], MUSICANDLIGHTS_VERSION);
        }
    }
    
    public function enqueue_admin_assets(): void {
        $js_file = MUSICANDLIGHTS_PLUGIN_PATH . 'assets/js/admin.js';
        $css_file = MUSICANDLIGHTS_PLUGIN_PATH . 'assets/css/admin.css';
        
        if (file_exists($js_file)) {
            wp_enqueue_script('musicandlights-admin', MUSICANDLIGHTS_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], MUSICANDLIGHTS_VERSION, true);
            
            wp_localize_script('musicandlights-admin', 'musicandlights_admin', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('musicandlights_nonce'),
            ]);
        }
        
        if (file_exists($css_file)) {
            wp_enqueue_style('musicandlights-admin', MUSICANDLIGHTS_PLUGIN_URL . 'assets/css/admin.css', [], MUSICANDLIGHTS_VERSION);
        }
    }
    
    public function activate(): void {
        $this->create_tables();
        $this->set_default_options();
        $this->create_default_pages();
        flush_rewrite_rules();
    }
    
    private function create_tables(): void {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $tables = [
            'dj_availability' => "CREATE TABLE {$wpdb->prefix}dj_availability (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                dj_id bigint(20) NOT NULL,
                date date NOT NULL,
                status varchar(20) DEFAULT 'available',
                booking_id bigint(20) DEFAULT NULL,
                notes text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY dj_id (dj_id),
                KEY date (date)
            ) $charset_collate;",
            
            'dj_equipment_assignments' => "CREATE TABLE {$wpdb->prefix}dj_equipment_assignments (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                dj_id bigint(20) NOT NULL,
                equipment_id bigint(20) NOT NULL,
                price decimal(10,2) NOT NULL,
                package_name varchar(100) DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY dj_id (dj_id),
                KEY equipment_id (equipment_id)
            ) $charset_collate;",
            
            'dj_commissions' => "CREATE TABLE {$wpdb->prefix}dj_commissions (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                booking_id bigint(20) NOT NULL,
                dj_id bigint(20) NOT NULL,
                total_amount decimal(10,2) NOT NULL,
                agency_commission decimal(10,2) NOT NULL,
                dj_earnings decimal(10,2) NOT NULL,
                status varchar(20) DEFAULT 'pending',
                earned_date datetime DEFAULT NULL,
                paid_date datetime DEFAULT NULL,
                payment_method varchar(50) DEFAULT NULL,
                payment_reference varchar(100) DEFAULT NULL,
                payment_notes text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY booking_id (booking_id),
                KEY dj_id (dj_id),
                KEY status (status)
            ) $charset_collate;",
            
            'dj_safeguards_log' => "CREATE TABLE {$wpdb->prefix}dj_safeguards_log (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                dj_id bigint(20) NOT NULL,
                enquiry_date date DEFAULT NULL,
                booking_id bigint(20) DEFAULT NULL,
                status_change_date datetime NOT NULL,
                old_status varchar(20) NOT NULL,
                new_status varchar(20) NOT NULL,
                alert_level varchar(20) DEFAULT 'low',
                notes text,
                reviewed_by bigint(20) DEFAULT NULL,
                reviewed_at datetime DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY dj_id (dj_id),
                KEY enquiry_date (enquiry_date),
                KEY alert_level (alert_level)
            ) $charset_collate;"
        ];
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        foreach ($tables as $table_sql) {
            dbDelta($table_sql);
        }
    }
    
    private function set_default_options(): void {
        $default_options = [
            'musicandlights_agency_commission' => '25',
            'musicandlights_deposit_percentage' => '50',
            'musicandlights_travel_free_miles' => '100',
            'musicandlights_travel_rate_per_mile' => '1.00',
            'musicandlights_accommodation_fee' => '200',
            'musicandlights_international_day_rate' => '1000',
            'musicandlights_booking_window_lock_hours' => '48',
            'musicandlights_company_name' => 'Music & Lights',
            'musicandlights_company_phone' => '',
            'musicandlights_company_address' => '',
            'musicandlights_email_logo' => '',
        ];
        
        foreach ($default_options as $option_name => $option_value) {
            add_option($option_name, $option_value);
        }
    }
    
    private function create_default_pages(): void {
        $pages = [
            'book-dj' => [
                'title' => 'Book a DJ',
                'content' => '[musicandlights_booking_form]'
            ],
            'our-djs' => [
                'title' => 'Our DJs',
                'content' => '[musicandlights_profiles]'
            ],
            'dj-dashboard' => [
                'title' => 'DJ Dashboard',
                'content' => '[musicandlights_dashboard]'
            ]
        ];
        
        foreach ($pages as $slug => $page_data) {
            if (!get_page_by_path($slug)) {
                wp_insert_post([
                    'post_title' => $page_data['title'],
                    'post_content' => $page_data['content'],
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'post_name' => $slug
                ]);
            }
        }
    }
    
    public function deactivate(): void {
        flush_rewrite_rules();
    }
    
    // Admin page callbacks
    public function admin_dashboard(): void {
        $file_path = MUSICANDLIGHTS_PLUGIN_PATH . 'admin/dashboard.php';
        if (file_exists($file_path)) {
            include $file_path;
        } else {
            echo '<div class="wrap"><h1>Dashboard</h1><p>Dashboard file not found.</p></div>';
        }
    }
    
    public function bookings_page(): void {
        $file_path = MUSICANDLIGHTS_PLUGIN_PATH . 'admin/bookings.php';
        if (file_exists($file_path)) {
            include $file_path;
        } else {
            echo '<div class="wrap"><h1>Bookings</h1><p>Bookings file not found.</p></div>';
        }
    }
    
    public function commissions_page(): void {
        $file_path = MUSICANDLIGHTS_PLUGIN_PATH . 'admin/commissions.php';
        if (file_exists($file_path)) {
            include $file_path;
        } else {
            echo '<div class="wrap"><h1>Commissions</h1><p>Commissions file not found.</p></div>';
        }
    }
    
    public function safeguards_page(): void {
        $file_path = MUSICANDLIGHTS_PLUGIN_PATH . 'admin/safeguards.php';
        if (file_exists($file_path)) {
            include $file_path;
        } else {
            echo '<div class="wrap"><h1>Safeguards</h1><p>Safeguards file not found.</p></div>';
        }
    }
    
    public function ghl_settings_page(): void {
        $file_path = MUSICANDLIGHTS_PLUGIN_PATH . 'admin/ghl-settings.php';
        if (file_exists($file_path)) {
            include $file_path;
        } else {
            echo '<div class="wrap"><h1>GHL Settings</h1><p>GHL Settings file not found.</p></div>';
        }
    }
    
    public function settings_page(): void {
        $file_path = MUSICANDLIGHTS_PLUGIN_PATH . 'admin/settings.php';
        if (file_exists($file_path)) {
            include $file_path;
        } else {
            echo '<div class="wrap"><h1>Settings</h1><p>Settings file not found.</p></div>';
        }
    }
    
    // Shortcode callbacks with error handling
    public function display_dj_profiles($atts): string {
        $atts = shortcode_atts([
            'limit' => 12,
            'location' => '',
            'specialisation' => ''
        ], $atts);
        
        $file_path = MUSICANDLIGHTS_PLUGIN_PATH . 'templates/dj-profiles.php';
        if (file_exists($file_path)) {
            ob_start();
            include $file_path;
            return ob_get_clean();
        } else {
            return '<p>DJ profiles template not found.</p>';
        }
    }
    
    public function display_booking_form($atts): string {
        $atts = shortcode_atts([
            'dj_id' => 0
        ], $atts);
        
        $file_path = MUSICANDLIGHTS_PLUGIN_PATH . 'templates/booking-form.php';
        if (file_exists($file_path)) {
            ob_start();
            include $file_path;
            return ob_get_clean();
        } else {
            return '<p>Booking form template not found.</p>';
        }
    }
    
    public function display_dj_dashboard($atts): string {
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to view your dashboard.', 'musicandlights') . '</p>';
        }
        
        $file_path = MUSICANDLIGHTS_PLUGIN_PATH . 'templates/dj-dashboard.php';
        if (file_exists($file_path)) {
            ob_start();
            include $file_path;
            return ob_get_clean();
        } else {
            return '<p>Dashboard template not found.</p>';
        }
    }
}

// Initialize the plugin
new MusicAndLightsSystem();

// AJAX handlers with proper error handling
add_action('wp_ajax_musicandlights_check_availability', 'musicandlights_check_availability_callback');
add_action('wp_ajax_nopriv_musicandlights_check_availability', 'musicandlights_check_availability_callback');

function musicandlights_check_availability_callback(): void {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'musicandlights_nonce')) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    $dj_id = intval($_POST['dj_id'] ?? 0);
    $date = sanitize_text_field($_POST['date'] ?? '');
    
    if (!$dj_id || !$date) {
        wp_send_json_error('Missing required parameters');
        return;
    }
    
    if (class_exists('DJ_Calendar_Manager')) {
        $calendar_manager = new DJ_Calendar_Manager();
        $available = $calendar_manager->check_availability($dj_id, $date);
        wp_send_json_success(['available' => $available]);
    } else {
        wp_send_json_error('Calendar manager not available');
    }
}

add_action('wp_ajax_musicandlights_create_booking', 'musicandlights_create_booking_callback');
add_action('wp_ajax_nopriv_musicandlights_create_booking', 'musicandlights_create_booking_callback');

function musicandlights_create_booking_callback(): void {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'musicandlights_nonce')) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    if (class_exists('DJ_Booking_System')) {
        $booking_system = new DJ_Booking_System();
        $result = $booking_system->create_booking($_POST);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    } else {
        wp_send_json_error('Booking system not available');
    }
}

// Add custom capabilities on plugin activation
register_activation_hook(__FILE__, 'musicandlights_add_capabilities');

function musicandlights_add_capabilities(): void {
    $role = get_role('administrator');
    if ($role) {
        $role->add_cap('manage_dj_bookings');
        $role->add_cap('view_dj_commissions');
        $role->add_cap('manage_dj_calendar');
    }
    
    // Add DJ role if it doesn't exist
    if (!get_role('dj_artist')) {
        add_role('dj_artist', 'DJ Artist', [
            'read' => true,
            'edit_dj_profile' => true,
            'view_dj_bookings' => true,
            'manage_dj_calendar' => true
        ]);
    }
}

// Webhook endpoint for GoHighLevel with proper error handling
add_action('wp_ajax_nopriv_musicandlights_ghl_webhook', 'musicandlights_handle_ghl_webhook');
add_action('wp_ajax_musicandlights_ghl_webhook', 'musicandlights_handle_ghl_webhook');

function musicandlights_handle_ghl_webhook(): void {
    $webhook_data = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_die('Invalid JSON', 'Bad Request', ['response' => 400]);
    }
    
    $webhook_secret = get_option('musicandlights_ghl_webhook_secret', '');
    if (!empty($webhook_secret)) {
        $signature = $_SERVER['HTTP_X_GHL_SIGNATURE'] ?? '';
        $expected_signature = hash_hmac('sha256', file_get_contents('php://input'), $webhook_secret);
        
        if (!hash_equals($expected_signature, $signature)) {
            wp_die('Invalid signature', 'Forbidden', ['response' => 403]);
        }
    }
    
    if (class_exists('GHL_Integration')) {
        $ghl_integration = new GHL_Integration();
        $ghl_integration->handle_webhook($webhook_data);
    }
    
    wp_die('OK', 'Success', ['response' => 200]);
}

// Custom login redirect for DJs
add_filter('login_redirect', 'musicandlights_login_redirect', 10, 3);

function musicandlights_login_redirect($redirect_to, $request, $user) {
    if (isset($user->roles) && is_array($user->roles)) {
        if (in_array('dj_artist', $user->roles)) {
            return home_url('/dj-dashboard/');
        }
    }
    return $redirect_to;
}

// Add custom body classes for styling
add_filter('body_class', 'musicandlights_body_classes');

function musicandlights_body_classes($classes) {
    if (is_page('book-dj') || is_page('our-djs') || is_page('dj-dashboard')) {
        $classes[] = 'musicandlights-page';
    }
    return $classes;
}

// Add error handling for missing asset files
add_action('wp_footer', 'musicandlights_check_assets');

function musicandlights_check_assets() {
    if (current_user_can('manage_options') && WP_DEBUG) {
        $missing_files = [];
        
        $asset_files = [
            'assets/js/frontend.js',
            'assets/css/frontend.css',
            'assets/js/admin.js',
            'assets/css/admin.css'
        ];
        
        foreach ($asset_files as $file) {
            if (!file_exists(MUSICANDLIGHTS_PLUGIN_PATH . $file)) {
                $missing_files[] = $file;
            }
        }
        
        if (!empty($missing_files)) {
            echo '<!-- Music & Lights Plugin: Missing asset files: ' . implode(', ', $missing_files) . ' -->';
        }
    }
}
?>