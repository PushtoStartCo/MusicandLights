<?php
/**
 * DJ Booking System Class
 * Handles booking creation, pre-call forms, payment processing, and GHL integration
 */

class DJ_Booking_System {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_ajax_create_booking', array($this, 'create_booking'));
        add_action('wp_ajax_nopriv_create_booking', array($this, 'create_booking'));
        add_action('wp_ajax_update_event_details', array($this, 'update_event_details'));
        add_action('wp_ajax_nopriv_update_event_details', array($this, 'update_event_details'));
        add_action('wp_ajax_process_stripe_payment', array($this, 'process_stripe_payment'));
        add_action('wp_ajax_nopriv_process_stripe_payment', array($this, 'process_stripe_payment'));
    }
    
    public function init() {
        // Register booking statuses
        $this->register_booking_statuses();
        
        // Add booking meta boxes
        add_action('add_meta_boxes', array($this, 'add_booking_meta_boxes'));
        add_action('save_post', array($this, 'save_booking_meta'));
        
        // Schedule cron jobs for payment reminders
        if (!wp_next_scheduled('dj_payment_reminders')) {
            wp_schedule_event(time(), 'daily', 'dj_payment_reminders');
        }
        add_action('dj_payment_reminders', array($this, 'send_payment_reminders'));
    }
    
    private function register_booking_statuses() {
        // Custom booking statuses
        $statuses = array(
            'enquiry' => 'Enquiry Received',
            'pending_details' => 'Pending Event Details',
            'pending_call' => 'Pending DJ Call',
            'quote_sent' => 'Quote Sent',
            'deposit_pending' => 'Deposit Pending',
            'deposit_paid' => 'Deposit Paid',
            'confirmed' => 'Confirmed',
            'final_payment_due' => 'Final Payment Due',
            'paid_in_full' => 'Paid in Full',
            'completed' => 'Event Completed',
            'cancelled' => 'Cancelled',
            'international_pending' => 'International - Pending Call'
        );
        
        foreach ($statuses as $status => $label) {
            register_post_status($status, array(
                'label' => $label,
                'public' => false,
                'exclude_from_search' => true,
                'show_in_admin_all_list' => true,
                'show_in_admin_status_list' => true,
                'label_count' => _n_noop($label . ' <span class="count">(%s)</span>', $label . ' <span class="count">(%s)</span>')
            ));
        }
    }
    
    public function add_booking_meta_boxes() {
        add_meta_box(
            'booking_details',
            'Booking Details',
            array($this, 'render_booking_details_meta_box'),
            'dj_booking',
            'normal',
            'high'
        );
        
        add_meta_box(
            'event_details',
            'Event Details',
            array($this, 'render_event_details_meta_box'),
            'dj_booking',
            'normal',
            'high'
        );
        
        add_meta_box(
            'payment_details',
            'Payment Details',
            array($this, 'render_payment_details_meta_box'),
            'dj_booking',
            'normal',
            'high'
        );
        
        add_meta_box(
            'communication_log',
            'Communication Log',
            array($this, 'render_communication_log_meta_box'),
            'dj_booking',
            'side',
            'high'
        );
    }
    
    public function render_booking_details_meta_box($post) {
        wp_nonce_field('booking_meta_nonce', 'booking_meta_nonce');
        
        $booking_data = $this->get_booking_data($post->ID);
        
        ?>
        <table class="form-table">
            <tr>
                <th><label for="client_name">Client Name</label></th>
                <td><input type="text" name="client_name" id="client_name" 
                           value="<?php echo esc_attr($booking_data['client_name']); ?>" class="regular-text" readonly></td>
            </tr>
            
            <tr>
                <th><label for="client_email">Client Email</label></th>
                <td><input type="email" name="client_email" id="client_email" 
                           value="<?php echo esc_attr($booking_data['client_email']); ?>" class="regular-text" readonly></td>
            </tr>
            
            <tr>
                <th><label for="client_phone">Client Phone</label></th>
                <td><input type="tel" name="client_phone" id="client_phone" 
                           value="<?php echo esc_attr($booking_data['client_phone']); ?>" class="regular-text"></td>
            </tr>
            
            <tr>
                <th><label for="assigned_dj">Assigned DJ</label></th>
                <td>
                    <select name="assigned_dj" id="assigned_dj" class="regular-text">
                        <option value="">Select DJ</option>
                        <?php
                        $djs = get_posts(array(
                            'post_type' => 'dj_profile',
                            'posts_per_page' => -1,
                            'post_status' => 'publish'
                        ));
                        
                        foreach ($djs as $dj) {
                            $selected = ($booking_data['assigned_dj'] == $dj->ID) ? 'selected' : '';
                            echo '<option value="' . $dj->ID . '" ' . $selected . '>' . $dj->post_title . '</option>';
                        }
                        ?>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th><label for="event_date">Event Date</label></th>
                <td><input type="date" name="event_date" id="event_date" 
                           value="<?php echo esc_attr($booking_data['event_date']); ?>" class="regular-text"></td>
            </tr>
            
            <tr>
                <th><label for="event_time">Event Time</label></th>
                <td><input type="time" name="event_time" id="event_time" 
                           value="<?php echo esc_attr($booking_data['event_time']); ?>" class="regular-text"></td>
            </tr>
            
            <tr>
                <th><label for="event_duration">Duration (hours)</label></th>
                <td><input type="number" name="event_duration" id="event_duration" 
                           value="<?php echo esc_attr($booking_data['event_duration']); ?>" min="1" max="24" class="small-text"></td>
            </tr>
            
            <tr>
                <th><label for="total_amount">Total Amount (£)</label></th>
                <td><input type="number" name="total_amount" id="total_amount" 
                           value="<?php echo esc_attr($booking_data['total_amount']); ?>" min="0" step="0.01" class="regular-text"></td>
            </tr>
            
            <tr>
                <th><label for="booking_source">Booking Source</label></th>
                <td>
                    <select name="booking_source" id="booking_source" class="regular-text">
                        <option value="website" <?php selected($booking_data['booking_source'], 'website'); ?>>Website</option>
                        <option value="ghl_funnel" <?php selected($booking_data['booking_source'], 'ghl_funnel'); ?>>GoHighLevel Funnel</option>
                        <option value="referral" <?php selected($booking_data['booking_source'], 'referral'); ?>>Referral</option>
                        <option value="social_media" <?php selected($booking_data['booking_source'], 'social_media'); ?>>Social Media</option>
                        <option value="direct" <?php selected($booking_data['booking_source'], 'direct'); ?>>Direct Contact</option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th><label for="ghl_contact_id">GHL Contact ID</label></th>
                <td><input type="text" name="ghl_contact_id" id="ghl_contact_id" 
                           value="<?php echo esc_attr($booking_data['ghl_contact_id']); ?>" class="regular-text" readonly></td>
            </tr>
        </table>
        <?php
    }
    
    public function render_event_details_meta_box($post) {
        $event_details = $this->get_event_details($post->ID);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="event_type">Event Type</label></th>
                <td>
                    <select name="event_type" id="event_type" class="regular-text">
                        <option value="">Select Event Type</option>
                        <option value="wedding" <?php selected($event_details['event_type'], 'wedding'); ?>>Wedding</option>
                        <option value="birthday" <?php selected($event_details['event_type'], 'birthday'); ?>>Birthday Party</option>
                        <option value="corporate" <?php selected($event_details['event_type'], 'corporate'); ?>>Corporate Event</option>
                        <option value="private" <?php selected($event_details['event_type'], 'private'); ?>>Private Party</option>
                        <option value="school" <?php selected($event_details['event_type'], 'school'); ?>>School Event</option>
                        <option value="charity" <?php selected($event_details['event_type'], 'charity'); ?>>Charity Event</option>
                        <option value="festival" <?php selected($event_details['event_type'], 'festival'); ?>>Festival</option>
                        <option value="club" <?php selected($event_details['event_type'], 'club'); ?>>Club/Nightclub</option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th><label for="venue_name">Venue Name</label></th>
                <td><input type="text" name="venue_name" id="venue_name" 
                           value="<?php echo esc_attr($event_details['venue_name']); ?>" class="regular-text"></td>
            </tr>
            
            <tr>
                <th><label for="venue_address">Venue Address</label></th>
                <td><textarea name="venue_address" id="venue_address" rows="3" 
                              class="large-text"><?php echo esc_textarea($event_details['venue_address']); ?></textarea></td>
            </tr>
            
            <tr>
                <th><label for="venue_postcode">Venue Postcode</label></th>
                <td><input type="text" name="venue_postcode" id="venue_postcode" 
                           value="<?php echo esc_attr($event_details['venue_postcode']); ?>" class="regular-text"></td>
            </tr>
            
            <tr>
                <th><label for="guest_count">Expected Guest Count</label></th>
                <td><input type="number" name="guest_count" id="guest_count" 
                           value="<?php echo esc_attr($event_details['guest_count']); ?>" min="1" class="small-text"></td>
            </tr>
            
            <tr>
                <th><label for="age_range">Age Range</label></th>
                <td>
                    <select name="age_range" id="age_range" class="regular-text">
                        <option value="">Select Age Range</option>
                        <option value="children" <?php selected($event_details['age_range'], 'children'); ?>>Children (Under 18)</option>
                        <option value="young_adults" <?php selected($event_details['age_range'], 'young_adults'); ?>>Young Adults (18-30)</option>
                        <option value="adults" <?php selected($event_details['age_range'], 'adults'); ?>>Adults (30-50)</option>
                        <option value="mature" <?php selected($event_details['age_range'], 'mature'); ?>>Mature (50+)</option>
                        <option value="mixed" <?php selected($event_details['age_range'], 'mixed'); ?>>Mixed Ages</option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th><label for="music_preferences">Music Preferences</label></th>
                <td><textarea name="music_preferences" id="music_preferences" rows="3" 
                              class="large-text" placeholder="Describe the music style and atmosphere wanted"><?php echo esc_textarea($event_details['music_preferences']); ?></textarea></td>
            </tr>
            
            <tr>
                <th><label for="must_play_list">Must Play List</label></th>
                <td><textarea name="must_play_list" id="must_play_list" rows="4" 
                              class="large-text" placeholder="Songs that must be played (one per line)"><?php echo esc_textarea($event_details['must_play_list']); ?></textarea></td>
            </tr>
            
            <tr>
                <th><label for="do_not_play_list">Do Not Play List</label></th>
                <td><textarea name="do_not_play_list" id="do_not_play_list" rows="4" 
                              class="large-text" placeholder="Songs/genres to avoid (one per line)"><?php echo esc_textarea($event_details['do_not_play_list']); ?></textarea></td>
            </tr>
            
            <tr>
                <th><label for="spotify_playlists">Spotify Playlist Links</label></th>
                <td><textarea name="spotify_playlists" id="spotify_playlists" rows="3" 
                              class="large-text" placeholder="Spotify playlist URLs (one per line)"><?php echo esc_textarea($event_details['spotify_playlists']); ?></textarea></td>
            </tr>
            
            <tr>
                <th><label for="event_timeline">Event Timeline</label></th>
                <td><textarea name="event_timeline" id="event_timeline" rows="4" 
                              class="large-text" placeholder="Key moments and their timings (e.g., first dance at 9pm)"><?php echo esc_textarea($event_details['event_timeline']); ?></textarea></td>
            </tr>
            
            <tr>
                <th><label for="special_requirements">Special Requirements</label></th>
                <td><textarea name="special_requirements" id="special_requirements" rows="3" 
                              class="large-text" placeholder="Any special requests, announcements, or considerations"><?php echo esc_textarea($event_details['special_requirements']); ?></textarea></td>
            </tr>
            
            <tr>
                <th><label for="equipment_requirements">Equipment Requirements</label></th>
                <td><textarea name="equipment_requirements" id="equipment_requirements" rows="3" 
                              class="large-text" placeholder="Specific equipment needs beyond standard setup"><?php echo esc_textarea($event_details['equipment_requirements']); ?></textarea></td>
            </tr>
            
            <tr>
                <th><label for="details_completed">Details Form Completed</label></th>
                <td>
                    <input type="checkbox" name="details_completed" id="details_completed" value="1" 
                           <?php checked($event_details['details_completed'], '1'); ?>>
                    <label for="details_completed">Client has completed all required details</label>
                </td>
            </tr>
        </table>
        <?php
    }
    
    public function render_payment_details_meta_box($post) {
        $payment_data = $this->get_payment_data($post->ID);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="deposit_amount">Deposit Amount (£)</label></th>
                <td><input type="number" name="deposit_amount" id="deposit_amount" 
                           value="<?php echo esc_attr($payment_data['deposit_amount']); ?>" min="0" step="0.01" class="regular-text"></td>
            </tr>
            
            <tr>
                <th><label for="deposit_status">Deposit Status</label></th>
                <td>
                    <select name="deposit_status" id="deposit_status" class="regular-text">
                        <option value="pending" <?php selected($payment_data['deposit_status'], 'pending'); ?>>Pending</option>
                        <option value="paid" <?php selected($payment_data['deposit_status'], 'paid'); ?>>Paid</option>
                        <option value="refunded" <?php selected($payment_data['deposit_status'], 'refunded'); ?>>Refunded</option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th><label for="deposit_paid_date">Deposit Paid Date</label></th>
                <td><input type="datetime-local" name="deposit_paid_date" id="deposit_paid_date" 
                           value="<?php echo esc_attr($payment_data['deposit_paid_date']); ?>" class="regular-text"></td>
            </tr>
            
            <tr>
                <th><label for="final_payment_amount">Final Payment Amount (£)</label></th>
                <td><input type="number" name="final_payment_amount" id="final_payment_amount" 
                           value="<?php echo esc_attr($payment_data['final_payment_amount']); ?>" min="0" step="0.01" class="regular-text"></td>
            </tr>
            
            <tr>
                <th><label for="final_payment_status">Final Payment Status</label></th>
                <td>
                    <select name="final_payment_status" id="final_payment_status" class="regular-text">
                        <option value="pending" <?php selected($payment_data['final_payment_status'], 'pending'); ?>>Pending</option>
                        <option value="due" <?php selected($payment_data['final_payment_status'], 'due'); ?>>Due</option>
                        <option value="paid" <?php selected($payment_data['final_payment_status'], 'paid'); ?>>Paid</option>
                        <option value="overdue" <?php selected($payment_data['final_payment_status'], 'overdue'); ?>>Overdue</option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th><label for="final_payment_due_date">Final Payment Due Date</label></th>
                <td><input type="date" name="final_payment_due_date" id="final_payment_due_date" 
                           value="<?php echo esc_attr($payment_data['final_payment_due_date']); ?>" class="regular-text"></td>
            </tr>
            
            <tr>
                <th><label for="final_payment_paid_date">Final Payment Paid Date</label></th>
                <td><input type="datetime-local" name="final_payment_paid_date" id="final_payment_paid_date" 
                           value="<?php echo esc_attr($payment_data['final_payment_paid_date']); ?>" class="regular-text"></td>
            </tr>
            
            <tr>
                <th><label for="stripe_payment_intent_id">Stripe Payment Intent ID</label></th>
                <td><input type="text" name="stripe_payment_intent_id" id="stripe_payment_intent_id" 
                           value="<?php echo esc_attr($payment_data['stripe_payment_intent_id']); ?>" class="regular-text" readonly></td>
            </tr>
            
            <tr>
                <th><label for="commission_amount">Agency Commission (£)</label></th>
                <td><input type="number" name="commission_amount" id="commission_amount" 
                           value="<?php echo esc_attr($payment_data['commission_amount']); ?>" min="0" step="0.01" class="regular-text" readonly></td>
            </tr>
            
            <tr>
                <th><label for="dj_earnings">DJ Earnings (£)</label></th>
                <td><input type="number" name="dj_earnings" id="dj_earnings" 
                           value="<?php echo esc_attr($payment_data['dj_earnings']); ?>" min="0" step="0.01" class="regular-text" readonly></td>
            </tr>
        </table>
        <?php
    }
    
    public function render_communication_log_meta_box($post) {
        $communication_log = get_post_meta($post->ID, 'communication_log', true);
        $communication_log = $communication_log ? json_decode($communication_log, true) : array();
        
        ?>
        <div id="communication-log">
            <div id="log-entries">
                <?php foreach ($communication_log as $entry): ?>
                    <div class="log-entry" style="border-bottom: 1px solid #ddd; padding: 10px 0;">
                        <strong><?php echo esc_html($entry['timestamp']); ?></strong><br>
                        <em><?php echo esc_html($entry['type']); ?></em><br>
                        <?php echo esc_html($entry['message']); ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div id="add-log-entry" style="margin-top: 15px;">
                <h4>Add Communication Entry</h4>
                <select id="log-type" class="regular-text">
                    <option value="email">Email</option>
                    <option value="phone">Phone Call</option>
                    <option value="sms">SMS</option>
                    <option value="system">System Action</option>
                    <option value="note">Internal Note</option>
                </select><br><br>
                
                <textarea id="log-message" rows="3" class="large-text" placeholder="Enter communication details..."></textarea><br><br>
                
                <button type="button" id="add-log-btn" class="button">Add Entry</button>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#add-log-btn').click(function() {
                const type = $('#log-type').val();
                const message = $('#log-message').val();
                
                if (!message.trim()) {
                    alert('Please enter a message');
                    return;
                }
                
                const timestamp = new Date().toLocaleString();
                const entryHtml = `
                    <div class="log-entry" style="border-bottom: 1px solid #ddd; padding: 10px 0;">
                        <strong>${timestamp}</strong><br>
                        <em>${type}</em><br>
                        ${message}
                    </div>
                `;
                
                $('#log-entries').append(entryHtml);
                $('#log-message').val('');
                
                // Add hidden input to save with post
                $('<input>').attr({
                    type: 'hidden',
                    name: 'new_communication_log[]',
                    value: JSON.stringify({
                        timestamp: timestamp,
                        type: type,
                        message: message
                    })
                }).appendTo('#communication-log');
            });
        });
        </script>
        <?php
    }
    
    public function create_booking($booking_data = null) {
        if ($booking_data === null) {
            $booking_data = $_POST;
        }
        
        // Validate required fields
        $required_fields = array('client_name', 'client_email', 'event_date', 'dj_id');
        foreach ($required_fields as $field) {
            if (empty($booking_data[$field])) {
                return array('success' => false, 'message' => 'Missing required field: ' . $field);
            }
        }
        
        // Sanitize input data
        $client_name = sanitize_text_field($booking_data['client_name']);
        $client_email = sanitize_email($booking_data['client_email']);
        $client_phone = sanitize_text_field($booking_data['client_phone'] ?? '');
        $event_date = sanitize_text_field($booking_data['event_date']);
        $event_time = sanitize_text_field($booking_data['event_time'] ?? '');
        $dj_id = intval($booking_data['dj_id']);
        $venue_postcode = sanitize_text_field($booking_data['venue_postcode'] ?? '');
        $event_duration = intval($booking_data['event_duration'] ?? 4);
        $package_id = sanitize_text_field($booking_data['package_id'] ?? '');
        
        // Check DJ availability
        $calendar_manager = new DJ_Calendar_Manager();
        if (!$calendar_manager->check_availability($dj_id, $event_date)) {
            return array('success' => false, 'message' => 'Selected DJ is not available on this date');
        }
        
        // Calculate pricing
        $profile_manager = new DJ_Profile_Manager();
        $pricing = $this->calculate_booking_price($dj_id, $event_date, $venue_postcode, $event_duration, $package_id);
        
        // Determine if international booking
        $is_international = $this->is_international_booking($venue_postcode);
        $initial_status = $is_international ? 'international_pending' : 'enquiry';
        
        // Create booking post
        $booking_post = array(
            'post_title' => $client_name . ' - ' . $event_date,
            'post_type' => 'dj_booking',
            'post_status' => $initial_status,
            'meta_input' => array(
                'client_name' => $client_name,
                'client_email' => $client_email,
                'client_phone' => $client_phone,
                'assigned_dj' => $dj_id,
                'event_date' => $event_date,
                'event_time' => $event_time,
                'event_duration' => $event_duration,
                'venue_postcode' => $venue_postcode,
                'total_amount' => $pricing['total_cost'],
                'deposit_amount' => $pricing['deposit_amount'],
                'final_payment_amount' => $pricing['final_payment'],
                'commission_amount' => $pricing['agency_commission'],
                'dj_earnings' => $pricing['dj_earnings'],
                'booking_source' => $booking_data['booking_source'] ?? 'website',
                'package_id' => $package_id,
                'booking_created_date' => current_time('mysql'),
                'details_completed' => '0'
            )
        );
        
        $booking_id = wp_insert_post($booking_post);
        
        if (is_wp_error($booking_id)) {
            return array('success' => false, 'message' => 'Failed to create booking');
        }
        
        // Set final payment due date (1 month before event)
        $event_timestamp = strtotime($event_date);
        $final_payment_due = date('Y-m-d', strtotime('-1 month', $event_timestamp));
        update_post_meta($booking_id, 'final_payment_due_date', $final_payment_due);
        
        // Reserve DJ availability
        $calendar_manager->reserve_date($dj_id, $event_date, $booking_id);
        
        // Create communication log entry
        $this->add_communication_log($booking_id, 'system', 'Booking created via ' . ($booking_data['booking_source'] ?? 'website'));
        
        // Send to GoHighLevel
        $ghl_integration = new GHL_Integration();
        $ghl_contact_id = $ghl_integration->create_or_update_contact(array(
            'email' => $client_email,
            'name' => $client_name,
            'phone' => $client_phone,
            'booking_id' => $booking_id,
            'event_date' => $event_date,
            'dj_name' => get_the_title($dj_id),
            'total_amount' => $pricing['total_cost'],
            'booking_status' => $initial_status
        ));
        
        if ($ghl_contact_id) {
            update_post_meta($booking_id, 'ghl_contact_id', $ghl_contact_id);
            
            // Trigger appropriate workflow
            if ($is_international) {
                $ghl_integration->trigger_workflow('international_booking_workflow', $ghl_contact_id);
            } else {
                $ghl_integration->trigger_workflow('new_booking_workflow', $ghl_contact_id);
            }
        }
        
        // Send event details form to client
        $this->send_event_details_form($booking_id);
        
        // Monitor for safeguards
        $safeguards_monitor = new DJ_Safeguards_Monitor();
        $safeguards_monitor->log_enquiry($dj_id, $event_date, $booking_id);
        
        return array(
            'success' => true, 
            'booking_id' => $booking_id,
            'message' => 'Booking created successfully',
            'redirect_url' => $this->get_booking_confirmation_url($booking_id)
        );
    }
    
    private function calculate_booking_price($dj_id, $event_date, $venue_postcode, $event_duration, $package_id) {
        $profile_manager = new DJ_Profile_Manager();
        $profile = $profile_manager->get_dj_profile($dj_id);
        
        // Calculate base rate
        $base_rate = 0;
        if (!empty($package_id)) {
            foreach ($profile['booking_packages'] as $package) {
                if ($package['name'] === $package_id) {
                    $base_rate = $package['price'];
                    break;
                }
            }
        } else {
            if ($profile['event_rate'] > 0) {
                $base_rate = $profile['event_rate'];
            } else {
                $base_rate = $profile['hourly_rate'] * $event_duration;
            }
        }
        
        // Calculate travel and accommodation
        $travel_cost = 0;
        $accommodation_cost = 0;
        $distance = 0;
        
        if (!empty($venue_postcode) && !empty($profile['base_postcode'])) {
            $distance_calculator = new Distance_Calculator();
            $distance = $distance_calculator->calculate_distance($profile['base_postcode'], $venue_postcode);
            
            if ($distance > $profile['travel_free_miles']) {
                $billable_miles = $distance - $profile['travel_free_miles'];
                $travel_cost = $billable_miles * $profile['travel_rate'] * 2; // Return journey
            }
            
            if ($distance > 250) {
                $accommodation_cost = $profile['accommodation_rate'];
            }
        }
        
        $total_cost = $base_rate + $travel_cost + $accommodation_cost;
        $agency_commission = $total_cost * 0.25; // 25% commission
        $deposit_amount = $total_cost * 0.5; // 50% deposit
        
        return array(
            'base_rate' => $base_rate,
            'travel_cost' => $travel_cost,
            'accommodation_cost' => $accommodation_cost,
            'total_cost' => $total_cost,
            'agency_commission' => $agency_commission,
            'dj_earnings' => $total_cost - $agency_commission,
            'deposit_amount' => $deposit_amount,
            'final_payment' => $total_cost - $deposit_amount,
            'distance' => $distance
        );
    }
    
    private function is_international_booking($postcode) {
        // Simple UK postcode validation
        $uk_pattern = '/^[A-Z]{1,2}[0-9R][0-9A-Z]? [0-9][ABD-HJLNP-UW-Z]{2}$/i';
        return !preg_match($uk_pattern, trim($postcode));
    }
    
    private function send_event_details_form($booking_id) {
        $booking_data = $this->get_booking_data($booking_id);
        
        $form_url = add_query_arg(array(
            'booking_id' => $booking_id,
            'token' => wp_create_nonce('event_details_' . $booking_id)
        ), home_url('/event-details-form/'));
        
        $email_template = new Email_Templates();
        $email_content = $email_template->get_event_details_form_email($booking_data, $form_url);
        
        wp_mail(
            $booking_data['client_email'],
            'Please Complete Your Event Details - ' . get_bloginfo('name'),
            $email_content,
            array('Content-Type: text/html; charset=UTF-8')
        );
        
        $this->add_communication_log($booking_id, 'email', 'Event details form sent to client');
        
        // Update booking status
        wp_update_post(array(
            'ID' => $booking_id,
            'post_status' => 'pending_details'
        ));
    }
    
    public function update_event_details() {
        check_ajax_referer('dj_hire_nonce', 'nonce');
        
        $booking_id = intval($_POST['booking_id']);
        $token = sanitize_text_field($_POST['token']);
        
        // Verify token
        if (!wp_verify_nonce($token, 'event_details_' . $booking_id)) {
            wp_send_json_error('Invalid security token');
        }
        
        // Update event details
        $event_details = array(
            'event_type' => sanitize_text_field($_POST['event_type'] ?? ''),
            'venue_name' => sanitize_text_field($_POST['venue_name'] ?? ''),
            'venue_address' => sanitize_textarea_field($_POST['venue_address'] ?? ''),
            'venue_postcode' => sanitize_text_field($_POST['venue_postcode'] ?? ''),
            'guest_count' => intval($_POST['guest_count'] ?? 0),
            'age_range' => sanitize_text_field($_POST['age_range'] ?? ''),
            'music_preferences' => sanitize_textarea_field($_POST['music_preferences'] ?? ''),
            'must_play_list' => sanitize_textarea_field($_POST['must_play_list'] ?? ''),
            'do_not_play_list' => sanitize_textarea_field($_POST['do_not_play_list'] ?? ''),
            'spotify_playlists' => sanitize_textarea_field($_POST['spotify_playlists'] ?? ''),
            'event_timeline' => sanitize_textarea_field($_POST['event_timeline'] ?? ''),
            'special_requirements' => sanitize_textarea_field($_POST['special_requirements'] ?? ''),
            'equipment_requirements' => sanitize_textarea_field($_POST['equipment_requirements'] ?? ''),
            'details_completed' => '1',
            'details_completed_date' => current_time('mysql')
        );
        
        foreach ($event_details as $key => $value) {
            update_post_meta($booking_id, $key, $value);
        }
        
        $this->add_communication_log($booking_id, 'system', 'Client completed event details form');
        
        // Update booking status and trigger DJ call task
        wp_update_post(array(
            'ID' => $booking_id,
            'post_status' => 'pending_call'
        ));
        
        // Trigger DJ call task in GoHighLevel
        $ghl_contact_id = get_post_meta($booking_id, 'ghl_contact_id', true);
        if ($ghl_contact_id) {
            $ghl_integration = new GHL_Integration();
            $ghl_integration->create_task_for_dj($booking_id, $ghl_contact_id);
        }
        
        wp_send_json_success('Event details updated successfully');
    }
    
    public function process_stripe_payment() {
        check_ajax_referer('dj_hire_nonce', 'nonce');
        
        $booking_id = intval($_POST['booking_id']);
        $payment_type = sanitize_text_field($_POST['payment_type']); // 'deposit' or 'final'
        $amount = floatval($_POST['amount']);
        
        // Get Stripe configuration
        $stripe_secret_key = get_option('dj_hire_stripe_secret_key');
        if (empty($stripe_secret_key)) {
            wp_send_json_error('Stripe not configured');
        }
        
        \Stripe\Stripe::setApiKey($stripe_secret_key);
        
        try {
            // Create payment intent
            $intent = \Stripe\PaymentIntent::create([
                'amount' => $amount * 100, // Convert to pence
                'currency' => 'gbp',
                'metadata' => [
                    'booking_id' => $booking_id,
                    'payment_type' => $payment_type
                ]
            ]);
            
            // Update booking with payment intent
            update_post_meta($booking_id, 'stripe_payment_intent_id', $intent->id);
            
            wp_send_json_success(array(
                'client_secret' => $intent->client_secret
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Payment processing failed: ' . $e->getMessage());
        }
    }
    
    public function confirm_payment($booking_id, $payment_type, $stripe_payment_intent_id) {
        $current_time = current_time('mysql');
        
        if ($payment_type === 'deposit') {
            update_post_meta($booking_id, 'deposit_status', 'paid');
            update_post_meta($booking_id, 'deposit_paid_date', $current_time);
            
            // Update booking status
            wp_update_post(array(
                'ID' => $booking_id,
                'post_status' => 'confirmed'
            ));
            
            $this->add_communication_log($booking_id, 'system', 'Deposit payment confirmed');
            
        } else if ($payment_type === 'final') {
            update_post_meta($booking_id, 'final_payment_status', 'paid');
            update_post_meta($booking_id, 'final_payment_paid_date', $current_time);
            
            // Update booking status
            wp_update_post(array(
                'ID' => $booking_id,
                'post_status' => 'paid_in_full'
            ));
            
            $this->add_communication_log($booking_id, 'system', 'Final payment confirmed');
            
            // Record commission
            $this->record_commission($booking_id);
        }
        
        // Update GoHighLevel
        $ghl_contact_id = get_post_meta($booking_id, 'ghl_contact_id', true);
        if ($ghl_contact_id) {
            $ghl_integration = new GHL_Integration();
            $ghl_integration->update_booking_status($ghl_contact_id, $booking_id, $payment_type . '_paid');
        }
    }
    
    private function record_commission($booking_id) {
        global $wpdb;
        
        $booking_data = $this->get_booking_data($booking_id);
        $table_name = $wpdb->prefix . 'dj_commissions';
        
        $wpdb->insert(
            $table_name,
            array(
                'booking_id' => $booking_id,
                'dj_id' => $booking_data['assigned_dj'],
                'total_amount' => $booking_data['total_amount'],
                'agency_commission' => $booking_data['commission_amount'],
                'dj_earnings' => $booking_data['dj_earnings'],
                'status' => 'completed'
            ),
            array('%d', '%d', '%f', '%f', '%f', '%s')
        );
    }
    
    public function send_payment_reminders() {
        // Find bookings where final payment is due in 2 weeks
        $two_weeks_from_now = date('Y-m-d', strtotime('+2 weeks'));
        
        $bookings = get_posts(array(
            'post_type' => 'dj_booking',
            'posts_per_page' => -1,
            'post_status' => array('confirmed', 'deposit_paid'),
            'meta_query' => array(
                array(
                    'key' => 'final_payment_due_date',
                    'value' => $two_weeks_from_now,
                    'compare' => '='
                ),
                array(
                    'key' => 'final_payment_status',
                    'value' => 'paid',
                    'compare' => '!='
                )
            )
        ));
        
        foreach ($bookings as $booking) {
            $this->send_payment_reminder($booking->ID);
        }
        
        // Find overdue payments
        $today = date('Y-m-d');
        $overdue_bookings = get_posts(array(
            'post_type' => 'dj_booking',
            'posts_per_page' => -1,
            'post_status' => array('confirmed', 'deposit_paid'),
            'meta_query' => array(
                array(
                    'key' => 'final_payment_due_date',
                    'value' => $today,
                    'compare' => '<'
                ),
                array(
                    'key' => 'final_payment_status',
                    'value' => 'paid',
                    'compare' => '!='
                )
            )
        ));
        
        foreach ($overdue_bookings as $booking) {
            update_post_meta($booking->ID, 'final_payment_status', 'overdue');
            wp_update_post(array(
                'ID' => $booking->ID,
                'post_status' => 'final_payment_due'
            ));
        }
    }
    
    private function send_payment_reminder($booking_id) {
        $booking_data = $this->get_booking_data($booking_id);
        
        $payment_url = add_query_arg(array(
            'booking_id' => $booking_id,
            'token' => wp_create_nonce('payment_' . $booking_id)
        ), home_url('/payment/'));
        
        $email_template = new Email_Templates();
        $email_content = $email_template->get_payment_reminder_email($booking_data, $payment_url);
        
        wp_mail(
            $booking_data['client_email'],
            'Final Payment Due - ' . get_bloginfo('name'),
            $email_content,
            array('Content-Type: text/html; charset=UTF-8')
        );
        
        $this->add_communication_log($booking_id, 'email', 'Payment reminder sent');
        
        // Update status
        update_post_meta($booking_id, 'final_payment_status', 'due');
        wp_update_post(array(
            'ID' => $booking_id,
            'post_status' => 'final_payment_due'
        ));
    }
    
    private function add_communication_log($booking_id, $type, $message) {
        $existing_log = get_post_meta($booking_id, 'communication_log', true);
        $log = $existing_log ? json_decode($existing_log, true) : array();
        
        $log[] = array(
            'timestamp' => current_time('mysql'),
            'type' => $type,
            'message' => $message
        );
        
        update_post_meta($booking_id, 'communication_log', json_encode($log));
    }
    
    public function save_booking_meta($post_id) {
        if (!isset($_POST['booking_meta_nonce']) || !wp_verify_nonce($_POST['booking_meta_nonce'], 'booking_meta_nonce')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save booking details
        $booking_fields = array(
            'client_name', 'client_email', 'client_phone', 'assigned_dj',
            'event_date', 'event_time', 'event_duration', 'total_amount',
            'booking_source', 'ghl_contact_id'
        );
        
        foreach ($booking_fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
            }
        }
        
        // Save event details
        $event_fields = array(
            'event_type', 'venue_name', 'venue_address', 'venue_postcode',
            'guest_count', 'age_range', 'music_preferences', 'must_play_list',
            'do_not_play_list', 'spotify_playlists', 'event_timeline',
            'special_requirements', 'equipment_requirements', 'details_completed'
        );
        
        foreach ($event_fields as $field) {
            if (isset($_POST[$field])) {
                if (in_array($field, array('venue_address', 'music_preferences', 'must_play_list', 'do_not_play_list', 'spotify_playlists', 'event_timeline', 'special_requirements', 'equipment_requirements'))) {
                    update_post_meta($post_id, $field, sanitize_textarea_field($_POST[$field]));
                } else {
                    update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
                }
            }
        }
        
        // Save payment details
        $payment_fields = array(
            'deposit_amount', 'deposit_status', 'deposit_paid_date',
            'final_payment_amount', 'final_payment_status', 'final_payment_due_date',
            'final_payment_paid_date', 'stripe_payment_intent_id',
            'commission_amount', 'dj_earnings'
        );
        
        foreach ($payment_fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
            }
        }
        
        // Save communication log entries
        if (isset($_POST['new_communication_log']) && is_array($_POST['new_communication_log'])) {
            $existing_log = get_post_meta($post_id, 'communication_log', true);
            $log = $existing_log ? json_decode($existing_log, true) : array();
            
            foreach ($_POST['new_communication_log'] as $entry_json) {
                $entry = json_decode(stripslashes($entry_json), true);
                if ($entry) {
                    $log[] = array(
                        'timestamp' => sanitize_text_field($entry['timestamp']),
                        'type' => sanitize_text_field($entry['type']),
                        'message' => sanitize_textarea_field($entry['message'])
                    );
                }
            }
            
            update_post_meta($post_id, 'communication_log', json_encode($log));
        }
    }
    
    private function get_booking_data($booking_id) {
        $post = get_post($booking_id);
        $meta = get_post_meta($booking_id);
        
        return array(
            'booking_id' => $booking_id,
            'client_name' => $meta['client_name'][0] ?? '',
            'client_email' => $meta['client_email'][0] ?? '',
            'client_phone' => $meta['client_phone'][0] ?? '',
            'assigned_dj' => $meta['assigned_dj'][0] ?? '',
            'event_date' => $meta['event_date'][0] ?? '',
            'event_time' => $meta['event_time'][0] ?? '',
            'event_duration' => $meta['event_duration'][0] ?? '',
            'total_amount' => $meta['total_amount'][0] ?? '',
            'booking_source' => $meta['booking_source'][0] ?? '',
            'ghl_contact_id' => $meta['ghl_contact_id'][0] ?? '',
            'package_id' => $meta['package_id'][0] ?? '',
            'venue_postcode' => $meta['venue_postcode'][0] ?? '',
            'commission_amount' => $meta['commission_amount'][0] ?? '',
            'dj_earnings' => $meta['dj_earnings'][0] ?? '',
            'deposit_amount' => $meta['deposit_amount'][0] ?? '',
            'final_payment_amount' => $meta['final_payment_amount'][0] ?? '',
            'status' => $post->post_status ?? ''
        );
    }
    
    private function get_event_details($booking_id) {
        $meta = get_post_meta($booking_id);
        
        return array(
            'event_type' => $meta['event_type'][0] ?? '',
            'venue_name' => $meta['venue_name'][0] ?? '',
            'venue_address' => $meta['venue_address'][0] ?? '',
            'venue_postcode' => $meta['venue_postcode'][0] ?? '',
            'guest_count' => $meta['guest_count'][0] ?? '',
            'age_range' => $meta['age_range'][0] ?? '',
            'music_preferences' => $meta['music_preferences'][0] ?? '',
            'must_play_list' => $meta['must_play_list'][0] ?? '',
            'do_not_play_list' => $meta['do_not_play_list'][0] ?? '',
            'spotify_playlists' => $meta['spotify_playlists'][0] ?? '',
            'event_timeline' => $meta['event_timeline'][0] ?? '',
            'special_requirements' => $meta['special_requirements'][0] ?? '',
            'equipment_requirements' => $meta['equipment_requirements'][0] ?? '',
            'details_completed' => $meta['details_completed'][0] ?? ''
        );
    }
    
    private function get_payment_data($booking_id) {
        $meta = get_post_meta($booking_id);
        
        return array(
            'deposit_amount' => $meta['deposit_amount'][0] ?? '',
            'deposit_status' => $meta['deposit_status'][0] ?? 'pending',
            'deposit_paid_date' => $meta['deposit_paid_date'][0] ?? '',
            'final_payment_amount' => $meta['final_payment_amount'][0] ?? '',
            'final_payment_status' => $meta['final_payment_status'][0] ?? 'pending',
            'final_payment_due_date' => $meta['final_payment_due_date'][0] ?? '',
            'final_payment_paid_date' => $meta['final_payment_paid_date'][0] ?? '',
            'stripe_payment_intent_id' => $meta['stripe_payment_intent_id'][0] ?? '',
            'commission_amount' => $meta['commission_amount'][0] ?? '',
            'dj_earnings' => $meta['dj_earnings'][0] ?? ''
        );
    }
    
    private function get_booking_confirmation_url($booking_id) {
        return add_query_arg(array(
            'booking_id' => $booking_id,
            'token' => wp_create_nonce('booking_confirmation_' . $booking_id)
        ), home_url('/booking-confirmation/'));
    }
}
?>