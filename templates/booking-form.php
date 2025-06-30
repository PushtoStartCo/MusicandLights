<?php
/**
 * Booking Form Template - Fixed Version
 * Multi-step booking form for DJ hire
 */

// Get DJ if specified
$dj_id = isset($atts['dj_id']) ? intval($atts['dj_id']) : (isset($_GET['dj_id']) ? intval($_GET['dj_id']) : 0);
$selected_dj = null;

if ($dj_id) {
    $selected_dj = get_post($dj_id);
    if (!$selected_dj || $selected_dj->post_type !== 'dj_profile') {
        $selected_dj = null;
        $dj_id = 0;
    }
}

// Get all available DJs if not pre-selected
$available_djs = array();
if (!$dj_id) {
    $available_djs = get_posts(array(
        'post_type' => 'dj_profile',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'orderby' => 'title',
        'order' => 'ASC'
    ));
}

// Enqueue jQuery if not already loaded
wp_enqueue_script('jquery');
?>

<div id="musicandlights-booking-form" class="booking-form-container">
    <div class="booking-progress">
        <div class="progress-bar">
            <div class="progress-fill" style="width: 25%;"></div>
        </div>
        <div class="progress-steps">
            <div class="step active" data-step="1">
                <span class="step-number">1</span>
                <span class="step-label"><?php echo esc_html__('Event Details', 'musicandlights'); ?></span>
            </div>
            <div class="step" data-step="2">
                <span class="step-number">2</span>
                <span class="step-label"><?php echo esc_html__('Choose DJ', 'musicandlights'); ?></span>
            </div>
            <div class="step" data-step="3">
                <span class="step-number">3</span>
                <span class="step-label"><?php echo esc_html__('Your Details', 'musicandlights'); ?></span>
            </div>
            <div class="step" data-step="4">
                <span class="step-number">4</span>
                <span class="step-label"><?php echo esc_html__('Review', 'musicandlights'); ?></span>
            </div>
        </div>
    </div>
    
    <form id="dj-booking-form" method="post">
        <?php wp_nonce_field('musicandlights_booking_nonce', 'booking_nonce'); ?>
        
        <!-- Step 1: Event Details -->
        <div class="form-step active" data-step="1">
            <h2><?php echo esc_html__('Tell us about your event', 'musicandlights'); ?></h2>
            
            <div class="form-group">
                <label for="event_type"><?php echo esc_html__('Event Type', 'musicandlights'); ?> <span class="required">*</span></label>
                <select name="event_type" id="event_type" required>
                    <option value=""><?php echo esc_html__('Select event type', 'musicandlights'); ?></option>
                    <option value="wedding"><?php echo esc_html__('Wedding', 'musicandlights'); ?></option>
                    <option value="birthday"><?php echo esc_html__('Birthday Party', 'musicandlights'); ?></option>
                    <option value="corporate"><?php echo esc_html__('Corporate Event', 'musicandlights'); ?></option>
                    <option value="private"><?php echo esc_html__('Private Party', 'musicandlights'); ?></option>
                    <option value="school"><?php echo esc_html__('School Event', 'musicandlights'); ?></option>
                    <option value="charity"><?php echo esc_html__('Charity Event', 'musicandlights'); ?></option>
                    <option value="other"><?php echo esc_html__('Other', 'musicandlights'); ?></option>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="event_date"><?php echo esc_html__('Event Date', 'musicandlights'); ?> <span class="required">*</span></label>
                    <input type="date" name="event_date" id="event_date" min="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="event_time"><?php echo esc_html__('Start Time', 'musicandlights'); ?></label>
                    <input type="time" name="event_time" id="event_time">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="event_duration"><?php echo esc_html__('Duration (hours)', 'musicandlights'); ?> <span class="required">*</span></label>
                    <select name="event_duration" id="event_duration" required>
                        <option value="3">3 hours</option>
                        <option value="4" selected>4 hours</option>
                        <option value="5">5 hours</option>
                        <option value="6">6 hours</option>
                        <option value="7">7 hours</option>
                        <option value="8">8+ hours</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="guest_count"><?php echo esc_html__('Number of Guests', 'musicandlights'); ?></label>
                    <select name="guest_count" id="guest_count">
                        <option value=""><?php echo esc_html__('Select guest count', 'musicandlights'); ?></option>
                        <option value="0-50">Up to 50</option>
                        <option value="50-100">50-100</option>
                        <option value="100-200">100-200</option>
                        <option value="200-500">200-500</option>
                        <option value="500+">500+</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="venue_postcode"><?php echo esc_html__('Venue Postcode', 'musicandlights'); ?> <span class="required">*</span></label>
                <input type="text" name="venue_postcode" id="venue_postcode" placeholder="e.g. AL1 1AA" required>
                <p class="help-text"><?php echo esc_html__('We need this to check DJ availability and calculate travel costs', 'musicandlights'); ?></p>
            </div>
            
            <div class="form-navigation">
                <button type="button" class="btn btn-primary next-step" data-current="1" data-next="2">
                    <?php echo esc_html__('Next: Choose DJ', 'musicandlights'); ?> ?
                </button>
            </div>
        </div>
        
        <!-- Additional steps would go here... -->
        <!-- For brevity, only showing first step and JavaScript fixes -->
        
    </form>
