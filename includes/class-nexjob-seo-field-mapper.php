<?php
/**
 * Field Mapper class for mapping POST data to WordPress fields
 */

if (!defined('ABSPATH')) {
    exit;
}

class NexJob_SEO_Field_Mapper {
    
    /**
     * Dependencies
     */
    private $logger;
    
    /**
     * Constructor
     */
    public function __construct($logger) {
        $this->logger = $logger;
    }
    
    /**
     * Map webhook data to WordPress post fields
     */
    public function map_data_to_post_fields($webhook_data, $field_mappings, $post_type) {
        try {
            $mapped_data = array();
            $flattened_data = $this->flatten_webhook_data($webhook_data);
            
            foreach ($field_mappings as $mapping) {
                $webhook_field = $mapping['webhook_field'];
                $wp_field = $mapping['wp_field'];
                $default_value = $mapping['default_value'] ?? '';
                
                // Get value from webhook data
                $value = $this->get_webhook_value($flattened_data, $webhook_field, $default_value);
                
                // Apply field-specific processing
                $processed_value = $this->process_field_value($value, $wp_field, $post_type);
                
                if ($processed_value !== null) {
                    $mapped_data[$wp_field] = $processed_value;
                }
            }
            
            // Validate required fields
            $validation = $this->validate_mapped_data($mapped_data, $post_type);
            if (!$validation['valid']) {
                return array('success' => false, 'message' => implode(', ', $validation['errors']));
            }
            
            return array('success' => true, 'data' => $mapped_data);
            
        } catch (Exception $e) {
            $this->logger->log("Field mapping error: " . $e->getMessage(), 'error', null, null, array(
                'webhook_data' => $webhook_data,
                'field_mappings' => $field_mappings,
                'post_type' => $post_type
            ));
            
            return array('success' => false, 'message' => $e->getMessage());
        }
    }
    
    /**
     * Flatten webhook data for easier access
     */
    private function flatten_webhook_data($data, $prefix = '') {
        $result = array();
        
        foreach ($data as $key => $value) {
            $new_key = $prefix === '' ? $key : $prefix . '.' . $key;
            
            if (is_array($value) && !empty($value)) {
                // If it's an indexed array with simple values, join them
                if (array_keys($value) === range(0, count($value) - 1) && !is_array($value[0])) {
                    $result[$new_key] = $value;
                    $result[$new_key . '_joined'] = implode(', ', $value);
                } else {
                    // Recursively flatten associative arrays
                    $result = array_merge($result, $this->flatten_webhook_data($value, $new_key));
                }
            } else {
                $result[$new_key] = $value;
            }
        }
        
        return $result;
    }
    
    /**
     * Get value from webhook data using dot notation
     */
    private function get_webhook_value($flattened_data, $webhook_field, $default_value = '') {
        if (isset($flattened_data[$webhook_field])) {
            return $flattened_data[$webhook_field];
        }
        
        return $default_value;
    }
    
    /**
     * Process field value based on WordPress field type
     */
    private function process_field_value($value, $wp_field, $post_type) {
        // Skip empty values except for specific fields
        if (empty($value) && !in_array($wp_field, array('post_content', 'post_excerpt'))) {
            return null;
        }
        
        switch ($wp_field) {
            case 'post_title':
                return sanitize_text_field($value);
                
            case 'post_content':
                return wp_kses_post($value);
                
            case 'post_excerpt':
                return sanitize_textarea_field($value);
                
            case 'post_status':
                $allowed_statuses = array('draft', 'publish', 'private', 'pending');
                return in_array($value, $allowed_statuses) ? $value : 'draft';
                
            case 'featured_image':
                return esc_url_raw($value);
                
            default:
                // Handle meta fields
                if (strpos($wp_field, 'meta_') === 0) {
                    return sanitize_text_field($value);
                }
                
                // Handle taxonomy fields
                if (strpos($wp_field, 'tax_') === 0) {
                    if (is_array($value)) {
                        return array_map('sanitize_text_field', $value);
                    } else {
                        return array_map('trim', explode(',', sanitize_text_field($value)));
                    }
                }
                
                return sanitize_text_field($value);
        }
    }
    
