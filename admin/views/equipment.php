<?php
/**
 * Admin Equipment View Template
 * 
 * Equipment management interface for Music & Lights admin
 * 
 * @package MusicAndLights
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current action and equipment ID
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
$equipment_id = isset($_GET['equipment_id']) ? intval($_GET['equipment_id']) : 0;

// Get filter parameters
$type_filter = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

// Get equipment data
global $wpdb;

if ($action === 'view' && $equipment_id) {
    // Get single equipment item for detailed view
    $equipment = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ml_equipment WHERE id = %d",
            $equipment_id
        )
    );
    
    // Get equipment usage history
    $usage_history = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT be.*, b.event_date, b.first_name, b.last_name, b.venue_name, b.status as booking_status
            FROM {$wpdb->prefix}ml_booking_equipment be
            JOIN {$wpdb->prefix}ml_bookings b ON be.booking_id = b.id
            WHERE be.equipment_id = %d
            ORDER BY b.event_date DESC
            LIMIT 20",
            $equipment_id
        )
    );
}

// Build WHERE clause for list view
$where_conditions = array();
$params = array();

if ($type_filter) {
    $where_conditions[] = "type = %s";
    $params[] = $type_filter;
}

if ($status_filter) {
    $where_conditions[] = "status = %s";
    $params[] = $status_filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get equipment list
if ($action === 'list' || $action === '') {
    $query = "SELECT e.*, 
              COUNT(be.id) as total_bookings,
              SUM(be.rental_cost) as total_revenue
              FROM {$wpdb->prefix}ml_equipment e
              LEFT JOIN {$wpdb->prefix}ml_booking_equipment be ON e.id = be.equipment_id
              $where_clause
              GROUP BY e.id
              ORDER BY e.name";
    
    if (!empty($params)) {
        $equipment_list = $wpdb->get_results($wpdb->prepare($query, $params));
    } else {
        $equipment_list = $wpdb->get_results($query);
    }
}

// Get equipment statistics
$equipment_stats = $wpdb->get_row(
    "SELECT 
    COUNT(*) as total_equipment,
    SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_count,
    SUM(CASE WHEN status = 'rented' THEN 1 ELSE 0 END) as rented_count,
    SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance_count,
    SUM(purchase_cost) as total_value,
    COUNT(DISTINCT type) as equipment_types
    FROM {$wpdb->prefix}ml_equipment"
);

// Get equipment types for filter
$equipment_types = $wpdb->get_col("SELECT DISTINCT type FROM {$wpdb->prefix}ml_equipment ORDER BY type");
?>

<div class="wrap ml-admin-wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-admin-tools"></span>
        Equipment Management
    </h1>
    
    <?php if ($action === 'list' || $action === ''): ?>
        <a href="<?php echo admin_url('admin.php?page=ml-equipment&action=add'); ?>" class="page-title-action">Add New Equipment</a>
        <a href="#" onclick="generateInventoryReport()" class="page-title-action">Inventory Report</a>
    <?php endif; ?>
    
    <hr class="wp-header-end">
    
    <?php if ($action === 'view' && $equipment): ?>
        
        <!-- Single Equipment View -->
        <div class="ml-equipment-detail">
            
            <div class="ml-equipment-header">
                <div class="ml-equipment-title">
                    <h2><?php echo esc_html($equipment->name); ?></h2>
                    <span class="ml-status-badge status-<?php echo esc_attr($equipment->status); ?>">
                        <?php echo esc_html(ucfirst($equipment->status)); ?>
                    </span>
                </div>
                <div class="ml-equipment-actions">
                    <a href="<?php echo admin_url('admin.php?page=ml-equipment'); ?>" class="button">← Back to List</a>
                    <button class="button button-secondary" onclick="printEquipmentDetails()">Print Details</button>
                    <button class="button button-primary" onclick="editEquipment(<?php echo $equipment->id; ?>)">Edit Equipment</button>
                </div>
            </div>
            
            <div class="ml-equipment-stats-bar">
                <div class="ml-stat-item">
                    <span class="ml-stat-number"><?php echo count($usage_history); ?></span>
                    <span class="ml-stat-label">Total Bookings</span>
                </div>
                <div class="ml-stat-item">
                    <span class="ml-stat-number">£<?php echo number_format($equipment->purchase_cost, 2); ?></span>
                    <span class="ml-stat-label">Purchase Cost</span>
                </div>
                <div class="ml-stat-item">
                    <span class="ml-stat-number">£<?php echo number_format($equipment->rental_cost_per_day, 2); ?></span>
                    <span class="ml-stat-label">Daily Rate</span>
                </div>
                <div class="ml-stat-item">
                    <span class="ml-stat-number"><?php echo $equipment->purchase_date ? esc_html(human_time_diff(strtotime($equipment->purchase_date), time())) : 'Unknown'; ?></span>
                    <span class="ml-stat-label">Age</span>
                </div>
            </div>
            
            <div class="ml-equipment-content">
                
                <div class="ml-equipment-left">
                    
                    <!-- Equipment Details -->
                    <div class="ml-info-section">
                        <h3>Equipment Details</h3>
                        <div class="ml-info-grid">
                            <div class="ml-info-item">
                                <label>Name:</label>
                                <span><?php echo esc_html($equipment->name); ?></span>
                            </div>
                            <div class="ml-info-item">
                                <label>Type:</label>
                                <span><?php echo esc_html($equipment->type); ?></span>
                            </div>
                            <div class="ml-info-item">
                                <label>Brand:</label>
                                <span><?php echo esc_html($equipment->brand); ?></span>
                            </div>
                            <div class="ml-info-item">
                                <label>Model:</label>
                                <span><?php echo esc_html($equipment->model); ?></span>
                            </div>
                            <div class="ml-info-item">
                                <label>Serial Number:</label>
                                <span><?php echo esc_html($equipment->serial_number); ?></span>
                            </div>
                            <div class="ml-info-item">
                                <label>Condition:</label>
                                <span class="ml-condition-badge condition-<?php echo esc_attr($equipment->condition); ?>">
                                    <?php echo esc_html(ucfirst($equipment->condition)); ?>
                                </span>
                            </div>
                            <?php if ($equipment->description): ?>
                            <div class="ml-info-item ml-full-width">
                                <label>Description:</label>
                                <span><?php echo nl2br(esc_html($equipment->description)); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Financial Information -->
                    <div class="ml-info-section">
                        <h3>Financial Information</h3>
                        <div class="ml-info-grid">
                            <div class="ml-info-item">
                                <label>Purchase Date:</label>
                                <span><?php echo $equipment->purchase_date ? esc_html(date('jS F Y', strtotime($equipment->purchase_date))) : 'Not recorded'; ?></span>
                            </div>
                            <div class="ml-info-item">
                                <label>Purchase Cost:</label>
                                <span>£<?php echo number_format($equipment->purchase_cost, 2); ?></span>
                            </div>
                            <div class="ml-info-item">
                                <label>Daily Rental Rate:</label>
                                <span>£<?php echo number_format($equipment->rental_cost_per_day, 2); ?></span>
                            </div>
                            <div class="ml-info-item">
                                <label>Total Revenue:</label>
                                <span id="equipment-total-revenue">Loading...</span>
                            </div>
                            <div class="ml-info-item">
                                <label>ROI:</label>
                                <span id="equipment-roi">Loading...</span>
                            </div>
                            <div class="ml-info-item">
                                <label>Current Value:</label>
                                <span id="equipment-current-value">Loading...</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Usage History -->
                    <div class="ml-info-section">
                        <h3>Usage History</h3>
                        <?php if (!empty($usage_history)): ?>
                            <div class="ml-usage-history">
                                <?php foreach ($usage_history as $usage): ?>
                                    <div class="ml-usage-item">
                                        <div class="ml-usage-date"><?php echo esc_html(date('jS M Y', strtotime($usage->event_date))); ?></div>
                                        <div class="ml-usage-details">
                                            <div class="ml-usage-customer"><?php echo esc_html($usage->first_name . ' ' . $usage->last_name); ?></div>
                                            <div class="ml-usage-venue"><?php echo esc_html($usage->venue_name); ?></div>
                                            <div class="ml-usage-revenue">£<?php echo number_format($usage->rental_cost, 2); ?></div>
                                        </div>
                                        <div class="ml-usage-status status-<?php echo esc_attr($usage->status); ?>">
                                            <?php echo esc_html(ucfirst($usage->status)); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="ml-no-data">No usage history available</div>
                        <?php endif; ?>
                    </div>
                    
                </div>
                
                <div class="ml-equipment-right">
                    
                    <!-- Current Assignment -->
                    <div class="ml-info-section">
                        <h3>Current Status</h3>
                        <div id="equipment-current-assignment">
                            <div class="ml-loading">Loading...</div>
                        </div>
                    </div>
                    
                    <!-- Upcoming Bookings -->
                    <div class="ml-info-section">
                        <h3>Upcoming Bookings</h3>
                        <div id="equipment-upcoming-bookings">
                            <div class="ml-loading">Loading...</div>
                        </div>
                    </div>
                    
                    <!-- Maintenance Schedule -->
                    <div class="ml-info-section">
                        <h3>Maintenance Schedule</h3>
                        <div id="equipment-maintenance-schedule">
                            <div class="ml-loading">Loading...</div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="ml-info-section">
                        <h3>Quick Actions</h3>
                        <div class="ml-quick-actions">
                            <?php if ($equipment->status === 'available'): ?>
                                <button class="button button-primary" onclick="assignToBooking(<?php echo $equipment->id; ?>)">Assign to Booking</button>
                            <?php endif; ?>
                            
                            <button class="button" onclick="scheduleMaintenance(<?php echo $equipment->id; ?>)">Schedule Maintenance</button>
                            <button class="button" onclick="viewFinancials(<?php echo $equipment->id; ?>)">View Financials</button>
                            <button class="button" onclick="generateQRCode(<?php echo $equipment->id; ?>)">Generate QR Code</button>
                            
                            <?php if ($equipment->status === 'available'): ?>
                                <button class="button button-secondary" onclick="markMaintenance(<?php echo $equipment->id; ?>)">Mark for Maintenance</button>
                            <?php elseif ($equipment->status === 'maintenance'): ?>
                                <button class="button button-secondary" onclick="markAvailable(<?php echo $equipment->id; ?>)">Mark Available</button>
                            <?php endif; ?>
                            
                            <button class="button button-link-delete" onclick="retireEquipment(<?php echo $equipment->id; ?>)">Retire Equipment</button>
                        </div>
                    </div>
                    
                </div>
                
            </div>
            
        </div>
        
    <?php elseif ($action === 'add'): ?>
        
        <!-- Add New Equipment Form -->
        <div class="ml-equipment-form">
            <h2>Add New Equipment</h2>
            
            <form id="ml-add-equipment-form" method="post">
                <?php wp_nonce_field('ml_add_equipment', 'ml_equipment_nonce'); ?>
                
                <div class="ml-form-section">
                    <h3>Equipment Information</h3>
                    <div class="ml-form-grid">
                        <div class="ml-form-field">
                            <label for="name">Equipment Name *</label>
                            <input type="text" id="name" name="name" required>
                        </div>
                        <div class="ml-form-field">
                            <label for="type">Type *</label>
                            <select id="type" name="type" required>
                                <option value="">Select Type</option>
                                <option value="speakers">Speakers</option>
                                <option value="microphones">Microphones</option>
                                <option value="lighting">Lighting</option>
                                <option value="dj_controller">DJ Controller</option>
                                <option value="mixer">Mixer</option>
                                <option value="amplifier">Amplifier</option>
                                <option value="cables">Cables</option>
                                <option value="stands">Stands</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="ml-form-field">
                            <label for="brand">Brand</label>
                            <input type="text" id="brand" name="brand">
                        </div>
                        <div class="ml-form-field">
                            <label for="model">Model</label>
                            <input type="text" id="model" name="model">
                        </div>
                        <div class="ml-form-field">
                            <label for="serial_number">Serial Number</label>
                            <input type="text" id="serial_number" name="serial_number">
                        </div>
                        <div class="ml-form-field">
                            <label for="condition">Condition</label>
                            <select id="condition" name="condition">
                                <option value="excellent">Excellent</option>
                                <option value="good" selected>Good</option>
                                <option value="fair">Fair</option>
                                <option value="poor">Poor</option>
                            </select>
                        </div>
                        <div class="ml-form-field ml-full-width">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="3" placeholder="Detailed description of the equipment..."></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="ml-form-section">
                    <h3>Financial Information</h3>
                    <div class="ml-form-grid">
                        <div class="ml-form-field">
                            <label for="purchase_date">Purchase Date</label>
                            <input type="date" id="purchase_date" name="purchase_date">
                        </div>
                        <div class="ml-form-field">
                            <label for="purchase_cost">Purchase Cost (£)</label>
                            <input type="number" id="purchase_cost" name="purchase_cost" min="0" step="0.01">
                        </div>
                        <div class="ml-form-field">
                            <label for="rental_cost_per_day">Daily Rental Rate (£)</label>
                            <input type="number" id="rental_cost_per_day" name="rental_cost_per_day" min="0" step="0.01">
                        </div>
                    </div>
                </div>
                
                <div class="ml-form-actions">
                    <button type="submit" class="button button-primary">Add Equipment</button>
                    <a href="<?php echo admin_url('admin.php?page=ml-equipment'); ?>" class="button">Cancel</a>
                </div>
            </form>
        </div>
        
    <?php else: ?>
        
        <!-- Equipment List View -->
        <div class="ml-equipment-list">
            
            <!-- Equipment Statistics -->
            <div class="ml-equipment-stats">
                <div class="ml-stat-card ml-stat-total">
                    <div class="ml-stat-icon">
                        <span class="dashicons dashicons-admin-tools"></span>
                    </div>
                    <div class="ml-stat-content">
                        <div class="ml-stat-number"><?php echo intval($equipment_stats->total_equipment); ?></div>
                        <div class="ml-stat-label">Total Equipment</div>
                        <div class="ml-stat-detail"><?php echo intval($equipment_stats->equipment_types); ?> types</div>
                    </div>
                </div>
                
                <div class="ml-stat-card ml-stat-available">
                    <div class="ml-stat-icon">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <div class="ml-stat-content">
                        <div class="ml-stat-number"><?php echo intval($equipment_stats->available_count); ?></div>
                        <div class="ml-stat-label">Available</div>
                        <div class="ml-stat-detail">Ready to use</div>
                    </div>
                </div>
                
                <div class="ml-stat-card ml-stat-rented">
                    <div class="ml-stat-icon">
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>
                    <div class="ml-stat-content">
                        <div class="ml-stat-number"><?php echo intval($equipment_stats->rented_count); ?></div>
                        <div class="ml-stat-label">Currently Rented</div>
                        <div class="ml-stat-detail">On events</div>
                    </div>
                </div>
                
                <div class="ml-stat-card ml-stat-maintenance">
                    <div class="ml-stat-icon">
                        <span class="dashicons dashicons-admin-tools"></span>
                    </div>
                    <div class="ml-stat-content">
                        <div class="ml-stat-number"><?php echo intval($equipment_stats->maintenance_count); ?></div>
                        <div class="ml-stat-label">Maintenance</div>
                        <div class="ml-stat-detail">Needs attention</div>
                    </div>
                </div>
                
                <div class="ml-stat-card ml-stat-value">
                    <div class="ml-stat-icon">
                        <span class="dashicons dashicons-money-alt"></span>
                    </div>
                    <div class="ml-stat-content">
                        <div class="ml-stat-number">£<?php echo number_format($equipment_stats->total_value, 0); ?></div>
                        <div class="ml-stat-label">Total Value</div>
                        <div class="ml-stat-detail">Purchase cost</div>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="ml-filters">
                <form method="get" action="<?php echo admin_url('admin.php'); ?>">
                    <input type="hidden" name="page" value="ml-equipment">
                    
                    <div class="ml-filter-row">
                        <select name="type">
                            <option value="">All Types</option>
                            <?php foreach ($equipment_types as $type): ?>
                                <option value="<?php echo esc_attr($type); ?>" <?php selected($type_filter, $type); ?>>
                                    <?php echo esc_html(ucfirst(str_replace('_', ' ', $type))); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="status">
                            <option value="">All Statuses</option>
                            <option value="available" <?php selected($status_filter, 'available'); ?>>Available</option>
                            <option value="rented" <?php selected($status_filter, 'rented'); ?>>Rented</option>
                            <option value="maintenance" <?php selected($status_filter, 'maintenance'); ?>>Maintenance</option>
                            <option value="retired" <?php selected($status_filter, 'retired'); ?>>Retired</option>
                        </select>
                        
                        <button type="submit" class="button">Filter</button>
                        <a href="<?php echo admin_url('admin.php?page=ml-equipment'); ?>" class="button">Clear</a>
                    </div>
                </form>
            </div>
            
            <!-- Equipment Grid -->
            <div class="ml-equipment-grid">
                <?php if (!empty($equipment_list)): ?>
                    <?php foreach ($equipment_list as $item): ?>
                        <div class="ml-equipment-card">
                            <div class="ml-equipment-card-header">
                                <div class="ml-equipment-image">
                                    <?php if ($item->image_url): ?>
                                        <img src="<?php echo esc_url($item->image_url); ?>" alt="<?php echo esc_attr($item->name); ?>">
                                    <?php else: ?>
                                        <span class="ml-equipment-icon">
                                            <span class="dashicons dashicons-admin-tools"></span>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="ml-equipment-basic-info">
                                    <h3><?php echo esc_html($item->name); ?></h3>
                                    <div class="ml-equipment-meta">
                                        <span class="ml-equipment-type"><?php echo esc_html(ucfirst(str_replace('_', ' ', $item->type))); ?></span>
                                        <span class="ml-equipment-brand"><?php echo esc_html($item->brand . ' ' . $item->model); ?></span>
                                    </div>
                                </div>
                                <div class="ml-equipment-status">
                                    <span class="ml-status-badge status-<?php echo esc_attr($item->status); ?>">
                                        <?php echo esc_html(ucfirst($item->status)); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="ml-equipment-card-stats">
                                <div class="ml-stat">
                                    <span class="ml-stat-number"><?php echo intval($item->total_bookings); ?></span>
                                    <span class="ml-stat-label">Bookings</span>
                                </div>
                                <div class="ml-stat">
                                    <span class="ml-stat-number">£<?php echo number_format($item->total_revenue, 0); ?></span>
                                    <span class="ml-stat-label">Revenue</span>
                                </div>
                                <div class="ml-stat">
                                    <span class="ml-stat-number">£<?php echo number_format($item->rental_cost_per_day, 0); ?></span>
                                    <span class="ml-stat-label">Daily Rate</span>
                                </div>
                                <div class="ml-stat">
                                    <span class="ml-stat-number condition-<?php echo esc_attr($item->condition); ?>"><?php echo esc_html(ucfirst($item->condition)); ?></span>
                                    <span class="ml-stat-label">Condition</span>
                                </div>
                            </div>
                            
                            <div class="ml-equipment-card-details">
                                <?php if ($item->description): ?>
                                    <div class="ml-equipment-description">
                                        <?php echo esc_html(wp_trim_words($item->description, 15)); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="ml-equipment-info-row">
                                    <span><strong>Serial:</strong> <?php echo esc_html($item->serial_number ?: 'N/A'); ?></span>
                                    <span><strong>Purchase:</strong> £<?php echo number_format($item->purchase_cost, 0); ?></span>
                                </div>
                            </div>
                            
                            <div class="ml-equipment-card-actions">
                                <a href="<?php echo admin_url('admin.php?page=ml-equipment&action=view&equipment_id=' . $item->id); ?>" 
                                   class="button button-primary">View Details</a>
                                
                                <?php if ($item->status === 'available'): ?>
                                    <button class="button button-secondary" onclick="assignToBooking(<?php echo $item->id; ?>)">Assign</button>
                                <?php endif; ?>
                                
                                <div class="ml-equipment-dropdown">
                                    <button class="button ml-dropdown-toggle">More ▼</button>
                                    <div class="ml-dropdown-menu">
                                        <a href="#" onclick="editEquipment(<?php echo $item->id; ?>)">Edit Equipment</a>
                                        <a href="#" onclick="viewFinancials(<?php echo $item->id; ?>)">View Financials</a>
                                        <a href="#" onclick="scheduleMaintenance(<?php echo $item->id; ?>)">Schedule Maintenance</a>
                                        <a href="#" onclick="generateQRCode(<?php echo $item->id; ?>)">Generate QR Code</a>
                                        <?php if ($item->status === 'available'): ?>
                                            <a href="#" onclick="markMaintenance(<?php echo $item->id; ?>)">Mark for Maintenance</a>
                                        <?php elseif ($item->status === 'maintenance'): ?>
                                            <a href="#" onclick="markAvailable(<?php echo $item->id; ?>)">Mark Available</a>
                                        <?php endif; ?>
                                        <a href="#" onclick="retireEquipment(<?php echo $item->id; ?>)" class="ml-danger">Retire</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="ml-empty-state">
                        <span class="dashicons dashicons-admin-tools"></span>
                        <h3>No equipment found</h3>
                        <p>Get started by <a href="<?php echo admin_url('admin.php?page=ml-equipment&action=add'); ?>">adding your first equipment item</a>.</p>
                    </div>
                <?php endif; ?>
            </div>
            
        </div>
        
    <?php endif; ?>
    
</div>

<!-- Edit Equipment Modal -->
<div id="ml-edit-equipment-modal" class="ml-modal" style="display: none;">
    <div class="ml-modal-content ml-modal-large">
        <div class="ml-modal-header">
            <h3>Edit Equipment</h3>
            <span class="ml-modal-close">&times;</span>
        </div>
        <div class="ml-modal-body">
            <form id="ml-edit-equipment-form">
                <!-- Form fields will be populated via AJAX -->
            </form>
        </div>
        <div class="ml-modal-footer">
            <button type="button" class="button" onclick="closeModal()">Cancel</button>
            <button type="submit" form="ml-edit-equipment-form" class="button button-primary">Save Changes</button>
        </div>
    </div>
</div>

<!-- Assign to Booking Modal -->
<div id="ml-assign-booking-modal" class="ml-modal" style="display: none;">
    <div class="ml-modal-content">
        <div class="ml-modal-header">
            <h3>Assign Equipment to Booking</h3>
            <span class="ml-modal-close">&times;</span>
        </div>
        <div class="ml-modal-body">
            <form id="ml-assign-equipment-form">
                <div class="ml-form-field">
                    <label for="booking_search">Search Bookings</label>
                    <input type="text" id="booking_search" placeholder="Search by customer name, event date, or booking ID">
                    <div id="booking_search_results" class="ml-search-results"></div>
                </div>
                <input type="hidden" id="assign_equipment_id" name="equipment_id">
                <input type="hidden" id="assign_booking_id" name="booking_id">
            </form>
        </div>
        <div class="ml-modal-footer">
            <button type="button" class="button" onclick="closeModal()">Cancel</button>
            <button type="submit" form="ml-assign-equipment-form" class="button button-primary">Assign Equipment</button>
        </div>
    </div>
</div>

<!-- Maintenance Schedule Modal -->
<div id="ml-maintenance-modal" class="ml-modal" style="display: none;">
    <div class="ml-modal-content">
        <div class="ml-modal-header">
            <h3>Schedule Maintenance</h3>
            <span class="ml-modal-close">&times;</span>
        </div>
        <div class="ml-modal-body">
            <form id="ml-maintenance-form">
                <div class="ml-form-field">
                    <label for="maintenance_date">Maintenance Date</label>
                    <input type="date" id="maintenance_date" name="maintenance_date" required>
                </div>
                <div class="ml-form-field">
                    <label for="maintenance_type">Maintenance Type</label>
                    <select id="maintenance_type" name="maintenance_type" required>
                        <option value="">Select Type</option>
                        <option value="routine">Routine Maintenance</option>
                        <option value="repair">Repair</option>
                        <option value="inspection">Inspection</option>
                        <option value="cleaning">Deep Cleaning</option>
                        <option value="upgrade">Upgrade</option>
                    </select>
                </div>
                <div class="ml-form-field">
                    <label for="maintenance_notes">Notes</label>
                    <textarea id="maintenance_notes" name="maintenance_notes" rows="3" placeholder="Describe the maintenance work needed..."></textarea>
                </div>
                <input type="hidden" id="maintenance_equipment_id" name="equipment_id">
            </form>
        </div>
        <div class="ml-modal-footer">
            <button type="button" class="button" onclick="closeModal()">Cancel</button>
            <button type="submit" form="ml-maintenance-form" class="button button-primary">Schedule Maintenance</button>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    
    // Load equipment data if on detail view
    <?php if ($action === 'view' && $equipment_id): ?>
    loadEquipmentDetails(<?php echo $equipment_id; ?>);
    <?php endif; ?>
    
    // Add equipment form submission
    $('#ml-add-equipment-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'ml_add_equipment');
        formData.append('nonce', ml_admin.nonce);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('Equipment added successfully!');
                    window.location.href = '<?php echo admin_url("admin.php?page=ml-equipment"); ?>';
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
        if (!$(e.target).closest('.ml-equipment-dropdown').length) {
            $('.ml-dropdown-menu').hide();
        }
    });
    
    // Booking search functionality
    $('#booking_search').on('input', function() {
        const query = $(this).val();
        if (query.length >= 3) {
            searchBookings(query);
        } else {
            $('#booking_search_results').empty();
        }
    });
    
    function loadEquipmentDetails(equipmentId) {
        // Load current assignment
        $.post(ajaxurl, {
            action: 'ml_get_equipment_assignment',
            equipment_id: equipmentId,
            nonce: ml_admin.nonce
        }, function(response) {
            displayCurrentAssignment(response.data);
        });
        
        // Load upcoming bookings
        $.post(ajaxurl, {
            action: 'ml_get_equipment_schedule',
            equipment_id: equipmentId,
            nonce: ml_admin.nonce
        }, function(response) {
            displayUpcomingBookings(response.data);
        });
        
        // Load financial data
        $.post(ajaxurl, {
            action: 'ml_get_equipment_financials',
            equipment_id: equipmentId,
            nonce: ml_admin.nonce
        }, function(response) {
            displayFinancialData(response.data);
        });
    }
    
    function displayCurrentAssignment(data) {
        let html = '';
        if (data && data.current_booking) {
            html = `
                <div class="ml-current-assignment">
                    <h4>Currently Assigned</h4>
                    <div class="ml-assignment-details">
                        <p><strong>Booking:</strong> #${data.current_booking.id}</p>
                        <p><strong>Customer:</strong> ${data.current_booking.customer_name}</p>
                        <p><strong>Event Date:</strong> ${data.current_booking.event_date}</p>
                        <p><strong>Venue:</strong> ${data.current_booking.venue_name}</p>
                    </div>
                </div>
            `;
        } else {
            html = '<div class="ml-no-assignment">Equipment is currently available</div>';
        }
        $('#equipment-current-assignment').html(html);
    }
    
    function displayUpcomingBookings(bookings) {
        let html = '';
        if (bookings && bookings.length > 0) {
            html = '<div class="ml-upcoming-bookings">';
            bookings.forEach(function(booking) {
                html += `
                    <div class="ml-upcoming-booking">
                        <div class="ml-booking-date">${booking.event_date}</div>
                        <div class="ml-booking-details">
                            <div class="ml-booking-customer">${booking.first_name} ${booking.last_name}</div>
                            <div class="ml-booking-venue">${booking.venue_name}</div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
        } else {
            html = '<div class="ml-no-data">No upcoming bookings</div>';
        }
        $('#equipment-upcoming-bookings').html(html);
    }
    
    function displayFinancialData(data) {
        $('#equipment-total-revenue').text('£' + parseFloat(data.total_revenue || 0).toFixed(2));
        $('#equipment-roi').text(data.roi + '%');
        $('#equipment-current-value').text('£' + parseFloat(data.current_value || 0).toFixed(2));
    }
    
    function searchBookings(query) {
        $.post(ajaxurl, {
            action: 'ml_search_bookings',
            query: query,
            nonce: ml_admin.nonce
        }, function(response) {
            if (response.success) {
                displayBookingResults(response.data);
            }
        });
    }
    
    function displayBookingResults(bookings) {
        let html = '';
        bookings.forEach(function(booking) {
            html += `
                <div class="ml-booking-result" onclick="selectBooking(${booking.id}, '${booking.customer_name}')">
                    <div class="ml-result-main">
                        <strong>#${booking.id} - ${booking.customer_name}</strong>
                    </div>
                    <div class="ml-result-details">
                        ${booking.event_date} - ${booking.venue_name}
                    </div>
                </div>
            `;
        });
        $('#booking_search_results').html(html);
    }
    
});

// Equipment action functions
function editEquipment(equipmentId) {
    jQuery('#ml-edit-equipment-modal').show();
    // Load equipment data and populate form
    jQuery.post(ajaxurl, {
        action: 'ml_get_equipment_data',
        equipment_id: equipmentId,
        nonce: ml_admin.nonce
    }, function(response) {
        if (response.success) {
            populateEditForm(response.data);
        }
    });
}

function assignToBooking(equipmentId) {
    jQuery('#assign_equipment_id').val(equipmentId);
    jQuery('#ml-assign-booking-modal').show();
}

function selectBooking(bookingId, customerName) {
    jQuery('#assign_booking_id').val(bookingId);
    jQuery('#booking_search').val(customerName);
    jQuery('#booking_search_results').empty();
}

function scheduleMaintenance(equipmentId) {
    jQuery('#maintenance_equipment_id').val(equipmentId);
    jQuery('#ml-maintenance-modal').show();
}

function markMaintenance(equipmentId) {
    if (confirm('Mark this equipment for maintenance? It will become unavailable for bookings.')) {
        jQuery.post(ajaxurl, {
            action: 'ml_mark_equipment_maintenance',
            equipment_id: equipmentId,
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

function markAvailable(equipmentId) {
    if (confirm('Mark this equipment as available? Ensure maintenance is complete.')) {
        jQuery.post(ajaxurl, {
            action: 'ml_mark_equipment_available',
            equipment_id: equipmentId,
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

function retireEquipment(equipmentId) {
    if (confirm('Retire this equipment? It will be permanently removed from active inventory.')) {
        jQuery.post(ajaxurl, {
            action: 'ml_retire_equipment',
            equipment_id: equipmentId,
            nonce: ml_admin.nonce
        }, function(response) {
            if (response.success) {
                alert('Equipment retired successfully.');
                window.location.href = '<?php echo admin_url("admin.php?page=ml-equipment"); ?>';
            } else {
                alert('Error: ' + response.data);
            }
        });
    }
}

function viewFinancials(equipmentId) {
    window.open('<?php echo admin_url("admin.php?page=ml-equipment&action=financials"); ?>&equipment_id=' + equipmentId);
}

function generateQRCode(equipmentId) {
    jQuery.post(ajaxurl, {
        action: 'ml_generate_equipment_qr',
        equipment_id: equipmentId,
        nonce: ml_admin.nonce
    }, function(response) {
        if (response.success) {
            // Open QR code in new window
            window.open(response.data.qr_url);
        } else {
            alert('Error: ' + response.data);
        }
    });
}

function generateInventoryReport() {
    window.location.href = ajaxurl + '?action=ml_generate_inventory_report&nonce=' + ml_admin.nonce;
}

function closeModal() {
    jQuery('.ml-modal').hide();
}

function printEquipmentDetails() {
    window.print();
}
</script>