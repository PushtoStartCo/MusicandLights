<?php
/**
 * Admin Bookings View Template
 * 
 * Bookings management interface for Music & Lights admin
 * 
 * @package MusicAndLights
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current action and booking ID
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$dj_filter = isset($_GET['dj_id']) ? intval($_GET['dj_id']) : 0;
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';

// Get bookings data
global $wpdb;

if ($action === 'view' && $booking_id) {
    // Get single booking for detailed view
    $booking = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT b.*, d.first_name as dj_first_name, d.last_name as dj_last_name, d.stage_name, d.email as dj_email, d.phone as dj_phone
            FROM {$wpdb->prefix}ml_bookings b
            LEFT JOIN {$wpdb->prefix}ml_djs d ON b.dj_id = d.id
            WHERE b.id = %d",
            $booking_id
        )
    );
}

// Build WHERE clause for list view
$where_conditions = array();
$params = array();

if ($status_filter) {
    $where_conditions[] = "b.status = %s";
    $params[] = $status_filter;
}

if ($dj_filter) {
    $where_conditions[] = "b.dj_id = %d";
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

// Get bookings list
if ($action === 'list' || $action === '') {
    $query = "SELECT b.*, d.first_name as dj_first_name, d.last_name as dj_last_name, d.stage_name
              FROM {$wpdb->prefix}ml_bookings b
              LEFT JOIN {$wpdb->prefix}ml_djs d ON b.dj_id = d.id
              $where_clause
              ORDER BY b.event_date DESC, b.created_at DESC";
    
    if (!empty($params)) {
        $bookings = $wpdb->get_results($wpdb->prepare($query, $params));
    } else {
        $bookings = $wpdb->get_results($query);
    }
}

// Get all DJs for filter dropdown
$all_djs = $wpdb->get_results("SELECT id, first_name, last_name, stage_name FROM {$wpdb->prefix}ml_djs WHERE status = 'active' ORDER BY first_name");
?>

<div class="wrap ml-admin-wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-calendar-alt"></span>
        Bookings Management
    </h1>
    
    <?php if ($action === 'list' || $action === ''): ?>
        <a href="<?php echo admin_url('admin.php?page=ml-bookings&action=add'); ?>" class="page-title-action">Add New Booking</a>
    <?php endif; ?>
    
    <hr class="wp-header-end">
    
    <?php if ($action === 'view' && $booking): ?>
        
        <!-- Single Booking View -->
        <div class="ml-booking-detail">
            
            <div class="ml-booking-header">
                <h2>Booking #<?php echo esc_html($booking->id); ?></h2>
                <div class="ml-booking-actions">
                    <a href="<?php echo admin_url('admin.php?page=ml-bookings'); ?>" class="button">← Back to List</a>
                    <button class="button button-secondary" onclick="printBooking()">Print</button>
                    <button class="button button-primary" onclick="editBooking(<?php echo $booking->id; ?>)">Edit Booking</button>
                </div>
            </div>
            
            <div class="ml-booking-status-bar">
                <span class="ml-status-badge status-<?php echo esc_attr($booking->status); ?>">
                    <?php echo esc_html(ucfirst($booking->status)); ?>
                </span>
                <span class="ml-booking-date">Event Date: <?php echo esc_html(date('jS F Y', strtotime($booking->event_date))); ?></span>
                <span class="ml-booking-time">Time: <?php echo esc_html($booking->event_time); ?></span>
            </div>
            
            <div class="ml-booking-content">
                
                <div class="ml-booking-left">
                    
                    <!-- Customer Information -->
                    <div class="ml-info-section">
                        <h3>Customer Information</h3>
                        <div class="ml-info-grid">
                            <div class="ml-info-item">
                                <label>Name:</label>
                                <span><?php echo esc_html($booking->first_name . ' ' . $booking->last_name); ?></span>
                            </div>
                            <div class="ml-info-item">
                                <label>Email:</label>
                                <span><a href="mailto:<?php echo esc_attr($booking->email); ?>"><?php echo esc_html($booking->email); ?></a></span>
                            </div>
                            <div class="ml-info-item">
                                <label>Phone:</label>
                                <span><a href="tel:<?php echo esc_attr($booking->phone); ?>"><?php echo esc_html($booking->phone); ?></a></span>
                            </div>
                            <div class="ml-info-item">
                                <label>Address:</label>
                                <span><?php echo esc_html($booking->address . ', ' . $booking->city . ', ' . $booking->postcode); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Event Details -->
                    <div class="ml-info-section">
                        <h3>Event Details</h3>
                        <div class="ml-info-grid">
                            <div class="ml-info-item">
                                <label>Event Type:</label>
                                <span><?php echo esc_html($booking->event_type); ?></span>
                            </div>
                            <div class="ml-info-item">
                                <label>Venue:</label>
                                <span><?php echo esc_html($booking->venue_name); ?></span>
                            </div>
                            <div class="ml-info-item">
                                <label>Guest Count:</label>
                                <span><?php echo esc_html($booking->guest_count); ?></span>
                            </div>
                            <div class="ml-info-item">
                                <label>Duration:</label>
                                <span><?php echo esc_html($booking->duration); ?> hours</span>
                            </div>
                            <?php if ($booking->special_requests): ?>
                            <div class="ml-info-item ml-full-width">
                                <label>Special Requests:</label>
                                <span><?php echo esc_html($booking->special_requests); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- DJ Information -->
                    <?php if ($booking->dj_id): ?>
                    <div class="ml-info-section">
                        <h3>Assigned DJ</h3>
                        <div class="ml-info-grid">
                            <div class="ml-info-item">
                                <label>DJ Name:</label>
                                <span><?php echo esc_html($booking->stage_name ?: $booking->dj_first_name . ' ' . $booking->dj_last_name); ?></span>
                            </div>
                            <div class="ml-info-item">
                                <label>Contact:</label>
                                <span>
                                    <a href="mailto:<?php echo esc_attr($booking->dj_email); ?>"><?php echo esc_html($booking->dj_email); ?></a><br>
                                    <a href="tel:<?php echo esc_attr($booking->dj_phone); ?>"><?php echo esc_html($booking->dj_phone); ?></a>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Equipment -->
                    <div class="ml-info-section">
                        <h3>Equipment Assignment</h3>
                        <div id="booking-equipment">
                            <div class="ml-loading">Loading equipment...</div>
                        </div>
                    </div>
                    
                </div>
                
                <div class="ml-booking-right">
                    
                    <!-- Payment Information -->
                    <div class="ml-info-section">
                        <h3>Payment Details</h3>
                        <div class="ml-payment-summary">
                            <div class="ml-payment-item">
                                <label>Package Cost:</label>
                                <span>£<?php echo number_format($booking->package_cost, 2); ?></span>
                            </div>
                            <div class="ml-payment-item">
                                <label>Travel Cost:</label>
                                <span>£<?php echo number_format($booking->travel_cost, 2); ?></span>
                            </div>
                            <div class="ml-payment-item">
                                <label>Equipment Cost:</label>
                                <span>£<?php echo number_format($booking->equipment_cost, 2); ?></span>
                            </div>
                            <div class="ml-payment-item ml-payment-total">
                                <label>Total Cost:</label>
                                <span>£<?php echo number_format($booking->total_cost, 2); ?></span>
                            </div>
                            <div class="ml-payment-item">
                                <label>Deposit Required:</label>
                                <span>£<?php echo number_format($booking->deposit_amount, 2); ?></span>
                            </div>
                            <div class="ml-payment-item">
                                <label>Deposit Status:</label>
                                <span class="ml-payment-status status-<?php echo $booking->deposit_paid ? 'paid' : 'pending'; ?>">
                                    <?php echo $booking->deposit_paid ? 'Paid' : 'Pending'; ?>
                                    <?php if ($booking->deposit_paid && $booking->deposit_paid_date): ?>
                                        <br><small>Paid: <?php echo date('jS M Y', strtotime($booking->deposit_paid_date)); ?></small>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="ml-payment-item">
                                <label>Balance Due:</label>
                                <span>£<?php echo number_format($booking->total_cost - $booking->deposit_amount, 2); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Booking Timeline -->
                    <div class="ml-info-section">
                        <h3>Booking Timeline</h3>
                        <div class="ml-timeline">
                            <div class="ml-timeline-item completed">
                                <div class="ml-timeline-date"><?php echo date('jS M Y H:i', strtotime($booking->created_at)); ?></div>
                                <div class="ml-timeline-event">Booking Created</div>
                            </div>
                            <?php if ($booking->deposit_paid): ?>
                            <div class="ml-timeline-item completed">
                                <div class="ml-timeline-date"><?php echo date('jS M Y H:i', strtotime($booking->deposit_paid_date)); ?></div>
                                <div class="ml-timeline-event">Deposit Paid</div>
                            </div>
                            <?php endif; ?>
                            <?php if ($booking->status === 'confirmed'): ?>
                            <div class="ml-timeline-item completed">
                                <div class="ml-timeline-date"><?php echo date('jS M Y H:i', strtotime($booking->updated_at)); ?></div>
                                <div class="ml-timeline-event">Booking Confirmed</div>
                            </div>
                            <?php endif; ?>
                            <div class="ml-timeline-item <?php echo (strtotime($booking->event_date) < time()) ? 'completed' : 'pending'; ?>">
                                <div class="ml-timeline-date"><?php echo date('jS M Y', strtotime($booking->event_date . ' ' . $booking->event_time)); ?></div>
                                <div class="ml-timeline-event">Event Date</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="ml-info-section">
                        <h3>Quick Actions</h3>
                        <div class="ml-quick-actions">
                            <?php if ($booking->status === 'pending'): ?>
                                <button class="button button-primary" onclick="confirmBooking(<?php echo $booking->id; ?>)">Confirm Booking</button>
                            <?php endif; ?>
                            
                            <?php if (!$booking->deposit_paid && $booking->status !== 'cancelled'): ?>
                                <button class="button" onclick="sendPaymentLink(<?php echo $booking->id; ?>)">Send Payment Link</button>
                            <?php endif; ?>
                            
                            <button class="button" onclick="sendEmail(<?php echo $booking->id; ?>)">Send Email</button>
                            <button class="button" onclick="rescheduleBooking(<?php echo $booking->id; ?>)">Reschedule</button>
                            
                            <?php if ($booking->status !== 'cancelled' && $booking->status !== 'completed'): ?>
                                <button class="button button-link-delete" onclick="cancelBooking(<?php echo $booking->id; ?>)">Cancel Booking</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                </div>
                
            </div>
            
        </div>
        
    <?php else: ?>
        
        <!-- Bookings List View -->
        <div class="ml-bookings-list">
            
            <!-- Filters -->
            <div class="ml-filters">
                <form method="get" action="<?php echo admin_url('admin.php'); ?>">
                    <input type="hidden" name="page" value="ml-bookings">
                    
                    <div class="ml-filter-row">
                        <select name="status">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php selected($status_filter, 'pending'); ?>>Pending</option>
                            <option value="confirmed" <?php selected($status_filter, 'confirmed'); ?>>Confirmed</option>
                            <option value="completed" <?php selected($status_filter, 'completed'); ?>>Completed</option>
                            <option value="cancelled" <?php selected($status_filter, 'cancelled'); ?>>Cancelled</option>
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
                        <a href="<?php echo admin_url('admin.php?page=ml-bookings'); ?>" class="button">Clear</a>
                    </div>
                </form>
            </div>
            
            <!-- Bookings Table -->
            <div class="ml-table-container">
                <table class="wp-list-table widefat fixed striped ml-bookings-table">
                    <thead>
                        <tr>
                            <th scope="col" class="manage-column">ID</th>
                            <th scope="col" class="manage-column">Customer</th>
                            <th scope="col" class="manage-column">Event Date</th>
                            <th scope="col" class="manage-column">Event Type</th>
                            <th scope="col" class="manage-column">DJ</th>
                            <th scope="col" class="manage-column">Status</th>
                            <th scope="col" class="manage-column">Total</th>
                            <th scope="col" class="manage-column">Deposit</th>
                            <th scope="col" class="manage-column">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($bookings)): ?>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td><strong>#<?php echo esc_html($booking->id); ?></strong></td>
                                    <td>
                                        <strong><?php echo esc_html($booking->first_name . ' ' . $booking->last_name); ?></strong><br>
                                        <small><?php echo esc_html($booking->email); ?></small>
                                    </td>
                                    <td>
                                        <?php echo esc_html(date('jS M Y', strtotime($booking->event_date))); ?><br>
                                        <small><?php echo esc_html($booking->event_time); ?></small>
                                    </td>
                                    <td><?php echo esc_html($booking->event_type); ?></td>
                                    <td>
                                        <?php if ($booking->dj_id): ?>
                                            <?php echo esc_html($booking->stage_name ?: $booking->dj_first_name . ' ' . $booking->dj_last_name); ?>
                                        <?php else: ?>
                                            <em>Not assigned</em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="ml-status-badge status-<?php echo esc_attr($booking->status); ?>">
                                            <?php echo esc_html(ucfirst($booking->status)); ?>
                                        </span>
                                    </td>
                                    <td>£<?php echo number_format($booking->total_cost, 2); ?></td>
                                    <td>
                                        <span class="ml-deposit-status status-<?php echo $booking->deposit_paid ? 'paid' : 'pending'; ?>">
                                            <?php echo $booking->deposit_paid ? 'Paid' : 'Pending'; ?>
                                        </span>
                                    </td>
                                    <td class="ml-actions">
                                        <a href="<?php echo admin_url('admin.php?page=ml-bookings&action=view&booking_id=' . $booking->id); ?>" 
                                           class="button button-small" title="View Details">View</a>
                                        <button class="button button-small" onclick="editBooking(<?php echo $booking->id; ?>)" title="Edit">Edit</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="ml-no-data">
                                    <div class="ml-empty-state">
                                        <span class="dashicons dashicons-calendar-alt"></span>
                                        <h3>No bookings found</h3>
                                        <p>Try adjusting your filters or <a href="<?php echo admin_url('admin.php?page=ml-bookings&action=add'); ?>">create a new booking</a>.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
        </div>
        
    <?php endif; ?>
    
</div>

<!-- Edit Booking Modal -->
<div id="ml-edit-booking-modal" class="ml-modal" style="display: none;">
    <div class="ml-modal-content">
        <div class="ml-modal-header">
            <h3>Edit Booking</h3>
            <span class="ml-modal-close">&times;</span>
        </div>
        <div class="ml-modal-body">
            <form id="ml-edit-booking-form">
                <!-- Form fields will be populated via AJAX -->
            </form>
        </div>
        <div class="ml-modal-footer">
            <button type="button" class="button" onclick="closeModal()">Cancel</button>
            <button type="submit" form="ml-edit-booking-form" class="button button-primary">Save Changes</button>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    
    // Load booking equipment if on detail view
    <?php if ($action === 'view' && $booking_id): ?>
    loadBookingEquipment(<?php echo $booking_id; ?>);
    <?php endif; ?>
    
    function loadBookingEquipment(bookingId) {
        $.post(ajaxurl, {
            action: 'ml_get_booking_equipment',
            booking_id: bookingId,
            nonce: ml_admin.nonce
        }, function(response) {
            if (response.success) {
                displayBookingEquipment(response.data);
            } else {
                $('#booking-equipment').html('<div class="ml-no-data">No equipment assigned</div>');
            }
        });
    }
    
    function displayBookingEquipment(equipment) {
        let html = '';
        if (equipment && equipment.length > 0) {
            html = '<div class="ml-equipment-list">';
            equipment.forEach(function(item) {
                html += `
                    <div class="ml-equipment-item">
                        <div class="ml-equipment-name">${item.name}</div>
                        <div class="ml-equipment-details">
                            <span class="ml-equipment-type">${item.type}</span>
                            <span class="ml-equipment-cost">£${parseFloat(item.rental_cost).toFixed(2)}/day</span>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
        } else {
            html = '<div class="ml-no-data">No equipment assigned to this booking</div>';
        }
        $('#booking-equipment').html(html);
    }
    
});

// Booking action functions
function editBooking(bookingId) {
    // Show edit modal and load booking data
    jQuery('#ml-edit-booking-modal').show();
    // TODO: Load booking data via AJAX and populate form
}

function confirmBooking(bookingId) {
    if (confirm('Are you sure you want to confirm this booking?')) {
        jQuery.post(ajaxurl, {
            action: 'ml_confirm_booking',
            booking_id: bookingId,
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

function cancelBooking(bookingId) {
    if (confirm('Are you sure you want to cancel this booking? This action cannot be undone.')) {
        jQuery.post(ajaxurl, {
            action: 'ml_cancel_booking',
            booking_id: bookingId,
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

function sendPaymentLink(bookingId) {
    jQuery.post(ajaxurl, {
        action: 'ml_send_payment_link',
        booking_id: bookingId,
        nonce: ml_admin.nonce
    }, function(response) {
        if (response.success) {
            alert('Payment link sent successfully!');
        } else {
            alert('Error: ' + response.data);
        }
    });
}

function closeModal() {
    jQuery('.ml-modal').hide();
}

function printBooking() {
    window.print();
}
</script>