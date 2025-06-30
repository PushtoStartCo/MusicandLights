<?php
/**
 * Distance Calculator Class
 * Calculates distances between UK postcodes for travel cost calculations
 */

class Distance_Calculator {
    
    private $google_maps_api_key;
    private $cache_duration = 2592000; // 30 days in seconds
    
    public function __construct() {
        $this->google_maps_api_key = get_option('musicandlights_google_maps_api_key', '');
        
        add_action('wp_ajax_calculate_distance', array($this, 'ajax_calculate_distance'));
        add_action('wp_ajax_nopriv_calculate_distance', array($this, 'ajax_calculate_distance'));
        add_action('wp_ajax_validate_postcode', array($this, 'ajax_validate_postcode'));
        add_action('wp_ajax_nopriv_validate_postcode', array($this, 'ajax_validate_postcode'));
    }
    
    /**
     * Calculate distance between two postcodes
     */
    public function calculate_distance($postcode1, $postcode2) {
        // Normalize postcodes
        $postcode1 = $this->normalize_postcode($postcode1);
        $postcode2 = $this->normalize_postcode($postcode2);
        
        if (!$postcode1 || !$postcode2) {
            return false;
        }
        
        // Check cache first
        $cache_key = 'distance_' . md5($postcode1 . '_' . $postcode2);
        $cached_distance = get_transient($cache_key);
        
        if ($cached_distance !== false) {
            return $cached_distance;
        }
        
        // Calculate distance
        $distance = $this->calculate_distance_api($postcode1, $postcode2);
        
        if ($distance !== false) {
            // Cache the result
            set_transient($cache_key, $distance, $this->cache_duration);
        }
        
        return $distance;
    }
    
