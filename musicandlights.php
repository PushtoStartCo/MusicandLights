<?php
/**
 * Plugin Name: Music & Lights DJ Hire Management System
 * Plugin URI: https://github.com/PushtoStartCo/MusicandLights
 * Description: Complete DJ hire booking system with GoHighLevel integration, commission tracking, and safeguards monitoring.
 * Version: 1.0.0
 * Author: PushtoStart Co
 * License: GPL v2 or later
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MUSICANDLIGHTS_VERSION', '1.0.0');
define('MUSICANDLIGHTS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MUSICANDLIGHTS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MUSICANDLIGHTS_PLUGIN_FILE', __FILE__);

/**
 * Main plugin class
 */
class MusicAndLights {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
        $this->include_files();
        $this->init_classes();
    }
    
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    private function include_files() {
        // Core classes
        require_once MUSICANDLIGHTS_PLUGIN_DIR . 'includes/class-ml-database.php';
        require_once MUSICANDLIGHTS_PLUGIN_DIR . 'includes/class-ml-booking.php';
        require_once MUSICANDLIGHTS_PLUGIN_DIR . 'includes/class-ml-dj.php';
        require_once MUSICANDLIGHTS_PLUGIN_DIR . 'includes/class-ml-commission.php';
        require_once MUSICANDLIGHTS_PLUGIN_DIR . 'includes/class-ml-payments.php';
        require_once MUSICANDLIGHTS_PLUGIN_DIR . 'includes/class-ml-email.php';
        require_once MUSICANDLIGHTS_PLUGIN_DIR . 'includes/class-ml-gohighlevel.php';
        require_once MUSICANDLIGHTS_PLUGIN_DIR . 'includes/class-ml-safeguards.php';
        require_once MUSICANDLIGHTS_PLUGIN_DIR . 'includes/class-ml-travel.php';
        require_once MUSICANDLIGHTS_PLUGIN_DIR . 'includes/class-ml-calendar.php';
        require_once MUSICANDLIGHTS_PLUGIN_DIR . 'includes/class-ml-equipment.php';
        require_once MUSICANDLIGHTS_PLUGIN_DIR . 'includes/class-ml-postcode.php';
        
        // Admin classes
        require_once MUSICANDLIGHTS_PLUGIN_DIR . 'admin/class-ml-admin.php';
        require_once MUSICANDLIGHTS_PLUGIN_DIR . 'admin/class-ml-settings.php';
        require_once MUSICANDLIGHTS_PLUGIN_DIR . 'admin/class-ml-dj-admin.php';
        require_once MUSICANDLIGHTS_PLUGIN_DIR . 'admin/class-ml-booking-admin.php';
        
        // Frontend classes
        require_once MUSICANDLIGHTS_PLUGIN_DIR . 'public/class-ml-public.php';
        require_once MUSICANDLIGHTS_PLUGIN_DIR . 'public/class-ml-booking-form.php';
        require_once MUSICANDLIGHTS_PLUGIN_DIR . 'public/class-ml-dj-profiles.php';
    }
    
    private function init_classes() {
        // Initialize core classes
        ML_Database::get_instance();
        ML_Booking::get_instance();
        ML_DJ::get_instance();
        ML_Commission::get_instance();
        ML_Payments::get_instance();
        ML_Email::get_instance();
        ML_GoHighLevel::get_instance();
        ML_Safeguards::get_instance();
        ML_Travel::get_instance();
        ML_Calendar::get_instance();
        ML_Equipment::get_instance();
        ML_Postcode::get_instance();
        
        // Initialize admin classes
        if (is_admin()) {
            ML_Admin::get_instance();
            ML_Settings::get_instance();
            ML_DJ_Admin::get_instance();
            ML_Booking_Admin::get_instance();
        }
        
        // Initialize public classes
        if (!is_admin()) {
            ML_Public::get_instance();
            ML_Booking_Form::get_instance();
            ML_DJ_Profiles::get_instance();
        }
    }
    
    public function init() {
        // Load textdomain for translations
        load_plugin_textdomain('musicandlights', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Add rewrite rules
        $this->add_rewrite_rules();
        
        // Flush rewrite rules if needed
        if (get_option('musicandlights_flush_rewrite_rules')) {
            flush_rewrite_rules();
            delete_option('musicandlights_flush_rewrite_rules');
        }
    }
    
    private function add_rewrite_rules() {
        // Add rewrite rules for booking system
        add_rewrite_rule('^book-dj/?$', 'index.php?ml_page=book-dj', 'top');
        add_rewrite_rule('^our-djs/?$', 'index.php?ml_page=our-djs', 'top');
        add_rewrite_rule('^dj-dashboard/?$', 'index.php?ml_page=dj-dashboard', 'top');
        add_rewrite_rule('^dj/([^/]+)/?$', 'index.php?ml_page=dj-profile&dj_slug=$matches[1]', 'top');
        
        // Add query vars
        add_filter('query_vars', function($vars) {
            $vars[] = 'ml_page';
            $vars[] = 'dj_slug';
            return $vars;
        });
        
        // Handle template redirects
        add_action('template_redirect', array($this, 'handle_template_redirect'));
    }
    
    public function handle_template_redirect() {
        $ml_page = get_query_var('ml_page');
        
        switch ($ml_page) {
            case 'book-dj':
                $this->load_template('booking-form');
                break;
            case 'our-djs':
                $this->load_template('dj-listing');
                break;
            case 'dj-dashboard':
                $this->load_template('dj-dashboard');
                break;
            case 'dj-profile':
                $this->load_template('dj-profile');
                break;
        }
    }
    
    private function load_template($template_name) {
        $template_path = MUSICANDLIGHTS_PLUGIN_DIR . "templates/{$template_name}.php";
        
        if (file_exists($template_path)) {
            include $template_path;
            exit;
        }
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Music & Lights',
            'Music & Lights',
            'manage_options',
            'musicandlights',
            array($this, 'admin_dashboard'),
            'dashicons-microphone',
            30
        );
        
        add_submenu_page(
            'musicandlights',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'musicandlights',
            array($this, 'admin_dashboard')
        );
        
        add_submenu_page(
            'musicandlights',
            'Bookings',
            'Bookings',
            'manage_options',
            'ml-bookings',
            array($this, 'admin_bookings')
        );
        
        add_submenu_page(
            'musicandlights',
            'DJ Profiles',
            'DJ Profiles',
            'manage_options',
            'ml-djs',
            array($this, 'admin_djs')
        );
        
        add_submenu_page(
            'musicandlights',
            'Commission Tracking',
            'Commissions',
            'manage_options',
            'ml-commissions',
            array($this, 'admin_commissions')
        );
        
        add_submenu_page(
            'musicandlights',
            'Safeguards',
            'Safeguards',
            'manage_options',
            'ml-safeguards',
            array($this, 'admin_safeguards')
        );
        
        add_submenu_page(
            'musicandlights',
            'Settings',
            'Settings',
            'manage_options',
            'ml-settings',
            array($this, 'admin_settings')
        );
    }
    
    public function admin_dashboard() {
        include MUSICANDLIGHTS_PLUGIN_DIR . 'admin/views/dashboard.php';
    }
    
    public function admin_bookings() {
        include MUSICANDLIGHTS_PLUGIN_DIR . 'admin/views/bookings.php';
    }
    
    public function admin_djs() {
        include MUSICANDLIGHTS_PLUGIN_DIR . 'admin/views/djs.php';
    }
    
    public function admin_commissions() {
        include MUSICANDLIGHTS_PLUGIN_DIR . 'admin/views/commissions.php';
    }
    
    public function admin_safeguards() {
        include MUSICANDLIGHTS_PLUGIN_DIR . 'admin/views/safeguards.php';
    }
    
    public function admin_settings() {
        include MUSICANDLIGHTS_PLUGIN_DIR . 'admin/views/settings.php';
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('ml-public-js', MUSICANDLIGHTS_PLUGIN_URL . 'assets/js/public.js', array('jquery'), MUSICANDLIGHTS_VERSION, true);
        wp_enqueue_style('ml-public-css', MUSICANDLIGHTS_PLUGIN_URL . 'assets/css/public.css', array(), MUSICANDLIGHTS_VERSION);
        
        // Localize script for AJAX
        wp_localize_script('ml-public-js', 'ml_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ml_nonce'),
        ));
    }
    
    public function enqueue_admin_scripts() {
        wp_enqueue_script('ml-admin-js', MUSICANDLIGHTS_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), MUSICANDLIGHTS_VERSION, true);
        wp_enqueue_style('ml-admin-css', MUSICANDLIGHTS_PLUGIN_URL . 'assets/css/admin.css', array(), MUSICANDLIGHTS_VERSION);
        
        // Localize script for AJAX
        wp_localize_script('ml-admin-js', 'ml_admin_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ml_admin_nonce'),
        ));
    }
    
    public function activate() {
        // Create database tables
        ML_Database::create_tables();
        
        // Add default options
        $this->add_default_options();
        
        // Create default pages
        $this->create_default_pages();
        
        // Set flag to flush rewrite rules
        add_option('musicandlights_flush_rewrite_rules', true);
        
        // Schedule cron jobs
        $this->schedule_cron_jobs();
    }
    
    public function deactivate() {
        // Clear scheduled cron jobs
        wp_clear_scheduled_hook('ml_daily_safeguards_check');
        wp_clear_scheduled_hook('ml_weekly_commission_report');
        wp_clear_scheduled_hook('ml_monthly_stats_report');
    }
    
    private function add_default_options() {
        $default_options = array(
            'ml_company_name' => 'Music & Lights',
            'ml_company_email' => '',
            'ml_company_phone' => '',
            'ml_company_address' => '',
            'ml_commission_rate' => 25,
            'ml_default_travel_rate' => 0.45, // Per mile
            'ml_stripe_public_key' => '',
            'ml_stripe_secret_key' => '',
            'ml_ghl_api_key' => '',
            'ml_ghl_location_id' => '',
            'ml_safeguards_enabled' => true,
            'ml_email_notifications' => true,
            'ml_default_deposit_percentage' => 25,
        );
        
        foreach ($default_options as $key => $value) {
            add_option($key, $value);
        }
    }
    
    private function create_default_pages() {
        $pages = array(
            'book-dj' => array(
                'title' => 'Book a DJ',
                'content' => '[ml_booking_form]',
                'slug' => 'book-dj'
            ),
            'our-djs' => array(
                'title' => 'Our DJs',
                'content' => '[ml_dj_listing]',
                'slug' => 'our-djs'
            ),
            'dj-dashboard' => array(
                'title' => 'DJ Dashboard',
                'content' => '[ml_dj_dashboard]',
                'slug' => 'dj-dashboard'
            )
        );
        
        foreach ($pages as $page_data) {
            $existing_page = get_page_by_path($page_data['slug']);
            
            if (!$existing_page) {
                wp_insert_post(array(
                    'post_title' => $page_data['title'],
                    'post_content' => $page_data['content'],
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'post_name' => $page_data['slug']
                ));
            }
        }
    }
    
    private function schedule_cron_jobs() {
        // Daily safeguards check
        if (!wp_next_scheduled('ml_daily_safeguards_check')) {
            wp_schedule_event(time(), 'daily', 'ml_daily_safeguards_check');
        }
        
        // Weekly commission report
        if (!wp_next_scheduled('ml_weekly_commission_report')) {
            wp_schedule_event(time(), 'weekly', 'ml_weekly_commission_report');
        }
        
        // Monthly stats report
        if (!wp_next_scheduled('ml_monthly_stats_report')) {
            wp_schedule_event(time(), 'monthly', 'ml_monthly_stats_report');
        }
    }
}

