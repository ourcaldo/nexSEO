<?php
/**
 * Post processor class for SEO generation logic
 */

if (!defined('ABSPATH')) {
    exit;
}

class NexJob_SEO_Post_Processor {
    
    /**
     * Dependencies
     */
    private $settings;
    private $logger;
    
    /**
     * Constructor
     */
    public function __construct($settings, $logger) {
        $this->settings = $settings;
        $this->logger = $logger;
    }
    
    /**
     * Process post manually (when saving)
     */
    public function process_post_manual($post_id) {
        // Skip if it's autosave, revision, or not published
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (function_exists('wp_is_post_revision') && wp_is_post_revision($post_id)) return;
        if (function_exists('get_post_status') && get_post_status($post_id) !== 'publish') return;
        
        $post_type = get_post_type($post_id);
        $this->logger->log("Manual processing triggered for post ID $post_id (type: $post_type)", 'info', $post_id, get_the_title($post_id), array(
            'post_type' => $post_type
        ));
        
        // Always process manually saved posts (ignore the needs_seo_processing check for manual saves)
        $this->process_single_post($post_id, false, true);
    }
    
    /**
     * Check if post needs SEO processing with detailed field checking
     */
    public function needs_seo_processing($post_id, $force_reprocess = false) {
        $post_title = get_the_title($post_id);
        $post_type = get_post_type($post_id);
        
        // Check if post type is configured for processing
        $configured_post_types = $this->settings->get('post_types');
        if (!in_array($post_type, $configured_post_types)) {
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
        $required_fields = $this->settings->get('required_fields');
        foreach ($required_fields as $field_key) {
            $field_value = get_post_meta($post_id, $field_key, true);
            $field_values[$field_key] = $field_value;
            
            if (empty($field_value)) {
                $missing_fields[] = $field_key;
            }
        }
        
        // If any required fields are missing, log details and return false
        if (!empty($missing_fields)) {
            $this->logger->log(
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
            $this->logger->log("Post ID $post_id needs processing - title mismatch", 'info', $post_id, $post_title, array(
                'current_title' => $current_title,
                'expected_title' => $expected_title,
                'post_type' => $post_type
            ));
            return true;
        }
        
        // Check if description is missing
        if (empty($current_description)) {
            $this->logger->log("Post ID $post_id needs processing - missing description", 'info', $post_id, $post_title, array(
                'post_type' => $post_type
            ));
            return true;
        }
        
        // Check if slug needs updating
        $current_post = get_post($post_id);
        $expected_slug = $this->generate_slug($post_id, $field_values);
        $unique_expected_slug = wp_unique_post_slug($expected_slug, $post_id, 'publish', $post_type, 0);
        
        if ($current_post->post_name !== $unique_expected_slug) {
            $this->logger->log("Post ID $post_id needs processing - slug mismatch", 'info', $post_id, $post_title, array(
                'current_slug' => $current_post->post_name,
                'expected_slug' => $unique_expected_slug,
                'post_type' => $post_type
            ));
            return true;
        }
        
        return false;
    }
    
    /**
     * Process a single post for SEO and slug
     */
    public function process_single_post($post_id, $is_cron = false, $force_reprocess = false) {
        $post_type = get_post_type($post_id);
        
        // Verify post type is configured
        $configured_post_types = $this->settings->get('post_types');
        if (!in_array($post_type, $configured_post_types)) {
            $this->logger->log("Post ID $post_id skipped - post type '$post_type' not configured for processing", 'warning', $post_id, get_the_title($post_id), array(
                'post_type' => $post_type,
                'configured_types' => $configured_post_types
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
        $required_fields = $this->settings->get('required_fields');
        foreach ($required_fields as $field_key) {
            $field_value = get_post_meta($post_id, $field_key, true);
            $field_values[$field_key] = $field_value;
            
            if (empty($field_value)) {
                $missing_fields[] = $field_key;
            }
        }
        
        // If required fields are missing, create them with default values or log detailed error
        if (!empty($missing_fields)) {
            $this->logger->log("Post ID $post_id processing failed - missing required fields: " . implode(', ', $missing_fields), 'error', $post_id, $post_title, array(
                'missing_fields' => $missing_fields,
                'all_field_values' => $field_values,
                'post_type' => $post_type,
                'required_fields' => $required_fields
            ));
            
            // Try to create missing fields with default values for certain fields
            foreach ($missing_fields as $missing_field) {
                if ($missing_field === 'nexjob_nama_perusahaan') {
                    $default_value = 'Unknown Company';
                    update_post_meta($post_id, $missing_field, $default_value);
                    $field_values[$missing_field] = $default_value;
                    $this->logger->log("Created missing field '$missing_field' with default value '$default_value' for post ID $post_id", 'info', $post_id, $post_title);
                } elseif ($missing_field === 'nexjob_lokasi_kota') {
                    $default_value = 'Unknown Location';
                    update_post_meta($post_id, $missing_field, $default_value);
                    $field_values[$missing_field] = $default_value;
                    $this->logger->log("Created missing field '$missing_field' with default value '$default_value' for post ID $post_id", 'info', $post_id, $post_title);
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
            
            // Generate SEO Description
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
            $this->logger->log("Post ID $post_id processed successfully" . ($force_reprocess ? ' (force reprocess)' : ''), 'success', $post_id, $post_title, array(
                'post_type' => $post_type,
                'seo_title' => $seo_title,
                'meta_description' => $meta_description,
                'slug' => $new_slug,
                'field_values' => $field_values,
                'force_reprocess' => $force_reprocess
            ));
            
        } catch (Exception $e) {
            $this->logger->log("Post ID $post_id processing failed with error: " . $e->getMessage(), 'error', $post_id, $post_title, array(
                'post_type' => $post_type,
                'error_trace' => $e->getTraceAsString()
            ));
        }
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
     * Generate meta description from content
     */
    private function generate_meta_description($post_content) {
        // Return empty if no content
        if (empty($post_content)) {
            return '';
        }
        
        // Get content before H2 tag
        $content_before_h2 = '';
        
        // Try to find H2 tag
        $h2_pos = stripos($post_content, '<h2');
        if ($h2_pos !== false) {
            $content_before_h2 = substr($post_content, 0, $h2_pos);
        } else {
            $content_before_h2 = $post_content;
        }

        // Clean HTML tags
        $content_clean = wp_strip_all_tags($content_before_h2);
        $content_clean = trim($content_clean);
        
        if (empty($content_clean)) {
            return '';
        }

        // Split by sentences (., !, ?)
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
        
        // Sanitize and return slug
        return sanitize_title($slug_string);
    }
    
    /**
     * Update post slug
     */
    private function update_post_slug($post_id, $new_slug) {
        $post_type = get_post_type($post_id);
        $unique_slug = wp_unique_post_slug($new_slug, $post_id, 'publish', $post_type, 0);
        
        // Update post slug
        $updated_post = array(
            'ID' => $post_id,
            'post_name' => $unique_slug
        );
        
        // Remove hook to prevent infinite loop
        remove_action("save_post_{$post_type}", array($this, 'process_post_manual'), 20);
        
        $result = wp_update_post($updated_post);
        
        // Re-add hook
        add_action("save_post_{$post_type}", array($this, 'process_post_manual'), 20);
        
        return $result;
    }
}