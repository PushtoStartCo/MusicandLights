<?php
/**
 * Admin Commissions View Template
 * 
 * Commission tracking and payment management for Music & Lights admin
 * 
 * @package MusicAndLights
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$dj_filter = isset($_GET['dj_id']) ? intval($_GET['dj_id']) : 0;
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';

// Get commissions data
global $wpdb;

// Build WHERE clause
$where_conditions = array();
$params = array();

if ($status_filter) {
    $where_conditions[] = "c.status = %s";
    $params[] = $status_filter;
}

if ($dj_filter) {
    $where_conditions[] = "c.dj_id = %d";
    $params[] = $dj_filter;
}

if ($date_from) {
    $where_conditions[] = "b.event_date >= %s";
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "b.event_date <= %s";
    $params[] = $date_to;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get commissions
$query = "SELECT c.*, b.event_date, b.first_name, b.last_name, b.venue_name, b.total_cost,
          d.first_name as dj_first_name, d.last_name as dj_last_name, d.stage_name
          FROM {$wpdb->prefix}ml_commissions c
          JOIN {$wpdb->prefix}ml_bookings b ON c.booking_id = b.id
          JOIN {$wpdb->prefix}ml_djs d ON c.dj_id = d.id
          $where_clause
          ORDER BY c.due_date DESC, c.created_at DESC";

if (!empty($params)) {
    $commissions = $wpdb->get_results($wpdb->prepare($query, $params));
} else {
    $commissions = $wpdb->get_results($query);
}

// Get all DJs for filter dropdown
$all_djs = $wpdb->get_results("SELECT id, first_name, last_name, stage_name FROM {$wpdb->prefix}ml_djs WHERE status = 'active' ORDER BY first_name");

// Get summary statistics
$stats = $wpdb->get_row(
    "SELECT 
    SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount,
    SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as paid_amount,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
    COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_count,
    SUM(CASE WHEN status = 'pending' AND due_date <= CURDATE() THEN amount ELSE 0 END) as overdue_amount,
    COUNT(CASE WHEN status = 'pending' AND due_date <= CURDATE() THEN 1 END) as overdue_count
    FROM {$wpdb->prefix}ml_commissions"
);
?>

<div class="wrap ml-admin-wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-money-alt"></span>
        Commission Management
    </h1>
    
    <a href="#" onclick="processMultiplePayments()" class="page-title-action">Process Payments</a>
    <a href="#" onclick="exportCommissions()" class="page-title-action">Export Data</a>
    
    <hr class="wp-header-end">
    
    <!-- Commission Statistics -->
    <div class="ml-commission-stats">
        <div class="ml-stat-card ml-stat-pending">
            <div class="ml-stat-icon">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div class="ml-stat-content">
                <div class="ml-stat-number">£<?php echo number_format($stats->pending_amount, 2); ?></div>
                <div class="ml-stat-label">Pending Commissions</div>
                <div class="ml-stat-detail"><?php echo intval($stats->pending_count); ?> payments</div>
            </div>
        </div>
        
        <div class="ml-stat-card ml-stat-paid">
            <div class="ml-stat-icon">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="ml-stat-content">
                <div class="ml-stat-number">£<?php echo number_format($stats->paid_amount, 2); ?></div>
                <div class="ml-stat-label">Paid This Month</div>
                <div class="ml-stat-detail"><?php echo intval($stats->paid_count); ?> payments</div>
            </div>
        </div>
        
        <div class="ml-stat-card ml-stat-overdue">
            <div class="ml-stat-icon">
                <span class="dashicons dashicons-warning"></span>
            </div>
            <div class="ml-stat-content">
                <div class="ml-stat-number">£<?php echo number_format($stats->overdue_amount, 2); ?></div>
                <div class="ml-stat-label">Overdue Payments</div>
                <div class="ml-stat-detail"><?php echo intval($stats->overdue_count); ?> payments</div>
            </div>
        </div>
        
        <div class="ml-stat-card ml-stat-total">
            <div class="ml-stat-icon">
                <span class="dashicons dashicons-chart-line"></span>
            </div>
            <div class="ml-stat-content">
                <div class="ml-stat-number">£<?php echo number_format($stats->pending_amount + $stats->paid_amount, 2); ?></div>
                <div class="ml-stat-label">Total Commissions</div>
                <div class="ml-stat-detail">All time</div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="ml-filters">
        <form method="get" action="<?php echo admin_url('admin.php'); ?>">
            <input type="hidden" name="page" value="ml-commissions">
            
            <div class="ml-filter-row">
                <select name="status">
                    <option value="">All Statuses</option>
                    <option value="pending" <?php selected($status_filter, 'pending'); ?>>Pending</option>
                    <option value="paid" <?php selected($status_filter, 'paid'); ?>>Paid</option>
                    <option value="processing" <?php selected($status_filter, 'processing'); ?>>Processing</option>
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
                <a href="<?php echo admin_url('admin.php?page=ml-commissions'); ?>" class="button">Clear</a>
            </div>
        </form>
    </div>
    
    <!-- Quick Actions -->
    <div class="ml-quick-actions-bar">
        <div class="ml-bulk-actions">
            <select id="bulk-action">
                <option value="">Bulk Actions</option>
                <option value="mark-paid">Mark as Paid</option>
                <option value="mark-processing">Mark as Processing</option>
                <option value="send-reminder">Send Reminder</option>
                <option value="export-selected">Export Selected</option>
            </select>
            <button type="button" class="button" onclick="applyBulkAction()">Apply</button>
        </div>
        
        <div class="ml-filter-buttons">
            <button type="button" class="button <?php echo ($status_filter === 'pending') ? 'button-primary' : ''; ?>" 
                    onclick="filterByStatus('pending')">
                Pending (<?php echo intval($stats->pending_count); ?>)
            </button>
            <button type="button" class="button <?php echo ($status_filter === 'overdue') ? 'button-primary' : ''; ?>" 
                    onclick="filterByStatus('overdue')">
                Overdue (<?php echo intval($stats->overdue_count); ?>)
            </button>
            <button type="button" class="button <?php echo ($status_filter === 'paid') ? 'button-primary' : ''; ?>" 
                    onclick="filterByStatus('paid')">
                Paid (<?php echo intval($stats->paid_count); ?>)
            </button>
        </div>
    </div>
    
    <!-- Commissions Table -->
    <div class="ml-table-container">
        <table class="wp-list-table widefat fixed striped ml-commissions-table">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-cb check-column">
                        <input type="checkbox" id="select-all-commissions">
                    </th>
                    <th scope="col" class="manage-column">Commission ID</th>
                    <th scope="col" class="manage-column">DJ</th>
                    <th scope="col" class="manage-column">Booking Details</th>
                    <th scope="col" class="manage-column">Event Date</th>
                    <th scope="col" class="manage-column">Amount</th>
                    <th scope="col" class="manage-column">Due Date</th>
                    <th scope="col" class="manage-column">Status</th>
                    <th scope="col" class="manage-column">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($commissions)): ?>
                    <?php foreach ($commissions as $commission): ?>
                        <?php
                        $is_overdue = ($commission->status === 'pending' && strtotime($commission->due_date) < time());
                        $dj_name = $commission->stage_name ?: $commission->dj_first_name . ' ' . $commission->dj_last_name;
                        ?>
                        <tr class="<?php echo $is_overdue ? 'ml-overdue-row' : ''; ?>">
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="commission_ids[]" value="<?php echo esc_attr($commission->id); ?>">
                            </th>
                            <td>
                                <strong>#<?php echo esc_html($commission->id); ?></strong><br>
                                <small>Booking #<?php echo esc_html($commission->booking_id); ?></small>
                            </td>
                            <td>
                                <strong><?php echo esc_html($dj_name); ?></strong><br>
                                <small><?php echo esc_html($commission->dj_first_name . ' ' . $commission->dj_last_name); ?></small>
                            </td>
                            <td>
                                <strong><?php echo esc_html($commission->first_name . ' ' . $commission->last_name); ?></strong><br>
                                <small><?php echo esc_html($commission->venue_name); ?></small><br>
                                <small>Total: £<?php echo number_format($commission->total_cost, 2); ?></small>
                            </td>
                            <td>
                                <?php echo esc_html(date('jS M Y', strtotime($commission->event_date))); ?>
                                <?php if ($is_overdue): ?>
                                    <br><small class="ml-overdue-text">Overdue</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong>£<?php echo number_format($commission->amount, 2); ?></strong>
                                <br><small>Rate: <?php echo esc_html($commission->commission_rate); ?>%</small>
                            </td>
                            <td>
                                <?php echo esc_html(date('jS M Y', strtotime($commission->due_date))); ?>
                                <?php if ($is_overdue): ?>
                                    <br><small class="ml-overdue-text">
                                        <?php echo esc_html(human_time_diff(strtotime($commission->due_date), time())); ?> ago
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="ml-status-badge status-<?php echo esc_attr($commission->status); ?>">
                                    <?php echo esc_html(ucfirst($commission->status)); ?>
                                </span>
                                <?php if ($commission->payment_date): ?>
                                    <br><small>Paid: <?php echo date('jS M Y', strtotime($commission->payment_date)); ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="ml-actions">
                                <?php if ($commission->status === 'pending'): ?>
                                    <button class="button button-small button-primary" 
                                            onclick="markAsPaid(<?php echo $commission->id; ?>)" 
                                            title="Mark as Paid">
                                        Pay
                                    </button>
                                <?php endif; ?>
                                
                                <button class="button button-small" 
                                        onclick="viewCommissionDetails(<?php echo $commission->id; ?>)" 
                                        title="View Details">
                                    View
                                </button>
                                
                                <div class="ml-action-dropdown">
                                    <button class="button button-small ml-dropdown-toggle">More ▼</button>
                                    <div class="ml-dropdown-menu">
                                        <a href="<?php echo admin_url('admin.php?page=ml-bookings&action=view&booking_id=' . $commission->booking_id); ?>">View Booking</a>
                                        <a href="<?php echo admin_url('admin.php?page=ml-djs&action=view&dj_id=' . $commission->dj_id); ?>">View DJ</a>
                                        <a href="#" onclick="sendCommissionEmail(<?php echo $commission->id; ?>)">Send Email</a>
                                        <?php if ($commission->status === 'pending'): ?>
                                            <a href="#" onclick="editCommission(<?php echo $commission->id; ?>)">Edit Commission</a>
                                        <?php endif; ?>
                                        <a href="#" onclick="downloadInvoice(<?php echo $commission->id; ?>)">Download Invoice</a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="ml-no-data">
                            <div class="ml-empty-state">
                                <span class="dashicons dashicons-money-alt"></span>
                                <h3>No commissions found</h3>
                                <p>Commissions will appear here once bookings are completed.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Commission Charts -->
    <div class="ml-commission-charts">
        <div class="ml-chart-container">
            <h3>Commission Trends</h3>
            <canvas id="commission-trend-chart" width="400" height="200"></canvas>
        </div>
        
        <div class="ml-chart-container">
            <h3>DJ Commission Breakdown</h3>
            <canvas id="dj-commission-chart" width="400" height="200"></canvas>
        </div>
    </div>
    
</div>

<!-- Commission Details Modal -->
<div id="ml-commission-details-modal" class="ml-modal" style="display: none;">
    <div class="ml-modal-content ml-modal-large">
        <div class="ml-modal-header">
            <h3>Commission Details</h3>
            <span class="ml-modal-close">&times;</span>
        </div>
        <div class="ml-modal-body">
            <div id="commission-details-content">
                <!-- Details will be loaded via AJAX -->
            </div>
        </div>
        <div class="ml-modal-footer">
            <button type="button" class="button" onclick="closeModal()">Close</button>
            <button type="button" class="button button-primary" onclick="processPayment()">Process Payment</button>
        </div>
    </div>
</div>

<!-- Mark as Paid Modal -->
<div id="ml-mark-paid-modal" class="ml-modal" style="display: none;">
    <div class="ml-modal-content">
        <div class="ml-modal-header">
            <h3>Mark Commission as Paid</h3>
            <span class="ml-modal-close">&times;</span>
        </div>
        <div class="ml-modal-body">
            <form id="ml-mark-paid-form">
                <div class="ml-form-field">
                    <label for="payment_date">Payment Date</label>
                    <input type="date" id="payment_date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="ml-form-field">
                    <label for="payment_method">Payment Method</label>
                    <select id="payment_method" name="payment_method" required>
                        <option value="">Select Method</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="cash">Cash</option>
                        <option value="cheque">Cheque</option>
                        <option value="paypal">PayPal</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="ml-form-field">
                    <label for="payment_reference">Payment Reference</label>
                    <input type="text" id="payment_reference" name="payment_reference" placeholder="Transaction ID, cheque number, etc.">
                </div>
                <div class="ml-form-field">
                    <label for="payment_notes">Notes</label>
                    <textarea id="payment_notes" name="payment_notes" rows="3" placeholder="Additional notes about this payment..."></textarea>
                </div>
                <input type="hidden" id="commission_id" name="commission_id">
            </form>
        </div>
        <div class="ml-modal-footer">
            <button type="button" class="button" onclick="closeModal()">Cancel</button>
            <button type="submit" form="ml-mark-paid-form" class="button button-primary">Mark as Paid</button>
        </div>
    </div>
</div>

<!-- Multiple Payments Modal -->
<div id="ml-multiple-payments-modal" class="ml-modal" style="display: none;">
    <div class="ml-modal-content ml-modal-large">
        <div class="ml-modal-header">
            <h3>Process Multiple Payments</h3>
            <span class="ml-modal-close">&times;</span>
        </div>
        <div class="ml-modal-body">
            <div id="multiple-payments-content">
                <!-- Content will be loaded via AJAX -->
            </div>
        </div>
        <div class="ml-modal-footer">
            <button type="button" class="button" onclick="closeModal()">Cancel</button>
            <button type="button" class="button button-primary" onclick="confirmMultiplePayments()">Process Payments</button>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    
    // Load commission charts
    loadCommissionCharts();
    
    // Select all checkbox
    $('#select-all-commissions').on('change', function() {
        $('input[name="commission_ids[]"]').prop('checked', this.checked);
    });
    
    // Mark as paid form submission
    $('#ml-mark-paid-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'ml_mark_commission_paid');
        formData.append('nonce', ml_admin.nonce);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('Commission marked as paid successfully!');
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
    
    function loadCommissionCharts() {
        // Load commission trend chart
        $.post(ajaxurl, {
            action: 'ml_get_commission_chart_data',
            nonce: ml_admin.nonce
        }, function(response) {
            if (response.success) {
                renderCommissionCharts(response.data);
            }
        });
    }
    
    function renderCommissionCharts(data) {
        // Commission trend chart
        const trendCtx = document.getElementById('commission-trend-chart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: data.trend.labels,
                datasets: [{
                    label: 'Commission Paid',
                    data: data.trend.paid,
                    borderColor: '#27ae60',
                    backgroundColor: 'rgba(39, 174, 96, 0.1)',
                    tension: 0.1
                }, {
                    label: 'Commission Due',
                    data: data.trend.due,
                    borderColor: '#e74c3c',
                    backgroundColor: 'rgba(231, 76, 60, 0.1)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '£' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        
        // DJ commission breakdown chart
        const djCtx = document.getElementById('dj-commission-chart').getContext('2d');
        new Chart(djCtx, {
            type: 'doughnut',
            data: {
                labels: data.dj_breakdown.labels,
                datasets: [{
                    data: data.dj_breakdown.data,
                    backgroundColor: [
                        '#3498db', '#e74c3c', '#f39c12', '#27ae60', '#9b59b6',
                        '#1abc9c', '#34495e', '#e67e22', '#95a5a6', '#f1c40f'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
    
});

// Commission action functions
function markAsPaid(commissionId) {
    jQuery('#commission_id').val(commissionId);
    jQuery('#ml-mark-paid-modal').show();
}

function viewCommissionDetails(commissionId) {
    jQuery('#ml-commission-details-modal').show();
    
    jQuery.post(ajaxurl, {
        action: 'ml_get_commission_details',
        commission_id: commissionId,
        nonce: ml_admin.nonce
    }, function(response) {
        if (response.success) {
            jQuery('#commission-details-content').html(response.data);
        }
    });
}

function sendCommissionEmail(commissionId) {
    jQuery.post(ajaxurl, {
        action: 'ml_send_commission_email',
        commission_id: commissionId,
        nonce: ml_admin.nonce
    }, function(response) {
        if (response.success) {
            alert('Email sent successfully!');
        } else {
            alert('Error: ' + response.data);
        }
    });
}

function editCommission(commissionId) {
    // Open edit commission modal
    // Implementation depends on requirements
}

function downloadInvoice(commissionId) {
    window.location.href = ajaxurl + '?action=ml_download_commission_invoice&commission_id=' + commissionId + '&nonce=' + ml_admin.nonce;
}

function processMultiplePayments() {
    const selected = jQuery('input[name="commission_ids[]"]:checked');
    if (selected.length === 0) {
        alert('Please select commissions to process.');
        return;
    }
    
    jQuery('#ml-multiple-payments-modal').show();
    
    const commissionIds = selected.map(function() {
        return this.value;
    }).get();
    
    // Load multiple payments interface
    jQuery.post(ajaxurl, {
        action: 'ml_load_multiple_payments',
        commission_ids: commissionIds,
        nonce: ml_admin.nonce
    }, function(response) {
        if (response.success) {
            jQuery('#multiple-payments-content').html(response.data);
        }
    });
}

function exportCommissions() {
    const params = new URLSearchParams(window.location.search);
    params.set('action', 'ml_export_commissions');
    params.set('nonce', ml_admin.nonce);
    
    window.location.href = ajaxurl + '?' + params.toString();
}

function applyBulkAction() {
    const action = jQuery('#bulk-action').val();
    const selected = jQuery('input[name="commission_ids[]"]:checked');
    
    if (!action) {
        alert('Please select an action.');
        return;
    }
    
    if (selected.length === 0) {
        alert('Please select commissions.');
        return;
    }
    
    const commissionIds = selected.map(function() {
        return this.value;
    }).get();
    
    jQuery.post(ajaxurl, {
        action: 'ml_bulk_commission_action',
        bulk_action: action,
        commission_ids: commissionIds,
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

function closeModal() {
    jQuery('.ml-modal').hide();
}
</script>