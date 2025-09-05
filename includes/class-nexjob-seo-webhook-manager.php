<?php
/**
 * Webhook Manager class for webhook creation and URL generation
 */

if (!defined('ABSPATH')) {
    exit;
}

class NexJob_SEO_Webhook_Manager {
    
    /**
     * Dependencies
     */
    private $logger;
    private $webhook_data;
    
    /**
     * Database table name
     */
    private $webhooks_table;
    
    /**
     * Constructor
     */
    public function __construct($logger, $webhook_data) {
        global $wpdb;
        $this->logger = $logger;
        $this->webhook_data = $webhook_data;
        $this->webhooks_table = $wpdb->prefix . 'nexjob_webhooks';
        
        // Ensure tables exist
        NexJob_SEO_Webhook_Database::ensure_tables_exist();
    }
    
    /**
     * Create a new webhook
     */
    public function create_webhook($name, $description = '') {
        global $wpdb;
        
        // Generate unique webhook token
        $webhook_token = $this->generate_webhook_token();
        
        // Insert webhook into database
        $result = $wpdb->insert(
            $this->webhooks_table,
            array(
                'name' => $name,
                'description' => $description,
                'webhook_token' => $webhook_token,
                'status' => 'inactive',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result) {
            $webhook_id = $wpdb->insert_id;
            $this->logger->log("Webhook created successfully: {$name} (ID: {$webhook_id})", 'success', null, null, array(
                'webhook_id' => $webhook_id,
                'webhook_token' => $webhook_token,
                'webhook_url' => $this->get_webhook_url($webhook_token)
            ));
            
            return array(
                'success' => true,
                'webhook_id' => $webhook_id,
                'webhook_token' => $webhook_token,
                'webhook_url' => $this->get_webhook_url($webhook_token)
            );
        }
        
        $this->logger->log("Failed to create webhook: {$name}", 'error');
        return array('success' => false, 'message' => 'Failed to create webhook');
    }
    
    /**
     * Generate unique webhook token
     */
    private function generate_webhook_token() {
        return 'nexjob_' . wp_generate_password(32, false, false);
    }
    
    /**
     * Get webhook URL from token
     */
    public function get_webhook_url($webhook_token) {
        return home_url("/wp-json/nexjob-seo/v1/webhook/{$webhook_token}");
    }
    
    /**
     * Get all webhooks
     */
    public function get_webhooks($status = '') {
        global $wpdb;
        
        $where_clause = '';
        $where_values = array();
        
        if (!empty($status)) {
            $where_clause = ' WHERE status = %s';
            $where_values[] = $status;
        }
        
        $query = "SELECT * FROM {$this->webhooks_table}{$where_clause} ORDER BY created_at DESC";
        
        if (!empty($where_values)) {
            return $wpdb->get_results($wpdb->prepare($query, $where_values));
        } else {
            return $wpdb->get_results($query);
        }
    }
    
    /**
     * Get webhook by ID
     */
    public function get_webhook($webhook_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->webhooks_table} WHERE id = %d",
            $webhook_id
        ));
    }
    
    /**
     * Get webhook by token
     */
    public function get_webhook_by_token($webhook_token) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->webhooks_table} WHERE webhook_token = %s",
            $webhook_token
        ));
    }
    
    /**
     * Update webhook status
     */
    public function update_webhook_status($webhook_id, $status) {
        global $wpdb;
        
        $result = $wpdb->update(
            $this->webhooks_table,
            array(
                'status' => $status,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $webhook_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            $this->logger->log("Webhook status updated: ID {$webhook_id} -> {$status}", 'info', null, null, array(
                'webhook_id' => $webhook_id,
                'new_status' => $status
            ));
            return true;
        }
        
        return false;
    }
    
    /**
     * Update webhook configuration
     */
    public function update_webhook_config($webhook_id, $config) {
        global $wpdb;
        
        $result = $wpdb->update(
            $this->webhooks_table,
            array(
                'post_type' => $config['post_type'],
                'field_mappings' => json_encode($config['field_mappings']),
                'default_status' => $config['default_status'],
                'auto_create' => $config['auto_create'] ? 1 : 0,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $webhook_id),
            array('%s', '%s', '%s', '%d', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            $this->logger->log("Webhook configuration updated: ID {$webhook_id}", 'info', null, null, array(
                'webhook_id' => $webhook_id,
                'config' => $config
            ));
            return true;
        }
        
        return false;
    }
    
    /**
     * Delete webhook
     */
    public function delete_webhook($webhook_id) {
        global $wpdb;
        
        // Get webhook info before deletion
        $webhook = $this->get_webhook($webhook_id);
        if (!$webhook) {
            return false;
        }
        
        // Delete associated webhook data first
        $this->webhook_data->delete_webhook_data($webhook_id);
        
        // Delete webhook
        $result = $wpdb->delete(
            $this->webhooks_table,
            array('id' => $webhook_id),
            array('%d')
        );
        
        if ($result) {
            $this->logger->log("Webhook deleted: {$webhook->name} (ID: {$webhook_id})", 'info', null, null, array(
                'webhook_id' => $webhook_id,
                'webhook_name' => $webhook->name
            ));
            return true;
        }
        
        return false;
    }
    
    /**
     * Regenerate webhook token
     */
    public function regenerate_webhook_token($webhook_id) {
        global $wpdb;
        
        $new_token = $this->generate_webhook_token();
        
        $result = $wpdb->update(
            $this->webhooks_table,
            array(
                'webhook_token' => $new_token,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $webhook_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            $this->logger->log("Webhook token regenerated: ID {$webhook_id}", 'info', null, null, array(
                'webhook_id' => $webhook_id,
                'new_token' => $new_token,
                'new_url' => $this->get_webhook_url($new_token)
            ));
            
            return array(
                'success' => true,
                'webhook_token' => $new_token,
                'webhook_url' => $this->get_webhook_url($new_token)
            );
        }
        
        return array('success' => false, 'message' => 'Failed to regenerate token');
    }
    
    /**
     * Get webhook statistics
     */
    public function get_webhook_stats($webhook_id) {
        $webhook_data_stats = $this->webhook_data->get_webhook_data_stats($webhook_id);
        
        return array(
            'total_requests' => $webhook_data_stats['total_requests'],
            'recent_requests' => $webhook_data_stats['recent_requests'],
            'posts_created' => $webhook_data_stats['posts_created'],
            'last_request' => $webhook_data_stats['last_request']
        );
    }
    
    /**
     * Validate webhook configuration
     */
    public function validate_webhook_config($config) {
        $errors = array();
        
        // Validate post type
        if (empty($config['post_type'])) {
            $errors[] = 'Post type is required';
        } elseif (!post_type_exists($config['post_type'])) {
            $errors[] = 'Invalid post type';
        }
        
        // Validate field mappings
        if (empty($config['field_mappings']) || !is_array($config['field_mappings'])) {
            $errors[] = 'Field mappings are required';
        } else {
            // Check if title mapping exists
            $has_title_mapping = false;
            foreach ($config['field_mappings'] as $mapping) {
                if ($mapping['wp_field'] === 'post_title') {
                    $has_title_mapping = true;
                    break;
                }
            }
            if (!$has_title_mapping) {
                $errors[] = 'Title field mapping is required';
            }
        }
        
        // Validate default status
        if (empty($config['default_status'])) {
            $errors[] = 'Default post status is required';
        } elseif (!in_array($config['default_status'], array('draft', 'publish', 'private'))) {
            $errors[] = 'Invalid default post status';
        }
        
        return empty($errors) ? array('valid' => true) : array('valid' => false, 'errors' => $errors);
    }
}