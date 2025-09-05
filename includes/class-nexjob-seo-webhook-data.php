<?php
/**
 * Webhook Data Storage class for managing received data
 */

if (!defined('ABSPATH')) {
    exit;
}

class NexJob_SEO_Webhook_Data {
    
    /**
     * Dependencies
     */
    private $logger;
    
    /**
     * Database table name
     */
    private $webhook_data_table;
    
    /**
     * Constructor
     */
    public function __construct($logger) {
        global $wpdb;
        $this->logger = $logger;
        $this->webhook_data_table = $wpdb->prefix . 'nexjob_webhook_data';
    }
    
    /**
     * Store incoming webhook data
     */
    public function store_webhook_data($webhook_id, $data, $headers = array()) {
        global $wpdb;
        
        // Sanitize and prepare data
        $json_data = json_encode($data, JSON_PRETTY_PRINT);
        $json_headers = json_encode($headers, JSON_PRETTY_PRINT);
        
        $result = $wpdb->insert(
            $this->webhook_data_table,
            array(
                'webhook_id' => $webhook_id,
                'data' => $json_data,
                'headers' => $json_headers,
                'status' => 'received',
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );
        
        if ($result) {
            $data_id = $wpdb->insert_id;
            $this->logger->log("Webhook data stored: Webhook ID {$webhook_id}, Data ID {$data_id}", 'info', null, null, array(
                'webhook_id' => $webhook_id,
                'data_id' => $data_id,
                'data_size' => strlen($json_data)
            ));
            
            return $data_id;
        }
        
        $this->logger->log("Failed to store webhook data for webhook ID {$webhook_id}", 'error', null, null, array(
            'webhook_id' => $webhook_id,
            'data' => $data
        ));
        
        return false;
    }
    
    /**
     * Get webhook data by webhook ID
     */
    public function get_webhook_data($webhook_id, $limit = 10, $offset = 0) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->webhook_data_table} 
             WHERE webhook_id = %d 
             ORDER BY created_at DESC 
             LIMIT %d, %d",
            $webhook_id, $limit, $offset
        ));
    }
    
    /**
     * Get latest unprocessed webhook data
     */
    public function get_latest_unprocessed_data($webhook_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->webhook_data_table} 
             WHERE webhook_id = %d AND status = 'received' 
             ORDER BY created_at DESC 
             LIMIT 1",
            $webhook_id
        ));
    }
    
    /**
     * Get webhook data by ID
     */
    public function get_webhook_data_by_id($data_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->webhook_data_table} WHERE id = %d",
            $data_id
        ));
    }
    
    /**
     * Update webhook data status
     */
    public function update_data_status($data_id, $status, $post_id = null, $error_message = null) {
        global $wpdb;
        
        $update_data = array(
            'status' => $status,
            'processed_at' => current_time('mysql')
        );
        
        if ($post_id) {
            $update_data['post_id'] = $post_id;
        }
        
        if ($error_message) {
            $update_data['error_message'] = $error_message;
        }
        
        $result = $wpdb->update(
            $this->webhook_data_table,
            $update_data,
            array('id' => $data_id),
            array('%s', '%s', '%d', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            $this->logger->log("Webhook data status updated: Data ID {$data_id} -> {$status}", 'info', $post_id, null, array(
                'data_id' => $data_id,
                'new_status' => $status,
                'post_id' => $post_id,
                'error_message' => $error_message
            ));
            return true;
        }
        
        return false;
    }
    
    /**
     * Parse webhook data to extract fields
     */
    public function parse_webhook_data($data_json) {
        $data = json_decode($data_json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->log("JSON parsing error: " . json_last_error_msg(), 'error', null, null, array(
                'data_json' => $data_json,
                'json_error' => json_last_error_msg()
            ));
            return array('error' => 'Invalid JSON data: ' . json_last_error_msg());
        }
        
        // Flatten nested arrays for easier field mapping
        $flattened_data = $this->flatten_array($data);
        $available_fields = array_keys($flattened_data);
        
        // Log the parsing results for debugging
        $this->logger->log("Webhook data parsed successfully", 'info', null, null, array(
            'original_data_keys' => array_keys($data),
            'flattened_data_keys' => $available_fields,
            'field_count' => count($available_fields)
        ));
        
        return array(
            'original' => $data,
            'flattened' => $flattened_data,
            'available_fields' => $available_fields
        );
    }
    
    /**
     * Flatten nested array for field mapping
     */
    private function flatten_array($array, $prefix = '') {
        $result = array();
        
        foreach ($array as $key => $value) {
            $new_key = $prefix === '' ? $key : $prefix . '.' . $key;
            
            if (is_array($value) && !empty($value)) {
                // If it's an indexed array with simple values, join them
                if (array_keys($value) === range(0, count($value) - 1) && !is_array($value[0])) {
                    $result[$new_key] = implode(', ', $value);
                } else {
                    // Recursively flatten associative arrays
                    $result = array_merge($result, $this->flatten_array($value, $new_key));
                }
            } else {
                $result[$new_key] = is_string($value) || is_numeric($value) ? $value : json_encode($value);
            }
        }
        
        return $result;
    }
    
    /**
     * Get webhook data statistics
     */
    public function get_webhook_data_stats($webhook_id) {
        global $wpdb;
        
        // Total requests
        $total_requests = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->webhook_data_table} WHERE webhook_id = %d",
            $webhook_id
        ));
        
        // Recent requests (last 24 hours)
        $recent_requests = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->webhook_data_table} 
             WHERE webhook_id = %d AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            $webhook_id
        ));
        
        // Posts created
        $posts_created = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->webhook_data_table} 
             WHERE webhook_id = %d AND status = 'processed' AND post_id IS NOT NULL",
            $webhook_id
        ));
        
        // Last request
        $last_request = $wpdb->get_var($wpdb->prepare(
            "SELECT created_at FROM {$this->webhook_data_table} 
             WHERE webhook_id = %d ORDER BY created_at DESC LIMIT 1",
            $webhook_id
        ));
        
        return array(
            'total_requests' => (int) $total_requests,
            'recent_requests' => (int) $recent_requests,
            'posts_created' => (int) $posts_created,
            'last_request' => $last_request
        );
    }
    
    /**
     * Delete webhook data for a specific webhook
     */
    public function delete_webhook_data($webhook_id) {
        global $wpdb;
        
        $result = $wpdb->delete(
            $this->webhook_data_table,
            array('webhook_id' => $webhook_id),
            array('%d')
        );
        
        if ($result !== false) {
            $this->logger->log("Webhook data deleted for webhook ID {$webhook_id}", 'info', null, null, array(
                'webhook_id' => $webhook_id,
                'deleted_records' => $result
            ));
        }
        
        return $result;
    }
    
    /**
     * Clean old webhook data (older than specified days)
     */
    public function clean_old_data($days = 30) {
        global $wpdb;
        
        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->webhook_data_table} 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
        
        if ($result !== false && $result > 0) {
            $this->logger->log("Cleaned old webhook data: {$result} records deleted", 'info', null, null, array(
                'deleted_records' => $result,
                'older_than_days' => $days
            ));
        }
        
        return $result;
    }
    
    /**
     * Get field suggestions based on webhook data
     */
    public function get_field_suggestions($webhook_id) {
        global $wpdb;
        
        // Get recent webhook data to analyze common fields
        $recent_data = $wpdb->get_results($wpdb->prepare(
            "SELECT data FROM {$this->webhook_data_table} 
             WHERE webhook_id = %d 
             ORDER BY created_at DESC 
             LIMIT 10",
            $webhook_id
        ));
        
        $all_fields = array();
        
        foreach ($recent_data as $data_record) {
            $parsed = $this->parse_webhook_data($data_record->data);
            if (isset($parsed['available_fields'])) {
                $all_fields = array_merge($all_fields, $parsed['available_fields']);
            }
        }
        
        // Count field frequency and return most common fields
        $field_frequency = array_count_values($all_fields);
        arsort($field_frequency);
        
        return array_keys($field_frequency);
    }
}