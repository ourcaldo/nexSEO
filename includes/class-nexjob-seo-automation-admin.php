<?php
/**
 * Admin interface for featured image automation management
 */

if (!defined('ABSPATH')) {
    exit;
}

class NexJob_SEO_Automation_Admin {
    
    private $logger;
    private $automation_manager;
    private $template_manager;
    private $auto_featured_image;
    
    public function __construct($logger, $automation_manager, $template_manager, $auto_featured_image) {
        $this->logger = $logger;
        $this->automation_manager = $automation_manager;
        $this->template_manager = $template_manager;
        $this->auto_featured_image = $auto_featured_image;
        
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_create_automation', array($this, 'handle_create_automation'));
        add_action('wp_ajax_update_automation', array($this, 'handle_update_automation'));
        add_action('wp_ajax_delete_automation', array($this, 'handle_delete_automation'));
        add_action('wp_ajax_toggle_automation', array($this, 'handle_toggle_automation'));
        add_action('wp_ajax_get_automation', array($this, 'handle_get_automation'));
        add_action('wp_ajax_test_automation', array($this, 'handle_test_automation'));
        add_action('wp_ajax_process_existing_posts', array($this, 'handle_process_existing_posts'));
    }
    
    /**
     * Render automation management page
     */
    public function render_automation_page() {
        $automations = $this->automation_manager->get_automations();
        $templates = $this->template_manager->get_available_templates();
        ?>
        <div class="wrap">
            <h1><?php _e('Featured Image Automations', 'nexjob-seo'); ?></h1>
            
            <div class="automation-controls" style="margin-bottom: 20px;">
                <button id="create-automation-btn" class="button button-primary">
                    <?php _e('Add New', 'nexjob-seo'); ?>
                </button>
                <button id="upload-template-btn" class="button">
                    <?php _e('Upload Template', 'nexjob-seo'); ?>
                </button>
            </div>
            
            <!-- Automations List -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Name', 'nexjob-seo'); ?></th>
                        <th><?php _e('Status', 'nexjob-seo'); ?></th>
                        <th><?php _e('Post Types', 'nexjob-seo'); ?></th>
                        <th><?php _e('Template', 'nexjob-seo'); ?></th>
                        <th><?php _e('Created', 'nexjob-seo'); ?></th>
                        <th><?php _e('Actions', 'nexjob-seo'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($automations as $automation): ?>
                    <tr>
                        <td><strong><?php echo esc_html($automation->name); ?></strong></td>
                        <td>
                            <span class="status-badge status-<?php echo esc_attr($automation->status); ?>">
                                <?php echo esc_html(ucfirst($automation->status)); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html(implode(', ', $automation->post_types)); ?></td>
                        <td><?php echo esc_html($automation->template_name); ?></td>
                        <td><?php echo esc_html(date('M j, Y', strtotime($automation->created_at))); ?></td>
                        <td>
                            <button class="button button-small edit-automation" data-id="<?php echo $automation->id; ?>">
                                <?php _e('Edit', 'nexjob-seo'); ?>
                            </button>
                            <button class="button button-small toggle-automation" data-id="<?php echo $automation->id; ?>">
                                <?php echo $automation->status === 'active' ? __('Disable', 'nexjob-seo') : __('Enable', 'nexjob-seo'); ?>
                            </button>
                            <button class="button button-small test-automation" data-id="<?php echo $automation->id; ?>">
                                <?php _e('Test', 'nexjob-seo'); ?>
                            </button>
                            <button class="button button-small button-link-delete delete-automation" data-id="<?php echo $automation->id; ?>">
                                <?php _e('Delete', 'nexjob-seo'); ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Create/Edit Automation Modal -->
        <div id="automation-modal" class="automation-modal" style="display: none;">
            <div class="automation-modal-content">
                <span class="automation-close">&times;</span>
                <h2 id="modal-title"><?php _e('Create Automation', 'nexjob-seo'); ?></h2>
                
                <form id="automation-form">
                    <input type="hidden" id="automation-id" name="automation_id" value="">
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="automation-name"><?php _e('Automation Name', 'nexjob-seo'); ?></label></th>
                            <td><input type="text" id="automation-name" name="name" class="regular-text" required></td>
                        </tr>
                        
                        <tr>
                            <th><label for="automation-status"><?php _e('Status', 'nexjob-seo'); ?></label></th>
                            <td>
                                <select id="automation-status" name="status">
                                    <option value="active"><?php _e('Active', 'nexjob-seo'); ?></option>
                                    <option value="inactive"><?php _e('Inactive', 'nexjob-seo'); ?></option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><label><?php _e('Post Types', 'nexjob-seo'); ?></label></th>
                            <td>
                                <?php
                                $post_types = get_post_types(array('public' => true), 'objects');
                                foreach ($post_types as $post_type) {
                                    echo '<label><input type="checkbox" name="post_types[]" value="' . esc_attr($post_type->name) . '"> ' . esc_html($post_type->label) . '</label><br>';
                                }
                                ?>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><label for="automation-template"><?php _e('Template', 'nexjob-seo'); ?></label></th>
                            <td>
                                <select id="automation-template" name="template_name">
                                    <?php foreach ($templates as $template): ?>
                                    <option value="<?php echo esc_attr($template); ?>"><?php echo esc_html($template); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><label for="font-size"><?php _e('Font Size', 'nexjob-seo'); ?></label></th>
                            <td><input type="number" id="font-size" name="font_size" value="48" min="16" max="120"></td>
                        </tr>
                        
                        <tr>
                            <th><label for="font-color"><?php _e('Font Color', 'nexjob-seo'); ?></label></th>
                            <td><input type="color" id="font-color" name="font_color" value="#FFFFFF"></td>
                        </tr>
                        
                        <tr>
                            <th><label for="text-align"><?php _e('Text Alignment', 'nexjob-seo'); ?></label></th>
                            <td>
                                <select id="text-align" name="text_align">
                                    <option value="left"><?php _e('Left', 'nexjob-seo'); ?></option>
                                    <option value="center"><?php _e('Center', 'nexjob-seo'); ?></option>
                                    <option value="right"><?php _e('Right', 'nexjob-seo'); ?></option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><label><?php _e('Text Area Position', 'nexjob-seo'); ?></label></th>
                            <td>
                                <label>X: <input type="number" name="text_area_x" value="50" min="0" max="1200"></label>
                                <label>Y: <input type="number" name="text_area_y" value="100" min="0" max="800"></label><br>
                                <label>Width: <input type="number" name="text_area_width" value="1100" min="200" max="1200"></label>
                                <label>Height: <input type="number" name="text_area_height" value="430" min="100" max="800"></label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th><label for="max-title-length"><?php _e('Max Title Length', 'nexjob-seo'); ?></label></th>
                            <td><input type="number" id="max-title-length" name="max_title_length" value="80" min="20" max="200"></td>
                        </tr>
                        
                        <tr>
                            <th><label for="apply-existing"><?php _e('Apply to Existing Posts', 'nexjob-seo'); ?></label></th>
                            <td><input type="checkbox" id="apply-existing" name="apply_to_existing" value="1"></td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php _e('Save Automation', 'nexjob-seo'); ?></button>
                        <button type="button" class="button automation-cancel"><?php _e('Cancel', 'nexjob-seo'); ?></button>
                    </p>
                </form>
            </div>
        </div>
        
        <style>
        .automation-modal {
            position: fixed;
            z-index: 100000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.4);
        }
        
        .automation-modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .automation-close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .status-badge {
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-active {
            background-color: #46b450;
            color: white;
        }
        
        .status-inactive {
            background-color: #dc3232;
            color: white;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Modal controls
            $('#create-automation-btn').click(function() {
                $('#modal-title').text('<?php _e('Create Automation', 'nexjob-seo'); ?>');
                $('#automation-form')[0].reset();
                $('#automation-id').val('');
                $('#automation-modal').show();
            });
            
            $('.edit-automation').click(function() {
                var id = $(this).data('id');
                $('#modal-title').text('<?php _e('Edit Automation', 'nexjob-seo'); ?>');
                $('#automation-id').val(id);
                loadAutomationData(id);
                $('#automation-modal').show();
            });
            
            $('.automation-close, .automation-cancel').click(function() {
                $('#automation-modal').hide();
            });
            
            // Form submission
            $('#automation-form').submit(function(e) {
                e.preventDefault();
                var formData = new FormData(this);
                var automationId = $('#automation-id').val();
                
                var action = automationId ? 'update_automation' : 'create_automation';
                formData.append('action', action);
                formData.append('nonce', '<?php echo wp_create_nonce('automation_nonce'); ?>');
                
                if (automationId) {
                    formData.append('automation_id', automationId);
                }
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            $('#automation-modal').hide();
                            location.reload();
                        } else {
                            alert('Error: ' + response.data);
                        }
                    }
                });
            });
            
            // Toggle automation
            $('.toggle-automation').click(function() {
                var id = $(this).data('id');
                $.post(ajaxurl, {
                    action: 'toggle_automation',
                    automation_id: id,
                    nonce: '<?php echo wp_create_nonce('automation_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    }
                });
            });
            
            // Delete automation
            $('.delete-automation').click(function() {
                if (!confirm('<?php _e('Are you sure you want to delete this automation?', 'nexjob-seo'); ?>')) {
                    return;
                }
                
                var id = $(this).data('id');
                $.post(ajaxurl, {
                    action: 'delete_automation',
                    automation_id: id,
                    nonce: '<?php echo wp_create_nonce('automation_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    }
                });
            });
            
            // Test automation
            $('.test-automation').click(function() {
                var id = $(this).data('id');
                $(this).text('Testing...').prop('disabled', true);
                
                $.post(ajaxurl, {
                    action: 'test_automation',
                    automation_id: id,
                    nonce: '<?php echo wp_create_nonce('automation_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('Test successful! Sample image generated.');
                    } else {
                        alert('Test failed: ' + response.data);
                    }
                    location.reload();
                });
            });
            
            function loadAutomationData(id) {
                $.post(ajaxurl, {
                    action: 'get_automation',
                    automation_id: id,
                    nonce: '<?php echo wp_create_nonce('automation_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        var data = response.data;
                        $('#automation-name').val(data.name);
                        $('#automation-status').val(data.status);
                        $('#automation-template').val(data.template_name);
                        $('#font-size').val(data.font_size);
                        $('#font-color').val(data.font_color);
                        $('#text-align').val(data.text_align);
                        $('input[name="text_area_x"]').val(data.text_area_x);
                        $('input[name="text_area_y"]').val(data.text_area_y);
                        $('input[name="text_area_width"]').val(data.text_area_width);
                        $('input[name="text_area_height"]').val(data.text_area_height);
                        $('#max-title-length').val(data.max_title_length);
                        $('#apply-existing').prop('checked', data.apply_to_existing == 1);
                        
                        // Set post types
                        $('input[name="post_types[]"]').prop('checked', false);
                        if (data.post_types) {
                            data.post_types.forEach(function(type) {
                                $('input[name="post_types[]"][value="' + type + '"]').prop('checked', true);
                            });
                        }
                    }
                });
            }
        });
        </script>
        <?php
    }
    
    /**
     * Handle create automation AJAX
     */
    public function handle_create_automation() {
        check_ajax_referer('automation_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'nexjob-seo'));
        }
        
        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'status' => sanitize_text_field($_POST['status']),
            'post_types' => isset($_POST['post_types']) ? $_POST['post_types'] : array(),
            'template_name' => sanitize_text_field($_POST['template_name']),
            'font_size' => intval($_POST['font_size']),
            'font_color' => sanitize_text_field($_POST['font_color']),
            'text_align' => sanitize_text_field($_POST['text_align']),
            'text_area_x' => intval($_POST['text_area_x']),
            'text_area_y' => intval($_POST['text_area_y']),
            'text_area_width' => intval($_POST['text_area_width']),
            'text_area_height' => intval($_POST['text_area_height']),
            'max_title_length' => intval($_POST['max_title_length']),
            'apply_to_existing' => isset($_POST['apply_to_existing']) ? 1 : 0
        );
        
        $result = $this->automation_manager->create_automation($data);
        
        if ($result) {
            wp_send_json_success(array('id' => $result));
        } else {
            wp_send_json_error(__('Failed to create automation.', 'nexjob-seo'));
        }
    }
    
    /**
     * Handle update automation AJAX
     */
    public function handle_update_automation() {
        check_ajax_referer('automation_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'nexjob-seo'));
        }
        
        $id = intval($_POST['automation_id']);
        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'status' => sanitize_text_field($_POST['status']),
            'post_types' => isset($_POST['post_types']) ? $_POST['post_types'] : array(),
            'template_name' => sanitize_text_field($_POST['template_name']),
            'font_size' => intval($_POST['font_size']),
            'font_color' => sanitize_text_field($_POST['font_color']),
            'text_align' => sanitize_text_field($_POST['text_align']),
            'text_area_x' => intval($_POST['text_area_x']),
            'text_area_y' => intval($_POST['text_area_y']),
            'text_area_width' => intval($_POST['text_area_width']),
            'text_area_height' => intval($_POST['text_area_height']),
            'max_title_length' => intval($_POST['max_title_length']),
            'apply_to_existing' => isset($_POST['apply_to_existing']) ? 1 : 0
        );
        
        $result = $this->automation_manager->update_automation($id, $data);
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Failed to update automation.', 'nexjob-seo'));
        }
    }
    
    /**
     * Handle delete automation AJAX
     */
    public function handle_delete_automation() {
        check_ajax_referer('automation_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'nexjob-seo'));
        }
        
        $id = intval($_POST['automation_id']);
        $result = $this->automation_manager->delete_automation($id);
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(__('Failed to delete automation.', 'nexjob-seo'));
        }
    }
    
    /**
     * Handle toggle automation AJAX
     */
    public function handle_toggle_automation() {
        check_ajax_referer('automation_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'nexjob-seo'));
        }
        
        $id = intval($_POST['automation_id']);
        $result = $this->automation_manager->toggle_status($id);
        
        if ($result) {
            wp_send_json_success(array('new_status' => $result));
        } else {
            wp_send_json_error(__('Failed to toggle automation.', 'nexjob-seo'));
        }
    }
    
    /**
     * Handle get automation AJAX
     */
    public function handle_get_automation() {
        check_ajax_referer('automation_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'nexjob-seo'));
        }
        
        $id = intval($_POST['automation_id']);
        $automation = $this->automation_manager->get_automation($id);
        
        if ($automation) {
            wp_send_json_success($automation);
        } else {
            wp_send_json_error(__('Automation not found.', 'nexjob-seo'));
        }
    }
    
    /**
     * Handle test automation AJAX
     */
    public function handle_test_automation() {
        check_ajax_referer('automation_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'nexjob-seo'));
        }
        
        $id = intval($_POST['automation_id']);
        $automation = $this->automation_manager->get_automation($id);
        
        if (!$automation) {
            wp_send_json_error(__('Automation not found.', 'nexjob-seo'));
        }
        
        // Test with sample text
        $test_title = "Sample Test Title for Automation";
        $result = $this->auto_featured_image->generate_featured_image($test_title, $automation);
        
        if ($result) {
            wp_send_json_success(array('image_path' => $result));
        } else {
            wp_send_json_error(__('Failed to generate test image.', 'nexjob-seo'));
        }
    }
}