// Initialize the plugin
function musicandlights_init() {
    return MusicAndLights::get_instance();
}

// Hook into plugins_loaded to ensure WordPress is fully loaded
add_action('plugins_loaded', 'musicandlights_init');

// Add shortcodes
add_action('init', function() {
    add_shortcode('ml_booking_form', array('ML_Booking_Form', 'render_shortcode'));
    add_shortcode('ml_dj_listing', array('ML_DJ_Profiles', 'render_listing_shortcode'));
    add_shortcode('ml_dj_dashboard', array('ML_DJ_Profiles', 'render_dashboard_shortcode'));
});

// AJAX handlers
add_action('wp_ajax_ml_submit_booking', array('ML_Booking_Form', 'handle_ajax_submission'));
add_action('wp_ajax_nopriv_ml_submit_booking', array('ML_Booking_Form', 'handle_ajax_submission'));

add_action('wp_ajax_ml_calculate_travel_cost', array('ML_Travel', 'ajax_calculate_cost'));
add_action('wp_ajax_nopriv_ml_calculate_travel_cost', array('ML_Travel', 'ajax_calculate_cost'));

add_action('wp_ajax_ml_check_dj_availability', array('ML_Calendar', 'ajax_check_availability'));
add_action('wp_ajax_nopriv_ml_check_dj_availability', array('ML_Calendar', 'ajax_check_availability'));

// Cron job handlers
add_action('ml_daily_safeguards_check', array('ML_Safeguards', 'daily_check'));
add_action('ml_weekly_commission_report', array('ML_Commission', 'weekly_report'));
add_action('ml_monthly_stats_report', array('ML_Admin', 'monthly_stats_report'));

?>