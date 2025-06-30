<?php
/**
 * Database management class for Music & Lights plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class ML_Database {
    
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
     * Create all necessary database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // DJ Profiles table
        $table_djs = $wpdb->prefix . 'ml_djs';
        $sql_djs = "CREATE TABLE $table_djs (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            stage_name varchar(255) NOT NULL,
            real_name varchar(255) NOT NULL,
            email varchar(255) NOT NULL,
            phone varchar(20) NOT NULL,
            bio text,
            profile_image varchar(255),
            experience_years int(3),
            specialties text,
            equipment_owned text,
            travel_radius int(5) DEFAULT 50,
            travel_rate decimal(5,2) DEFAULT 0.45,
            base_rate decimal(8,2) NOT NULL,
            hourly_rate decimal(8,2) NOT NULL,
            coverage_areas text,
            available_dates text,
            unavailable_dates text,
            commission_rate decimal(5,2) DEFAULT 25.00,
            status enum('active','inactive','suspended') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id),
            KEY email (email),
            KEY status (status)
        ) $charset_collate;";
        
        // DJ Packages table
        $table_packages = $wpdb->prefix . 'ml_dj_packages';
        $sql_packages = "CREATE TABLE $table_packages (
            id int(11) NOT NULL AUTO_INCREMENT,
            dj_id int(11) NOT NULL,
            package_name varchar(255) NOT NULL,
            description text,
            duration_hours int(3) NOT NULL,
            price decimal(8,2) NOT NULL,
            equipment_included text,
            extras_available text,
            is_active tinyint(1) DEFAULT 1,
            sort_order int(3) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY dj_id (dj_id),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        // Bookings table
        $table_bookings = $wpdb->prefix . 'ml_bookings';
        $sql_bookings = "CREATE TABLE $table_bookings (
            id int(11) NOT NULL AUTO_INCREMENT,
            booking_reference varchar(20) NOT NULL,
            client_name varchar(255) NOT NULL,
            client_email varchar(255) NOT NULL,
            client_phone varchar(20) NOT NULL,
            event_type varchar(100) NOT NULL,
            event_date date NOT NULL,
            event_start_time time NOT NULL,
            event_end_time time NOT NULL,
            event_duration int(3) NOT NULL,
            venue_name varchar(255) NOT NULL,
            venue_address text NOT NULL,
            venue_postcode varchar(10) NOT NULL,
            dj_id int(11) NOT NULL,
            package_id int(11),
            base_price decimal(8,2) NOT NULL,
            travel_cost decimal(8,2) DEFAULT 0.00,
            extras_cost decimal(8,2) DEFAULT 0.00,
            total_price decimal(8,2) NOT NULL,
            agency_commission decimal(8,2) NOT NULL,
            dj_payout decimal(8,2) NOT NULL,
            deposit_amount decimal(8,2) NOT NULL,
            deposit_paid tinyint(1) DEFAULT 0,
            deposit_paid_date datetime NULL,
            final_payment_amount decimal(8,2) NOT NULL,
            final_payment_paid tinyint(1) DEFAULT 0,
            final_payment_paid_date datetime NULL,
            special_requests text,
            equipment_requests text,
            music_preferences text,
            status enum('pending','confirmed','completed','cancelled','refunded') DEFAULT 'pending',
            payment_status enum('pending','deposit_paid','fully_paid','refunded') DEFAULT 'pending',
            stripe_payment_intent_id varchar(255),
            ghl_contact_id varchar(255),
            ghl_opportunity_id varchar(255),
            safeguard_score int(3) DEFAULT 0,
            safeguard_flags text,
            admin_notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY booking_reference (booking_reference),
            KEY dj_id (dj_id),
            KEY event_date (event_date),
            KEY status (status),
            KEY payment_status (payment_status),
            KEY client_email (client_email)
        ) $charset_collate;";
        
        // Commission Tracking table
        $table_commissions = $wpdb->prefix . 'ml_commissions';
        $sql_commissions = "CREATE TABLE $table_commissions (
            id int(11) NOT NULL AUTO_INCREMENT,
            booking_id int(11) NOT NULL,
            dj_id int(11) NOT NULL,
            commission_amount decimal(8,2) NOT NULL,
            commission_rate decimal(5,2) NOT NULL,
            booking_total decimal(8,2) NOT NULL,
            status enum('pending','paid','disputed') DEFAULT 'pending',
            paid_date datetime NULL,
            payment_method varchar(50),
            payment_reference varchar(255),
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY booking_id (booking_id),
            KEY dj_id (dj_id),
            KEY status (status)
        ) $charset_collate;";
        
        // Equipment table
        $table_equipment = $wpdb->prefix . 'ml_equipment';
        $sql_equipment = "CREATE TABLE $table_equipment (
            id int(11) NOT NULL AUTO_INCREMENT,
            equipment_name varchar(255) NOT NULL,
            equipment_type varchar(100) NOT NULL,
            brand varchar(100),
            model varchar(100),
            serial_number varchar(100),
            purchase_date date,
            purchase_price decimal(8,2),
            current_value decimal(8,2),
            condition_status enum('excellent','good','fair','poor') DEFAULT 'good',
            location varchar(255),
            assigned_to_dj int(11) NULL,
            maintenance_schedule text,
            last_maintenance date,
            insurance_covered tinyint(1) DEFAULT 0,
            notes text,
            status enum('available','in_use','maintenance','retired') DEFAULT 'available',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY equipment_type (equipment_type),
            KEY assigned_to_dj (assigned_to_dj),
            KEY status (status)
        ) $charset_collate;";
        
        // Booking Equipment table (many-to-many)
        $table_booking_equipment = $wpdb->prefix . 'ml_booking_equipment';
        $sql_booking_equipment = "CREATE TABLE $table_booking_equipment (
            id int(11) NOT NULL AUTO_INCREMENT,
            booking_id int(11) NOT NULL,
            equipment_id int(11) NOT NULL,
            quantity int(3) DEFAULT 1,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY booking_id (booking_id),
            KEY equipment_id (equipment_id)
        ) $charset_collate;";
        
        // Safeguards Log table
        $table_safeguards = $wpdb->prefix . 'ml_safeguards_log';
        $sql_safeguards = "CREATE TABLE $table_safeguards (
            id int(11) NOT NULL AUTO_INCREMENT,
            dj_id int(11) NOT NULL,
            client_email varchar(255),
            client_phone varchar(20),
            event_type varchar(100),
            risk_level enum('low','medium','high','critical') DEFAULT 'low',
            risk_factors text,
            action_taken varchar(255),
            admin_notified tinyint(1) DEFAULT 0,
            status enum('open','resolved','dismissed') DEFAULT 'open',
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY dj_id (dj_id),
            KEY risk_level (risk_level),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Travel Costs Cache table
        $table_travel_cache = $wpdb->prefix . 'ml_travel_cache';
        $sql_travel_cache = "CREATE TABLE $table_travel_cache (
            id int(11) NOT NULL AUTO_INCREMENT,
            from_postcode varchar(10) NOT NULL,
            to_postcode varchar(10) NOT NULL,
            distance_miles decimal(6,2) NOT NULL,
            drive_time_minutes int(5) NOT NULL,
            api_response text,
            expires_at datetime NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY postcode_pair (from_postcode, to_postcode),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        
        // Email Log table
        $table_email_log = $wpdb->prefix . 'ml_email_log';
        $sql_email_log = "CREATE TABLE $table_email_log (
            id int(11) NOT NULL AUTO_INCREMENT,
            booking_id int(11) NULL,
            dj_id int(11) NULL,
            recipient_email varchar(255) NOT NULL,
            email_type varchar(50) NOT NULL,
            subject varchar(255) NOT NULL,
            template_used varchar(100),
            status enum('sent','failed','bounced') DEFAULT 'sent',
            error_message text,
            sent_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY booking_id (booking_id),
            KEY dj_id (dj_id),
            KEY email_type (email_type),
            KEY status (status),
            KEY sent_at (sent_at)
        ) $charset_collate;";
        
        // GoHighLevel Sync Log
        $table_ghl_sync = $wpdb->prefix . 'ml_ghl_sync_log';
        $sql_ghl_sync = "CREATE TABLE $table_ghl_sync (
            id int(11) NOT NULL AUTO_INCREMENT,
            booking_id int(11) NOT NULL,
            action_type varchar(50) NOT NULL,
            ghl_contact_id varchar(255),
            ghl_opportunity_id varchar(255),
            ghl_response text,
            status enum('success','failed','pending') DEFAULT 'pending',
            error_message text,
            retry_count int(2) DEFAULT 0,
            last_retry_at datetime NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY booking_id (booking_id),
            KEY status (status),
            KEY action_type (action_type)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Execute table creation
        dbDelta($sql_djs);
        dbDelta($sql_packages);
        dbDelta($sql_bookings);
        dbDelta($sql_commissions);
        dbDelta($sql_equipment);
        dbDelta($sql_booking_equipment);
        dbDelta($sql_safeguards);
        dbDelta($sql_travel_cache);
        dbDelta($sql_email_log);
        dbDelta($sql_ghl_sync);
        
        // Add foreign key constraints (if needed)
        self::add_foreign_keys();
        
        // Insert default data
        self::insert_default_data();
    }
    
    /**
     * Add foreign key constraints
     */
    private static function add_foreign_keys() {
        global $wpdb;
        
        // Note: WordPress typically doesn't use foreign keys due to portability concerns
        // But we can add them for data integrity if the hosting environment supports it
        
        $constraints = array(
            "ALTER TABLE {$wpdb->prefix}ml_dj_packages ADD CONSTRAINT fk_package_dj 
             FOREIGN KEY (dj_id) REFERENCES {$wpdb->prefix}ml_djs(id) ON DELETE CASCADE",
            
            "ALTER TABLE {$wpdb->prefix}ml_bookings ADD CONSTRAINT fk_booking_dj 
             FOREIGN KEY (dj_id) REFERENCES {$wpdb->prefix}ml_djs(id) ON DELETE RESTRICT",
            
            "ALTER TABLE {$wpdb->prefix}ml_commissions ADD CONSTRAINT fk_commission_booking 
             FOREIGN KEY (booking_id) REFERENCES {$wpdb->prefix}ml_bookings(id) ON DELETE CASCADE",
            
            "ALTER TABLE {$wpdb->prefix}ml_booking_equipment ADD CONSTRAINT fk_be_booking 
             FOREIGN KEY (booking_id) REFERENCES {$wpdb->prefix}ml_bookings(id) ON DELETE CASCADE",
            
            "ALTER TABLE {$wpdb->prefix}ml_booking_equipment ADD CONSTRAINT fk_be_equipment 
             FOREIGN KEY (equipment_id) REFERENCES {$wpdb->prefix}ml_equipment(id) ON DELETE CASCADE"
        );
        
        foreach ($constraints as $constraint) {
            try {
                $wpdb->query($constraint);
            } catch (Exception $e) {
                // Silently fail if foreign keys aren't supported
                error_log('ML Plugin: Foreign key constraint failed: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Insert default data
     */
    private static function insert_default_data() {
        global $wpdb;
        
        // Insert default equipment types
        $default_equipment = array(
            array(
                'equipment_name' => 'Pioneer CDJ-2000NXS2',
                'equipment_type' => 'CDJ Player',
                'brand' => 'Pioneer',
                'model' => 'CDJ-2000NXS2',
                'status' => 'available'
            ),
            array(
                'equipment_name' => 'Pioneer DJM-900NXS2',
                'equipment_type' => 'Mixer',
                'brand' => 'Pioneer',
                'model' => 'DJM-900NXS2',
                'status' => 'available'
            ),
            array(
                'equipment_name' => 'QSC K12.2 Speaker',
                'equipment_type' => 'Speaker',
                'brand' => 'QSC',
                'model' => 'K12.2',
                'status' => 'available'
            ),
            array(
                'equipment_name' => 'Shure SM58 Microphone',
                'equipment_type' => 'Microphone',
                'brand' => 'Shure',
                'model' => 'SM58',
                'status' => 'available'
            ),
            array(
                'equipment_name' => 'American DJ Lighting Set',
                'equipment_type' => 'Lighting',
                'brand' => 'American DJ',
                'model' => 'Basic Package',
                'status' => 'available'
            )
        );
        
        $equipment_table = $wpdb->prefix . 'ml_equipment';
        foreach ($default_equipment as $equipment) {
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM $equipment_table WHERE equipment_name = %s",
                $equipment['equipment_name']
            ));
            
            if (!$existing) {
                $wpdb->insert($equipment_table, $equipment);
            }
        }
    }
    
    /**
     * Get table name with prefix
     */
    public static function get_table_name($table) {
        global $wpdb;
        return $wpdb->prefix . 'ml_' . $table;
    }
    
    /**
     * Drop all plugin tables (for uninstall)
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            'ml_ghl_sync_log',
            'ml_email_log',
            'ml_travel_cache',
            'ml_safeguards_log',
            'ml_booking_equipment',
            'ml_equipment',
            'ml_commissions',
            'ml_bookings',
            'ml_dj_packages',
            'ml_djs'
        );
        
        foreach ($tables as $table) {
            $table_name = $wpdb->prefix . $table;
            $wpdb->query("DROP TABLE IF EXISTS $table_name");
        }
    }
    
    /**
     * Clean expired cache entries
     */
    public static function clean_expired_cache() {
        global $wpdb;
        
        $table_name = self::get_table_name('travel_cache');
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE expires_at < %s",
            current_time('mysql')
        ));
    }
    
    /**
     * Get database statistics
     */
    public static function get_stats() {
        global $wpdb;
        
        $stats = array();
        
        $tables = array(
            'djs' => 'Total DJs',
            'bookings' => 'Total Bookings',
            'commissions' => 'Commission Records',
            'equipment' => 'Equipment Items',
            'safeguards_log' => 'Safeguard Alerts'
        );
        
        foreach ($tables as $table => $label) {
            $table_name = self::get_table_name($table);
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            $stats[$label] = (int) $count;
        }
        
        return $stats;
    }
}
?>