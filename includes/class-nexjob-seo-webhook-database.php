<?php
/**
 * Webhook Database class for managing webhook database tables
 */

if (!defined('ABSPATH')) {
    exit;
}

class NexJob_SEO_Webhook_Database {
    
    /**
     * Create webhook-related database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Webhooks table
        $webhooks_table = $wpdb->prefix . 'nexjob_webhooks';
        $webhooks_sql = "CREATE TABLE IF NOT EXISTS {$webhooks_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text DEFAULT NULL,
            webhook_token varchar(100) NOT NULL UNIQUE,
            status enum('active', 'inactive') DEFAULT 'inactive',
            post_type varchar(50) DEFAULT NULL,
            field_mappings longtext DEFAULT NULL,
            default_status varchar(20) DEFAULT 'draft',
            auto_create tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY webhook_token (webhook_token),
            KEY status (status),
            KEY post_type (post_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Webhook data table
        $webhook_data_table = $wpdb->prefix . 'nexjob_webhook_data';
        $webhook_data_sql = "CREATE TABLE IF NOT EXISTS {$webhook_data_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            webhook_id int(11) NOT NULL,
            data longtext NOT NULL,
            headers text DEFAULT NULL,
            status enum('received', 'processing', 'processed', 'failed') DEFAULT 'received',
            post_id int(11) DEFAULT NULL,
            error_message text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            processed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY webhook_id (webhook_id),
            KEY status (status),
            KEY post_id (post_id),
            KEY created_at (created_at),
            FOREIGN KEY (webhook_id) REFERENCES {$webhooks_table} (id) ON DELETE CASCADE
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Create webhooks table first
        dbDelta($webhooks_sql);
        
        // Create webhook data table without foreign key constraint
        $webhook_data_sql_no_fk = "CREATE TABLE IF NOT EXISTS {$webhook_data_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            webhook_id int(11) NOT NULL,
            data longtext NOT NULL,
            headers text DEFAULT NULL,
            status enum('received', 'processing', 'processed', 'failed') DEFAULT 'received',
            post_id int(11) DEFAULT NULL,
            error_message text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            processed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY webhook_id (webhook_id),
            KEY status (status),
            KEY post_id (post_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($webhook_data_sql_no_fk);
        
        // Log table creation success
        error_log('NexJob SEO: Database tables created successfully');
        
        // Log table creation
        if (class_exists('NexJob_SEO_Logger')) {
            $logger = new NexJob_SEO_Logger();
            $logger->log('Webhook database tables created', 'info');
        }
    }
    
    /**
     * Drop webhook-related database tables
     */
    public static function drop_tables() {
        global $wpdb;
        
        $webhook_data_table = $wpdb->prefix . 'nexjob_webhook_data';
        $webhooks_table = $wpdb->prefix . 'nexjob_webhooks';
        
        // Drop tables in correct order (child table first)
        $wpdb->query("DROP TABLE IF EXISTS {$webhook_data_table}");
        $wpdb->query("DROP TABLE IF EXISTS {$webhooks_table}");
        
        // Log table deletion
        if (class_exists('NexJob_SEO_Logger')) {
            $logger = new NexJob_SEO_Logger();
            $logger->log('Webhook database tables dropped', 'info');
        }
    }
    
    /**
     * Force create tables if they don't exist
     */
    public static function ensure_tables_exist() {
        global $wpdb;
        
        $webhooks_table = $wpdb->prefix . 'nexjob_webhooks';
        $webhook_data_table = $wpdb->prefix . 'nexjob_webhook_data';
        
        // Check if tables exist
        $webhooks_exists = $wpdb->get_var("SHOW TABLES LIKE '{$webhooks_table}'") === $webhooks_table;
        $webhook_data_exists = $wpdb->get_var("SHOW TABLES LIKE '{$webhook_data_table}'") === $webhook_data_table;
        
        if (!$webhooks_exists || !$webhook_data_exists) {
            self::create_tables();
        }
        
        return $webhooks_exists && $webhook_data_exists;
    }
    
    /**
     * Check if webhook tables exist
     */
    public static function tables_exist() {
        global $wpdb;
        
        $webhooks_table = $wpdb->prefix . 'nexjob_webhooks';
        $webhook_data_table = $wpdb->prefix . 'nexjob_webhook_data';
        
        $webhooks_exists = $wpdb->get_var("SHOW TABLES LIKE '{$webhooks_table}'") === $webhooks_table;
        $webhook_data_exists = $wpdb->get_var("SHOW TABLES LIKE '{$webhook_data_table}'") === $webhook_data_table;
        
        return $webhooks_exists && $webhook_data_exists;
    }
    
    /**
     * Get database table status
     */
    public static function get_table_status() {
        global $wpdb;
        
        $webhooks_table = $wpdb->prefix . 'nexjob_webhooks';
        $webhook_data_table = $wpdb->prefix . 'nexjob_webhook_data';
        
        $status = array();
        
        // Check webhooks table
        $webhooks_count = $wpdb->get_var("SELECT COUNT(*) FROM {$webhooks_table}");
        $status['webhooks'] = array(
            'table' => $webhooks_table,
            'exists' => $wpdb->get_var("SHOW TABLES LIKE '{$webhooks_table}'") === $webhooks_table,
            'count' => (int) $webhooks_count
        );
        
        // Check webhook data table
        $webhook_data_count = $wpdb->get_var("SELECT COUNT(*) FROM {$webhook_data_table}");
        $status['webhook_data'] = array(
            'table' => $webhook_data_table,
            'exists' => $wpdb->get_var("SHOW TABLES LIKE '{$webhook_data_table}'") === $webhook_data_table,
            'count' => (int) $webhook_data_count
        );
        
        return $status;
    }
    
    /**
     * Update database schema if needed
     */
    public static function update_schema() {
        // Check current version and update if needed
        $current_version = get_option('nexjob_webhook_db_version', '1.0.0');
        
        // If we need to add new columns or tables in the future, we can do it here
        // For now, just ensure tables are created
        if (!self::tables_exist()) {
            self::create_tables();
        }
        
        // Update version
        update_option('nexjob_webhook_db_version', '1.0.0');
    }
}