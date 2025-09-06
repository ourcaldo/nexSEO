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
     * Add automation admin menu pages
     */
    public function add_automation_admin_menu() {
        // Add automation page (hidden from menu, called from main admin)
        add_submenu_page(
            null, // Hidden from menu
            __('Add Automation', 'nexjob-seo'),
            __('Add Automation', 'nexjob-seo'),
            'manage_options',
            'nexjob-seo-add-automation',
            array($this, 'add_automation_page')
        );
        
        // Configure automation page
        add_submenu_page(
            null, // Hidden from menu
            __('Configure Automation', 'nexjob-seo'),
            __('Configure Automation', 'nexjob-seo'),
            'manage_options',
            'nexjob-seo-configure-automation',
            array($this, 'configure_automation_page')
        );
    }
    
    /**
     * Render automation management page
     */
    public function render_automation_page() {
        $automations = $this->automation_manager->get_automations();
        ?>
        <div class="wrap">
            <h1>
                <?php _e('Featured Image Automations', 'nexjob-seo'); ?>
                <a href="<?php echo admin_url('admin.php?page=nexjob-seo-add-automation'); ?>" class="page-title-action">
                    <?php _e('Add New', 'nexjob-seo'); ?>
                </a>
            </h1>
            
            <?php if (empty($automations)): ?>
                <div class="notice notice-info">
                    <p><?php _e('No automations found. Create your first automation to start generating featured images.', 'nexjob-seo'); ?></p>
                </div>
            <?php else: ?>
            
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
                            <a href="<?php echo admin_url('admin.php?page=nexjob-seo-configure-automation&automation_id=' . $automation->id); ?>" class="button button-small">
                                <?php _e('Configure', 'nexjob-seo'); ?>
                            </a>
                            <a href="<?php echo add_query_arg(array('action' => 'toggle_automation', 'automation_id' => $automation->id, 'nonce' => wp_create_nonce('automation_action'))); ?>" class="button button-small">
                                <?php echo $automation->status === 'active' ? __('Disable', 'nexjob-seo') : __('Enable', 'nexjob-seo'); ?>
                            </a>
                            <a href="<?php echo add_query_arg(array('action' => 'delete_automation', 'automation_id' => $automation->id, 'nonce' => wp_create_nonce('automation_action'))); ?>" 
                               class="button button-small button-link-delete" 
                               onclick="return confirm('<?php _e('Are you sure you want to delete this automation?', 'nexjob-seo'); ?>')">
                                <?php _e('Delete', 'nexjob-seo'); ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Add automation page
     */
    public function add_automation_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Add New Automation', 'nexjob-seo'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('create_automation', 'automation_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="automation_name"><?php _e('Automation Name', 'nexjob-seo'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="automation_name" name="automation_name" class="regular-text" required>
                            <p class="description"><?php _e('Give this automation a descriptive name.', 'nexjob-seo'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="automation_description"><?php _e('Description', 'nexjob-seo'); ?></label>
                        </th>
                        <td>
                            <textarea id="automation_description" name="automation_description" class="large-text" rows="3"></textarea>
                            <p class="description"><?php _e('Optional description for this automation.', 'nexjob-seo'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php _e('Create Automation', 'nexjob-seo'); ?></button>
                    <a href="<?php echo admin_url('admin.php?page=nexjob-seo-automations'); ?>" class="button"><?php _e('Cancel', 'nexjob-seo'); ?></a>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Configure automation page
     */
    public function configure_automation_page() {
        $automation_id = intval($_GET['automation_id'] ?? 0);
        $automation = $this->automation_manager->get_automation($automation_id);
        
        if (!$automation) {
            wp_die(__('Automation not found.', 'nexjob-seo'));
        }
        
        $templates = $this->template_manager->get_available_templates();
        $post_types = get_post_types(array('public' => true), 'objects');
        ?>
        <div class="wrap">
            <h1><?php printf(__('Configure Automation: %s', 'nexjob-seo'), esc_html($automation->name)); ?></h1>
            
            <div class="automation-config-container">
                <!-- Step 1: Basic Settings -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Step 1: Basic Settings', 'nexjob-seo'); ?></h2>
                    <div class="inside">
                        <form method="post" action="">
                            <?php wp_nonce_field('configure_automation', 'automation_config_nonce'); ?>
                            <input type="hidden" name="automation_id" value="<?php echo $automation_id; ?>">
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="automation_name"><?php _e('Name', 'nexjob-seo'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="automation_name" name="name" value="<?php echo esc_attr($automation->name); ?>" class="regular-text" required>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="automation_status"><?php _e('Status', 'nexjob-seo'); ?></label>
                                    </th>
                                    <td>
                                        <select id="automation_status" name="status">
                                            <option value="active" <?php selected($automation->status, 'active'); ?>><?php _e('Active', 'nexjob-seo'); ?></option>
                                            <option value="inactive" <?php selected($automation->status, 'inactive'); ?>><?php _e('Inactive', 'nexjob-seo'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                            
                            <p class="submit">
                                <button type="submit" class="button button-primary"><?php _e('Save Settings', 'nexjob-seo'); ?></button>
                            </p>
                        </form>
                    </div>
                </div>
                
                <!-- Step 2: Post Types -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Step 2: Select Post Types', 'nexjob-seo'); ?></h2>
                    <div class="inside">
                        <form method="post" action="">
                            <?php wp_nonce_field('configure_automation', 'automation_config_nonce'); ?>
                            <input type="hidden" name="automation_id" value="<?php echo $automation_id; ?>">
                            
                            <p><?php _e('Select which post types this automation should apply to:', 'nexjob-seo'); ?></p>
                            
                            <?php foreach ($post_types as $post_type): ?>
                                <label style="display: block; margin-bottom: 5px;">
                                    <input type="checkbox" name="post_types[]" value="<?php echo esc_attr($post_type->name); ?>"
                                           <?php checked(in_array($post_type->name, $automation->post_types ?? [])); ?>>
                                    <?php echo esc_html($post_type->label); ?>
                                </label>
                            <?php endforeach; ?>
                            
                            <p class="submit">
                                <button type="submit" class="button button-primary"><?php _e('Save Post Types', 'nexjob-seo'); ?></button>
                            </p>
                        </form>
                    </div>
                </div>
                
                <!-- Step 3: Template & Styling -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Step 3: Template & Styling', 'nexjob-seo'); ?></h2>
                    <div class="inside">
                        <form method="post" action="">
                            <?php wp_nonce_field('configure_automation', 'automation_config_nonce'); ?>
                            <input type="hidden" name="automation_id" value="<?php echo $automation_id; ?>">
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="template_name"><?php _e('Template', 'nexjob-seo'); ?></label>
                                    </th>
                                    <td>
                                        <select id="template_name" name="template_name">
                                            <?php foreach ($templates as $template_id => $template_data): ?>
                                                <option value="<?php echo esc_attr($template_id); ?>" <?php selected($automation->template_name, $template_id); ?>>
                                                    <?php echo esc_html($template_data['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="font_size"><?php _e('Font Size', 'nexjob-seo'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" id="font_size" name="font_size" value="<?php echo esc_attr($automation->font_size ?? 48); ?>" min="16" max="120">
                                        <p class="description"><?php _e('Font size in pixels', 'nexjob-seo'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="font_color"><?php _e('Font Color', 'nexjob-seo'); ?></label>
                                    </th>
                                    <td>
                                        <input type="color" id="font_color" name="font_color" value="<?php echo esc_attr($automation->font_color ?? '#FFFFFF'); ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="text_align"><?php _e('Text Alignment', 'nexjob-seo'); ?></label>
                                    </th>
                                    <td>
                                        <select id="text_align" name="text_align">
                                            <option value="left" <?php selected($automation->text_align ?? 'left', 'left'); ?>><?php _e('Left', 'nexjob-seo'); ?></option>
                                            <option value="center" <?php selected($automation->text_align ?? 'center', 'center'); ?>><?php _e('Center', 'nexjob-seo'); ?></option>
                                            <option value="right" <?php selected($automation->text_align ?? 'right', 'right'); ?>><?php _e('Right', 'nexjob-seo'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                            
                            <h3><?php _e('Text Area Position', 'nexjob-seo'); ?></h3>
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php _e('Position', 'nexjob-seo'); ?></th>
                                    <td>
                                        <label>X: <input type="number" name="text_area_x" value="<?php echo esc_attr($automation->text_area_x ?? 50); ?>" min="0" max="1200" style="width: 80px;"></label>
                                        <label>Y: <input type="number" name="text_area_y" value="<?php echo esc_attr($automation->text_area_y ?? 100); ?>" min="0" max="800" style="width: 80px;"></label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e('Size', 'nexjob-seo'); ?></th>
                                    <td>
                                        <label>Width: <input type="number" name="text_area_width" value="<?php echo esc_attr($automation->text_area_width ?? 1100); ?>" min="200" max="1200" style="width: 80px;"></label>
                                        <label>Height: <input type="number" name="text_area_height" value="<?php echo esc_attr($automation->text_area_height ?? 430); ?>" min="100" max="800" style="width: 80px;"></label>
                                    </td>
                                </tr>
                            </table>
                            
                            <p class="submit">
                                <button type="submit" class="button button-primary"><?php _e('Save Template Settings', 'nexjob-seo'); ?></button>
                            </p>
                        </form>
                    </div>
                </div>
                
                <!-- Step 4: Preview Studio -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Step 4: Preview Studio', 'nexjob-seo'); ?></h2>
                    <div class="inside">
                        <p><?php _e('Preview how your featured images will look:', 'nexjob-seo'); ?></p>
                        
                        <div style="margin-bottom: 20px;">
                            <label for="preview_title"><?php _e('Sample Title:', 'nexjob-seo'); ?></label><br>
                            <input type="text" id="preview_title" value="Sample Job Title - Marketing Manager Position" style="width: 100%; max-width: 500px;">
                            <button type="button" id="generate_preview" class="button"><?php _e('Generate Preview', 'nexjob-seo'); ?></button>
                        </div>
                        
                        <div id="preview_container" style="border: 1px solid #ddd; padding: 20px; background: #f9f9f9; text-align: center;">
                            <p><?php _e('Click "Generate Preview" to see your featured image preview', 'nexjob-seo'); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Step 5: Apply to Existing Posts -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Step 5: Apply to Existing Posts', 'nexjob-seo'); ?></h2>
                    <div class="inside">
                        <p><?php _e('Apply this automation to existing posts that don\'t have featured images:', 'nexjob-seo'); ?></p>
                        
                        <button type="button" id="apply_to_existing" class="button button-secondary" data-automation-id="<?php echo $automation_id; ?>">
                            <?php _e('Process Existing Posts', 'nexjob-seo'); ?>
                        </button>
                        
                        <div id="process_progress" style="display: none; margin-top: 10px;">
                            <progress id="progress_bar" max="100" value="0" style="width: 100%;"></progress>
                            <p id="progress_text">Processing...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Generate preview
            $('#generate_preview').click(function() {
                var title = $('#preview_title').val();
                var automationId = <?php echo $automation_id; ?>;
                
                $('#preview_container').html('<p>Generating preview...</p>');
                
                $.post(ajaxurl, {
                    action: 'nexjob_generate_preview',
                    automation_id: automationId,
                    sample_title: title,
                    nonce: '<?php echo wp_create_nonce('automation_preview'); ?>'
                }, function(response) {
                    if (response.success) {
                        $('#preview_container').html('<img src="' + response.data.preview_url + '" style="max-width: 100%; height: auto; border: 1px solid #ccc;">');
                    } else {
                        $('#preview_container').html('<p style="color: red;">Error: ' + response.data.message + '</p>');
                    }
                });
            });
            
            // Apply to existing posts
            $('#apply_to_existing').click(function() {
                var automationId = $(this).data('automation-id');
                $(this).prop('disabled', true);
                $('#process_progress').show();
                
                $.post(ajaxurl, {
                    action: 'nexjob_apply_automation_to_existing',
                    automation_id: automationId,
                    nonce: '<?php echo wp_create_nonce('automation_process'); ?>'
                }, function(response) {
                    if (response.success) {
                        $('#progress_text').text('Processing ' + response.data.total_posts + ' posts...');
                        monitorProgress(response.data.batch_id);
                    } else {
                        $('#progress_text').text('Error: ' + response.data.message);
                    }
                });
            });
            
            function monitorProgress(batchId) {
                $.post(ajaxurl, {
                    action: 'nexjob_get_batch_status',
                    batch_id: batchId,
                    nonce: '<?php echo wp_create_nonce('automation_process'); ?>'
                }, function(response) {
                    if (response.success) {
                        var status = response.data;
                        $('#progress_bar').val(status.progress_percentage);
                        $('#progress_text').text(status.processed + '/' + status.total + ' processed (' + status.successful + ' successful)');
                        
                        if (status.status === 'completed') {
                            $('#apply_to_existing').prop('disabled', false);
                            $('#progress_text').text('Completed! ' + status.successful + ' images generated.');
                        } else {
                            setTimeout(function() { monitorProgress(batchId); }, 2000);
                        }
                    }
                });
            }
        });
        </script>
        <?php
    }
    
    /**
     * Handle automation admin actions
     */
    public function handle_automation_admin_actions() {
        if (!current_user_can('manage_options')) return;
        
        // Create automation
        if (isset($_POST['automation_nonce'])) {
            if (wp_verify_nonce($_POST['automation_nonce'], 'create_automation')) {
                $name = sanitize_text_field($_POST['automation_name']);
                $description = sanitize_textarea_field($_POST['automation_description']);
                
                $result = $this->automation_manager->create_automation($name, $description);
                
                if ($result['success']) {
                    wp_redirect(admin_url('admin.php?page=nexjob-seo-configure-automation&automation_id=' . $result['automation_id'] . '&message=created'));
                    exit;
                } else {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-error"><p>' . __('Failed to create automation.', 'nexjob-seo') . '</p></div>';
                    });
                }
            }
        }
        
        // Configure automation
        if (isset($_POST['automation_config_nonce'])) {
            if (wp_verify_nonce($_POST['automation_config_nonce'], 'configure_automation')) {
                $automation_id = intval($_POST['automation_id']);
                
                // Collect form data with defaults
                $data = array(
                    'name' => isset($_POST['name']) ? sanitize_text_field($_POST['name']) : 'Untitled Automation',
                    'status' => isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'inactive',
                    'post_types' => isset($_POST['post_types']) ? array_map('sanitize_text_field', $_POST['post_types']) : array('post'),
                    'template_name' => isset($_POST['template_name']) ? sanitize_text_field($_POST['template_name']) : 'default.png',
                    'font_size' => isset($_POST['font_size']) ? intval($_POST['font_size']) : 48,
                    'font_color' => isset($_POST['font_color']) ? sanitize_text_field($_POST['font_color']) : '#FFFFFF',
                    'text_align' => isset($_POST['text_align']) ? sanitize_text_field($_POST['text_align']) : 'center',
                    'text_area_x' => isset($_POST['text_area_x']) ? intval($_POST['text_area_x']) : 50,
                    'text_area_y' => isset($_POST['text_area_y']) ? intval($_POST['text_area_y']) : 100,
                    'text_area_width' => isset($_POST['text_area_width']) ? intval($_POST['text_area_width']) : 1100,
                    'text_area_height' => isset($_POST['text_area_height']) ? intval($_POST['text_area_height']) : 430,
                    'max_title_length' => isset($_POST['max_title_length']) ? intval($_POST['max_title_length']) : 80,
                    'apply_to_existing' => isset($_POST['apply_to_existing']) ? 1 : 0
                );
                
                $result = $this->automation_manager->update_automation_config($automation_id, $data);
                
                if ($result) {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-success"><p>' . __('Automation updated successfully.', 'nexjob-seo') . '</p></div>';
                    });
                } else {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-error"><p>' . __('Failed to update automation.', 'nexjob-seo') . '</p></div>';
                    });
                }
            }
        }
        
        // Handle URL actions
        if (isset($_GET['action'])) {
            $action = $_GET['action'];
            $automation_id = intval($_GET['automation_id'] ?? 0);
            $nonce = $_GET['nonce'] ?? '';
            
            if (!wp_verify_nonce($nonce, 'automation_action')) {
                wp_die(__('Security check failed.', 'nexjob-seo'));
            }
            
            switch ($action) {
                case 'toggle_automation':
                    $automation = $this->automation_manager->get_automation($automation_id);
                    if ($automation) {
                        $new_status = $automation->status === 'active' ? 'inactive' : 'active';
                        $this->automation_manager->update_automation($automation_id, array('status' => $new_status));
                    }
                    wp_redirect(admin_url('admin.php?page=nexjob-seo-automations'));
                    exit;
                    break;
                    
                case 'delete_automation':
                    $this->automation_manager->delete_automation($automation_id);
                    wp_redirect(admin_url('admin.php?page=nexjob-seo-automations&message=deleted'));
                    exit;
                    break;
            }
        }
    }
    
    // Old modal code and AJAX handlers removed - now using page-based interface
    
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