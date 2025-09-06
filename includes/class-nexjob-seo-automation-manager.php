<?php
/**
 * Manager for featured image automation configurations
 */

if (!defined('ABSPATH')) {
    exit;
}

class NexJob_SEO_Automation_Manager {
    
    private $logger;
    
    public function __construct($logger) {
        $this->logger = $logger;
    }
    
    /**
     * Get all automations
     */
    public function get_automations($status = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'nexjob_featured_image_automations';
        
        $where = '';
        if ($status) {
            $where = $wpdb->prepare("WHERE status = %s", $status);
        }
        
        $results = $wpdb->get_results("SELECT * FROM $table $where ORDER BY created_at DESC");
        
        // Decode post_types JSON
        foreach ($results as $automation) {
            $automation->post_types = json_decode($automation->post_types, true);
        }
        
        return $results;
    }
    
    /**
     * Get automation by ID
     */
    public function get_automation($id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'nexjob_featured_image_automations';
        
        $automation = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
        
        if ($automation) {
            $automation->post_types = json_decode($automation->post_types, true);
        }
        
        return $automation;
    }
    
    /**
     * Create new automation with basic info
     */
    public function create_automation($name, $description = '') {
        global $wpdb;
        
        $table = $wpdb->prefix . 'nexjob_featured_image_automations';
        
        // Create basic automation entry with defaults
        $insert_data = array(
            'name' => sanitize_text_field($name),
            'status' => 'inactive',
            'post_types' => json_encode(array('post')),
            'template_name' => 'default.png',
            'font_size' => 48,
            'font_color' => '#FFFFFF',
            'text_align' => 'center',
            'text_area_x' => 50,
            'text_area_y' => 100,
            'text_area_width' => 1100,
            'text_area_height' => 430,
            'max_title_length' => 80,
            'apply_to_existing' => 0
        );
        
        $result = $wpdb->insert($table, $insert_data);
        
        if ($result !== false) {
            $automation_id = $wpdb->insert_id;
            $this->logger->log("Created automation: " . $name, 'info');
            return array('success' => true, 'automation_id' => $automation_id);
        }
        
        return array('success' => false, 'error' => 'Database insert failed');
    }
    
    /**
     * Update automation with full configuration
     */
    public function update_automation_config($automation_id, $data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'nexjob_featured_image_automations';
        
        // Prepare update data
        $update_data = array(
            'name' => sanitize_text_field($data['name']),
            'status' => sanitize_text_field($data['status']),
            'post_types' => json_encode($data['post_types']),
            'template_name' => sanitize_text_field($data['template_name']),
            'font_size' => intval($data['font_size']),
            'font_color' => sanitize_text_field($data['font_color']),
            'text_align' => sanitize_text_field($data['text_align']),
            'text_area_x' => intval($data['text_area_x']),
            'text_area_y' => intval($data['text_area_y']),
            'text_area_width' => intval($data['text_area_width']),
            'text_area_height' => intval($data['text_area_height']),
            'max_title_length' => intval($data['max_title_length']),
            'apply_to_existing' => intval($data['apply_to_existing'])
        );
        
        $result = $wpdb->update($table, $update_data, array('id' => $automation_id));
        
        if ($result !== false) {
            $this->logger->log("Updated automation ID {$automation_id}", 'info');
            return true;
        }
        
        return false;
    }
    
    /**
     * Update automation
     */
    public function update_automation($id, $data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'nexjob_featured_image_automations';
        
        // Prepare data
        $update_data = array(
            'name' => sanitize_text_field($data['name']),
            'status' => sanitize_text_field($data['status']),
            'post_types' => json_encode($data['post_types']),
            'template_name' => sanitize_text_field($data['template_name']),
            'font_size' => intval($data['font_size']),
            'font_color' => sanitize_text_field($data['font_color']),
            'text_align' => sanitize_text_field($data['text_align']),
            'text_area_x' => intval($data['text_area_x']),
            'text_area_y' => intval($data['text_area_y']),
            'text_area_width' => intval($data['text_area_width']),
            'text_area_height' => intval($data['text_area_height']),
            'max_title_length' => intval($data['max_title_length']),
            'apply_to_existing' => intval($data['apply_to_existing'])
        );
        
        $result = $wpdb->update($table, $update_data, array('id' => $id));
        
        if ($result !== false) {
            $this->logger->log("Updated automation ID: $id", 'info');
            return true;
        }
        
        return false;
    }
    
    /**
     * Delete automation
     */
    public function delete_automation($id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'nexjob_featured_image_automations';
        
        $result = $wpdb->delete($table, array('id' => $id));
        
        if ($result !== false) {
            $this->logger->log("Deleted automation ID: $id", 'info');
            return true;
        }
        
        return false;
    }
    
    /**
     * Get automation for specific post type
     */
    public function get_automation_for_post_type($post_type) {
        $automations = $this->get_automations('active');
        
        foreach ($automations as $automation) {
            if (in_array($post_type, $automation->post_types)) {
                return $automation;
            }
        }
        
        return null;
    }
    
    /**
     * Toggle automation status
     */
    public function toggle_status($id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'nexjob_featured_image_automations';
        
        $current = $wpdb->get_var($wpdb->prepare("SELECT status FROM $table WHERE id = %d", $id));
        
        if ($current) {
            $new_status = ($current === 'active') ? 'inactive' : 'active';
            
            $result = $wpdb->update($table, array('status' => $new_status), array('id' => $id));
            
            if ($result !== false) {
                $this->logger->log("Toggled automation ID: $id to $new_status", 'info');
                return $new_status;
            }
        }
        
        return false;
    }
}