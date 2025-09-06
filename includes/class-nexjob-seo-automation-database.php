<?php
/**
 * Database manager for auto featured images automation configurations
 */

if (!defined('ABSPATH')) {
    exit;
}

class NexJob_SEO_Automation_Database {
    
    /**
     * Create automation tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Automation configurations table
        $automation_table = $wpdb->prefix . 'nexjob_featured_image_automations';
        $automation_sql = "CREATE TABLE $automation_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            status enum('active','inactive') DEFAULT 'active',
            post_types text NOT NULL,
            template_name varchar(255) NOT NULL,
            font_size int(11) DEFAULT 48,
            font_color varchar(7) DEFAULT '#FFFFFF',
            text_align enum('left','center','right') DEFAULT 'center',
            text_area_x int(11) DEFAULT 50,
            text_area_y int(11) DEFAULT 100,
            text_area_width int(11) DEFAULT 1100,
            text_area_height int(11) DEFAULT 430,
            max_title_length int(11) DEFAULT 80,
            apply_to_existing tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($automation_sql);
        
        // Create default automation if none exists
        $existing = $wpdb->get_var("SELECT COUNT(*) FROM $automation_table");
        if ($existing == 0) {
            $wpdb->insert($automation_table, array(
                'name' => 'Default Automation',
                'status' => 'active',
                'post_types' => json_encode(array('post', 'page')),
                'template_name' => 'default.png',
                'font_size' => 48,
                'font_color' => '#FFFFFF',
                'text_align' => 'center',
                'text_area_x' => 50,
                'text_area_y' => 100,
                'text_area_width' => 1100,
                'text_area_height' => 430,
                'max_title_length' => 80,
                'apply_to_existing' => 0
            ));
        }
    }
    
    /**
     * Drop automation tables
     */
    public static function drop_tables() {
        global $wpdb;
        
        $automation_table = $wpdb->prefix . 'nexjob_featured_image_automations';
        $wpdb->query("DROP TABLE IF EXISTS $automation_table");
    }
    
    /**
     * Check table status
     */
    public static function check_tables() {
        global $wpdb;
        
        $automation_table = $wpdb->prefix . 'nexjob_featured_image_automations';
        
        $automation_exists = $wpdb->get_var("SHOW TABLES LIKE '$automation_table'") === $automation_table;
        
        return array(
            'automations' => $automation_exists
        );
    }
}