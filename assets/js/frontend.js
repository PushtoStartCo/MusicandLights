/**
 * Music & Lights Frontend JavaScript
 */

(function($) {
    'use strict';

    // Initialize when DOM is ready
    $(document).ready(function() {
        
        // Initialize components
        initDJProfiles();
        initBookingForm();
        initDistanceCalculator();
        initPaymentForm();
        
    });
    
    /**
     * Initialize DJ Profiles functionality
     */
    function initDJProfiles() {
        // Filter DJs
        $('#filter-djs').on('click', function() {
            const specialisation = $('#specialisation-filter').val();
            const location = $('#location-filter').val();
            
            // Show loading
            $('#dj-profiles-grid').css('opacity', '0.5');
            
            // In a real implementation, this would make an AJAX call
            // For now, we'll just filter visible cards
            $('.dj-profile-card').each(function() {
                const $card = $(this);
                let show = true;
                
                // Filter logic would go here
                
                if (show) {
                    $card.show();
                } else {
                    $card.hide();
                }
            });
            
            $('#dj-profiles-grid').css('opacity', '1');
        });
        
        // Clear filters
        $('#clear-filters').on('click', function() {
            $('#specialisation-filter').val('');
            $('#location-filter').val('');
            $('.dj-profile-card').show();
        });
    }
    
    /**
     * Initialize Booking Form
     */
    function initBookingForm() {
        // This is handled in the booking-form.php template
        // Additional form enhancements can be added here
    }
    
    /**
     * Initialize Distance Calculator
     */
    function initDistanceCalculator() {
        // Postcode validation
        $('.postcode-input').on('blur', function() {
            const $input = $(this);
            const postcode = $input.val();
            
            if (postcode) {
                $.ajax({
                    url: musicandlights.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'validate_postcode',
                        postcode: postcode,
                        nonce: musicandlights.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $input.removeClass('error').addClass('valid');
                            $input.val(response.data.normalized);
                        } else {
                            $input.addClass('error').removeClass('valid');
                        }
                    }
                });
            }
        });
    }
    
    /**
     * Initialize Payment Form
     */
    function initPaymentForm() {
        if (typeof Stripe === 'undefined' || !musicandlights.stripe_public_key) {
            return;
        }
        
        const stripe = Stripe(musicandlights.stripe_public_key);
        const elements = stripe.elements();
        
        // Create card element
        const cardElement = elements.create('card', {
            style: {
                base: {
                    fontSize: '16px',
                    color: '#32325d',
                    '::placeholder': {
                        color: '#aab7c4'
                    }
                },
                invalid: {
                    color: '#fa755a',
                    iconColor: '#fa755a'
                }
            }
        });
        
        // Mount card element if container exists
        if ($('#card-element').length) {
            cardElement.mount('#card-element');
            
            // Handle payment submission
            $('#payment-form').on('submit', async function(e) {
                e.preventDefault();
                
                const $form = $(this);
                const $submitBtn = $form.find('button[type="submit"]');
                
                $submitBtn.prop('disabled', true).text('Processing...');
                
                // Create payment intent
                const response = await $.ajax({
                    url: musicandlights.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'process_stripe_payment',
                        booking_id: $form.find('input[name="booking_id"]').val(),
                        payment_type: $form.find('input[name="payment_type"]').val(),
                        amount: $form.find('input[name="amount"]').val(),
                        nonce: musicandlights.nonce
                    }
                });
                
                if (response.success) {
                    // Confirm payment with Stripe
                    const {error} = await stripe.confirmCardPayment(response.data.client_secret, {
                        payment_method: {
                            card: cardElement,
                            billing_details: {
                                name: $form.find('input[name="cardholder_name"]').val()
                            }
                        }
                    });
                    
                    if (error) {
                        showPaymentError(error.message);
                        $submitBtn.prop('disabled', false).text('Pay Now');
                    } else {
                        // Payment successful
                        showPaymentSuccess();
                    }
                } else {
                    showPaymentError(response.data);
                    $submitBtn.prop('disabled', false).text('Pay Now');
                }
            });
        }
    }
    
    /**
     * Show payment error
     */
    function showPaymentError(message) {
        $('#payment-error').text(message).show();
        $('html, body').animate({
            scrollTop: $('#payment-error').offset().top - 100
        }, 500);
    }
    
    /**
     * Show payment success
     */
    function showPaymentSuccess() {
        $('#payment-form').hide();
        $('#payment-success').show();
        
        // Redirect after 3 seconds
        setTimeout(function() {
            window.location.href = musicandlights.success_url || '/';
        }, 3000);
    }
    
    /**
     * Utility Functions
     */
    
    // Format currency
    window.formatCurrency = function(amount) {
        return new Intl.NumberFormat('en-GB', {
            style: 'currency',
            currency: 'GBP'
        }).format(amount);
    };
    
    // Format date
    window.formatDate = function(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-GB', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    };
    
    // Smooth scroll to element
    window.smoothScrollTo = function(target) {
        $('html, body').animate({
            scrollTop: $(target).offset().top - 100
        }, 500);
    };

})(jQuery);