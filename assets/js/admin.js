/**
 * Music & Lights Admin JavaScript
 */

(function($) {
    'use strict';

    // Initialize when DOM is ready
    $(document).ready(function() {
        
        // Initialize components
        initDatePickers();
        initDataTables();
        initAjaxHandlers();
        initCharts();
        
    });
    
    /**
     * Initialize date pickers
     */
    function initDatePickers() {
        if ($.fn.datepicker) {
            $('.datepicker').datepicker({
                dateFormat: 'yy-mm-dd',
                minDate: 0
            });
        }
    }
    
    /**
     * Initialize data tables
     */
    function initDataTables() {
        if ($.fn.DataTable) {
            $('.data-table').DataTable({
                pageLength: 25,
                order: [[0, 'desc']],
                responsive: true
            });
        }
    }
    
    /**
     * Initialize AJAX handlers
     */
    function initAjaxHandlers() {
        // Clear distance cache
        $(document).on('click', '#clear-distance-cache', function() {
            const $button = $(this);
            const $status = $('#cache-status');
            
            $button.prop('disabled', true);
            $status.html('<span style="color: #666;">Clearing cache...</span>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'clear_distance_cache',
                    nonce: musicandlights_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.html('<span style="color: #00a32a;">✓ Cache cleared successfully!</span>');
                    } else {
                        $status.html('<span style="color: #d63638;">✗ Failed to clear cache</span>');
                    }
                },
                complete: function() {
                    $button.prop('disabled', false);
                    setTimeout(function() {
                        $status.html('');
                    }, 3000);
                }
            });
        });
        
        // Send test email
        $(document).on('click', '#send-test-email', function() {
            const $button = $(this);
            const $status = $('#email-status');
            const email = $('#test-email-address').val();
            const type = $('#test-email-type').val();
            
            if (!email) {
                alert('Please enter an email address');
                return;
            }
            
            $button.prop('disabled', true);
            $status.html('<span style="color: #666;">Sending email...</span>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'send_test_email',
                    email: email,
                    type: type,
                    nonce: musicandlights_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.html('<span style="color: #00a32a;">✓ Test email sent!</span>');
                    } else {
                        $status.html('<span style="color: #d63638;">✗ Failed to send email</span>');
                    }
                },
                complete: function() {
                    $button.prop('disabled', false);
                    setTimeout(function() {
                        $status.html('');
                    }, 5000);
                }
            });
        });
        
        // Sync booking to GoHighLevel
        $(document).on('click', '.sync-to-ghl', function() {
            const $button = $(this);
            const bookingId = $button.data('booking-id');
            
            $button.prop('disabled', true).text('Syncing...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'sync_booking_to_ghl',
                    booking_id: bookingId,
                    nonce: musicandlights_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $button.text('✓ Synced');
                        setTimeout(function() {
                            $button.prop('disabled', false).text('Sync to GHL');
                        }, 3000);
                    } else {
                        alert('Sync failed: ' + response.data);
                        $button.prop('disabled', false).text('Sync to GHL');
                    }
                }
            });
        });
    }
    
    /**
     * Initialize charts
     */
    function initCharts() {
        // Revenue chart
        if ($('#revenue-chart').length && typeof Chart !== 'undefined') {
            const ctx = document.getElementById('revenue-chart').getContext('2d');
            
            // Get chart data from data attributes or AJAX
            const chartData = {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Revenue',
                    data: [12000, 15000, 18000, 14000, 20000, 22000],
                    backgroundColor: 'rgba(0, 124, 186, 0.2)',
                    borderColor: 'rgba(0, 124, 186, 1)',
                    borderWidth: 2
                }]
            };
            
            new Chart(ctx, {
                type: 'line',
                data: chartData,
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
        }
        
        // Bookings by DJ chart
        if ($('#dj-bookings-chart').length && typeof Chart !== 'undefined') {
            const ctx = document.getElementById('dj-bookings-chart').getContext('2d');
            
            const chartData = {
                labels: ['DJ Mike', 'DJ Sarah', 'DJ Tom', 'DJ Lisa', 'DJ James'],
                datasets: [{
                    label: 'Bookings',
                    data: [12, 19, 15, 17, 14],
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.2)',
                        'rgba(54, 162, 235, 0.2)',
                        'rgba(255, 206, 86, 0.2)',
                        'rgba(75, 192, 192, 0.2)',
                        'rgba(153, 102, 255, 0.2)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)'
                    ],
                    borderWidth: 1
                }]
            };
            
            new Chart(ctx, {
                type: 'bar',
                data: chartData,
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
    }
    
    /**
     * Calendar functionality
     */
    window.initCalendar = function(elementId, events) {
        if (typeof FullCalendar === 'undefined') {
            return;
        }
        
        const calendarEl = document.getElementById(elementId);
        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            events: events,
            eventClick: function(info) {
                // Handle event click
                if (info.event.extendedProps.bookingId) {
                    window.location.href = '/wp-admin/post.php?post=' + info.event.extendedProps.bookingId + '&action=edit';
                }
            }
        });
        
        calendar.render();
    };
    
    /**
     * Bulk actions handler
     */
    window.handleBulkAction = function(action, itemIds) {
        if (itemIds.length === 0) {
            alert('Please select at least one item');
            return;
        }
        
        if (!confirm('Are you sure you want to ' + action + ' ' + itemIds.length + ' items?')) {
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'bulk_' + action,
                item_ids: itemIds,
                nonce: musicandlights_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            }
        });
    };
    
    /**
     * Export data handler
     */
    window.exportData = function(type, format) {
        const url = ajaxurl + '?' + $.param({
            action: 'export_' + type,
            format: format,
            nonce: musicandlights_admin.nonce
        });
        
        window.location.href = url;
    };

})(jQuery);