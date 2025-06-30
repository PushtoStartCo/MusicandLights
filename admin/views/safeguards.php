<?php
/**
 * Admin Safeguards View Template
 * 
 * Safeguards monitoring and alert management for Music & Lights admin
 * 
 * @package MusicAndLights
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get filter parameters
$severity_filter = isset($_GET['severity']) ? sanitize_text_field($_GET['severity']) : '';
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$alert_type_filter = isset($_GET['alert_type']) ? sanitize_text_field($_GET['alert_type']) : '';
$dj_filter = isset($_GET['dj_id']) ? intval($_GET['dj_id']) : 0;
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';

// Get safeguards instance
$safeguards = ML_Safeguards::get_instance();

// Get alerts data
global $wpdb;

// Build WHERE clause
$where_conditions = array();
$params = array();

if ($severity_filter) {
    $where_conditions[] = "severity = %s";
    $params[] = $severity_filter;
}

if ($status_filter) {
    $where_conditions[] = "status = %s";
    $params[] = $status_filter;
}

if ($alert_type_filter) {
    $where_conditions[] = "alert_type = %s";
    $params[] = $alert_type_filter;
}

if ($dj_filter) {
    $where_conditions[] = "JSON_EXTRACT(alert_data, '$.dj_id') = %d";
    $params[] = $dj_filter;
}

if ($date_from) {
    $where_conditions[] = "DATE(created_at) >= %s";
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "DATE(created_at) <= %s";
    $params[] = $date_to;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get alerts
$query = "SELECT sl.*, d.first_name as dj_first_name, d.last_name as dj_last_name, d.stage_name
          FROM {$wpdb->prefix}ml_safeguards_log sl
          LEFT JOIN {$wpdb->prefix}ml_djs d ON JSON_EXTRACT(sl.alert_data, '$.dj_id') = d.id
          $where_clause
          ORDER BY sl.created_at DESC
          LIMIT 50";

if (!empty($params)) {
    $alerts = $wpdb->get_results($wpdb->prepare($query, $params));
} else {
    $alerts = $wpdb->get_results($query);
}

// Get all DJs for filter dropdown
$all_djs = $wpdb->get_results("SELECT id, first_name, last_name, stage_name FROM {$wpdb->prefix}ml_djs WHERE status = 'active' ORDER BY first_name");

// Get safeguards statistics
$stats = $safeguards->get_statistics(30);

// Get alert types for filter
$alert_types = $wpdb->get_col(
    "SELECT DISTINCT alert_type FROM {$wpdb->prefix}ml_safeguards_log ORDER BY alert_type"
);
?>

<div class="wrap ml-admin-wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-warning"></span>
        Safeguards Monitoring
    </h1>
    
    <a href="#" onclick="generateSafeguardsReport()" class="page-title-action">Generate Report</a>
    <a href="#" onclick="exportAlerts()" class="page-title-action">Export Alerts</a>
    
    <hr class="wp-header-end">
    
    <!-- Safeguards Overview -->
    <div class="ml-safeguards-overview">
        
        <!-- Alert Statistics -->
        <div class="ml-alert-stats">
            <div class="ml-stat-card ml-stat-total">
                <div class="ml-stat-icon">
                    <span class="dashicons dashicons-warning"></span>
                </div>
                <div class="ml-stat-content">
                    <div class="ml-stat-number"><?php echo intval($stats['total_alerts']); ?></div>
                    <div class="ml-stat-label">Total Alerts (30 days)</div>
                    <div class="ml-stat-detail">Last month</div>
                </div>
            </div>
            
            <div class="ml-stat-card ml-stat-high">
                <div class="ml-stat-icon">
                    <span class="dashicons dashicons-dismiss"></span>
                </div>
                <div class="ml-stat-content">
                    <div class="ml-stat-number"><?php echo intval($stats['by_severity']['high'] ?? 0); ?></div>
                    <div class="ml-stat-label">High Priority</div>
                    <div class="ml-stat-detail">Needs attention</div>
                </div>
            </div>
            
            <div class="ml-stat-card ml-stat-medium">
                <div class="ml-stat-icon">
                    <span class="dashicons dashicons-flag"></span>
                </div>
                <div class="ml-stat-content">
                    <div class="ml-stat-number"><?php echo intval($stats['by_severity']['medium'] ?? 0); ?></div>
                    <div class="ml-stat-label">Medium Priority</div>
                    <div class="ml-stat-detail">Monitor closely</div>
                </div>
            </div>
            
            <div class="ml-stat-card ml-stat-low">
                <div class="ml-stat-icon">
                    <span class="dashicons dashicons-info"></span>
                </div>
                <div class="ml-stat-content">
                    <div class="ml-stat-number"><?php echo intval($stats['by_severity']['low'] ?? 0); ?></div>
                    <div class="ml-stat-label">Low Priority</div>
                    <div class="ml-stat-detail">Informational</div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="ml-safeguards-actions">
            <div class="ml-action-buttons">
                <button type="button" class="button button-primary" onclick="runManualCheck()">
                    <span class="dashicons dashicons-search"></span>
                    Run Manual Check
                </button>
                <button type="button" class="button button-secondary" onclick="toggleMonitoring()">
                    <span class="dashicons dashicons-controls-play"></span>
                    <span id="monitoring-status">Enable Monitoring</span>
                </button>
                <button type="button" class="button" onclick="openSafeguardsSettings()">
                    <span class="dashicons dashicons-admin-settings"></span>
                    Settings
                </button>
            </div>
            
            <div class="ml-monitoring-status">
                <div class="ml-status-indicator" id="safeguards-status">
                    <span class="ml-status-dot status-active"></span>
                    <span class="ml-status-text">Monitoring Active</span>
                </div>
                <div class="ml-last-check">
                    Last check: <span id="last-check-time">2 minutes ago</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Top Alert Types -->
    <div class="ml-top-alerts">
        <h3>Most Common Alert Types</h3>
        <div class="ml-alert-types-grid">
            <?php if (!empty($stats['top_alert_types'])): ?>
                <?php foreach ($stats['top_alert_types'] as $alert_type): ?>
                    <div class="ml-alert-type-card">
                        <div class="ml-alert-type-name"><?php echo esc_html(ucwords(str_replace('_', ' ', $alert_type->alert_type))); ?></div>
                        <div class="ml-alert-type-count"><?php echo intval($alert_type->count); ?> alerts</div>
                        <div class="ml-alert-type-actions">
                            <button class="button button-small" onclick="filterByType('<?php echo esc_js($alert_type->alert_type); ?>')">View All</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="ml-no-data">No alert data available</div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="ml-filters">
        <form method="get" action="<?php echo admin_url('admin.php'); ?>">
            <input type="hidden" name="page" value="ml-safeguards">
            
            <div class="ml-filter-row">
                <select name="severity">
                    <option value="">All Severities</option>
                    <option value="high" <?php selected($severity_filter, 'high'); ?>>High</option>
                    <option value="medium" <?php selected($severity_filter, 'medium'); ?>>Medium</option>
                    <option value="low" <?php selected($severity_filter, 'low'); ?>>Low</option>
                </select>
                
                <select name="status">
                    <option value="">All Statuses</option>
                    <option value="open" <?php selected($status_filter, 'open'); ?>>Open</option>
                    <option value="resolved" <?php selected($status_filter, 'resolved'); ?>>Resolved</option>
                    <option value="dismissed" <?php selected($status_filter, 'dismissed'); ?>>Dismissed</option>
                </select>
                
                <select name="alert_type">
                    <option value="">All Types</option>
                    <?php foreach ($alert_types as $type): ?>
                        <option value="<?php echo esc_attr($type); ?>" <?php selected($alert_type_filter, $type); ?>>
                            <?php echo esc_html(ucwords(str_replace('_', ' ', $type))); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select name="dj_id">
                    <option value="">All DJs</option>
                    <?php foreach ($all_djs as $dj): ?>
                        <option value="<?php echo esc_attr($dj->id); ?>" <?php selected($dj_filter, $dj->id); ?>>
                            <?php echo esc_html($dj->stage_name ?: $dj->first_name . ' ' . $dj->last_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" placeholder="From Date">
                <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" placeholder="To Date">
                
                <button type="submit" class="button">Filter</button>
                <a href="<?php echo admin_url('admin.php?page=ml-safeguards'); ?>" class="button">Clear</a>
            </div>
        </form>
    </div>
    
    <!-- Bulk Actions -->
    <div class="ml-bulk-actions-bar">
        <div class="ml-bulk-actions">
            <select id="bulk-action">
                <option value="">Bulk Actions</option>
                <option value="mark-resolved">Mark as Resolved</option>
                <option value="mark-dismissed">Mark as Dismissed</option>
                <option value="export-selected">Export Selected</option>
                <option value="delete-selected">Delete Selected</option>
            </select>
            <button type="button" class="button" onclick="applyBulkAction()">Apply</button>
        </div>
        
        <div class="ml-filter-buttons">
            <button type="button" class="button <?php echo ($status_filter === 'open') ? 'button-primary' : ''; ?>" 
                    onclick="filterByStatus('open')">
                Open Alerts
            </button>
            <button type="button" class="button <?php echo ($severity_filter === 'high') ? 'button-primary' : ''; ?>" 
                    onclick="filterBySeverity('high')">
                High Priority
            </button>
            <button type="button" class="button <?php echo ($status_filter === 'resolved') ? 'button-primary' : ''; ?>" 
                    onclick="filterByStatus('resolved')">
                Resolved
            </button>
        </div>
    </div>
    
    <!-- Alerts Table -->
    <div class="ml-table-container">
        <table class="wp-list-table widefat fixed striped ml-alerts-table">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-cb check-column">
                        <input type="checkbox" id="select-all-alerts">
                    </th>
                    <th scope="col" class="manage-column">Alert Details</th>
                    <th scope="col" class="manage-column">DJ/Booking</th>
                    <th scope="col" class="manage-column">Alert Data</th>
                    <th scope="col" class="manage-column">Severity</th>
                    <th scope="col" class="manage-column">Date/Time</th>
                    <th scope="col" class="manage-column">Status</th>
                    <th scope="col" class="manage-column">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($alerts)): ?>
                    <?php foreach ($alerts as $alert): ?>
                        <?php
                        $alert_data = json_decode($alert->alert_data, true);
                        $dj_name = '';
                        if ($alert->dj_first_name) {
                            $dj_name = $alert->stage_name ?: $alert->dj_first_name . ' ' . $alert->dj_last_name;
                        }
                        ?>
                        <tr class="ml-alert-row severity-<?php echo esc_attr($alert->severity); ?>">
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="alert_ids[]" value="<?php echo esc_attr($alert->id); ?>">
                            </th>
                            <td>
                                <div class="ml-alert-main">
                                    <strong><?php echo esc_html(ucwords(str_replace('_', ' ', $alert->alert_type))); ?></strong>
                                    <?php if (isset($alert_data['message'])): ?>
                                        <div class="ml-alert-message"><?php echo esc_html($alert_data['message']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($dj_name): ?>
                                    <strong><?php echo esc_html($dj_name); ?></strong><br>
                                <?php endif; ?>
                                <?php if ($alert->booking_id): ?>
                                    <small>Booking #<?php echo esc_html($alert->booking_id); ?></small>
                                <?php else: ?>
                                    <small>System alert</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="ml-alert-data">
                                    <?php
                                    foreach ($alert_data as $key => $value) {
                                        if ($key !== 'message' && $key !== 'dj_id') {
                                            echo '<div class="ml-data-item">';
                                            echo '<span class="ml-data-key">' . esc_html(ucwords(str_replace('_', ' ', $key))) . ':</span> ';
                                            echo '<span class="ml-data-value">' . esc_html($value) . '</span>';
                                            echo '</div>';
                                        }
                                    }
                                    ?>
                                </div>
                            </td>
                            <td>
                                <span class="ml-severity-badge severity-<?php echo esc_attr($alert->severity); ?>">
                                    <?php echo esc_html(ucfirst($alert->severity)); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo esc_html(date('jS M Y', strtotime($alert->created_at))); ?><br>
                                <small><?php echo esc_html(date('H:i:s', strtotime($alert->created_at))); ?></small>
                            </td>
                            <td>
                                <span class="ml-status-badge status-<?php echo esc_attr($alert->status); ?>">
                                    <?php echo esc_html(ucfirst($alert->status)); ?>
                                </span>
                                <?php if ($alert->resolved_at): ?>
                                    <br><small>Resolved: <?php echo date('jS M Y', strtotime($alert->resolved_at)); ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="ml-actions">
                                <?php if ($alert->status === 'open'): ?>
                                    <button class="button button-small button-primary" 
                                            onclick="resolveAlert(<?php echo $alert->id; ?>)" 
                                            title="Resolve Alert">
                                        Resolve
                                    </button>
                                    <button class="button button-small" 
                                            onclick="dismissAlert(<?php echo $alert->id; ?>)" 
                                            title="Dismiss Alert">
                                        Dismiss
                                    </button>
                                <?php endif; ?>
                                
                                <div class="ml-action-dropdown">
                                    <button class="button button-small ml-dropdown-toggle">More ▼</button>
                                    <div class="ml-dropdown-menu">
                                        <a href="#" onclick="viewAlertDetails(<?php echo $alert->id; ?>)">View Details</a>
                                        <?php if ($alert->booking_id): ?>
                                            <a href="<?php echo admin_url('admin.php?page=ml-bookings&action=view&booking_id=' . $alert->booking_id); ?>">View Booking</a>
                                        <?php endif; ?>
                                        <?php if ($dj_name): ?>
                                            <a href="<?php echo admin_url('admin.php?page=ml-djs&action=view&dj_id=' . ($alert_data['dj_id'] ?? '')); ?>">View DJ</a>
                                        <?php endif; ?>
                                        <a href="#" onclick="addNote(<?php echo $alert->id; ?>)">Add Note</a>
                                        <a href="#" onclick="createRule(<?php echo $alert->id; ?>)">Create Rule</a>
                                        <?php if ($alert->status !== 'open'): ?>
                                            <a href="#" onclick="reopenAlert(<?php echo $alert->id; ?>)">Reopen Alert</a>
                                        <?php endif; ?>
                                        <a href="#" onclick="deleteAlert(<?php echo $alert->id; ?>)" class="ml-danger">Delete</a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="ml-no-data">
                            <div class="ml-empty-state">
                                <span class="dashicons dashicons-shield-alt"></span>
                                <h3>No alerts found</h3>
                                <p>Great! No safeguards alerts match your current filters.</p>
                                <?php if (!empty($where_conditions)): ?>
                                    <p><a href="<?php echo admin_url('admin.php?page=ml-safeguards'); ?>">View all alerts</a></p>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Monitoring Rules -->
    <div class="ml-monitoring-rules">
        <h3>Monitoring Rules</h3>
        <div class="ml-rules-container">
            <div class="ml-rule-card">
                <h4>Duplicate Customer Detection</h4>
                <p>Monitors for customers making multiple bookings with different DJs within 30 days.</p>
                <div class="ml-rule-status">
                    <span class="ml-status-indicator active">Active</span>
                    <button class="button button-small" onclick="editRule('duplicate_customer')">Configure</button>
                </div>
            </div>
            
            <div class="ml-rule-card">
                <h4>Commission Discrepancy</h4>
                <p>Detects when commission calculations don't match expected amounts.</p>
                <div class="ml-rule-status">
                    <span class="ml-status-indicator active">Active</span>
                    <button class="button button-small" onclick="editRule('commission_discrepancy')">Configure</button>
                </div>
            </div>
            
            <div class="ml-rule-card">
                <h4>High Cancellation Rate</h4>
                <p>Alerts when a DJ has an unusually high cancellation rate (>30%).</p>
                <div class="ml-rule-status">
                    <span class="ml-status-indicator active">Active</span>
                    <button class="button button-small" onclick="editRule('high_cancellation')">Configure</button>
                </div>
            </div>
            
            <div class="ml-rule-card">
                <h4>Excessive Contact Access</h4>
                <p>Monitors DJ access to customer contact information for unusual patterns.</p>
                <div class="ml-rule-status">
                    <span class="ml-status-indicator active">Active</span>
                    <button class="button button-small" onclick="editRule('contact_access')">Configure</button>
                </div>
            </div>
        </div>
        
        <div class="ml-add-rule">
            <button class="button button-secondary" onclick="addCustomRule()">Add Custom Rule</button>
        </div>
    </div>
    
    <!-- Alert Trends Chart -->
    <div class="ml-alert-trends">
        <h3>Alert Trends</h3>
        <div class="ml-chart-container">
            <canvas id="alerts-trend-chart" width="800" height="300"></canvas>
        </div>
    </div>
    
</div>

<!-- Alert Details Modal -->
<div id="ml-alert-details-modal" class="ml-modal" style="display: none;">
    <div class="ml-modal-content ml-modal-large">
        <div class="ml-modal-header">
            <h3>Alert Details</h3>
            <span class="ml-modal-close">&times;</span>
        </div>
        <div class="ml-modal-body">
            <div id="alert-details-content">
                <!-- Details will be loaded via AJAX -->
            </div>
        </div>
        <div class="ml-modal-footer">
            <button type="button" class="button" onclick="closeModal()">Close</button>
            <button type="button" class="button button-primary" onclick="resolveCurrentAlert()">Resolve Alert</button>
        </div>
    </div>
</div>

<!-- Resolve Alert Modal -->
<div id="ml-resolve-alert-modal" class="ml-modal" style="display: none;">
    <div class="ml-modal-content">
        <div class="ml-modal-header">
            <h3>Resolve Alert</h3>
            <span class="ml-modal-close">&times;</span>
        </div>
        <div class="ml-modal-body">
            <form id="ml-resolve-alert-form">
                <div class="ml-form-field">
                    <label for="resolution_notes">Resolution Notes</label>
                    <textarea id="resolution_notes" name="resolution_notes" rows="4" 
                              placeholder="Describe how this alert was resolved..." required></textarea>
                </div>
                <div class="ml-form-field">
                    <label for="resolution_action">Action Taken</label>
                    <select id="resolution_action" name="resolution_action" required>
                        <option value="">Select Action</option>
                        <option value="investigated">Investigated - No Action Needed</option>
                        <option value="contacted_dj">Contacted DJ</option>
                        <option value="contacted_customer">Contacted Customer</option>
                        <option value="adjusted_commission">Adjusted Commission</option>
                        <option value="updated_policies">Updated Policies</option>
                        <option value="referred_management">Referred to Management</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="ml-form-field">
                    <label>
                        <input type="checkbox" name="prevent_similar" value="1">
                        Create rule to prevent similar alerts
                    </label>
                </div>
                <input type="hidden" id="resolve_alert_id" name="alert_id">
            </form>
        </div>
        <div class="ml-modal-footer">
            <button type="button" class="button" onclick="closeModal()">Cancel</button>
            <button type="submit" form="ml-resolve-alert-form" class="button button-primary">Resolve Alert</button>
        </div>
    </div>
</div>

<!-- Add Note Modal -->
<div id="ml-add-note-modal" class="ml-modal" style="display: none;">
    <div class="ml-modal-content">
        <div class="ml-modal-header">
            <h3>Add Note to Alert</h3>
            <span class="ml-modal-close">&times;</span>
        </div>
        <div class="ml-modal-body">
            <form id="ml-add-note-form">
                <div class="ml-form-field">
                    <label for="note_content">Note</label>
                    <textarea id="note_content" name="note_content" rows="4" 
                              placeholder="Add your note about this alert..." required></textarea>
                </div>
                <div class="ml-form-field">
                    <label for="note_type">Note Type</label>
                    <select id="note_type" name="note_type">
                        <option value="investigation">Investigation</option>
                        <option value="followup">Follow-up Required</option>
                        <option value="escalation">Escalation</option>
                        <option value="resolution">Resolution Attempt</option>
                        <option value="general">General Note</option>
                    </select>
                </div>
                <input type="hidden" id="note_alert_id" name="alert_id">
            </form>
        </div>
        <div class="ml-modal-footer">
            <button type="button" class="button" onclick="closeModal()">Cancel</button>
            <button type="submit" form="ml-add-note-form" class="button button-primary">Add Note</button>
        </div>
    </div>
</div>

<!-- Custom Rule Modal -->
<div id="ml-custom-rule-modal" class="ml-modal" style="display: none;">
    <div class="ml-modal-content ml-modal-large">
        <div class="ml-modal-header">
            <h3>Create Custom Monitoring Rule</h3>
            <span class="ml-modal-close">&times;</span>
        </div>
        <div class="ml-modal-body">
            <form id="ml-custom-rule-form">
                <div class="ml-form-section">
                    <h4>Rule Configuration</h4>
                    <div class="ml-form-grid">
                        <div class="ml-form-field">
                            <label for="rule_name">Rule Name</label>
                            <input type="text" id="rule_name" name="rule_name" required>
                        </div>
                        <div class="ml-form-field">
                            <label for="rule_severity">Severity Level</label>
                            <select id="rule_severity" name="rule_severity" required>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                    </div>
                    <div class="ml-form-field">
                        <label for="rule_description">Description</label>
                        <textarea id="rule_description" name="rule_description" rows="3" 
                                  placeholder="Describe what this rule monitors for..."></textarea>
                    </div>
                </div>
                
                <div class="ml-form-section">
                    <h4>Trigger Conditions</h4>
                    <div class="ml-conditions-builder">
                        <div class="ml-condition-row">
                            <select name="conditions[0][field]" class="condition-field">
                                <option value="">Select Field</option>
                                <option value="booking_count">Booking Count</option>
                                <option value="cancellation_rate">Cancellation Rate</option>
                                <option value="commission_amount">Commission Amount</option>
                                <option value="customer_email">Customer Email</option>
                                <option value="booking_frequency">Booking Frequency</option>
                            </select>
                            <select name="conditions[0][operator]" class="condition-operator">
                                <option value="equals">Equals</option>
                                <option value="greater_than">Greater Than</option>
                                <option value="less_than">Less Than</option>
                                <option value="contains">Contains</option>
                                <option value="not_contains">Does Not Contain</option>
                            </select>
                            <input type="text" name="conditions[0][value]" class="condition-value" placeholder="Value">
                            <button type="button" class="button button-small" onclick="removeCondition(0)">Remove</button>
                        </div>
                    </div>
                    <button type="button" class="button button-secondary" onclick="addCondition()">Add Condition</button>
                </div>
                
                <div class="ml-form-section">
                    <h4>Actions</h4>
                    <div class="ml-form-field">
                        <label>
                            <input type="checkbox" name="actions[email_alert]" value="1" checked>
                            Send email alert
                        </label>
                    </div>
                    <div class="ml-form-field">
                        <label>
                            <input type="checkbox" name="actions[dashboard_alert]" value="1" checked>
                            Show in dashboard
                        </label>
                    </div>
                    <div class="ml-form-field">
                        <label>
                            <input type="checkbox" name="actions[auto_suspend]" value="1">
                            Auto-suspend DJ (high severity only)
                        </label>
                    </div>
                </div>
            </form>
        </div>
        <div class="ml-modal-footer">
            <button type="button" class="button" onclick="closeModal()">Cancel</button>
            <button type="submit" form="ml-custom-rule-form" class="button button-primary">Create Rule</button>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    
    // Load alert trends chart
    loadAlertTrendsChart();
    
    // Update monitoring status
    updateMonitoringStatus();
    
    // Select all checkbox
    $('#select-all-alerts').on('change', function() {
        $('input[name="alert_ids[]"]').prop('checked', this.checked);
    });
    
    // Resolve alert form submission
    $('#ml-resolve-alert-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'ml_resolve_alert');
        formData.append('nonce', ml_admin.nonce);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('Alert resolved successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            }
        });
    });
    
    // Add note form submission
    $('#ml-add-note-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'ml_add_alert_note');
        formData.append('nonce', ml_admin.nonce);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('Note added successfully!');
                    closeModal();
                    // Refresh alert details if modal is open
                    if ($('#ml-alert-details-modal').is(':visible')) {
                        viewAlertDetails($('#note_alert_id').val());
                    }
                } else {
                    alert('Error: ' + response.data);
                }
            }
        });
    });
    
    // Custom rule form submission
    $('#ml-custom-rule-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'ml_create_custom_rule');
        formData.append('nonce', ml_admin.nonce);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('Custom rule created successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            }
        });
    });
    
    // Dropdown menus
    $('.ml-dropdown-toggle').on('click', function(e) {
        e.preventDefault();
        $('.ml-dropdown-menu').not($(this).next()).hide();
        $(this).next('.ml-dropdown-menu').toggle();
    });
    
    // Close dropdowns when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.ml-action-dropdown').length) {
            $('.ml-dropdown-menu').hide();
        }
    });
    
    function loadAlertTrendsChart() {
        $.post(ajaxurl, {
            action: 'ml_get_alert_trends_data',
            nonce: ml_admin.nonce
        }, function(response) {
            if (response.success) {
                renderAlertTrendsChart(response.data);
            }
        });
    }
    
    function renderAlertTrendsChart(data) {
        const ctx = document.getElementById('alerts-trend-chart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'High Priority',
                    data: data.high,
                    borderColor: '#e74c3c',
                    backgroundColor: 'rgba(231, 76, 60, 0.1)',
                    tension: 0.1
                }, {
                    label: 'Medium Priority',
                    data: data.medium,
                    borderColor: '#f39c12',
                    backgroundColor: 'rgba(243, 156, 18, 0.1)',
                    tension: 0.1
                }, {
                    label: 'Low Priority',
                    data: data.low,
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        stepSize: 1
                    }
                },
                plugins: {
                    legend: {
                        position: 'top'
                    }
                }
            }
        });
    }
    
    function updateMonitoringStatus() {
        $.post(ajaxurl, {
            action: 'ml_get_monitoring_status',
            nonce: ml_admin.nonce
        }, function(response) {
            if (response.success) {
                const status = response.data;
                $('#safeguards-status .ml-status-dot').attr('class', 'ml-status-dot status-' + (status.active ? 'active' : 'inactive'));
                $('#safeguards-status .ml-status-text').text(status.active ? 'Monitoring Active' : 'Monitoring Inactive');
                $('#last-check-time').text(status.last_check);
                $('#monitoring-status').text(status.active ? 'Disable Monitoring' : 'Enable Monitoring');
            }
        });
    }
    
    // Auto-refresh monitoring status every 30 seconds
    setInterval(updateMonitoringStatus, 30000);
    
});

// Safeguards action functions
function resolveAlert(alertId) {
    jQuery('#resolve_alert_id').val(alertId);
    jQuery('#ml-resolve-alert-modal').show();
}

function dismissAlert(alertId) {
    if (confirm('Are you sure you want to dismiss this alert? It will be marked as resolved without action.')) {
        jQuery.post(ajaxurl, {
            action: 'ml_dismiss_alert',
            alert_id: alertId,
            nonce: ml_admin.nonce
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + response.data);
            }
        });
    }
}

function viewAlertDetails(alertId) {
    jQuery('#ml-alert-details-modal').show();
    
    jQuery.post(ajaxurl, {
        action: 'ml_get_alert_details',
        alert_id: alertId,
        nonce: ml_admin.nonce
    }, function(response) {
        if (response.success) {
            jQuery('#alert-details-content').html(response.data);
        }
    });
}

function addNote(alertId) {
    jQuery('#note_alert_id').val(alertId);
    jQuery('#ml-add-note-modal').show();
}

function reopenAlert(alertId) {
    if (confirm('Reopen this alert for further investigation?')) {
        jQuery.post(ajaxurl, {
            action: 'ml_reopen_alert',
            alert_id: alertId,
            nonce: ml_admin.nonce
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + response.data);
            }
        });
    }
}

function deleteAlert(alertId) {
    if (confirm('Are you sure you want to permanently delete this alert? This action cannot be undone.')) {
        jQuery.post(ajaxurl, {
            action: 'ml_delete_alert',
            alert_id: alertId,
            nonce: ml_admin.nonce
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + response.data);
            }
        });
    }
}

function runManualCheck() {
    const button = jQuery('button:contains("Run Manual Check")');
    button.prop('disabled', true).html('<span class="dashicons dashicons-update-alt ml-spin"></span> Running Check...');
    
    jQuery.post(ajaxurl, {
        action: 'ml_run_manual_safeguards_check',
        nonce: ml_admin.nonce
    }, function(response) {
        button.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Run Manual Check');
        
        if (response.success) {
            alert('Manual check completed. ' + response.data.alerts_created + ' new alerts generated.');
            location.reload();
        } else {
            alert('Error: ' + response.data);
        }
    });
}

function toggleMonitoring() {
    jQuery.post(ajaxurl, {
        action: 'ml_toggle_monitoring',
        nonce: ml_admin.nonce
    }, function(response) {
        if (response.success) {
            location.reload();
        } else {
            alert('Error: ' + response.data);
        }
    });
}

function openSafeguardsSettings() {
    window.location.href = '<?php echo admin_url("admin.php?page=ml-settings&tab=safeguards"); ?>';
}

function generateSafeguardsReport() {
    const params = new URLSearchParams(window.location.search);
    params.set('action', 'ml_generate_safeguards_report');
    params.set('nonce', ml_admin.nonce);
    
    window.location.href = ajaxurl + '?' + params.toString();
}

function exportAlerts() {
    const params = new URLSearchParams(window.location.search);
    params.set('action', 'ml_export_alerts');
    params.set('nonce', ml_admin.nonce);
    
    window.location.href = ajaxurl + '?' + params.toString();
}

function applyBulkAction() {
    const action = jQuery('#bulk-action').val();
    const selected = jQuery('input[name="alert_ids[]"]:checked');
    
    if (!action) {
        alert('Please select an action.');
        return;
    }
    
    if (selected.length === 0) {
        alert('Please select alerts.');
        return;
    }
    
    const alertIds = selected.map(function() {
        return this.value;
    }).get();
    
    jQuery.post(ajaxurl, {
        action: 'ml_bulk_alert_action',
        bulk_action: action,
        alert_ids: alertIds,
        nonce: ml_admin.nonce
    }, function(response) {
        if (response.success) {
            alert(response.data);
            location.reload();
        } else {
            alert('Error: ' + response.data);
        }
    });
}

function filterByStatus(status) {
    const url = new URL(window.location.href);
    url.searchParams.set('status', status);
    window.location.href = url.toString();
}

function filterBySeverity(severity) {
    const url = new URL(window.location.href);
    url.searchParams.set('severity', severity);
    window.location.href = url.toString();
}

function filterByType(type) {
    const url = new URL(window.location.href);
    url.searchParams.set('alert_type', type);
    window.location.href = url.toString();
}

function editRule(ruleType) {
    // Open rule configuration interface
    window.location.href = '<?php echo admin_url("admin.php?page=ml-settings&tab=safeguards"); ?>&rule=' + ruleType;
}

function addCustomRule() {
    jQuery('#ml-custom-rule-modal').show();
}

function createRule(alertId) {
    // Pre-populate custom rule form based on alert
    jQuery.post(ajaxurl, {
        action: 'ml_get_alert_rule_template',
        alert_id: alertId,
        nonce: ml_admin.nonce
    }, function(response) {
        if (response.success) {
            // Populate form with suggested rule
            addCustomRule();
            // Set form values from response
        }
    });
}

function addCondition() {
    const conditionsBuilder = jQuery('.ml-conditions-builder');
    const conditionCount = conditionsBuilder.find('.ml-condition-row').length;
    
    const newCondition = `
        <div class="ml-condition-row">
            <select name="conditions[${conditionCount}][field]" class="condition-field">
                <option value="">Select Field</option>
                <option value="booking_count">Booking Count</option>
                <option value="cancellation_rate">Cancellation Rate</option>
                <option value="commission_amount">Commission Amount</option>
                <option value="customer_email">Customer Email</option>
                <option value="booking_frequency">Booking Frequency</option>
            </select>
            <select name="conditions[${conditionCount}][operator]" class="condition-operator">
                <option value="equals">Equals</option>
                <option value="greater_than">Greater Than</option>
                <option value="less_than">Less Than</option>
                <option value="contains">Contains</option>
                <option value="not_contains">Does Not Contain</option>
            </select>
            <input type="text" name="conditions[${conditionCount}][value]" class="condition-value" placeholder="Value">
            <button type="button" class="button button-small" onclick="removeCondition(${conditionCount})">Remove</button>
        </div>
    `;
    
    conditionsBuilder.append(newCondition);
}

function removeCondition(index) {
    jQuery('.ml-condition-row').eq(index).remove();
}

function resolveCurrentAlert() {
    const alertId = jQuery('#alert-details-content').data('alert-id');
    if (alertId) {
        resolveAlert(alertId);
    }
}

function closeModal() {
    jQuery('.ml-modal').hide();
}
</script>">