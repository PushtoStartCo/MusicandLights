<?php
/**
 * Admin Dashboard View Template
 * 
 * Main dashboard for Music & Lights admin interface
 * 
 * @package MusicAndLights
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get dashboard data
$admin = ML_Admin::get_instance();
$notices = $admin->get_admin_notices();
$widgets = $admin->get_summary_widgets();
?>

<div class="wrap ml-admin-wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-controls-volumeon"></span>
        Music & Lights Dashboard
    </h1>
    
    <hr class="wp-header-end">
    
    <?php
    // Display admin notices
    foreach ($notices as $notice) {
        $admin->display_admin_notice($notice);
    }
    ?>
    
    <div class="ml-dashboard-container">
        
        <!-- Quick Stats Cards -->
        <div class="ml-stats-row">
            <div class="ml-stat-card ml-stat-bookings">
                <div class="ml-stat-icon">
                    <span class="dashicons dashicons-calendar-alt"></span>
                </div>
                <div class="ml-stat-content">
                    <div class="ml-stat-number" id="total-bookings">-</div>
                    <div class="ml-stat-label">Total Bookings</div>
                    <div class="ml-stat-change" id="bookings-change">-</div>
                </div>
            </div>
            
            <div class="ml-stat-card ml-stat-revenue">
                <div class="ml-stat-icon">
                    <span class="dashicons dashicons-money-alt"></span>
                </div>
                <div class="ml-stat-content">
                    <div class="ml-stat-number" id="total-revenue">-</div>
                    <div class="ml-stat-label">Monthly Revenue</div>
                    <div class="ml-stat-change" id="revenue-change">-</div>
                </div>
            </div>
            
            <div class="ml-stat-card ml-stat-djs">
                <div class="ml-stat-icon">
                    <span class="dashicons dashicons-groups"></span>
                </div>
                <div class="ml-stat-content">
                    <div class="ml-stat-number" id="active-djs">-</div>
                    <div class="ml-stat-label">Active DJs</div>
                    <div class="ml-stat-change" id="djs-change">-</div>
                </div>
            </div>
            
            <div class="ml-stat-card ml-stat-alerts">
                <div class="ml-stat-icon">
                    <span class="dashicons dashicons-warning"></span>
                </div>
                <div class="ml-stat-content">
                    <div class="ml-stat-number" id="open-alerts">-</div>
                    <div class="ml-stat-label">Open Alerts</div>
                    <div class="ml-stat-change" id="alerts-change">-</div>
                </div>
            </div>
        </div>
        
        <!-- Main Dashboard Content -->
        <div class="ml-dashboard-main">
            
            <!-- Left Column -->
            <div class="ml-dashboard-left">
                
                <!-- Bookings Chart -->
                <div class="ml-dashboard-widget">
                    <div class="ml-widget-header">
                        <h3>Bookings Overview</h3>
                        <div class="ml-widget-controls">
                            <select id="bookings-chart-period">
                                <option value="7">Last 7 days</option>
                                <option value="30" selected>Last 30 days</option>
                                <option value="90">Last 90 days</option>
                            </select>
                        </div>
                    </div>
                    <div class="ml-widget-content">
                        <canvas id="bookings-chart" width="400" height="200"></canvas>
                    </div>
                </div>
                
                <!-- Recent Bookings -->
                <div class="ml-dashboard-widget">
                    <div class="ml-widget-header">
                        <h3>Recent Bookings</h3>
                        <a href="<?php echo admin_url('admin.php?page=ml-bookings'); ?>" class="button button-secondary">View All</a>
                    </div>
                    <div class="ml-widget-content">
                        <div class="ml-recent-bookings" id="recent-bookings">
                            <div class="ml-loading">Loading...</div>
                        </div>
                    </div>
                </div>
                
                <!-- Top DJs -->
                <div class="ml-dashboard-widget">
                    <div class="ml-widget-header">
                        <h3>Top Performing DJs</h3>
                        <a href="<?php echo admin_url('admin.php?page=ml-djs'); ?>" class="button button-secondary">Manage DJs</a>
                    </div>
                    <div class="ml-widget-content">
                        <div class="ml-top-djs" id="top-djs">
                            <div class="ml-loading">Loading...</div>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <!-- Right Column -->
            <div class="ml-dashboard-right">
                
                <!-- Today's Events -->
                <div class="ml-dashboard-widget">
                    <div class="ml-widget-header">
                        <h3>Today's Events</h3>
                        <span class="ml-event-count"><?php echo count($widgets['todays_events']['items']); ?> events</span>
                    </div>
                    <div class="ml-widget-content">
                        <?php if (!empty($widgets['todays_events']['items'])): ?>
                            <div class="ml-todays-events">
                                <?php foreach ($widgets['todays_events']['items'] as $event): ?>
                                    <div class="ml-event-item">
                                        <div class="ml-event-time"><?php echo esc_html(date('H:i', strtotime($event->event_time))); ?></div>
                                        <div class="ml-event-details">
                                            <div class="ml-event-customer"><?php echo esc_html($event->first_name . ' ' . $event->last_name); ?></div>
                                            <div class="ml-event-dj">DJ: <?php echo esc_html($event->stage_name ?: $event->dj_first_name . ' ' . $event->dj_last_name); ?></div>
                                            <div class="ml-event-venue"><?php echo esc_html($event->venue_name); ?></div>
                                        </div>
                                        <div class="ml-event-status status-<?php echo esc_attr($event->status); ?>">
                                            <?php echo esc_html(ucfirst($event->status)); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="ml-no-events">
                                <span class="dashicons dashicons-calendar-alt"></span>
                                <p>No events scheduled for today</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="ml-dashboard-widget">
                    <div class="ml-widget-header">
                        <h3>Quick Actions</h3>
                    </div>
                    <div class="ml-widget-content">
                        <div class="ml-quick-actions">
                            <a href="<?php echo admin_url('admin.php?page=ml-bookings&action=add'); ?>" class="ml-quick-action">
                                <span class="dashicons dashicons-plus-alt"></span>
                                Add New Booking
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=ml-djs&action=add'); ?>" class="ml-quick-action">
                                <span class="dashicons dashicons-groups"></span>
                                Add New DJ
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=ml-equipment&action=add'); ?>" class="ml-quick-action">
                                <span class="dashicons dashicons-admin-tools"></span>
                                Add Equipment
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=ml-commissions'); ?>" class="ml-quick-action">
                                <span class="dashicons dashicons-money-alt"></span>
                                Process Commissions
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=ml-settings'); ?>" class="ml-quick-action">
                                <span class="dashicons dashicons-admin-settings"></span>
                                Plugin Settings
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Revenue Summary -->
                <div class="ml-dashboard-widget">
                    <div class="ml-widget-header">
                        <h3>Revenue Summary</h3>
                    </div>
                    <div class="ml-widget-content">
                        <div class="ml-revenue-summary">
                            <div class="ml-revenue-item">
                                <span class="ml-revenue-label">This Month</span>
                                <span class="ml-revenue-amount" id="revenue-this-month">£0.00</span>
                            </div>
                            <div class="ml-revenue-item">
                                <span class="ml-revenue-label">Pending Deposits</span>
                                <span class="ml-revenue-amount" id="pending-deposits">£0.00</span>
                            </div>
                            <div class="ml-revenue-item">
                                <span class="ml-revenue-label">Commission Owed</span>
                                <span class="ml-revenue-amount" id="commission-owed">£0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="ml-dashboard-widget">
                    <div class="ml-widget-header">
                        <h3>Recent Activity</h3>
                    </div>
                    <div class="ml-widget-content">
                        <div class="ml-recent-activity" id="recent-activity">
                            <div class="ml-loading">Loading...</div>
                        </div>
                    </div>
                </div>
                
                <!-- System Status -->
                <div class="ml-dashboard-widget">
                    <div class="ml-widget-header">
                        <h3>System Status</h3>
                    </div>
                    <div class="ml-widget-content">
                        <div class="ml-system-status">
                            <div class="ml-status-item">
                                <span class="ml-status-label">Stripe</span>
                                <span class="ml-status-indicator" id="stripe-status">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    Connected
                                </span>
                            </div>
                            <div class="ml-status-item">
                                <span class="ml-status-label">GoHighLevel</span>
                                <span class="ml-status-indicator" id="ghl-status">
                                    <span class="dashicons dashicons-warning"></span>
                                    Not Connected
                                </span>
                            </div>
                            <div class="ml-status-item">
                                <span class="ml-status-label">Email</span>
                                <span class="ml-status-indicator" id="email-status">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    Working
                                </span>
                            </div>
                            <div class="ml-status-item">
                                <span class="ml-status-label">Safeguards</span>
                                <span class="ml-status-indicator" id="safeguards-status">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    Active
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
            
        </div>
        
    </div>
    
</div>

<script>
jQuery(document).ready(function($) {
    // Load dashboard data
    loadDashboardStats();
    loadRecentActivity();
    
    // Refresh data every 5 minutes
    setInterval(function() {
        loadDashboardStats();
        loadRecentActivity();
    }, 300000);
    
    // Chart period change
    $('#bookings-chart-period').on('change', function() {
        loadBookingsChart($(this).val());
    });
    
    function loadDashboardStats() {
        $.post(ajaxurl, {
            action: 'ml_dashboard_stats',
            nonce: ml_admin.nonce
        }, function(response) {
            if (response.success) {
                updateStatsCards(response.data);
                loadBookingsChart(30);
                loadTopDJs(response.data.top_djs);
            }
        });
    }
    
    function updateStatsCards(data) {
        $('#total-bookings').text(data.bookings.total);
        $('#total-revenue').text('£' + parseFloat(data.revenue.this_month || 0).toLocaleString('en-GB', {minimumFractionDigits: 2}));
        $('#active-djs').text(data.djs.total);
        $('#open-alerts').text(data.safeguards.total_alerts);
        
        // Update revenue summary
        $('#revenue-this-month').text('£' + parseFloat(data.revenue.this_month || 0).toLocaleString('en-GB', {minimumFractionDigits: 2}));
        $('#pending-deposits').text('£' + parseFloat(data.revenue.pending_deposits || 0).toLocaleString('en-GB', {minimumFractionDigits: 2}));
        $('#commission-owed').text('£' + parseFloat(data.revenue.commission_owed || 0).toLocaleString('en-GB', {minimumFractionDigits: 2}));
    }
    
    function loadBookingsChart(period) {
        const ctx = document.getElementById('bookings-chart').getContext('2d');
        
        // Sample data - replace with actual AJAX call
        const chartData = {
            labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
            datasets: [{
                label: 'Bookings',
                data: [12, 19, 8, 15],
                borderColor: '#0073aa',
                backgroundColor: 'rgba(0, 115, 170, 0.1)',
                tension: 0.1
            }]
        };
        
        new Chart(ctx, {
            type: 'line',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
    
    function loadTopDJs(topDJs) {
        let html = '';
        if (topDJs && topDJs.length > 0) {
            topDJs.forEach(function(dj) {
                const djName = dj.stage_name || (dj.first_name + ' ' + dj.last_name);
                html += `
                    <div class="ml-top-dj-item">
                        <div class="ml-dj-name">${djName}</div>
                        <div class="ml-dj-stats">
                            <span class="ml-dj-bookings">${dj.booking_count} bookings</span>
                            <span class="ml-dj-revenue">£${parseFloat(dj.total_revenue || 0).toLocaleString('en-GB', {minimumFractionDigits: 2})}</span>
                        </div>
                    </div>
                `;
            });
        } else {
            html = '<div class="ml-no-data">No DJ data available</div>';
        }
        $('#top-djs').html(html);
    }
    
    function loadRecentActivity() {
        $.post(ajaxurl, {
            action: 'ml_recent_activity',
            nonce: ml_admin.nonce
        }, function(response) {
            if (response.success) {
                displayRecentActivity(response.data);
            }
        });
    }
    
    function displayRecentActivity(activities) {
        let html = '';
        if (activities && activities.length > 0) {
            activities.forEach(function(activity) {
                const timeAgo = moment(activity.time).fromNow();
                html += `
                    <div class="ml-activity-item activity-${activity.type}">
                        <div class="ml-activity-icon">
                            <span class="dashicons dashicons-${getActivityIcon(activity.type)}"></span>
                        </div>
                        <div class="ml-activity-content">
                            <div class="ml-activity-title">${activity.title}</div>
                            <div class="ml-activity-description">${activity.description}</div>
                            <div class="ml-activity-time">${timeAgo}</div>
                        </div>
                    </div>
                `;
            });
        } else {
            html = '<div class="ml-no-data">No recent activity</div>';
        }
        $('#recent-activity').html(html);
    }
    
    function getActivityIcon(type) {
        switch (type) {
            case 'booking': return 'calendar-alt';
            case 'commission': return 'money-alt';
            case 'alert': return 'warning';
            default: return 'admin-generic';
        }
    }
});
</script>