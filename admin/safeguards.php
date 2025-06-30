<?php
// =============================================================================
// FILE: admin/safeguards.php (Complete version)
// =============================================================================
?>
<div class="wrap">
    <h1><?php echo esc_html__('Safeguards Monitor', 'musicandlights'); ?></h1>
    
    <?php
    global $wpdb;
    $safeguards_table = $wpdb->prefix . 'dj_safeguards_log';
    
    // Handle alert actions
    if (isset($_POST['action']) && isset($_POST['alert_id'])) {
        check_admin_referer('safeguards_action_nonce');
        
        $alert_id = intval($_POST['alert_id']);
        $action = sanitize_text_field($_POST['action']);
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        
        $update_data = [
            'reviewed_by' => get_current_user_id(),
            'reviewed_at' => current_time('mysql'),
            'notes' => $notes
        ];
        
        switch ($action) {
            case 'resolve':
                $update_data['alert_level'] = 'resolved';
                break;
            case 'escalate':
                $update_data['alert_level'] = 'high';
                break;
            case 'dismiss':
                $update_data['alert_level'] = 'dismissed';
                break;
        }
        
        $wpdb->update($safeguards_table, $update_data, ['id' => $alert_id]);
        echo '<div class="notice notice-success"><p>' . esc_html__('Alert updated successfully!', 'musicandlights') . '</p></div>';
    }
    
    // Get safeguards data
    $alert_level_filter = $_GET['alert_level'] ?? 'all';
    $dj_filter = $_GET['dj_id'] ?? '';
    
    $where_conditions = ["sl.alert_level NOT IN ('resolved', 'dismissed')"];
    if ($alert_level_filter !== 'all') {
        $where_conditions[] = $wpdb->prepare('sl.alert_level = %s', $alert_level_filter);
    }
    if ($dj_filter) {
        $where_conditions[] = $wpdb->prepare('sl.dj_id = %d', $dj_filter);
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $alerts = $wpdb->get_results("
        SELECT sl.*, p.post_title as dj_name
        FROM $safeguards_table sl
        LEFT JOIN {$wpdb->posts} p ON sl.dj_id = p.ID
        WHERE $where_clause
        ORDER BY sl.created_at DESC
        LIMIT 50
    ");
    
    $djs = get_posts(['post_type' => 'dj_profile', 'posts_per_page' => -1]);
    
    // Get summary stats
    $stats = $wpdb->get_row("
        SELECT 
            COUNT(CASE WHEN alert_level = 'high' THEN 1 END) as high_alerts,
            COUNT(CASE WHEN alert_level = 'medium' THEN 1 END) as medium_alerts,
            COUNT(CASE WHEN alert_level = 'low' THEN 1 END) as low_alerts,
            COUNT(DISTINCT dj_id) as flagged_djs
        FROM $safeguards_table
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND alert_level NOT IN ('resolved', 'dismissed')
    ");
    ?>
    
    <!-- Alert Summary -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
        <div style="background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #d63638;">
            <h3 style="margin: 0; color: #d63638;"><?php echo esc_html__('High Priority', 'musicandlights'); ?></h3>
            <div style="font-size: 2em; font-weight: bold; color: #d63638;">
                <?php echo intval($stats->high_alerts ?? 0); ?>
            </div>
        </div>
        
        <div style="background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #f56e28;">
            <h3 style="margin: 0; color: #f56e28;"><?php echo esc_html__('Medium Priority', 'musicandlights'); ?></h3>
            <div style="font-size: 2em; font-weight: bold; color: #f56e28;">
                <?php echo intval($stats->medium_alerts ?? 0); ?>
            </div>
        </div>
        
        <div style="background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #f0b849;">
            <h3 style="margin: 0; color: #f0b849;"><?php echo esc_html__('Low Priority', 'musicandlights'); ?></h3>
            <div style="font-size: 2em; font-weight: bold; color: #f0b849;">
                <?php echo intval($stats->low_alerts ?? 0); ?>
            </div>
        </div>
        
        <div style="background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #646970;">
            <h3 style="margin: 0; color: #646970;"><?php echo esc_html__('Flagged DJs', 'musicandlights'); ?></h3>
            <div style="font-size: 2em; font-weight: bold; color: #646970;">
                <?php echo intval($stats->flagged_djs ?? 0); ?>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div style="background: white; padding: 20px; margin: 20px 0; border-radius: 8px;">
        <form method="get" style="display: flex; gap: 15px; align-items: end; flex-wrap: wrap;">
            <input type="hidden" name="page" value="musicandlights-safeguards">
            
            <div>
                <label><?php echo esc_html__('Alert Level:', 'musicandlights'); ?></label>
                <select name="alert_level">
                    <option value="all"><?php echo esc_html__('All Levels', 'musicandlights'); ?></option>
                    <option value="high" <?php selected($alert_level_filter, 'high'); ?>><?php echo esc_html__('High', 'musicandlights'); ?></option>
                    <option value="medium" <?php selected($alert_level_filter, 'medium'); ?>><?php echo esc_html__('Medium', 'musicandlights'); ?></option>
                    <option value="low" <?php selected($alert_level_filter, 'low'); ?>><?php echo esc_html__('Low', 'musicandlights'); ?></option>
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
    
    <!-- Alerts Table -->
    <?php if (empty($alerts)): ?>
        <div style="text-align: center; padding: 40px; background: white; border-radius: 8px;">
            <h3><?php echo esc_html__('No active alerts', 'musicandlights'); ?></h3>
            <p><?php echo esc_html__('All safeguard alerts have been resolved or dismissed.', 'musicandlights'); ?></p>
        </div>
    <?php else: ?>
        <div style="background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Date', 'musicandlights'); ?></th>
                        <th><?php echo esc_html__('DJ', 'musicandlights'); ?></th>
                        <th><?php echo esc_html__('Alert Type', 'musicandlights'); ?></th>
                        <th><?php echo esc_html__('Level', 'musicandlights'); ?></th>
                        <th><?php echo esc_html__('Details', 'musicandlights'); ?></th>
                        <th><?php echo esc_html__('Actions', 'musicandlights'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alerts as $alert):
                        $alert_type = str_replace(['flagged_', '_'], ['', ' '], $alert->new_status);
                        $level_colors = [
                            'high' => '#d63638',
                            'medium' => '#f56e28',
                            'low' => '#f0b849'
                        ];
                        $level_color = $level_colors[$alert->alert_level] ?? '#646970';
                        
                        // Parse notes for details
                        $notes_data = json_decode($alert->notes, true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            $notes_data = ['message' => $alert->notes];
                        }
                    ?>
                        <tr>
                            <td><?php echo esc_html(date('j M Y H:i', strtotime($alert->created_at))); ?></td>
                            <td><strong><?php echo esc_html($alert->dj_name ?: 'Unknown DJ'); ?></strong></td>
                            <td><?php echo esc_html(ucwords($alert_type)); ?></td>
                            <td>
                                <span style="display: inline-block; padding: 4px 8px; background: <?php echo $level_color; ?>; color: white; border-radius: 12px; font-size: 11px;">
                                    <?php echo esc_html(ucfirst($alert->alert_level)); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($alert->enquiry_date): ?>
                                    <strong><?php echo esc_html__('Enquiry Date:', 'musicandlights'); ?></strong> 
                                    <?php echo esc_html(date('j M Y', strtotime($alert->enquiry_date))); ?><br>
                                <?php endif; ?>
                                
                                <?php if (isset($notes_data['message'])): ?>
                                    <?php echo esc_html($notes_data['message']); ?>
                                <?php endif; ?>
                                
                                <?php if ($alert->reviewed_at): ?>
                                    <br><small style="color: #666;">
                                        <?php echo sprintf(
                                            esc_html__('Reviewed by %s on %s', 'musicandlights'),
                                            get_user_by('ID', $alert->reviewed_by)->display_name ?? 'Unknown',
                                            date('j M Y', strtotime($alert->reviewed_at))
                                        ); ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="button button-small review-alert" 
                                        data-alert-id="<?php echo $alert->id; ?>"
                                        data-dj-name="<?php echo esc_attr($alert->dj_name); ?>"
                                        data-alert-type="<?php echo esc_attr($alert_type); ?>">
                                    <?php echo esc_html__('Review', 'musicandlights'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Review Alert Modal -->
<div id="review-alert-modal" style="display: none;">
    <div style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 99998;"></div>
    <div style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; max-width: 500px; width: 90%; z-index: 99999; box-shadow: 0 5px 25px rgba(0,0,0,0.2);">
        <h2 style="margin-top: 0;"><?php echo esc_html__('Review Alert', 'musicandlights'); ?></h2>
        
        <form method="post" id="review-alert-form">
            <?php wp_nonce_field('safeguards_action_nonce'); ?>
            <input type="hidden" name="alert_id" id="modal-alert-id">
            
            <p><strong><?php echo esc_html__('DJ:', 'musicandlights'); ?></strong> <span id="modal-dj-name"></span></p>
            <p><strong><?php echo esc_html__('Alert Type:', 'musicandlights'); ?></strong> <span id="modal-alert-type"></span></p>
            
            <div style="margin: 20px 0;">
                <label for="modal-action"><?php echo esc_html__('Action:', 'musicandlights'); ?></label>
                <select name="action" id="modal-action" style="width: 100%;">
                    <option value="resolve"><?php echo esc_html__('Mark as Resolved', 'musicandlights'); ?></option>
                    <option value="escalate"><?php echo esc_html__('Escalate to High Priority', 'musicandlights'); ?></option>
                    <option value="dismiss"><?php echo esc_html__('Dismiss (False Positive)', 'musicandlights'); ?></option>
                </select>
            </div>
            
            <div style="margin: 20px 0;">
                <label for="modal-notes"><?php echo esc_html__('Notes:', 'musicandlights'); ?></label>
                <textarea name="notes" id="modal-notes" rows="4" style="width: 100%;"></textarea>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="button" onclick="closeReviewModal()">
                    <?php echo esc_html__('Cancel', 'musicandlights'); ?>
                </button>
                <button type="submit" class="button button-primary">
                    <?php echo esc_html__('Save', 'musicandlights'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showReviewModal(alertId, djName, alertType) {
    document.getElementById('modal-alert-id').value = alertId;
    document.getElementById('modal-dj-name').textContent = djName;
    document.getElementById('modal-alert-type').textContent = alertType;
    document.getElementById('review-alert-modal').style.display = 'block';
}

function closeReviewModal() {
    document.getElementById('review-alert-modal').style.display = 'none';
    document.getElementById('modal-notes').value = '';
}

jQuery(document).ready(function($) {
    $('.review-alert').on('click', function() {
        const alertId = $(this).data('alert-id');
        const djName = $(this).data('dj-name');
        const alertType = $(this).data('alert-type');
        showReviewModal(alertId, djName, alertType);
    });
    
    // Close modal on background click
    $('#review-alert-modal > div:first-child').on('click', closeReviewModal);
});
</script>