</div>

<style>
.booking-form-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.booking-progress {
    margin-bottom: 40px;
}

.progress-bar {
    height: 8px;
    background: #e0e0e0;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 30px;
}

.progress-fill {
    height: 100%;
    background: #007cba;
    transition: width 0.3s ease;
}

.progress-steps {
    display: flex;
    justify-content: space-between;
}

.step {
    text-align: center;
    flex: 1;
    position: relative;
    opacity: 0.5;
    transition: opacity 0.3s ease;
}

.step.active {
    opacity: 1;
}

.step-number {
    display: inline-block;
    width: 30px;
    height: 30px;
    line-height: 30px;
    background: #e0e0e0;
    border-radius: 50%;
    margin-bottom: 5px;
    font-weight: bold;
}

.step.active .step-number {
    background: #007cba;
    color: white;
}

.step-label {
    display: block;
    font-size: 14px;
}

.form-step {
    display: none;
    background: white;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.form-step.active {
    display: block;
}

.form-group {
    margin-bottom: 20px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

@media (max-width: 600px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}

label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}

input[type="text"],
input[type="email"],
input[type="tel"],
input[type="date"],
input[type="time"],
select,
textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 16px;
    box-sizing: border-box;
}

.required {
    color: #d63638;
}

.help-text {
    font-size: 14px;
    color: #666;
    margin-top: 5px;
}

.form-navigation {
    display: flex;
    justify-content: space-between;
    margin-top: 30px;
}

.btn {
    display: inline-block;
    padding: 12px 24px;
    border: none;
    border-radius: 4px;
    font-size: 16px;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.3s ease;
}

.btn-primary {
    background: #007cba;
    color: white;
}

.btn-primary:hover {
    background: #005a87;
}

.btn-secondary {
    background: #f0f0f0;
    color: #333;
}

.btn-secondary:hover {
    background: #e0e0e0;
}

.error-message {
    color: #d63638;
    font-size: 14px;
    margin-top: 5px;
}

.loading {
    text-align: center;
    color: #666;
    padding: 20px;
}

@keyframes pulse {
    0% { opacity: 0.6; }
    50% { opacity: 1; }
    100% { opacity: 0.6; }
}

.checking {
    animation: pulse 1.5s infinite;
}
</style>

