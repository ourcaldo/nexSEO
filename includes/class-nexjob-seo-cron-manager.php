<?php
/**
 * Cron manager class for scheduled tasks
 */

if (!defined('ABSPATH')) {
    exit;
}

class NexJob_SEO_Cron_Manager {
    
    /**
     * Dependencies
     */
    private $settings;
    private $logger;
    private $post_processor;
    
    /**
     * Cron hook name
     */
    const CRON_HOOK = 'nexjob_process_seo_cron';
    
    /**
     * Constructor
     */
    public function __construct($settings, $logger, $post_processor) {
        $this->settings = $settings;
        $this->logger = $logger;
        $this->post_processor = $post_processor;
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('wp', array($this, 'setup_cron_job'));
        add_action(self::CRON_HOOK, array($this, 'process_posts_via_cron'));
        add_filter('cron_schedules', array($this, 'add_cron_intervals'));
    }
    
    /**
     * Setup cron job with configurable interval
     */
    public function setup_cron_job() {
        // Clear existing cron job
        wp_clear_scheduled_hook(self::CRON_HOOK);
        
        // Schedule new cron job with current interval setting
        $cron_interval = $this->settings->get('cron_interval');
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), $cron_interval, self::CRON_HOOK);
            $this->logger->log("Cron job scheduled with interval: {$cron_interval}", 'info');
        }
    }
    
    /**
     * Add custom cron intervals
     */
    public function add_cron_intervals($schedules) {
        $schedules['every_minute'] = array(
            'interval' => 60,
            'display'  => __('Every Minute', 'nexjob-seo')
        );
        $schedules['every_two_minutes'] = array(
            'interval' => 120,
            'display'  => __('Every 2 Minutes', 'nexjob-seo')
        );
        $schedules['every_five_minutes'] = array(
            'interval' => 300,
            'display'  => __('Every 5 Minutes', 'nexjob-seo')
        );
        $schedules['every_ten_minutes'] = array(
            'interval' => 600,
            'display'  => __('Every 10 Minutes', 'nexjob-seo')
        );
        $schedules['every_fifteen_minutes'] = array(
            'interval' => 900,
            'display'  => __('Every 15 Minutes', 'nexjob-seo')
        );
        $schedules['every_thirty_minutes'] = array(
            'interval' => 1800,
            'display'  => __('Every 30 Minutes', 'nexjob-seo')
        );
        
        return $schedules;
    }
    
    /**
     * Process posts via cron job
     */
    public function process_posts_via_cron($force_reprocess = false) {
        $this->logger->log('Cron job started' . ($force_reprocess ? ' (force reprocess mode)' : ''), 'info', null, null, array(
            'configured_post_types' => $this->settings->get('post_types'),
            'cron_interval' => $this->settings->get('cron_interval'),
            'force_reprocess' => $force_reprocess
        ));
        
        $total_processed = 0;
        $total_skipped = 0;
        
        // Process each configured post type
        $post_types = $this->settings->get('post_types');
        $posts_per_batch = $this->settings->get('posts_per_batch');
        $max_posts_per_run = $this->settings->get('max_posts_per_run');
        
        foreach ($post_types as $post_type) {
            $posts = get_posts(array(
                'post_type'      => $post_type,
                'post_status'    => 'publish',
                'posts_per_page' => $posts_per_batch,
                'orderby'        => 'date',
                'order'          => 'DESC'
            ));
            
            $processed_count = 0;
            $skipped_count = 0;
            
            foreach ($posts as $post) {
                if ($this->post_processor->needs_seo_processing($post->ID, $force_reprocess)) {
                    $this->post_processor->process_single_post($post->ID, true, $force_reprocess);
                    $processed_count++;
                    $total_processed++;
                    
                    // Limit processing to avoid timeout (unless force reprocessing)
                    if (!$force_reprocess && $processed_count >= $max_posts_per_run) {
                        break;
                    }
                } else {
                    $skipped_count++;
                    $total_skipped++;
                }
            }
            
            // Log processing summary for this post type
            if (!empty($posts)) {
                $this->logger->log("Post type '$post_type' processed - Processed: $processed_count, Skipped: $skipped_count", 'info', null, null, array(
                    'post_type' => $post_type,
                    'total_posts_checked' => count($posts),
                    'processed' => $processed_count,
                    'skipped' => $skipped_count,
                    'force_reprocess' => $force_reprocess
                ));
            }
        }
        
        // Log overall processing summary
        $this->logger->log("Cron job completed - Total Processed: $total_processed, Total Skipped: $total_skipped", 'info', null, null, array(
            'total_processed' => $total_processed,
            'total_skipped' => $total_skipped,
            'processed_post_types' => $post_types,
            'force_reprocess' => $force_reprocess
        ));
    }
    
    /**
     * Get cron job information
     */
    public function get_cron_info() {
        $next_run = wp_next_scheduled(self::CRON_HOOK);
        $schedules = wp_get_schedules();
        
        $current_interval = $this->settings->get('cron_interval');
        $interval_name = isset($schedules[$current_interval]) 
            ? $schedules[$current_interval]['display']
            : $current_interval;
        
        return array(
            'next_run' => $next_run ? date('Y-m-d H:i:s', $next_run) : __('Not scheduled', 'nexjob-seo'),
            'interval' => $interval_name,
            'hook' => self::CRON_HOOK,
            'is_scheduled' => (bool) $next_run
        );
    }
    
    /**
     * Force reprocess all posts
     */
    public function force_reprocess_all() {
        $this->logger->log('Force reprocess all posts initiated', 'info', null, null, array(
            'configured_post_types' => $this->settings->get('post_types')
        ));
        
        // Delete all processed flags for configured post types
        $post_types = $this->settings->get('post_types');
        foreach ($post_types as $post_type) {
            $post_ids = get_posts(array(
                'post_type' => $post_type,
                'post_status' => 'publish',
                'fields' => 'ids',
                'posts_per_page' => -1
            ));
            
            foreach ($post_ids as $post_id) {
                delete_post_meta($post_id, '_nexjob_seo_processed');
                delete_post_meta($post_id, '_nexjob_seo_processed_date');
            }
            
            $this->logger->log("Cleared processing flags for {$post_type} posts", 'info', null, null, array(
                'post_type' => $post_type,
                'posts_cleared' => count($post_ids)
            ));
        }
        
        // Process posts with force reprocess enabled
        $this->process_posts_via_cron(true);
    }
    
    /**
     * Clear scheduled cron job
     */
    public function clear_cron_job() {
        $result = wp_clear_scheduled_hook(self::CRON_HOOK);
        if ($result) {
            $this->logger->log('Cron job cleared successfully', 'info');
        }
        return $result;
    }
}