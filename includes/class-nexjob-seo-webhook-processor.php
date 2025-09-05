<?php
/**
 * Webhook Processor class for handling incoming POST requests
 */

if (!defined('ABSPATH')) {
    exit;
}

class NexJob_SEO_Webhook_Processor {
    
    /**
     * Dependencies
     */
    private $logger;
    private $webhook_manager;
    private $webhook_data;
    private $field_mapper;
    
    /**
     * Constructor
     */
    public function __construct($logger, $webhook_manager, $webhook_data, $field_mapper) {
        $this->logger = $logger;
        $this->webhook_manager = $webhook_manager;
        $this->webhook_data = $webhook_data;
        $this->field_mapper = $field_mapper;
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('rest_api_init', array($this, 'register_webhook_endpoints'));
    }
    
    /**
     * Register webhook REST API endpoints
     */
    public function register_webhook_endpoints() {
        register_rest_route('nexjob-seo/v1', '/webhook/(?P<token>[a-zA-Z0-9_]+)', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_webhook_request'),
            'permission_callback' => '__return_true', // Public endpoint
            'args' => array(
                'token' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
        
        // Health check endpoint
        register_rest_route('nexjob-seo/v1', '/webhook/(?P<token>[a-zA-Z0-9_]+)/health', array(
            'methods' => 'GET',
            'callback' => array($this, 'webhook_health_check'),
            'permission_callback' => '__return_true'
        ));
    }
    
    /**
     * Handle incoming webhook request
     */
    public function handle_webhook_request($request) {
        $webhook_token = $request->get_param('token');
        
        // Get webhook by token
        $webhook = $this->webhook_manager->get_webhook_by_token($webhook_token);
        
        if (!$webhook) {
            $this->logger->log("Invalid webhook token received: {$webhook_token}", 'warning', null, null, array(
                'webhook_token' => $webhook_token,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ));
            
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Invalid webhook token'
            ), 404);
        }
        
        // Check if webhook is active
        if ($webhook->status !== 'active') {
            $this->logger->log("Inactive webhook accessed: {$webhook->name} (ID: {$webhook->id})", 'warning', null, null, array(
                'webhook_id' => $webhook->id,
                'webhook_status' => $webhook->status
            ));
            
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Webhook is not active'
            ), 403);
        }
        
        // Get request data and headers
        $request_data = $request->get_json_params();
        if (empty($request_data)) {
            $request_data = $request->get_body_params();
        }
        
        $headers = $request->get_headers();
        
        // Store webhook data
        $data_id = $this->webhook_data->store_webhook_data($webhook->id, $request_data, $headers);
        
        if (!$data_id) {
            $this->logger->log("Failed to store webhook data for webhook: {$webhook->name} (ID: {$webhook->id})", 'error', null, null, array(
                'webhook_id' => $webhook->id,
                'data' => $request_data
            ));
            
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Failed to store webhook data'
            ), 500);
        }
        
        $this->logger->log("Webhook data received: {$webhook->name} (ID: {$webhook->id})", 'info', null, null, array(
            'webhook_id' => $webhook->id,
            'data_id' => $data_id,
            'data_size' => strlen(json_encode($request_data))
        ));
        
        // Auto-create post if enabled and configured
        if ($webhook->auto_create && !empty($webhook->field_mappings)) {
            $post_result = $this->auto_create_post($webhook, $data_id, $request_data);
            
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Webhook data received and processed',
                'data_id' => $data_id,
                'post_created' => $post_result['success'],
                'post_id' => $post_result['post_id'] ?? null
            ), 200);
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Webhook data received',
            'data_id' => $data_id
        ), 200);
    }
    
    /**
     * Webhook health check endpoint
     */
    public function webhook_health_check($request) {
        $webhook_token = $request->get_param('token');
        $webhook = $this->webhook_manager->get_webhook_by_token($webhook_token);
        
        if (!$webhook) {
            return new WP_REST_Response(array(
                'status' => 'error',
                'message' => 'Webhook not found'
            ), 404);
        }
        
        return new WP_REST_Response(array(
            'status' => 'ok',
            'webhook_name' => $webhook->name,
            'webhook_status' => $webhook->status,
            'timestamp' => current_time('mysql')
        ), 200);
    }
    
    /**
     * Auto-create post from webhook data
     */
    public function auto_create_post($webhook, $data_id, $request_data) {
        try {
            // Update data status to processing
            $this->webhook_data->update_data_status($data_id, 'processing');
            
            // Map webhook data to WordPress fields
            $mapped_data = $this->field_mapper->map_data_to_post_fields(
                $request_data,
                json_decode($webhook->field_mappings, true),
                $webhook->post_type
            );
            
            if (!$mapped_data['success']) {
                $this->webhook_data->update_data_status($data_id, 'failed', null, $mapped_data['message']);
                return array('success' => false, 'message' => $mapped_data['message']);
            }
            
            // Create the post
            $post_id = $this->create_post_from_mapped_data($mapped_data['data'], $webhook->post_type, $webhook->default_status);
            
            if (is_wp_error($post_id)) {
                $error_message = $post_id->get_error_message();
                $this->webhook_data->update_data_status($data_id, 'failed', null, $error_message);
                
                $this->logger->log("Failed to create post from webhook data: {$error_message}", 'error', null, null, array(
                    'webhook_id' => $webhook->id,
                    'data_id' => $data_id,
                    'error' => $error_message
                ));
                
                return array('success' => false, 'message' => $error_message);
            }
            
            // Update data status to processed
            $this->webhook_data->update_data_status($data_id, 'processed', $post_id);
            
            $this->logger->log("Post created from webhook data: Post ID {$post_id}", 'success', $post_id, get_the_title($post_id), array(
                'webhook_id' => $webhook->id,
                'data_id' => $data_id,
                'post_id' => $post_id,
                'post_type' => $webhook->post_type
            ));
            
            return array('success' => true, 'post_id' => $post_id);
            
        } catch (Exception $e) {
            $this->webhook_data->update_data_status($data_id, 'failed', null, $e->getMessage());
            
            $this->logger->log("Exception in auto_create_post: " . $e->getMessage(), 'error', null, null, array(
                'webhook_id' => $webhook->id,
                'data_id' => $data_id,
                'exception' => $e->getTraceAsString()
            ));
            
            return array('success' => false, 'message' => $e->getMessage());
        }
    }
    
    /**
     * Create WordPress post from mapped data
     */
    private function create_post_from_mapped_data($mapped_data, $post_type, $default_status) {
        // Prepare post data
        $post_data = array(
            'post_type' => $post_type,
            'post_status' => $mapped_data['post_status'] ?? $default_status,
            'post_title' => $mapped_data['post_title'] ?? 'Untitled',
            'post_content' => $mapped_data['post_content'] ?? '',
            'post_excerpt' => $mapped_data['post_excerpt'] ?? '',
            'post_author' => get_current_user_id() ?: 1
        );
        
        // Remove post fields from mapped data to handle separately
        unset($mapped_data['post_title'], $mapped_data['post_content'], $mapped_data['post_excerpt'], $mapped_data['post_status']);
        
        // Insert the post
        $post_id = wp_insert_post($post_data, true);
        
        if (is_wp_error($post_id)) {
            return $post_id;
        }
        
        // Handle meta fields
        foreach ($mapped_data as $field_name => $field_value) {
            if (strpos($field_name, 'meta_') === 0) {
                $meta_key = substr($field_name, 5); // Remove 'meta_' prefix
                update_post_meta($post_id, $meta_key, $field_value);
            }
        }
        
        // Handle taxonomies - collect all terms per taxonomy first
        $taxonomy_terms = array();
        foreach ($mapped_data as $field_name => $field_value) {
            if (strpos($field_name, 'tax_') === 0) {
                $taxonomy = substr($field_name, 4); // Remove 'tax_' prefix
                if (taxonomy_exists($taxonomy)) {
                    // Handle different value formats
                    if (is_string($field_value)) {
                        $terms = array_map('trim', explode(',', $field_value));
                    } elseif (is_array($field_value)) {
                        $terms = $field_value;
                    } else {
                        $terms = array($field_value);
                    }
                    
                    // Initialize taxonomy array if not exists
                    if (!isset($taxonomy_terms[$taxonomy])) {
                        $taxonomy_terms[$taxonomy] = array();
                    }
                    
                    // Process each term - create if doesn't exist
                    foreach ($terms as $term_name) {
                        if (empty($term_name)) continue;
                        
                        // Check if it's numeric (might be an ID)
                        if (is_numeric($term_name)) {
                            $existing_term = get_term((int)$term_name, $taxonomy);
                            if ($existing_term && !is_wp_error($existing_term)) {
                                $taxonomy_terms[$taxonomy][] = $existing_term->term_id;
                                continue;
                            }
                        }
                        
                        // Try to find existing term by name or slug
                        $existing_term = get_term_by('name', $term_name, $taxonomy);
                        if (!$existing_term) {
                            $existing_term = get_term_by('slug', sanitize_title($term_name), $taxonomy);
                        }
                        
                        if ($existing_term) {
                            $taxonomy_terms[$taxonomy][] = $existing_term->term_id;
                        } else {
                            // Create new term if it doesn't exist
                            $new_term = wp_insert_term($term_name, $taxonomy);
                            if (!is_wp_error($new_term)) {
                                $taxonomy_terms[$taxonomy][] = $new_term['term_id'];
                            } else {
                                // If term already exists (race condition), try to get it
                                if (strpos($new_term->get_error_code(), 'term_exists') !== false) {
                                    $existing_term = get_term_by('name', $term_name, $taxonomy);
                                    if ($existing_term) {
                                        $taxonomy_terms[$taxonomy][] = $existing_term->term_id;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // Now assign all collected terms to their respective taxonomies
        foreach ($taxonomy_terms as $taxonomy => $term_ids) {
            if (!empty($term_ids)) {
                // Remove duplicates and assign
                $unique_term_ids = array_unique($term_ids);
                wp_set_post_terms($post_id, $unique_term_ids, $taxonomy);
                
                // For categories, explicitly remove the default "Uncategorized" if we assigned custom categories
                if ($taxonomy === 'category') {
                    $default_category_id = get_option('default_category');
                    if ($default_category_id && !in_array($default_category_id, $unique_term_ids)) {
                        // Remove default category since we have custom categories
                        wp_remove_object_terms($post_id, (int)$default_category_id, 'category');
                    }
                }
            }
        }
        
        // Handle featured image if provided
        if (isset($mapped_data['featured_image']) && !empty($mapped_data['featured_image'])) {
            $this->set_featured_image_from_url($post_id, $mapped_data['featured_image']);
        }
        
        return $post_id;
    }
    
    /**
     * Set featured image from URL
     */
    private function set_featured_image_from_url($post_id, $image_url) {
        try {
            // Download image
            $image_data = wp_remote_get($image_url);
            
            if (is_wp_error($image_data)) {
                return false;
            }
            
            $image_body = wp_remote_retrieve_body($image_data);
            $image_name = basename(parse_url($image_url, PHP_URL_PATH));
            
            // Upload to media library
            $upload = wp_upload_bits($image_name, null, $image_body);
            
            if ($upload['error']) {
                return false;
            }
            
            // Create attachment
            $attachment = array(
                'post_mime_type' => wp_check_filetype($upload['file'])['type'],
                'post_title' => sanitize_file_name($image_name),
                'post_content' => '',
                'post_status' => 'inherit'
            );
            
            $attachment_id = wp_insert_attachment($attachment, $upload['file'], $post_id);
            
            if (!is_wp_error($attachment_id)) {
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload['file']);
                wp_update_attachment_metadata($attachment_id, $attachment_data);
                
                set_post_thumbnail($post_id, $attachment_id);
                return $attachment_id;
            }
            
        } catch (Exception $e) {
            $this->logger->log("Failed to set featured image: " . $e->getMessage(), 'error', $post_id);
        }
        
        return false;
    }
    
    /**
     * Process webhook data manually (for admin interface)
     */
    public function process_webhook_data_manually($webhook_id, $data_id) {
        $webhook = $this->webhook_manager->get_webhook($webhook_id);
        $data_record = $this->webhook_data->get_webhook_data_by_id($data_id);
        
        if (!$webhook || !$data_record) {
            return array('success' => false, 'message' => 'Webhook or data not found');
        }
        
        if (empty($webhook->field_mappings)) {
            return array('success' => false, 'message' => 'Webhook not configured for auto-creation');
        }
        
        $request_data = json_decode($data_record->data, true);
        return $this->auto_create_post($webhook, $data_id, $request_data);
    }
}