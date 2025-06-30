<?php
/**
 * Admin Settings View Template
 * 
 * Settings configuration interface for Music & Lights admin
 * 
 * @package MusicAndLights
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get settings instance
$settings = ML_Settings::get_instance();
$current_settings = $settings->get_settings();
$current_tab = $settings->get_current_tab();
$tabs = $settings->get_settings_tabs();
?>

<div class="wrap ml-admin-wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-admin-settings"></span>
        Music & Lights Settings
    </h1>
    
    <hr class="wp-header-end">
    
    <!-- Settings Tabs -->
    <nav class="nav-tab-wrapper wp-clearfix">
        <?php foreach ($tabs as $tab_key => $tab_label): ?>
            <a href="<?php echo admin_url('admin.php?page=ml-settings&tab=' . $tab_key); ?>" 
               class="nav-tab <?php echo ($current_tab === $tab_key) ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html($tab_label); ?>
            </a>
        <?php endforeach; ?>
    </nav>
    
    <!-- Settings Form -->
    <form id="ml-settings-form" method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('ml_settings', 'ml_settings_nonce'); ?>
        
        <div class="ml-settings-content">
            
            <?php if ($current_tab === 'general'): ?>
                
                <!-- General Settings -->
                <div class="ml-settings-section">
                    <h2>General Settings</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="company_name">Company Name</label>
                            </th>
                            <td>
                                <textarea id="terms_conditions" name="settings[terms_conditions]" 
                                          rows="8" class="large-text"><?php echo esc_textarea($current_settings['terms_conditions']); ?></textarea>
                                <p class="description">General terms and conditions for bookings.</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
            <?php elseif ($current_tab === 'payment'): ?>
                
                <!-- Payment Settings -->
                <div class="ml-settings-section">
                    <h2>Stripe Payment Settings</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="stripe_enabled">Enable Stripe Payments</label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="stripe_enabled" name="settings[stripe_enabled]" 
                                           value="1" <?php checked($current_settings['stripe_enabled']); ?>>
                                    Enable Stripe payment processing
                                </label>
                                <p class="description">Allow customers to pay deposits and final payments online.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="stripe_test_mode">Test Mode</label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="stripe_test_mode" name="settings[stripe_test_mode]" 
                                           value="1" <?php checked($current_settings['stripe_test_mode']); ?>>
                                    Use test mode (no real payments)
                                </label>
                                <p class="description">Use Stripe's test environment for development.</p>
                            </td>
                        </tr>
                    </table>
                    
                    <h3>Test Keys</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="stripe_test_publishable_key">Test Publishable Key</label>
                            </th>
                            <td>
                                <input type="text" id="stripe_test_publishable_key" 
                                       name="settings[stripe_test_publishable_key]" 
                                       value="<?php echo esc_attr($current_settings['stripe_test_publishable_key']); ?>" 
                                       class="regular-text" placeholder="pk_test_...">
                                <p class="description">Your Stripe test publishable key (starts with pk_test_).</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="stripe_test_secret_key">Test Secret Key</label>
                            </th>
                            <td>
                                <input type="password" id="stripe_test_secret_key" 
                                       name="settings[stripe_test_secret_key]" 
                                       value="<?php echo esc_attr($current_settings['stripe_test_secret_key']); ?>" 
                                       class="regular-text" placeholder="sk_test_...">
                                <p class="description">Your Stripe test secret key (starts with sk_test_).</p>
                            </td>
                        </tr>
                    </table>
                    
                    <h3>Live Keys</h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="stripe_live_publishable_key">Live Publishable Key</label>
                            </th>
                            <td>
                                <input type="text" id="stripe_live_publishable_key" 
                                       name="settings[stripe_live_publishable_key]" 
                                       value="<?php echo esc_attr($current_settings['stripe_live_publishable_key']); ?>" 
                                       class="regular-text" placeholder="pk_live_...">
                                <p class="description">Your Stripe live publishable key (starts with pk_live_).</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="stripe_live_secret_key">Live Secret Key</label>
                            </th>
                            <td>
                                <input type="password" id="stripe_live_secret_key" 
                                       name="settings[stripe_live_secret_key]" 
                                       value="<?php echo esc_attr($current_settings['stripe_live_secret_key']); ?>" 
                                       class="regular-text" placeholder="sk_live_...">
                                <p class="description">Your Stripe live secret key (starts with sk_live_).</p>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="ml-test-connection">
                        <button type="button" class="button button-secondary" onclick="testStripeConnection()">
                            Test Stripe Connection
                        </button>
                        <span id="stripe-test-result"></span>
                    </div>
                </div>
                
            <?php elseif ($current_tab === 'email'): ?>
                
                <!-- Email Settings -->
                <div class="ml-settings-section">
                    <h2>Email Settings</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="email_from">From Email Address</label>
                            </th>
                            <td>
                                <input type="email" id="email_from" name="settings[email_from]" 
                                       value="<?php echo esc_attr($current_settings['email_from']); ?>" 
                                       class="regular-text" required>
                                <p class="description">Email address used for outgoing emails.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="email_from_name">From Name</label>
                            </th>
                            <td>
                                <input type="text" id="email_from_name" name="settings[email_from_name]" 
                                       value="<?php echo esc_attr($current_settings['email_from_name']); ?>" 
                                       class="regular-text" required>
                                <p class="description">Name shown as sender of emails.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="email_footer_text">Email Footer Text</label>
                            </th>
                            <td>
                                <textarea id="email_footer_text" name="settings[email_footer_text]" 
                                          rows="3" class="large-text"><?php echo esc_textarea($current_settings['email_footer_text']); ?></textarea>
                                <p class="description">Additional text to include in email footers.</p>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="ml-test-email">
                        <h3>Test Email Settings</h3>
                        <p>Send a test email to verify your email configuration is working correctly.</p>
                        
                        <div class="ml-test-email-form">
                            <input type="email" id="test_email_address" placeholder="Enter test email address" class="regular-text">
                            <button type="button" class="button button-secondary" onclick="sendTestEmail()">
                                Send Test Email
                            </button>
                            <span id="email-test-result"></span>
                        </div>
                    </div>
                </div>
                
            <?php elseif ($current_tab === 'integration'): ?>
                
                <!-- Integration Settings -->
                <div class="ml-settings-section">
                    <h2>GoHighLevel Integration</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="ghl_enabled">Enable GoHighLevel</label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="ghl_enabled" name="settings[ghl_enabled]" 
                                           value="1" <?php checked($current_settings['ghl_enabled']); ?>>
                                    Enable GoHighLevel CRM integration
                                </label>
                                <p class="description">Automatically sync bookings and contacts with GoHighLevel.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="ghl_api_key">API Key</label>
                            </th>
                            <td>
                                <input type="password" id="ghl_api_key" name="settings[ghl_api_key]" 
                                       value="<?php echo esc_attr($current_settings['ghl_api_key']); ?>" 
                                       class="regular-text">
                                <p class="description">Your GoHighLevel API key.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="ghl_location_id">Location ID</label>
                            </th>
                            <td>
                                <input type="text" id="ghl_location_id" name="settings[ghl_location_id]" 
                                       value="<?php echo esc_attr($current_settings['ghl_location_id']); ?>" 
                                       class="regular-text">
                                <p class="description">Your GoHighLevel location ID.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="ghl_assigned_user">Default Assigned User</label>
                            </th>
                            <td>
                                <input type="text" id="ghl_assigned_user" name="settings[ghl_assigned_user]" 
                                       value="<?php echo esc_attr($current_settings['ghl_assigned_user']); ?>" 
                                       class="regular-text">
                                <p class="description">Default user ID for assigning new opportunities.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="ghl_webhook_url">Webhook URL</label>
                            </th>
                            <td>
                                <input type="url" id="ghl_webhook_url" name="settings[ghl_webhook_url]" 
                                       value="<?php echo esc_attr($current_settings['ghl_webhook_url']); ?>" 
                                       class="regular-text">
                                <p class="description">Webhook URL for GoHighLevel automation triggers.</p>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="ml-test-connection">
                        <button type="button" class="button button-secondary" onclick="testGHLConnection()">
                            Test GoHighLevel Connection
                        </button>
                        <span id="ghl-test-result"></span>
                    </div>
                </div>
                
                <div class="ml-settings-section">
                    <h3>Google Maps Integration</h3>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="google_maps_api_key">Google Maps API Key</label>
                            </th>
                            <td>
                                <input type="password" id="google_maps_api_key" name="settings[google_maps_api_key]" 
                                       value="<?php echo esc_attr($current_settings['google_maps_api_key']); ?>" 
                                       class="regular-text">
                                <p class="description">Google Maps API key for distance calculations. <a href="https://developers.google.com/maps/documentation/javascript/get-api-key" target="_blank">Get API Key</a></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
            <?php elseif ($current_tab === 'safeguards'): ?>
                
                <!-- Safeguards Settings -->
                <div class="ml-settings-section">
                    <h2>Safeguards & Monitoring</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="safeguards_enabled">Enable Safeguards</label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="safeguards_enabled" name="settings[safeguards_enabled]" 
                                           value="1" <?php checked($current_settings['safeguards_enabled']); ?>>
                                    Enable DJ monitoring and safeguards system
                                </label>
                                <p class="description">Monitor DJ behavior to prevent commission circumvention.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="safeguards_threshold">Alert Threshold</label>
                            </th>
                            <td>
                                <input type="number" id="safeguards_threshold" name="settings[safeguards_threshold]" 
                                       value="<?php echo esc_attr($current_settings['safeguards_threshold']); ?>" 
                                       min="1" max="10" class="small-text">
                                <p class="description">Number of suspicious activities before triggering an alert.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="safeguards_email_alerts">Email Alerts</label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="safeguards_email_alerts" name="settings[safeguards_email_alerts]" 
                                           value="1" <?php checked($current_settings['safeguards_email_alerts']); ?>>
                                    Send email alerts for high-priority safeguards issues
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="admin_email">Admin Email</label>
                            </th>
                            <td>
                                <input type="email" id="admin_email" name="settings[admin_email]" 
                                       value="<?php echo esc_attr($current_settings['admin_email']); ?>" 
                                       class="regular-text" required>
                                <p class="description">Email address to receive safeguards alerts.</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
            <?php elseif ($current_tab === 'advanced'): ?>
                
                <!-- Advanced Settings -->
                <div class="ml-settings-section">
                    <h2>Advanced Settings</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="debug_mode">Debug Mode</label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="debug_mode" name="settings[debug_mode]" 
                                           value="1" <?php checked($current_settings['debug_mode']); ?>>
                                    Enable debug mode (logs additional information)
                                </label>
                                <p class="description">Only enable for troubleshooting. May impact performance.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="cache_duration">Cache Duration</label>
                            </th>
                            <td>
                                <input type="number" id="cache_duration" name="settings[cache_duration]" 
                                       value="<?php echo esc_attr($current_settings['cache_duration']); ?>" 
                                       min="300" max="86400" class="small-text">
                                <span>seconds</span>
                                <p class="description">How long to cache distance calculations (300-86400 seconds).</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="api_rate_limit">API Rate Limit</label>
                            </th>
                            <td>
                                <input type="number" id="api_rate_limit" name="settings[api_rate_limit]" 
                                       value="<?php echo esc_attr($current_settings['api_rate_limit']); ?>" 
                                       min="10" max="1000" class="small-text">
                                <span>requests per hour</span>
                                <p class="description">Maximum API requests per hour for external services.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="equipment_tracking_enabled">Equipment Tracking</label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="equipment_tracking_enabled" name="settings[equipment_tracking_enabled]" 
                                           value="1" <?php checked($current_settings['equipment_tracking_enabled']); ?>>
                                    Enable equipment inventory tracking
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="equipment_auto_assign">Auto-assign Equipment</label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="equipment_auto_assign" name="settings[equipment_auto_assign]" 
                                           value="1" <?php checked($current_settings['equipment_auto_assign']); ?>>
                                    Automatically assign available equipment to bookings
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="ml-settings-section">
                    <h3>Data Management</h3>
                    
                    <div class="ml-data-actions">
                        <div class="ml-action-group">
                            <h4>Export Settings</h4>
                            <p>Download your current settings as a backup.</p>
                            <button type="button" class="button button-secondary" onclick="exportSettings()">
                                Export Settings
                            </button>
                        </div>
                        
                        <div class="ml-action-group">
                            <h4>Import Settings</h4>
                            <p>Upload a settings file to restore configuration.</p>
                            <input type="file" id="import_settings_file" accept=".json">
                            <button type="button" class="button button-secondary" onclick="importSettings()">
                                Import Settings
                            </button>
                        </div>
                        
                        <div class="ml-action-group ml-danger-zone">
                            <h4>Reset Settings</h4>
                            <p>Reset all settings to default values. This cannot be undone.</p>
                            <button type="button" class="button button-secondary" onclick="resetSettings()">
                                Reset to Defaults
                            </button>
                        </div>
                    </div>
                </div>
                
            <?php endif; ?>
            
        </div>
        
        <!-- Save Button -->
        <div class="ml-settings-footer">
            <button type="submit" class="button button-primary button-large">
                Save Settings
            </button>
            <span id="save-status"></span>
        </div>
        
    </form>
    
</div>

<script>
jQuery(document).ready(function($) {
    
    // Settings form submission
    $('#ml-settings-form').on('submit', function(e) {
        e.preventDefault();
        
        $('#save-status').html('<span class="ml-saving">Saving...</span>');
        
        const formData = new FormData(this);
        formData.append('action', 'ml_save_settings');
        formData.append('nonce', ml_admin.nonce);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $('#save-status').html('<span class="ml-saved">✓ Settings saved successfully!</span>');
                    setTimeout(function() {
                        $('#save-status').html('');
                    }, 3000);
                } else {
                    $('#save-status').html('<span class="ml-error">Error: ' + response.data + '</span>');
                }
            },
            error: function() {
                $('#save-status').html('<span class="ml-error">Error saving settings</span>');
            }
        });
    });
    
    // Auto-save on input change (with debounce)
    let saveTimeout;
    $('.ml-settings-content input, .ml-settings-content textarea, .ml-settings-content select').on('change', function() {
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(function() {
            $('#ml-settings-form').trigger('submit');
        }, 2000);
    });
    
});

// Test functions
function testStripeConnection() {
    jQuery('#stripe-test-result').html('<span class="ml-testing">Testing...</span>');
    
    jQuery.post(ajaxurl, {
        action: 'ml_test_stripe_connection',
        nonce: ml_admin.nonce
    }, function(response) {
        if (response.success) {
            jQuery('#stripe-test-result').html('<span class="ml-success">✓ Connection successful! ' + response.data.message + '</span>');
        } else {
            jQuery('#stripe-test-result').html('<span class="ml-error">✗ ' + response.data + '</span>');
        }
    });
}

function testGHLConnection() {
    jQuery('#ghl-test-result').html('<span class="ml-testing">Testing...</span>');
    
    jQuery.post(ajaxurl, {
        action: 'ml_test_ghl_connection',
        nonce: ml_admin.nonce
    }, function(response) {
        if (response.success) {
            jQuery('#ghl-test-result').html('<span class="ml-success">✓ ' + response.data + '</span>');
        } else {
            jQuery('#ghl-test-result').html('<span class="ml-error">✗ ' + response.data + '</span>');
        }
    });
}

function sendTestEmail() {
    const testEmail = jQuery('#test_email_address').val();
    if (!testEmail) {
        alert('Please enter a test email address.');
        return;
    }
    
    jQuery('#email-test-result').html('<span class="ml-testing">Sending...</span>');
    
    jQuery.post(ajaxurl, {
        action: 'ml_test_email_settings',
        test_email: testEmail,
        nonce: ml_admin.nonce
    }, function(response) {
        if (response.success) {
            jQuery('#email-test-result').html('<span class="ml-success">✓ ' + response.data + '</span>');
        } else {
            jQuery('#email-test-result').html('<span class="ml-error">✗ ' + response.data + '</span>');
        }
    });
}

function uploadLogo() {
    const fileInput = jQuery('#company_logo')[0];
    if (!fileInput.files[0]) {
        alert('Please select a logo file.');
        return;
    }
    
    const formData = new FormData();
    formData.append('logo', fileInput.files[0]);
    formData.append('action', 'ml_upload_logo');
    formData.append('nonce', ml_admin.nonce);
    
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                alert('Logo uploaded successfully!');
                location.reload();
            } else {
                alert('Error: ' + response.data);
            }
        }
    });
}

function removeLogo() {
    if (confirm('Are you sure you want to remove the current logo?')) {
        jQuery('input[name="settings[company_logo]"]').val('');
        jQuery('#ml-settings-form').trigger('submit');
    }
}

function exportSettings() {
    window.location.href = ajaxurl + '?action=ml_export_settings&nonce=' + ml_admin.nonce;
}

function importSettings() {
    const fileInput = jQuery('#import_settings_file')[0];
    if (!fileInput.files[0]) {
        alert('Please select a settings file.');
        return;
    }
    
    const reader = new FileReader();
    reader.onload = function(e) {
        jQuery.post(ajaxurl, {
            action: 'ml_import_settings',
            settings_data: e.target.result,
            nonce: ml_admin.nonce
        }, function(response) {
            if (response.success) {
                alert('Settings imported successfully!');
                location.reload();
            } else {
                alert('Error: ' + response.data);
            }
        });
    };
    reader.readAsText(fileInput.files[0]);
}

function resetSettings() {
    if (confirm('Are you sure you want to reset all settings to defaults? This cannot be undone.')) {
        jQuery.post(ajaxurl, {
            action: 'ml_reset_settings',
            nonce: ml_admin.nonce
        }, function(response) {
            if (response.success) {
                alert('Settings reset successfully!');
                location.reload();
            } else {
                alert('Error: ' + response.data);
            }
        });
    }
}
</script>
                                <input type="text" id="company_name" name="settings[company_name]" 
                                       value="<?php echo esc_attr($current_settings['company_name']); ?>" 
                                       class="regular-text" required>
                                <p class="description">Your company name as it appears in emails and documents.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="company_email">Company Email</label>
                            </th>
                            <td>
                                <input type="email" id="company_email" name="settings[company_email]" 
                                       value="<?php echo esc_attr($current_settings['company_email']); ?>" 
                                       class="regular-text" required>
                                <p class="description">Main contact email address for your business.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="company_phone">Company Phone</label>
                            </th>
                            <td>
                                <input type="tel" id="company_phone" name="settings[company_phone]" 
                                       value="<?php echo esc_attr($current_settings['company_phone']); ?>" 
                                       class="regular-text">
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="company_website">Company Website</label>
                            </th>
                            <td>
                                <input type="url" id="company_website" name="settings[company_website]" 
                                       value="<?php echo esc_attr($current_settings['company_website']); ?>" 
                                       class="regular-text">
                            </td>
                        </tr>
                    </table>
                </div>
                
            <?php elseif ($current_tab === 'company'): ?>
                
                <!-- Company Information -->
                <div class="ml-settings-section">
                    <h2>Company Information</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="company_address">Company Address</label>
                            </th>
                            <td>
                                <textarea id="company_address" name="settings[company_address]" 
                                          rows="3" class="large-text"><?php echo esc_textarea($current_settings['company_address']); ?></textarea>
                                <p class="description">Full business address for invoices and correspondence.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="company_logo">Company Logo</label>
                            </th>
                            <td>
                                <div class="ml-logo-upload">
                                    <?php if ($current_settings['company_logo']): ?>
                                        <div class="ml-current-logo">
                                            <img src="<?php echo esc_url($current_settings['company_logo']); ?>" 
                                                 alt="Company Logo" style="max-width: 200px; height: auto;">
                                            <br>
                                            <button type="button" class="button button-secondary" onclick="removeLogo()">Remove Logo</button>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="ml-logo-upload-area">
                                        <input type="file" id="company_logo" name="logo" accept="image/*">
                                        <button type="button" class="button" onclick="uploadLogo()">Upload New Logo</button>
                                        <p class="description">Upload a logo for emails and documents. Max size: 2MB (JPG, PNG, GIF)</p>
                                    </div>
                                </div>
                                <input type="hidden" name="settings[company_logo]" value="<?php echo esc_attr($current_settings['company_logo']); ?>">
                            </td>
                        </tr>
                    </table>
                </div>
                
            <?php elseif ($current_tab === 'booking'): ?>
                
                <!-- Booking Settings -->
                <div class="ml-settings-section">
                    <h2>Booking Settings</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="default_deposit_percentage">Default Deposit Percentage</label>
                            </th>
                            <td>
                                <input type="number" id="default_deposit_percentage" 
                                       name="settings[default_deposit_percentage]" 
                                       value="<?php echo esc_attr($current_settings['default_deposit_percentage']); ?>" 
                                       min="0" max="100" step="0.1" class="small-text">
                                <span>%</span>
                                <p class="description">Default deposit percentage for new bookings.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="default_commission_rate">Default Commission Rate</label>
                            </th>
                            <td>
                                <input type="number" id="default_commission_rate" 
                                       name="settings[default_commission_rate]" 
                                       value="<?php echo esc_attr($current_settings['default_commission_rate']); ?>" 
                                       min="0" max="100" step="0.1" class="small-text">
                                <span>%</span>
                                <p class="description">Default agency commission rate for new DJs.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="booking_advance_days">Minimum Booking Advance</label>
                            </th>
                            <td>
                                <input type="number" id="booking_advance_days" 
                                       name="settings[booking_advance_days]" 
                                       value="<?php echo esc_attr($current_settings['booking_advance_days']); ?>" 
                                       min="0" class="small-text">
                                <span>days</span>
                                <p class="description">Minimum number of days in advance for bookings.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="default_travel_rate">Default Travel Rate</label>
                            </th>
                            <td>
                                <span>£</span>
                                <input type="number" id="default_travel_rate" 
                                       name="settings[default_travel_rate]" 
                                       value="<?php echo esc_attr($current_settings['default_travel_rate']); ?>" 
                                       min="0" step="0.01" class="small-text">
                                <span>per mile</span>
                                <p class="description">Default travel cost per mile for new DJs.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="free_travel_radius">Free Travel Radius</label>
                            </th>
                            <td>
                                <input type="number" id="free_travel_radius" 
                                       name="settings[free_travel_radius]" 
                                       value="<?php echo esc_attr($current_settings['free_travel_radius']); ?>" 
                                       min="0" class="small-text">
                                <span>miles</span>
                                <p class="description">Distance within which no travel charges apply.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="max_travel_distance">Maximum Travel Distance</label>
                            </th>
                            <td>
                                <input type="number" id="max_travel_distance" 
                                       name="settings[max_travel_distance]" 
                                       value="<?php echo esc_attr($current_settings['max_travel_distance']); ?>" 
                                       min="0" class="small-text">
                                <span>miles</span>
                                <p class="description">Maximum distance DJs will travel for bookings.</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="ml-settings-section">
                    <h3>Terms & Conditions</h3>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="cancellation_policy">Cancellation Policy</label>
                            </th>
                            <td>
                                <textarea id="cancellation_policy" name="settings[cancellation_policy]" 
                                          rows="5" class="large-text"><?php echo esc_textarea($current_settings['cancellation_policy']); ?></textarea>
                                <p class="description">Your cancellation policy terms.</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="terms_conditions">Terms & Conditions</label>
                            </th>