    /**
     * Validate mapped data
     */
    private function validate_mapped_data($mapped_data, $post_type) {
        $errors = array();
        
        // Check required fields
        if (empty($mapped_data['post_title'])) {
            $errors[] = 'Post title is required';
        }
        
        // Validate post status
        if (isset($mapped_data['post_status'])) {
            $allowed_statuses = array('draft', 'publish', 'private', 'pending');
            if (!in_array($mapped_data['post_status'], $allowed_statuses)) {
                $errors[] = 'Invalid post status';
            }
        }
        
        // Validate featured image URL if provided
        if (isset($mapped_data['featured_image']) && !empty($mapped_data['featured_image'])) {
            if (!filter_var($mapped_data['featured_image'], FILTER_VALIDATE_URL)) {
                $errors[] = 'Invalid featured image URL';
            }
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }
    
    /**
     * Get available WordPress fields for a post type
     */
    public function get_available_wp_fields($post_type) {
        $fields = array(
            // Core post fields
            'post_title' => array(
                'label' => 'Post Title',
                'type' => 'text',
                'required' => true,
                'group' => 'Core Fields'
            ),
            'post_content' => array(
                'label' => 'Post Content',
                'type' => 'textarea',
                'required' => false,
                'group' => 'Core Fields'
            ),
            'post_excerpt' => array(
                'label' => 'Post Excerpt',
                'type' => 'textarea',
                'required' => false,
                'group' => 'Core Fields'
            ),
            'post_status' => array(
                'label' => 'Post Status',
                'type' => 'select',
                'options' => array('draft' => 'Draft', 'publish' => 'Published', 'private' => 'Private', 'pending' => 'Pending'),
                'required' => false,
                'group' => 'Core Fields'
            ),
            'featured_image' => array(
                'label' => 'Featured Image URL',
                'type' => 'url',
                'required' => false,
                'group' => 'Core Fields'
            )
        );
        
        // Add taxonomies for this post type
        $taxonomies = get_object_taxonomies($post_type, 'objects');
        foreach ($taxonomies as $taxonomy) {
            $fields['tax_' . $taxonomy->name] = array(
                'label' => $taxonomy->labels->name,
                'type' => 'text',
                'required' => false,
                'group' => 'Taxonomies',
                'description' => 'Comma-separated values'
            );
        }
        
        // Add common meta fields
        $common_meta_fields = $this->get_common_meta_fields($post_type);
        foreach ($common_meta_fields as $meta_key => $meta_info) {
            $fields['meta_' . $meta_key] = array(
                'label' => $meta_info['label'],
                'type' => $meta_info['type'],
                'required' => false,
                'group' => 'Meta Fields'
            );
        }
        
        return $fields;
    }
    
    /**
     * Get common meta fields for a post type
     */
    private function get_common_meta_fields($post_type) {
        global $wpdb;
        $meta_fields = array();
        
        // SEO meta fields (RankMath, Yoast, etc.)
        $meta_fields['rank_math_title'] = array('label' => 'SEO Title (RankMath)', 'type' => 'text');
        $meta_fields['rank_math_description'] = array('label' => 'SEO Description (RankMath)', 'type' => 'textarea');
        $meta_fields['_yoast_wpseo_title'] = array('label' => 'SEO Title (Yoast)', 'type' => 'text');
        $meta_fields['_yoast_wpseo_metadesc'] = array('label' => 'SEO Description (Yoast)', 'type' => 'textarea');
        
        // Get actual meta fields used by this post type from database
        $actual_meta_fields = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT pm.meta_key 
             FROM {$wpdb->postmeta} pm 
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
             WHERE p.post_type = %s 
             AND pm.meta_key NOT LIKE '\_%' 
             AND pm.meta_key NOT LIKE '%revision%'
             AND pm.meta_value != ''
             ORDER BY pm.meta_key",
            $post_type
        ));
        
        foreach ($actual_meta_fields as $meta_field) {
            $key = $meta_field->meta_key;
            $label = $this->format_meta_field_label($key);
            $meta_fields[$key] = array('label' => $label, 'type' => 'text');
        }
        
        // Custom post type specific fields
        if ($post_type === 'lowongan-kerja' || $post_type === 'job') {
            $meta_fields['nexjob_nama_perusahaan'] = array('label' => 'Company Name', 'type' => 'text');
            $meta_fields['nexjob_lokasi_kota'] = array('label' => 'City Location', 'type' => 'text');
            $meta_fields['nexjob_gaji'] = array('label' => 'Salary', 'type' => 'text');
            $meta_fields['nexjob_tipe_kerja'] = array('label' => 'Work Type', 'type' => 'text');
            $meta_fields['company_name'] = array('label' => 'Company Name', 'type' => 'text');
            $meta_fields['job_location'] = array('label' => 'Job Location', 'type' => 'text');
            $meta_fields['job_salary'] = array('label' => 'Job Salary', 'type' => 'text');
            $meta_fields['job_type'] = array('label' => 'Job Type', 'type' => 'text');
            $meta_fields['job_category'] = array('label' => 'Job Category', 'type' => 'text');
            $meta_fields['application_deadline'] = array('label' => 'Application Deadline', 'type' => 'date');
        }
        
