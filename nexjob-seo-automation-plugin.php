<?php
/**
 * Plugin Name: NexJob SEO Automation
 * Plugin URI: https://nexjob.com
 * Description: Automated SEO meta title, description, and slug generation for job posts with RankMath integration
 * Version: 1.1.0
 * Author: NexJob Team
 * License: GPL v2 or later
 * Text Domain: nexjob-seo
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('NEXJOB_SEO_VERSION', '1.1.0');
define('NEXJOB_SEO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NEXJOB_SEO_PLUGIN_URL', plugin_dir_url(__FILE__));

class NexJobSEOAutomationPlugin {
    
    private $log_table;
    private $settings;
    
    public function __construct() {
        global $wpdb;
        $this->log_table = $wpdb->prefix . 'nexjob_seo_logs';
        
        // Load settings
        $this->load_settings();
        
        // Plugin activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Initialize plugin
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    /**
     * Load plugin settings
     */
    private function load_settings() {
        $defaults = array(
            'post_types' => array('lowongan-kerja'),
            'cron_interval' => 'every_five_minutes',
            'posts_per_batch' => 20,
            'max_posts_per_run' => 10,
            'required_fields' => array(
                'nexjob_nama_perusahaan',
                'nexjob_lokasi_kota'
            )
        );
        
        $this->settings = wp_parse_args(get_option('nexjob_seo_settings', array()), $defaults);
    }
    
    /**
     * Save plugin settings
     */
    private function save_settings() {
        update_option('nexjob_seo_settings', $this->settings);
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Hook for manual save (backup) - dynamic for all configured post types
        foreach ($this->settings['post_types'] as $post_type) {
            add_action("save_post_{$post_type}", array($this, 'process_post_manual'), 20);
        }
        
        // Setup cron job
        add_action('wp', array($this, 'setup_cron_job'));
        add_action('nexjob_process_seo_cron', array($this, 'process_posts_via_cron'));
        
        // Add custom cron intervals
        add_filter('cron_schedules', array($this, 'add_cron_intervals'));
        
        // Admin menu and pages
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_admin_actions'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // Admin notices
        add_action('admin_notices', array($this, 'show_admin_notices'));
        
        // Add manual process buttons to post list page
        foreach ($this->settings['post_types'] as $post_type) {
            add_filter("views_edit-{$post_type}", array($this, 'add_manual_process_buttons'));
        }
        
        // AJAX handlers
        add_action('wp_ajax_nexjob_clear_logs', array($this, 'ajax_clear_logs'));
        add_action('wp_ajax_nexjob_get_logs', array($this, 'ajax_get_logs'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        $this->create_log_table();
        $this->log('Plugin activated', 'info');
        
        // Setup cron job
        $this->setup_cron_job();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        $this->log('Plugin deactivated', 'info');
        wp_clear_scheduled_hook('nexjob_process_seo_cron');
    }
    
    /**
     * Create log table
     */
    private function create_log_table() {
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
    private function log($message, $level = 'info', $post_id = null, $post_title = null, $context = null) {
        global $wpdb;
        
        $post_type = null;
        if ($post_id) {
            $post_type = get_post_type($post_id);
        }
        
        $wpdb->insert(
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
    }
    
    /**
     * Setup cron job with configurable interval
     */
    public function setup_cron_job() {
        // Clear existing cron job
        wp_clear_scheduled_hook('nexjob_process_seo_cron');
        
        // Schedule new cron job with current interval setting
        if (!wp_next_scheduled('nexjob_process_seo_cron')) {
            wp_schedule_event(time(), $this->settings['cron_interval'], 'nexjob_process_seo_cron');
            $this->log("Cron job scheduled with interval: {$this->settings['cron_interval']}", 'info');
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
     * Check if post needs SEO processing with detailed field checking
     */
    private function needs_seo_processing($post_id, $force_reprocess = false) {
        $post_title = get_the_title($post_id);
        $post_type = get_post_type($post_id);
        
        // Check if post type is configured for processing
        if (!in_array($post_type, $this->settings['post_types'])) {
            return false;
        }
        
        // If force reprocess is enabled, always return true
        if ($force_reprocess) {
            return true;
        }
        
        // Detailed field checking
        $missing_fields = array();
        $field_values = array();
        
        // Check post title
        if (empty($post_title)) {
            $missing_fields[] = 'post_title';
        }
        $field_values['post_title'] = $post_title;
        
        // Check required custom fields
        foreach ($this->settings['required_fields'] as $field_key) {
            $field_value = get_post_meta($post_id, $field_key, true);
            $field_values[$field_key] = $field_value;
            
            if (empty($field_value)) {
                $missing_fields[] = $field_key;
            }
        }
        
        // If any required fields are missing, log details and return false
        if (!empty($missing_fields)) {
            $this->log(
                "Post ID $post_id skipped - missing required fields: " . implode(', ', $missing_fields), 
                'warning', 
                $post_id, 
                $post_title, 
                array(
                    'missing_fields' => $missing_fields,
                    'all_field_values' => $field_values,
                    'post_type' => $post_type
                )
            );
            return false;
        }
        
        // Get site name for title generation
        $site_name = get_bloginfo('name');
        
        // Get current SEO data from RankMath
        $current_title = get_post_meta($post_id, 'rank_math_title', true);
        $current_description = get_post_meta($post_id, 'rank_math_description', true);
        
        // Generate expected title based on available fields
        $expected_title = $this->generate_seo_title($post_id, $field_values, $site_name);
        
        // Check if SEO title is missing or doesn't match expected format
        if (empty($current_title) || $current_title !== $expected_title) {
            $this->log("Post ID $post_id needs processing - title mismatch", 'info', $post_id, $post_title, array(
                'current_title' => $current_title,
                'expected_title' => $expected_title,
                'post_type' => $post_type
            ));
            return true;
        }
        
        // Check if description is missing
        if (empty($current_description)) {
            $this->log("Post ID $post_id needs processing - missing description", 'info', $post_id, $post_title, array(
                'post_type' => $post_type
            ));
            return true;
        }
        
        // Check if slug needs updating
        $current_post = get_post($post_id);
        $expected_slug = $this->generate_slug($post_id, $field_values);
        $unique_expected_slug = wp_unique_post_slug($expected_slug, $post_id, 'publish', $post_type, 0);
        
        if ($current_post->post_name !== $unique_expected_slug) {
            $this->log("Post ID $post_id needs processing - slug mismatch", 'info', $post_id, $post_title, array(
                'current_slug' => $current_post->post_name,
                'expected_slug' => $unique_expected_slug,
                'post_type' => $post_type
            ));
            return true;
        }
        
        return false;
    }
    
    /**
     * Generate SEO title based on post type and available fields
     */
    private function generate_seo_title($post_id, $field_values, $site_name) {
        $post_title = $field_values['post_title'];
        $post_type = get_post_type($post_id);
        
        // Default format for job posts (lowongan-kerja)
        if ($post_type === 'lowongan-kerja') {
            $nama_perusahaan = isset($field_values['nexjob_nama_perusahaan']) ? $field_values['nexjob_nama_perusahaan'] : '';
            $lokasi_kota = isset($field_values['nexjob_lokasi_kota']) ? $field_values['nexjob_lokasi_kota'] : '';
            
            return "Lowongan Kerja {$post_title} {$nama_perusahaan} di {$lokasi_kota} - {$site_name}";
        }
        
        // Generic format for other post types
        return "{$post_title} - {$site_name}";
    }
    
    /**
     * Process posts via cron job
     */
    public function process_posts_via_cron($force_reprocess = false) {
        $this->log('Cron job started' . ($force_reprocess ? ' (force reprocess mode)' : ''), 'info', null, null, array(
            'configured_post_types' => $this->settings['post_types'],
            'cron_interval' => $this->settings['cron_interval'],
            'force_reprocess' => $force_reprocess
        ));
        
        $total_processed = 0;
        $total_skipped = 0;
        
        // Process each configured post type
        foreach ($this->settings['post_types'] as $post_type) {
            $posts = get_posts(array(
                'post_type'      => $post_type,
                'post_status'    => 'publish',
                'posts_per_page' => $this->settings['posts_per_batch'],
                'orderby'        => 'date',
                'order'          => 'DESC'
            ));
            
            $processed_count = 0;
            $skipped_count = 0;
            
            foreach ($posts as $post) {
                if ($this->needs_seo_processing($post->ID, $force_reprocess)) {
                    $this->process_single_post($post->ID, true, $force_reprocess);
                    $processed_count++;
                    $total_processed++;
                    
                    // Limit processing to avoid timeout (unless force reprocessing)
                    if (!$force_reprocess && $processed_count >= $this->settings['max_posts_per_run']) {
                        break;
                    }
                } else {
                    $skipped_count++;
                    $total_skipped++;
                }
            }
            
            // Log processing summary for this post type
            if (!empty($posts)) {
                $this->log("Post type '$post_type' processed - Processed: $processed_count, Skipped: $skipped_count", 'info', null, null, array(
                    'post_type' => $post_type,
                    'total_posts_checked' => count($posts),
                    'processed' => $processed_count,
                    'skipped' => $skipped_count,
                    'force_reprocess' => $force_reprocess
                ));
            }
        }
        
        // Log overall processing summary
        $this->log("Cron job completed - Total Processed: $total_processed, Total Skipped: $total_skipped", 'info', null, null, array(
            'total_processed' => $total_processed,
            'total_skipped' => $total_skipped,
            'processed_post_types' => $this->settings['post_types'],
            'force_reprocess' => $force_reprocess
        ));
    }
    
    /**
     * Process post manually (when saving)
     */
    public function process_post_manual($post_id) {
        // Skip if it's autosave, revision, or not published
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;
        if (get_post_status($post_id) !== 'publish') return;
        
        $post_type = get_post_type($post_id);
        $this->log("Manual processing triggered for post ID $post_id (type: $post_type)", 'info', $post_id, get_the_title($post_id), array(
            'post_type' => $post_type
        ));
        
        // Always process manually saved posts (ignore the needs_seo_processing check for manual saves)
        $this->process_single_post($post_id, false, true);
    }
    
    /**
     * Process a single post for SEO and slug
     */
    public function process_single_post($post_id, $is_cron = false, $force_reprocess = false) {
        $post_type = get_post_type($post_id);
        
        // Verify post type is configured
        if (!in_array($post_type, $this->settings['post_types'])) {
            $this->log("Post ID $post_id skipped - post type '$post_type' not configured for processing", 'warning', $post_id, get_the_title($post_id), array(
                'post_type' => $post_type,
                'configured_types' => $this->settings['post_types']
            ));
            return;
        }
        
        // Get post data
        $post_title = get_the_title($post_id);
        $post_content = get_post_field('post_content', $post_id);
        $site_name = get_bloginfo('name');
        
        // Collect all required field values
        $field_values = array('post_title' => $post_title);
        $missing_fields = array();
        
        // Check post title
        if (empty($post_title)) {
            $missing_fields[] = 'post_title';
        }
        
        // Check required custom fields
        foreach ($this->settings['required_fields'] as $field_key) {
            $field_value = get_post_meta($post_id, $field_key, true);
            $field_values[$field_key] = $field_value;
            
            if (empty($field_value)) {
                $missing_fields[] = $field_key;
            }
        }
        
        // If required fields are missing, create them with default values or log detailed error
        if (!empty($missing_fields)) {
            $this->log("Post ID $post_id processing failed - missing required fields: " . implode(', ', $missing_fields), 'error', $post_id, $post_title, array(
                'missing_fields' => $missing_fields,
                'all_field_values' => $field_values,
                'post_type' => $post_type,
                'required_fields' => $this->settings['required_fields']
            ));
            
            // Try to create missing fields with default values for certain fields
            foreach ($missing_fields as $missing_field) {
                if ($missing_field === 'nexjob_nama_perusahaan') {
                    $default_value = 'Unknown Company';
                    update_post_meta($post_id, $missing_field, $default_value);
                    $field_values[$missing_field] = $default_value;
                    $this->log("Created missing field '$missing_field' with default value '$default_value' for post ID $post_id", 'info', $post_id, $post_title);
                } elseif ($missing_field === 'nexjob_lokasi_kota') {
                    $default_value = 'Unknown Location';
                    update_post_meta($post_id, $missing_field, $default_value);
                    $field_values[$missing_field] = $default_value;
                    $this->log("Created missing field '$missing_field' with default value '$default_value' for post ID $post_id", 'info', $post_id, $post_title);
                }
            }
            
            // If we still have missing fields after trying to create defaults, mark and return
            $still_missing = array();
            foreach ($missing_fields as $field) {
                if (empty($field_values[$field])) {
                    $still_missing[] = $field;
                }
            }
            
            if (!empty($still_missing)) {
                if ($is_cron) {
                    // Mark as processed but incomplete to avoid repeated processing
                    update_post_meta($post_id, '_nexjob_seo_processed', 'incomplete');
                    update_post_meta($post_id, '_nexjob_seo_processed_date', current_time('mysql'));
                }
                return;
            }
        }
        
        try {
            // Generate SEO Title
            $seo_title = $this->generate_seo_title($post_id, $field_values, $site_name);
            
            // Generate SEO Description using your exact logic
            $meta_description = $this->generate_meta_description($post_content);
            
            // Generate and update slug
            $new_slug = $this->generate_slug($post_id, $field_values);
            $this->update_post_slug($post_id, $new_slug);
            
            // Update SEO meta - always update regardless of existing content
            update_post_meta($post_id, 'rank_math_title', $seo_title);
            if (!empty($meta_description)) {
                update_post_meta($post_id, 'rank_math_description', $meta_description);
            }
            
            // Mark as processed with timestamp
            update_post_meta($post_id, '_nexjob_seo_processed', 'complete');
            update_post_meta($post_id, '_nexjob_seo_processed_date', current_time('mysql'));
            
            // Log successful processing
            $this->log("Post ID $post_id processed successfully" . ($force_reprocess ? ' (force reprocess)' : ''), 'success', $post_id, $post_title, array(
                'post_type' => $post_type,
                'seo_title' => $seo_title,
                'meta_description' => $meta_description,
                'slug' => $new_slug,
                'field_values' => $field_values,
                'force_reprocess' => $force_reprocess
            ));
            
        } catch (Exception $e) {
            $this->log("Post ID $post_id processing failed with error: " . $e->getMessage(), 'error', $post_id, $post_title, array(
                'post_type' => $post_type,
                'error_trace' => $e->getTraceAsString()
            ));
        }
    }
    
    /**
     * Generate meta description from content using your exact logic
     */
    private function generate_meta_description($post_content) {
        // Return empty if no content
        if (empty($post_content)) {
            return '';
        }
        
        // === Ambil konten sebelum <h2> ===
        $content_before_h2 = '';
        
        // Coba cari tag H2
        $h2_pos = stripos($post_content, '<h2');
        if ($h2_pos !== false) {
            $content_before_h2 = substr($post_content, 0, $h2_pos);
        } else {
            $content_before_h2 = $post_content;
        }

        // Bersihkan tag HTML
        $content_clean = wp_strip_all_tags($content_before_h2);
        $content_clean = trim($content_clean);
        
        if (empty($content_clean)) {
            return '';
        }

        // Split berdasarkan titik (., !, ?)
        $sentences = preg_split('/(?<=[.?!])\s+/', $content_clean, -1, PREG_SPLIT_NO_EMPTY);

        $meta_description = '';
        if (!empty($sentences[0])) {
            $meta_description = trim($sentences[0]);
            if (!empty($sentences[1])) {
                $meta_description .= ' ' . trim($sentences[1]);
            }
        }
        
        return $meta_description;
    }
    
    /**
     * Generate slug based on post type and available fields
     */
    private function generate_slug($post_id, $field_values) {
        $post_title = $field_values['post_title'];
        $post_type = get_post_type($post_id);
        
        // Default format for job posts
        if ($post_type === 'lowongan-kerja') {
            $nama_perusahaan = isset($field_values['nexjob_nama_perusahaan']) ? $field_values['nexjob_nama_perusahaan'] : '';
            $lokasi_kota = isset($field_values['nexjob_lokasi_kota']) ? $field_values['nexjob_lokasi_kota'] : '';
            
            $slug_string = $post_title . ' ' . $nama_perusahaan . ' ' . $lokasi_kota;
        } else {
            // Generic format for other post types
            $slug_string = $post_title;
        }
        
        // Convert to lowercase
        $slug_string = strtolower($slug_string);
        
        // Remove special characters and replace with spaces
        $slug_string = preg_replace('/[^a-z0-9\s\-]/', '', $slug_string);
        
        // Replace multiple spaces with single space
        $slug_string = preg_replace('/\s+/', ' ', $slug_string);
        
        // Replace spaces with hyphens
        $slug_string = str_replace(' ', '-', trim($slug_string));
        
        // Remove multiple consecutive hyphens
        $slug_string = preg_replace('/-+/', '-', $slug_string);
        
        // Remove leading/trailing hyphens
        $slug_string = trim($slug_string, '-');
        
        // Limit length to 200 characters
        if (strlen($slug_string) > 200) {
            $slug_string = substr($slug_string, 0, 200);
            $slug_string = rtrim($slug_string, '-');
        }
        
        return $slug_string;
    }
    
    /**
     * Update post slug
     */
    private function update_post_slug($post_id, $new_slug) {
        // Get current post
        $current_post = get_post($post_id);
        if (!$current_post) return;
        
        $post_type = get_post_type($post_id);
        
        // Make sure slug is unique
        $unique_slug = wp_unique_post_slug($new_slug, $post_id, 'publish', $post_type, 0);
        
        // Check if slug needs updating
        if ($current_post->post_name === $unique_slug) return;
        
        // Update post slug
        $updated_post = array(
            'ID'            => $post_id,
            'post_name'     => $unique_slug
        );
        
        // Remove the hook temporarily to avoid infinite loop
        remove_action("save_post_{$post_type}", array($this, 'process_post_manual'), 20);
        
        wp_update_post($updated_post);
        
        // Re-add the hook
        add_action("save_post_{$post_type}", array($this, 'process_post_manual'), 20);
        
        $this->log("Slug updated for post ID $post_id", 'info', $post_id, get_the_title($post_id), array(
            'post_type' => $post_type,
            'old_slug' => $current_post->post_name,
            'new_slug' => $unique_slug
        ));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('NexJob SEO', 'nexjob-seo'),
            __('NexJob SEO', 'nexjob-seo'),
            'manage_options',
            'nexjob-seo',
            array($this, 'admin_page'),
            'dashicons-search',
            30
        );
        
        add_submenu_page(
            'nexjob-seo',
            __('Settings', 'nexjob-seo'),
            __('Settings', 'nexjob-seo'),
            'manage_options',
            'nexjob-seo-settings',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'nexjob-seo',
            __('Logs', 'nexjob-seo'),
            __('Logs', 'nexjob-seo'),
            'manage_options',
            'nexjob-seo-logs',
            array($this, 'logs_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('nexjob_seo_settings_group', 'nexjob_seo_settings', array($this, 'sanitize_settings'));
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // Post types
        if (isset($input['post_types']) && is_array($input['post_types'])) {
            $sanitized['post_types'] = array_map('sanitize_text_field', $input['post_types']);
        } else {
            $sanitized['post_types'] = array('lowongan-kerja');
        }
        
        // Cron interval
        $sanitized['cron_interval'] = sanitize_text_field($input['cron_interval']);
        
        // Numeric settings
        $sanitized['posts_per_batch'] = intval($input['posts_per_batch']);
        $sanitized['max_posts_per_run'] = intval($input['max_posts_per_run']);
        
        // Required fields
        if (isset($input['required_fields']) && is_array($input['required_fields'])) {
            $sanitized['required_fields'] = array_map('sanitize_text_field', $input['required_fields']);
        } else {
            $sanitized['required_fields'] = array('nexjob_nama_perusahaan', 'nexjob_lokasi_kota');
        }
        
        return $sanitized;
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        if (isset($_POST['submit'])) {
            $this->settings = $this->sanitize_settings($_POST['nexjob_seo_settings']);
            $this->save_settings();
            
            // Reschedule cron job with new interval
            $this->setup_cron_job();
            
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully!', 'nexjob-seo') . '</p></div>';
        }
        
        // Get all post types
        $post_types = get_post_types(array('public' => true), 'objects');
        $cpt_post_types = get_post_types(array('public' => true, '_builtin' => false), 'objects');
        
        // Get available cron intervals
        $cron_schedules = wp_get_schedules();
        
        ?>
        <div class="wrap">
            <h1><?php _e('NexJob SEO Settings', 'nexjob-seo'); ?></h1>
            
            <form method="post" action="">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Post Types to Process', 'nexjob-seo'); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><?php _e('Post Types', 'nexjob-seo'); ?></legend>
                                
                                <h4><?php _e('Built-in Post Types', 'nexjob-seo'); ?></h4>
                                <?php foreach ($post_types as $post_type): ?>
                                    <?php if ($post_type->_builtin): ?>
                                        <label>
                                            <input type="checkbox" name="nexjob_seo_settings[post_types][]" value="<?php echo esc_attr($post_type->name); ?>" 
                                                <?php checked(in_array($post_type->name, $this->settings['post_types'])); ?>>
                                            <?php echo esc_html($post_type->label); ?> (<?php echo esc_html($post_type->name); ?>)
                                        </label><br>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                
                                <?php if (!empty($cpt_post_types)): ?>
                                    <h4><?php _e('Custom Post Types', 'nexjob-seo'); ?></h4>
                                    <?php foreach ($cpt_post_types as $post_type): ?>
                                        <label>
                                            <input type="checkbox" name="nexjob_seo_settings[post_types][]" value="<?php echo esc_attr($post_type->name); ?>" 
                                                <?php checked(in_array($post_type->name, $this->settings['post_types'])); ?>>
                                            <?php echo esc_html($post_type->label); ?> (<?php echo esc_html($post_type->name); ?>)
                                        </label><br>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </fieldset>
                            <p class="description"><?php _e('Select which post types should be processed for SEO automation.', 'nexjob-seo'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Cron Interval', 'nexjob-seo'); ?></th>
                        <td>
                            <select name="nexjob_seo_settings[cron_interval]">
                                <?php foreach ($cron_schedules as $key => $schedule): ?>
                                    <?php if (strpos($key, 'every_') === 0 || $key === 'hourly' || $key === 'daily'): ?>
                                        <option value="<?php echo esc_attr($key); ?>" <?php selected($this->settings['cron_interval'], $key); ?>>
                                            <?php echo esc_html($schedule['display']); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e('How often should the plugin check for posts that need SEO processing?', 'nexjob-seo'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Posts Per Batch', 'nexjob-seo'); ?></th>
                        <td>
                            <input type="number" name="nexjob_seo_settings[posts_per_batch]" value="<?php echo esc_attr($this->settings['posts_per_batch']); ?>" min="1" max="100">
                            <p class="description"><?php _e('How many posts to check in each cron run.', 'nexjob-seo'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Max Posts Per Run', 'nexjob-seo'); ?></th>
                        <td>
                            <input type="number" name="nexjob_seo_settings[max_posts_per_run]" value="<?php echo esc_attr($this->settings['max_posts_per_run']); ?>" min="1" max="50">
                            <p class="description"><?php _e('Maximum number of posts to actually process in each cron run (to avoid timeouts).', 'nexjob-seo'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Required Fields', 'nexjob-seo'); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><?php _e('Required Fields', 'nexjob-seo'); ?></legend>
                                <input type="text" name="nexjob_seo_settings[required_fields][]" value="nexjob_nama_perusahaan" readonly> <?php _e('Company Name', 'nexjob-seo'); ?><br>
                                <input type="text" name="nexjob_seo_settings[required_fields][]" value="nexjob_lokasi_kota" readonly> <?php _e('Location/City', 'nexjob-seo'); ?><br>
                                <p class="description"><?php _e('These are the required custom fields that must be present for SEO processing. Currently fixed fields.', 'nexjob-seo'); ?></p>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        $stats = $this->get_processing_stats();
        $cron_info = $this->get_cron_info();
        ?>
        <div class="wrap">
            <h1><?php _e('NexJob SEO Automation', 'nexjob-seo'); ?></h1>
            
            <div class="card">
                <h2><?php _e('Current Configuration', 'nexjob-seo'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php _e('Configured Post Types', 'nexjob-seo'); ?></th>
                        <td><?php echo implode(', ', $this->settings['post_types']); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Cron Interval', 'nexjob-seo'); ?></th>
                        <td><?php echo $cron_info['interval']; ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Posts Per Batch', 'nexjob-seo'); ?></th>
                        <td><?php echo $this->settings['posts_per_batch']; ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Max Posts Per Run', 'nexjob-seo'); ?></th>
                        <td><?php echo $this->settings['max_posts_per_run']; ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="card">
                <h2><?php _e('Processing Statistics', 'nexjob-seo'); ?></h2>
                <table class="form-table">
                    <?php foreach ($stats as $post_type => $type_stats): ?>
                        <tr>
                            <th colspan="2"><strong><?php echo esc_html(ucfirst($post_type)); ?></strong></th>
                        </tr>
                        <tr>
                            <th style="padding-left: 20px;"><?php _e('Total Posts', 'nexjob-seo'); ?></th>
                            <td><?php echo $type_stats['total']; ?></td>
                        </tr>
                        <tr>
                            <th style="padding-left: 20px;"><?php _e('Processed Posts', 'nexjob-seo'); ?></th>
                            <td><?php echo $type_stats['processed']; ?></td>
                        </tr>
                        <tr>
                            <th style="padding-left: 20px;"><?php _e('Remaining Posts', 'nexjob-seo'); ?></th>
                            <td><?php echo $type_stats['remaining']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            
            <div class="card">
                <h2><?php _e('Cron Job Status', 'nexjob-seo'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php _e('Next Scheduled Run', 'nexjob-seo'); ?></th>
                        <td><?php echo $cron_info['next_run']; ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Interval', 'nexjob-seo'); ?></th>
                        <td><?php echo $cron_info['interval']; ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="card">
                <h2><?php _e('Manual Actions', 'nexjob-seo'); ?></h2>
                <p>
                    <a href="<?php echo add_query_arg('action', 'nexjob_manual_process'); ?>" class="button button-primary">
                        <?php _e('Process Posts Now', 'nexjob-seo'); ?>
                    </a>
                    <a href="<?php echo add_query_arg('action', 'nexjob_force_reprocess'); ?>" class="button button-secondary" onclick="return confirm('<?php _e('This will reprocess ALL posts, including those already processed. Continue?', 'nexjob-seo'); ?>')">
                        <?php _e('Force Regenerate All Posts', 'nexjob-seo'); ?>
                    </a>
                </p>
                <p class="description">
                    <strong><?php _e('Process Posts Now:', 'nexjob-seo'); ?></strong> <?php _e('Processes only posts that need SEO updates (missing or incorrect data).', 'nexjob-seo'); ?><br>
                    <strong><?php _e('Force Regenerate All Posts:', 'nexjob-seo'); ?></strong> <?php _e('Reprocesses ALL posts regardless of their current status. Use this if you want to regenerate SEO data for all posts.', 'nexjob-seo'); ?>
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Logs page
     */
    public function logs_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('NexJob SEO Logs', 'nexjob-seo'); ?></h1>
            
            <div class="tablenav top">
                <div class="alignleft actions">
                    <select id="log-level-filter">
                        <option value=""><?php _e('All Levels', 'nexjob-seo'); ?></option>
                        <option value="error"><?php _e('Error', 'nexjob-seo'); ?></option>
                        <option value="warning"><?php _e('Warning', 'nexjob-seo'); ?></option>
                        <option value="info"><?php _e('Info', 'nexjob-seo'); ?></option>
                        <option value="success"><?php _e('Success', 'nexjob-seo'); ?></option>
                    </select>
                    <select id="post-type-filter">
                        <option value=""><?php _e('All Post Types', 'nexjob-seo'); ?></option>
                        <?php foreach ($this->settings['post_types'] as $post_type): ?>
                            <option value="<?php echo esc_attr($post_type); ?>"><?php echo esc_html($post_type); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="button" id="filter-logs"><?php _e('Filter', 'nexjob-seo'); ?></button>
                </div>
                <div class="alignright actions">
                    <button type="button" class="button" id="refresh-logs"><?php _e('Refresh', 'nexjob-seo'); ?></button>
                    <button type="button" class="button button-secondary" id="clear-logs"><?php _e('Clear Logs', 'nexjob-seo'); ?></button>
                </div>
            </div>
            
            <div id="logs-container">
                <?php $this->display_logs(); ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#refresh-logs, #filter-logs').click(function() {
                var level = $('#log-level-filter').val();
                var post_type = $('#post-type-filter').val();
                $.post(ajaxurl, {
                    action: 'nexjob_get_logs',
                    level: level,
                    post_type: post_type,
                    nonce: '<?php echo wp_create_nonce('nexjob_logs'); ?>'
                }, function(response) {
                    $('#logs-container').html(response);
                });
            });
            
            $('#clear-logs').click(function() {
                if (confirm('<?php _e('Are you sure you want to clear all logs?', 'nexjob-seo'); ?>')) {
                    $.post(ajaxurl, {
                        action: 'nexjob_clear_logs',
                        nonce: '<?php echo wp_create_nonce('nexjob_logs'); ?>'
                    }, function(response) {
                        if (response.success) {
                            $('#logs-container').html('<p><?php _e('No logs found.', 'nexjob-seo'); ?></p>');
                        }
                    });
                }
            });
            
            // Auto refresh every 30 seconds
            setInterval(function() {
                $('#refresh-logs').click();
            }, 30000);
        });
        </script>
        <?php
    }
    
    /**
     * Display logs
     */
    private function display_logs($level = '', $post_type = '') {
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
        
        $query = "SELECT * FROM {$this->log_table}{$where_clause} ORDER BY timestamp DESC LIMIT 100";
        
        if (!empty($where_values)) {
            $logs = $wpdb->get_results($wpdb->prepare($query, $where_values));
        } else {
            $logs = $wpdb->get_results($query);
        }
        
        if (empty($logs)) {
            echo '<p>' . __('No logs found.', 'nexjob-seo') . '</p>';
            return;
        }
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . __('Timestamp', 'nexjob-seo') . '</th>';
        echo '<th>' . __('Level', 'nexjob-seo') . '</th>';
        echo '<th>' . __('Message', 'nexjob-seo') . '</th>';
        echo '<th>' . __('Post', 'nexjob-seo') . '</th>';
        echo '<th>' . __('Post Type', 'nexjob-seo') . '</th>';
        echo '<th>' . __('Context', 'nexjob-seo') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        
        foreach ($logs as $log) {
            $level_class = 'log-' . $log->level;
            echo "<tr class='{$level_class}'>";
            echo '<td>' . $log->timestamp . '</td>';
            echo '<td><span class="log-level log-level-' . $log->level . '">' . ucfirst($log->level) . '</span></td>';
            echo '<td>' . esc_html($log->message) . '</td>';
            echo '<td>';
            if ($log->post_id) {
                echo '<a href="' . get_edit_post_link($log->post_id) . '">' . esc_html($log->post_title) . ' (#' . $log->post_id . ')</a>';
            } else {
                echo '-';
            }
            echo '</td>';
            echo '<td>' . esc_html($log->post_type ?: '-') . '</td>';
            echo '<td>';
            if ($log->context) {
                echo '<details><summary>View</summary><pre>' . esc_html($log->context) . '</pre></details>';
            } else {
                echo '-';
            }
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        
        // Add CSS for log levels
        echo '<style>
        .log-level { padding: 2px 6px; border-radius: 3px; color: white; font-size: 11px; }
        .log-level-error { background: #dc3232; }
        .log-level-warning { background: #ffb900; }
        .log-level-info { background: #0073aa; }
        .log-level-success { background: #46b450; }
        .log-error { background-color: #ffeaea; }
        .log-warning { background-color: #fff8e5; }
        .log-success { background-color: #eafaf1; }
        details { cursor: pointer; }
        details pre { background: #f1f1f1; padding: 10px; margin: 5px 0; border-radius: 3px; font-size: 11px; white-space: pre-wrap; word-wrap: break-word; }
        </style>';
    }
    
    /**
     * Handle admin actions
     */
    public function handle_admin_actions() {
        if (!current_user_can('manage_options')) return;
        
        if (isset($_GET['action'])) {
            switch ($_GET['action']) {
                case 'nexjob_manual_process':
                    $this->process_posts_via_cron(false);
                    wp_redirect(add_query_arg('message', 'processed', remove_query_arg('action')));
                    exit;
                    
                case 'nexjob_force_reprocess':
                    $this->force_reprocess_all();
                    wp_redirect(add_query_arg('message', 'reprocessed', remove_query_arg('action')));
                    exit;
            }
        }
    }
    
    /**
     * Show admin notices
     */
    public function show_admin_notices() {
        if (isset($_GET['message'])) {
            switch ($_GET['message']) {
                case 'processed':
                    echo '<div class="notice notice-success is-dismissible"><p>' . __('Manual processing completed!', 'nexjob-seo') . '</p></div>';
                    break;
                case 'reprocessed':
                    echo '<div class="notice notice-success is-dismissible"><p>' . __('Force reprocessing completed! All posts will be reprocessed.', 'nexjob-seo') . '</p></div>';
                    break;
            }
        }
        
        // Show processing status on configured post type pages
        $screen = get_current_screen();
        if ($screen && in_array($screen->post_type, $this->settings['post_types'])) {
            $stats = $this->get_processing_stats();
            
            if (isset($stats[$screen->post_type]) && $stats[$screen->post_type]['remaining'] > 0) {
                echo '<div class="notice notice-info"><p>';
                echo sprintf(
                    __('NexJob SEO Automation: %d of %d %s posts processed properly. %d remaining.', 'nexjob-seo'),
                    $stats[$screen->post_type]['processed'],
                    $stats[$screen->post_type]['total'],
                    $screen->post_type,
                    $stats[$screen->post_type]['remaining']
                );
                echo '</p></div>';
            }
        }
    }
    
    /**
     * Add manual process buttons to post list page
     */
    public function add_manual_process_buttons($views) {
        $manual_url = add_query_arg('action', 'nexjob_manual_process');
        $force_url = add_query_arg('action', 'nexjob_force_reprocess');
        
        $views['manual_process'] = '<a href="' . esc_url($manual_url) . '">' . __('Manual Process SEO', 'nexjob-seo') . '</a>';
        $views['force_reprocess'] = '<a href="' . esc_url($force_url) . '" onclick="return confirm(\'' . __('This will reprocess ALL posts, including those already processed. Continue?', 'nexjob-seo') . '\')">' . __('Force Regenerate All', 'nexjob-seo') . '</a>';
        
        return $views;
    }
    
    /**
     * AJAX: Clear logs
     */
    public function ajax_clear_logs() {
        if (!wp_verify_nonce($_POST['nonce'], 'nexjob_logs') || !current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$this->log_table}");
        
        wp_send_json_success();
    }
    
    /**
     * AJAX: Get logs
     */
    public function ajax_get_logs() {
        if (!wp_verify_nonce($_POST['nonce'], 'nexjob_logs') || !current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $level = sanitize_text_field($_POST['level']);
        $post_type = sanitize_text_field($_POST['post_type']);
        
        ob_start();
        $this->display_logs($level, $post_type);
        $output = ob_get_clean();
        
        echo $output;
        wp_die();
    }
    
    /**
     * Get processing statistics for all configured post types
     */
    public function get_processing_stats() {
        $stats = array();
        
        foreach ($this->settings['post_types'] as $post_type) {
            $total_posts = wp_count_posts($post_type);
            
            if (!isset($total_posts->publish)) {
                continue;
            }
            
            // Get posts for this type
            $posts_of_type = get_posts(array(
                'post_type'      => $post_type,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids'
            ));
            
            $properly_processed = 0;
            foreach ($posts_of_type as $post_id) {
                if (!$this->needs_seo_processing($post_id, false)) {
                    $properly_processed++;
                }
            }
            
            $stats[$post_type] = array(
                'total' => $total_posts->publish,
                'processed' => $properly_processed,
                'remaining' => $total_posts->publish - $properly_processed
            );
        }
        
        return $stats;
    }
    
    /**
     * Get cron job information
     */
    private function get_cron_info() {
        $next_run = wp_next_scheduled('nexjob_process_seo_cron');
        $schedules = wp_get_schedules();
        
        $interval_name = isset($schedules[$this->settings['cron_interval']]) 
            ? $schedules[$this->settings['cron_interval']]['display']
            : $this->settings['cron_interval'];
        
        return array(
            'next_run' => $next_run ? date('Y-m-d H:i:s', $next_run) : __('Not scheduled', 'nexjob-seo'),
            'interval' => $interval_name
        );
    }
    
    /**
     * Force reprocess all posts
     */
    public function force_reprocess_all() {
        global $wpdb;
        
        $this->log('Force reprocess all posts initiated', 'info', null, null, array(
            'configured_post_types' => $this->settings['post_types']
        ));
        
        // Delete all processed flags for configured post types
        foreach ($this->settings['post_types'] as $post_type) {
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
            
            $this->log("Cleared processing flags for {$post_type} posts", 'info', null, null, array(
                'post_type' => $post_type,
                'posts_cleared' => count($post_ids)
            ));
        }
        
        // Process posts with force reprocess enabled
        $this->process_posts_via_cron(true);
    }
}

// Initialize the plugin
new NexJobSEOAutomationPlugin();