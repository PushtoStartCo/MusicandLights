<?php
/**
 * Email Templates Class
 * Manages all email templates for the DJ booking system
 */

class Email_Templates {
    
    private $company_name;
    private $company_phone;
    private $company_email;
    private $company_address;
    private $email_logo;
    
    public function __construct() {
        $this->company_name = get_option('musicandlights_company_name', 'Music & Lights');
        $this->company_phone = get_option('musicandlights_company_phone', '');
        $this->company_email = get_option('musicandlights_company_email', get_option('admin_email'));
        $this->company_address = get_option('musicandlights_company_address', '');
        $this->email_logo = get_option('musicandlights_email_logo', '');
        
        // Add email hooks
        add_filter('wp_mail_content_type', array($this, 'set_html_content_type'));
        add_filter('wp_mail_from', array($this, 'set_mail_from'));
        add_filter('wp_mail_from_name', array($this, 'set_mail_from_name'));
        
        // Add custom email actions
        add_action('musicandlights_send_booking_confirmation', array($this, 'send_booking_confirmation'), 10, 1);
        add_action('musicandlights_send_event_details_form', array($this, 'send_event_details_form'), 10, 1);
        add_action('musicandlights_send_quote', array($this, 'send_quote'), 10, 1);
        add_action('musicandlights_send_payment_reminder', array($this, 'send_payment_reminder'), 10, 1);
    }
    
    /**
     * Set email content type to HTML
     */
    public function set_html_content_type() {
        return 'text/html';
    }
    
    /**
     * Set email from address
     */
    public function set_mail_from($email) {
        return $this->company_email;
    }
    
    /**
     * Set email from name
     */
    public function set_mail_from_name($name) {
        return $this->company_name;
    }
    
