<?php
// =============================================================================
// FILE: admin/bookings.php
// =============================================================================
?>
<div class="wrap">
    <h1><?php echo esc_html__('Bookings Management', 'musicandlights'); ?></h1>
    
    <?php
    // Get bookings with filters
    $status_filter = $_GET['status'] ?? 'all';
    $date_filter = $_GET['date_from'] ?? '';
    $dj_filter = $_GET['dj_id'] ?? '';
    
    $args = [
        'post_type' => 'dj_booking',
        'posts_per_page' => 20,
        'post_status' => ['enquiry', 'pending_details', 'pending_call', 'quote_sent', 'deposit_pending', 'confirmed', 'paid_in_full', 'completed', 'cancelled']
    ];
    
    if ($status_filter !== 'all') {
        $args['post_status'] = [$status_filter];
    }
    
    $meta_query = [];
    if ($date_filter) {
        $meta_query[] = [
            'key' => 'event_date',
            'value' => $date_filter,
            'compare' => '>='
        ];
    }
    
    if ($dj_filter) {
        $meta_query[] = [
            'key' => 'assigned_dj',
            'value' => $dj_filter,
            'compare' => '='
        ];
    }
    
    if (!empty($meta_query)) {
        $args['meta_query'] = $meta_query;
    }
    
    $bookings = get_posts($args);
    $djs = get_posts(['post_type' => 'dj_profile', 'posts_per_page' => -1]);
    ?>
    
    <!-- Filters -->
    <div style="background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <form method="get" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
            <input type="hidden" name="page" value="musicandlights-bookings">
            
            <div>
                <label><?php echo esc_html__('Status:', 'musicandlights'); ?></label>
                <select name="status" class="regular-text">
                    <option value="all"><?php echo esc_html__('All Statuses', 'musicandlights'); ?></option>
                    <option value="enquiry" <?php selected($status_filter, 'enquiry'); ?>><?php echo esc_html__('Enquiry', 'musicandlights'); ?></option>
                    <option value="pending_details" <?php selected($status_filter, 'pending_details'); ?>><?php echo esc_html__('Pending Details', 'musicandlights'); ?></option>
                    <option value="confirmed" <?php selected($status_filter, 'confirmed'); ?>><?php echo esc_html__('Confirmed', 'musicandlights'); ?></option>
                    <option value="paid_in_full" <?php selected($status_filter, 'paid_in_full'); ?>><?php echo esc_html__('Paid in Full', 'musicandlights'); ?></option>
                </select>
            </div>
            
            <div>
                <label><?php echo esc_html__('From Date:', 'musicandlights'); ?></label>
                <input type="date" name="date_from" value="<?php echo esc_attr($date_filter); ?>" class="regular-text">
            </div>
            
            <div>
                <label><?php echo esc_html__('DJ:', 'musicandlights'); ?></label>
                <select name="dj_id" class="regular-text">
                    <option value=""><?php echo esc_html__('All DJs', 'musicandlights'); ?></option>
                    <?php foreach ($djs as $dj): ?>
                        <option value="<?php echo $dj->ID; ?>" <?php selected($dj_filter, $dj->ID); ?>>
                            <?php echo esc_html($dj->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <button type="submit" class="button button-primary"><?php echo esc_html__('Filter', 'musicandlights'); ?></button>
                <a href="<?php echo admin_url('admin.php?page=musicandlights-bookings'); ?>" class="button"><?php echo esc_html__('Clear', 'musicandlights'); ?></a>
            </div>
        </form>
    </div>
    
    <!-- Bookings Table -->
    <?php if (empty($bookings)): ?>
        <div style="text-align: center; padding: 40px; background: white; border-radius: 8px;">
            <h3><?php echo esc_html__('No bookings found', 'musicandlights'); ?></h3>
            <p><?php echo esc_html__('No bookings match your current filters.', 'musicandlights'); ?></p>
        </div>
    <?php else: ?>
        <div style="background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Booking ID', 'musicandlights'); ?></th>
                        <th><?php echo esc_html__('Client', 'musicandlights'); ?></th>
                        <th><?php echo esc_html__('DJ', 'musicandlights'); ?></th>
                        <th><?php echo esc_html__('Event Date', 'musicandlights'); ?></th>
                        <th><?php echo esc_html__('Status', 'musicandlights'); ?></th>
                        <th><?php echo esc_html__('Total', 'musicandlights'); ?></th>
                        <th><?php echo esc_html__('Actions', 'musicandlights'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking):
                        $meta = get_post_meta($booking->ID);
                        $client_name = $meta['client_name'][0] ?? '';
                        $client_email = $meta['client_email'][0] ?? '';
                        $assigned_dj = $meta['assigned_dj'][0] ?? '';
                        $dj_name = $assigned_dj ? get_the_title($assigned_dj) : 'Not assigned';
                        $event_date = $meta['event_date'][0] ?? '';
                        $total_amount = $meta['total_amount'][0] ?? '0';
                        
                        // Status styling
                        $status_colors = [
                            'enquiry' => '#72aee6',
                            'pending_details' => '#f0b849',
                            'confirmed' => '#00a32a',
                            'paid_in_full' => '#046bd2',
                            'cancelled' => '#d63638'
                        ];
                        $status_color = $status_colors[$booking->post_status] ?? '#646970';
                    ?>
                        <tr>
                            <td><strong>#<?php echo $booking->ID; ?></strong></td>
                            <td>
                                <?php echo esc_html($client_name); ?><br>
                                <small><?php echo esc_html($client_email); ?></small>
                            </td>
                            <td><?php echo esc_html($dj_name); ?></td>
                            <td>
                                <?php echo $event_date ? esc_html(date('j M Y', strtotime($event_date))) : 'TBC'; ?>
                            </td>
                            <td>
                                <span style="background: <?php echo $status_color; ?>; color: white; padding: 4px 8px; border-radius: 12px; font-size: 11px;">
                                    <?php echo esc_html(ucwords(str_replace('_', ' ', $booking->post_status))); ?>
                                </span>
                            </td>
                            <td>£<?php echo number_format($total_amount, 2); ?></td>
                            <td>
                                <a href="<?php echo admin_url('post.php?post=' . $booking->ID . '&action=edit'); ?>" 
                                   class="button button-small"><?php echo esc_html__('Edit', 'musicandlights'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 20px; text-align: center;">
            <a href="<?php echo admin_url('post-new.php?post_type=dj_booking'); ?>" class="button button-primary button-large">
                <?php echo esc_html__('Add New Booking', 'musicandlights'); ?>
            </a>
        </div>
    <?php endif; ?>
</div>
