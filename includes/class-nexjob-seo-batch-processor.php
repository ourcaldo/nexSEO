<?php
/**
 * Batch Processor
 * 
 * Handles queue management, bulk processing, and performance optimization for featured image generation
 */

class NexJob_SEO_Batch_Processor {
    private $settings;
    private $logger;
    private $auto_featured_image;
    private $batch_size;
    private $processing_timeout;

    public function __construct($settings, $logger, $auto_featured_image) {
        $this->settings = $settings;
        $this->logger = $logger;
        $this->auto_featured_image = $auto_featured_image;
        $this->batch_size = $this->settings->get('batch_processing_size', 10);
        $this->processing_timeout = $this->settings->get('batch_processing_timeout', 300); // 5 minutes
        
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Schedule batch processing
        add_action('nexjob_seo_batch_process_featured_images', array($this, 'process_batch'));
        
        // AJAX endpoints
        add_action('wp_ajax_nexjob_start_batch_processing', array($this, 'ajax_start_batch_processing'));
        add_action('wp_ajax_nexjob_get_batch_status', array($this, 'ajax_get_batch_status'));
        add_action('wp_ajax_nexjob_cancel_batch_processing', array($this, 'ajax_cancel_batch_processing'));
    }

    /**
     * Start batch processing for posts without featured images
     */
    public function start_batch_processing($post_types = null, $limit = null) {
        $post_types = $post_types ?: $this->settings->get('auto_featured_images_post_types', array('post', 'page'));
        $limit = $limit ?: $this->batch_size;

        // Get posts without featured images
        $posts = $this->auto_featured_image->get_posts_without_featured_images($limit);
        
        if (empty($posts)) {
            $this->logger->log('No posts found without featured images for batch processing');
            return array(
                'success' => true,
                'message' => 'No posts found without featured images',
                'processed' => 0
            );
        }

        // Create batch job
        $batch_id = $this->create_batch_job($posts);
        
        // Schedule immediate processing
        if (!wp_next_scheduled('nexjob_seo_batch_process_featured_images', array($batch_id))) {
            wp_schedule_single_event(time(), 'nexjob_seo_batch_process_featured_images', array($batch_id));
        }

        $this->logger->log("Started batch processing for " . count($posts) . " posts (Batch ID: {$batch_id})");

        return array(
            'success' => true,
            'batch_id' => $batch_id,
            'total_posts' => count($posts),
            'message' => 'Batch processing started'
        );
    }

    /**
     * Create a batch job
     */
    private function create_batch_job($posts) {
        $batch_id = uniqid('batch_');
        $post_ids = wp_list_pluck($posts, 'ID');
        
        $batch_data = array(
            'id' => $batch_id,
            'post_ids' => $post_ids,
            'total' => count($post_ids),
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'status' => 'pending',
            'started_at' => current_time('mysql'),
            'completed_at' => null,
            'errors' => array()
        );

        update_option('nexjob_seo_batch_' . $batch_id, $batch_data);
        
        return $batch_id;
    }

    /**
     * Process a batch of featured image generations
     */
    public function process_batch($batch_id) {
        $batch_data = get_option('nexjob_seo_batch_' . $batch_id);
        
        if (!$batch_data || $batch_data['status'] !== 'pending') {
            return;
        }

        // Mark batch as processing
        $batch_data['status'] = 'processing';
        update_option('nexjob_seo_batch_' . $batch_id, $batch_data);

        $this->logger->log("Processing batch {$batch_id} with " . count($batch_data['post_ids']) . " posts");

        $start_time = time();
        $processed_in_session = 0;
        $max_execution_time = min($this->processing_timeout, ini_get('max_execution_time') - 30);

        foreach ($batch_data['post_ids'] as $index => $post_id) {
            // Check execution time limit
            if ((time() - $start_time) > $max_execution_time) {
                // Reschedule remaining posts
                $remaining_posts = array_slice($batch_data['post_ids'], $index);
                $this->reschedule_batch($batch_id, $remaining_posts, $batch_data);
                return;
            }

            // Skip if already processed
            if ($index < $batch_data['processed']) {
                continue;
            }

            try {
                $result = $this->auto_featured_image->generate_featured_image($post_id);
                
                if ($result) {
                    $batch_data['successful']++;
                    $this->logger->log("Batch {$batch_id}: Successfully generated featured image for post {$post_id}");
                } else {
                    $batch_data['failed']++;
                    $batch_data['errors'][] = "Failed to generate featured image for post {$post_id}";
                    $this->logger->log("Batch {$batch_id}: Failed to generate featured image for post {$post_id}", 'error');
                }

            } catch (Exception $e) {
                $batch_data['failed']++;
                $batch_data['errors'][] = "Error processing post {$post_id}: " . $e->getMessage();
                $this->logger->log("Batch {$batch_id}: Error processing post {$post_id}: " . $e->getMessage(), 'error');
            }

            $batch_data['processed']++;
            $processed_in_session++;

            // Update progress every 5 posts or if we're near the end
            if ($processed_in_session % 5 === 0 || $batch_data['processed'] >= $batch_data['total']) {
                update_option('nexjob_seo_batch_' . $batch_id, $batch_data);
            }

            // Short pause to prevent overwhelming the server
            usleep(100000); // 0.1 seconds
        }

        // Mark batch as completed
        $batch_data['status'] = 'completed';
        $batch_data['completed_at'] = current_time('mysql');
        update_option('nexjob_seo_batch_' . $batch_id, $batch_data);

        $this->logger->log("Batch {$batch_id} completed: {$batch_data['successful']} successful, {$batch_data['failed']} failed");

        // Clean up old batch data (keep for 7 days)
        $this->cleanup_old_batches();
    }