<script>
(function($) {
    'use strict';
    
    // Wait for document ready
    $(document).ready(function() {
        
        // Check if musicandlights object exists
        if (typeof musicandlights === 'undefined') {
            console.warn('Music & Lights: AJAX object not available');
            return;
        }
        
        // Form data storage
        var formData = {};
        
        // Navigation between steps
        $('.next-step, .prev-step').on('click', function(e) {
            e.preventDefault();
            
            var currentStep = parseInt($(this).data('current'));
            var nextStep = parseInt($(this).data('next'));
            
            // Validate current step before moving forward
            if ($(this).hasClass('next-step')) {
                if (!validateStep(currentStep)) {
                    return;
                }
                
                // Store form data
                collectStepData(currentStep);
            }
            
            // Update progress
            updateProgress(nextStep);
            
            // Show next step
            $('.form-step').removeClass('active');
            $('.form-step[data-step="' + nextStep + '"]').addClass('active');
            
            // Scroll to top
            $('html, body').animate({ 
                scrollTop: $('#musicandlights-booking-form').offset().top - 100 
            }, 300);
            
            // Step-specific actions
            if (nextStep === 2) {
                checkDJAvailability();
            } else if (nextStep === 4) {
                populateSummary();
            }
        });
        
        // Form submission
        $('#dj-booking-form').on('submit', function(e) {
            e.preventDefault();
            
            // Collect all form data
            collectStepData(4);
            
            // Submit booking
            submitBooking();
        });
        
        // Validate step
        function validateStep(step) {
            var isValid = true;
            var $step = $('.form-step[data-step="' + step + '"]');
            
            // Clear previous error messages
            $step.find('.error-message').remove();
            
            // Check required fields
            $step.find('[required]').each(function() {
                var $field = $(this);
                $field.removeClass('error');
                
                if (!$field.val().trim()) {
                    $field.addClass('error');
                    $field.after('<div class="error-message">This field is required.</div>');
                    isValid = false;
                }
            });
            
            // Step-specific validation
            if (step === 1) {
                // Validate postcode
                var postcode = $('#venue_postcode').val();
                if (postcode && !validateUKPostcode(postcode)) {
                    $('#venue_postcode').addClass('error');
                    $('#venue_postcode').after('<div class="error-message">Please enter a valid UK postcode.</div>');
                    isValid = false;
                }
            }
            
            return isValid;
        }
        
        // UK Postcode validation
        function validateUKPostcode(postcode) {
            var ukPostcodeRegex = /^[A-Z]{1,2}[0-9R][0-9A-Z]? [0-9][ABD-HJLNP-UW-Z]{2}$/i;
            return ukPostcodeRegex.test(postcode.trim());
        }
        
        // Collect form data from step
        function collectStepData(step) {
            var $step = $('.form-step[data-step="' + step + '"]');
            
            $step.find('input, select, textarea').each(function() {
                var $field = $(this);
                var name = $field.attr('name');
                var type = $field.attr('type');
                
                if (name) {
                    if (type === 'checkbox') {
                        formData[name] = $field.is(':checked');
                    } else if (type === 'radio') {
                        if ($field.is(':checked')) {
                            formData[name] = $field.val();
                        }
                    } else {
                        formData[name] = $field.val();
                    }
                }
            });
        }
        
        // Update progress bar
        function updateProgress(step) {
            var progress = (step / 4) * 100;
            $('.progress-fill').css('width', progress + '%');
            
            $('.step').removeClass('active');
            for (var i = 1; i <= step; i++) {
                $('.step[data-step="' + i + '"]').addClass('active');
            }
        }
        
        // Check DJ availability
        function checkDJAvailability() {
            var eventDate = $('#event_date').val();
            
            if (!eventDate) return;
            
            $('.availability-status').each(function() {
                var $status = $(this);
                var djId = $status.data('dj-id');
                
                if (!djId) return;
                
                $.ajax({
                    url: musicandlights.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'musicandlights_check_availability',
                        dj_id: djId,
                        date: eventDate,
                        nonce: musicandlights.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data.available) {
                            $status.html('<span style="color: #00a32a;">? Available</span>');
                        } else {
                            $status.html('<span style="color: #d63638;">? Not available</span>');
                            $status.closest('.dj-card').find('.select-dj').prop('disabled', true);
                        }
                    },
                    error: function() {
                        $status.html('<span style="color: #666;">Unable to check</span>');
                    }
                });
            });
        }
        
        // Submit booking
        function submitBooking() {
            var $submitBtn = $('#submit-booking');
            if ($submitBtn.length === 0) {
                console.error('Submit button not found');
                return;
            }
            
            $submitBtn.prop('disabled', true).text('Processing...');
            
            // Add required data
            formData.action = 'musicandlights_create_booking';
            formData.nonce = musicandlights.nonce;
            
            $.ajax({
                url: musicandlights.ajaxurl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        showBookingResult(true, response.data.message || 'Booking created successfully!');
                        
                        // Redirect if URL provided
                        if (response.data.redirect_url) {
                            setTimeout(function() {
                                window.location.href = response.data.redirect_url;
                            }, 2000);
                        }
                    } else {
                        showBookingResult(false, response.data || 'Booking failed. Please try again.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    showBookingResult(false, 'Network error. Please try again.');
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).text('Submit Booking Enquiry');
                }
            });
        }
        
        // Show booking result
        function showBookingResult(success, message) {
            var $result = $('#booking-result');
            var resultClass = success ? 'success' : 'error';
            var resultIcon = success ? '?' : '?';
            
            $result.html(
                '<div class="result-content ' + resultClass + '">' +
                '<h3>' + resultIcon + ' ' + (success ? 'Success!' : 'Error') + '</h3>' +
                '<p>' + message + '</p>' +
                '</div>'
            ).show();
            
            // Hide form on success
            if (success) {
                $('#dj-booking-form').fadeOut();
            }
            
            // Scroll to result
            $('html, body').animate({
                scrollTop: $result.offset().top - 100
            }, 300);
        }
        
        // Add CSS for result messages
        $('<style>')
            .prop('type', 'text/css')
            .html(`
                .result-content {
                    padding: 30px;
                    text-align: center;
                    border-radius: 8px;
                    margin: 20px 0;
                }
                .result-content.success {
                    background: #d4edda;
                    border: 1px solid #c3e6cb;
                    color: #155724;
                }
                .result-content.error {
                    background: #f8d7da;
                    border: 1px solid #f5c6cb;
                    color: #721c24;
                }
                .result-content h3 {
                    margin: 0 0 15px 0;
                    font-size: 24px;
                }
                .result-content p {
                    margin: 0;
                    font-size: 16px;
                }
                input.error, select.error {
                    border-color: #d63638;
                    box-shadow: 0 0 0 1px #d63638;
                }
            `)
            .appendTo('head');
    });
    
})(jQuery);
</script>