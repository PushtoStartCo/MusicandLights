<?php
/**
 * Music & Lights Settings Class - Settings Management
 * 
 * Handles plugin settings configuration, validation,
 * and storage for the Music & Lights system.
 * 
 * @package MusicAndLights
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class ML_Settings {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Settings option name
     */
    private $option_name = 'ml_settings';
    
    /**
     * Default settings
     */
    private $default_settings = array();
    
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
        $this->init_default_settings();
        $this->init_hooks();
    }
    
    /**
     * Initialize default settings
     */
    private function init_default_settings() {
        $this->default_settings = array(
            // Company Information
            'company_name' => 'Music & Lights',
            'company_address' => '',
            'company_phone' => '',
            'company_email' => get_option('admin_email'),
            'company_website' => home_url(),
            'company_logo' => '',
            
            // Email Settings
            'email_from' => get_option('admin_email'),
            'email_from_name' => get_option('blogname'),
            'email_footer_text' => '',
            
            // Booking Settings
            'default_deposit_percentage' => 25,
            'default_commission_rate' => 25,
            'booking_advance_days' => 14,
            'cancellation_policy' => '',
            'terms_conditions' => '',
            
            // Payment Settings - Stripe
            'stripe_enabled' => false,
            'stripe_test_mode' => true,
            'stripe_test_publishable_key' => '',
            'stripe_test_secret_key' => '',
            'stripe_live_publishable_key' => '',
            'stripe_live_secret_key' => '',
            
            // GoHighLevel Integration
            'ghl_enabled' => false,
            'ghl_api_key' => '',
            'ghl_location_id' => '',
            'ghl_assigned_user' => '',
            'ghl_webhook_url' => '',
            
            // Travel Settings
            'default_travel_rate' => 0.45,
            'free_travel_radius' => 10,
            'max_travel_distance' => 200,
            'google_maps_api_key' => '',
            
            // Safeguards Settings
            'safeguards_enabled' => true,
            'safeguards_threshold' => 3,
            'safeguards_email_alerts' => true,
            'admin_email' => get_option('admin_email'),
            
            // Equipment Settings
            'equipment_tracking_enabled' => true,
            'equipment_auto_assign' => false,
            
            // Advanced Settings
            'debug_mode' => false,
            'cache_duration' => 3600,
            'api_rate_limit' => 100
        );
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_ml_save_settings', array($this, 'save_settings'));
        add_action('wp_ajax_ml_reset_settings', array($this, 'reset_settings'));
        add_action('wp_ajax_ml_test_email_settings', array($this, 'test_email_settings'));
        add_action('wp_ajax_ml_test_stripe_connection', array($this, 'test_stripe_connection'));
        add_action('wp_ajax_ml_upload_logo', array($this, 'upload_logo'));
        add_action('admin_init', array($this, 'init_settings'));
    }
    
    /**
     * Initialize settings
     */
    public function init_settings() {
        // Register settings
        register_setting($this->option_name, $this->option_name, array($this, 'validate_settings'));
        
        // Create settings sections
        $this->create_settings_sections();
    }
    
    /**
     * Create settings sections
     */
    private function create_settings_sections() {
        // Company Information Section
        add_settings_section(
            'ml_company_section',
            'Company Information',
            array($this, 'company_section_callback'),
            $this->option_name
        );
        
        // Email Settings Section
        add_settings_section(
            'ml_email_section',
            'Email Settings',
            array($this, 'email_section_callback'),
            $this->option_name
        );
        
        // Booking Settings Section
        add_settings_section(
            'ml_booking_section',
            'Booking Settings',
            array($this, 'booking_section_callback'),
            $this->option_name
        );
        
        // Payment Settings Section
        add_settings_section(
            'ml_payment_section',
            'Payment Settings',
            array($this, 'payment_section_callback'),
            $this->option_name
        );
        
        // Integration Settings Section
        add_settings_section(
            'ml_integration_section',
            'Integration Settings',
            array($this, 'integration_section_callback'),
            $this->option_name
        );
    }
    
    /**
     * Get all settings
     */
    public function get_settings() {
        $saved_settings = get_option($this->option_name, array());
        return wp_parse_args($saved_settings, $this->default_settings);
    }
    
    /**
     * Get specific setting
     */
    public function get_setting($key, $default = null) {
        $settings = $this->get_settings();
        return isset($settings[$key]) ? $settings[$key] : $default;
    }
    
    /**
     * Save settings
     */
    public function save_settings() {
        if (!wp_verify_nonce($_POST['nonce'], 'ml_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $settings_data = $_POST['settings'];
        $validated_settings = $this->validate_settings($settings_data);
        
        if (is_wp_error($validated_settings)) {
            wp_send_json_error($validated_settings->get_error_message());
            return;
        }
        
        $result = update_option($this->option_name, $validated_settings);
        
        if ($result) {
            wp_send_json_success('Settings saved successfully');
        } else {
            wp_send_json_error('Failed to save settings');
        }
    }
    
    /**
     * Validate settings
     */
    public function validate_settings($settings) {
        $validated = array();
        $errors = array();
        
        // Validate company information
        $validated['company_name'] = sanitize_text_field($settings['company_name'] ?? '');
        $validated['company_address'] = sanitize_textarea_field($settings['company_address'] ?? '');
        $validated['company_phone'] = sanitize_text_field($settings['company_phone'] ?? '');
        
        // Validate email
        if (!empty($settings['company_email'])) {
            if (is_email($settings['company_email'])) {
                $validated['company_email'] = sanitize_email($settings['company_email']);
            } else {
                $errors[] = 'Invalid company email address';
            }
        }
        
        // Validate email settings
        if (!empty($settings['email_from'])) {
            if (is_email($settings['email_from'])) {
                $validated['email_from'] = sanitize_email($settings['email_from']);
            } else {
                $errors[] = 'Invalid email from address';
            }
        }
        
        $validated['email_from_name'] = sanitize_text_field($settings['email_from_name'] ?? '');
        $validated['email_footer_text'] = sanitize_textarea_field($settings['email_footer_text'] ?? '');
        
        // Validate numeric settings
        $numeric_fields = array(
            'default_deposit_percentage',
            'default_commission_rate',
            'booking_advance_days',
            'default_travel_rate',
            'free_travel_radius',
            'max_travel_distance',
            'safeguards_threshold',
            'cache_duration',
            'api_rate_limit'
        );
        
        foreach ($numeric_fields as $field) {
            if (isset($settings[$field])) {
                $value = floatval($settings[$field]);
                if ($value >= 0) {
                    $validated[$field] = $value;
                } else {
                    $errors[] = "Invalid value for {$field}";
                }
            }
        }
        
        // Validate percentage fields
        $percentage_fields = array('default_deposit_percentage', 'default_commission_rate');
        foreach ($percentage_fields as $field) {
            if (isset($validated[$field]) && ($validated[$field] < 0 || $validated[$field] > 100)) {
                $errors[] = "{$field} must be between 0 and 100";
            }
        }
        
        // Validate boolean settings
        $boolean_fields = array(
            'stripe_enabled',
            'stripe_test_mode',
            'ghl_enabled',
            'safeguards_enabled',
            'safeguards_email_alerts',
            'equipment_tracking_enabled',
            'equipment_auto_assign',
            'debug_mode'
        );
        
        foreach ($boolean_fields as $field) {
            $validated[$field] = isset($settings[$field]) ? (bool) $settings[$field] : false;
        }
        
        // Validate API keys and sensitive data
        $secure_fields = array(
            'stripe_test_publishable_key',
            'stripe_test_secret_key',
            'stripe_live_publishable_key',
            'stripe_live_secret_key',
            'ghl_api_key',
            'google_maps_api_key'
        );
        
        foreach ($secure_fields as $field) {
            if (isset($settings[$field])) {
                $validated[$field] = sanitize_text_field($settings[$field]);
            }
        }
        
        // Validate text fields
        $text_fields = array(
            'company_website',
            'company_logo',
            'ghl_location_id',
            'ghl_assigned_user',
            'ghl_webhook_url',
            'admin_email',
            'cancellation_policy',
            'terms_conditions'
        );
        
        foreach ($text_fields as $field) {
            if (isset($settings[$field])) {
                if ($field === 'admin_email' && !empty($settings[$field])) {
                    if (is_email($settings[$field])) {
                        $validated[$field] = sanitize_email($settings[$field]);
                    } else {
                        $errors[] = 'Invalid admin email address';
                    }
                } elseif (in_array($field, array('company_website', 'ghl_webhook_url')) && !empty($settings[$field])) {
                    $validated[$field] = esc_url_raw($settings[$field]);
                } else {
                    $validated[$field] = sanitize_text_field($settings[$field]);
                }
            }
        }
        
        // Return errors if any
        if (!empty($errors)) {
            return new WP_Error('validation_error', implode('; ', $errors));
        }
        
        // Merge with existing settings to preserve any not in form
        $existing_settings = $this->get_settings();
        return array_merge($existing_settings, $validated);
    }
    
    /**
     * Reset settings to defaults
     */
    public function reset_settings() {
        if (!wp_verify_nonce($_POST['nonce'], 'ml_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $result = update_option($this->option_name, $this->default_settings);
        
        if ($result) {
            wp_send_json_success('Settings reset to defaults');
        } else {
            wp_send_json_error('Failed to reset settings');
        }
    }
    
    /**
     * Test email settings
     */
    public function test_email_settings() {
        if (!wp_verify_nonce($_POST['nonce'], 'ml_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        $test_email = sanitize_email($_POST['test_email']);
        
        if (!is_email($test_email)) {
            wp_send_json_error('Invalid email address');
            return;
        }
        
        $subject = 'Music & Lights Email Test';
        $message = '<h2>Email Test Successful</h2>';
        $message .= '<p>This is a test email from the Music & Lights plugin.</p>';
        $message .= '<p>If you received this email, your email settings are configured correctly.</p>';
        $message .= '<p>Sent at: ' . current_time('mysql') . '</p>';
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        $sent = wp_mail($test_email, $subject, $message, $headers);
        
        if ($sent) {
            wp_send_json_success('Test email sent successfully to ' . $test_email);
        } else {
            wp_send_json_error('Failed to send test email');
        }
    }
    
    /**
     * Test Stripe connection
     */
    public function test_stripe_connection() {
        if (!wp_verify_nonce($_POST['nonce'], 'ml_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        $settings = $this->get_settings();
        $test_mode = $settings['stripe_test_mode'];
        $api_key = $test_mode ? $settings['stripe_test_secret_key'] : $settings['stripe_live_secret_key'];
        
        if (empty($api_key)) {
            wp_send_json_error('Stripe API key not configured');
            return;
        }
        
        // Test Stripe connection
        try {
            if (!class_exists('\Stripe\Stripe')) {
                require_once(plugin_dir_path(__FILE__) . '../vendor/stripe/stripe-php/init.php');
            }
            
            \Stripe\Stripe::setApiKey($api_key);
            $account = \Stripe\Account::retrieve();
            
            wp_send_json_success(array(
                'message' => 'Stripe connection successful',
                'account_id' => $account->id,
                'business_profile' => $account->business_profile->name ?? 'Not set',
                'mode' => $test_mode ? 'Test Mode' : 'Live Mode'
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Stripe connection failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Upload company logo
     */
    public function upload_logo() {
        if (!wp_verify_nonce($_POST['nonce'], 'ml_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        if (empty($_FILES['logo'])) {
            wp_send_json_error('No file uploaded');
            return;
        }
        
        $file = $_FILES['logo'];
        
        // Check file type
        $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/gif');
        if (!in_array($file['type'], $allowed_types)) {
            wp_send_json_error('Invalid file type. Please upload a JPG, PNG, or GIF image.');
            return;
        }
        
        // Check file size (max 2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            wp_send_json_error('File too large. Maximum size is 2MB.');
            return;
        }
        
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        
        $upload = wp_handle_upload($file, array('test_form' => false));
        
        if (isset($upload['error'])) {
            wp_send_json_error($upload['error']);
            return;
        }
        
        // Update settings with new logo URL
        $settings = $this->get_settings();
        $settings['company_logo'] = $upload['url'];
        update_option($this->option_name, $settings);
        
        wp_send_json_success(array(
            'message' => 'Logo uploaded successfully',
            'url' => $upload['url']
        ));
    }
    
    /**
     * Section callbacks
     */
    public function company_section_callback() {
        echo '<p>Configure your company information for emails and branding.</p>';
    }
    
    public function email_section_callback() {
        echo '<p>Configure email settings for automated notifications.</p>';
    }
    
    public function booking_section_callback() {
        echo '<p>Configure default booking and commission settings.</p>';
    }
    
    public function payment_section_callback() {
        echo '<p>Configure payment processing settings.</p>';
    }
    
    public function integration_section_callback() {
        echo '<p>Configure third-party integrations.</p>';
    }
    
    /**
     * Get settings tabs
     */
    public function get_settings_tabs() {
        return array(
            'general' => 'General',
            'company' => 'Company',
            'booking' => 'Booking',
            'payment' => 'Payment',
            'email' => 'Email',
            'integration' => 'Integrations',
            'safeguards' => 'Safeguards',
            'advanced' => 'Advanced'
        );
    }
    
    /**
     * Get current tab
     */
    public function get_current_tab() {
        return isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
    }
    
    /**
     * Export settings
     */
    public function export_settings() {
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        $settings = $this->get_settings();
        
        // Remove sensitive data from export
        $export_settings = $settings;
        unset($export_settings['stripe_test_secret_key']);
        unset($export_settings['stripe_live_secret_key']);
        unset($export_settings['ghl_api_key']);
        unset($export_settings['google_maps_api_key']);
        
        return json_encode($export_settings, JSON_PRETTY_PRINT);
    }
    
    /**
     * Import settings
     */
    public function import_settings($json_data) {
        if (!current_user_can('manage_options')) {
            return new WP_Error('permission_denied', 'Insufficient permissions');
        }
        
        $settings_data = json_decode($json_data, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_json', 'Invalid JSON data');
        }
        
        $validated_settings = $this->validate_settings($settings_data);
        
        if (is_wp_error($validated_settings)) {
            return $validated_settings;
        }
        
        $result = update_option($this->option_name, $validated_settings);
        
        if ($result) {
            return true;
        } else {
            return new WP_Error('save_failed', 'Failed to save imported settings');
        }
    }
}

// Initialize the settings class
ML_Settings::get_instance();