        // Common useful custom fields
        $meta_fields['price'] = array('label' => 'Price', 'type' => 'text');
        $meta_fields['address'] = array('label' => 'Address', 'type' => 'text');
        $meta_fields['phone'] = array('label' => 'Phone', 'type' => 'text');
        $meta_fields['email'] = array('label' => 'Email', 'type' => 'email');
        $meta_fields['website'] = array('label' => 'Website', 'type' => 'url');
        $meta_fields['description'] = array('label' => 'Additional Description', 'type' => 'textarea');
        
        return $meta_fields;
    }
    
    /**
     * Format meta field key into readable label
     */
    private function format_meta_field_label($key) {
        // Remove common prefixes
        $label = preg_replace('/^(nexjob_|job_|meta_|custom_)/', '', $key);
        
        // Replace underscores and hyphens with spaces
        $label = str_replace(array('_', '-'), ' ', $label);
        
        // Capitalize words
        $label = ucwords($label);
        
        return $label;
    }
    
    /**
     * Generate field mapping suggestions based on field names
     */
    public function suggest_field_mappings($webhook_fields, $post_type) {
        $suggestions = array();
        $wp_fields = $this->get_available_wp_fields($post_type);
        
        foreach ($webhook_fields as $webhook_field) {
            $suggestion = $this->find_best_field_match($webhook_field, array_keys($wp_fields));
            if ($suggestion) {
                $suggestions[] = array(
                    'webhook_field' => $webhook_field,
                    'suggested_wp_field' => $suggestion,
                    'confidence' => $this->calculate_match_confidence($webhook_field, $suggestion)
                );
            }
        }
        
        // Sort by confidence
        usort($suggestions, function($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });
        
        return $suggestions;
    }
    
    /**
     * Find best field match using string similarity
     */
    private function find_best_field_match($webhook_field, $wp_fields) {
        $best_match = null;
        $best_score = 0;
        
        $webhook_field_clean = strtolower(str_replace(array('_', '-', '.'), ' ', $webhook_field));
        
        foreach ($wp_fields as $wp_field) {
            $wp_field_clean = strtolower(str_replace(array('_', '-', 'meta_', 'tax_'), ' ', $wp_field));
            
            // Calculate similarity
            $score = 0;
            
            // Exact match bonus
            if ($webhook_field_clean === $wp_field_clean) {
                $score += 100;
            }
            
            // Contains match
            if (strpos($webhook_field_clean, $wp_field_clean) !== false || strpos($wp_field_clean, $webhook_field_clean) !== false) {
                $score += 50;
            }
            
            // Common keywords
            $common_keywords = array(
                'title' => array('post_title'),
                'content' => array('post_content'),
                'description' => array('post_content', 'post_excerpt'),
                'excerpt' => array('post_excerpt'),
                'status' => array('post_status'),
                'image' => array('featured_image'),
                'category' => array('tax_category'),
                'tag' => array('tax_post_tag'),
                'company' => array('meta_nexjob_nama_perusahaan'),
                'location' => array('meta_nexjob_lokasi_kota'),
                'city' => array('meta_nexjob_lokasi_kota'),
                'salary' => array('meta_nexjob_gaji'),
                'work' => array('meta_nexjob_tipe_kerja')
            );
            
            foreach ($common_keywords as $keyword => $fields) {
                if (strpos($webhook_field_clean, $keyword) !== false && in_array($wp_field, $fields)) {
                    $score += 30;
                }
            }
            
            if ($score > $best_score) {
                $best_score = $score;
                $best_match = $wp_field;
            }
        }
        
        return $best_score > 20 ? $best_match : null;
    }
    
    /**
     * Calculate match confidence percentage
     */
    private function calculate_match_confidence($webhook_field, $wp_field) {
        $webhook_field_clean = strtolower(str_replace(array('_', '-', '.'), ' ', $webhook_field));
        $wp_field_clean = strtolower(str_replace(array('_', '-', 'meta_', 'tax_'), ' ', $wp_field));
        
        if ($webhook_field_clean === $wp_field_clean) {
            return 100;
        }
        
        if (strpos($webhook_field_clean, $wp_field_clean) !== false || strpos($wp_field_clean, $webhook_field_clean) !== false) {
            return 80;
        }
        
        // Use similar_text for basic similarity
        similar_text($webhook_field_clean, $wp_field_clean, $percent);
        return round($percent);
    }
}