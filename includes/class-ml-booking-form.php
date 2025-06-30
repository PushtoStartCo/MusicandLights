<?php
/**
 * Booking form class for Music & Lights plugin frontend
 */

if (!defined('ABSPATH')) {
    exit;
}

class ML_Booking_Form {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        // AJAX handlers
        add_action('wp_ajax_ml_submit_booking', array($this, 'handle_ajax_submission'));
        add_action('wp_ajax_nopriv_ml_submit_booking', array($this, 'handle_ajax_submission'));
        add_action('wp_ajax_ml_get_booking_step_data', array($this, 'ajax_get_step_data'));
        add_action('wp_ajax_nopriv_ml_get_booking_step_data', array($this, 'ajax_get_step_data'));
    }
    
    /**
     * Render booking form shortcode
     */
    public static function render_shortcode($atts = array()) {
        $atts = shortcode_atts(array(
            'style' => 'default',
            'show_progress' => 'true',
            'steps' => '4'
        ), $atts);
        
        ob_start();
        self::render_booking_form($atts);
        return ob_get_clean();
    }
    
    /**
     * Render the complete booking form
     */
    public static function render_booking_form($atts = array()) {
        $form_id = 'ml-booking-form-' . uniqid();
        ?>
        <div id="<?php echo esc_attr($form_id); ?>" class="ml-booking-form-container">
            <?php if ($atts['show_progress'] === 'true'): ?>
                <div class="ml-progress-bar">
                    <div class="ml-progress-step active" data-step="1">
                        <span class="step-number">1</span>
                        <span class="step-title">Event Details</span>
                    </div>
                    <div class="ml-progress-step" data-step="2">
                        <span class="step-number">2</span>
                        <span class="step-title">Select DJ</span>
                    </div>
                    <div class="ml-progress-step" data-step="3">
                        <span class="step-number">3</span>
                        <span class="step-title">Contact Details</span>
                    </div>
                    <div class="ml-progress-step" data-step="4">
                        <span class="step-number">4</span>
                        <span class="step-title">Confirmation</span>
                    </div>
                </div>
            <?php endif; ?>
            
            <form id="ml-booking-form" method="post">
                <?php wp_nonce_field('ml_nonce', 'ml_booking_nonce'); ?>
                
                <!-- Step 1: Event Details -->
                <div class="ml-form-step" id="step-1">
                    <h2>Tell us about your event</h2>
                    
                    <div class="ml-form-row">
                        <div class="ml-form-group">
                            <label for="event_type">Event Type *</label>
                            <select id="event_type" name="event_type" required>
                                <option value="">Select event type</option>
                                <option value="wedding">Wedding</option>
                                <option value="birthday">Birthday Party</option>
                                <option value="corporate">Corporate Event</option>
                                <option value="anniversary">Anniversary</option>
                                <option value="christening">Christening</option>
                                <option value="engagement">Engagement Party</option>
                                <option value="school_prom">School Prom</option>
                                <option value="charity">Charity Event</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="ml-form-group">
                            <label for="event_date">Event Date *</label>
                            <input type="date" id="event_date" name="event_date" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    
                    <div class="ml-form-row">
                        <div class="ml-form-group">
                            <label for="event_start_time">Start Time *</label>
                            <input type="time" id="event_start_time" name="event_start_time" required>
                        </div>
                        
                        <div class="ml-form-group">
                            <label for="event_end_time">End Time *</label>
                            <input type="time" id="event_end_time" name="event_end_time" required>
                        </div>
                    </div>
                    
                    <div class="ml-form-group">
                        <label for="guest_count">Expected Number of Guests</label>
                        <select id="guest_count" name="guest_count">
                            <option value="">Select guest count</option>
                            <option value="1-25">1-25 guests</option>
                            <option value="26-50">26-50 guests</option>
                            <option value="51-100">51-100 guests</option>
                            <option value="101-200">101-200 guests</option>
                            <option value="201-300">201-300 guests</option>
                            <option value="300+">300+ guests</option>
                        </select>
                    </div>
                    
                    <h3>Venue Information</h3>
                    
                    <div class="ml-form-group">
                        <label for="venue_name">Venue Name *</label>
                        <input type="text" id="venue_name" name="venue_name" required>
                    </div>
                    
                    <div class="ml-form-group">
                        <label for="venue_address">Venue Address *</label>
                        <textarea id="venue_address" name="venue_address" rows="3" required></textarea>
                    </div>
                    
                    <div class="ml-form-row">
                        <div class="ml-form-group">
                            <label for="venue_postcode">Postcode *</label>
                            <input type="text" id="venue_postcode" name="venue_postcode" required placeholder="e.g. AL1 2AB">
                            <div class="ml-postcode-validation"></div>
                        </div>
                        
                        <div class="ml-form-group">
                            <label for="venue_type">Venue Type</label>
                            <select id="venue_type" name="venue_type">
                                <option value="">Select venue type</option>
                                <option value="hotel">Hotel</option>
                                <option value="village_hall">Village Hall</option>
                                <option value="church_hall">Church Hall</option>
                                <option value="marquee">Marquee</option>
                                <option value="private_home">Private Home</option>
                                <option value="restaurant">Restaurant</option>
                                <option value="pub">Pub</option>
                                <option value="club">Club</option>
                                <option value="outdoor">Outdoor Venue</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="ml-form-actions">
                        <button type="button" class="ml-btn ml-btn-primary ml-next-step" data-next="2">
                            Next: Select DJ
                        </button>
                    </div>
                </div>
                
                <!-- Step 2: DJ Selection -->
                <div class="ml-form-step" id="step-2" style="display: none;">
                    <h2>Select Your DJ</h2>
                    
                    <div class="ml-loading-message">
                        <p>Finding available DJs for your event...</p>
                        <div class="ml-spinner"></div>
                    </div>
                    
                    <div class="ml-available-djs"></div>
                    
                    <div class="ml-form-actions">
                        <button type="button" class="ml-btn ml-btn-secondary ml-prev-step" data-prev="1">
                            Previous
                        </button>
                        <button type="button" class="ml-btn ml-btn-primary ml-next-step" data-next="3" disabled>
                            Next: Contact Details
                        </button>
                    </div>
                </div>
                
                <!-- Step 3: Contact Details & Preferences -->
                <div class="ml-form-step" id="step-3" style="display: none;">
                    <h2>Your Contact Details</h2>
                    
                    <div class="ml-form-row">
                        <div class="ml-form-group">
                            <label for="client_name">Full Name *</label>
                            <input type="text" id="client_name" name="client_name" required>
                        </div>
                        
                        <div class="ml-form-group">
                            <label for="client_email">Email Address *</label>
                            <input type="email" id="client_email" name="client_email" required>
                        </div>
                    </div>
                    
                    <div class="ml-form-row">
                        <div class="ml-form-group">
                            <label for="client_phone">Phone Number *</label>
                            <input type="tel" id="client_phone" name="client_phone" required>
                        </div>
                        
                        <div class="ml-form-group">
                            <label for="preferred_contact">Preferred Contact Method</label>
                            <select id="preferred_contact" name="preferred_contact">
                                <option value="email">Email</option>
                                <option value="phone">Phone</option>
                                <option value="text">Text Message</option>
                            </select>
                        </div>
                    </div>
                    
                    <h3>Event Preferences</h3>
                    
                    <div class="ml-form-group">
                        <label for="music_preferences">Music Preferences</label>
                        <textarea id="music_preferences" name="music_preferences" rows="3" 
                                placeholder="Tell us about your music preferences, genres you love, must-play songs, or songs to avoid..."></textarea>
                    </div>
                    
                    <div class="ml-form-group">
                        <label for="special_requests">Special Requests</label>
                        <textarea id="special_requests" name="special_requests" rows="3"
                                placeholder="Any special requests for your event? First dance songs, announcements, etc..."></textarea>
                    </div>
                    
                    <div class="ml-form-group">
                        <label for="equipment_requests">Additional Equipment Needed</label>
                        <div class="ml-checkbox-group">
                            <label><input type="checkbox" name="equipment_requests[]" value="microphone"> Microphone</label>
                            <label><input type="checkbox" name="equipment_requests[]" value="uplighting"> Uplighting</label>
                            <label><input type="checkbox" name="equipment_requests[]" value="photo_booth"> Photo Booth</label>
                            <label><input type="checkbox" name="equipment_requests[]" value="fog_machine"> Fog Machine</label>
                            <label><input type="checkbox" name="equipment_requests[]" value="projector"> Projector/Screen</label>
                            <label><input type="checkbox" name="equipment_requests[]" value="extra_speakers"> Extra Speakers</label>
                        </div>
                        <textarea name="equipment_requests_other" placeholder="Other equipment requirements..."></textarea>
                    </div>
                    
                    <div class="ml-form-actions">
                        <button type="button" class="ml-btn ml-btn-secondary ml-prev-step" data-prev="2">
                            Previous
                        </button>
                        <button type="button" class="ml-btn ml-btn-primary ml-next-step" data-next="4">
                            Review Booking
                        </button>
                    </div>
                </div>
                
                <!-- Step 4: Confirmation & Payment -->
                <div class="ml-form-step" id="step-4" style="display: none;">
                    <h2>Confirm Your Booking</h2>
                    
                    <div class="ml-booking-summary">
                        <h3>Booking Summary</h3>
                        <div class="ml-summary-content"></div>
                    </div>
                    
                    <div class="ml-pricing-breakdown">
                        <h3>Pricing</h3>
                        <div class="ml-pricing-content"></div>
                    </div>
                    
                    <div class="ml-terms-conditions">
                        <label>
                            <input type="checkbox" id="accept_terms" name="accept_terms" required>
                            I accept the <a href="#" target="_blank">Terms and Conditions</a> *
                        </label>
                    </div>
                    
                    <div class="ml-marketing-consent">
                        <label>
                            <input type="checkbox" id="marketing_consent" name="marketing_consent">
                            I would like to receive updates about Music & Lights services and special offers
                        </label>
                    </div>
                    
                    <div class="ml-form-actions">
                        <button type="button" class="ml-btn ml-btn-secondary ml-prev-step" data-prev="3">
                            Previous
                        </button>
                        <button type="submit" class="ml-btn ml-btn-success ml-submit-booking" disabled>
                            Confirm Booking & Pay Deposit
                        </button>
                    </div>
                </div>
                
                <!-- Hidden fields for selected data -->
                <input type="hidden" id="selected_dj_id" name="selected_dj_id">
                <input type="hidden" id="selected_package_id" name="selected_package_id">
                <input type="hidden" id="calculated_travel_cost" name="calculated_travel_cost">
                <input type="hidden" id="calculated_total_cost" name="calculated_total_cost">
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            new MLBookingForm('#<?php echo esc_js($form_id); ?>');
        });
        </script>
        <?php
    }
    
    /**
     * Handle AJAX form submission
     */
    public function handle_ajax_submission() {
        check_ajax_referer('ml_nonce', 'nonce');
        
        // Validate and sanitize form data
        $booking_data = $this->validate_booking_data($_POST);
        
        if (is_wp_error($booking_data)) {
            wp_send_json_error($booking_data->get_error_message());
        }
        
        // Create the booking
        $booking_manager = ML_Booking::get_instance();
        $booking_id = $booking_manager->create_booking($booking_data);
        
        if (is_wp_error($booking_id)) {
            wp_send_json_error($booking_id->get_error_message());
        }
        
        // Get booking details for response
        $booking = $booking_manager->get_booking_by_id($booking_id);
        
        // Initialize payment if Stripe is configured
        $payment_intent = null;
        if (get_option('ml_stripe_secret_key')) {
            $payments = ML_Payments::get_instance();
            $payment_intent = $payments->create_payment_intent($booking_id, 'deposit');
            
            if (is_wp_error($payment_intent)) {
                // Log error but don't fail the booking
                error_log('ML Plugin: Payment intent creation failed: ' . $payment_intent->get_error_message());
            }
        }
        
        wp_send_json_success(array(
            'booking_id' => $booking_id,
            'booking_reference' => $booking->booking_reference,
            'payment_intent' => $payment_intent,
            'message' => 'Booking created successfully'
        ));
    }
    
    /**
     * Validate booking form data
     */
    private function validate_booking_data($form_data) {
        $errors = array();
        
        // Required fields
        $required_fields = array(
            'event_type' => 'Event type',
            'event_date' => 'Event date',
            'event_start_time' => 'Start time',
            'event_end_time' => 'End time',
            'venue_name' => 'Venue name',
            'venue_address' => 'Venue address',
            'venue_postcode' => 'Venue postcode',
            'client_name' => 'Your name',
            'client_email' => 'Email address',
            'client_phone' => 'Phone number',
            'selected_dj_id' => 'DJ selection'
        );
        
        foreach ($required_fields as $field => $label) {
            if (empty($form_data[$field])) {
                $errors[] = $label . ' is required';
            }
        }
        
        if (!empty($errors)) {
            return new WP_Error('validation_failed', implode(', ', $errors));
        }
        
        // Validate email
        if (!is_email($form_data['client_email'])) {
            return new WP_Error('invalid_email', 'Please enter a valid email address');
        }
        
        // Validate date
        $event_date = strtotime($form_data['event_date']);
        if ($event_date < strtotime('today')) {
            return new WP_Error('invalid_date', 'Event date cannot be in the past');
        }
        
        // Validate times
        $start_time = strtotime($form_data['event_start_time']);
        $end_time = strtotime($form_data['event_end_time']);
        if ($end_time <= $start_time) {
            return new WP_Error('invalid_time', 'End time must be after start time');
        }
        
        // Validate postcode
        $travel_calculator = ML_Travel::get_instance();
        $postcode_validation = $travel_calculator->validate_postcode($form_data['venue_postcode']);
        if (is_wp_error($postcode_validation)) {
            return new WP_Error('invalid_postcode', 'Please enter a valid UK postcode');
        }
        
        // Validate DJ selection
        $dj_manager = ML_DJ::get_instance();
        $selected_dj = $dj_manager->get_dj_by_id($form_data['selected_dj_id']);
        if (!$selected_dj || $selected_dj->status !== 'active') {
            return new WP_Error('invalid_dj', 'Selected DJ is not available');
        }
        
        // Validate terms acceptance
        if (empty($form_data['accept_terms'])) {
            return new WP_Error('terms_required', 'You must accept the terms and conditions');
        }
        
        // Sanitize and return data
        return array(
            'event_type' => sanitize_text_field($form_data['event_type']),
            'event_date' => sanitize_text_field($form_data['event_date']),
            'event_start_time' => sanitize_text_field($form_data['event_start_time']),
            'event_end_time' => sanitize_text_field($form_data['event_end_time']),
            'guest_count' => sanitize_text_field($form_data['guest_count'] ?? ''),
            'venue_name' => sanitize_text_field($form_data['venue_name']),
            'venue_address' => sanitize_textarea_field($form_data['venue_address']),
            'venue_postcode' => strtoupper(str_replace(' ', '', sanitize_text_field($form_data['venue_postcode']))),
            'venue_type' => sanitize_text_field($form_data['venue_type'] ?? ''),
            'client_name' => sanitize_text_field($form_data['client_name']),
            'client_email' => sanitize_email($form_data['client_email']),
            'client_phone' => sanitize_text_field($form_data['client_phone']),
            'preferred_contact' => sanitize_text_field($form_data['preferred_contact'] ?? 'email'),
            'music_preferences' => sanitize_textarea_field($form_data['music_preferences'] ?? ''),
            'special_requests' => sanitize_textarea_field($form_data['special_requests'] ?? ''),
            'equipment_requests' => $this->sanitize_equipment_requests($form_data),
            'dj_id' => intval($form_data['selected_dj_id']),
            'package_id' => !empty($form_data['selected_package_id']) ? intval($form_data['selected_package_id']) : null,
            'marketing_consent' => !empty($form_data['marketing_consent'])
        );
    }
    
    /**
     * Sanitize equipment requests
     */
    private function sanitize_equipment_requests($form_data) {
        $equipment = array();
        
        if (!empty($form_data['equipment_requests']) && is_array($form_data['equipment_requests'])) {
            $equipment = array_map('sanitize_text_field', $form_data['equipment_requests']);
        }
        
        if (!empty($form_data['equipment_requests_other'])) {
            $equipment[] = 'Other: ' . sanitize_textarea_field($form_data['equipment_requests_other']);
        }
        
        return implode(', ', $equipment);
    }
    
    /**
     * AJAX: Get step data
     */
    public function ajax_get_step_data() {
        check_ajax_referer('ml_nonce', 'nonce');
        
        $step = intval($_POST['step']);
        $form_data = $_POST['form_data'] ?? array();
        
        switch ($step) {
            case 2:
                // Get available DJs
                $this->get_available_djs_data($form_data);
                break;
            case 4:
                // Get booking summary
                $this->get_booking_summary_data($form_data);
                break;
            default:
                wp_send_json_error('Invalid step');
        }
    }
    
    /**
     * Get available DJs for step 2
     */
    private function get_available_djs_data($form_data) {
        if (empty($form_data['event_date']) || empty($form_data['event_start_time']) || empty($form_data['event_end_time'])) {
            wp_send_json_error('Event details required');
        }
        
        $dj_manager = ML_DJ::get_instance();
        $available_djs = $dj_manager->get_available_djs(
            $form_data['event_date'],
            $form_data['event_start_time'],
            $form_data['event_end_time'],
            $form_data['venue_postcode'] ?? ''
        );
        
        // Add packages and travel costs to each DJ
        $travel_calculator = ML_Travel::get_instance();
        
        foreach ($available_djs as &$dj) {
            $dj->packages = $dj_manager->get_dj_packages($dj->id);
            
            // Calculate travel cost if postcode provided
            if (!empty($form_data['venue_postcode'])) {
                $travel_cost = $travel_calculator->calculate_cost(
                    $dj->coverage_areas, // This should contain DJ's base postcode
                    $form_data['venue_postcode'],
                    $dj->travel_rate
                );
                
                $dj->travel_cost = !is_wp_error($travel_cost) ? $travel_cost['travel_cost'] : 0;
            } else {
                $dj->travel_cost = 0;
            }
        }
        
        wp_send_json_success(array(
            'available_djs' => $available_djs,
            'count' => count($available_djs)
        ));
    }
    
    /**
     * Get booking summary for step 4
     */
    private function get_booking_summary_data($form_data) {
        if (empty($form_data['selected_dj_id'])) {
            wp_send_json_error('DJ selection required');
        }
        
        $dj_manager = ML_DJ::get_instance();
        $dj = $dj_manager->get_dj_by_id($form_data['selected_dj_id']);
        
        if (!$dj) {
            wp_send_json_error('Selected DJ not found');
        }
        
        $package = null;
        if (!empty($form_data['selected_package_id'])) {
            $package = $dj_manager->get_dj_packages($dj->id);
            $package = array_filter($package, function($p) use ($form_data) {
                return $p->id == $form_data['selected_package_id'];
            });
            $package = reset($package);
        }
        
        // Calculate pricing
        $pricing = $this->calculate_booking_pricing($form_data, $dj, $package);
        
        wp_send_json_success(array(
            'dj' => $dj,
            'package' => $package,
            'pricing' => $pricing,
            'form_data' => $form_data
        ));
    }
    
    /**
     * Calculate booking pricing for summary
     */
    private function calculate_booking_pricing($form_data, $dj, $package = null) {
        $base_price = 0;
        
        if ($package) {
            $base_price = $package->price;
        } else {
            // Calculate based on hourly rate
            $start_time = strtotime($form_data['event_start_time']);
            $end_time = strtotime($form_data['event_end_time']);
            $duration_hours = ceil(($end_time - $start_time) / 3600);
            $base_price = $dj->hourly_rate * $duration_hours;
        }
        
        // Calculate travel cost
        $travel_cost = 0;
        if (!empty($form_data['venue_postcode'])) {
            $travel_calculator = ML_Travel::get_instance();
            $travel_result = $travel_calculator->calculate_cost(
                $dj->coverage_areas,
                $form_data['venue_postcode'],
                $dj->travel_rate
            );
            
            if (!is_wp_error($travel_result)) {
                $travel_cost = $travel_result['travel_cost'];
            }
        }
        
        $subtotal = $base_price + $travel_cost;
        $deposit_percentage = get_option('ml_default_deposit_percentage', 25);
        $deposit_amount = ($subtotal * $deposit_percentage) / 100;
        $final_payment = $subtotal - $deposit_amount;
        
        return array(
            'base_price' => $base_price,
            'travel_cost' => $travel_cost,
            'subtotal' => $subtotal,
            'deposit_percentage' => $deposit_percentage,
            'deposit_amount' => $deposit_amount,
            'final_payment' => $final_payment
        );
    }
}
?>