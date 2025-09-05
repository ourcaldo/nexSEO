<?php
/**
 * Main plugin class that orchestrates all components
 */

if (!defined('ABSPATH')) {
    exit;
}

class NexJob_SEO_Plugin {
    
    /**
     * Plugin components
     */
    private $settings;
    private $logger;
    private $post_processor;
    private $cron_manager;
    private $admin;
    private $ajax_handlers;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_components();
        $this->init_hooks();
    }
    
    /**
     * Initialize plugin components
     */
    private function init_components() {
        // Initialize settings first as other components depend on it
        $this->settings = new NexJob_SEO_Settings();
        
        // Initialize logger
        $this->logger = new NexJob_SEO_Logger();
        
        // Initialize post processor with dependencies
        $this->post_processor = new NexJob_SEO_Post_Processor($this->settings, $this->logger);
        
        // Initialize cron manager with dependencies
        $this->cron_manager = new NexJob_SEO_Cron_Manager($this->settings, $this->logger, $this->post_processor);
        
        // Initialize admin interface with dependencies (only in admin)
        if (is_admin()) {
            $this->admin = new NexJob_SEO_Admin($this->settings, $this->logger, $this->post_processor, $this->cron_manager);
            $this->ajax_handlers = new NexJob_SEO_Ajax_Handlers($this->settings, $this->logger);
        }
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Hook for manual save processing - dynamic for all configured post types
        $post_types = $this->settings->get('post_types');
        foreach ($post_types as $post_type) {
            add_action("save_post_{$post_type}", array($this->post_processor, 'process_post_manual'), 20);
        }
    }
    
    /**
     * Plugin activation
     */
    public static function activate() {
        // Create logger instance for activation
        $logger = new NexJob_SEO_Logger();
        $logger->create_log_table();
        $logger->log('Plugin activated', 'info');
        
        // Initialize cron manager for activation
        $settings = new NexJob_SEO_Settings();
        $post_processor = new NexJob_SEO_Post_Processor($settings, $logger);
        $cron_manager = new NexJob_SEO_Cron_Manager($settings, $logger, $post_processor);
        $cron_manager->setup_cron_job();
    }
    
    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        // Create logger instance for deactivation
        $logger = new NexJob_SEO_Logger();
        $logger->log('Plugin deactivated', 'info');
        
        // Clear cron job
        wp_clear_scheduled_hook('nexjob_process_seo_cron');
    }
    
    /**
     * Get plugin component instances
     */
    public function get_settings() {
        return $this->settings;
    }
    
    public function get_logger() {
        return $this->logger;
    }
    
    public function get_post_processor() {
        return $this->post_processor;
    }
    
    public function get_cron_manager() {
        return $this->cron_manager;
    }
    
    public function get_admin() {
        return $this->admin;
    }
}