
<?php
// =============================================================================
// FILE: admin/commissions.php
// =============================================================================
?>
<div class="wrap">
    <h1><?php echo esc_html__('Commission Tracking', 'musicandlights'); ?></h1>
    
    <?php
    global $wpdb;
    $commissions_table = $wpdb->prefix . 'dj_commissions';
    
    // Handle bulk payment processing
    if (isset($_POST['process_payments']) && !empty($_POST['commission_ids'])) {
        check_admin_referer('process_commissions_nonce');
        
        $processed = 0;
        foreach ($_POST['commission_ids'] as $commission_id) {
            $result = $wpdb->update(
                $commissions_table,
                [
                    'status' => 'paid',
                    'paid_date' => current_time('mysql'),
                    'payment_method' => sanitize_text_field($_POST['payment_method'] ?? 'bank_transfer'),
                    'payment_reference' => sanitize_text_field($_POST['payment_reference'] ?? '')
                ],
                ['id' => intval($commission_id)],
                ['%s', '%s', '%s', '%s'],
                ['%d']
            );
            
            if ($result) $processed++;
        }
        
        echo '<div class="notice notice-success"><p>' . 
             sprintf(esc_html__('Processed %d commission payments successfully!', 'musicandlights'), $processed) . 
             '</p></div>';
    }
    
    // Get commissions data
    $status_filter = $_GET['status'] ?? 'all';
    $dj_filter = $_GET['dj_id'] ?? '';
    
    $where_conditions = ['1=1'];
    if ($status_filter !== 'all') {
        $where_conditions[] = $wpdb->prepare('c.status = %s', $status_filter);
    }
    if ($dj_filter) {
        $where_conditions[] = $wpdb->prepare('c.dj_id = %d', $dj_filter);
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $commissions = $wpdb->get_results("
        SELECT c.*, p.post_title as dj_name, 
               pm1.meta_value as client_name,
               pm2.meta_value as event_date
        FROM $commissions_table c
        LEFT JOIN {$wpdb->posts} p ON c.dj_id = p.ID
        LEFT JOIN {$wpdb->postmeta} pm1 ON c.booking_id = pm1.post_id AND pm1.meta_key = 'client_name'
        LEFT JOIN {$wpdb->postmeta} pm2 ON c.booking_id = pm2.post_id AND pm2.meta_key = 'event_date'
        WHERE $where_clause
        ORDER BY c.created_at DESC
        LIMIT 50
    ");
    
    $djs = get_posts(['post_type' => 'dj_profile', 'posts_per_page' => -1]);
    ?>
    
    <!-- Summary Stats -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
        <?php
        $stats = $wpdb->get_row("
            SELECT 
                SUM(CASE WHEN status = 'earned' THEN dj_earnings ELSE 0 END) as total_due,
                SUM(CASE WHEN status = 'paid' THEN dj_earnings ELSE 0 END) as total_paid,
                SUM(agency_commission) as total_commission,
                COUNT(*) as total_bookings
            FROM $commissions_table
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        ?>
        
        <div style="background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #f56e28;">
            <h3 style="margin: 0;"><?php echo esc_html__('Due to DJs', 'musicandlights'); ?></h3>
            <div style="font-size: 2em; font-weight: bold; color: #f56e28;">
                £<?php echo number_format($stats->total_due ?? 0, 2); ?>
            </div>
        </div>
        
        <div style="background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #00a32a;">
            <h3 style="margin: 0;"><?php echo esc_html__('Paid Out', 'musicandlights'); ?></h3>
            <div style="font-size: 2em; font-weight: bold; color: #00a32a;">
                £<?php echo number_format($stats->total_paid ?? 0, 2); ?>
            </div>
        </div>
        
        <div style="background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #3858e9;">
            <h3 style="margin: 0;"><?php echo esc_html__('Agency Commission', 'musicandlights'); ?></h3>
            <div style="font-size: 2em; font-weight: bold; color: #3858e9;">
                £<?php echo number_format($stats->total_commission ?? 0, 2); ?>
            </div>
        </div>
        
        <div style="background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #646970;">
            <h3 style="margin: 0;"><?php echo esc_html__('Total Bookings', 'musicandlights'); ?></h3>
            <div style="font-size: 2em; font-weight: bold; color: #646970;">
                <?php echo intval($stats->total_bookings ?? 0); ?>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div style="background: white; padding: 20px; margin: 20px 0; border-radius: 8px;">
        <form method="get" style="display: flex; gap: 15px; align-items: end; flex-wrap: wrap;">
            <input type="hidden" name="page" value="musicandlights-commissions">
            
            <div>
                <label><?php echo esc_html__('Status:', 'musicandlights'); ?></label>
                <select name="status">
                    <option value="all"><?php echo esc_html__('All', 'musicandlights'); ?></option>
                    <option value="earned" <?php selected($status_filter, 'earned'); ?>><?php echo esc_html__('Earned (Due)', 'musicandlights'); ?></option>
                    <option value="paid" <?php selected($status_filter, 'paid'); ?>><?php echo esc_html__('Paid', 'musicandlights'); ?></option>
                </select>
            </div>
            
            <div>
                <label><?php echo esc_html__('DJ:', 'musicandlights'); ?></label>
                <select name="dj_id">
                    <option value=""><?php echo esc_html__('All DJs', 'musicandlights'); ?></option>
                    <?php foreach ($djs as $dj): ?>
                        <option value="<?php echo $dj->ID; ?>" <?php selected($dj_filter, $dj->ID); ?>>
                            <?php echo esc_html($dj->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" class="button button-primary"><?php echo esc_html__('Filter', 'musicandlights'); ?></button>
        </form>
    </div>
    
    <!-- Commissions Table -->
    <?php if (empty($commissions)): ?>
        <div style="text-align: center; padding: 40px; background: white; border-radius: 8px;">
            <h3><?php echo esc_html__('No commissions found', 'musicandlights'); ?></h3>
        </div>
    <?php else: ?>
        <form method="post" style="background: white; border-radius: 8px; overflow: hidden;">
            <?php wp_nonce_field('process_commissions_nonce'); ?>
            
            <!-- Bulk Actions -->
            <div style="padding: 20px; border-bottom: 1px solid #ddd; background: #f9f9f9;">
                <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                    <label>
                        <input type="checkbox" id="select-all"> <?php echo esc_html__('Select All', 'musicandlights'); ?>
                    </label>
                    
                    <select name="payment_method">
                        <option value="bank_transfer"><?php echo esc_html__('Bank Transfer', 'musicandlights'); ?></option>
                        <option value="paypal"><?php echo esc_html__('PayPal', 'musicandlights'); ?></option>
                        <option value="cash"><?php echo esc_html__('Cash', 'musicandlights'); ?></option>
                    </select>
                    
                    <input type="text" name="payment_reference" placeholder="<?php echo esc_attr__('Payment Reference', 'musicandlights'); ?>" class="regular-text">
                    
                    <button type="submit" name="process_payments" class="button button-primary" 
                            onclick="return confirm('<?php echo esc_js__('Process selected commission payments?', 'musicandlights'); ?>')">
                        <?php echo esc_html__('Process Payments', 'musicandlights'); ?>
                    </button>
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <td class="check-column"><input type="checkbox" id="cb-select-all"></td>
                        <th><?php echo esc_html__('DJ', 'musicandlights'); ?></th>
                        <th><?php echo esc_html__('Client', 'musicandlights'); ?></th>
                        <th><?php echo esc_html__('Event Date', 'musicandlights'); ?></th>
                        <th><?php echo esc_html__('Total Amount', 'musicandlights'); ?></th>
                        <th><?php echo esc_html__('DJ Earnings', 'musicandlights'); ?></th>
                        <th><?php echo esc_html__('Agency Commission', 'musicandlights'); ?></th>
                        <th><?php echo esc_html__('Status', 'musicandlights'); ?></th>
                        <th><?php echo esc_html__('Date', 'musicandlights'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($commissions as $commission): ?>
                        <tr>
                            <td class="check-column">
                                <?php if ($commission->status === 'earned'): ?>
                                    <input type="checkbox" name="commission_ids[]" value="<?php echo $commission->id; ?>" class="commission-checkbox">
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo esc_html($commission->dj_name ?: 'Unknown DJ'); ?></strong></td>
                            <td><?php echo esc_html($commission->client_name ?: 'Unknown Client'); ?></td>
                            <td>
                                <?php echo $commission->event_date ? esc_html(date('j M Y', strtotime($commission->event_date))) : 'TBC'; ?>
                            </td>
                            <td>£<?php echo number_format($commission->total_amount, 2); ?></td>
                            <td><strong>£<?php echo number_format($commission->dj_earnings, 2); ?></strong></td>
                            <td>£<?php echo number_format($commission->agency_commission, 2); ?></td>
                            <td>
                                <?php if ($commission->status === 'earned'): ?>
                                    <span style="background: #f56e28; color: white; padding: 4px 8px; border-radius: 12px; font-size: 11px;">
                                        <?php echo esc_html__('Due', 'musicandlights'); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="background: #00a32a; color: white; padding: 4px 8px; border-radius: 12px; font-size: 11px;">
                                        <?php echo esc_html__('Paid', 'musicandlights'); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($commission->status === 'paid' && $commission->paid_date): ?>
                                    <?php echo esc_html(date('j M Y', strtotime($commission->paid_date))); ?>
                                <?php else: ?>
                                    <?php echo esc_html(date('j M Y', strtotime($commission->earned_date ?: $commission->created_at))); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </form>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    $('#select-all, #cb-select-all').on('change', function() {
        $('.commission-checkbox').prop('checked', this.checked);
    });
    
    $('.commission-checkbox').on('change', function() {
        const total = $('.commission-checkbox').length;
        const checked = $('.commission-checkbox:checked').length;
        $('#select-all, #cb-select-all').prop('checked', total === checked);
    });
});
</script>

