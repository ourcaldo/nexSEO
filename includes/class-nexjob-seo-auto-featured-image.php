<?php
/**
 * Auto Featured Image Generator
 * 
 * Core engine for generating template-based featured images with dynamic text overlays
 */

class NexJob_SEO_Auto_Featured_Image {
    private $template_manager;
    private $image_processor;
    private $settings;
    private $logger;

    public function __construct($settings, $logger) {
        $this->settings = $settings;
        $this->logger = $logger;
        $this->template_manager = new NexJob_SEO_Template_Manager($settings);
        $this->image_processor = new NexJob_SEO_Image_Processor($settings, $logger);
        
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Generate featured image when post is published
        add_action('publish_post', array($this, 'generate_on_publish'), 10, 2);
        add_action('publish_page', array($this, 'generate_on_publish'), 10, 2);
        
        // AJAX endpoints for manual generation
        add_action('wp_ajax_nexjob_generate_featured_image', array($this, 'ajax_generate_featured_image'));
        add_action('wp_ajax_nexjob_bulk_generate_featured_images', array($this, 'ajax_bulk_generate_featured_images'));
    }

    /**
     * Generate featured image when post is published
     */
    public function generate_on_publish($post_id, $post) {
        // Skip if auto-generation is disabled
        if (!$this->settings->get('auto_featured_images_enabled', true)) {
            return;
        }

        // Skip if post already has featured image
        if (has_post_thumbnail($post_id)) {
            return;
        }

        // Skip if post type is not enabled
        $enabled_post_types = $this->settings->get('auto_featured_images_post_types', array('post', 'page'));
        if (!in_array($post->post_type, $enabled_post_types)) {
            return;
        }

        $this->generate_featured_image($post_id);
    }

