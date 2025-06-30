<?php
/**
 * Admin Dashboard Page
 * Main overview page for the Music & Lights DJ Booking System
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current user
$current_user = wp_get_current_user();

// Get date ranges
$today = date('Y-m-d');
$this_month_start = date('Y-m-01');
$this_month_end = date('Y-m-t');
$last_month_start = date('Y-m-01', strtotime('-1 month'));
$last_month_end = date('Y-m-t', strtotime('-1 month'));

// Get statistics
global $wpdb;

// Booking statistics
$total_bookings_this_month = $wpdb->get_var($wpdb->prepare("
    SELECT COUNT(*) FROM {$wpdb->posts} p
    INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
    WHERE p.post_type = 'dj_booking'
    AND pm.meta_key = 'event_date'
    AND pm.meta_value BETWEEN %s AND %s
    AND p.post_status NOT IN ('cancelled', 'trash')
", $this_month_start, $this_month_end));

$confirmed_bookings_this_month = $wpdb->get_var($wpdb->prepare("
    SELECT COUNT(*) FROM {$wpdb->posts} p
    INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
    WHERE p.post_type = 'dj_booking'
    AND pm.meta_key = 'event_date'
    AND pm.meta_value BETWEEN %s AND %s
    AND p.post_status IN ('confirmed', 'deposit_paid', 'paid_in_full')
", $this_month_start, $this_month_end));

// Revenue statistics
$revenue_this_month = $wpdb->get_var($wpdb->prepare("
    SELECT SUM(pm2.meta_value) FROM {$wpdb->posts} p
    INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'event_date'
    INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'total_amount'
    WHERE p.post_type = 'dj_booking'
    AND pm1.meta_value BETWEEN %s AND %s
    AND p.post_status IN ('confirmed', 'deposit_paid', 'paid_in_full')
", $this_month_start, $this_month_end));

// Commission statistics
$commissions_table = $wpdb->prefix . 'dj_commissions';
$pending_commissions = $wpdb->get_var("
    SELECT SUM(dj_earnings) FROM $commissions_table
    WHERE status IN ('earned', 'completed')
");

// Recent bookings
$recent_bookings = $wpdb->get_results("
    SELECT p.*, 
           pm1.meta_value as client_name,
           pm2.meta_value as event_date,
           pm3.meta_value as assigned_dj,
           pm4.meta_value as total_amount
    FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'client_name'
    LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'event_date'
    LEFT JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = 'assigned_dj'
    LEFT JOIN {$wpdb->postmeta} pm4 ON p.ID = pm4.post_id AND pm4.meta_key = 'total_amount'
    WHERE p.post_type = 'dj_booking'
    ORDER BY p.post_date DESC
    LIMIT 10
");

// Upcoming events
$upcoming_events = $wpdb->get_results($wpdb->prepare("
    SELECT p.*, 
           pm1.meta_value as client_name,
           pm2.meta_value as event_date,
           pm3.meta_value as assigned_dj,
           pm4.meta_value as venue_name
    FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'client_name'
    LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'event_date'
    LEFT JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = 'assigned_dj'
    LEFT JOIN {$wpdb->postmeta} pm4 ON p.ID = pm4.post_id AND pm4.meta_key = 'venue_name'
    WHERE p.post_type = 'dj_booking'
    AND pm2.meta_value >= %s
    AND p.post_status IN ('confirmed', 'deposit_paid', 'paid_in_full')
    ORDER BY pm2.meta_value ASC
    LIMIT 10
", $today));

// Safeguards alerts
$safeguards_table = $wpdb->prefix . 'dj_safeguards_log';
$active_alerts = $wpdb->get_var("
    SELECT COUNT(*) FROM $safeguards_table
    WHERE alert_level IN ('high', 'medium')
    AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");

// Get GHL integration status
$ghl_integration = new GHL_Integration();
$ghl_status = $ghl_integration->get_configuration_status();
?>

<div class="wrap">
    <h1><?php echo esc_html__('Music & Lights Dashboard', 'musicandlights'); ?></h1>
    
    <div style="margin: 20px 0;">
        <p style="font-size: 16px;">
            <?php echo sprintf(
                esc_html__('Welcome back, %s! Here\'s your business overview.', 'musicandlights'),
                esc_html($current_user->display_name)
            ); ?>
        </p>
    </div>
    
    <!-- Quick Stats -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <!-- This Month's Bookings -->
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid #007cba;">
            <h3 style="margin: 0 0 10px 0; color: #666; font-size: 14px; font-weight: normal;">
                <?php echo esc_html__('This Month\'s Bookings', 'musicandlights'); ?>
            </h3>
            <div style="font-size: 32px; font-weight: bold; color: #007cba;">
                <?php echo intval($total_bookings_this_month); ?>
            </div>
            <p style="margin: 10px 0 0 0; color: #666; font-size: 14px;">
                <?php echo sprintf(
                    esc_html__('%d confirmed', 'musicandlights'),
                    intval($confirmed_bookings_this_month)
                ); ?>
            </p>
        </div>
        
        <!-- Revenue This Month -->
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid #00a32a;">
            <h3 style="margin: 0 0 10px 0; color: #666; font-size: 14px; font-weight: normal;">
                <?php echo esc_html__('Revenue This Month', 'musicandlights'); ?>
            </h3>
            <div style="font-size: 32px; font-weight: bold; color: #00a32a;">
                £<?php echo number_format(floatval($revenue_this_month), 0); ?>
            </div>
            <p style="margin: 10px 0 0 0; color: #666; font-size: 14px;">
                <?php 
                $avg_booking_value = $confirmed_bookings_this_month > 0 ? floatval($revenue_this_month) / $confirmed_bookings_this_month : 0;
                echo sprintf(
                    esc_html__('Avg: £%s per booking', 'musicandlights'),
                    number_format($avg_booking_value, 0)
                ); 
                ?>
            </p>
        </div>
        
        <!-- Pending Commissions -->
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid #f56e28;">
            <h3 style="margin: 0 0 10px 0; color: #666; font-size: 14px; font-weight: normal;">
                <?php echo esc_html__('Pending DJ Payments', 'musicandlights'); ?>
            </h3>
            <div style="font-size: 32px; font-weight: bold; color: #f56e28;">
                £<?php echo number_format(floatval($pending_commissions), 0); ?>
            </div>
            <p style="margin: 10px 0 0 0;">
                <a href="<?php echo admin_url('admin.php?page=musicandlights-commissions'); ?>" style="color: #f56e28;">
                    <?php echo esc_html__('Process payments →', 'musicandlights'); ?>
                </a>
            </p>
        </div>
        
        <!-- Active Alerts -->
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 4px solid #d63638;">
            <h3 style="margin: 0 0 10px 0; color: #666; font-size: 14px; font-weight: normal;">
                <?php echo esc_html__('Active Safeguards Alerts', 'musicandlights'); ?>
            </h3>
            <div style="font-size: 32px; font-weight: bold; color: #d63638;">
                <?php echo intval($active_alerts); ?>
            </div>
            <p style="margin: 10px 0 0 0;">
                <a href="<?php echo admin_url('admin.php?page=musicandlights-safeguards'); ?>" style="color: #d63638;">
                    <?php echo esc_html__('Review alerts →', 'musicandlights'); ?>
                </a>
            </p>
        </div>
    </div>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
        <!-- Recent Bookings -->
        <div style="background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden;">
            <div style="padding: 20px; border-bottom: 1px solid #eee;">
                <h2 style="margin: 0; font-size: 18px;"><?php echo esc_html__('Recent Bookings', 'musicandlights'); ?></h2>
            </div>
            <div style="max-height: 400px; overflow-y: auto;">
                <?php if (empty($recent_bookings)): ?>
                    <p style="padding: 20px; text-align: center; color: #666;">
                        <?php echo esc_html__('No recent bookings found.', 'musicandlights'); ?>
                    </p>
                <?php else: ?>
                    <table style="width: 100%; border-collapse: collapse;">
                        <?php foreach ($recent_bookings as $booking):
                            $dj_name = $booking->assigned_dj ? get_the_title($booking->assigned_dj) : 'Not assigned';
                            $status_colors = [
                                'enquiry' => '#72aee6',
                                'confirmed' => '#00a32a',
                                'deposit_paid' => '#046bd2',
                                'cancelled' => '#d63638'
                            ];
                            $status_color = $status_colors[$booking->post_status] ?? '#646970';
                        ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 15px;">
                                    <div>
                                        <strong><?php echo esc_html($booking->client_name); ?></strong><br>
                                        <small style="color: #666;">
                                            <?php echo $booking->event_date ? esc_html(date('j M Y', strtotime($booking->event_date))) : 'Date TBC'; ?>
                                        </small>
                                    </div>
                                </td>
                                <td style="padding: 15px; text-align: right;">
                                    <span style="display: inline-block; padding: 4px 8px; background: <?php echo $status_color; ?>; color: white; border-radius: 12px; font-size: 11px;">
                                        <?php echo esc_html(ucwords(str_replace('_', ' ', $booking->post_status))); ?>
                                    </span><br>
                                    <small style="color: #666;">£<?php echo number_format(floatval($booking->total_amount), 0); ?></small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>
            <div style="padding: 15px; border-top: 1px solid #eee; text-align: center;">
                <a href="<?php echo admin_url('admin.php?page=musicandlights-bookings'); ?>" class="button">
                    <?php echo esc_html__('View All Bookings', 'musicandlights'); ?>
                </a>
            </div>
        </div>
        
        <!-- Upcoming Events -->
        <div style="background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden;">
            <div style="padding: 20px; border-bottom: 1px solid #eee;">
                <h2 style="margin: 0; font-size: 18px;"><?php echo esc_html__('Upcoming Events', 'musicandlights'); ?></h2>
            </div>
            <div style="max-height: 400px; overflow-y: auto;">
                <?php if (empty($upcoming_events)): ?>
                    <p style="padding: 20px; text-align: center; color: #666;">
                        <?php echo esc_html__('No upcoming events.', 'musicandlights'); ?>
                    </p>
                <?php else: ?>
                    <table style="width: 100%; border-collapse: collapse;">
                        <?php foreach ($upcoming_events as $event):
                            $dj_name = $event->assigned_dj ? get_the_title($event->assigned_dj) : 'Not assigned';
                            $days_until = floor((strtotime($event->event_date) - time()) / 86400);
                        ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 15px;">
                                    <div>
                                        <strong><?php echo esc_html(date('j M', strtotime($event->event_date))); ?></strong>
                                        <span style="color: #666; font-size: 12px;">
                                            (<?php echo sprintf(esc_html__('%d days', 'musicandlights'), $days_until); ?>)
                                        </span><br>
                                        <small style="color: #666;">
                                            <?php echo esc_html($event->client_name); ?><br>
                                            <?php echo esc_html($event->venue_name ?: 'Venue TBC'); ?>
                                        </small>
                                    </div>
                                </td>
                                <td style="padding: 15px; text-align: right;">
                                    <small style="color: #666;">
                                        <?php echo esc_html($dj_name); ?>
                                    </small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>
            </div>
            <div style="padding: 15px; border-top: 1px solid #eee; text-align: center;">
                <a href="<?php echo admin_url('admin.php?page=musicandlights-bookings&status=confirmed'); ?>" class="button">
                    <?php echo esc_html__('View Calendar', 'musicandlights'); ?>
                </a>
            </div>
        </div>
    </div>
    
    <!-- System Status -->
    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h2 style="margin: 0 0 20px 0; font-size: 18px;"><?php echo esc_html__('System Status', 'musicandlights'); ?></h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
            <!-- GoHighLevel Status -->
            <div>
                <h3 style="margin: 0 0 10px 0; font-size: 16px;"><?php echo esc_html__('GoHighLevel Integration', 'musicandlights'); ?></h3>
                <table style="width: 100%;">
                    <tr>
                        <td style="padding: 5px 0;"><?php echo esc_html__('API Connection', 'musicandlights'); ?></td>
                        <td style="text-align: right; padding: 5px 0;">
                            <?php if ($ghl_status['api_key_configured']): ?>
                                <span style="color: #00a32a;">✓ <?php echo esc_html__('Connected', 'musicandlights'); ?></span>
                            <?php else: ?>
                                <span style="color: #d63638;">✗ <?php echo esc_html__('Not configured', 'musicandlights'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0;"><?php echo esc_html__('Workflows', 'musicandlights'); ?></td>
                        <td style="text-align: right; padding: 5px 0;">
                            <?php if ($ghl_status['workflows_configured']): ?>
                                <span style="color: #00a32a;">✓ <?php echo esc_html__('Configured', 'musicandlights'); ?></span>
                            <?php else: ?>
                                <span style="color: #f56e28;">⚠ <?php echo esc_html__('Setup required', 'musicandlights'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                <p style="margin: 10px 0 0 0;">
                    <a href="<?php echo admin_url('admin.php?page=musicandlights-ghl-settings'); ?>">
                        <?php echo esc_html__('Configure GoHighLevel →', 'musicandlights'); ?>
                    </a>
                </p>
            </div>
            
            <!-- Payment Gateway Status -->
            <div>
                <h3 style="margin: 0 0 10px 0; font-size: 16px;"><?php echo esc_html__('Payment Gateway', 'musicandlights'); ?></h3>
                <table style="width: 100%;">
                    <tr>
                        <td style="padding: 5px 0;"><?php echo esc_html__('Stripe', 'musicandlights'); ?></td>
                        <td style="text-align: right; padding: 5px 0;">
                            <?php if (get_option('musicandlights_stripe_public_key')): ?>
                                <span style="color: #00a32a;">✓ <?php echo esc_html__('Connected', 'musicandlights'); ?></span>
                            <?php else: ?>
                                <span style="color: #d63638;">✗ <?php echo esc_html__('Not configured', 'musicandlights'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0;"><?php echo esc_html__('Test Mode', 'musicandlights'); ?></td>
                        <td style="text-align: right; padding: 5px 0;">
                            <?php if (get_option('musicandlights_stripe_test_mode', 'yes') === 'yes'): ?>
                                <span style="color: #f56e28;"><?php echo esc_html__('Enabled', 'musicandlights'); ?></span>
                            <?php else: ?>
                                <span style="color: #666;"><?php echo esc_html__('Disabled', 'musicandlights'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                <p style="margin: 10px 0 0 0;">
                    <a href="<?php echo admin_url('admin.php?page=musicandlights-settings'); ?>">
                        <?php echo esc_html__('Configure payments →', 'musicandlights'); ?>
                    </a>
                </p>
            </div>
            
            <!-- Quick Actions -->
            <div>
                <h3 style="margin: 0 0 10px 0; font-size: 16px;"><?php echo esc_html__('Quick Actions', 'musicandlights'); ?></h3>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <a href="<?php echo admin_url('post-new.php?post_type=dj_booking'); ?>" class="button button-primary">
                        <?php echo esc_html__('Create New Booking', 'musicandlights'); ?>
                    </a>
                    <a href="<?php echo admin_url('post-new.php?post_type=dj_profile'); ?>" class="button">
                        <?php echo esc_html__('Add New DJ', 'musicandlights'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=musicandlights-commissions'); ?>" class="button">
                        <?php echo esc_html__('Process Payments', 'musicandlights'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>