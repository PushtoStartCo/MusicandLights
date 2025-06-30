<?php
/**
 * GoHighLevel Settings Page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle form submission
if (isset($_POST['save_ghl_settings'])) {
    // Verify nonce for security
    if (!wp_verify_nonce($_POST['_wpnonce'], 'ghl_settings_nonce')) {
        wp_die(__('Security check failed. Please try again.', 'musicandlights'));
    }
    
    // Save API credentials with validation
    $api_key = sanitize_text_field($_POST['ghl_api_key'] ?? '');
    $location_id = sanitize_text_field($_POST['ghl_location_id'] ?? '');
    $webhook_secret = sanitize_text_field($_POST['ghl_webhook_secret'] ?? '');
    
    // Validate required fields
    $errors = array();
    if (empty($api_key)) {
        $errors[] = __('API Key is required.', 'musicandlights');
    }
    if (empty($location_id)) {
        $errors[] = __('Location ID is required.', 'musicandlights');
    }
    
    if (empty($errors)) {
        // Save API credentials
        update_option('musicandlights_ghl_api_key', $api_key);
        update_option('musicandlights_ghl_location_id', $location_id);
        update_option('musicandlights_ghl_webhook_secret', $webhook_secret);
        
        // Save workflow IDs
        $workflow_fields = array(
            'ghl_new_booking_workflow_id',
            'ghl_international_workflow_id', 
            'ghl_details_completed_workflow_id',
            'ghl_deposit_paid_workflow_id',
            'ghl_payment_due_workflow_id',
            'ghl_payment_paid_workflow_id',
            'ghl_completed_workflow_id',
            'ghl_cancelled_workflow_id',
            'ghl_review_workflow_id'
        );
        
        foreach ($workflow_fields as $field) {
            update_option('musicandlights_' . $field, sanitize_text_field($_POST[$field] ?? ''));
        }
        
        // Save pipeline and stage IDs
        $pipeline_fields = array(
            'ghl_pipeline_id',
            'ghl_initial_stage_id',
            'ghl_quote_stage_id', 
            'ghl_deposit_stage_id',
            'ghl_confirmed_stage_id',
            'ghl_completed_stage_id'
        );
        
        foreach ($pipeline_fields as $field) {
            update_option('musicandlights_' . $field, sanitize_text_field($_POST[$field] ?? ''));
        }
        
        // Save calendar settings
        update_option('musicandlights_ghl_calendar_id', sanitize_text_field($_POST['ghl_calendar_id'] ?? ''));
        update_option('musicandlights_ghl_default_user_id', sanitize_text_field($_POST['ghl_default_user_id'] ?? ''));
        
        echo '<div class="notice notice-success"><p>' . esc_html__('GoHighLevel settings saved successfully!', 'musicandlights') . '</p></div>';
    } else {
        echo '<div class="notice notice-error"><p>' . implode('<br>', array_map('esc_html', $errors)) . '</p></div>';
    }
}

// Get current settings with defaults
$api_key = get_option('musicandlights_ghl_api_key', '');
$location_id = get_option('musicandlights_ghl_location_id', '');
$webhook_secret = get_option('musicandlights_ghl_webhook_secret', '');

// Workflow IDs
$workflow_ids = array(
    'new_booking' => get_option('musicandlights_ghl_new_booking_workflow_id', ''),
    'international' => get_option('musicandlights_ghl_international_workflow_id', ''),
    'details_completed' => get_option('musicandlights_ghl_details_completed_workflow_id', ''),
    'deposit_paid' => get_option('musicandlights_ghl_deposit_paid_workflow_id', ''),
    'payment_due' => get_option('musicandlights_ghl_payment_due_workflow_id', ''),
    'payment_paid' => get_option('musicandlights_ghl_payment_paid_workflow_id', ''),
    'completed' => get_option('musicandlights_ghl_completed_workflow_id', ''),
    'cancelled' => get_option('musicandlights_ghl_cancelled_workflow_id', ''),
    'review' => get_option('musicandlights_ghl_review_workflow_id', '')
);

// Pipeline settings
$pipeline_id = get_option('musicandlights_ghl_pipeline_id', '');
$stage_ids = array(
    'initial' => get_option('musicandlights_ghl_initial_stage_id', ''),
    'quote' => get_option('musicandlights_ghl_quote_stage_id', ''),
    'deposit' => get_option('musicandlights_ghl_deposit_stage_id', ''),
    'confirmed' => get_option('musicandlights_ghl_confirmed_stage_id', ''),
    'completed' => get_option('musicandlights_ghl_completed_stage_id', '')
);

// Calendar settings
$calendar_id = get_option('musicandlights_ghl_calendar_id', '');
$default_user_id = get_option('musicandlights_ghl_default_user_id', '');

// Test connection if API key exists
$connection_status = false;
if (!empty($api_key) && !empty($location_id) && class_exists('GHL_Integration')) {
    try {
        $ghl_integration = new GHL_Integration();
        // Uncomment when test_connection method is implemented
        // $connection_status = $ghl_integration->test_connection();
    } catch (Exception $e) {
        error_log('GHL Integration error: ' . $e->getMessage());
    }
}

// Generate proper webhook URL
$webhook_url = add_query_arg(array(
    'action' => 'ghl_webhook'
), admin_url('admin-ajax.php'));
?>

<div class="wrap">
    <h1><?php echo esc_html__('GoHighLevel Integration Settings', 'musicandlights'); ?></h1>
    
    <div class="notice notice-info">
        <p><strong><?php echo esc_html__('Webhook URL:', 'musicandlights'); ?></strong></p>
        <p>
            <code style="background: #f0f0f0; padding: 4px 8px; border-radius: 4px; word-break: break-all;">
                <?php echo esc_url($webhook_url); ?>
            </code>
        </p>
        <p><small><?php echo esc_html__('Add this URL to your GoHighLevel webhooks configuration.', 'musicandlights'); ?></small></p>
    </div>
    
    <form method="post" action="" id="ghl-settings-form">
        <?php wp_nonce_field('ghl_settings_nonce'); ?>
        
        <!-- API Credentials -->
        <div class="postbox">
            <h2 class="hndle"><?php echo esc_html__('API Credentials', 'musicandlights'); ?></h2>
            <div class="inside">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="ghl_api_key"><?php echo esc_html__('API Key', 'musicandlights'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="password" name="ghl_api_key" id="ghl_api_key" 
                                   value="<?php echo esc_attr($api_key); ?>" class="regular-text" required />
                            <button type="button" class="button button-small" id="toggle-api-key">
                                <?php echo esc_html__('Show', 'musicandlights'); ?>
                            </button>
                            <p class="description"><?php echo esc_html__('Your GoHighLevel API key (required)', 'musicandlights'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="ghl_location_id"><?php echo esc_html__('Location ID', 'musicandlights'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="text" name="ghl_location_id" id="ghl_location_id" 
                                   value="<?php echo esc_attr($location_id); ?>" class="regular-text" required />
                            <p class="description"><?php echo esc_html__('Your GoHighLevel location ID (required)', 'musicandlights'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="ghl_webhook_secret"><?php echo esc_html__('Webhook Secret', 'musicandlights'); ?></label>
                        </th>
                        <td>
                            <input type="password" name="ghl_webhook_secret" id="ghl_webhook_secret" 
                                   value="<?php echo esc_attr($webhook_secret); ?>" class="regular-text" />
                            <p class="description"><?php echo esc_html__('Optional: Secret key for webhook verification (recommended for security)', 'musicandlights'); ?></p>
                        </td>
                    </tr>
                    
                    <?php if (!empty($api_key) && !empty($location_id)): ?>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Connection Status', 'musicandlights'); ?></th>
                        <td>
                            <button type="button" class="button" id="test-ghl-connection">
                                <span class="dashicons dashicons-admin-plugins"></span>
                                <?php echo esc_html__('Test Connection', 'musicandlights'); ?>
                            </button>
                            <span id="connection-status" style="margin-left: 10px;"></span>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        
        <!-- Workflow Configuration -->
        <div class="postbox">
            <h2 class="hndle"><?php echo esc_html__('Workflow Configuration', 'musicandlights'); ?></h2>
            <div class="inside">
                <p><?php echo esc_html__('Enter the workflow IDs from your GoHighLevel account for each booking stage.', 'musicandlights'); ?></p>
                
                <table class="form-table">
                    <?php 
                    $workflow_config = array(
                        'new_booking' => __('New Booking Workflow', 'musicandlights'),
                        'international' => __('International Booking Workflow', 'musicandlights'),
                        'details_completed' => __('Details Completed Workflow', 'musicandlights'),
                        'deposit_paid' => __('Deposit Paid Workflow', 'musicandlights'),
                        'payment_due' => __('Payment Due Workflow', 'musicandlights'),
                        'payment_paid' => __('Payment Paid Workflow', 'musicandlights'),
                        'completed' => __('Event Completed Workflow', 'musicandlights'),
                        'cancelled' => __('Booking Cancelled Workflow', 'musicandlights'),
                        'review' => __('Review Request Workflow', 'musicandlights')
                    );
                    
                    foreach ($workflow_config as $key => $label): ?>
                    <tr>
                        <th scope="row">
                            <label for="ghl_<?php echo esc_attr($key); ?>_workflow_id"><?php echo esc_html($label); ?></label>
                        </th>
                        <td>
                            <input type="text" name="ghl_<?php echo esc_attr($key); ?>_workflow_id" 
                                   id="ghl_<?php echo esc_attr($key); ?>_workflow_id" 
                                   value="<?php echo esc_attr($workflow_ids[$key]); ?>" class="regular-text" 
                                   placeholder="<?php echo esc_attr__('Workflow ID', 'musicandlights'); ?>" />
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
        
        <!-- Pipeline Configuration -->
        <div class="postbox">
            <h2 class="hndle"><?php echo esc_html__('Pipeline & Stages', 'musicandlights'); ?></h2>
            <div class="inside">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="ghl_pipeline_id"><?php echo esc_html__('Pipeline ID', 'musicandlights'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="ghl_pipeline_id" id="ghl_pipeline_id" 
                                   value="<?php echo esc_attr($pipeline_id); ?>" class="regular-text" 
                                   placeholder="<?php echo esc_attr__('Pipeline ID', 'musicandlights'); ?>" />
                        </td>
                    </tr>
                </table>
                
                <h3><?php echo esc_html__('Stage IDs', 'musicandlights'); ?></h3>
                <table class="form-table">
                    <?php 
                    $stage_config = array(
                        'initial' => __('Initial Enquiry Stage', 'musicandlights'),
                        'quote' => __('Quote Sent Stage', 'musicandlights'),
                        'deposit' => __('Deposit Pending Stage', 'musicandlights'),
                        'confirmed' => __('Confirmed Stage', 'musicandlights'),
                        'completed' => __('Completed Stage', 'musicandlights')
                    );
                    
                    foreach ($stage_config as $key => $label): ?>
                    <tr>
                        <th scope="row">
                            <label for="ghl_<?php echo esc_attr($key); ?>_stage_id"><?php echo esc_html($label); ?></label>
                        </th>
                        <td>
                            <input type="text" name="ghl_<?php echo esc_attr($key); ?>_stage_id" 
                                   id="ghl_<?php echo esc_attr($key); ?>_stage_id" 
                                   value="<?php echo esc_attr($stage_ids[$key]); ?>" class="regular-text"
                                   placeholder="<?php echo esc_attr__('Stage ID', 'musicandlights'); ?>" />
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
        
        <!-- Calendar Settings -->
        <div class="postbox">
            <h2 class="hndle"><?php echo esc_html__('Calendar & User Settings', 'musicandlights'); ?></h2>
            <div class="inside">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="ghl_calendar_id"><?php echo esc_html__('Calendar ID', 'musicandlights'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="ghl_calendar_id" id="ghl_calendar_id" 
                                   value="<?php echo esc_attr($calendar_id); ?>" class="regular-text" 
                                   placeholder="<?php echo esc_attr__('Calendar ID', 'musicandlights'); ?>" />
                            <p class="description"><?php echo esc_html__('The GoHighLevel calendar ID for booking events', 'musicandlights'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="ghl_default_user_id"><?php echo esc_html__('Default User ID', 'musicandlights'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="ghl_default_user_id" id="ghl_default_user_id" 
                                   value="<?php echo esc_attr($default_user_id); ?>" class="regular-text" 
                                   placeholder="<?php echo esc_attr__('User ID', 'musicandlights'); ?>" />
                            <p class="description"><?php echo esc_html__('Default GHL user ID for task assignments', 'musicandlights'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Custom Fields Setup -->
        <div class="postbox">
            <h2 class="hndle"><?php echo esc_html__('Custom Fields Setup', 'musicandlights'); ?></h2>
            <div class="inside">
                <p><?php echo esc_html__('The following custom fields need to be created in your GoHighLevel account:', 'musicandlights'); ?></p>
                
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Field Name', 'musicandlights'); ?></th>
                            <th><?php echo esc_html__('Field Key', 'musicandlights'); ?></th>
                            <th><?php echo esc_html__('Type', 'musicandlights'); ?></th>
                            <th><?php echo esc_html__('Description', 'musicandlights'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $custom_fields = array(
                            array('Booking ID', 'booking_id', 'Text', __('WordPress booking ID reference', 'musicandlights')),
                            array('Event Date', 'event_date', 'Date', __('Date of the event', 'musicandlights')),
                            array('DJ Name', 'dj_name', 'Text', __('Assigned DJ name', 'musicandlights')),
                            array('Total Amount', 'total_amount', 'Number', __('Total booking amount', 'musicandlights')),
                            array('Booking Status', 'booking_status', 'Text', __('Current booking status', 'musicandlights')),
                            array('Venue Name', 'venue_name', 'Text', __('Event venue name', 'musicandlights')),
                            array('Event Type', 'event_type', 'Text', __('Type of event', 'musicandlights'))
                        );
                        
                        foreach ($custom_fields as $field): ?>
                        <tr>
                            <td><strong><?php echo esc_html($field[0]); ?></strong></td>
                            <td><code><?php echo esc_html($field[1]); ?></code></td>
                            <td><?php echo esc_html($field[2]); ?></td>
                            <td><?php echo esc_html($field[3]); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if (!empty($api_key) && !empty($location_id)): ?>
                <p style="margin-top: 20px;">
                    <button type="button" class="button" id="setup-custom-fields">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php echo esc_html__('Auto-Create Custom Fields', 'musicandlights'); ?>
                    </button>
                    <span id="setup-status" style="margin-left: 10px;"></span>
                </p>
                <p class="description">
                    <?php echo esc_html__('This will attempt to create the required custom fields in your GoHighLevel account automatically.', 'musicandlights'); ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
        
        <p class="submit">
            <input type="submit" name="save_ghl_settings" class="button-primary" 
                   value="<?php echo esc_attr__('Save Settings', 'musicandlights'); ?>" />
            <input type="button" class="button" id="reset-settings" 
                   value="<?php echo esc_attr__('Reset to Defaults', 'musicandlights'); ?>" />
        </p>
    </form>
</div>

<style>
.required {
    color: #d63638;
}
.postbox {
    margin-bottom: 20px;
}
.postbox .hndle {
    padding: 12px 20px;
    background: #f9f9f9;
    border-bottom: 1px solid #eee;
    margin: 0;
}
.postbox .inside {
    padding: 20px;
}
#connection-status .dashicons {
    width: 16px;
    height: 16px;
    font-size: 16px;
    vertical-align: middle;
}
.success-message {
    color: #00a32a;
}
.error-message {
    color: #d63638;
}
.loading-message {
    color: #666;
}
</style>

<script>
jQuery(document).ready(function($) {
    
    // Toggle API key visibility
    $('#toggle-api-key').on('click', function() {
        var $apiKeyField = $('#ghl_api_key');
        var $button = $(this);
        
        if ($apiKeyField.attr('type') === 'password') {
            $apiKeyField.attr('type', 'text');
            $button.text('<?php echo esc_js(__('Hide', 'musicandlights')); ?>');
        } else {
            $apiKeyField.attr('type', 'password');
            $button.text('<?php echo esc_js(__('Show', 'musicandlights')); ?>');
        }
    });
    
    // Test connection
    $('#test-ghl-connection').on('click', function() {
        var $button = $(this);
        var $status = $('#connection-status');
        var $spinner = $('<span class="dashicons dashicons-update-alt" style="animation: rotation 1s infinite linear;"></span>');
        
        $button.prop('disabled', true);
        $status.html($spinner.add('<span class="loading-message"> <?php echo esc_js(__('Testing connection...', 'musicandlights')); ?></span>'));
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'test_ghl_connection',
                api_key: $('#ghl_api_key').val(),
                location_id: $('#ghl_location_id').val(),
                nonce: '<?php echo wp_create_nonce('test_ghl_connection'); ?>'
            },
            timeout: 15000,
            success: function(response) {
                if (response.success) {
                    $status.html('<span class="dashicons dashicons-yes-alt success-message"></span> <span class="success-message"><?php echo esc_js(__('Connection successful!', 'musicandlights')); ?></span>');
                } else {
                    $status.html('<span class="dashicons dashicons-dismiss error-message"></span> <span class="error-message"><?php echo esc_js(__('Connection failed:', 'musicandlights')); ?> ' + (response.data || '<?php echo esc_js(__('Unknown error', 'musicandlights')); ?>') + '</span>');
                }
            },
            error: function(xhr, status, error) {
                var errorMessage = status === 'timeout' ? 
                    '<?php echo esc_js(__('Connection timeout', 'musicandlights')); ?>' : 
                    '<?php echo esc_js(__('Connection error', 'musicandlights')); ?>';
                $status.html('<span class="dashicons dashicons-dismiss error-message"></span> <span class="error-message">' + errorMessage + '</span>');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });
    
    // Setup custom fields
    $('#setup-custom-fields').on('click', function() {
        var $button = $(this);
        var $status = $('#setup-status');
        
        if (!confirm('<?php echo esc_js(__('This will create custom fields in your GoHighLevel account. Continue?', 'musicandlights')); ?>')) {
            return;
        }
        
        $button.prop('disabled', true);
        var $spinner = $('<span class="dashicons dashicons-update-alt" style="animation: rotation 1s infinite linear;"></span>');
        $status.html($spinner.add('<span class="loading-message"> <?php echo esc_js(__('Creating custom fields...', 'musicandlights')); ?></span>'));
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'setup_ghl_custom_fields',
                api_key: $('#ghl_api_key').val(),
                location_id: $('#ghl_location_id').val(),
                nonce: '<?php echo wp_create_nonce('setup_ghl_custom_fields'); ?>'
            },
            timeout: 30000,
            success: function(response) {
                if (response.success) {
                    $status.html('<span class="dashicons dashicons-yes-alt success-message"></span> <span class="success-message"><?php echo esc_js(__('Custom fields created successfully!', 'musicandlights')); ?></span>');
                } else {
                    $status.html('<span class="dashicons dashicons-dismiss error-message"></span> <span class="error-message"><?php echo esc_js(__('Failed to create custom fields:', 'musicandlights')); ?> ' + (response.data || '<?php echo esc_js(__('Unknown error', 'musicandlights')); ?>') + '</span>');
                }
            },
            error: function(xhr, status, error) {
                var errorMessage = status === 'timeout' ? 
                    '<?php echo esc_js(__('Request timeout', 'musicandlights')); ?>' : 
                    '<?php echo esc_js(__('Request failed', 'musicandlights')); ?>';
                $status.html('<span class="dashicons dashicons-dismiss error-message"></span> <span class="error-message">' + errorMessage + '</span>');
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });
    
    // Reset settings
    $('#reset-settings').on('click', function() {
        if (confirm('<?php echo esc_js(__('Are you sure you want to reset all settings? This action cannot be undone.', 'musicandlights')); ?>')) {
            $('#ghl-settings-form')[0].reset();
        }
    });
    
    // Form validation
    $('#ghl-settings-form').on('submit', function(e) {
        var apiKey = $('#ghl_api_key').val().trim();
        var locationId = $('#ghl_location_id').val().trim();
        
        if (!apiKey || !locationId) {
            e.preventDefault();
            alert('<?php echo esc_js(__('API Key and Location ID are required fields.', 'musicandlights')); ?>');
            return false;
        }
    });
});

// CSS animation for spinner
document.head.insertAdjacentHTML('beforeend', 
    '<style>@keyframes rotation { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }</style>'
);
</script>