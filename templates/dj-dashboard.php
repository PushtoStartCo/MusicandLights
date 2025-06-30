<?php
// FILE: templates/dj-dashboard.php
?>
<div class="musicandlights-dj-dashboard">
    <?php
    $current_user = wp_get_current_user();
    
    // Check if user is a DJ
    if (!in_array('dj_artist', $current_user->roles) && !current_user_can('manage_options')) {
        echo '<p>' . esc_html__('Access denied. This dashboard is for DJs only.', 'musicandlights') . '</p>';
        return;
    }
    
    // Find DJ profile for current user
    $dj_profile = get_posts([
        'post_type' => 'dj_profile',
        'meta_query' => [
            [
                'key' => 'dj_user_id',
                'value' => $current_user->ID,
                'compare' => '='
            ]
        ],
        'posts_per_page' => 1
    ]);
    
    if (empty($dj_profile) && !current_user_can('manage_options')):
    ?>
        <div style="text-align: center; padding: 40px;">
            <h2><?php echo esc_html__('DJ Profile Not Found', 'musicandlights'); ?></h2>
            <p><?php echo esc_html__('Your DJ profile hasn\'t been set up yet. Please contact the administrator.', 'musicandlights'); ?></p>
        </div>
    <?php 
        return;
    endif;
    
    $dj_id = !empty($dj_profile) ? $dj_profile[0]->ID : 0;
    ?>
    
    <div style="margin-bottom: 30px;">
        <h1><?php echo esc_html__('DJ Dashboard', 'musicandlights'); ?></h1>
        <p><?php echo esc_html(sprintf(__('Welcome back, %s!', 'musicandlights'), $current_user->display_name)); ?></p>
    </div>
    
    <!-- Quick Stats -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 4px solid #00a32a;">
            <h3 style="margin: 0 0 10px 0; color: #333;"><?php echo esc_html__('This Month', 'musicandlights'); ?></h3>
            <div style="font-size: 2em; font-weight: bold; color: #00a32a;">
                <?php echo esc_html('0'); // Will be populated with real data ?>
            </div>
            <p style="margin: 0; color: #666;"><?php echo esc_html__('Bookings', 'musicandlights'); ?></p>
        </div>
        
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 4px solid #3858e9;">
            <h3 style="margin: 0 0 10px 0; color: #333;"><?php echo esc_html__('Earnings', 'musicandlights'); ?></h3>
            <div style="font-size: 2em; font-weight: bold; color: #3858e9;">
                £<?php echo esc_html('0.00'); // Will be populated with real data ?>
            </div>
            <p style="margin: 0; color: #666;"><?php echo esc_html__('This Month', 'musicandlights'); ?></p>
        </div>
        
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-left: 4px solid #f56e28;">
            <h3 style="margin: 0 0 10px 0; color: #333;"><?php echo esc_html__('Next Event', 'musicandlights'); ?></h3>
            <div style="font-size: 1.2em; font-weight: bold; color: #f56e28;">
                <?php echo esc_html__('No events', 'musicandlights'); // Will be populated with real data ?>
            </div>
            <p style="margin: 0; color: #666;"><?php echo esc_html__('Upcoming', 'musicandlights'); ?></p>
        </div>
    </div>
    
    <!-- Recent Bookings -->
    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 30px;">
        <h2 style="margin: 0 0 20px 0;"><?php echo esc_html__('Recent Bookings', 'musicandlights'); ?></h2>
        
        <?php
        $recent_bookings = get_posts([
            'post_type' => 'dj_booking',
            'meta_query' => [
                [
                    'key' => 'assigned_dj',
                    'value' => $dj_id,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => 5,
            'post_status' => ['confirmed', 'deposit_paid', 'paid_in_full']
        ]);
        
        if (empty($recent_bookings)):
        ?>
            <p style="text-align: center; color: #666; padding: 20px;">
                <?php echo esc_html__('No recent bookings found.', 'musicandlights'); ?>
            </p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #ddd;">
                            <th style="text-align: left; padding: 10px;"><?php echo esc_html__('Client', 'musicandlights'); ?></th>
                            <th style="text-align: left; padding: 10px;"><?php echo esc_html__('Event Date', 'musicandlights'); ?></th>
                            <th style="text-align: left; padding: 10px;"><?php echo esc_html__('Status', 'musicandlights'); ?></th>
                            <th style="text-align: left; padding: 10px;"><?php echo esc_html__('Earnings', 'musicandlights'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_bookings as $booking):
                            $client_name = get_post_meta($booking->ID, 'client_name', true);
                            $event_date = get_post_meta($booking->ID, 'event_date', true);
                            $dj_earnings = get_post_meta($booking->ID, 'dj_earnings', true);
                        ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 10px;"><?php echo esc_html($client_name ?: 'Unknown'); ?></td>
                                <td style="padding: 10px;">
                                    <?php echo $event_date ? esc_html(date('j M Y', strtotime($event_date))) : 'TBC'; ?>
                                </td>
                                <td style="padding: 10px;">
                                    <span style="padding: 4px 8px; background: #e8f5e8; color: #2e7d32; border-radius: 12px; font-size: 12px;">
                                        <?php echo esc_html(ucwords(str_replace('_', ' ', $booking->post_status))); ?>
                                    </span>
                                </td>
                                <td style="padding: 10px; font-weight: bold;">
                                    £<?php echo $dj_earnings ? esc_html(number_format($dj_earnings, 2)) : '0.00'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Quick Actions -->
    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <h2 style="margin: 0 0 20px 0;"><?php echo esc_html__('Quick Actions', 'musicandlights'); ?></h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <?php if ($dj_id): ?>
                <a href="<?php echo admin_url('post.php?post=' . $dj_id . '&action=edit'); ?>" 
                   class="btn btn-primary" style="text-align: center;">
                    <?php echo esc_html__('Edit Profile', 'musicandlights'); ?>
                </a>
            <?php endif; ?>
            
            <a href="<?php echo admin_url('edit.php?post_type=dj_booking'); ?>" 
               class="btn btn-secondary" style="text-align: center; background: #6c757d; color: white;">
                <?php echo esc_html__('View All Bookings', 'musicandlights'); ?>
            </a>
            
            <a href="<?php echo home_url('/our-djs/'); ?>" 
               class="btn btn-secondary" style="text-align: center; background: #6c757d; color: white;">
                <?php echo esc_html__('View Public Profile', 'musicandlights'); ?>
            </a>
        </div>
    </div>
</div>