    /**
     * Get base email template
     */
    private function get_email_template($content, $subject = '') {
        $logo_html = '';
        if (!empty($this->email_logo)) {
            $logo_html = '<img src="' . esc_url($this->email_logo) . '" alt="' . esc_attr($this->company_name) . '" style="max-width: 200px; height: auto; margin-bottom: 20px;">';
        }
        
        $template = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . esc_html($subject) . '</title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                    line-height: 1.6;
                    color: #333333;
                    background-color: #f4f4f4;
                    margin: 0;
                    padding: 0;
                }
                .email-container {
                    max-width: 600px;
                    margin: 20px auto;
                    background-color: #ffffff;
                    border-radius: 8px;
                    overflow: hidden;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }
                .email-header {
                    background-color: #1e1e1e;
                    color: #ffffff;
                    padding: 30px;
                    text-align: center;
                }
                .email-header h1 {
                    margin: 0;
                    font-size: 24px;
                    font-weight: 500;
                }
                .email-body {
                    padding: 40px 30px;
                }
                .email-footer {
                    background-color: #f8f8f8;
                    padding: 20px 30px;
                    text-align: center;
                    font-size: 14px;
                    color: #666666;
                }
                .button {
                    display: inline-block;
                    padding: 12px 30px;
                    background-color: #007cba;
                    color: #ffffff !important;
                    text-decoration: none;
                    border-radius: 5px;
                    font-weight: 500;
                    margin: 20px 0;
                }
                .button:hover {
                    background-color: #005a87;
                }
                .info-box {
                    background-color: #f0f8ff;
                    border-left: 4px solid #007cba;
                    padding: 15px;
                    margin: 20px 0;
                }
                .detail-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 20px 0;
                }
                .detail-table th {
                    text-align: left;
                    padding: 10px;
                    background-color: #f8f8f8;
                    border-bottom: 2px solid #e0e0e0;
                    font-weight: 600;
                }
                .detail-table td {
                    padding: 10px;
                    border-bottom: 1px solid #e0e0e0;
                }
                .highlight {
                    background-color: #fff3cd;
                    padding: 2px 5px;
                    border-radius: 3px;
                }
                .price {
                    font-size: 24px;
                    font-weight: bold;
                    color: #007cba;
                }
            </style>
        </head>
        <body>
            <div class="email-container">
                <div class="email-header">
                    ' . $logo_html . '
                    <h1>' . esc_html($this->company_name) . '</h1>
                </div>
                <div class="email-body">
                    ' . $content . '
                </div>
                <div class="email-footer">
                    <p>' . esc_html($this->company_name) . '<br>
                    ' . nl2br(esc_html($this->company_address)) . '<br>
                    Phone: ' . esc_html($this->company_phone) . '<br>
                    Email: <a href="mailto:' . esc_attr($this->company_email) . '">' . esc_html($this->company_email) . '</a></p>
                    <p style="margin-top: 20px; font-size: 12px; color: #999999;">
                        This email was sent regarding your DJ booking enquiry. 
                        If you did not make this enquiry, please contact us immediately.
                    </p>
                </div>
            </div>
        </body>
        </html>';
        
        return $template;
    }
    
    /**
     * Send booking confirmation email
     */
    public function send_booking_confirmation($booking_id) {
        $booking_data = $this->get_booking_data($booking_id);
        
        $subject = 'Booking Confirmation - ' . $booking_data['event_date'];
        
        $content = '
        <h2>Thank You for Your Booking!</h2>
        <p>Dear ' . esc_html($booking_data['client_name']) . ',</p>
        <p>We\'re delighted to confirm that we\'ve received your DJ booking enquiry. Your booking reference is <strong>#' . $booking_id . '</strong>.</p>
        
        <div class="info-box">
            <h3>What Happens Next?</h3>
            <ol>
                <li>We\'ll send you an event details form to complete</li>
                <li>Your assigned DJ will call you within 48 hours to discuss your requirements</li>
                <li>We\'ll send you a formal quote and booking agreement</li>
                <li>A 50% deposit secures your date</li>
            </ol>
        </div>
        
        <h3>Booking Summary</h3>
        <table class="detail-table">
            <tr>
                <th>Event Date</th>
                <td>' . esc_html(date('l, j F Y', strtotime($booking_data['event_date']))) . '</td>
            </tr>
            <tr>
                <th>Event Time</th>
                <td>' . esc_html($booking_data['event_time'] ?: 'To be confirmed') . '</td>
            </tr>
            <tr>
                <th>Duration</th>
                <td>' . esc_html($booking_data['event_duration'] . ' hours') . '</td>
            </tr>
            <tr>
                <th>Venue Postcode</th>
                <td>' . esc_html($booking_data['venue_postcode'] ?: 'To be confirmed') . '</td>
            </tr>
            <tr>
                <th>Assigned DJ</th>
                <td>' . esc_html($booking_data['dj_name'] ?: 'To be confirmed') . '</td>
            </tr>
            <tr>
                <th>Estimated Total</th>
                <td class="price">£' . number_format($booking_data['total_amount'], 2) . '</td>
            </tr>
        </table>
        
        <p>If you have any immediate questions, please don\'t hesitate to contact us.</p>
        <p>We look forward to making your event unforgettable!</p>
        
        <p>Best regards,<br>
        The ' . esc_html($this->company_name) . ' Team</p>';
        
        $html = $this->get_email_template($content, $subject);
        
        return wp_mail($booking_data['client_email'], $subject, $html);
    }
    
    /**
     * Get event details form email
     */
    public function get_event_details_form_email($booking_data, $form_url) {
        $subject = 'Please Complete Your Event Details - ' . $this->company_name;
        
        $content = '
        <h2>Let\'s Plan Your Perfect Event!</h2>
        <p>Dear ' . esc_html($booking_data['client_name']) . ',</p>
        <p>To ensure your DJ delivers exactly what you\'re looking for, we need a few more details about your event.</p>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="' . esc_url($form_url) . '" class="button">Complete Event Details Form</a>
        </div>
        
        <div class="info-box">
            <h3>Why This Is Important</h3>
            <p>The information you provide helps your DJ:</p>
            <ul>
                <li>Prepare the perfect playlist for your guests</li>
                <li>Understand your venue\'s requirements</li>
                <li>Plan the timeline for key moments</li>
                <li>Bring the right equipment for your event</li>
            </ul>
        </div>
        
        <h3>What We\'ll Ask About</h3>
        <ul>
            <li>Venue details and access information</li>
            <li>Event timeline and special moments</li>
            <li>Music preferences and must-play songs</li>
            <li>Any songs or genres to avoid</li>
            <li>Special announcements or requirements</li>
        </ul>
        
        <p><strong>Please complete this form within 48 hours</strong> so your DJ can call you fully prepared to discuss your event.</p>
        
        <p>If you have any questions or can\'t access the form, please reply to this email or call us on ' . esc_html($this->company_phone) . '.</p>
        
        <p>Best regards,<br>
        The ' . esc_html($this->company_name) . ' Team</p>';
        
        return $this->get_email_template($content, $subject);
    }
    
    /**
     * Send quote email
     */
    public function send_quote($booking_id) {
        $booking_data = $this->get_booking_data($booking_id);
        $breakdown = $this->get_price_breakdown($booking_id);
        
        $subject = 'Your DJ Booking Quote - ' . $booking_data['event_date'];
        
        $content = '
        <h2>Your Personalised Quote</h2>
        <p>Dear ' . esc_html($booking_data['client_name']) . ',</p>
        <p>Thank you for choosing ' . esc_html($this->company_name) . ' for your event. Based on our discussion, here\'s your personalised quote:</p>
        
        <div style="background-color: #f8f8f8; padding: 20px; border-radius: 5px; margin: 20px 0;">
            <h3 style="margin-top: 0;">Quote Summary</h3>
            <table class="detail-table">
                <tr>
                    <th>DJ Performance (' . esc_html($booking_data['event_duration']) . ' hours)</th>
                    <td style="text-align: right;">£' . number_format($breakdown['base_rate'], 2) . '</td>
                </tr>';
        
        if ($breakdown['travel_cost'] > 0) {
            $content .= '
                <tr>
                    <th>Travel Costs</th>
                    <td style="text-align: right;">£' . number_format($breakdown['travel_cost'], 2) . '</td>
                </tr>';
        }
        
        if ($breakdown['accommodation_cost'] > 0) {
            $content .= '
                <tr>
                    <th>Accommodation</th>
                    <td style="text-align: right;">£' . number_format($breakdown['accommodation_cost'], 2) . '</td>
                </tr>';
        }
        
        if (!empty($breakdown['equipment_cost']) && $breakdown['equipment_cost'] > 0) {
            $content .= '
                <tr>
                    <th>Additional Equipment</th>
                    <td style="text-align: right;">£' . number_format($breakdown['equipment_cost'], 2) . '</td>
                </tr>';
        }
        
        $content .= '
                <tr style="border-top: 2px solid #333;">
                    <th style="font-size: 18px;">Total</th>
                    <td style="text-align: right;" class="price">£' . number_format($booking_data['total_amount'], 2) . '</td>
                </tr>
            </table>
        </div>
        
        <h3>What\'s Included</h3>
        <ul>
            <li>Professional DJ performance by ' . esc_html($booking_data['dj_name']) . '</li>
            <li>High-quality sound system suitable for your venue</li>
            <li>Wireless microphone for speeches</li>
            <li>Professional DJ booth and lighting</li>
            <li>Consultation call to plan your perfect playlist</li>
            <li>Public liability insurance</li>
            <li>Setup and breakdown time included</li>
        </ul>
        
        <h3>Payment Terms</h3>
        <div class="info-box">
            <p><strong>50% Deposit (£' . number_format($booking_data['deposit_amount'], 2) . ')</strong> - Due now to secure your date<br>
            <strong>50% Balance (£' . number_format($booking_data['final_payment_amount'], 2) . ')</strong> - Due one month before your event</p>
        </div>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="' . esc_url($this->get_payment_link($booking_id, 'deposit')) . '" class="button">Pay Deposit & Confirm Booking</a>
        </div>
        
        <p><strong>This quote is valid for 14 days.</strong> After this time, pricing and availability may change.</p>
        
        <p>If you have any questions about this quote or would like to make any changes, please don\'t hesitate to contact us.</p>
        
        <p>We look forward to being part of your special event!</p>
        
        <p>Best regards,<br>
        ' . esc_html($booking_data['dj_name']) . '<br>
        The ' . esc_html($this->company_name) . ' Team</p>';
        
        $html = $this->get_email_template($content, $subject);
        
        return wp_mail($booking_data['client_email'], $subject, $html);
    }
    
    /**
     * Get payment reminder email
     */
    public function get_payment_reminder_email($booking_data, $payment_url) {
        $subject = 'Payment Reminder - Your Event on ' . date('j F', strtotime($booking_data['event_date']));
        
        $days_until_event = floor((strtotime($booking_data['event_date']) - time()) / 86400);
        
        $content = '
        <h2>Final Payment Reminder</h2>
        <p>Dear ' . esc_html($booking_data['client_name']) . ',</p>
        
        <p>We hope you\'re getting excited about your event! This is a friendly reminder that your final payment is now due.</p>
        
        <div class="info-box" style="background-color: #fff3cd; border-color: #ffc107;">
            <h3 style="margin-top: 0;">Payment Due</h3>
            <p style="margin-bottom: 0;">
                <strong>Amount Due:</strong> <span class="price">£' . number_format($booking_data['final_payment_amount'], 2) . '</span><br>
                <strong>Event Date:</strong> ' . esc_html(date('l, j F Y', strtotime($booking_data['event_date']))) . '<br>
                <strong>Days Until Event:</strong> ' . $days_until_event . ' days
            </p>
        </div>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="' . esc_url($payment_url) . '" class="button">Make Final Payment</a>
        </div>
        
        <h3>Event Details Confirmed</h3>
        <table class="detail-table">
            <tr>
                <th>DJ</th>
                <td>' . esc_html($booking_data['dj_name']) . '</td>
            </tr>
            <tr>
                <th>Venue</th>
                <td>' . esc_html(get_post_meta($booking_data['booking_id'], 'venue_name', true) ?: 'To be confirmed') . '</td>
            </tr>
            <tr>
                <th>Time</th>
                <td>' . esc_html($booking_data['event_time'] ?: 'To be confirmed') . '</td>
            </tr>
            <tr>
                <th>Duration</th>
                <td>' . esc_html($booking_data['event_duration']) . ' hours</td>
            </tr>
        </table>
        
        <p>Once your final payment is received, we\'ll send you a confirmation and your DJ will contact you during the week of your event for any last-minute details.</p>
        
        <p>If you have any questions or concerns, please contact us immediately on ' . esc_html($this->company_phone) . '.</p>
        
        <p>Looking forward to making your event amazing!</p>
        
        <p>Best regards,<br>
        The ' . esc_html($this->company_name) . ' Team</p>';
        
        return $this->get_email_template($content, $subject);
    }
    
    /**
     * Send payment confirmation email
     */
    public function send_payment_confirmation($booking_id, $payment_type) {
        $booking_data = $this->get_booking_data($booking_id);
        
        if ($payment_type === 'deposit') {
            $subject = 'Deposit Received - Your DJ Booking is Confirmed!';
            $amount = $booking_data['deposit_amount'];
        } else {
            $subject = 'Payment Received - You\'re All Set!';
            $amount = $booking_data['final_payment_amount'];
        }
        
        $content = '
        <h2>Payment Confirmation</h2>
        <p>Dear ' . esc_html($booking_data['client_name']) . ',</p>';
        
        if ($payment_type === 'deposit') {
            $content .= '
            <p>Fantastic news! We\'ve received your deposit and your DJ booking is now <strong>confirmed</strong>.</p>
            
            <div class="info-box" style="background-color: #d4edda; border-color: #28a745;">
                <h3 style="margin-top: 0; color: #155724;">Booking Confirmed!</h3>
                <p style="margin-bottom: 0;">
                    <strong>Payment Received:</strong> £' . number_format($amount, 2) . '<br>
                    <strong>Booking Reference:</strong> #' . $booking_id . '<br>
                    <strong>Status:</strong> <span class="highlight" style="background-color: #28a745; color: white;">Confirmed</span>
                </p>
            </div>
            
            <h3>What Happens Next?</h3>
            <ol>
                <li>Your date is now secured in our calendar</li>
                <li>Your DJ will contact you 2-4 weeks before your event</li>
                <li>Final payment of £' . number_format($booking_data['final_payment_amount'], 2) . ' is due one month before your event</li>
                <li>Get ready for an amazing event!</li>
            </ol>';
        } else {
            $content .= '
            <p>Thank you! We\'ve received your final payment. Everything is now in place for your event.</p>
            
            <div class="info-box" style="background-color: #d4edda; border-color: #28a745;">
                <h3 style="margin-top: 0; color: #155724;">Fully Paid!</h3>
                <p style="margin-bottom: 0;">
                    <strong>Payment Received:</strong> £' . number_format($amount, 2) . '<br>
                    <strong>Total Paid:</strong> £' . number_format($booking_data['total_amount'], 2) . '<br>
                    <strong>Status:</strong> <span class="highlight" style="background-color: #28a745; color: white;">Paid in Full</span>
                </p>
            </div>
            
            <h3>Final Preparations</h3>
            <p>Your DJ will contact you during the week of your event to:</p>
            <ul>
                <li>Confirm arrival and setup times</li>
                <li>Review your playlist and any special requests</li>
                <li>Discuss any last-minute changes</li>
                <li>Ensure everything is perfect for your event</li>
            </ul>';
        }
        
        $content .= '
        <h3>Your Event Details</h3>
        <table class="detail-table">
            <tr>
                <th>Date</th>
                <td>' . esc_html(date('l, j F Y', strtotime($booking_data['event_date']))) . '</td>
            </tr>
            <tr>
                <th>DJ</th>
                <td>' . esc_html($booking_data['dj_name']) . '</td>
            </tr>
            <tr>
                <th>Venue</th>
                <td>' . esc_html(get_post_meta($booking_id, 'venue_name', true) ?: 'To be confirmed') . '</td>
            </tr>
        </table>
        
        <p>Thank you for choosing ' . esc_html($this->company_name) . '. We can\'t wait to be part of your special event!</p>
        
        <p>Best regards,<br>
        The ' . esc_html($this->company_name) . ' Team</p>';
        
        $html = $this->get_email_template($content, $subject);
        
        return wp_mail($booking_data['client_email'], $subject, $html);
    }
    
    /**
     * Send DJ notification email
     */
    public function send_dj_notification($dj_id, $booking_id, $notification_type) {
        $dj_user_id = get_post_meta($dj_id, 'dj_user_id', true);
        if (!$dj_user_id) return false;
        
        $dj_user = get_user_by('ID', $dj_user_id);
        if (!$dj_user) return false;
        
        $booking_data = $this->get_booking_data($booking_id);
        
        switch ($notification_type) {
            case 'new_booking':
                $subject = 'New Booking Assigned - ' . $booking_data['event_date'];
                $content = $this->get_dj_new_booking_email($dj_user, $booking_data);
                break;
                
            case 'details_completed':
                $subject = 'Event Details Ready - ' . $booking_data['client_name'];
                $content = $this->get_dj_details_ready_email($dj_user, $booking_data);
                break;
                
            case 'booking_confirmed':
                $subject = 'Booking Confirmed - ' . $booking_data['event_date'];
                $content = $this->get_dj_booking_confirmed_email($dj_user, $booking_data);
                break;
                
            default:
                return false;
        }
        
        $html = $this->get_email_template($content, $subject);
        
        return wp_mail($dj_user->user_email, $subject, $html);
    }
    
    /**
     * Get DJ new booking email content
     */
    private function get_dj_new_booking_email($dj_user, $booking_data) {
        return '
        <h2>New Booking Assignment</h2>
        <p>Hi ' . esc_html($dj_user->display_name) . ',</p>
        <p>Great news! You\'ve been assigned to a new booking.</p>
        
        <div class="info-box">
            <h3>Booking Details</h3>
            <p>
                <strong>Client:</strong> ' . esc_html($booking_data['client_name']) . '<br>
                <strong>Date:</strong> ' . esc_html(date('l, j F Y', strtotime($booking_data['event_date']))) . '<br>
                <strong>Time:</strong> ' . esc_html($booking_data['event_time'] ?: 'TBC') . '<br>
                <strong>Duration:</strong> ' . esc_html($booking_data['event_duration']) . ' hours<br>
                <strong>Location:</strong> ' . esc_html($booking_data['venue_postcode'] ?: 'TBC') . '
            </p>
        </div>
        
        <h3>Next Steps</h3>
        <ol>
            <li>The client will complete an event details form</li>
            <li>You\'ll receive notification when it\'s ready</li>
            <li>Please call the client within 48 hours of receiving their details</li>
            <li>Log into your dashboard to view full booking information</li>
        </ol>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="' . esc_url(home_url('/dj-dashboard/')) . '" class="button">View in Dashboard</a>
        </div>
        
        <p>If you have any questions about this booking, please contact the office.</p>
        
        <p>Thanks,<br>
        The ' . esc_html($this->company_name) . ' Team</p>';
    }
    
    /**
     * Get DJ details ready email content
     */
    private function get_dj_details_ready_email($dj_user, $booking_data) {
        $event_details = get_post_meta($booking_data['booking_id'], 'event_type', true);
        
        return '
        <h2>Event Details Completed</h2>
        <p>Hi ' . esc_html($dj_user->display_name) . ',</p>
        <p>The client has completed their event details form. Please review the information and call them within 48 hours.</p>
        
        <div class="info-box" style="background-color: #fff3cd; border-color: #ffc107;">
            <h3 style="margin-top: 0;">Action Required</h3>
            <p><strong>Please call ' . esc_html($booking_data['client_name']) . ' within 48 hours</strong></p>
            <p>Phone: ' . esc_html(get_post_meta($booking_data['booking_id'], 'client_phone', true)) . '</p>
        </div>
        
        <h3>Event Summary</h3>
        <table class="detail-table">
            <tr>
                <th>Event Type</th>
                <td>' . esc_html(ucwords(str_replace('_', ' ', $event_details))) . '</td>
            </tr>
            <tr>
                <th>Date</th>
                <td>' . esc_html(date('l, j F Y', strtotime($booking_data['event_date']))) . '</td>
            </tr>
            <tr>
                <th>Venue</th>
                <td>' . esc_html(get_post_meta($booking_data['booking_id'], 'venue_name', true)) . '</td>
            </tr>
            <tr>
                <th>Guest Count</th>
                <td>' . esc_html(get_post_meta($booking_data['booking_id'], 'guest_count', true)) . '</td>
            </tr>
        </table>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="' . esc_url(admin_url('post.php?post=' . $booking_data['booking_id'] . '&action=edit')) . '" class="button">View Full Details</a>
        </div>
        
        <p>Remember to discuss:</p>
        <ul>
            <li>Music preferences and playlist</li>
            <li>Timeline and special moments</li>
            <li>Equipment requirements</li>
            <li>Access and setup details</li>
        </ul>
        
        <p>Thanks,<br>
        The ' . esc_html($this->company_name) . ' Team</p>';
    }
    
    /**
     * Send booking cancellation email
     */
    public function send_cancellation_email($booking_id) {
        $booking_data = $this->get_booking_data($booking_id);
        
        $subject = 'Booking Cancellation Confirmation';
        
        $content = '
        <h2>Booking Cancellation</h2>
        <p>Dear ' . esc_html($booking_data['client_name']) . ',</p>
        <p>This email confirms that your DJ booking for ' . esc_html(date('j F Y', strtotime($booking_data['event_date']))) . ' has been cancelled as requested.</p>
        
        <div class="info-box">
            <h3>Cancellation Details</h3>
            <p>
                <strong>Booking Reference:</strong> #' . $booking_id . '<br>
                <strong>Event Date:</strong> ' . esc_html($booking_data['event_date']) . '<br>
                <strong>Cancellation Date:</strong> ' . date('j F Y') . '
            </p>
        </div>';
        
        // Add refund information if applicable
        if ($booking_data['deposit_status'] === 'paid') {
            $content .= '
            <h3>Refund Information</h3>
            <p>According to our cancellation policy, refunds are processed as follows:</p>
            <ul>
                <li>More than 30 days before event: Full refund minus £50 admin fee</li>
                <li>14-30 days before event: 50% refund</li>
                <li>Less than 14 days before event: No refund</li>
            </ul>
            <p>Your refund will be processed within 5-7 business days.</p>';
        }
        
        $content .= '
        <p>We\'re sorry we won\'t be part of your event. If your plans change or you need a DJ for a future event, please don\'t hesitate to contact us.</p>
        
        <p>Best regards,<br>
        The ' . esc_html($this->company_name) . ' Team</p>';
        
        $html = $this->get_email_template($content, $subject);
        
        return wp_mail($booking_data['client_email'], $subject, $html);
    }
    
    /**
     * Send review request email
     */
    public function send_review_request($booking_id) {
        $booking_data = $this->get_booking_data($booking_id);
        
        $subject = 'How was your event? We\'d love your feedback!';
        
        $review_link = home_url('/leave-review/?booking=' . $booking_id . '&token=' . wp_create_nonce('review_' . $booking_id));
        
        $content = '
        <h2>We Hope You Had an Amazing Event!</h2>
        <p>Dear ' . esc_html($booking_data['client_name']) . ',</p>
        <p>We hope ' . esc_html($booking_data['dj_name']) . ' helped make your event on ' . esc_html(date('j F', strtotime($booking_data['event_date']))) . ' truly special.</p>
        
        <p>Your feedback is incredibly valuable to us and helps other clients choose the perfect DJ for their events.</p>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="' . esc_url($review_link) . '" class="button">Leave a Review</a>
        </div>
        
        <div class="info-box">
            <h3>Why Leave a Review?</h3>
            <ul style="margin-bottom: 0;">
                <li>Help others find the perfect DJ</li>
                <li>Recognise your DJ\'s hard work</li>
                <li>Help us improve our service</li>
                <li>Get 10% off your next booking!</li>
            </ul>
        </div>
        
        <p>It only takes 2 minutes, and as a thank you, we\'ll send you a 10% discount code for your next booking with us.</p>
        
        <p>Thank you for choosing ' . esc_html($this->company_name) . '. We hope to be part of your celebrations again soon!</p>
        
        <p>Best regards,<br>
        The ' . esc_html($this->company_name) . ' Team</p>
        
        <p style="font-size: 12px; color: #666; margin-top: 30px;">
        P.S. You can also share your experience on our social media:<br>
        <a href="#">Facebook</a> | <a href="#">Instagram</a> | <a href="#">Google Reviews</a>
        </p>';
        
        $html = $this->get_email_template($content, $subject);
        
        return wp_mail($booking_data['client_email'], $subject, $html);
    }
    
    /**
     * Helper functions
     */
    private function get_booking_data($booking_id) {
        $booking = get_post($booking_id);
        $meta = get_post_meta($booking_id);
        
        $dj_id = $meta['assigned_dj'][0] ?? '';
        $dj_name = $dj_id ? get_the_title($dj_id) : 'To be assigned';
        
        return array(
            'booking_id' => $booking_id,
            'client_name' => $meta['client_name'][0] ?? '',
            'client_email' => $meta['client_email'][0] ?? '',
            'event_date' => $meta['event_date'][0] ?? '',
            'event_time' => $meta['event_time'][0] ?? '',
            'event_duration' => $meta['event_duration'][0] ?? '4',
            'venue_postcode' => $meta['venue_postcode'][0] ?? '',
            'total_amount' => floatval($meta['total_amount'][0] ?? 0),
            'deposit_amount' => floatval($meta['deposit_amount'][0] ?? 0),
            'final_payment_amount' => floatval($meta['final_payment_amount'][0] ?? 0),
            'deposit_status' => $meta['deposit_status'][0] ?? 'pending',
            'dj_id' => $dj_id,
            'dj_name' => $dj_name
        );
    }
    
    private function get_price_breakdown($booking_id) {
        $meta = get_post_meta($booking_id);
        
        return array(
            'base_rate' => floatval($meta['base_rate'][0] ?? 0),
            'travel_cost' => floatval($meta['travel_cost'][0] ?? 0),
            'accommodation_cost' => floatval($meta['accommodation_cost'][0] ?? 0),
            'equipment_cost' => floatval($meta['equipment_cost'][0] ?? 0)
        );
    }
    
    private function get_payment_link($booking_id, $payment_type) {
        return add_query_arg(array(
            'booking_id' => $booking_id,
            'payment_type' => $payment_type,
            'token' => wp_create_nonce('payment_' . $booking_id)
        ), home_url('/payment/'));
    }
    
    /**
     * Send test email
     */
    public function send_test_email($email_type, $recipient_email) {
        // Create dummy booking data for testing
        $test_booking_data = array(
            'booking_id' => 9999,
            'client_name' => 'Test Client',
            'client_email' => $recipient_email,
            'event_date' => date('Y-m-d', strtotime('+30 days')),
            'event_time' => '19:00',
            'event_duration' => '5',
            'venue_postcode' => 'AL1 1AA',
            'total_amount' => 850.00,
            'deposit_amount' => 425.00,
            'final_payment_amount' => 425.00,
            'dj_name' => 'DJ Test'
        );
        
        switch ($email_type) {
            case 'booking_confirmation':
                $subject = '[TEST] Booking Confirmation';
                $content = $this->get_event_details_form_email($test_booking_data, home_url('/test-form/'));
                break;
                
            case 'quote':
                return $this->send_quote(9999);
                
            case 'payment_reminder':
                $subject = '[TEST] Payment Reminder';
                $content = $this->get_payment_reminder_email($test_booking_data, home_url('/test-payment/'));
                break;
                
            default:
                return false;
        }
        
        $html = $this->get_email_template($content, $subject);
        
        return wp_mail($recipient_email, $subject, $html);
    }
}
?>