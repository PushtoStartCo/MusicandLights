<?php
/**
 * Travel cost calculator class for Music & Lights plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class ML_Travel {
    
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
        add_action('wp_ajax_ml_calculate_travel_cost', array($this, 'ajax_calculate_cost'));
        add_action('wp_ajax_nopriv_ml_calculate_travel_cost', array($this, 'ajax_calculate_cost'));
        add_action('wp_ajax_ml_validate_postcode', array($this, 'ajax_validate_postcode'));
        add_action('wp_ajax_nopriv_ml_validate_postcode', array($this, 'ajax_validate_postcode'));
    }
    
    /**
     * Calculate travel cost between two postcodes
     */
    public function calculate_cost($from_postcode, $to_postcode, $rate_per_mile = null) {
        if (!$rate_per_mile) {
            $rate_per_mile = get_option('ml_default_travel_rate', 0.45);
        }
        
        // Get distance between postcodes
        $distance_data = $this->get_distance($from_postcode, $to_postcode);
        
        if (is_wp_error($distance_data)) {
            return $distance_data;
        }
        
        // Calculate cost
        $distance_miles = $distance_data['distance_miles'];
        $travel_cost = $distance_miles * $rate_per_mile;
        
        // Apply minimum charge if set
        $minimum_charge = get_option('ml_minimum_travel_charge', 0);
        if ($minimum_charge > 0 && $travel_cost < $minimum_charge) {
            $travel_cost = $minimum_charge;
        }
        
        // Apply maximum radius restriction if set
        $max_travel_radius = get_option('ml_max_travel_radius', 0);
        if ($max_travel_radius > 0 && $distance_miles > $max_travel_radius) {
            return new WP_Error('distance_exceeded', 'Distance exceeds maximum travel radius');
        }
        
        return array(
            'distance_miles' => $distance_miles,
            'drive_time_minutes' => $distance_data['drive_time_minutes'],
            'travel_cost' => round($travel_cost, 2),
            'rate_per_mile' => $rate_per_mile
        );
    }
    
    /**
     * Get distance between two postcodes
     */
    public function get_distance($from_postcode, $to_postcode) {
        // Check cache first
        $cached_result = $this->get_cached_distance($from_postcode, $to_postcode);
        if ($cached_result) {
            return $cached_result;
        }
        
        // Try multiple APIs for distance calculation
        $apis_to_try = array(
            'google_maps',
            'postcodes_io',
            'uk_postcode_data'
        );
        
        $distance_data = null;
        
        foreach ($apis_to_try as $api) {
            $distance_data = $this->call_distance_api($api, $from_postcode, $to_postcode);
            
            if (!is_wp_error($distance_data)) {
                break;
            }
        }
        
        if (is_wp_error($distance_data)) {
            return $distance_data;
        }
        
        // Cache the result
        $this->cache_distance($from_postcode, $to_postcode, $distance_data);
        
        return $distance_data;
    }
    
    /**
     * Call distance API
     */
    private function call_distance_api($api, $from_postcode, $to_postcode) {
        switch ($api) {
            case 'google_maps':
                return $this->google_maps_distance($from_postcode, $to_postcode);
            case 'postcodes_io':
                return $this->postcodes_io_distance($from_postcode, $to_postcode);
            case 'uk_postcode_data':
                return $this->uk_postcode_distance($from_postcode, $to_postcode);
            default:
                return new WP_Error('invalid_api', 'Invalid distance API specified');
        }
    }
    
    /**
     * Google Maps Distance Matrix API
     */
    private function google_maps_distance($from_postcode, $to_postcode) {
        $api_key = get_option('ml_google_maps_api_key');
        
        if (!$api_key) {
            return new WP_Error('no_api_key', 'Google Maps API key not configured');
        }
        
        $url = 'https://maps.googleapis.com/maps/api/distancematrix/json';
        $params = array(
            'origins' => $from_postcode . ', UK',
            'destinations' => $to_postcode . ', UK',
            'units' => 'imperial',
            'mode' => 'driving',
            'key' => $api_key
        );
        
        $url .= '?' . http_build_query($params);
        
        $response = wp_remote_get($url, array('timeout' => 30));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || $data['status'] !== 'OK') {
            return new WP_Error('api_error', 'Google Maps API error: ' . ($data['error_message'] ?? 'Unknown error'));
        }
        
        $element = $data['rows'][0]['elements'][0];
        
        if ($element['status'] !== 'OK') {
            return new WP_Error('route_error', 'No route found between postcodes');
        }
        
        $distance_meters = $element['distance']['value'];
        $duration_seconds = $element['duration']['value'];
        
        return array(
            'distance_miles' => round($distance_meters * 0.000621371, 2),
            'drive_time_minutes' => round($duration_seconds / 60),
            'api_response' => $data
        );
    }
    
    /**
     * Postcodes.io API (free UK postcode data)
     */
    private function postcodes_io_distance($from_postcode, $to_postcode) {
        // Get coordinates for both postcodes
        $from_coords = $this->get_postcode_coordinates($from_postcode);
        $to_coords = $this->get_postcode_coordinates($to_postcode);
        
        if (is_wp_error($from_coords) || is_wp_error($to_coords)) {
            return new WP_Error('postcode_lookup_failed', 'Failed to lookup postcode coordinates');
        }
        
        // Calculate straight-line distance
        $distance_miles = $this->calculate_haversine_distance(
            $from_coords['latitude'], $from_coords['longitude'],
            $to_coords['latitude'], $to_coords['longitude']
        );
        
        // Estimate driving distance (typically 1.3x straight-line distance)
        $driving_distance = $distance_miles * 1.3;
        
        // Estimate driving time (assuming 30 mph average)
        $drive_time_minutes = round($driving_distance * 2);
        
        return array(
            'distance_miles' => round($driving_distance, 2),
            'drive_time_minutes' => $drive_time_minutes,
            'api_response' => array(
                'from_coords' => $from_coords,
                'to_coords' => $to_coords,
                'straight_line_distance' => $distance_miles
            )
        );
    }
    
    /**
     * Get postcode coordinates from postcodes.io
     */
    private function get_postcode_coordinates($postcode) {
        $url = 'https://api.postcodes.io/postcodes/' . urlencode(str_replace(' ', '', $postcode));
        
        $response = wp_remote_get($url, array('timeout' => 30));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || $data['status'] !== 200) {
            return new WP_Error('postcode_not_found', 'Postcode not found: ' . $postcode);
        }
        
        return array(
            'latitude' => $data['result']['latitude'],
            'longitude' => $data['result']['longitude'],
            'country' => $data['result']['country'],
            'region' => $data['result']['region']
        );
    }
    
    /**
     * UK Postcode data fallback (using stored data)
     */
    private function uk_postcode_distance($from_postcode, $to_postcode) {
        // This would use a local database of UK postcodes
        // For now, return an error suggesting configuration
        return new WP_Error('not_implemented', 'UK postcode database not configured');
    }
    
    /**
     * Calculate Haversine distance between two coordinates
     */
    private function calculate_haversine_distance($lat1, $lon1, $lat2, $lon2) {
        $earth_radius = 3959; // Earth's radius in miles
        
        $lat1_rad = deg2rad($lat1);
        $lon1_rad = deg2rad($lon1);
        $lat2_rad = deg2rad($lat2);
        $lon2_rad = deg2rad($lon2);
        
        $delta_lat = $lat2_rad - $lat1_rad;
        $delta_lon = $lon2_rad - $lon1_rad;
        
        $a = sin($delta_lat / 2) * sin($delta_lat / 2) +
             cos($lat1_rad) * cos($lat2_rad) *
             sin($delta_lon / 2) * sin($delta_lon / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earth_radius * $c;
    }
    
    /**
     * Get cached distance
     */
    private function get_cached_distance($from_postcode, $to_postcode) {
        global $wpdb;
        
        $table_name = ML_Database::get_table_name('travel_cache');
        
        // Normalize postcodes
        $from_postcode = strtoupper(str_replace(' ', '', $from_postcode));
        $to_postcode = strtoupper(str_replace(' ', '', $to_postcode));
        
        $cached = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE (from_postcode = %s AND to_postcode = %s) 
             OR (from_postcode = %s AND to_postcode = %s)
             AND expires_at > %s",
            $from_postcode, $to_postcode,
            $to_postcode, $from_postcode,
            current_time('mysql')
        ));
        
        if ($cached) {
            return array(
                'distance_miles' => $cached->distance_miles,
                'drive_time_minutes' => $cached->drive_time_minutes,
                'api_response' => json_decode($cached->api_response, true)
            );
        }
        
        return null;
    }
    
    /**
     * Cache distance calculation
     */
    private function cache_distance($from_postcode, $to_postcode, $distance_data) {
        global $wpdb;
        
        $table_name = ML_Database::get_table_name('travel_cache');
        
        // Normalize postcodes
        $from_postcode = strtoupper(str_replace(' ', '', $from_postcode));
        $to_postcode = strtoupper(str_replace(' ', '', $to_postcode));
        
        // Cache for 30 days
        $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        $cache_data = array(
            'from_postcode' => $from_postcode,
            'to_postcode' => $to_postcode,
            'distance_miles' => $distance_data['distance_miles'],
            'drive_time_minutes' => $distance_data['drive_time_minutes'],
            'api_response' => json_encode($distance_data['api_response'] ?? array()),
            'expires_at' => $expires_at
        );
        
        $wpdb->insert($table_name, $cache_data);
    }
    
    /**
     * Validate UK postcode format
     */
    public function validate_postcode($postcode) {
        // Remove spaces and convert to uppercase
        $postcode = strtoupper(str_replace(' ', '', $postcode));
        
        // UK postcode regex pattern
        $pattern = '/^[A-Z]{1,2}[0-9R][0-9A-Z]?[0-9][ABD-HJLNP-UW-Z]{2}$/';
        
        if (!preg_match($pattern, $postcode)) {
            return new WP_Error('invalid_format', 'Invalid UK postcode format');
        }
        
        // Check if postcode exists (using postcodes.io)
        $coords = $this->get_postcode_coordinates($postcode);
        
        if (is_wp_error($coords)) {
            return new WP_Error('postcode_not_found', 'Postcode not found in database');
        }
        
        return array(
            'postcode' => $postcode,
            'formatted' => $this->format_postcode($postcode),
            'coordinates' => $coords,
            'valid' => true
        );
    }
    
    /**
     * Format postcode with proper spacing
     */
    public function format_postcode($postcode) {
        $postcode = strtoupper(str_replace(' ', '', $postcode));
        
        // Add space before last 3 characters
        if (strlen($postcode) >= 5) {
            return substr($postcode, 0, -3) . ' ' . substr($postcode, -3);
        }
        
        return $postcode;
    }
    
    /**
     * Get travel zones and rates
     */
    public function get_travel_zones() {
        $zones = array(
            'local' => array(
                'name' => 'Local Area (0-25 miles)',
                'max_distance' => 25,
                'rate_multiplier' => 1.0,
                'description' => 'Standard travel rate within local area'
            ),
            'regional' => array(
                'name' => 'Regional (26-50 miles)',
                'max_distance' => 50,
                'rate_multiplier' => 1.2,
                'description' => 'Increased rate for regional travel'
            ),
            'extended' => array(
                'name' => 'Extended (51-100 miles)',
                'max_distance' => 100,
                'rate_multiplier' => 1.5,
                'description' => 'Extended travel with higher rates'
            ),
            'national' => array(
                'name' => 'National (100+ miles)',
                'max_distance' => 999,
                'rate_multiplier' => 2.0,
                'description' => 'National travel requiring custom quotes'
            )
        );
        
        return apply_filters('ml_travel_zones', $zones);
    }
    
    /**
     * Get travel zone for distance
     */
    public function get_travel_zone($distance_miles) {
        $zones = $this->get_travel_zones();
        
        foreach ($zones as $zone_key => $zone) {
            if ($distance_miles <= $zone['max_distance']) {
                return array_merge($zone, array('zone_key' => $zone_key));
            }
        }
        
        return $zones['national'];
    }
    
    /**
     * Calculate travel cost with zone-based pricing
     */
    public function calculate_zone_based_cost($from_postcode, $to_postcode, $base_rate = null) {
        if (!$base_rate) {
            $base_rate = get_option('ml_default_travel_rate', 0.45);
        }
        
        $distance_data = $this->get_distance($from_postcode, $to_postcode);
        
        if (is_wp_error($distance_data)) {
            return $distance_data;
        }
        
        $distance_miles = $distance_data['distance_miles'];
        $travel_zone = $this->get_travel_zone($distance_miles);
        
        $adjusted_rate = $base_rate * $travel_zone['rate_multiplier'];
        $travel_cost = $distance_miles * $adjusted_rate;
        
        // Apply zone-specific minimum charges
        $minimum_charges = get_option('ml_zone_minimum_charges', array());
        $zone_minimum = $minimum_charges[$travel_zone['zone_key']] ?? 0;
        
        if ($zone_minimum > 0 && $travel_cost < $zone_minimum) {
            $travel_cost = $zone_minimum;
        }
        
        return array(
            'distance_miles' => $distance_miles,
            'drive_time_minutes' => $distance_data['drive_time_minutes'],
            'travel_zone' => $travel_zone,
            'base_rate' => $base_rate,
            'adjusted_rate' => $adjusted_rate,
            'travel_cost' => round($travel_cost, 2)
        );
    }
    
    /**
     * Get coverage areas for DJ
     */
    public function get_dj_coverage_areas($dj_id) {
        global $wpdb;
        
        $dj_table = ML_Database::get_table_name('djs');
        $dj = $wpdb->get_row($wpdb->prepare(
            "SELECT coverage_areas, travel_radius FROM $dj_table WHERE id = %d",
            $dj_id
        ));
        
        if (!$dj) {
            return new WP_Error('dj_not_found', 'DJ not found');
        }
        
        $coverage_areas = array();
        
        if ($dj->coverage_areas) {
            $areas = json_decode($dj->coverage_areas, true);
            if (is_array($areas)) {
                $coverage_areas = $areas;
            } else {
                // Legacy format - split by comma
                $coverage_areas = array_map('trim', explode(',', $dj->coverage_areas));
            }
        }
        
        return array(
            'coverage_areas' => $coverage_areas,
            'travel_radius' => $dj->travel_radius ?: 50
        );
    }
    
    /**
     * Check if postcode is within DJ coverage
     */
    public function is_postcode_in_coverage($dj_id, $postcode) {
        $coverage = $this->get_dj_coverage_areas($dj_id);
        
        if (is_wp_error($coverage)) {
            return $coverage;
        }
        
        // If specific coverage areas are defined, check against them
        if (!empty($coverage['coverage_areas'])) {
            foreach ($coverage['coverage_areas'] as $area) {
                if ($this->postcode_matches_area($postcode, $area)) {
                    return true;
                }
            }
        }
        
        // Fallback to radius-based coverage
        // Would need DJ's base postcode for this calculation
        // For now, return true if within reasonable UK bounds
        return $this->is_uk_postcode($postcode);
    }
    
    /**
     * Check if postcode matches coverage area
     */
    private function postcode_matches_area($postcode, $area) {
        $postcode = strtoupper(str_replace(' ', '', $postcode));
        $area = strtoupper(str_replace(' ', '', $area));
        
        // Exact match
        if ($postcode === $area) {
            return true;
        }
        
        // Area code match (e.g., "AL" matches "AL1 2AB")
        if (strlen($area) <= 4 && strpos($postcode, $area) === 0) {
            return true;
        }
        
        // District match (e.g., "AL1" matches "AL1 2AB")
        if (preg_match('/^[A-Z]{1,2}[0-9R][0-9A-Z]?$/', $area)) {
            return strpos($postcode, $area) === 0;
        }
        
        return false;
    }
    
    /**
     * Check if postcode is a UK postcode
     */
    private function is_uk_postcode($postcode) {
        $coords = $this->get_postcode_coordinates($postcode);
        return !is_wp_error($coords) && isset($coords['country']) && $coords['country'] === 'England';
    }
    
    /**
     * Clean expired cache entries
     */
    public function clean_cache() {
        global $wpdb;
        
        $table_name = ML_Database::get_table_name('travel_cache');
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE expires_at < %s",
            current_time('mysql')
        ));
        
        return $deleted;
    }
    
    /**
     * Get travel statistics
     */
    public function get_travel_stats($date_from = null, $date_to = null) {
        global $wpdb;
        
        $booking_table = ML_Database::get_table_name('bookings');
        
        $where_clause = "WHERE travel_cost > 0";
        $params = array();
        
        if ($date_from) {
            $where_clause .= " AND event_date >= %s";
            $params[] = $date_from;
        }
        
        if ($date_to) {
            $where_clause .= " AND event_date <= %s";
            $params[] = $date_to;
        }
        
        $stats = array();
        
        // Total travel revenue
        $query = "SELECT SUM(travel_cost) FROM $booking_table $where_clause";
        $stats['total_travel_revenue'] = $wpdb->get_var($wpdb->prepare($query, $params)) ?: 0;
        
        // Average travel cost
        $query = "SELECT AVG(travel_cost) FROM $booking_table $where_clause";
        $stats['average_travel_cost'] = $wpdb->get_var($wpdb->prepare($query, $params)) ?: 0;
        
        // Bookings with travel
        $query = "SELECT COUNT(*) FROM $booking_table $where_clause";
        $stats['bookings_with_travel'] = $wpdb->get_var($wpdb->prepare($query, $params)) ?: 0;
        
        // Top travel destinations
        $query = "SELECT venue_postcode, COUNT(*) as booking_count, AVG(travel_cost) as avg_cost 
                  FROM $booking_table $where_clause 
                  GROUP BY venue_postcode 
                  ORDER BY booking_count DESC 
                  LIMIT 10";
        $stats['top_destinations'] = $wpdb->get_results($wpdb->prepare($query, $params));
        
        return $stats;
    }
    
    /**
     * AJAX: Calculate travel cost
     */
    public function ajax_calculate_cost() {
        check_ajax_referer('ml_nonce', 'nonce');
        
        $from_postcode = sanitize_text_field($_POST['from_postcode']);
        $to_postcode = sanitize_text_field($_POST['to_postcode']);
        $rate_per_mile = floatval($_POST['rate_per_mile'] ?? 0);
        $use_zones = isset($_POST['use_zones']) && $_POST['use_zones'] === 'true';
        
        if ($use_zones) {
            $result = $this->calculate_zone_based_cost($from_postcode, $to_postcode, $rate_per_mile ?: null);
        } else {
            $result = $this->calculate_cost($from_postcode, $to_postcode, $rate_per_mile ?: null);
        }
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }
    
    /**
     * AJAX: Validate postcode
     */
    public function ajax_validate_postcode() {
        check_ajax_referer('ml_nonce', 'nonce');
        
        $postcode = sanitize_text_field($_POST['postcode']);
        $result = $this->validate_postcode($postcode);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success($result);
        }
    }
}

