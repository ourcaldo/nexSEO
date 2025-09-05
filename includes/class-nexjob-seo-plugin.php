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
    
    // Webhook components
    private $webhook_data;
    private $webhook_manager;
    private $field_mapper;
    private $webhook_processor;
    private $webhook_admin;
    
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
        
        // Initialize webhook components
        $this->webhook_data = new NexJob_SEO_Webhook_Data($this->logger);
        $this->webhook_manager = new NexJob_SEO_Webhook_Manager($this->logger, $this->webhook_data);
        $this->field_mapper = new NexJob_SEO_Field_Mapper($this->logger);
        $this->webhook_processor = new NexJob_SEO_Webhook_Processor($this->logger, $this->webhook_manager, $this->webhook_data, $this->field_mapper);
        
        // Initialize admin interface with dependencies (only in admin)
        if (is_admin()) {
            $this->admin = new NexJob_SEO_Admin($this->settings, $this->logger, $this->post_processor, $this->cron_manager);
            $this->ajax_handlers = new NexJob_SEO_Ajax_Handlers($this->settings, $this->logger);
            $this->webhook_admin = new NexJob_SEO_Webhook_Admin($this->logger, $this->webhook_manager, $this->webhook_data, $this->field_mapper, $this->webhook_processor);
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
        
        // Create webhook database tables
        NexJob_SEO_Webhook_Database::create_tables();
        
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
    
    public function get_webhook_manager() {
        return $this->webhook_manager;
    }
    
    public function get_webhook_data() {
        return $this->webhook_data;
    }
    
    public function get_field_mapper() {
        return $this->field_mapper;
    }
    
    public function get_webhook_processor() {
        return $this->webhook_processor;
    }
    
    public function get_webhook_admin() {
        return $this->webhook_admin;
    }
}