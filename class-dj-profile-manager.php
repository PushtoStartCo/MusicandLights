<?php
/**
 * DJ Profile Manager Class
 * Handles DJ profiles, specialisations, pricing tiers, and individual settings
 */

class DJ_Profile_Manager {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_dj_profile_meta'));
        add_action('wp_ajax_update_dj_availability', array($this, 'update_availability'));
        add_action('wp_ajax_get_dj_rates', array($this, 'get_dj_rates'));
        add_action('wp_ajax_nopriv_get_dj_rates', array($this, 'get_dj_rates'));
    }
    
    public function init() {
        // Register user roles
        add_role('dj_artist', 'DJ Artist', array(
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
            'upload_files' => true,
            'edit_dj_profile' => true,
            'view_dj_bookings' => true,
            'manage_dj_calendar' => true
        ));
        
        // Add custom fields to DJ profiles
        add_action('admin_init', array($this, 'register_dj_meta_fields'));
    }
    
    public function register_dj_meta_fields() {
        // Register all DJ profile meta fields
        $meta_fields = array(
            'dj_user_id',
            'dj_specialisations',
            'dj_base_location',
            'dj_base_postcode',
            'dj_coverage_areas',
            'dj_hourly_rate',
            'dj_event_rate',
            'dj_travel_rate',
            'dj_travel_free_miles',
            'dj_accommodation_rate',
            'dj_commission_type',
            'dj_commission_rate',
            'dj_experience_years',
            'dj_bio',
            'dj_music_styles',
            'dj_equipment_included',
            'dj_additional_services',
            'dj_availability_status',
            'dj_portfolio_images',
            'dj_video_links',
            'dj_testimonials',
            'dj_social_links',
            'dj_contact_preferences',
            'dj_booking_packages'
        );
        
        foreach ($meta_fields as $field) {
            register_meta('post', $field, array(
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
            ));
        }
    }
    
    public function add_meta_boxes() {
        add_meta_box(
            'dj_profile_details',
            'DJ Profile Details',
            array($this, 'render_profile_details_meta_box'),
            'dj_profile',
            'normal',
            'high'
        );
        
        add_meta_box(
            'dj_pricing_commission',
            'Pricing & Commission',
            array($this, 'render_pricing_commission_meta_box'),
            'dj_profile',
            'normal',
            'high'
        );
        
        add_meta_box(
            'dj_location_travel',
            'Location & Travel',
            array($this, 'render_location_travel_meta_box'),
            'dj_profile',
            'normal',
            'high'
        );
        
        add_meta_box(
            'dj_packages_equipment',
            'Packages & Equipment',
            array($this, 'render_packages_equipment_meta_box'),
            'dj_profile',
            'normal',
            'high'
        );
    }
    
    public function render_profile_details_meta_box($post) {
        wp_nonce_field('dj_profile_meta_nonce', 'dj_profile_meta_nonce');
        
        // Get existing values
        $user_id = get_post_meta($post->ID, 'dj_user_id', true);
        $specialisations = get_post_meta($post->ID, 'dj_specialisations', true);
        $experience_years = get_post_meta($post->ID, 'dj_experience_years', true);
        $bio = get_post_meta($post->ID, 'dj_bio', true);
        $music_styles = get_post_meta($post->ID, 'dj_music_styles', true);
        $additional_services = get_post_meta($post->ID, 'dj_additional_services', true);
        $social_links = get_post_meta($post->ID, 'dj_social_links', true);
        $portfolio_images = get_post_meta($post->ID, 'dj_portfolio_images', true);
        $video_links = get_post_meta($post->ID, 'dj_video_links', true);
        $testimonials = get_post_meta($post->ID, 'dj_testimonials', true);
        
        // Parse arrays
        $specialisations = $specialisations ? json_decode($specialisations, true) : array();
        $music_styles = $music_styles ? json_decode($music_styles, true) : array();
        $additional_services = $additional_services ? json_decode($additional_services, true) : array();
        $social_links = $social_links ? json_decode($social_links, true) : array();
        $portfolio_images = $portfolio_images ? json_decode($portfolio_images, true) : array();
        $video_links = $video_links ? json_decode($video_links, true) : array();
        $testimonials = $testimonials ? json_decode($testimonials, true) : array();
        
        ?>
        <table class="form-table">
            <tr>
                <th><label for="dj_user_id">Associated WordPress User</label></th>
                <td>
                    <select name="dj_user_id" id="dj_user_id" class="regular-text">
                        <option value="">Select User</option>
                        <?php
                        $users = get_users(array('role' => 'dj_artist'));
                        foreach ($users as $user) {
                            echo '<option value="' . $user->ID . '"' . selected($user_id, $user->ID, false) . '>' . 
                                 $user->display_name . ' (' . $user->user_email . ')</option>';
                        }
                        ?>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th><label for="dj_specialisations">Specialisations</label></th>
                <td>
                    <?php
                    $specialisation_options = array(
                        'wedding' => 'Wedding DJ',
                        'corporate' => 'Corporate Events',
                        'birthday' => 'Birthday Parties',
                        'club' => 'Club/Nightclub',
                        'festival' => 'Festivals',
                        'private' => 'Private Parties',
                        'school' => 'School Events',
                        'charity' => 'Charity Events'
                    );
                    
                    foreach ($specialisation_options as $key => $label) {
                        $checked = in_array($key, $specialisations) ? 'checked="checked"' : '';
                        echo '<label><input type="checkbox" name="dj_specialisations[]" value="' . $key . '" ' . $checked . '> ' . $label . '</label><br>';
                    }
                    ?>
                </td>
            </tr>
            
            <tr>
                <th><label for="dj_experience_years">Years of Experience</label></th>
                <td>
                    <input type="number" name="dj_experience_years" id="dj_experience_years" 
                           value="<?php echo esc_attr($experience_years); ?>" min="0" max="50">
                </td>
            </tr>
            
            <tr>
                <th><label for="dj_bio">DJ Biography</label></th>
                <td>
                    <textarea name="dj_bio" id="dj_bio" rows="5" cols="50" class="large-text"><?php echo esc_textarea($bio); ?></textarea>
                    <p class="description">Tell potential clients about your background, style, and what makes you unique.</p>
                </td>
            </tr>
            
            <tr>
                <th><label for="dj_music_styles">Music Styles</label></th>
                <td>
                    <?php
                    $music_style_options = array(
                        'pop' => 'Pop',
                        'rock' => 'Rock',
                        'dance' => 'Dance/Electronic',
                        'hip_hop' => 'Hip Hop',
                        'rnb' => 'R&B',
                        'indie' => 'Indie',
                        'funk' => 'Funk',
                        'disco' => 'Disco',
                        'house' => 'House',
                        'techno' => 'Techno',
                        'reggae' => 'Reggae',
                        'jazz' => 'Jazz',
                        'classical' => 'Classical',
                        'country' => 'Country',
                        'latin' => 'Latin',
                        'afrobeat' => 'Afrobeat',
                        'oldies' => 'Oldies/Classics'
                    );
                    
                    foreach ($music_style_options as $key => $label) {
                        $checked = in_array($key, $music_styles) ? 'checked="checked"' : '';
                        echo '<label><input type="checkbox" name="dj_music_styles[]" value="' . $key . '" ' . $checked . '> ' . $label . '</label><br>';
                    }
                    ?>
                </td>
            </tr>
            
            <tr>
                <th><label for="dj_additional_services">Additional Services</label></th>
                <td>
                    <?php
                    $service_options = array(
                        'mc' => 'MC/Hosting',
                        'lighting' => 'Lighting Setup',
                        'karaoke' => 'Karaoke',
                        'photobooth' => 'Photo Booth',
                        'uplighting' => 'Uplighting',
                        'dance_floor' => 'Dance Floor',
                        'staging' => 'Staging',
                        'ceremony' => 'Ceremony Music',
                        'live_mixing' => 'Live Mixing/Scratching'
                    );
                    
                    foreach ($service_options as $key => $label) {
                        $checked = in_array($key, $additional_services) ? 'checked="checked"' : '';
                        echo '<label><input type="checkbox" name="dj_additional_services[]" value="' . $key . '" ' . $checked . '> ' . $label . '</label><br>';
                    }
                    ?>
                </td>
            </tr>
            
            <tr>
                <th><label>Social Media Links</label></th>
                <td>
                    <p><label>Instagram: <input type="url" name="dj_social_links[instagram]" value="<?php echo esc_attr($social_links['instagram'] ?? ''); ?>" class="regular-text"></label></p>
                    <p><label>Facebook: <input type="url" name="dj_social_links[facebook]" value="<?php echo esc_attr($social_links['facebook'] ?? ''); ?>" class="regular-text"></label></p>
                    <p><label>SoundCloud: <input type="url" name="dj_social_links[soundcloud]" value="<?php echo esc_attr($social_links['soundcloud'] ?? ''); ?>" class="regular-text"></label></p>
                    <p><label>Mixcloud: <input type="url" name="dj_social_links[mixcloud]" value="<?php echo esc_attr($social_links['mixcloud'] ?? ''); ?>" class="regular-text"></label></p>
                    <p><label>YouTube: <input type="url" name="dj_social_links[youtube]" value="<?php echo esc_attr($social_links['youtube'] ?? ''); ?>" class="regular-text"></label></p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    public function render_pricing_commission_meta_box($post) {
        $hourly_rate = get_post_meta($post->ID, 'dj_hourly_rate', true);
        $event_rate = get_post_meta($post->ID, 'dj_event_rate', true);
        $commission_type = get_post_meta($post->ID, 'dj_commission_type', true);
        $commission_rate = get_post_meta($post->ID, 'dj_commission_rate', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th><label for="dj_hourly_rate">Hourly Rate (£)</label></th>
                <td>
                    <input type="number" name="dj_hourly_rate" id="dj_hourly_rate" 
                           value="<?php echo esc_attr($hourly_rate); ?>" min="0" step="0.01" class="small-text">
                    <p class="description">Base hourly rate for events</p>
                </td>
            </tr>
            
            <tr>
                <th><label for="dj_event_rate">Event Rate (£)</label></th>
                <td>
                    <input type="number" name="dj_event_rate" id="dj_event_rate" 
                           value="<?php echo esc_attr($event_rate); ?>" min="0" step="0.01" class="small-text">
                    <p class="description">Fixed rate for events (alternative to hourly)</p>
                </td>
            </tr>
            
            <tr>
                <th><label for="dj_commission_type">Commission Structure</label></th>
                <td>
                    <p class="description">Agency takes 25% commission from all bookings</p>
                    <em>This is set by the agency and cannot be modified</em>
                </td>
            </tr>
        </table>
        <?php
    }
    
    public function render_location_travel_meta_box($post) {
        $base_location = get_post_meta($post->ID, 'dj_base_location', true);
        $base_postcode = get_post_meta($post->ID, 'dj_base_postcode', true);
        $coverage_areas = get_post_meta($post->ID, 'dj_coverage_areas', true);
        $travel_rate = get_post_meta($post->ID, 'dj_travel_rate', true);
        $travel_free_miles = get_post_meta($post->ID, 'dj_travel_free_miles', true);
        $accommodation_rate = get_post_meta($post->ID, 'dj_accommodation_rate', true);
        
        // Parse coverage areas array
        $coverage_areas = $coverage_areas ? json_decode($coverage_areas, true) : array();
        
        ?>
        <table class="form-table">
            <tr>
                <th><label for="dj_base_location">Base Location</label></th>
                <td>
                    <input type="text" name="dj_base_location" id="dj_base_location" 
                           value="<?php echo esc_attr($base_location); ?>" class="regular-text">
                    <p class="description">City/Town where you're based</p>
                </td>
            </tr>
            
            <tr>
                <th><label for="dj_base_postcode">Base Postcode</label></th>
                <td>
                    <input type="text" name="dj_base_postcode" id="dj_base_postcode" 
                           value="<?php echo esc_attr($base_postcode); ?>" class="regular-text">
                    <p class="description">Used for distance calculations</p>
                </td>
            </tr>
            
            <tr>
                <th><label>Coverage Areas</label></th>
                <td>
                    <?php
                    $area_options = array(
                        'hertfordshire' => 'Hertfordshire',
                        'london' => 'London',
                        'essex' => 'Essex',
                        'cambridgeshire' => 'Cambridgeshire',
                        'suffolk' => 'Suffolk',
                        'bedfordshire' => 'Bedfordshire',
                        'buckinghamshire' => 'Buckinghamshire',
                        'kent' => 'Kent',
                        'surrey' => 'Surrey',
                        'uk_wide' => 'UK Wide',
                        'international' => 'International'
                    );
                    
                    foreach ($area_options as $key => $label) {
                        $checked = in_array($key, $coverage_areas) ? 'checked="checked"' : '';
                        echo '<label><input type="checkbox" name="dj_coverage_areas[]" value="' . $key . '" ' . $checked . '> ' . $label . '</label><br>';
                    }
                    ?>
                </td>
            </tr>
            
            <tr>
                <th><label for="dj_travel_free_miles">Free Travel Miles</label></th>
                <td>
                    <input type="number" name="dj_travel_free_miles" id="dj_travel_free_miles" 
                           value="<?php echo esc_attr($travel_free_miles ?: '100'); ?>" min="0" class="small-text">
                    <p class="description">Miles you'll travel for free (default: 100)</p>
                </td>
            </tr>
            
            <tr>
                <th><label for="dj_travel_rate">Travel Rate (£ per mile)</label></th>
                <td>
                    <input type="number" name="dj_travel_rate" id="dj_travel_rate" 
                           value="<?php echo esc_attr($travel_rate ?: '1.00'); ?>" min="0" step="0.01" class="small-text">
                    <p class="description">Rate charged per mile after free travel distance (default: £1.00)</p>
                </td>
            </tr>
            
            <tr>
                <th><label for="dj_accommodation_rate">Accommodation Fee (£)</label></th>
                <td>
                    <input type="number" name="dj_accommodation_rate" id="dj_accommodation_rate" 
                           value="<?php echo esc_attr($accommodation_rate ?: '200'); ?>" min="0" step="0.01" class="small-text">
                    <p class="description">Fee for overnight accommodation (default: £200)</p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    public function render_packages_equipment_meta_box($post) {
        $booking_packages = get_post_meta($post->ID, 'dj_booking_packages', true);
        $equipment_included = get_post_meta($post->ID, 'dj_equipment_included', true);
        
        // Parse arrays
        $booking_packages = $booking_packages ? json_decode($booking_packages, true) : array();
        $equipment_included = $equipment_included ? json_decode($equipment_included, true) : array();
        
        ?>
        <div id="dj-packages-container">
            <h4>Booking Packages</h4>
            <p class="description">Create preset packages for different types of events</p>
            
            <div id="dj-packages-list">
                <?php
                if (!empty($booking_packages)) {
                    foreach ($booking_packages as $index => $package) {
                        $this->render_package_fields($index, $package);
                    }
                }
                ?>
            </div>
            
            <button type="button" id="add-package-btn" class="button">Add Package</button>
        </div>
        
        <div id="dj-equipment-container" style="margin-top: 30px;">
            <h4>Equipment Included</h4>
            <p class="description">Select equipment you can provide</p>
            
            <?php
            $equipment_options = array(
                'sound_system_small' => 'Sound System (Small Events)',
                'sound_system_medium' => 'Sound System (Medium Events)',
                'sound_system_large' => 'Sound System (Large Events)',
                'wireless_microphones' => 'Wireless Microphones',
                'wired_microphones' => 'Wired Microphones',
                'dj_booth' => 'DJ Booth/Table',
                'uplighting' => 'Uplighting',
                'moving_heads' => 'Moving Head Lights',
                'mirror_ball' => 'Mirror Ball & Spot',
                'lasers' => 'Laser Lights',
                'smoke_machine' => 'Smoke/Haze Machine',
                'led_strips' => 'LED Strip Lights',
                'dance_floor' => 'Portable Dance Floor',
                'staging' => 'Staging/Platform'
            );
            
            foreach ($equipment_options as $key => $label) {
                $checked = in_array($key, $equipment_included) ? 'checked="checked"' : '';
                echo '<label><input type="checkbox" name="dj_equipment_included[]" value="' . $key . '" ' . $checked . '> ' . $label . '</label><br>';
            }
            ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            let packageIndex = <?php echo count($booking_packages); ?>;
            
            $('#add-package-btn').click(function() {
                const packageHtml = `
                    <div class="dj-package-item" style="border: 1px solid #ddd; padding: 15px; margin-bottom: 15px;">
                        <h5>Package ${packageIndex + 1}</h5>
                        <table class="form-table">
                            <tr>
                                <th><label>Package Name</label></th>
                                <td><input type="text" name="dj_booking_packages[${packageIndex}][name]" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th><label>Description</label></th>
                                <td><textarea name="dj_booking_packages[${packageIndex}][description]" rows="3" class="large-text"></textarea></td>
                            </tr>
                            <tr>
                                <th><label>Price (£)</label></th>
                                <td><input type="number" name="dj_booking_packages[${packageIndex}][price]" min="0" step="0.01" class="small-text"></td>
                            </tr>
                            <tr>
                                <th><label>Duration (hours)</label></th>
                                <td><input type="number" name="dj_booking_packages[${packageIndex}][duration]" min="1" class="small-text"></td>
                            </tr>
                            <tr>
                                <th><label>Event Types</label></th>
                                <td>
                                    <label><input type="checkbox" name="dj_booking_packages[${packageIndex}][event_types][]" value="wedding"> Wedding</label><br>
                                    <label><input type="checkbox" name="dj_booking_packages[${packageIndex}][event_types][]" value="corporate"> Corporate</label><br>
                                    <label><input type="checkbox" name="dj_booking_packages[${packageIndex}][event_types][]" value="birthday"> Birthday</label><br>
                                    <label><input type="checkbox" name="dj_booking_packages[${packageIndex}][event_types][]" value="private"> Private Party</label><br>
                                </td>
                            </tr>
                        </table>
                        <button type="button" class="button remove-package-btn">Remove Package</button>
                    </div>
                `;
                
                $('#dj-packages-list').append(packageHtml);
                packageIndex++;
            });
            
            $(document).on('click', '.remove-package-btn', function() {
                $(this).closest('.dj-package-item').remove();
            });
        });
        </script>
        <?php
    }
    
    private function render_package_fields($index, $package) {
        $event_types = $package['event_types'] ?? array();
        ?>
        <div class="dj-package-item" style="border: 1px solid #ddd; padding: 15px; margin-bottom: 15px;">
            <h5>Package <?php echo $index + 1; ?></h5>
            <table class="form-table">
                <tr>
                    <th><label>Package Name</label></th>
                    <td><input type="text" name="dj_booking_packages[<?php echo $index; ?>][name]" 
                               value="<?php echo esc_attr($package['name'] ?? ''); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label>Description</label></th>
                    <td><textarea name="dj_booking_packages[<?php echo $index; ?>][description]" 
                                  rows="3" class="large-text"><?php echo esc_textarea($package['description'] ?? ''); ?></textarea></td>
                </tr>
                <tr>
                    <th><label>Price (£)</label></th>
                    <td><input type="number" name="dj_booking_packages[<?php echo $index; ?>][price]" 
                               value="<?php echo esc_attr($package['price'] ?? ''); ?>" min="0" step="0.01" class="small-text"></td>
                </tr>
                <tr>
                    <th><label>Duration (hours)</label></th>
                    <td><input type="number" name="dj_booking_packages[<?php echo $index; ?>][duration]" 
                               value="<?php echo esc_attr($package['duration'] ?? ''); ?>" min="1" class="small-text"></td>
                </tr>
                <tr>
                    <th><label>Event Types</label></th>
                    <td>
                        <?php
                        $event_type_options = array('wedding' => 'Wedding', 'corporate' => 'Corporate', 'birthday' => 'Birthday', 'private' => 'Private Party');
                        foreach ($event_type_options as $key => $label) {
                            $checked = in_array($key, $event_types) ? 'checked="checked"' : '';
                            echo '<label><input type="checkbox" name="dj_booking_packages[' . $index . '][event_types][]" value="' . $key . '" ' . $checked . '> ' . $label . '</label><br>';
                        }
                        ?>
                    </td>
                </tr>
            </table>
            <button type="button" class="button remove-package-btn">Remove Package</button>
        </div>
        <?php
    }
    
    public function save_dj_profile_meta($post_id) {
        if (!isset($_POST['dj_profile_meta_nonce']) || !wp_verify_nonce($_POST['dj_profile_meta_nonce'], 'dj_profile_meta_nonce')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save all meta fields
        $meta_fields = array(
            'dj_user_id' => 'sanitize_text_field',
            'dj_base_location' => 'sanitize_text_field',
            'dj_base_postcode' => 'sanitize_text_field',
            'dj_hourly_rate' => 'floatval',
            'dj_event_rate' => 'floatval',
            'dj_travel_rate' => 'floatval',
            'dj_travel_free_miles' => 'intval',
            'dj_accommodation_rate' => 'floatval',
            'dj_experience_years' => 'intval',
            'dj_bio' => 'sanitize_textarea_field'
        );
        
        foreach ($meta_fields as $field => $sanitize_function) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, $sanitize_function($_POST[$field]));
            }
        }
        
        // Save array fields
        $array_fields = array(
            'dj_specialisations',
            'dj_coverage_areas',
            'dj_music_styles',
            'dj_additional_services',
            'dj_equipment_included'
        );
        
        foreach ($array_fields as $field) {
            if (isset($_POST[$field]) && is_array($_POST[$field])) {
                $clean_array = array_map('sanitize_text_field', $_POST[$field]);
                update_post_meta($post_id, $field, json_encode($clean_array));
            } else {
                update_post_meta($post_id, $field, json_encode(array()));
            }
        }
        
        // Save social links
        if (isset($_POST['dj_social_links']) && is_array($_POST['dj_social_links'])) {
            $social_links = array();
            foreach ($_POST['dj_social_links'] as $platform => $url) {
                if (!empty($url)) {
                    $social_links[sanitize_key($platform)] = esc_url_raw($url);
                }
            }
            update_post_meta($post_id, 'dj_social_links', json_encode($social_links));
        }
        
        // Save booking packages
        if (isset($_POST['dj_booking_packages']) && is_array($_POST['dj_booking_packages'])) {
            $packages = array();
            foreach ($_POST['dj_booking_packages'] as $package) {
                if (!empty($package['name'])) {
                    $clean_package = array(
                        'name' => sanitize_text_field($package['name']),
                        'description' => sanitize_textarea_field($package['description']),
                        'price' => floatval($package['price']),
                        'duration' => intval($package['duration']),
                        'event_types' => isset($package['event_types']) ? array_map('sanitize_text_field', $package['event_types']) : array()
                    );
                    $packages[] = $clean_package;
                }
            }
            update_post_meta($post_id, 'dj_booking_packages', json_encode($packages));
        }
    }
    
    public function get_dj_profile($dj_id) {
        $profile = get_post($dj_id);
        if (!$profile || $profile->post_type !== 'dj_profile') {
            return false;
        }
        
        $meta_data = get_post_meta($dj_id);
        $profile_data = array(
            'id' => $dj_id,
            'name' => $profile->post_title,
            'description' => $profile->post_content,
            'featured_image' => get_the_post_thumbnail_url($dj_id, 'full'),
            'user_id' => $meta_data['dj_user_id'][0] ?? '',
            'specialisations' => json_decode($meta_data['dj_specialisations'][0] ?? '[]', true),
            'base_location' => $meta_data['dj_base_location'][0] ?? '',
            'base_postcode' => $meta_data['dj_base_postcode'][0] ?? '',
            'coverage_areas' => json_decode($meta_data['dj_coverage_areas'][0] ?? '[]', true),
            'hourly_rate' => floatval($meta_data['dj_hourly_rate'][0] ?? 0),
            'event_rate' => floatval($meta_data['dj_event_rate'][0] ?? 0),
            'travel_rate' => floatval($meta_data['dj_travel_rate'][0] ?? 1.00),
            'travel_free_miles' => intval($meta_data['dj_travel_free_miles'][0] ?? 100),
            'accommodation_rate' => floatval($meta_data['dj_accommodation_rate'][0] ?? 200),
            'experience_years' => intval($meta_data['dj_experience_years'][0] ?? 0),
            'bio' => $meta_data['dj_bio'][0] ?? '',
            'music_styles' => json_decode($meta_data['dj_music_styles'][0] ?? '[]', true),
            'additional_services' => json_decode($meta_data['dj_additional_services'][0] ?? '[]', true),
            'equipment_included' => json_decode($meta_data['dj_equipment_included'][0] ?? '[]', true),
            'social_links' => json_decode($meta_data['dj_social_links'][0] ?? '{}', true),
            'booking_packages' => json_decode($meta_data['dj_booking_packages'][0] ?? '[]', true)
        );
        
        return $profile_data;
    }
    
    public function get_dj_rates() {
        check_ajax_referer('dj_hire_nonce', 'nonce');
        
        $dj_id = intval($_POST['dj_id']);
        $event_date = sanitize_text_field($_POST['event_date']);
        $venue_postcode = sanitize_text_field($_POST['venue_postcode']);
        $event_duration = intval($_POST['event_duration']);
        $package_id = sanitize_text_field($_POST['package_id'] ?? '');
        
        $profile = $this->get_dj_profile($dj_id);
        if (!$profile) {
            wp_send_json_error('DJ profile not found');
        }
        
        // Calculate base rate
        $base_rate = 0;
        if (!empty($package_id)) {
            // Find specific package
            foreach ($profile['booking_packages'] as $package) {
                if ($package['name'] === $package_id) {
                    $base_rate = $package['price'];
                    break;
                }
            }
        } else {
            // Use hourly or event rate
            if ($profile['event_rate'] > 0) {
                $base_rate = $profile['event_rate'];
            } else {
                $base_rate = $profile['hourly_rate'] * $event_duration;
            }
        }
        
        // Calculate travel costs
        $travel_cost = 0;
        $accommodation_cost = 0;
        
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
        
        $breakdown = array(
            'base_rate' => $base_rate,
            'travel_cost' => $travel_cost,
            'accommodation_cost' => $accommodation_cost,
            'total_cost' => $total_cost,
            'agency_commission' => $agency_commission,
            'dj_earnings' => $total_cost - $agency_commission,
            'deposit_amount' => $deposit_amount,
            'final_payment' => $total_cost - $deposit_amount,
            'distance' => $distance ?? 0
        );
        
        wp_send_json_success($breakdown);
    }
    
    public function update_availability() {
        check_ajax_referer('dj_hire_nonce', 'nonce');
        
        if (!current_user_can('manage_dj_calendar')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $dj_id = intval($_POST['dj_id']);
        $date = sanitize_text_field($_POST['date']);
        $status = sanitize_text_field($_POST['status']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_availability';
        
        // Check if record exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE dj_id = %d AND date = %s",
            $dj_id, $date
        ));
        
        if ($existing) {
            // Update existing record
            $result = $wpdb->update(
                $table_name,
                array('status' => $status),
                array('dj_id' => $dj_id, 'date' => $date),
                array('%s'),
                array('%d', '%s')
            );
        } else {
            // Insert new record
            $result = $wpdb->insert(
                $table_name,
                array(
                    'dj_id' => $dj_id,
                    'date' => $date,
                    'status' => $status
                ),
                array('%d', '%s', '%s')
            );
        }
        
        if ($result !== false) {
            wp_send_json_success('Availability updated');
        } else {
            wp_send_json_error('Failed to update availability');
        }
    }
}
?>