/**
 * Postcode utility class
 */
class ML_Postcode {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Constructor
    }
    
    /**
     * Get postcode data from various sources
     */
    public function get_postcode_data($postcode) {
        $travel_calculator = ML_Travel::get_instance();
        return $travel_calculator->get_postcode_coordinates($postcode);
    }
    
    /**
     * Get nearby postcodes
     */
    public function get_nearby_postcodes($postcode, $radius_miles = 10) {
        // This would require a comprehensive UK postcode database
        // For now, return empty array
        return array();
    }
    
    /**
     * Get postcode area information
     */
    public function get_area_info($postcode) {
        $coords = $this->get_postcode_data($postcode);
        
        if (is_wp_error($coords)) {
            return $coords;
        }
        
        return array(
            'postcode' => $postcode,
            'area_code' => $this->extract_area_code($postcode),
            'district_code' => $this->extract_district_code($postcode),
            'coordinates' => $coords,
            'region' => $coords['region'] ?? '',
            'country' => $coords['country'] ?? ''
        );
    }
    
    /**
     * Extract area code from postcode (e.g., "AL" from "AL1 2AB")
     */
    private function extract_area_code($postcode) {
        $postcode = strtoupper(str_replace(' ', '', $postcode));
        
        if (preg_match('/^([A-Z]{1,2})/', $postcode, $matches)) {
            return $matches[1];
        }
        
        return '';
    }
    
    /**
     * Extract district code from postcode (e.g., "AL1" from "AL1 2AB")
     */
    private function extract_district_code($postcode) {
        $postcode = strtoupper(str_replace(' ', '', $postcode));
        
        if (preg_match('/^([A-Z]{1,2}[0-9R][0-9A-Z]?)/', $postcode, $matches)) {
            return $matches[1];
        }
        
        return '';
    }
    
    /**
     * Get coverage areas within radius of base postcode
     */
    public function get_coverage_areas_in_radius($base_postcode, $radius_miles) {
        // This would require a comprehensive postcode database
        // For now, return some common area codes for the region
        
        $area_code = $this->extract_area_code($base_postcode);
        
        // Common area codes by region (simplified)
        $regional_areas = array(
            'AL' => array('AL', 'SG', 'HP', 'WD', 'EN'), // Hertfordshire
            'E' => array('E', 'EC', 'WC', 'N', 'NW', 'SW', 'SE', 'W'), // London
            'CM' => array('CM', 'SS', 'CO', 'IP'), // Essex
            'CB' => array('CB', 'PE', 'SG'), // Cambridgeshire
            'IP' => array('IP', 'CO', 'NR'), // Suffolk
        );
        
        return $regional_areas[$area_code] ?? array($area_code);
    }
}
?>