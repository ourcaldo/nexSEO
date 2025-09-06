<?php
/**
 * Additional AJAX handlers for automation admin
 */

// Add these methods to the NexJob_SEO_Automation_Admin class

/**
 * Handle AJAX preview generation
 */
public function handle_generate_preview() {
    if (!wp_verify_nonce($_POST['nonce'], 'automation_preview')) {
        wp_die('Security check failed');
    }
    
    $automation_id = intval($_POST['automation_id']);
    $sample_title = sanitize_text_field($_POST['sample_title']);
    
    $automation = $this->automation_manager->get_automation($automation_id);
    if (!$automation) {
        wp_send_json_error(array('message' => 'Automation not found'));
    }
    
    // Get template path
    $templates = $this->template_manager->get_available_templates();
    if (!isset($templates[$automation->template_name])) {
        wp_send_json_error(array('message' => 'Template not found'));
    }
    
    $template_path = $templates[$automation->template_name]['path'];
    
    // Prepare configuration
    $config = array(
        'font_size' => $automation->font_size,
        'font_color' => $automation->font_color,
        'text_align' => $automation->text_align,
        'text_area' => array(
            'x' => $automation->text_area_x,
            'y' => $automation->text_area_y,
            'width' => $automation->text_area_width,
            'height' => $automation->text_area_height
        )
    );
    
    // Generate preview image
    $image_processor = new NexJob_SEO_Image_Processor($this->template_manager->get_settings(), $this->logger);
    $generated_image = $image_processor->generate_image_with_text($template_path, $sample_title, $config);
    
    if (!$generated_image) {
        wp_send_json_error(array('message' => 'Failed to generate preview image'));
    }
    
    // Upload to media library as temporary preview
    $upload_dir = wp_upload_dir();
    $preview_filename = 'preview-' . uniqid() . '.png';
    $preview_path = $upload_dir['path'] . '/' . $preview_filename;
    
    if (copy($generated_image, $preview_path)) {
        $preview_url = $upload_dir['url'] . '/' . $preview_filename;
        
        // Clean up temp file
        unlink($generated_image);
        
        wp_send_json_success(array('preview_url' => $preview_url));
    } else {
        wp_send_json_error(array('message' => 'Failed to save preview image'));
    }
}

/**
 * Handle AJAX template upload
 */
public function handle_upload_template() {
    if (!wp_verify_nonce($_POST['nonce'], 'upload_template')) {
        wp_die('Security check failed');
    }
    
    if (!isset($_FILES['template_file'])) {
        wp_send_json_error(array('message' => 'No file uploaded'));
    }
    
    $result = $this->template_manager->upload_custom_template($_FILES['template_file']);
    
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
    }
    
    wp_send_json_success(array('message' => 'Template uploaded successfully', 'template' => $result));
}