    /**
     * Reschedule remaining posts in a batch
     */
    private function reschedule_batch($batch_id, $remaining_posts, $batch_data) {
        $batch_data['post_ids'] = $remaining_posts;
        $batch_data['status'] = 'pending';
        update_option('nexjob_seo_batch_' . $batch_id, $batch_data);

        // Schedule next batch processing in 2 minutes
        wp_schedule_single_event(time() + 120, 'nexjob_seo_batch_process_featured_images', array($batch_id));
        
        $this->logger->log("Batch {$batch_id} rescheduled with " . count($remaining_posts) . " remaining posts");
    }

    /**
     * Get batch processing status
     */
    public function get_batch_status($batch_id) {
        $batch_data = get_option('nexjob_seo_batch_' . $batch_id);
        
        if (!$batch_data) {
            return array(
                'success' => false,
                'message' => 'Batch not found'
            );
        }

        $progress_percentage = $batch_data['total'] > 0 ? 
            round(($batch_data['processed'] / $batch_data['total']) * 100, 2) : 0;

        return array(
            'success' => true,
            'batch_id' => $batch_id,
            'status' => $batch_data['status'],
            'total' => $batch_data['total'],
            'processed' => $batch_data['processed'],
            'successful' => $batch_data['successful'],
            'failed' => $batch_data['failed'],
            'progress_percentage' => $progress_percentage,
            'started_at' => $batch_data['started_at'],
            'completed_at' => $batch_data['completed_at'],
            'errors' => $batch_data['errors']
        );
    }

    /**
     * Cancel batch processing
     */
    public function cancel_batch_processing($batch_id) {
        $batch_data = get_option('nexjob_seo_batch_' . $batch_id);
        
        if (!$batch_data) {
            return array(
                'success' => false,
                'message' => 'Batch not found'
            );
        }

        if ($batch_data['status'] === 'completed') {
            return array(
                'success' => false,
                'message' => 'Batch already completed'
            );
        }

        // Mark as cancelled
        $batch_data['status'] = 'cancelled';
        $batch_data['completed_at'] = current_time('mysql');
        update_option('nexjob_seo_batch_' . $batch_id, $batch_data);

        // Clear scheduled events
        wp_clear_scheduled_hook('nexjob_seo_batch_process_featured_images', array($batch_id));

        $this->logger->log("Batch {$batch_id} cancelled by user");

        return array(
            'success' => true,
            'message' => 'Batch processing cancelled'
        );
    }

    /**
     * Clean up old batch data
     */
    private function cleanup_old_batches() {
        global $wpdb;
        
        // Get all batch options
        $batch_options = $wpdb->get_results(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'nexjob_seo_batch_%'"
        );

        $cutoff_date = date('Y-m-d H:i:s', strtotime('-7 days'));

        foreach ($batch_options as $option) {
            $batch_data = get_option($option->option_name);
            
            if (isset($batch_data['completed_at']) && 
                $batch_data['completed_at'] < $cutoff_date) {
                delete_option($option->option_name);
            }
        }
    }

    /**
     * AJAX handler for starting batch processing
     */
    public function ajax_start_batch_processing() {
        check_ajax_referer('nexjob_seo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $post_types = isset($_POST['post_types']) ? array_map('sanitize_text_field', $_POST['post_types']) : null;
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : null;

        $result = $this->start_batch_processing($post_types, $limit);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX handler for getting batch status
     */
    public function ajax_get_batch_status() {
        check_ajax_referer('nexjob_seo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $batch_id = sanitize_text_field($_POST['batch_id']);
        $status = $this->get_batch_status($batch_id);
        
        wp_send_json($status);
    }

    /**
     * AJAX handler for cancelling batch processing
     */
    public function ajax_cancel_batch_processing() {
        check_ajax_referer('nexjob_seo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $batch_id = sanitize_text_field($_POST['batch_id']);
        $result = $this->cancel_batch_processing($batch_id);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Get all recent batches
     */
    public function get_recent_batches($limit = 10) {
        global $wpdb;
        
        $batch_options = $wpdb->get_results(
            "SELECT option_name FROM {$wpdb->options} 
             WHERE option_name LIKE 'nexjob_seo_batch_%' 
             ORDER BY option_id DESC 
             LIMIT {$limit}"
        );

        $batches = array();
        foreach ($batch_options as $option) {
            $batch_data = get_option($option->option_name);
            if ($batch_data) {
                $batches[] = $batch_data;
            }
        }

        return $batches;
    }
}