    /**
     * Calculate distance using Google Maps API
     */
    private function calculate_distance_api($postcode1, $postcode2) {
        if (empty($this->google_maps_api_key)) {
            // Fallback to approximate calculation
            return $this->calculate_distance_approximate($postcode1, $postcode2);
        }
        
        $url = 'https://maps.googleapis.com/maps/api/distancematrix/json';
        $params = array(
            'origins' => urlencode($postcode1 . ', UK'),
            'destinations' => urlencode($postcode2 . ', UK'),
            'mode' => 'driving',
            'units' => 'imperial',
            'key' => $this->google_maps_api_key
        );
        
        $request_url = $url . '?' . http_build_query($params);
        $response = wp_remote_get($request_url, array('timeout' => 10));
        
        if (is_wp_error($response)) {
            error_log('Distance API Error: ' . $response->get_error_message());
            return $this->calculate_distance_approximate($postcode1, $postcode2);
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($data['status'] !== 'OK' || !isset($data['rows'][0]['elements'][0]['distance'])) {
            error_log('Distance API Response Error: ' . $body);
            return $this->calculate_distance_approximate($postcode1, $postcode2);
        }
        
        // Convert meters to miles
        $distance_meters = $data['rows'][0]['elements'][0]['distance']['value'];
        $distance_miles = $distance_meters * 0.000621371;
        
        return round($distance_miles, 1);
    }
    
    /**
     * Approximate distance calculation using postcode coordinates
     */
    private function calculate_distance_approximate($postcode1, $postcode2) {
        $coords1 = $this->get_postcode_coordinates($postcode1);
        $coords2 = $this->get_postcode_coordinates($postcode2);
        
        if (!$coords1 || !$coords2) {
            return false;
        }
        
        // Haversine formula
        $earth_radius = 3959; // Miles
        
        $lat1 = deg2rad($coords1['lat']);
        $lat2 = deg2rad($coords2['lat']);
        $lng1 = deg2rad($coords1['lng']);
        $lng2 = deg2rad($coords2['lng']);
        
        $dlat = $lat2 - $lat1;
        $dlng = $lng2 - $lng1;
        
        $a = sin($dlat / 2) * sin($dlat / 2) + 
             cos($lat1) * cos($lat2) * 
             sin($dlng / 2) * sin($dlng / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = $earth_radius * $c;
        
        // Add 20% for road distance vs straight line
        return round($distance * 1.2, 1);
    }
    
    /**
     * Get coordinates for a UK postcode
     */
    private function get_postcode_coordinates($postcode) {
        // Check cache
        $cache_key = 'postcode_coords_' . md5($postcode);
        $cached_coords = get_transient($cache_key);
        
        if ($cached_coords !== false) {
            return $cached_coords;
        }
        
        // Use Postcodes.io API (free UK postcode lookup)
        $url = 'https://api.postcodes.io/postcodes/' . urlencode($postcode);
        $response = wp_remote_get($url, array('timeout' => 5));
        
        if (is_wp_error($response)) {
            // Fallback to approximate coordinates based on outcode
            return $this->get_outcode_coordinates($postcode);
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($data['status'] !== 200 || !isset($data['result'])) {
            return $this->get_outcode_coordinates($postcode);
        }
        
        $coords = array(
            'lat' => $data['result']['latitude'],
            'lng' => $data['result']['longitude']
        );
        
        // Cache for 30 days
        set_transient($cache_key, $coords, $this->cache_duration);
        
        return $coords;
    }
    
    /**
     * Get approximate coordinates based on outcode
     */
    private function get_outcode_coordinates($postcode) {
        $outcode = $this->get_outcode($postcode);
        
        // Hardcoded major UK outcodes for fallback
        $outcode_coords = array(
            // Hertfordshire
            'AL' => array('lat' => 51.7520, 'lng' => -0.3360), // St Albans
            'HP' => array('lat' => 51.6294, 'lng' => -0.7480), // Hemel Hempstead
            'SG' => array('lat' => 51.9015, 'lng' => -0.2018), // Stevenage
            'WD' => array('lat' => 51.6565, 'lng' => -0.3903), // Watford
            'EN' => array('lat' => 51.6521, 'lng' => -0.0833), // Enfield
            
            // London
            'EC' => array('lat' => 51.5155, 'lng' => -0.0922), // City of London
            'WC' => array('lat' => 51.5246, 'lng' => -0.1340), // Central London
            'E' => array('lat' => 51.5423, 'lng' => -0.0023),  // East London
            'N' => array('lat' => 51.5646, 'lng' => -0.1063),  // North London
            'NW' => array('lat' => 51.5424, 'lng' => -0.1785), // Northwest London
            'SE' => array('lat' => 51.4828, 'lng' => -0.0059), // Southeast London
            'SW' => array('lat' => 51.4615, 'lng' => -0.1690), // Southwest London
            'W' => array('lat' => 51.5074, 'lng' => -0.2201),  // West London
            
            // Essex
            'CM' => array('lat' => 51.7343, 'lng' => 0.4691),  // Chelmsford
            'CO' => array('lat' => 51.8959, 'lng' => 0.8919),  // Colchester
            'IG' => array('lat' => 51.5591, 'lng' => 0.0742),  // Ilford
            'RM' => array('lat' => 51.5760, 'lng' => 0.1834),  // Romford
            'SS' => array('lat' => 51.5444, 'lng' => 0.7046),  // Southend
            
            // Cambridgeshire
            'CB' => array('lat' => 52.2053, 'lng' => 0.1218),  // Cambridge
            'PE' => array('lat' => 52.5695, 'lng' => -0.2405), // Peterborough
            
            // Suffolk
            'IP' => array('lat' => 52.0567, 'lng' => 1.1582),  // Ipswich
            'NR' => array('lat' => 52.6309, 'lng' => 1.2974),  // Norwich (Norfolk border)
            
            // Additional key areas
            'MK' => array('lat' => 52.0406, 'lng' => -0.7594), // Milton Keynes
            'LU' => array('lat' => 51.8787, 'lng' => -0.4200), // Luton
            'OX' => array('lat' => 51.7520, 'lng' => -1.2577), // Oxford
        );
        
        return isset($outcode_coords[$outcode]) ? $outcode_coords[$outcode] : false;
    }
    
    /**
     * Normalize UK postcode format
     */
    private function normalize_postcode($postcode) {
        // Remove all spaces and convert to uppercase
        $postcode = strtoupper(preg_replace('/\s+/', '', trim($postcode)));
        
        // Validate basic format
        if (!preg_match('/^[A-Z]{1,2}[0-9R][0-9A-Z]?[0-9][A-Z]{2}$/', $postcode)) {
            return false;
        }
        
        // Insert space before last 3 characters
        $postcode = substr($postcode, 0, -3) . ' ' . substr($postcode, -3);
        
        return $postcode;
    }
    
    /**
     * Get outcode from postcode
     */
    private function get_outcode($postcode) {
        $parts = explode(' ', $postcode);
        return $parts[0];
    }
    
    /**
     * Validate UK postcode
     */
    public function validate_postcode($postcode) {
        $normalized = $this->normalize_postcode($postcode);
        
        if (!$normalized) {
            return false;
        }
        
        // For more thorough validation, check with API
        $coords = $this->get_postcode_coordinates($normalized);
        
        return $coords !== false;
    }
    
    /**
     * Calculate travel costs based on distance
     */
    public function calculate_travel_cost($distance, $free_miles = 100, $rate_per_mile = 1.0) {
        if ($distance <= $free_miles) {
            return 0;
        }
        
        $billable_miles = $distance - $free_miles;
        $travel_cost = $billable_miles * $rate_per_mile * 2; // Return journey
        
        return round($travel_cost, 2);
    }
    
    /**
     * Check if accommodation is required
     */
    public function requires_accommodation($distance, $threshold = 250) {
        return $distance > $threshold;
    }
    
    /**
     * AJAX handler for distance calculation
     */
    public function ajax_calculate_distance() {
        check_ajax_referer('dj_hire_nonce', 'nonce');
        
        $postcode1 = sanitize_text_field($_POST['postcode1']);
        $postcode2 = sanitize_text_field($_POST['postcode2']);
        
        $distance = $this->calculate_distance($postcode1, $postcode2);
        
        if ($distance === false) {
            wp_send_json_error('Unable to calculate distance');
        }
        
        // Get DJ travel settings if provided
        $dj_id = intval($_POST['dj_id'] ?? 0);
        $travel_data = array('distance' => $distance);
        
        if ($dj_id) {
            $free_miles = get_post_meta($dj_id, 'dj_travel_free_miles', true) ?: 100;
            $rate_per_mile = get_post_meta($dj_id, 'dj_travel_rate', true) ?: 1.0;
            $accommodation_rate = get_post_meta($dj_id, 'dj_accommodation_rate', true) ?: 200;
            
            $travel_cost = $this->calculate_travel_cost($distance, $free_miles, $rate_per_mile);
            $accommodation_required = $this->requires_accommodation($distance);
            
            $travel_data['travel_cost'] = $travel_cost;
            $travel_data['accommodation_required'] = $accommodation_required;
            $travel_data['accommodation_cost'] = $accommodation_required ? $accommodation_rate : 0;
            $travel_data['total_travel_cost'] = $travel_cost + ($accommodation_required ? $accommodation_rate : 0);
        }
        
        wp_send_json_success($travel_data);
    }
    
    /**
     * AJAX handler for postcode validation
     */
    public function ajax_validate_postcode() {
        check_ajax_referer('dj_hire_nonce', 'nonce');
        
        $postcode = sanitize_text_field($_POST['postcode']);
        $valid = $this->validate_postcode($postcode);
        
        if ($valid) {
            wp_send_json_success(array(
                'valid' => true,
                'normalized' => $this->normalize_postcode($postcode)
            ));
        } else {
            wp_send_json_error('Invalid UK postcode');
        }
    }
    
    /**
     * Get travel time estimate
     */
    public function estimate_travel_time($distance) {
        // Rough estimate based on average UK driving speeds
        if ($distance < 20) {
            // Urban driving ~20mph
            return round($distance / 20 * 60); // minutes
        } elseif ($distance < 100) {
            // Mixed driving ~40mph
            return round($distance / 40 * 60);
        } else {
            // Mostly motorway ~60mph
            return round($distance / 60 * 60);
        }
    }
    
    /**
     * Get all DJs within range of a postcode
     */
    public function get_djs_in_range($postcode, $max_distance = 100) {
        $available_djs = array();
        
        $djs = get_posts(array(
            'post_type' => 'dj_profile',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        foreach ($djs as $dj) {
            $dj_postcode = get_post_meta($dj->ID, 'dj_base_postcode', true);
            
            if (empty($dj_postcode)) {
                continue;
            }
            
            $distance = $this->calculate_distance($dj_postcode, $postcode);
            
            if ($distance !== false && $distance <= $max_distance) {
                $available_djs[] = array(
                    'dj' => $dj,
                    'distance' => $distance,
                    'travel_time' => $this->estimate_travel_time($distance)
                );
            }
        }
        
        // Sort by distance
        usort($available_djs, function($a, $b) {
            return $a['distance'] - $b['distance'];
        });
        
        return $available_djs;
    }
    
    /**
     * Get coverage area polygon for a DJ
     */
    public function get_coverage_polygon($dj_id) {
        $base_postcode = get_post_meta($dj_id, 'dj_base_postcode', true);
        $coverage_areas = json_decode(get_post_meta($dj_id, 'dj_coverage_areas', true) ?: '[]', true);
        $travel_free_miles = get_post_meta($dj_id, 'dj_travel_free_miles', true) ?: 100;
        
        if (empty($base_postcode)) {
            return false;
        }
        
        $base_coords = $this->get_postcode_coordinates($base_postcode);
        if (!$base_coords) {
            return false;
        }
        
        // Generate polygon points for coverage area
        $polygon_points = array();
        $num_points = 16; // Create 16-sided polygon
        
        for ($i = 0; $i < $num_points; $i++) {
            $angle = ($i / $num_points) * 2 * pi();
            
            // Convert miles to degrees (rough approximation)
            $lat_offset = ($travel_free_miles / 69) * cos($angle);
            $lng_offset = ($travel_free_miles / 54.6) * sin($angle);
            
            $polygon_points[] = array(
                'lat' => $base_coords['lat'] + $lat_offset,
                'lng' => $base_coords['lng'] + $lng_offset
            );
        }
        
        return array(
            'center' => $base_coords,
            'radius' => $travel_free_miles,
            'polygon' => $polygon_points,
            'coverage_areas' => $coverage_areas
        );
    }
    
    /**
     * Batch calculate distances for optimization
     */
    public function batch_calculate_distances($origin_postcode, $destination_postcodes) {
        $results = array();
        
        foreach ($destination_postcodes as $destination) {
            $distance = $this->calculate_distance($origin_postcode, $destination);
            $results[$destination] = $distance;
        }
        
        return $results;
    }
    
    /**
     * Find optimal DJ based on location and availability
     */
    public function find_optimal_dj($venue_postcode, $event_date, $preferences = array()) {
        $candidates = array();
        
        // Get all available DJs
        $djs = get_posts(array(
            'post_type' => 'dj_profile',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => 'dj_base_postcode',
                    'compare' => 'EXISTS'
                )
            )
        ));
        
        $calendar_manager = new DJ_Calendar_Manager();
        
        foreach ($djs as $dj) {
            // Check availability
            if (!$calendar_manager->check_availability($dj->ID, $event_date)) {
                continue;
            }
            
            // Calculate distance and costs
            $dj_postcode = get_post_meta($dj->ID, 'dj_base_postcode', true);
            $distance = $this->calculate_distance($dj_postcode, $venue_postcode);
            
            if ($distance === false) {
                continue;
            }
            
            // Get DJ rates
            $hourly_rate = get_post_meta($dj->ID, 'dj_hourly_rate', true) ?: 0;
            $event_rate = get_post_meta($dj->ID, 'dj_event_rate', true) ?: 0;
            $travel_free_miles = get_post_meta($dj->ID, 'dj_travel_free_miles', true) ?: 100;
            $travel_rate = get_post_meta($dj->ID, 'dj_travel_rate', true) ?: 1.0;
            $accommodation_rate = get_post_meta($dj->ID, 'dj_accommodation_rate', true) ?: 200;
            
            // Calculate costs
            $base_rate = $event_rate ?: ($hourly_rate * 4); // Default 4 hours
            $travel_cost = $this->calculate_travel_cost($distance, $travel_free_miles, $travel_rate);
            $accommodation_cost = $this->requires_accommodation($distance) ? $accommodation_rate : 0;
            $total_cost = $base_rate + $travel_cost + $accommodation_cost;
            
            // Score based on preferences
            $score = $this->calculate_dj_score($dj->ID, $distance, $total_cost, $preferences);
            
            $candidates[] = array(
                'dj_id' => $dj->ID,
                'dj_name' => $dj->post_title,
                'distance' => $distance,
                'travel_time' => $this->estimate_travel_time($distance),
                'base_rate' => $base_rate,
                'travel_cost' => $travel_cost,
                'accommodation_cost' => $accommodation_cost,
                'total_cost' => $total_cost,
                'score' => $score
            );
        }
        
        // Sort by score (higher is better)
        usort($candidates, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        return $candidates;
    }
    
    /**
     * Calculate DJ score based on various factors
     */
    private function calculate_dj_score($dj_id, $distance, $total_cost, $preferences) {
        $score = 100;
        
        // Distance factor (closer is better)
        if ($distance <= 20) {
            $score += 20;
        } elseif ($distance <= 50) {
            $score += 10;
        } elseif ($distance > 100) {
            $score -= 10;
        }
        
        // Cost factor (lower is better, but not the only factor)
        if ($total_cost < 500) {
            $score += 15;
        } elseif ($total_cost > 1000) {
            $score -= 10;
        }
        
        // Experience factor
        $experience_years = get_post_meta($dj_id, 'dj_experience_years', true) ?: 0;
        $score += min($experience_years * 2, 20); // Max 20 points for experience
        
        // Specialization match
        if (!empty($preferences['event_type'])) {
            $specializations = json_decode(get_post_meta($dj_id, 'dj_specialisations', true) ?: '[]', true);
            if (in_array($preferences['event_type'], $specializations)) {
                $score += 25;
            }
        }
        
        // Music style match
        if (!empty($preferences['music_styles'])) {
            $dj_music_styles = json_decode(get_post_meta($dj_id, 'dj_music_styles', true) ?: '[]', true);
            $style_matches = array_intersect($preferences['music_styles'], $dj_music_styles);
            $score += count($style_matches) * 5;
        }
        
        // Reviews/ratings (placeholder for future implementation)
        // $average_rating = $this->get_dj_average_rating($dj_id);
        // $score += $average_rating * 10;
        
        return $score;
    }
    
    /**
     * Get region from postcode
     */
    public function get_postcode_region($postcode) {
        $outcode = $this->get_outcode($postcode);
        
        // Map outcodes to regions
        $region_map = array(
            // Hertfordshire
            'AL' => 'Hertfordshire',
            'HP' => 'Hertfordshire',
            'SG' => 'Hertfordshire',
            'WD' => 'Hertfordshire',
            'EN' => 'Hertfordshire',
            
            // London
            'EC' => 'London',
            'WC' => 'London',
            'E' => 'London',
            'N' => 'London',
            'NW' => 'London',
            'SE' => 'London',
            'SW' => 'London',
            'W' => 'London',
            'BR' => 'London',
            'CR' => 'London',
            'DA' => 'London',
            'HA' => 'London',
            'KT' => 'London',
            'SM' => 'London',
            'TW' => 'London',
            'UB' => 'London',
            
            // Essex
            'CM' => 'Essex',
            'CO' => 'Essex',
            'IG' => 'Essex',
            'RM' => 'Essex',
            'SS' => 'Essex',
            
            // Cambridgeshire
            'CB' => 'Cambridgeshire',
            'PE' => 'Cambridgeshire',
            
            // Suffolk
            'IP' => 'Suffolk',
            
            // Additional
            'MK' => 'Buckinghamshire',
            'LU' => 'Bedfordshire',
            'OX' => 'Oxfordshire',
        );
        
        return isset($region_map[$outcode]) ? $region_map[$outcode] : 'Other';
    }
    
    /**
     * Check if postcode is within service area
     */
    public function is_within_service_area($postcode) {
        $region = $this->get_postcode_region($postcode);
        $service_areas = array('Hertfordshire', 'London', 'Essex', 'Cambridgeshire', 'Suffolk');
        
        return in_array($region, $service_areas);
    }
    
    /**
     * Get nearest major city
     */
    public function get_nearest_city($postcode) {
        $coords = $this->get_postcode_coordinates($postcode);
        if (!$coords) {
            return 'Unknown';
        }
        
        // Major cities with coordinates
        $cities = array(
            'London' => array('lat' => 51.5074, 'lng' => -0.1278),
            'Cambridge' => array('lat' => 52.2053, 'lng' => 0.1218),
            'St Albans' => array('lat' => 51.7520, 'lng' => -0.3360),
            'Watford' => array('lat' => 51.6565, 'lng' => -0.3903),
            'Chelmsford' => array('lat' => 51.7343, 'lng' => 0.4691),
            'Colchester' => array('lat' => 51.8959, 'lng' => 0.8919),
            'Ipswich' => array('lat' => 52.0567, 'lng' => 1.1582),
            'Stevenage' => array('lat' => 51.9015, 'lng' => -0.2018),
            'Luton' => array('lat' => 51.8787, 'lng' => -0.4200),
            'Peterborough' => array('lat' => 52.5695, 'lng' => -0.2405)
        );
        
        $nearest_city = '';
        $min_distance = PHP_FLOAT_MAX;
        
        foreach ($cities as $city => $city_coords) {
            // Simple distance calculation
            $distance = sqrt(
                pow($coords['lat'] - $city_coords['lat'], 2) + 
                pow($coords['lng'] - $city_coords['lng'], 2)
            );
            
            if ($distance < $min_distance) {
                $min_distance = $distance;
                $nearest_city = $city;
            }
        }
        
        return $nearest_city;
    }
    
    /**
     * Format distance for display
     */
    public function format_distance($distance) {
        if ($distance < 1) {
            return 'Less than 1 mile';
        } elseif ($distance == 1) {
            return '1 mile';
        } else {
            return round($distance) . ' miles';
        }
    }
    
    /**
     * Format travel time for display
     */
    public function format_travel_time($minutes) {
        if ($minutes < 60) {
            return $minutes . ' minutes';
        } else {
            $hours = floor($minutes / 60);
            $mins = $minutes % 60;
            
            if ($mins == 0) {
                return $hours . ' hour' . ($hours > 1 ? 's' : '');
            } else {
                return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ' . $mins . ' minutes';
            }
        }
    }
    
    /**
     * Clear distance cache
     */
    public function clear_cache() {
        global $wpdb;
        
        // Delete all transients starting with 'distance_' or 'postcode_coords_'
        $wpdb->query("
            DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_distance_%' 
            OR option_name LIKE '_transient_postcode_coords_%'
            OR option_name LIKE '_transient_timeout_distance_%'
            OR option_name LIKE '_transient_timeout_postcode_coords_%'
        ");
        
        return true;
    }
    
    /**
     * Get cache statistics
     */
    public function get_cache_stats() {
        global $wpdb;
        
        $distance_cache_count = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_distance_%'
        ");
        
        $postcode_cache_count = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_postcode_coords_%'
        ");
        
        return array(
            'distance_calculations' => $distance_cache_count,
            'postcode_lookups' => $postcode_cache_count,
            'total_cached' => $distance_cache_count + $postcode_cache_count
        );
    }
}