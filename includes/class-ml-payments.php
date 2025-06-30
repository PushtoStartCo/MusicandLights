<?php
/**
 * Music & Lights Payments Class - Stripe Integration
 * 
 * Handles all payment processing functionality including Stripe integration,
 * deposit processing, and payment tracking.
 * 
 * @package MusicAndLights
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class ML_Payments {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Stripe API key
     */
    private $stripe_secret_key;
    
    /**
     * Stripe publishable key
     */
    private $stripe_publishable_key;
    
    /**
     * Test mode flag
     */
    private $test_mode;
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
        $this->init_hooks();
    }
    
    /**
     * Initialize settings
     */
    private function init() {
        $settings = get_option('ml_settings', array());
        $this->test_mode = isset($settings['stripe_test_mode']) ? $settings['stripe_test_mode'] : true;
        
        if ($this->test_mode) {
            $this->stripe_secret_key = isset($settings['stripe_test_secret_key']) ? $settings['stripe_test_secret_key'] : '';
            $this->stripe_publishable_key = isset($settings['stripe_test_publishable_key']) ? $settings['stripe_test_publishable_key'] : '';
        } else {
            $this->stripe_secret_key = isset($settings['stripe_live_secret_key']) ? $settings['stripe_live_secret_key'] : '';
            $this->stripe_publishable_key = isset($settings['stripe_live_publishable_key']) ? $settings['stripe_live_publishable_key'] : '';
        }
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_ml_process_payment', array($this, 'process_payment'));
        add_action('wp_ajax_nopriv_ml_process_payment', array($this, 'process_payment'));
        add_action('wp_ajax_ml_process_deposit', array($this, 'process_deposit'));
        add_action('wp_ajax_nopriv_ml_process_deposit', array($this, 'process_deposit'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_stripe_scripts'));
    }
    
    /**
     * Enqueue Stripe scripts
     */
    public function enqueue_stripe_scripts() {
        if (is_page('book-dj') || is_admin()) {
            wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', array(), '3.0', true);
            wp_localize_script('stripe-js', 'ml_stripe', array(
                'publishable_key' => $this->stripe_publishable_key,
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ml_payment_nonce')
            ));
        }
    }
    
    /**
     * Create payment intent for deposit
     */
    public function create_payment_intent($booking_id, $amount, $currency = 'gbp') {
        if (!$this->stripe_secret_key) {
            return new WP_Error('no_stripe_key', 'Stripe API key not configured');
        }
        
        try {
            $this->init_stripe();
            
            $booking = $this->get_booking($booking_id);
            if (!$booking) {
                return new WP_Error('invalid_booking', 'Booking not found');
            }
            
            $intent = \Stripe\PaymentIntent::create([
                'amount' => $amount * 100, // Convert to pence
                'currency' => $currency,
                'metadata' => [
                    'booking_id' => $booking_id,
                    'customer_email' => $booking->email,
                    'customer_name' => $booking->first_name . ' ' . $booking->last_name,
                    'event_date' => $booking->event_date,
                    'payment_type' => 'deposit'
                ],
                'description' => 'DJ Booking Deposit - Event Date: ' . $booking->event_date
            ]);
            
            return $intent;
            
        } catch (\Stripe\Exception\CardException $e) {
            return new WP_Error('card_error', $e->getError()->message);
        } catch (\Stripe\Exception\RateLimitException $e) {
            return new WP_Error('rate_limit', 'Too many requests to Stripe API');
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            return new WP_Error('invalid_request', 'Invalid request to Stripe API');
        } catch (\Stripe\Exception\AuthenticationException $e) {
            return new WP_Error('auth_error', 'Stripe authentication failed');
        } catch (\Stripe\Exception\ApiConnectionException $e) {
            return new WP_Error('connection_error', 'Network communication with Stripe failed');
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return new WP_Error('api_error', 'Stripe API error occurred');
        } catch (Exception $e) {
            return new WP_Error('general_error', $e->getMessage());
        }
    }
    
    /**
     * Process deposit payment
     */
    public function process_deposit() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'ml_payment_nonce')) {
            wp_die('Security check failed');
        }
        
        $booking_id = intval($_POST['booking_id']);
        $payment_intent_id = sanitize_text_field($_POST['payment_intent_id']);
        
        if (!$booking_id || !$payment_intent_id) {
            wp_send_json_error('Missing required parameters');
            return;
        }
        
        try {
            $this->init_stripe();
            
            // Retrieve the payment intent from Stripe
            $intent = \Stripe\PaymentIntent::retrieve($payment_intent_id);
            
            if ($intent->status === 'succeeded') {
                // Update booking with payment details
                $this->update_booking_payment($booking_id, array(
                    'deposit_amount' => $intent->amount / 100,
                    'deposit_paid' => 1,
                    'deposit_payment_id' => $payment_intent_id,
                    'deposit_paid_date' => current_time('mysql'),
                    'status' => 'confirmed'
                ));
                
                // Log the payment
                $this->log_payment($booking_id, 'deposit', $intent->amount / 100, $payment_intent_id, 'completed');
                
                // Send confirmation emails
                do_action('ml_booking_deposit_paid', $booking_id);
                
                wp_send_json_success(array(
                    'message' => 'Deposit payment successful',
                    'booking_id' => $booking_id,
                    'amount' => $intent->amount / 100
                ));
            } else {
                wp_send_json_error('Payment not completed');
            }
            
        } catch (Exception $e) {
            wp_send_json_error('Payment processing failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Process final payment
     */
    public function process_payment() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'ml_payment_nonce')) {
            wp_die('Security check failed');
        }
        
        $booking_id = intval($_POST['booking_id']);
        $amount = floatval($_POST['amount']);
        
        if (!$booking_id || !$amount) {
            wp_send_json_error('Missing required parameters');
            return;
        }
        
        $intent = $this->create_payment_intent($booking_id, $amount);
        
        if (is_wp_error($intent)) {
            wp_send_json_error($intent->get_error_message());
            return;
        }
        
        wp_send_json_success(array(
            'client_secret' => $intent->client_secret,
            'payment_intent_id' => $intent->id
        ));
    }
    
    /**
     * Create refund
     */
    public function create_refund($payment_intent_id, $amount = null, $reason = 'requested_by_customer') {
        if (!$this->stripe_secret_key) {
            return new WP_Error('no_stripe_key', 'Stripe API key not configured');
        }
        
        try {
            $this->init_stripe();
            
            $refund_data = array(
                'payment_intent' => $payment_intent_id,
                'reason' => $reason
            );
            
            if ($amount) {
                $refund_data['amount'] = $amount * 100; // Convert to pence
            }
            
            $refund = \Stripe\Refund::create($refund_data);
            
            return $refund;
            
        } catch (Exception $e) {
            return new WP_Error('refund_error', $e->getMessage());
        }
    }
    
    /**
     * Get payment history for booking
     */
    public function get_payment_history($booking_id) {
        global $wpdb;
        
        $payments = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ml_payments WHERE booking_id = %d ORDER BY created_at DESC",
                $booking_id
            )
        );
        
        return $payments;
    }
    
    /**
     * Initialize Stripe API
     */
    private function init_stripe() {
        if (!class_exists('\Stripe\Stripe')) {
            require_once(plugin_dir_path(__FILE__) . '../vendor/stripe/stripe-php/init.php');
        }
        
        \Stripe\Stripe::setApiKey($this->stripe_secret_key);
    }
    
    /**
     * Get booking details
     */
    private function get_booking($booking_id) {
        global $wpdb;
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ml_bookings WHERE id = %d",
                $booking_id
            )
        );
    }
    
    /**
     * Update booking payment details
     */
    private function update_booking_payment($booking_id, $payment_data) {
        global $wpdb;
        
        $wpdb->update(
            $wpdb->prefix . 'ml_bookings',
            $payment_data,
            array('id' => $booking_id),
            array('%f', '%d', '%s', '%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * Log payment transaction
     */
    private function log_payment($booking_id, $type, $amount, $transaction_id, $status) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'ml_payments',
            array(
                'booking_id' => $booking_id,
                'payment_type' => $type,
                'amount' => $amount,
                'transaction_id' => $transaction_id,
                'status' => $status,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%f', '%s', '%s', '%s')
        );
    }
    
    /**
     * Get Stripe publishable key
     */
    public function get_publishable_key() {
        return $this->stripe_publishable_key;
    }
    
    /**
     * Check if Stripe is configured
     */
    public function is_configured() {
        return !empty($this->stripe_secret_key) && !empty($this->stripe_publishable_key);
    }
}

// Initialize the payments class
ML_Payments::get_instance();