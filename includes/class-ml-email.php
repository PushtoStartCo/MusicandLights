<?php
/**
 * Music & Lights Email Class - Template System
 * 
 * Handles all email functionality including template management,
 * automated notifications, and email tracking.
 * 
 * @package MusicAndLights
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class ML_Email {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Email settings
     */
    private $from_email;
    private $from_name;
    private $logo_url;
    
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
        $this->from_email = isset($settings['email_from']) ? $settings['email_from'] : get_option('admin_email');
        $this->from_name = isset($settings['email_from_name']) ? $settings['email_from_name'] : get_option('blogname');
        $this->logo_url = isset($settings['company_logo']) ? $settings['company_logo'] : '';
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('ml_booking_created', array($this, 'send_booking_confirmation'), 10, 1);
        add_action('ml_booking_deposit_paid', array($this, 'send_deposit_confirmation'), 10, 1);
        add_action('ml_booking_status_changed', array($this, 'send_status_update'), 10, 2);
        add_action('ml_commission_payment', array($this, 'send_commission_notification'), 10, 2);
        add_action('ml_dj_assigned', array($this, 'send_dj_notification'), 10, 2);
        add_filter('wp_mail_from', array($this, 'set_from_email'));
        add_filter('wp_mail_from_name', array($this, 'set_from_name'));
    }
    
    /**
     * Set from email
     */
    public function set_from_email($email) {
        return $this->from_email;
    }
    
    /**
     * Set from name
     */
    public function set_from_name($name) {
        return $this->from_name;
    }
    
    /**
     * Send booking confirmation email
     */
    public function send_booking_confirmation($booking_id) {
        $booking = $this->get_booking($booking_id);
        if (!$booking) {
            return false;
        }
        
        $subject = 'Booking Confirmation - ' . $booking->event_date;
        $template_data = array(
            'booking' => $booking,
            'customer_name' => $booking->first_name . ' ' . $booking->last_name,
            'event_date' => date('jS F Y', strtotime($booking->event_date)),
            'event_time' => $booking->event_time,
            'venue' => $booking->venue_name,
            'total_cost' => '£' . number_format($booking->total_cost, 2),
            'deposit_amount' => '£' . number_format($booking->deposit_amount, 2)
        );
        
        $message = $this->get_template('booking-confirmation', $template_data);
        
        $sent = $this->send_email($booking->email, $subject, $message);
        $this->log_email($booking_id, $booking->email, $subject, $sent ? 'sent' : 'failed');
        
        return $sent;
    }
    
    /**
     * Send deposit confirmation email
     */
    public function send_deposit_confirmation($booking_id) {
        $booking = $this->get_booking($booking_id);
        if (!$booking) {
            return false;
        }
        
        $subject = 'Deposit Received - Booking Confirmed';
        $template_data = array(
            'booking' => $booking,
            'customer_name' => $booking->first_name . ' ' . $booking->last_name,
            'deposit_amount' => '£' . number_format($booking->deposit_amount, 2),
            'remaining_balance' => '£' . number_format($booking->total_cost - $booking->deposit_amount, 2),
            'event_date' => date('jS F Y', strtotime($booking->event_date))
        );
        
        $message = $this->get_template('deposit-confirmation', $template_data);
        
        $sent = $this->send_email($booking->email, $subject, $message);
        $this->log_email($booking_id, $booking->email, $subject, $sent ? 'sent' : 'failed');
        
        return $sent;
    }
    
    /**
     * Send DJ notification email
     */
    public function send_dj_notification($booking_id, $dj_id) {
        $booking = $this->get_booking($booking_id);
        $dj = $this->get_dj($dj_id);
        
        if (!$booking || !$dj) {
            return false;
        }
        
        $subject = 'New Booking Assignment - ' . $booking->event_date;
        $template_data = array(
            'booking' => $booking,
            'dj' => $dj,
            'dj_name' => $dj->first_name . ' ' . $dj->last_name,
            'event_date' => date('jS F Y', strtotime($booking->event_date)),
            'event_time' => $booking->event_time,
            'venue' => $booking->venue_name,
            'customer_name' => $booking->first_name . ' ' . $booking->last_name,
            'customer_phone' => $booking->phone,
            'special_requests' => $booking->special_requests,
            'commission_amount' => '£' . number_format($booking->total_cost * 0.75, 2) // DJ gets 75%
        );
        
        $message = $this->get_template('dj-notification', $template_data);
        
        $sent = $this->send_email($dj->email, $subject, $message);
        $this->log_email($booking_id, $dj->email, $subject, $sent ? 'sent' : 'failed', 'dj');
        
        return $sent;
    }
    
    /**
     * Send status update email
     */
    public function send_status_update($booking_id, $new_status) {
        $booking = $this->get_booking($booking_id);
        if (!$booking) {
            return false;
        }
        
        $status_messages = array(
            'confirmed' => 'Your booking has been confirmed!',
            'cancelled' => 'Your booking has been cancelled',
            'completed' => 'Thank you for choosing Music & Lights',
            'pending' => 'Your booking is being processed'
        );
        
        $subject = 'Booking Update - ' . ucfirst($new_status);
        $template_data = array(
            'booking' => $booking,
            'customer_name' => $booking->first_name . ' ' . $booking->last_name,
            'status' => $new_status,
            'status_message' => isset($status_messages[$new_status]) ? $status_messages[$new_status] : 'Your booking status has been updated',
            'event_date' => date('jS F Y', strtotime($booking->event_date))
        );
        
        $message = $this->get_template('status-update', $template_data);
        
        $sent = $this->send_email($booking->email, $subject, $message);
        $this->log_email($booking_id, $booking->email, $subject, $sent ? 'sent' : 'failed');
        
        return $sent;
    }
    
    /**
     * Send commission payment notification
     */
    public function send_commission_notification($commission_id, $dj_id) {
        $commission = $this->get_commission($commission_id);
        $dj = $this->get_dj($dj_id);
        
        if (!$commission || !$dj) {
            return false;
        }
        
        $subject = 'Commission Payment Processed';
        $template_data = array(
            'commission' => $commission,
            'dj' => $dj,
            'dj_name' => $dj->first_name . ' ' . $dj->last_name,
            'amount' => '£' . number_format($commission->amount, 2),
            'payment_date' => date('jS F Y', strtotime($commission->payment_date)),
            'booking_reference' => $commission->booking_id
        );
        
        $message = $this->get_template('commission-payment', $template_data);
        
        $sent = $this->send_email($dj->email, $subject, $message);
        $this->log_email($commission->booking_id, $dj->email, $subject, $sent ? 'sent' : 'failed', 'commission');
        
        return $sent;
    }
    
    /**
     * Get email template
     */
    private function get_template($template_name, $data = array()) {
        $template_path = plugin_dir_path(__FILE__) . '../templates/emails/' . $template_name . '.php';
        
        if (!file_exists($template_path)) {
            return $this->get_default_template($template_name, $data);
        }
        
        ob_start();
        extract($data);
        include $template_path;
        $content = ob_get_clean();
        
        return $this->wrap_template($content, $data);
    }
    
    /**
     * Wrap content in email template
     */
    private function wrap_template($content, $data = array()) {
        $company_name = get_option('ml_settings')['company_name'] ?? 'Music & Lights';
        $company_address = get_option('ml_settings')['company_address'] ?? '';
        $company_phone = get_option('ml_settings')['company_phone'] ?? '';
        $company_email = get_option('ml_settings')['company_email'] ?? $this->from_email;
        
        $template = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Music & Lights</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #1a1a1a; color: white; padding: 20px; text-align: center; }
                .logo { max-width: 200px; height: auto; }
                .content { padding: 30px 20px; background: #f9f9f9; }
                .footer { background: #333; color: white; padding: 20px; text-align: center; font-size: 12px; }
                .button { display: inline-block; padding: 12px 24px; background: #007cba; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
                .booking-details { background: white; padding: 20px; border-radius: 5px; margin: 20px 0; }
                .booking-details h3 { margin-top: 0; color: #1a1a1a; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    ' . ($this->logo_url ? '<img src="' . esc_url($this->logo_url) . '" alt="' . esc_attr($company_name) . '" class="logo">' : '<h1>' . esc_html($company_name) . '</h1>') . '
                </div>
                <div class="content">
                    ' . $content . '
                </div>
                <div class="footer">
                    <p><strong>' . esc_html($company_name) . '</strong></p>
                    ' . ($company_address ? '<p>' . nl2br(esc_html($company_address)) . '</p>' : '') . '
                    ' . ($company_phone ? '<p>Phone: ' . esc_html($company_phone) . '</p>' : '') . '
                    <p>Email: ' . esc_html($company_email) . '</p>
                    <p>&copy; ' . date('Y') . ' ' . esc_html($company_name) . '. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>';
        
        return $template;
    }
    
    /**
     * Get default template content
     */
    private function get_default_template($template_name, $data) {
        switch ($template_name) {
            case 'booking-confirmation':
                return $this->get_booking_confirmation_template($data);
            case 'deposit-confirmation':
                return $this->get_deposit_confirmation_template($data);
            case 'dj-notification':
                return $this->get_dj_notification_template($data);
            case 'status-update':
                return $this->get_status_update_template($data);
            case 'commission-payment':
                return $this->get_commission_payment_template($data);
            default:
                return '<p>Email template not found.</p>';
        }
    }
    
    /**
     * Default booking confirmation template
     */
    private function get_booking_confirmation_template($data) {
        return '
        <h2>Booking Confirmation</h2>
        <p>Dear ' . esc_html($data['customer_name']) . ',</p>
        <p>Thank you for your booking! We have received your request and will be in touch shortly to confirm all details.</p>
        
        <div class="booking-details">
            <h3>Booking Details</h3>
            <p><strong>Event Date:</strong> ' . esc_html($data['event_date']) . '</p>
            <p><strong>Event Time:</strong> ' . esc_html($data['event_time']) . '</p>
            <p><strong>Venue:</strong> ' . esc_html($data['venue']) . '</p>
            <p><strong>Total Cost:</strong> ' . esc_html($data['total_cost']) . '</p>
            <p><strong>Deposit Required:</strong> ' . esc_html($data['deposit_amount']) . '</p>
        </div>
        
        <p>To secure your booking, please pay the deposit amount as soon as possible. We will send you payment instructions shortly.</p>
        <p>If you have any questions, please don\'t hesitate to contact us.</p>
        <p>Best regards,<br>Music & Lights Team</p>';
    }
    
    /**
     * Default deposit confirmation template
     */
    private function get_deposit_confirmation_template($data) {
        return '
        <h2>Deposit Received - Booking Confirmed!</h2>
        <p>Dear ' . esc_html($data['customer_name']) . ',</p>
        <p>Great news! We have received your deposit payment and your booking is now confirmed.</p>
        
        <div class="booking-details">
            <h3>Payment Details</h3>
            <p><strong>Deposit Paid:</strong> ' . esc_html($data['deposit_amount']) . '</p>
            <p><strong>Remaining Balance:</strong> ' . esc_html($data['remaining_balance']) . '</p>
            <p><strong>Event Date:</strong> ' . esc_html($data['event_date']) . '</p>
        </div>
        
        <p>The remaining balance will be due on the day of your event. We will assign a DJ to your booking and send you their contact details soon.</p>
        <p>Thank you for choosing Music & Lights!</p>
        <p>Best regards,<br>Music & Lights Team</p>';
    }
    
    /**
     * Default DJ notification template
     */
    private function get_dj_notification_template($data) {
        return '
        <h2>New Booking Assignment</h2>
        <p>Hi ' . esc_html($data['dj_name']) . ',</p>
        <p>You have been assigned a new booking! Please review the details below and confirm your availability.</p>
        
        <div class="booking-details">
            <h3>Event Details</h3>
            <p><strong>Date:</strong> ' . esc_html($data['event_date']) . '</p>
            <p><strong>Time:</strong> ' . esc_html($data['event_time']) . '</p>
            <p><strong>Venue:</strong> ' . esc_html($data['venue']) . '</p>
            <p><strong>Customer:</strong> ' . esc_html($data['customer_name']) . '</p>
            <p><strong>Phone:</strong> ' . esc_html($data['customer_phone']) . '</p>
            <p><strong>Your Commission:</strong> ' . esc_html($data['commission_amount']) . '</p>
        </div>
        
        ' . (!empty($data['special_requests']) ? '<p><strong>Special Requests:</strong> ' . esc_html($data['special_requests']) . '</p>' : '') . '
        
        <p>Please log into your DJ dashboard to confirm this booking and view full details.</p>
        <a href="' . home_url('/dj-dashboard/') . '" class="button">View Booking</a>
        
        <p>Best regards,<br>Music & Lights Team</p>';
    }
    
    /**
     * Send email
     */
    private function send_email($to, $subject, $message, $headers = array()) {
        if (empty($headers)) {
            $headers = array('Content-Type: text/html; charset=UTF-8');
        }
        
        return wp_mail($to, $subject, $message, $headers);
    }
    
    /**
     * Log email
     */
    private function log_email($booking_id, $recipient, $subject, $status, $type = 'customer') {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'ml_email_log',
            array(
                'booking_id' => $booking_id,
                'recipient' => $recipient,
                'subject' => $subject,
                'status' => $status,
                'type' => $type,
                'sent_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Get booking
     */
    private function get_booking($booking_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ml_bookings WHERE id = %d", $booking_id));
    }
    
    /**
     * Get DJ
     */
    private function get_dj($dj_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ml_djs WHERE id = %d", $dj_id));
    }
    
    /**
     * Get commission
     */
    private function get_commission($commission_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ml_commissions WHERE id = %d", $commission_id));
    }
}

// Initialize the email class
ML_Email::get_instance();