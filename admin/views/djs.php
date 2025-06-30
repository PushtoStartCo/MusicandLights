<?php
/**
 * Admin DJs View Template
 * 
 * DJ management interface for Music & Lights admin
 * 
 * @package MusicAndLights
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current action and DJ ID
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
$dj_id = isset($_GET['dj_id']) ? intval($_GET['dj_id']) : 0;

// Get DJs data
global $wpdb;

if ($action === 'view' && $dj_id) {
    // Get single DJ for detailed view
    $dj = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ml_djs WHERE id = %d",
            $dj_id
        )
    );
    
    // Get DJ packages
    $dj_packages = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ml_dj_packages WHERE dj_id = %d ORDER BY price ASC",
            $dj_id
        )
    );
    
    // Get DJ statistics
    $dj_stats = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT 
            COUNT(*) as total_bookings,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
            SUM(CASE WHEN status = 'completed' THEN total_cost ELSE 0 END) as total_revenue,
            AVG(CASE WHEN rating IS NOT NULL THEN rating ELSE NULL END) as avg_rating
            FROM {$wpdb->prefix}ml_bookings 
            WHERE dj_id = %d",
            $dj_id
        )
    );
}

// Get all DJs for list view
if ($action === 'list' || $action === '') {
    $djs = $wpdb->get_results(
        "SELECT d.*, 
         COUNT(b.id) as total_bookings,
         SUM(CASE WHEN b.status = 'completed' THEN b.total_cost ELSE 0 END) as total_revenue,
         AVG(CASE WHEN b.rating IS NOT NULL THEN b.rating ELSE NULL END) as avg_rating
         FROM {$wpdb->prefix}ml_djs d
         LEFT JOIN {$wpdb->prefix}ml_bookings b ON d.id = b.dj_id
         GROUP BY d.id
         ORDER BY d.first_name, d.last_name"
    );
}
?>

<div class="wrap ml-admin-wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-groups"></span>
        DJ Management
    </h1>
    
    <?php if ($action === 'list' || $action === ''): ?>
        <a href="<?php echo admin_url('admin.php?page=ml-djs&action=add'); ?>" class="page-title-action">Add New DJ</a>
    <?php endif; ?>
    
    <hr class="wp-header-end">
    
    <?php if ($action === 'view' && $dj): ?>
        
        <!-- Single DJ View -->
        <div class="ml-dj-detail">
            
            <div class="ml-dj-header">
                <div class="ml-form-section">
                    <h3>Professional Details</h3>
                    <div class="ml-form-grid">
                        <div class="ml-form-field">
                            <label for="experience_years">Years of Experience</label>
                            <input type="number" id="experience_years" name="experience_years" min="0" max="50">
                        </div>
                        <div class="ml-form-field">
                            <label for="commission_rate">Commission Rate (%)</label>
                            <input type="number" id="commission_rate" name="commission_rate" min="0" max="100" step="0.1" value="25">
                        </div>
                        <div class="ml-form-field">
                            <label for="travel_rate">Travel Rate (£ per mile)</label>
                            <input type="number" id="travel_rate" name="travel_rate" min="0" step="0.01" value="0.45">
                        </div>
                        <div class="ml-form-field">
                            <label for="max_travel_distance">Max Travel Distance (miles)</label>
                            <input type="number" id="max_travel_distance" name="max_travel_distance" min="0" value="50">
                        </div>
                        <div class="ml-form-field ml-full-width">
                            <label for="specialities">Specialities</label>
                            <input type="text" id="specialities" name="specialities" placeholder="e.g., Weddings, Corporate Events, House Music">
                        </div>
                        <div class="ml-form-field ml-full-width">
                            <label for="bio">Biography</label>
                            <textarea id="bio" name="bio" rows="4" placeholder="Tell us about this DJ's background and experience..."></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="ml-form-section">
                    <h3>Equipment & Transport</h3>
                    <div class="ml-form-grid">
                        <div class="ml-form-field">
                            <label>
                                <input type="checkbox" name="has_own_equipment" value="1">
                                Has Own Equipment
                            </label>
                        </div>
                        <div class="ml-form-field">
                            <label>
                                <input type="checkbox" name="has_own_transport" value="1">
                                Has Own Transport
                            </label>
                        </div>
                        <div class="ml-form-field ml-full-width">
                            <label for="equipment_description">Equipment Description</label>
                            <textarea id="equipment_description" name="equipment_description" rows="3" placeholder="Describe the DJ's equipment setup..."></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="ml-form-section">
                    <h3>Coverage Areas</h3>
                    <div class="ml-form-field">
                        <label for="coverage_areas">Coverage Areas (one per line)</label>
                        <textarea id="coverage_areas" name="coverage_areas" rows="5" placeholder="Hertfordshire&#10;London&#10;Essex&#10;Cambridgeshire"></textarea>
                        <small>Enter one area per line</small>
                    </div>
                </div>
                
                <div class="ml-form-actions">
                    <button type="submit" class="button button-primary">Add DJ</button>
                    <a href="<?php echo admin_url('admin.php?page=ml-djs'); ?>" class="button">Cancel</a>
                </div>
            </form>
        </div>
        
    <?php else: ?>
        
        <!-- DJs List View -->
        <div class="ml-djs-list">
            
            <!-- DJs Grid -->
            <div class="ml-djs-grid">
                <?php if (!empty($djs)): ?>
                    <?php foreach ($djs as $dj): ?>
                        <div class="ml-dj-card">
                            <div class="ml-dj-card-header">
                                <div class="ml-dj-avatar">
                                    <?php if ($dj->profile_image): ?>
                                        <img src="<?php echo esc_url($dj->profile_image); ?>" alt="<?php echo esc_attr($dj->first_name); ?>">
                                    <?php else: ?>
                                        <span class="ml-avatar-initials">
                                            <?php echo esc_html(substr($dj->first_name, 0, 1) . substr($dj->last_name, 0, 1)); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="ml-dj-basic-info">
                                    <h3><?php echo esc_html($dj->stage_name ?: $dj->first_name . ' ' . $dj->last_name); ?></h3>
                                    <div class="ml-dj-contact">
                                        <span><?php echo esc_html($dj->email); ?></span>
                                        <span><?php echo esc_html($dj->phone); ?></span>
                                    </div>
                                </div>
                                <div class="ml-dj-status">
                                    <span class="ml-status-badge status-<?php echo esc_attr($dj->status); ?>">
                                        <?php echo esc_html(ucfirst($dj->status)); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="ml-dj-card-stats">
                                <div class="ml-stat">
                                    <span class="ml-stat-number"><?php echo intval($dj->total_bookings); ?></span>
                                    <span class="ml-stat-label">Bookings</span>
                                </div>
                                <div class="ml-stat">
                                    <span class="ml-stat-number">£<?php echo number_format($dj->total_revenue, 0); ?></span>
                                    <span class="ml-stat-label">Revenue</span>
                                </div>
                                <div class="ml-stat">
                                    <span class="ml-stat-number"><?php echo $dj->avg_rating ? number_format($dj->avg_rating, 1) : 'N/A'; ?></span>
                                    <span class="ml-stat-label">Rating</span>
                                </div>
                                <div class="ml-stat">
                                    <span class="ml-stat-number"><?php echo esc_html($dj->experience_years); ?>y</span>
                                    <span class="ml-stat-label">Experience</span>
                                </div>
                            </div>
                            
                            <div class="ml-dj-card-details">
                                <?php if ($dj->specialities): ?>
                                    <div class="ml-dj-specialities">
                                        <strong>Specialities:</strong> <?php echo esc_html($dj->specialities); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="ml-dj-info-row">
                                    <span><strong>Commission:</strong> <?php echo esc_html($dj->commission_rate); ?>%</span>
                                    <span><strong>Travel:</strong> £<?php echo number_format($dj->travel_rate, 2); ?>/mile</span>
                                </div>
                                
                                <div class="ml-dj-features">
                                    <?php if ($dj->has_own_equipment): ?>
                                        <span class="ml-feature-tag">Own Equipment</span>
                                    <?php endif; ?>
                                    <?php if ($dj->has_own_transport): ?>
                                        <span class="ml-feature-tag">Own Transport</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="ml-dj-card-actions">
                                <a href="<?php echo admin_url('admin.php?page=ml-djs&action=view&dj_id=' . $dj->id); ?>" 
                                   class="button button-primary">View Profile</a>
                                <button class="button button-secondary" onclick="createBooking(<?php echo $dj->id; ?>)">New Booking</button>
                                <div class="ml-dj-dropdown">
                                    <button class="button ml-dropdown-toggle">More ▼</button>
                                    <div class="ml-dropdown-menu">
                                        <a href="#" onclick="editDJ(<?php echo $dj->id; ?>)">Edit DJ</a>
                                        <a href="<?php echo admin_url('admin.php?page=ml-commissions&dj_id=' . $dj->id); ?>">View Commissions</a>
                                        <a href="#" onclick="sendMessage(<?php echo $dj->id; ?>)">Send Message</a>
                                        <a href="#" onclick="setAvailability(<?php echo $dj->id; ?>)">Set Availability</a>
                                        <?php if ($dj->status === 'active'): ?>
                                            <a href="#" onclick="deactivateDJ(<?php echo $dj->id; ?>)" class="ml-danger">Deactivate</a>
                                        <?php else: ?>
                                            <a href="#" onclick="activateDJ(<?php echo $dj->id; ?>)">Activate</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="ml-empty-state">
                        <span class="dashicons dashicons-groups"></span>
                        <h3>No DJs found</h3>
                        <p>Get started by <a href="<?php echo admin_url('admin.php?page=ml-djs&action=add'); ?>">adding your first DJ</a>.</p>
                    </div>
                <?php endif; ?>
            </div>
            
        </div>
        
    <?php endif; ?>
    
</div>

<!-- Edit DJ Modal -->
<div id="ml-edit-dj-modal" class="ml-modal" style="display: none;">
    <div class="ml-modal-content ml-modal-large">
        <div class="ml-modal-header">
            <h3>Edit DJ Profile</h3>
            <span class="ml-modal-close">&times;</span>
        </div>
        <div class="ml-modal-body">
            <form id="ml-edit-dj-form">
                <!-- Form fields will be populated via AJAX -->
            </form>
        </div>
        <div class="ml-modal-footer">
            <button type="button" class="button" onclick="closeModal()">Cancel</button>
            <button type="submit" form="ml-edit-dj-form" class="button button-primary">Save Changes</button>
        </div>
    </div>
</div>

<!-- Manage Packages Modal -->
<div id="ml-packages-modal" class="ml-modal" style="display: none;">
    <div class="ml-modal-content ml-modal-large">
        <div class="ml-modal-header">
            <h3>Manage DJ Packages</h3>
            <span class="ml-modal-close">&times;</span>
        </div>
        <div class="ml-modal-body">
            <div id="packages-content">
                <!-- Packages will be loaded via AJAX -->
            </div>
        </div>
        <div class="ml-modal-footer">
            <button type="button" class="button" onclick="closeModal()">Close</button>
            <button type="button" class="button button-primary" onclick="addNewPackage()">Add New Package</button>
        </div>
    </div>
</div>

<!-- Send Message Modal -->
<div id="ml-message-modal" class="ml-modal" style="display: none;">
    <div class="ml-modal-content">
        <div class="ml-modal-header">
            <h3>Send Message to DJ</h3>
            <span class="ml-modal-close">&times;</span>
        </div>
        <div class="ml-modal-body">
            <form id="ml-message-form">
                <div class="ml-form-field">
                    <label for="message_subject">Subject</label>
                    <input type="text" id="message_subject" name="subject" required>
                </div>
                <div class="ml-form-field">
                    <label for="message_content">Message</label>
                    <textarea id="message_content" name="message" rows="6" required></textarea>
                </div>
                <input type="hidden" id="message_dj_id" name="dj_id">
            </form>
        </div>
        <div class="ml-modal-footer">
            <button type="button" class="button" onclick="closeModal()">Cancel</button>
            <button type="submit" form="ml-message-form" class="button button-primary">Send Message</button>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    
    // Load DJ data if on detail view
    <?php if ($action === 'view' && $dj_id): ?>
    loadDJRecentBookings(<?php echo $dj_id; ?>);
    loadDJCommissionSummary(<?php echo $dj_id; ?>);
    loadDJAvailabilityCalendar(<?php echo $dj_id; ?>);
    <?php endif; ?>
    
    // Add DJ form submission
    $('#ml-add-dj-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'ml_add_dj');
        formData.append('nonce', ml_admin.nonce);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('DJ added successfully!');
                    window.location.href = '<?php echo admin_url("admin.php?page=ml-djs"); ?>';
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
        if (!$(e.target).closest('.ml-dj-dropdown').length) {
            $('.ml-dropdown-menu').hide();
        }
    });
    
    function loadDJRecentBookings(djId) {
        $.post(ajaxurl, {
            action: 'ml_get_dj_recent_bookings',
            dj_id: djId,
            nonce: ml_admin.nonce
        }, function(response) {
            if (response.success) {
                displayRecentBookings(response.data);
            } else {
                $('#dj-recent-bookings').html('<div class="ml-no-data">No recent bookings</div>');
            }
        });
    }
    
    function displayRecentBookings(bookings) {
        let html = '';
        if (bookings && bookings.length > 0) {
            html = '<div class="ml-recent-bookings-list">';
            bookings.forEach(function(booking) {
                html += `
                    <div class="ml-booking-item">
                        <div class="ml-booking-date">${booking.event_date}</div>
                        <div class="ml-booking-details">
                            <div class="ml-booking-customer">${booking.first_name} ${booking.last_name}</div>
                            <div class="ml-booking-venue">${booking.venue_name}</div>
                        </div>
                        <div class="ml-booking-status status-${booking.status}">${booking.status}</div>
                    </div>
                `;
            });
            html += '</div>';
        } else {
            html = '<div class="ml-no-data">No recent bookings</div>';
        }
        $('#dj-recent-bookings').html(html);
    }
    
    function loadDJCommissionSummary(djId) {
        $.post(ajaxurl, {
            action: 'ml_get_dj_commission_summary',
            dj_id: djId,
            nonce: ml_admin.nonce
        }, function(response) {
            if (response.success) {
                displayCommissionSummary(response.data);
            }
        });
    }
    
    function displayCommissionSummary(data) {
        const html = `
            <div class="ml-commission-summary">
                <div class="ml-commission-item">
                    <span class="ml-commission-label">Total Earned</span>
                    <span class="ml-commission-amount">£${parseFloat(data.total_earned || 0).toFixed(2)}</span>
                </div>
                <div class="ml-commission-item">
                    <span class="ml-commission-label">Pending Payment</span>
                    <span class="ml-commission-amount">£${parseFloat(data.pending_payment || 0).toFixed(2)}</span>
                </div>
                <div class="ml-commission-item">
                    <span class="ml-commission-label">This Month</span>
                    <span class="ml-commission-amount">£${parseFloat(data.this_month || 0).toFixed(2)}</span>
                </div>
            </div>
        `;
        $('#dj-commission-summary').html(html);
    }
    
    function loadDJAvailabilityCalendar(djId) {
        // Initialize mini calendar showing DJ availability
        const html = `
            <div class="ml-mini-calendar">
                <div class="ml-calendar-header">
                    <button type="button" class="ml-calendar-nav" data-direction="prev">‹</button>
                    <span class="ml-calendar-title">${new Date().toLocaleDateString('en-GB', { month: 'long', year: 'numeric' })}</span>
                    <button type="button" class="ml-calendar-nav" data-direction="next">›</button>
                </div>
                <div class="ml-calendar-grid">
                    <!-- Calendar grid will be populated -->
                </div>
            </div>
        `;
        $('#dj-availability-calendar').html(html);
    }
    
});

// DJ action functions
function editDJ(djId) {
    jQuery('#ml-edit-dj-modal').show();
    // Load DJ data and populate form
    jQuery.post(ajaxurl, {
        action: 'ml_get_dj_data',
        dj_id: djId,
        nonce: ml_admin.nonce
    }, function(response) {
        if (response.success) {
            populateEditForm(response.data);
        }
    });
}

function createBooking(djId) {
    window.location.href = '<?php echo admin_url("admin.php?page=ml-bookings&action=add"); ?>&dj_id=' + djId;
}

function managePackages(djId) {
    jQuery('#ml-packages-modal').show();
    loadDJPackages(djId);
}

function loadDJPackages(djId) {
    jQuery.post(ajaxurl, {
        action: 'ml_get_dj_packages',
        dj_id: djId,
        nonce: ml_admin.nonce
    }, function(response) {
        if (response.success) {
            displayPackages(response.data);
        }
    });
}

function sendMessage(djId) {
    jQuery('#message_dj_id').val(djId);
    jQuery('#ml-message-modal').show();
}

function viewCommissions(djId) {
    window.location.href = '<?php echo admin_url("admin.php?page=ml-commissions"); ?>&dj_id=' + djId;
}

function setAvailability(djId) {
    // Open availability setting interface
    window.location.href = '<?php echo admin_url("admin.php?page=ml-djs&action=availability"); ?>&dj_id=' + djId;
}

function activateDJ(djId) {
    if (confirm('Are you sure you want to activate this DJ?')) {
        jQuery.post(ajaxurl, {
            action: 'ml_activate_dj',
            dj_id: djId,
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

function deactivateDJ(djId) {
    if (confirm('Are you sure you want to deactivate this DJ? They will no longer receive new bookings.')) {
        jQuery.post(ajaxurl, {
            action: 'ml_deactivate_dj',
            dj_id: djId,
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

function closeModal() {
    jQuery('.ml-modal').hide();
}

function printDJProfile() {
    window.print();
}
</script>="ml-dj-title">
                    <h2><?php echo esc_html($dj->stage_name ?: $dj->first_name . ' ' . $dj->last_name); ?></h2>
                    <span class="ml-status-badge status-<?php echo esc_attr($dj->status); ?>">
                        <?php echo esc_html(ucfirst($dj->status)); ?>
                    </span>
                </div>
                <div class="ml-dj-actions">
                    <a href="<?php echo admin_url('admin.php?page=ml-djs'); ?>" class="button">← Back to List</a>
                    <button class="button button-secondary" onclick="printDJProfile()">Print Profile</button>
                    <button class="button button-primary" onclick="editDJ(<?php echo $dj->id; ?>)">Edit DJ</button>
                </div>
            </div>
            
            <div class="ml-dj-stats-bar">
                <div class="ml-stat-item">
                    <span class="ml-stat-number"><?php echo intval($dj_stats->total_bookings); ?></span>
                    <span class="ml-stat-label">Total Bookings</span>
                </div>
                <div class="ml-stat-item">
                    <span class="ml-stat-number"><?php echo intval($dj_stats->completed_bookings); ?></span>
                    <span class="ml-stat-label">Completed</span>
                </div>
                <div class="ml-stat-item">
                    <span class="ml-stat-number">£<?php echo number_format($dj_stats->total_revenue, 0); ?></span>
                    <span class="ml-stat-label">Revenue Generated</span>
                </div>
                <div class="ml-stat-item">
                    <span class="ml-stat-number"><?php echo $dj_stats->avg_rating ? number_format($dj_stats->avg_rating, 1) : 'N/A'; ?></span>
                    <span class="ml-stat-label">Average Rating</span>
                </div>
            </div>
            
            <div class="ml-dj-content">
                
                <div class="ml-dj-left">
                    
                    <!-- Personal Information -->
                    <div class="ml-info-section">
                        <h3>Personal Information</h3>
                        <div class="ml-info-grid">
                            <div class="ml-info-item">
                                <label>Full Name:</label>
                                <span><?php echo esc_html($dj->first_name . ' ' . $dj->last_name); ?></span>
                            </div>
                            <div class="ml-info-item">
                                <label>Stage Name:</label>
                                <span><?php echo esc_html($dj->stage_name ?: 'Not set'); ?></span>
                            </div>
                            <div class="ml-info-item">
                                <label>Email:</label>
                                <span><a href="mailto:<?php echo esc_attr($dj->email); ?>"><?php echo esc_html($dj->email); ?></a></span>
                            </div>
                            <div class="ml-info-item">
                                <label>Phone:</label>
                                <span><a href="tel:<?php echo esc_attr($dj->phone); ?>"><?php echo esc_html($dj->phone); ?></a></span>
                            </div>
                            <div class="ml-info-item">
                                <label>Date of Birth:</label>
                                <span><?php echo $dj->date_of_birth ? esc_html(date('jS F Y', strtotime($dj->date_of_birth))) : 'Not provided'; ?></span>
                            </div>
                            <div class="ml-info-item ml-full-width">
                                <label>Address:</label>
                                <span><?php echo esc_html($dj->address . ', ' . $dj->city . ', ' . $dj->postcode); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Professional Details -->
                    <div class="ml-info-section">
                        <h3>Professional Details</h3>
                        <div class="ml-info-grid">
                            <div class="ml-info-item">
                                <label>Experience:</label>
                                <span><?php echo esc_html($dj->experience_years); ?> years</span>
                            </div>
                            <div class="ml-info-item">
                                <label>Commission Rate:</label>
                                <span><?php echo esc_html($dj->commission_rate); ?>%</span>
                            </div>
                            <div class="ml-info-item">
                                <label>Travel Rate:</label>
                                <span>£<?php echo number_format($dj->travel_rate, 2); ?> per mile</span>
                            </div>
                            <div class="ml-info-item">
                                <label>Max Travel Distance:</label>
                                <span><?php echo esc_html($dj->max_travel_distance); ?> miles</span>
                            </div>
                            <?php if ($dj->specialities): ?>
                            <div class="ml-info-item ml-full-width">
                                <label>Specialities:</label>
                                <span><?php echo esc_html($dj->specialities); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($dj->bio): ?>
                            <div class="ml-info-item ml-full-width">
                                <label>Biography:</label>
                                <span><?php echo nl2br(esc_html($dj->bio)); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Equipment -->
                    <div class="ml-info-section">
                        <h3>Equipment & Setup</h3>
                        <div class="ml-info-grid">
                            <div class="ml-info-item">
                                <label>Own Equipment:</label>
                                <span><?php echo $dj->has_own_equipment ? 'Yes' : 'No'; ?></span>
                            </div>
                            <div class="ml-info-item">
                                <label>Own Transport:</label>
                                <span><?php echo $dj->has_own_transport ? 'Yes' : 'No'; ?></span>
                            </div>
                            <div class="ml-info-item">
                                <label>Equipment Description:</label>
                                <span><?php echo esc_html($dj->equipment_description ?: 'Not provided'); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Coverage Areas -->
                    <div class="ml-info-section">
                        <h3>Coverage Areas</h3>
                        <div class="ml-coverage-areas">
                            <?php
                            $coverage_areas = json_decode($dj->coverage_areas, true);
                            if ($coverage_areas && is_array($coverage_areas)):
                                foreach ($coverage_areas as $area):
                            ?>
                                <span class="ml-coverage-tag"><?php echo esc_html($area); ?></span>
                            <?php
                                endforeach;
                            else:
                            ?>
                                <em>No coverage areas specified</em>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                </div>
                
                <div class="ml-dj-right">
                    
                    <!-- DJ Packages -->
                    <div class="ml-info-section">
                        <h3>DJ Packages</h3>
                        <?php if (!empty($dj_packages)): ?>
                            <div class="ml-packages-list">
                                <?php foreach ($dj_packages as $package): ?>
                                    <div class="ml-package-item">
                                        <div class="ml-package-header">
                                            <h4><?php echo esc_html($package->name); ?></h4>
                                            <span class="ml-package-price">£<?php echo number_format($package->price, 2); ?></span>
                                        </div>
                                        <div class="ml-package-details">
                                            <div class="ml-package-duration"><?php echo esc_html($package->duration); ?> hours</div>
                                            <div class="ml-package-description"><?php echo esc_html($package->description); ?></div>
                                            <?php if ($package->includes): ?>
                                                <div class="ml-package-includes">
                                                    <strong>Includes:</strong> <?php echo esc_html($package->includes); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="ml-no-data">No packages configured</div>
                        <?php endif; ?>
                        <button class="button button-secondary" onclick="managePackages(<?php echo $dj->id; ?>)">Manage Packages</button>
                    </div>
                    
                    <!-- Recent Bookings -->
                    <div class="ml-info-section">
                        <h3>Recent Bookings</h3>
                        <div id="dj-recent-bookings">
                            <div class="ml-loading">Loading...</div>
                        </div>
                    </div>
                    
                    <!-- Availability Calendar -->
                    <div class="ml-info-section">
                        <h3>Availability Calendar</h3>
                        <div id="dj-availability-calendar">
                            <div class="ml-loading">Loading calendar...</div>
                        </div>
                    </div>
                    
                    <!-- Commission Summary -->
                    <div class="ml-info-section">
                        <h3>Commission Summary</h3>
                        <div id="dj-commission-summary">
                            <div class="ml-loading">Loading...</div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="ml-info-section">
                        <h3>Quick Actions</h3>
                        <div class="ml-quick-actions">
                            <button class="button button-primary" onclick="createBooking(<?php echo $dj->id; ?>)">Create Booking</button>
                            <button class="button" onclick="sendMessage(<?php echo $dj->id; ?>)">Send Message</button>
                            <button class="button" onclick="viewCommissions(<?php echo $dj->id; ?>)">View Commissions</button>
                            <button class="button" onclick="setAvailability(<?php echo $dj->id; ?>)">Set Availability</button>
                            
                            <?php if ($dj->status === 'active'): ?>
                                <button class="button button-link-delete" onclick="deactivateDJ(<?php echo $dj->id; ?>)">Deactivate DJ</button>
                            <?php else: ?>
                                <button class="button button-secondary" onclick="activateDJ(<?php echo $dj->id; ?>)">Activate DJ</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                </div>
                
            </div>
            
        </div>
        
    <?php elseif ($action === 'add'): ?>
        
        <!-- Add New DJ Form -->
        <div class="ml-dj-form">
            <h2>Add New DJ</h2>
            
            <form id="ml-add-dj-form" method="post">
                <?php wp_nonce_field('ml_add_dj', 'ml_dj_nonce'); ?>
                
                <div class="ml-form-section">
                    <h3>Personal Information</h3>
                    <div class="ml-form-grid">
                        <div class="ml-form-field">
                            <label for="first_name">First Name *</label>
                            <input type="text" id="first_name" name="first_name" required>
                        </div>
                        <div class="ml-form-field">
                            <label for="last_name">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" required>
                        </div>
                        <div class="ml-form-field">
                            <label for="stage_name">Stage Name</label>
                            <input type="text" id="stage_name" name="stage_name">
                        </div>
                        <div class="ml-form-field">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        <div class="ml-form-field">
                            <label for="phone">Phone Number *</label>
                            <input type="tel" id="phone" name="phone" required>
                        </div>
                        <div class="ml-form-field">
                            <label for="date_of_birth">Date of Birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth">
                        </div>
                    </div>
                </div>
                
                <div class="ml-form-section">
                    <h3>Address</h3>
                    <div class="ml-form-grid">
                        <div class="ml-form-field ml-full-width">
                            <label for="address">Address</label>
                            <input type="text" id="address" name="address">
                        </div>
                        <div class="ml-form-field">
                            <label for="city">City</label>
                            <input type="text" id="city" name="city">
                        </div>
                        <div class="ml-form-field">
                            <label for="postcode">Postcode</label>
                            <input type="text" id="postcode" name="postcode">
                        </div>
                    </div>
                </div>
                
                <div class