    /**
     * Generate featured image for a specific post
     */
    public function generate_featured_image($post_id) {
        try {
            $post = get_post($post_id);
            if (!$post) {
                throw new Exception("Post not found: {$post_id}");
            }

            $this->logger->log("Starting featured image generation for post {$post_id}: {$post->post_title}");

            // Get automation for this post type
            $automation_manager = new NexJob_SEO_Automation_Manager($this->logger);
            $automation = $automation_manager->get_automation_for_post_type($post->post_type);
            
            if (!$automation) {
                // Fallback to old template method
                return $this->generate_featured_image_legacy($post_id);
            }

            // Get template path from automation
            $templates = $this->template_manager->get_available_templates();
            if (!isset($templates[$automation->template_name])) {
                throw new Exception("Template not found: {$automation->template_name}");
            }
            
            $template_path = $templates[$automation->template_name]['path'];

            // Prepare text content
            $title_text = $this->prepare_title_text($post->post_title, $automation->max_title_length);
            
            // Prepare simplified configuration
            $config = array(
                'font_color' => $automation->font_color
            );
            
            // Generate image
            $generated_image = $this->image_processor->generate_image_with_text(
                $template_path,
                $title_text,
                $config
            );

            if (!$generated_image) {
                throw new Exception("Failed to generate image for post {$post_id}");
            }

            // Upload to WordPress media library
            $attachment_id = $this->upload_to_media_library($generated_image, $post);
            
            if (!$attachment_id) {
                throw new Exception("Failed to upload generated image to media library");
            }

            // Set as featured image
            if (set_post_thumbnail($post_id, $attachment_id)) {
                $this->logger->log("Successfully generated and set featured image for post {$post_id} using automation {$automation->name}");
                
                // Clean up temporary file
                if (file_exists($generated_image)) {
                    unlink($generated_image);
                }
                
                return $attachment_id;
            } else {
                throw new Exception("Failed to set featured image for post {$post_id}");
            }

        } catch (Exception $e) {
            $this->logger->log("Error generating featured image for post {$post_id}: " . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Legacy method for generating featured images without automation
     */
    private function generate_featured_image_legacy($post_id) {
        try {
            $post = get_post($post_id);
            
            // Get template for this post
            $template_path = $this->template_manager->get_template_for_post($post);
            if (!$template_path) {
                throw new Exception("No template available for post {$post_id}");
            }

            // Prepare text content
            $title_text = $this->prepare_title_text($post->post_title);
            
            // Generate image
            $generated_image = $this->image_processor->generate_image_with_text(
                $template_path,
                $title_text,
                $this->get_text_config_for_post($post)
            );

            if (!$generated_image) {
                throw new Exception("Failed to generate image for post {$post_id}");
            }

            // Upload to WordPress media library
            $attachment_id = $this->upload_to_media_library($generated_image, $post);
            
            if (!$attachment_id) {
                throw new Exception("Failed to upload generated image to media library");
            }

            // Set as featured image
            if (set_post_thumbnail($post_id, $attachment_id)) {
                $this->logger->log("Successfully generated and set featured image for post {$post_id} (legacy method)");
                
                // Clean up temporary file
                if (file_exists($generated_image)) {
                    unlink($generated_image);
                }
                
                return $attachment_id;
            } else {
                throw new Exception("Failed to set featured image for post {$post_id}");
            }

        } catch (Exception $e) {
            $this->logger->log("Error generating featured image for post {$post_id}: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Prepare title text for image overlay
     */
    private function prepare_title_text($title, $max_length = null) {
        // Remove HTML tags
        $title = strip_tags($title);
        
        // Decode HTML entities
        $title = html_entity_decode($title, ENT_QUOTES, 'UTF-8');
        
        // Limit length
        $max_length = $this->settings->get('auto_featured_images_max_title_length', 80);
        if (strlen($title) > $max_length) {
            $title = substr($title, 0, $max_length - 3) . '...';
        }

        return $title;
    }

    /**
     * Get text configuration for a specific post
     */
    private function get_text_config_for_post($post) {
        $default_config = array(
            'font_size' => 48,
            'font_color' => '#FFFFFF',
            'font_weight' => 'bold',
            'text_area' => array(
                'x' => 50,
                'y' => 100,
                'width' => 700,
                'height' => 400
            ),
            'line_height' => 1.2,
            'text_align' => 'center'
        );

        // Allow customization based on post type or other criteria
        $config = apply_filters('nexjob_seo_featured_image_text_config', $default_config, $post);
        
        return $config;
    }

    /**
     * Upload generated image to WordPress media library
     */
    private function upload_to_media_library($image_path, $post) {
        if (!file_exists($image_path)) {
            return false;
        }

        $filename = 'featured-image-' . $post->ID . '-' . time() . '.png';
        $upload_dir = wp_upload_dir();
        $target_path = $upload_dir['path'] . '/' . $filename;

        // Copy file to uploads directory
        if (!copy($image_path, $target_path)) {
            return false;
        }

        // Create attachment
        $attachment = array(
            'guid' => $upload_dir['url'] . '/' . $filename,
            'post_mime_type' => 'image/png',
            'post_title' => 'Featured Image: ' . $post->post_title,
            'post_content' => '',
            'post_status' => 'inherit'
        );

        $attachment_id = wp_insert_attachment($attachment, $target_path, $post->ID);
        
        if (!$attachment_id) {
            return false;
        }

        // Generate attachment metadata
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $target_path);
        wp_update_attachment_metadata($attachment_id, $attachment_data);

        return $attachment_id;
    }

    /**
     * AJAX handler for manual featured image generation
     */
    public function ajax_generate_featured_image() {
        check_ajax_referer('nexjob_seo_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('Insufficient permissions');
        }

        $post_id = intval($_POST['post_id']);
        $result = $this->generate_featured_image($post_id);

        if ($result) {
            wp_send_json_success(array(
                'message' => 'Featured image generated successfully',
                'attachment_id' => $result
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Failed to generate featured image'
            ));
        }
    }

    /**
     * AJAX handler for bulk featured image generation
     */
    public function ajax_bulk_generate_featured_images() {
        check_ajax_referer('nexjob_seo_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die('Insufficient permissions');
        }

        $post_ids = isset($_POST['post_ids']) ? array_map('intval', $_POST['post_ids']) : array();
        $results = array();

        foreach ($post_ids as $post_id) {
            $result = $this->generate_featured_image($post_id);
            $results[$post_id] = $result !== false;
        }

        $success_count = count(array_filter($results));
        $total_count = count($results);

        wp_send_json_success(array(
            'message' => "Generated {$success_count} of {$total_count} featured images",
            'results' => $results
        ));
    }

    /**
     * Get posts without featured images
     */
    public function get_posts_without_featured_images($limit = 20, $offset = 0) {
        $args = array(
            'post_type' => $this->settings->get('auto_featured_images_post_types', array('post', 'page')),
            'post_status' => 'publish',
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_thumbnail_id',
                    'compare' => 'NOT EXISTS'
                ),
                array(
                    'key' => '_thumbnail_id',
                    'value' => '',
                    'compare' => '='
                )
            ),
            'posts_per_page' => $limit,
            'offset' => $offset,
            'orderby' => 'date',
            'order' => 'DESC'
        );

        return get_posts($args);
    }

    /**
     * Get statistics about featured image generation
     */
    public function get_statistics() {
        $stats = array();
        
        // Count posts without featured images
        $posts_without_images = $this->get_posts_without_featured_images(-1);
        $stats['posts_without_featured_images'] = count($posts_without_images);
        
        // Get generation statistics from logs
        $generation_logs = $this->logger->get_logs(array(
            'action' => 'featured_image_generation',
            'limit' => -1
        ));
        
        $stats['total_generations'] = count($generation_logs);
        $stats['successful_generations'] = count(array_filter($generation_logs, function($log) {
            return strpos($log->message, 'Successfully generated') !== false;
        }));
        
        $stats['failed_generations'] = $stats['total_generations'] - $stats['successful_generations'];
        $stats['success_rate'] = $stats['total_generations'] > 0 ? 
            round(($stats['successful_generations'] / $stats['total_generations']) * 100, 2) : 0;
        
        return $stats;
    }
}