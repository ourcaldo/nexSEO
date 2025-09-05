<?php
/**
 * Logger class for database logging functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class NexJob_SEO_Logger {
    
    /**
     * Log table name
     */
    private $log_table;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->log_table = $wpdb->prefix . 'nexjob_seo_logs';
    }
    
    /**
     * Create log table if it doesn't exist
     */
    public function create_log_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->log_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            level varchar(20) NOT NULL DEFAULT 'info',
            message text NOT NULL,
            post_id int(11) DEFAULT NULL,
            post_title varchar(255) DEFAULT NULL,
            post_type varchar(50) DEFAULT NULL,
            context text DEFAULT NULL,
            PRIMARY KEY (id),
            KEY timestamp (timestamp),
            KEY level (level),
            KEY post_id (post_id),
            KEY post_type (post_type)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Log message to database
     */
    public function log($message, $level = 'info', $post_id = null, $post_title = null, $context = null) {
        global $wpdb;
        
        $post_type = null;
        if ($post_id && function_exists('get_post_type')) {
            $post_type = get_post_type($post_id);
        }
        
        $result = $wpdb->insert(
            $this->log_table,
            array(
                'timestamp' => current_time('mysql'),
                'level' => $level,
                'message' => $message,
                'post_id' => $post_id,
                'post_title' => $post_title,
                'post_type' => $post_type,
                'context' => is_array($context) ? json_encode($context, JSON_PRETTY_PRINT) : $context
            ),
            array('%s', '%s', '%s', '%d', '%s', '%s', '%s')
        );
        
        // Also log to WordPress error log for critical errors
        if ($level === 'error') {
            error_log("NexJob SEO Error: $message");
        }
        
        return $result;
    }
    
    /**
     * Get logs with optional filtering
     */
    public function get_logs($level = '', $post_type = '', $limit = 100) {
        global $wpdb;
        
        $where_conditions = array();
        $where_values = array();
        
        if (!empty($level)) {
            $where_conditions[] = 'level = %s';
            $where_values[] = $level;
        }
        
        if (!empty($post_type)) {
            $where_conditions[] = 'post_type = %s';
            $where_values[] = $post_type;
        }
        
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = ' WHERE ' . implode(' AND ', $where_conditions);
        }
        
        $query = "SELECT * FROM {$this->log_table}{$where_clause} ORDER BY timestamp DESC LIMIT %d";
        $where_values[] = $limit;
        
        return $wpdb->get_results($wpdb->prepare($query, $where_values));
    }
    
    /**
     * Clear all logs
     */
    public function clear_logs() {
        global $wpdb;
        return $wpdb->query("TRUNCATE TABLE {$this->log_table}");
    }
    
    /**
     * Get log statistics
     */
    public function get_log_stats() {
        global $wpdb;
        
        $stats = $wpdb->get_results("
            SELECT level, COUNT(*) as count 
            FROM {$this->log_table} 
            GROUP BY level
        ");
        
        $formatted_stats = array();
        foreach ($stats as $stat) {
            $formatted_stats[$stat->level] = $stat->count;
        }
        
        return $formatted_stats;
    }
    
    /**
     * Get recent log entries count
     */
    public function get_recent_logs_count($hours = 24) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$this->log_table} 
            WHERE timestamp > DATE_SUB(NOW(), INTERVAL %d HOUR)
        ", $hours));
    }
    
    /**
     * Get log entries for specific post
     */
    public function get_post_logs($post_id, $limit = 20) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT * 
            FROM {$this->log_table} 
            WHERE post_id = %d 
            ORDER BY timestamp DESC 
            LIMIT %d
        ", $post_id, $limit));
    }
}