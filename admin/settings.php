<?php
/**
 * Settings Page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle form submission
if (isset($_POST['save_settings'])) {
    check_admin_referer('musicandlights_settings_nonce');
    
    // Company Information
    update_option('musicandlights_company_name', sanitize_text_field($_POST['company_name']));
    update_option('musicandlights_company_phone', sanitize_text_field($_POST['company_phone']));
    update_option('musicandlights_company_email', sanitize_email($_POST['company_email']));
    update_option('musicandlights_company_address', sanitize_textarea_field($_POST['company_address']));
    
    // Business Settings
    update_option('musicandlights_agency_commission', floatval($_POST['agency_commission']));
    update_option('musicandlights_deposit_percentage', floatval($_POST['deposit_percentage']));
    update_option('musicandlights_travel_free_miles', intval($_POST['travel_free_miles']));
    update_option('musicandlights_travel_rate_per_mile', floatval($_POST['travel_rate_per_mile']));
    update_option('musicandlights_accommodation_fee', floatval($_POST['accommodation_fee']));
    update_option('musicandlights_international_day_rate', floatval($_POST['international_day_rate']));
    update_option('musicandlights_booking_window_lock_hours', intval($_POST['booking_window_lock_hours']));
    
    // Payment Settings
    update_option('musicandlights_stripe_public_key', sanitize_text_field($_POST['stripe_public_key']));
    update_option('musicandlights_stripe_secret_key', sanitize_text_field($_POST['stripe_secret_key']));
    update_option('musicandlights_stripe_test_mode', isset($_POST['stripe_test_mode']) ? 'yes' : 'no');
    
    // Email Settings
    if (isset($_FILES['email_logo']) && $_FILES['email_logo']['error'] == 0) {
        $upload = wp_handle_upload($_FILES['email_logo'], array('test_form' => false));
        if ($upload && !isset($upload['error'])) {
            update_option('musicandlights_email_logo', $upload['url']);
        }
    }
    
    // Google Maps API
    update_option('musicandlights_google_maps_api_key', sanitize_text_field($_POST['google_maps_api_key']));
    
    echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved successfully!', 'musicandlights') . '</p></div>';
}

// Get current settings
$company_name = get_option('musicandlights_company_name', 'Music & Lights');
$company_phone = get_option('musicandlights_company_phone', '');
$company_email = get_option('musicandlights_company_email', get_option('admin_email'));
$company_address = get_option('musicandlights_company_address', '');
$email_logo = get_option('musicandlights_email_logo', '');

$agency_commission = get_option('musicandlights_agency_commission', '25');
$deposit_percentage = get_option('musicandlights_deposit_percentage', '50');
$travel_free_miles = get_option('musicandlights_travel_free_miles', '100');
$travel_rate_per_mile = get_option('musicandlights_travel_rate_per_mile', '1.00');
$accommodation_fee = get_option('musicandlights_accommodation_fee', '200');
$international_day_rate = get_option('musicandlights_international_day_rate', '1000');
$booking_window_lock_hours = get_option('musicandlights_booking_window_lock_hours', '48');

$stripe_public_key = get_option('musicandlights_stripe_public_key', '');
$stripe_secret_key = get_option('musicandlights_stripe_secret_key', '');
$stripe_test_mode = get_option('musicandlights_stripe_test_mode', 'yes');

$google_maps_api_key = get_option('musicandlights_google_maps_api_key', '');
?>

<div class="wrap">
    <h1><?php echo esc_html__('Music & Lights Settings', 'musicandlights'); ?></h1>
    
    <form method="post" action="" enctype="multipart/form-data">
        <?php wp_nonce_field('musicandlights_settings_nonce'); ?>
        
        <!-- Company Information -->
        <div style="background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h2 style="margin-top: 0;"><?php echo esc_html__('Company Information', 'musicandlights'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="company_name"><?php echo esc_html__('Company Name', 'musicandlights'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="company_name" id="company_name" 
                               value="<?php echo esc_attr($company_name); ?>" class="regular-text" required />
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="company_phone"><?php echo esc_html__('Company Phone', 'musicandlights'); ?></label>
                    </th>
                    <td>
                        <input type="tel" name="company_phone" id="company_phone" 
                               value="<?php echo esc_attr($company_phone); ?>" class="regular-text" />
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="company_email"><?php echo esc_html__('Company Email', 'musicandlights'); ?></label>
                    </th>
                    <td>
                        <input type="email" name="company_email" id="company_email" 
                               value="<?php echo esc_attr($company_email); ?>" class="regular-text" required />
                        <p class="description"><?php echo esc_html__('This email will be used for all system notifications', 'musicandlights'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="company_address"><?php echo esc_html__('Company Address', 'musicandlights'); ?></label>
                    </th>
                    <td>
                        <textarea name="company_address" id="company_address" rows="3" 
                                  class="large-text"><?php echo esc_textarea($company_address); ?></textarea>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="email_logo"><?php echo esc_html__('Email Logo', 'musicandlights'); ?></label>
                    </th>
                    <td>
                        <?php if ($email_logo): ?>
                            <div style="margin-bottom: 10px;">
                                <img src="<?php echo esc_url($email_logo); ?>" alt="Email Logo" style="max-width: 200px; height: auto;" />
                            </div>
                        <?php endif; ?>
                        <input type="file" name="email_logo" id="email_logo" accept="image/*" />
                        <p class="description"><?php echo esc_html__('Logo to display in email headers (recommended: 200px wide)', 'musicandlights'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Business Settings -->
        <div style="background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h2 style="margin-top: 0;"><?php echo esc_html__('Business Settings', 'musicandlights'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="agency_commission"><?php echo esc_html__('Agency Commission (%)', 'musicandlights'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="agency_commission" id="agency_commission" 
                               value="<?php echo esc_attr($agency_commission); ?>" min="0" max="100" step="0.01" class="small-text" />
                        <span>%</span>
                        <p class="description"><?php echo esc_html__('Percentage of booking total that goes to the agency', 'musicandlights'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="deposit_percentage"><?php echo esc_html__('Deposit Percentage (%)', 'musicandlights'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="deposit_percentage" id="deposit_percentage" 
                               value="<?php echo esc_attr($deposit_percentage); ?>" min="0" max="100" step="0.01" class="small-text" />
                        <span>%</span>
                        <p class="description"><?php echo esc_html__('Percentage of total required as deposit', 'musicandlights'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="travel_free_miles"><?php echo esc_html__('Free Travel Miles', 'musicandlights'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="travel_free_miles" id="travel_free_miles" 
                               value="<?php echo esc_attr($travel_free_miles); ?>" min="0" class="small-text" />
                        <span><?php echo esc_html__('miles', 'musicandlights'); ?></span>
                        <p class="description"><?php echo esc_html__('Default free travel distance for DJs', 'musicandlights'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="travel_rate_per_mile"><?php echo esc_html__('Travel Rate per Mile (£)', 'musicandlights'); ?></label>
                    </th>
                    <td>
                        <span>£</span>
                        <input type="number" name="travel_rate_per_mile" id="travel_rate_per_mile" 
                               value="<?php echo esc_attr($travel_rate_per_mile); ?>" min="0" step="0.01" class="small-text" />
                        <p class="description"><?php echo esc_html__('Default rate charged per mile after free distance', 'musicandlights'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="accommodation_fee"><?php echo esc_html__('Accommodation Fee (£)', 'musicandlights'); ?></label>
                    </th>
                    <td>
                        <span>£</span>
                        <input type="number" name="accommodation_fee" id="accommodation_fee" 
                               value="<?php echo esc_attr($accommodation_fee); ?>" min="0" step="0.01" class="regular-text" />
                        <p class="description"><?php echo esc_html__('Default accommodation fee for long-distance bookings', 'musicandlights'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="international_day_rate"><?php echo esc_html__('International Day Rate (£)', 'musicandlights'); ?></label>
                    </th>
                    <td>
                        <span>£</span>
                        <input type="number" name="international_day_rate" id="international_day_rate" 
                               value="<?php echo esc_attr($international_day_rate); ?>" min="0" step="0.01" class="regular-text" />
                        <p class="description"><?php echo esc_html__('Default day rate for international bookings', 'musicandlights'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="booking_window_lock_hours"><?php echo esc_html__('Booking Window Lock (hours)', 'musicandlights'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="booking_window_lock_hours" id="booking_window_lock_hours" 
                               value="<?php echo esc_attr($booking_window_lock_hours); ?>" min="0" class="small-text" />
                        <span><?php echo esc_html__('hours', 'musicandlights'); ?></span>
                        <p class="description"><?php echo esc_html__('How long to lock a date when DJ is matched with an enquiry', 'musicandlights'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Payment Gateway Settings -->
        <div style="background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h2 style="margin-top: 0;"><?php echo esc_html__('Payment Gateway (Stripe)', 'musicandlights'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="stripe_test_mode"><?php echo esc_html__('Test Mode', 'musicandlights'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="stripe_test_mode" id="stripe_test_mode" value="yes" 
                                   <?php checked($stripe_test_mode, 'yes'); ?> />
                            <?php echo esc_html__('Enable test mode', 'musicandlights'); ?>
                        </label>
                        <p class="description"><?php echo esc_html__('Use Stripe test keys for development', 'musicandlights'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="stripe_public_key"><?php echo esc_html__('Publishable Key', 'musicandlights'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="stripe_public_key" id="stripe_public_key" 
                               value="<?php echo esc_attr($stripe_public_key); ?>" class="large-text" />
                        <p class="description"><?php echo esc_html__('Your Stripe publishable key', 'musicandlights'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="stripe_secret_key"><?php echo esc_html__('Secret Key', 'musicandlights'); ?></label>
                    </th>
                    <td>
                        <input type="password" name="stripe_secret_key" id="stripe_secret_key" 
                               value="<?php echo esc_attr($stripe_secret_key); ?>" class="large-text" />
                        <p class="description"><?php echo esc_html__('Your Stripe secret key', 'musicandlights'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- API Settings -->
        <div style="background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h2 style="margin-top: 0;"><?php echo esc_html__('API Settings', 'musicandlights'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="google_maps_api_key"><?php echo esc_html__('Google Maps API Key', 'musicandlights'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="google_maps_api_key" id="google_maps_api_key" 
                               value="<?php echo esc_attr($google_maps_api_key); ?>" class="large-text" />
                        <p class="description">
                            <?php echo esc_html__('Optional: For accurate distance calculations. If not provided, approximate calculations will be used.', 'musicandlights'); ?>
                            <a href="https://developers.google.com/maps/documentation/distance-matrix/get-api-key" target="_blank">
                                <?php echo esc_html__('Get API Key', 'musicandlights'); ?>
                            </a>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Page Settings -->
        <div style="background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h2 style="margin-top: 0;"><?php echo esc_html__('Page URLs', 'musicandlights'); ?></h2>
            <p><?php echo esc_html__('The following pages were created automatically:', 'musicandlights'); ?></p>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html__('Booking Page', 'musicandlights'); ?></th>
                    <td>
                        <a href="<?php echo esc_url(home_url('/book-dj/')); ?>" target="_blank">
                            <?php echo esc_url(home_url('/book-dj/')); ?>
                        </a>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('DJ Profiles Page', 'musicandlights'); ?></th>
                    <td>
                        <a href="<?php echo esc_url(home_url('/our-djs/')); ?>" target="_blank">
                            <?php echo esc_url(home_url('/our-djs/')); ?>
                        </a>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('DJ Dashboard', 'musicandlights'); ?></th>
                    <td>
                        <a href="<?php echo esc_url(home_url('/dj-dashboard/')); ?>" target="_blank">
                            <?php echo esc_url(home_url('/dj-dashboard/')); ?>
                        </a>
                    </td>
                </tr>
            </table>
        </div>
        
        <p class="submit">
            <input type="submit" name="save_settings" class="button-primary" 
                   value="<?php echo esc_attr__('Save Settings', 'musicandlights'); ?>" />
        </p>
    </form>
    
    <!-- System Tools -->
    <div style="background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h2 style="margin-top: 0;"><?php echo esc_html__('System Tools', 'musicandlights'); ?></h2>
        
        <div style="margin-bottom: 20px;">
            <h3><?php echo esc_html__('Distance Cache', 'musicandlights'); ?></h3>
            <p><?php echo esc_html__('Clear cached distance calculations to force fresh lookups.', 'musicandlights'); ?></p>
            <button type="button" class="button" id="clear-distance-cache">
                <?php echo esc_html__('Clear Distance Cache', 'musicandlights'); ?>
            </button>
            <span id="cache-status" style="margin-left: 10px;"></span>
        </div>
        
        <div style="margin-bottom: 20px;">
            <h3><?php echo esc_html__('Test Email', 'musicandlights'); ?></h3>
            <p><?php echo esc_html__('Send a test email to verify email templates are working correctly.', 'musicandlights'); ?></p>
            <input type="email" id="test-email-address" placeholder="<?php echo esc_attr__('Email address', 'musicandlights'); ?>" class="regular-text" />
            <select id="test-email-type">
                <option value="booking_confirmation"><?php echo esc_html__('Booking Confirmation', 'musicandlights'); ?></option>
                <option value="quote"><?php echo esc_html__('Quote Email', 'musicandlights'); ?></option>
                <option value="payment_reminder"><?php echo esc_html__('Payment Reminder', 'musicandlights'); ?></option>
            </select>
            <button type="button" class="button" id="send-test-email">
                <?php echo esc_html__('Send Test Email', 'musicandlights'); ?>
            </button>
            <span id="email-status" style="margin-left: 10px;"></span>
        </div>
        
        <div>
            <h3><?php echo esc_html__('Export/Import', 'musicandlights'); ?></h3>
            <p>
                <a href="<?php echo admin_url('admin.php?page=musicandlights-settings&action=export'); ?>" class="button">
                    <?php echo esc_html__('Export Settings', 'musicandlights'); ?>
                </a>
                <span style="margin-left: 10px;"><?php echo esc_html__('Download all plugin settings as JSON', 'musicandlights'); ?></span>
            </p>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Clear distance cache
    $('#clear-distance-cache').on('click', function() {
        var $button = $(this);
        var $status = $('#cache-status');
        
        $button.prop('disabled', true);
        $status.html('<span style="color: #666;">Clearing cache...</span>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'clear_distance_cache',
                nonce: '<?php echo wp_create_nonce('musicandlights_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $status.html('<span style="color: #00a32a;">✓ Cache cleared successfully!</span>');
                } else {
                    $status.html('<span style="color: #d63638;">✗ Failed to clear cache</span>');
                }
            },
            complete: function() {
                $button.prop('disabled', false);
                setTimeout(function() {
                    $status.html('');
                }, 3000);
            }
        });
    });
    
    // Send test email
    $('#send-test-email').on('click', function() {
        var $button = $(this);
        var $status = $('#email-status');
        var email = $('#test-email-address').val();
        var type = $('#test-email-type').val();
        
        if (!email) {
            alert('Please enter an email address');
            return;
        }
        
        $button.prop('disabled', true);
        $status.html('<span style="color: #666;">Sending email...</span>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'send_test_email',
                email: email,
                type: type,
                nonce: '<?php echo wp_create_nonce('musicandlights_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $status.html('<span style="color: #00a32a;">✓ Test email sent!</span>');
                } else {
                    $status.html('<span style="color: #d63638;">✗ Failed to send email</span>');
                }
            },
            complete: function() {
                $button.prop('disabled', false);
                setTimeout(function() {
                    $status.html('');
                }, 5000);
            }
        });
    });
});
</script>