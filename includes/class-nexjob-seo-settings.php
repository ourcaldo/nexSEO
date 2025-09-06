<?php
/**
 * Settings manager class for handling plugin configuration
 */

if (!defined('ABSPATH')) {
    exit;
}

class NexJob_SEO_Settings {
    
    /**
     * Settings option name
     */
    const OPTION_NAME = 'nexjob_seo_settings';
    
    /**
     * Settings data
     */
    private $settings;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->load_settings();
        $this->init_hooks();
    }
    
    /**
     * Load plugin settings with defaults
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
            ),
            
            // Auto Featured Images settings
            'auto_featured_images_enabled' => true,
            'auto_featured_images_post_types' => array('post', 'page', 'lowongan-kerja'),
            'auto_featured_images_max_title_length' => 80,
            'template_min_width' => 800,
            'template_min_height' => 600,
            'template_max_size' => 5242880, // 5MB
            'featured_image_width' => 1200,
            'featured_image_height' => 630,
            'featured_image_quality' => 85,
            'batch_processing_size' => 10,
            'batch_processing_timeout' => 300
        );
        
        $this->settings = wp_parse_args(get_option(self::OPTION_NAME, array()), $defaults);
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Register settings with WordPress
     */
    public function register_settings() {
        register_setting('nexjob_seo_settings_group', self::OPTION_NAME, array(
            'sanitize_callback' => array($this, 'sanitize_settings')
        ));
        
        // Add settings sections
        add_settings_section(
            'nexjob_seo_general',
            __('General Settings', 'nexjob-seo'),
            array($this, 'general_section_callback'),
            'nexjob-seo-settings'
        );
        
        // Add settings fields
        add_settings_field(
            'post_types',
            __('Post Types', 'nexjob-seo'),
            array($this, 'post_types_callback'),
            'nexjob-seo-settings',
            'nexjob_seo_general'
        );
        
        add_settings_field(
            'cron_interval',
            __('Cron Interval', 'nexjob-seo'),
            array($this, 'cron_interval_callback'),
            'nexjob-seo-settings',
            'nexjob_seo_general'
        );
        
        add_settings_field(
            'posts_per_batch',
            __('Posts Per Batch', 'nexjob-seo'),
            array($this, 'posts_per_batch_callback'),
            'nexjob-seo-settings',
            'nexjob_seo_general'
        );
        
        add_settings_field(
            'max_posts_per_run',
            __('Max Posts Per Run', 'nexjob-seo'),
            array($this, 'max_posts_per_run_callback'),
            'nexjob-seo-settings',
            'nexjob_seo_general'
        );
        
        add_settings_field(
            'required_fields',
            __('Required Fields', 'nexjob-seo'),
            array($this, 'required_fields_callback'),
            'nexjob-seo-settings',
            'nexjob_seo_general'
        );
        
        // Auto Featured Images section
        add_settings_section(
            'nexjob_seo_featured_images',
            __('Auto Featured Images', 'nexjob-seo'),
            array($this, 'featured_images_section_callback'),
            'nexjob-seo-settings'
        );
        
        add_settings_field(
            'auto_featured_images_enabled',
            __('Enable Auto Featured Images', 'nexjob-seo'),
            array($this, 'auto_featured_images_enabled_callback'),
            'nexjob-seo-settings',
            'nexjob_seo_featured_images'
        );
        
        add_settings_field(
            'auto_featured_images_post_types',
            __('Featured Images Post Types', 'nexjob-seo'),
            array($this, 'auto_featured_images_post_types_callback'),
            'nexjob-seo-settings',
            'nexjob_seo_featured_images'
        );
        
        add_settings_field(
            'auto_featured_images_max_title_length',
            __('Max Title Length', 'nexjob-seo'),
            array($this, 'auto_featured_images_max_title_length_callback'),
            'nexjob-seo-settings',
            'nexjob_seo_featured_images'
        );
        
        add_settings_field(
            'featured_image_dimensions',
            __('Image Dimensions', 'nexjob-seo'),
            array($this, 'featured_image_dimensions_callback'),
            'nexjob-seo-settings',
            'nexjob_seo_featured_images'
        );
    }
    
    /**
     * Get a setting value
     */
    public function get($key = null, $default = null) {
        if ($key === null) {
            return $this->settings;
        }
        
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }
    
    /**
     * Set a setting value
     */
    public function set($key, $value) {
        $this->settings[$key] = $value;
        return $this->save();
    }
    
    /**
     * Save settings to database
     */
    public function save() {
        return update_option(self::OPTION_NAME, $this->settings);
    }
    
    /**
     * Sanitize settings before saving
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // Sanitize post types
        if (isset($input['post_types']) && is_array($input['post_types'])) {
            $sanitized['post_types'] = array_map('sanitize_text_field', $input['post_types']);
        }
        
        // Sanitize cron interval
        if (isset($input['cron_interval'])) {
            $sanitized['cron_interval'] = sanitize_text_field($input['cron_interval']);
        }
        
        // Sanitize numeric values
        if (isset($input['posts_per_batch'])) {
            $sanitized['posts_per_batch'] = absint($input['posts_per_batch']);
        }
        
        if (isset($input['max_posts_per_run'])) {
            $sanitized['max_posts_per_run'] = absint($input['max_posts_per_run']);
        }
        
        // Sanitize required fields
        if (isset($input['required_fields'])) {
            if (is_string($input['required_fields'])) {
                // Convert textarea input to array
                $fields = explode("\n", $input['required_fields']);
                $fields = array_map('trim', $fields);
                $fields = array_filter($fields); // Remove empty lines
                $sanitized['required_fields'] = array_map('sanitize_text_field', $fields);
            } elseif (is_array($input['required_fields'])) {
                $sanitized['required_fields'] = array_map('sanitize_text_field', $input['required_fields']);
            } else {
                $sanitized['required_fields'] = array();
            }
        } else {
            $sanitized['required_fields'] = array();
        }
        
        return wp_parse_args($sanitized, $this->settings);
    }
    
    /**
     * Settings section callback
     */
    public function general_section_callback() {
        echo '<p>' . __('Configure general settings for NexJob SEO Automation.', 'nexjob-seo') . '</p>';
    }
    
    /**
     * Post types field callback
     */
    public function post_types_callback() {
        $post_types = $this->get('post_types');
        $available_post_types = get_post_types(array('public' => true), 'objects');
        
        echo '<select name="' . self::OPTION_NAME . '[post_types][]" multiple>';
        foreach ($available_post_types as $post_type) {
            $selected = in_array($post_type->name, $post_types) ? 'selected' : '';
            echo '<option value="' . esc_attr($post_type->name) . '" ' . $selected . '>' . esc_html($post_type->label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('Select post types to process for SEO automation.', 'nexjob-seo') . '</p>';
    }
    
    /**
     * Cron interval field callback
     */
    public function cron_interval_callback() {
        $current_interval = $this->get('cron_interval');
        $intervals = array(
            'every_minute' => __('Every Minute', 'nexjob-seo'),
            'every_two_minutes' => __('Every 2 Minutes', 'nexjob-seo'),
            'every_five_minutes' => __('Every 5 Minutes', 'nexjob-seo'),
            'every_ten_minutes' => __('Every 10 Minutes', 'nexjob-seo'),
            'every_fifteen_minutes' => __('Every 15 Minutes', 'nexjob-seo'),
            'every_thirty_minutes' => __('Every 30 Minutes', 'nexjob-seo'),
            'hourly' => __('Hourly', 'nexjob-seo'),
            'daily' => __('Daily', 'nexjob-seo')
        );
        
        echo '<select name="' . self::OPTION_NAME . '[cron_interval]">';
        foreach ($intervals as $value => $label) {
            $selected = $current_interval === $value ? 'selected' : '';
            echo '<option value="' . esc_attr($value) . '" ' . $selected . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('How often should the SEO automation run automatically.', 'nexjob-seo') . '</p>';
    }
    
    /**
     * Posts per batch field callback
     */
    public function posts_per_batch_callback() {
        $value = $this->get('posts_per_batch');
        echo '<input type="number" name="' . self::OPTION_NAME . '[posts_per_batch]" value="' . esc_attr($value) . '" min="1" max="100" />';
        echo '<p class="description">' . __('Number of posts to fetch per batch.', 'nexjob-seo') . '</p>';
    }
    
    /**
     * Max posts per run field callback
     */
    public function max_posts_per_run_callback() {
        $value = $this->get('max_posts_per_run');
        echo '<input type="number" name="' . self::OPTION_NAME . '[max_posts_per_run]" value="' . esc_attr($value) . '" min="1" max="50" />';
        echo '<p class="description">' . __('Maximum number of posts to process per cron run.', 'nexjob-seo') . '</p>';
    }
    
    /**
     * Required fields callback
     */
    public function required_fields_callback() {
        $required_fields = $this->get('required_fields');
        $fields_text = is_array($required_fields) ? implode("\n", $required_fields) : '';
        echo '<textarea name="' . self::OPTION_NAME . '[required_fields]" rows="4" cols="50" placeholder="Enter field names (one per line)...">' . esc_textarea($fields_text) . '</textarea>';
        echo '<p class="description">' . __('Required custom field keys (one per line). Leave empty if no required fields.', 'nexjob-seo') . '</p>';
    }
    
    /**
     * Featured Images section callback
     */
    public function featured_images_section_callback() {
        echo '<p>' . __('Configure auto featured image generation settings.', 'nexjob-seo') . '</p>';
    }
    
    /**
     * Auto featured images enabled callback
     */
    public function auto_featured_images_enabled_callback() {
        $value = $this->get('auto_featured_images_enabled', true);
        echo '<input type="checkbox" name="' . self::OPTION_NAME . '[auto_featured_images_enabled]" value="1" ' . checked(1, $value, false) . '>';
        echo '<p class="description">' . __('Automatically generate featured images for posts that don\'t have them.', 'nexjob-seo') . '</p>';
    }
    
    /**
     * Auto featured images post types callback
     */
    public function auto_featured_images_post_types_callback() {
        $selected = $this->get('auto_featured_images_post_types', array('post', 'page'));
        $post_types = get_post_types(array('public' => true), 'objects');
        
        foreach ($post_types as $post_type) {
            $checked = in_array($post_type->name, $selected) ? 'checked' : '';
            echo '<label>';
            echo '<input type="checkbox" name="' . self::OPTION_NAME . '[auto_featured_images_post_types][]" value="' . esc_attr($post_type->name) . '" ' . $checked . '>';
            echo ' ' . esc_html($post_type->label);
            echo '</label><br>';
        }
        echo '<p class="description">' . __('Select which post types should have auto featured images generated.', 'nexjob-seo') . '</p>';
    }
    
    /**
     * Auto featured images max title length callback
     */
    public function auto_featured_images_max_title_length_callback() {
        $value = $this->get('auto_featured_images_max_title_length', 80);
        echo '<input type="number" name="' . self::OPTION_NAME . '[auto_featured_images_max_title_length]" value="' . esc_attr($value) . '" min="20" max="200">';
        echo '<p class="description">' . __('Maximum number of characters for post titles in featured images.', 'nexjob-seo') . '</p>';
    }
    
    /**
     * Featured image dimensions callback
     */
    public function featured_image_dimensions_callback() {
        $width = $this->get('featured_image_width', 1200);
        $height = $this->get('featured_image_height', 630);
        $quality = $this->get('featured_image_quality', 85);
        
        echo '<label>Width: <input type="number" name="' . self::OPTION_NAME . '[featured_image_width]" value="' . esc_attr($width) . '" min="400" max="2000"></label><br>';
        echo '<label>Height: <input type="number" name="' . self::OPTION_NAME . '[featured_image_height]" value="' . esc_attr($height) . '" min="300" max="1500"></label><br>';
        echo '<label>Quality: <input type="number" name="' . self::OPTION_NAME . '[featured_image_quality]" value="' . esc_attr($quality) . '" min="50" max="100">%</label>';
        echo '<p class="description">' . __('Output dimensions and quality for generated featured images.', 'nexjob-seo') . '</p>';
    }
}