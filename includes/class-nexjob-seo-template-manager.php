<?php
/**
 * Template Manager
 * 
 * Handles loading, validation, and management of featured image templates
 */

class NexJob_SEO_Template_Manager {
    private $settings;
    private $templates_dir;
    private $template_cache = array();

    public function __construct($settings) {
        $this->settings = $settings;
        $this->templates_dir = NEXJOB_SEO_PLUGIN_DIR . 'templates/featured-images/';
        
        $this->ensure_templates_directory();
    }

    /**
     * Ensure templates directory exists
     */
    private function ensure_templates_directory() {
        if (!file_exists($this->templates_dir)) {
            wp_mkdir_p($this->templates_dir);
        }
        
        $custom_dir = $this->templates_dir . 'custom/';
        if (!file_exists($custom_dir)) {
            wp_mkdir_p($custom_dir);
        }
    }

    /**
     * Get template path for a specific post
     */
    public function get_template_for_post($post) {
        // Check for post-type specific template
        $post_type_template = $this->templates_dir . $post->post_type . '.png';
        if (file_exists($post_type_template)) {
            return $post_type_template;
        }

        // Check for category-specific template (for posts)
        if ($post->post_type === 'post') {
            $categories = get_the_category($post->ID);
            if (!empty($categories)) {
                $category_template = $this->templates_dir . 'category-' . $categories[0]->slug . '.png';
                if (file_exists($category_template)) {
                    return $category_template;
                }
            }
        }

        // Use default template
        $default_template = $this->templates_dir . 'default.png';
        if (file_exists($default_template)) {
            return $default_template;
        }

        return false;
    }

    /**
     * Get all available templates
     */
    public function get_available_templates() {
        if (!empty($this->template_cache)) {
            return $this->template_cache;
        }

        $templates = array();
        
        // Scan main templates directory
        $files = glob($this->templates_dir . '*.{png,jpg,jpeg}', GLOB_BRACE);
        foreach ($files as $file) {
            $name = basename($file, '.' . pathinfo($file, PATHINFO_EXTENSION));
            $templates[$name] = array(
                'name' => $this->format_template_name($name),
                'path' => $file,
                'url' => $this->get_template_url($file),
                'type' => 'default'
            );
        }

        // Scan custom templates directory
        $custom_files = glob($this->templates_dir . 'custom/*.{png,jpg,jpeg}', GLOB_BRACE);
        foreach ($custom_files as $file) {
            $name = 'custom-' . basename($file, '.' . pathinfo($file, PATHINFO_EXTENSION));
            $templates[$name] = array(
                'name' => $this->format_template_name(basename($file, '.' . pathinfo($file, PATHINFO_EXTENSION))),
                'path' => $file,
                'url' => $this->get_template_url($file),
                'type' => 'custom'
            );
        }

        $this->template_cache = $templates;
        return $templates;
    }

    /**
     * Format template name for display
     */
    private function format_template_name($name) {
        // Convert filename to readable name
        $name = str_replace(array('-', '_'), ' ', $name);
        return ucwords($name);
    }

    /**
     * Get template URL for preview
     */
    private function get_template_url($file_path) {
        $relative_path = str_replace(NEXJOB_SEO_PLUGIN_DIR, '', $file_path);
        return NEXJOB_SEO_PLUGIN_URL . $relative_path;
    }

    /**
     * Validate template file
     */
    public function validate_template($file_path) {
        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', 'Template file not found');
        }

        // Check if it's a valid image
        $image_info = getimagesize($file_path);
        if (!$image_info) {
            return new WP_Error('invalid_image', 'File is not a valid image');
        }

        // Check image dimensions
        $min_width = $this->settings->get('template_min_width', 800);
        $min_height = $this->settings->get('template_min_height', 600);
        
        if ($image_info[0] < $min_width || $image_info[1] < $min_height) {
            return new WP_Error('dimensions_too_small', 
                "Image dimensions must be at least {$min_width}x{$min_height}px");
        }

        // Check file size
        $max_size = $this->settings->get('template_max_size', 5 * 1024 * 1024); // 5MB default
        if (filesize($file_path) > $max_size) {
            return new WP_Error('file_too_large', 'Template file is too large');
        }

        return true;
    }

    /**
     * Upload custom template
     */
    public function upload_custom_template($file) {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return new WP_Error('upload_error', 'File upload failed');
        }

        // Validate file
        $validation = $this->validate_template($file['tmp_name']);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Generate unique filename
        $filename = sanitize_file_name($file['name']);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $basename = pathinfo($filename, PATHINFO_FILENAME);
        $basename = sanitize_title($basename);
        
        $target_file = $this->templates_dir . 'custom/' . $basename . '.' . $extension;
        
        // Handle filename conflicts
        $counter = 1;
        while (file_exists($target_file)) {
            $target_file = $this->templates_dir . 'custom/' . $basename . '-' . $counter . '.' . $extension;
            $counter++;
        }

        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $target_file)) {
            // Clear template cache
            $this->template_cache = array();
            
            return array(
                'success' => true,
                'file' => $target_file,
                'name' => basename($target_file, '.' . $extension),
                'url' => $this->get_template_url($target_file)
            );
        } else {
            return new WP_Error('move_failed', 'Failed to save uploaded template');
        }
    }

    /**
     * Delete custom template
     */
    public function delete_template($template_name) {
        $templates = $this->get_available_templates();
        
        if (!isset($templates[$template_name])) {
            return new WP_Error('template_not_found', 'Template not found');
        }

        $template = $templates[$template_name];
        
        // Only allow deletion of custom templates
        if ($template['type'] !== 'custom') {
            return new WP_Error('cannot_delete', 'Cannot delete default templates');
        }

        if (file_exists($template['path']) && unlink($template['path'])) {
            // Clear template cache
            $this->template_cache = array();
            return true;
        } else {
            return new WP_Error('delete_failed', 'Failed to delete template file');
        }
    }

    /**
     * Get template configuration
     */
    public function get_template_config($template_name = 'default') {
        $default_config = array(
            'text_area' => array(
                'x' => 50,
                'y' => 100,
                'width' => 700,
                'height' => 400
            ),
            'font' => array(
                'family' => 'Arial',
                'size' => 48,
                'color' => '#FFFFFF',
                'weight' => 'bold'
            ),
            'branding' => array(
                'logo_position' => 'bottom-left',
                'brand_text' => get_bloginfo('name')
            )
        );

        // Get saved configuration for this template
        $saved_config = $this->settings->get('template_config_' . $template_name, array());
        
        return array_merge($default_config, $saved_config);
    }

    /**
     * Save template configuration
     */
    public function save_template_config($template_name, $config) {
        return $this->settings->set('template_config_' . $template_name, $config);
    }

    /**
     * Get default template path
     */
    public function get_default_template() {
        $default_template = $this->templates_dir . 'default.png';
        return file_exists($default_template) ? $default_template : false;
    }
}