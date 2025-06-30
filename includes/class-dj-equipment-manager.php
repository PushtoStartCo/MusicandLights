<?php
/**
 * DJ Equipment Manager Class
 * Manages equipment profiles and packages for DJs
 */

class DJ_Equipment_Manager {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('add_meta_boxes', array($this, 'add_equipment_meta_boxes'));
        add_action('save_post', array($this, 'save_equipment_meta'));
        add_action('wp_ajax_get_equipment_for_dj', array($this, 'get_equipment_for_dj'));
        add_action('wp_ajax_nopriv_get_equipment_for_dj', array($this, 'get_equipment_for_dj'));
        add_action('wp_ajax_calculate_equipment_cost', array($this, 'calculate_equipment_cost'));
        add_action('wp_ajax_nopriv_calculate_equipment_cost', array($this, 'calculate_equipment_cost'));
    }
    
    public function init() {
        // Register equipment categories taxonomy
        register_taxonomy('equipment_category', 'dj_equipment', array(
            'labels' => array(
                'name' => __('Equipment Categories', 'musicandlights'),
                'singular_name' => __('Equipment Category', 'musicandlights'),
                'search_items' => __('Search Categories', 'musicandlights'),
                'all_items' => __('All Categories', 'musicandlights'),
                'parent_item' => __('Parent Category', 'musicandlights'),
                'parent_item_colon' => __('Parent Category:', 'musicandlights'),
                'edit_item' => __('Edit Category', 'musicandlights'),
                'update_item' => __('Update Category', 'musicandlights'),
                'add_new_item' => __('Add New Category', 'musicandlights'),
                'new_item_name' => __('New Category Name', 'musicandlights'),
                'menu_name' => __('Categories', 'musicandlights')
            ),
            'hierarchical' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'equipment-category')
        ));
        
        // Add default equipment categories
        $this->create_default_categories();
    }
    
    private function create_default_categories() {
        $categories = array(
            'sound-systems' => __('Sound Systems', 'musicandlights'),
            'lighting' => __('Lighting', 'musicandlights'),
            'effects' => __('Special Effects', 'musicandlights'),
            'microphones' => __('Microphones', 'musicandlights'),
            'staging' => __('Staging & Decor', 'musicandlights'),
            'accessories' => __('Accessories', 'musicandlights')
        );
        
        foreach ($categories as $slug => $name) {
            if (!term_exists($slug, 'equipment_category')) {
                wp_insert_term($name, 'equipment_category', array('slug' => $slug));
            }
        }
    }
    
    public function add_equipment_meta_boxes() {
        add_meta_box(
            'equipment_details',
            __('Equipment Details', 'musicandlights'),
            array($this, 'render_equipment_details_meta_box'),
            'dj_equipment',
            'normal',
            'high'
        );
        
        add_meta_box(
            'equipment_specifications',
            __('Technical Specifications', 'musicandlights'),
            array($this, 'render_equipment_specifications_meta_box'),
            'dj_equipment',
            'normal',
            'high'
        );
        
        add_meta_box(
            'equipment_pricing',
            __('Pricing & Availability', 'musicandlights'),
            array($this, 'render_equipment_pricing_meta_box'),
            'dj_equipment',
            'side',
            'high'
        );
    }
    
    public function render_equipment_details_meta_box($post) {
        wp_nonce_field('equipment_meta_nonce', 'equipment_meta_nonce');
        
        $equipment_type = get_post_meta($post->ID, 'equipment_type', true);
        $brand = get_post_meta($post->ID, 'equipment_brand', true);
        $model = get_post_meta($post->ID, 'equipment_model', true);
        $suitable_events = json_decode(get_post_meta($post->ID, 'suitable_events', true) ?: '[]', true);
        $guest_capacity = get_post_meta($post->ID, 'guest_capacity', true);
        $setup_time = get_post_meta($post->ID, 'setup_time', true);
        $power_requirements = get_post_meta($post->ID, 'power_requirements', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th><label for="equipment_type"><?php _e('Equipment Type', 'musicandlights'); ?></label></th>
                <td>
                    <select name="equipment_type" id="equipment_type" class="regular-text">
                        <option value=""><?php _e('Select Type', 'musicandlights'); ?></option>
                        <option value="sound_system" <?php selected($equipment_type, 'sound_system'); ?>><?php _e('Sound System', 'musicandlights'); ?></option>
                        <option value="lighting_rig" <?php selected($equipment_type, 'lighting_rig'); ?>><?php _e('Lighting Rig', 'musicandlights'); ?></option>
                        <option value="microphone" <?php selected($equipment_type, 'microphone'); ?>><?php _e('Microphone', 'musicandlights'); ?></option>
                        <option value="dj_controller" <?php selected($equipment_type, 'dj_controller'); ?>><?php _e('DJ Controller', 'musicandlights'); ?></option>
                        <option value="effects_unit" <?php selected($equipment_type, 'effects_unit'); ?>><?php _e('Effects Unit', 'musicandlights'); ?></option>
                        <option value="staging" <?php selected($equipment_type, 'staging'); ?>><?php _e('Staging', 'musicandlights'); ?></option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th><label for="equipment_brand"><?php _e('Brand', 'musicandlights'); ?></label></th>
                <td><input type="text" name="equipment_brand" id="equipment_brand" 
                           value="<?php echo esc_attr($brand); ?>" class="regular-text"></td>
            </tr>
            
            <tr>
                <th><label for="equipment_model"><?php _e('Model', 'musicandlights'); ?></label></th>
                <td><input type="text" name="equipment_model" id="equipment_model" 
                           value="<?php echo esc_attr($model); ?>" class="regular-text"></td>
            </tr>
            
            <tr>
                <th><label><?php _e('Suitable for Events', 'musicandlights'); ?></label></th>
                <td>
                    <?php
                    $event_types = array(
                        'wedding' => __('Weddings', 'musicandlights'),
                        'corporate' => __('Corporate Events', 'musicandlights'),
                        'birthday' => __('Birthday Parties', 'musicandlights'),
                        'festival' => __('Festivals', 'musicandlights'),
                        'club' => __('Club/Nightclub', 'musicandlights'),
                        'private' => __('Private Parties', 'musicandlights'),
                        'outdoor' => __('Outdoor Events', 'musicandlights'),
                        'indoor' => __('Indoor Events', 'musicandlights')
                    );
                    
                    foreach ($event_types as $key => $label) {
                        $checked = in_array($key, $suitable_events) ? 'checked="checked"' : '';
                        echo '<label style="display: block; margin-bottom: 5px;"><input type="checkbox" name="suitable_events[]" value="' . $key . '" ' . $checked . '> ' . $label . '</label>';
                    }
                    ?>
                </td>
            </tr>
            
            <tr>
                <th><label for="guest_capacity"><?php _e('Guest Capacity', 'musicandlights'); ?></label></th>
                <td>
                    <select name="guest_capacity" id="guest_capacity" class="regular-text">
                        <option value=""><?php _e('Select Capacity', 'musicandlights'); ?></option>
                        <option value="0-50" <?php selected($guest_capacity, '0-50'); ?>>0-50 guests</option>
                        <option value="50-100" <?php selected($guest_capacity, '50-100'); ?>>50-100 guests</option>
                        <option value="100-200" <?php selected($guest_capacity, '100-200'); ?>>100-200 guests</option>
                        <option value="200-500" <?php selected($guest_capacity, '200-500'); ?>>200-500 guests</option>
                        <option value="500+" <?php selected($guest_capacity, '500+'); ?>>500+ guests</option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th><label for="setup_time"><?php _e('Setup Time (minutes)', 'musicandlights'); ?></label></th>
                <td><input type="number" name="setup_time" id="setup_time" 
                           value="<?php echo esc_attr($setup_time); ?>" min="0" class="small-text"> minutes</td>
            </tr>
            
            <tr>
                <th><label for="power_requirements"><?php _e('Power Requirements', 'musicandlights'); ?></label></th>
                <td>
                    <textarea name="power_requirements" id="power_requirements" rows="3" class="large-text"
                              placeholder="e.g., 2x 13A sockets, 16A three-phase"><?php echo esc_textarea($power_requirements); ?></textarea>
                </td>
            </tr>
        </table>
        <?php
    }
    
    public function render_equipment_specifications_meta_box($post) {
        $specifications = json_decode(get_post_meta($post->ID, 'technical_specifications', true) ?: '{}', true);
        $features = json_decode(get_post_meta($post->ID, 'features', true) ?: '[]', true);
        $included_items = get_post_meta($post->ID, 'included_items', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th><label><?php _e('Technical Specifications', 'musicandlights'); ?></label></th>
                <td>
                    <div id="specifications-container">
                        <p class="description"><?php _e('Add key specifications for this equipment', 'musicandlights'); ?></p>
                        <div id="spec-fields">
                            <?php
                            if (!empty($specifications)) {
                                foreach ($specifications as $key => $value) {
                                    echo '<div class="spec-field" style="margin-bottom: 10px;">';
                                    echo '<input type="text" name="spec_key[]" value="' . esc_attr($key) . '" placeholder="' . __('Specification', 'musicandlights') . '" style="width: 30%;">';
                                    echo ' : ';
                                    echo '<input type="text" name="spec_value[]" value="' . esc_attr($value) . '" placeholder="' . __('Value', 'musicandlights') . '" style="width: 30%;">';
                                    echo ' <button type="button" class="button remove-spec">' . __('Remove', 'musicandlights') . '</button>';
                                    echo '</div>';
                                }
                            }
                            ?>
                        </div>
                        <button type="button" id="add-spec" class="button"><?php _e('Add Specification', 'musicandlights'); ?></button>
                    </div>
                </td>
            </tr>
            
            <tr>
                <th><label><?php _e('Key Features', 'musicandlights'); ?></label></th>
                <td>
                    <div id="features-container">
                        <p class="description"><?php _e('List the key features of this equipment', 'musicandlights'); ?></p>
                        <div id="feature-fields">
                            <?php
                            if (!empty($features)) {
                                foreach ($features as $feature) {
                                    echo '<div class="feature-field" style="margin-bottom: 10px;">';
                                    echo '<input type="text" name="features[]" value="' . esc_attr($feature) . '" class="regular-text">';
                                    echo ' <button type="button" class="button remove-feature">' . __('Remove', 'musicandlights') . '</button>';
                                    echo '</div>';
                                }
                            }
                            ?>
                        </div>
                        <button type="button" id="add-feature" class="button"><?php _e('Add Feature', 'musicandlights'); ?></button>
                    </div>
                </td>
            </tr>
            
            <tr>
                <th><label for="included_items"><?php _e('What\'s Included', 'musicandlights'); ?></label></th>
                <td>
                    <textarea name="included_items" id="included_items" rows="4" class="large-text"
                              placeholder="List all items included with this equipment rental"><?php echo esc_textarea($included_items); ?></textarea>
                </td>
            </tr>
        </table>
        
        <script>
        jQuery(document).ready(function($) {
            // Add specification
            $('#add-spec').click(function() {
                const specHtml = `
                    <div class="spec-field" style="margin-bottom: 10px;">
                        <input type="text" name="spec_key[]" placeholder="<?php _e('Specification', 'musicandlights'); ?>" style="width: 30%;">
                        : 
                        <input type="text" name="spec_value[]" placeholder="<?php _e('Value', 'musicandlights'); ?>" style="width: 30%;">
                        <button type="button" class="button remove-spec"><?php _e('Remove', 'musicandlights'); ?></button>
                    </div>
                `;
                $('#spec-fields').append(specHtml);
            });
            
            // Remove specification
            $(document).on('click', '.remove-spec', function() {
                $(this).closest('.spec-field').remove();
            });
            
            // Add feature
            $('#add-feature').click(function() {
                const featureHtml = `
                    <div class="feature-field" style="margin-bottom: 10px;">
                        <input type="text" name="features[]" class="regular-text">
                        <button type="button" class="button remove-feature"><?php _e('Remove', 'musicandlights'); ?></button>
                    </div>
                `;
                $('#feature-fields').append(featureHtml);
            });
            
            // Remove feature
            $(document).on('click', '.remove-feature', function() {
                $(this).closest('.feature-field').remove();
            });
        });
        </script>
        <?php
    }
    
    public function render_equipment_pricing_meta_box($post) {
        $base_price = get_post_meta($post->ID, 'base_price', true);
        $price_per_day = get_post_meta($post->ID, 'price_per_day', true);
        $availability_status = get_post_meta($post->ID, 'availability_status', true);
        $quantity_available = get_post_meta($post->ID, 'quantity_available', true);
        $dj_assignments = json_decode(get_post_meta($post->ID, 'dj_assignments', true) ?: '[]', true);
        
        // Get all DJs
        $djs = get_posts(array(
            'post_type' => 'dj_profile',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        ?>
        <p>
            <label for="base_price"><?php _e('Base Price (£)', 'musicandlights'); ?></label><br>
            <input type="number" name="base_price" id="base_price" 
                   value="<?php echo esc_attr($base_price); ?>" min="0" step="0.01" class="regular-text">
        </p>
        
        <p>
            <label for="price_per_day"><?php _e('Price Per Day (£)', 'musicandlights'); ?></label><br>
            <input type="number" name="price_per_day" id="price_per_day" 
                   value="<?php echo esc_attr($price_per_day); ?>" min="0" step="0.01" class="regular-text">
        </p>
        
        <p>
            <label for="availability_status"><?php _e('Availability Status', 'musicandlights'); ?></label><br>
            <select name="availability_status" id="availability_status" class="regular-text">
                <option value="available" <?php selected($availability_status, 'available'); ?>><?php _e('Available', 'musicandlights'); ?></option>
                <option value="limited" <?php selected($availability_status, 'limited'); ?>><?php _e('Limited Availability', 'musicandlights'); ?></option>
                <option value="unavailable" <?php selected($availability_status, 'unavailable'); ?>><?php _e('Unavailable', 'musicandlights'); ?></option>
                <option value="maintenance" <?php selected($availability_status, 'maintenance'); ?>><?php _e('Under Maintenance', 'musicandlights'); ?></option>
            </select>
        </p>
        
        <p>
            <label for="quantity_available"><?php _e('Quantity Available', 'musicandlights'); ?></label><br>
            <input type="number" name="quantity_available" id="quantity_available" 
                   value="<?php echo esc_attr($quantity_available ?: 1); ?>" min="0" class="small-text">
        </p>
        
        <hr>
        
        <p><strong><?php _e('Assign to DJs', 'musicandlights'); ?></strong></p>
        <p class="description"><?php _e('Select which DJs can offer this equipment', 'musicandlights'); ?></p>
        
        <?php foreach ($djs as $dj): ?>
            <label style="display: block; margin-bottom: 5px;">
                <input type="checkbox" name="dj_assignments[]" value="<?php echo $dj->ID; ?>" 
                       <?php checked(in_array($dj->ID, $dj_assignments)); ?>>
                <?php echo esc_html($dj->post_title); ?>
            </label>
        <?php endforeach; ?>
        <?php
    }
    
    public function save_equipment_meta($post_id) {
        if (!isset($_POST['equipment_meta_nonce']) || !wp_verify_nonce($_POST['equipment_meta_nonce'], 'equipment_meta_nonce')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save basic details
        $fields = array(
            'equipment_type' => 'sanitize_text_field',
            'equipment_brand' => 'sanitize_text_field',
            'equipment_model' => 'sanitize_text_field',
            'guest_capacity' => 'sanitize_text_field',
            'setup_time' => 'intval',
            'power_requirements' => 'sanitize_textarea_field',
            'included_items' => 'sanitize_textarea_field',
            'base_price' => 'floatval',
            'price_per_day' => 'floatval',
            'availability_status' => 'sanitize_text_field',
            'quantity_available' => 'intval'
        );
        
        foreach ($fields as $field => $sanitize_function) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, $sanitize_function($_POST[$field]));
            }
        }
        
        // Save suitable events
        if (isset($_POST['suitable_events']) && is_array($_POST['suitable_events'])) {
            $suitable_events = array_map('sanitize_text_field', $_POST['suitable_events']);
            update_post_meta($post_id, 'suitable_events', json_encode($suitable_events));
        } else {
            update_post_meta($post_id, 'suitable_events', json_encode(array()));
        }
        
        // Save technical specifications
        if (isset($_POST['spec_key']) && isset($_POST['spec_value'])) {
            $specifications = array();
            $spec_keys = $_POST['spec_key'];
            $spec_values = $_POST['spec_value'];
            
            for ($i = 0; $i < count($spec_keys); $i++) {
                if (!empty($spec_keys[$i]) && !empty($spec_values[$i])) {
                    $key = sanitize_text_field($spec_keys[$i]);
                    $value = sanitize_text_field($spec_values[$i]);
                    $specifications[$key] = $value;
                }
            }
            
            update_post_meta($post_id, 'technical_specifications', json_encode($specifications));
        }
        
        // Save features
        if (isset($_POST['features']) && is_array($_POST['features'])) {
            $features = array_filter(array_map('sanitize_text_field', $_POST['features']));
            update_post_meta($post_id, 'features', json_encode(array_values($features)));
        }
        
        // Save DJ assignments
        if (isset($_POST['dj_assignments']) && is_array($_POST['dj_assignments'])) {
            $dj_assignments = array_map('intval', $_POST['dj_assignments']);
            update_post_meta($post_id, 'dj_assignments', json_encode($dj_assignments));
            
            // Update equipment assignments table
            $this->update_equipment_assignments($post_id, $dj_assignments);
        } else {
            update_post_meta($post_id, 'dj_assignments', json_encode(array()));
            $this->update_equipment_assignments($post_id, array());
        }
    }
    
    private function update_equipment_assignments($equipment_id, $dj_ids) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_equipment_assignments';
        
        // Remove existing assignments
        $wpdb->delete($table_name, array('equipment_id' => $equipment_id), array('%d'));
        
        // Add new assignments
        $base_price = get_post_meta($equipment_id, 'base_price', true);
        
        foreach ($dj_ids as $dj_id) {
            $wpdb->insert(
                $table_name,
                array(
                    'dj_id' => $dj_id,
                    'equipment_id' => $equipment_id,
                    'price' => $base_price,
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%d', '%f', '%s')
            );
        }
    }
    
    /**
     * Get equipment available for a specific DJ
     */
    public function get_equipment_for_dj() {
        check_ajax_referer('dj_hire_nonce', 'nonce');
        
        $dj_id = intval($_POST['dj_id']);
        $event_type = sanitize_text_field($_POST['event_type'] ?? '');
        $guest_count = intval($_POST['guest_count'] ?? 0);
        
        // Get equipment assigned to this DJ
        $equipment = $this->get_dj_equipment($dj_id, $event_type, $guest_count);
        
        wp_send_json_success($equipment);
    }
    
    public function get_dj_equipment($dj_id, $event_type = '', $guest_count = 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_equipment_assignments';
        
        // Base query
        $query = "
            SELECT DISTINCT p.*, ea.price as assigned_price
            FROM {$wpdb->posts} p
            INNER JOIN $table_name ea ON p.ID = ea.equipment_id
            WHERE ea.dj_id = %d
            AND p.post_status = 'publish'
            AND p.post_type = 'dj_equipment'
        ";
        
        $results = $wpdb->get_results($wpdb->prepare($query, $dj_id));
        
        $equipment_list = array();
        
        foreach ($results as $equipment) {
            $meta = get_post_meta($equipment->ID);
            $suitable_events = json_decode($meta['suitable_events'][0] ?? '[]', true);
            $guest_capacity = $meta['guest_capacity'][0] ?? '';
            $availability_status = $meta['availability_status'][0] ?? 'available';
            
            // Filter by event type if specified
            if (!empty($event_type) && !in_array($event_type, $suitable_events)) {
                continue;
            }
            
            // Filter by guest capacity if specified
            if ($guest_count > 0 && !$this->is_suitable_for_guest_count($guest_capacity, $guest_count)) {
                continue;
            }
            
            // Skip unavailable equipment
            if ($availability_status === 'unavailable') {
                continue;
            }
            
            $equipment_list[] = array(
                'id' => $equipment->ID,
                'name' => $equipment->post_title,
                'description' => $equipment->post_content,
                'type' => $meta['equipment_type'][0] ?? '',
                'brand' => $meta['equipment_brand'][0] ?? '',
                'model' => $meta['equipment_model'][0] ?? '',
                'price' => $equipment->assigned_price ?: $meta['base_price'][0] ?? 0,
                'price_per_day' => $meta['price_per_day'][0] ?? 0,
                'availability_status' => $availability_status,
                'features' => json_decode($meta['features'][0] ?? '[]', true),
                'specifications' => json_decode($meta['technical_specifications'][0] ?? '{}', true),
                'included_items' => $meta['included_items'][0] ?? '',
                'setup_time' => $meta['setup_time'][0] ?? 0,
                'power_requirements' => $meta['power_requirements'][0] ?? '',
                'thumbnail' => get_the_post_thumbnail_url($equipment->ID, 'medium')
            );
        }
        
        return $equipment_list;
    }
    
    private function is_suitable_for_guest_count($capacity_range, $guest_count) {
        switch ($capacity_range) {
            case '0-50':
                return $guest_count <= 50;
            case '50-100':
                return $guest_count <= 100;
            case '100-200':
                return $guest_count <= 200;
            case '200-500':
                return $guest_count <= 500;
            case '500+':
                return true;
            default:
                return true;
        }
    }
    
    /**
     * Calculate total equipment cost
     */
    public function calculate_equipment_cost() {
        check_ajax_referer('dj_hire_nonce', 'nonce');
        
        $equipment_ids = array_map('intval', $_POST['equipment_ids'] ?? array());
        $event_duration = intval($_POST['event_duration'] ?? 1);
        
        $total_cost = 0;
        $equipment_breakdown = array();
        
        foreach ($equipment_ids as $equipment_id) {
            $base_price = floatval(get_post_meta($equipment_id, 'base_price', true));
            $price_per_day = floatval(get_post_meta($equipment_id, 'price_per_day', true));
            
            // Calculate cost based on duration
            if ($event_duration > 1 && $price_per_day > 0) {
                $equipment_cost = $base_price + (($event_duration - 1) * $price_per_day);
            } else {
                $equipment_cost = $base_price;
            }
            
            $total_cost += $equipment_cost;
            
            $equipment_breakdown[] = array(
                'id' => $equipment_id,
                'name' => get_the_title($equipment_id),
                'cost' => $equipment_cost
            );
        }
        
        wp_send_json_success(array(
            'total_cost' => $total_cost,
            'breakdown' => $equipment_breakdown
        ));
    }
    
    /**
     * Get equipment packages for a DJ
     */
    public function get_dj_equipment_packages($dj_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dj_equipment_assignments';
        
        // Get custom packages created by the DJ
        $packages = array();
        
        // Standard packages based on event size
        $standard_packages = array(
            'small' => array(
                'name' => __('Small Event Package', 'musicandlights'),
                'description' => __('Perfect for intimate gatherings up to 50 guests', 'musicandlights'),
                'guest_range' => '0-50',
                'equipment_types' => array('sound_system', 'microphone', 'lighting_rig')
            ),
            'medium' => array(
                'name' => __('Medium Event Package', 'musicandlights'),
                'description' => __('Ideal for parties and events with 50-200 guests', 'musicandlights'),
                'guest_range' => '50-200',
                'equipment_types' => array('sound_system', 'microphone', 'lighting_rig', 'effects_unit')
            ),
            'large' => array(
                'name' => __('Large Event Package', 'musicandlights'),
                'description' => __('Complete setup for major events with 200+ guests', 'musicandlights'),
                'guest_range' => '200+',
                'equipment_types' => array('sound_system', 'microphone', 'lighting_rig', 'effects_unit', 'staging')
            )
        );
        
        // Build packages based on available equipment
        foreach ($standard_packages as $key => $package_template) {
            $package_equipment = array();
            $package_price = 0;
            
            // Get equipment for each type
            foreach ($package_template['equipment_types'] as $equipment_type) {
                $equipment_query = $wpdb->prepare("
                    SELECT p.ID, p.post_title, pm.meta_value as base_price
                    FROM {$wpdb->posts} p
                    INNER JOIN $table_name ea ON p.ID = ea.equipment_id
                    INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'equipment_type'
                    INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'base_price'
                    WHERE ea.dj_id = %d
                    AND pm.meta_value = %s
                    AND p.post_status = 'publish'
                    ORDER BY pm2.meta_value ASC
                    LIMIT 1
                ", $dj_id, $equipment_type);
                
                $equipment = $wpdb->get_row($equipment_query);
                
                if ($equipment) {
                    $package_equipment[] = array(
                        'id' => $equipment->ID,
                        'name' => $equipment->post_title,
                        'type' => $equipment_type
                    );
                    $package_price += floatval($equipment->base_price);
                }
            }
            
            if (!empty($package_equipment)) {
                $packages[$key] = array(
                    'name' => $package_template['name'],
                    'description' => $package_template['description'],
                    'equipment' => $package_equipment,
                    'price' => $package_price,
                    'guest_range' => $package_template['guest_range']
                );
            }
        }
        
        return $packages;
    }
    
    /**
     * Check equipment availability for a date
     */
    public function check_equipment_availability($equipment_id, $date) {
        global $wpdb;
        
        // Check if equipment is booked on this date
        $bookings = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'event_date'
            INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'equipment_ids'
            WHERE p.post_type = 'dj_booking'
            AND p.post_status IN ('confirmed', 'deposit_paid', 'paid_in_full')
            AND pm1.meta_value = %s
            AND pm2.meta_value LIKE %s
        ", $date, '%"' . $equipment_id . '"%'));
        
        $quantity_available = get_post_meta($equipment_id, 'quantity_available', true) ?: 1;
        
        return $bookings < $quantity_available;
    }
    
    /**
     * Reserve equipment for a booking
     */
    public function reserve_equipment($booking_id, $equipment_ids) {
        // Store equipment IDs with the booking
        update_post_meta($booking_id, 'equipment_ids', json_encode($equipment_ids));
        
        // Log equipment reservation
        foreach ($equipment_ids as $equipment_id) {
            $this->log_equipment_reservation($booking_id, $equipment_id);
        }
    }
    
    private function log_equipment_reservation($booking_id, $equipment_id) {
        // This could be expanded to track equipment usage history
        $reservation_log = get_post_meta($equipment_id, 'reservation_log', true) ?: array();
        
        $reservation_log[] = array(
            'booking_id' => $booking_id,
            'reserved_date' => current_time('mysql'),
            'event_date' => get_post_meta($booking_id, 'event_date', true)
        );
        
        update_post_meta($equipment_id, 'reservation_log', $reservation_log);
    }
    
    /**
     * Get equipment suggestions based on event details
     */
    public function suggest_equipment($event_type, $guest_count, $venue_type = '') {
        $suggestions = array();
        
        // Sound system suggestions
        if ($guest_count <= 50) {
            $suggestions['sound'] = array(
                'type' => 'sound_system',
                'recommendation' => __('Compact PA System', 'musicandlights'),
                'reason' => __('Perfect for intimate venues with clear sound coverage', 'musicandlights')
            );
        } elseif ($guest_count <= 200) {
            $suggestions['sound'] = array(
                'type' => 'sound_system',
                'recommendation' => __('Professional PA System with Subwoofer', 'musicandlights'),
                'reason' => __('Provides full-range sound with deep bass for dancing', 'musicandlights')
            );
        } else {
            $suggestions['sound'] = array(
                'type' => 'sound_system',
                'recommendation' => __('Line Array System', 'musicandlights'),
                'reason' => __('Even sound distribution for large venues', 'musicandlights')
            );
        }
        
        // Lighting suggestions based on event type
        switch ($event_type) {
            case 'wedding':
                $suggestions['lighting'] = array(
                    'type' => 'lighting_rig',
                    'recommendation' => __('Uplighting Package with Warm Tones', 'musicandlights'),
                    'reason' => __('Creates romantic ambiance for wedding receptions', 'musicandlights')
                );
                break;
                
            case 'corporate':
                $suggestions['lighting'] = array(
                    'type' => 'lighting_rig',
                    'recommendation' => __('Professional Stage Lighting', 'musicandlights'),
                    'reason' => __('Ensures speakers and presentations are well-lit', 'musicandlights')
                );
                break;
                
            case 'birthday':
            case 'private':
                $suggestions['lighting'] = array(
                    'type' => 'lighting_rig',
                    'recommendation' => __('Party Lighting with Moving Heads', 'musicandlights'),
                    'reason' => __('Creates dynamic party atmosphere', 'musicandlights')
                );
                break;
        }
        
        // Microphone suggestions
        if (in_array($event_type, array('wedding', 'corporate'))) {
            $suggestions['microphone'] = array(
                'type' => 'microphone',
                'recommendation' => __('Wireless Microphone System', 'musicandlights'),
                'reason' => __('Essential for speeches and announcements', 'musicandlights')
            );
        }
        
        // Effects suggestions
        if ($venue_type === 'outdoor' || $guest_count > 200) {
            $suggestions['effects'] = array(
                'type' => 'effects_unit',
                'recommendation' => __('Haze Machine', 'musicandlights'),
                'reason' => __('Enhances lighting effects visibility', 'musicandlights')
            );
        }
        
        return $suggestions;
    }
    
    /**
     * Generate equipment list for contract/invoice
     */
    public function generate_equipment_list($booking_id) {
        $equipment_ids = json_decode(get_post_meta($booking_id, 'equipment_ids', true) ?: '[]', true);
        $equipment_list = array();
        
        foreach ($equipment_ids as $equipment_id) {
            $equipment = get_post($equipment_id);
            if ($equipment) {
                $equipment_list[] = array(
                    'name' => $equipment->post_title,
                    'type' => get_post_meta($equipment_id, 'equipment_type', true),
                    'brand' => get_post_meta($equipment_id, 'equipment_brand', true),
                    'model' => get_post_meta($equipment_id, 'equipment_model', true),
                    'price' => get_post_meta($equipment_id, 'base_price', true)
                );
            }
        }
        
        return $equipment_list;
    }
    
    /**
     * Equipment maintenance tracking
     */
    public function schedule_maintenance($equipment_id, $maintenance_date, $notes = '') {
        $maintenance_log = get_post_meta($equipment_id, 'maintenance_log', true) ?: array();
        
        $maintenance_log[] = array(
            'scheduled_date' => $maintenance_date,
            'notes' => $notes,
            'created_at' => current_time('mysql'),
            'status' => 'scheduled'
        );
        
        update_post_meta($equipment_id, 'maintenance_log', $maintenance_log);
        
        // Update availability status if maintenance date is soon
        if (strtotime($maintenance_date) <= strtotime('+7 days')) {
            update_post_meta($equipment_id, 'availability_status', 'maintenance');
        }
    }
    
    /**
     * Get equipment statistics
     */
    public function get_equipment_stats($equipment_id) {
        global $wpdb;
        
        // Get booking count
        $booking_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'equipment_ids'
            WHERE p.post_type = 'dj_booking'
            AND p.post_status IN ('completed', 'paid_in_full')
            AND pm.meta_value LIKE %s
        ", '%"' . $equipment_id . '"%'));
        
        // Get revenue generated
        $revenue_data = $wpdb->get_results($wpdb->prepare("
            SELECT pm2.meta_value as total_amount
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'equipment_ids'
            INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'total_amount'
            WHERE p.post_type = 'dj_booking'
            AND p.post_status IN ('completed', 'paid_in_full')
            AND pm1.meta_value LIKE %s
        ", '%"' . $equipment_id . '"%'));
        
        $total_revenue = 0;
        foreach ($revenue_data as $booking) {
            $total_revenue += floatval($booking->total_amount);
        }
        
        // Get average rating (if reviews are implemented)
        $avg_rating = 0; // Placeholder for review system
        
        return array(
            'booking_count' => $booking_count,
            'total_revenue' => $total_revenue,
            'average_revenue' => $booking_count > 0 ? $total_revenue / $booking_count : 0,
            'average_rating' => $avg_rating,
            'last_booked' => $this->get_last_booking_date($equipment_id)
        );
    }
    
    private function get_last_booking_date($equipment_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare("
            SELECT pm2.meta_value
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'equipment_ids'
            INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'event_date'
            WHERE p.post_type = 'dj_booking'
            AND p.post_status IN ('completed', 'paid_in_full')
            AND pm1.meta_value LIKE %s
            ORDER BY pm2.meta_value DESC
            LIMIT 1
        ", '%"' . $equipment_id . '"%'));
    }
}
?>