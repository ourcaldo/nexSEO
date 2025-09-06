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
                
                // Collect form data
                $data = array();
                if (isset($_POST['name'])) $data['name'] = sanitize_text_field($_POST['name']);
                if (isset($_POST['status'])) $data['status'] = sanitize_text_field($_POST['status']);
                if (isset($_POST['post_types'])) $data['post_types'] = array_map('sanitize_text_field', $_POST['post_types']);
                if (isset($_POST['template_name'])) $data['template_name'] = sanitize_text_field($_POST['template_name']);
                if (isset($_POST['font_size'])) $data['font_size'] = intval($_POST['font_size']);
                if (isset($_POST['font_color'])) $data['font_color'] = sanitize_text_field($_POST['font_color']);
                if (isset($_POST['text_align'])) $data['text_align'] = sanitize_text_field($_POST['text_align']);
                if (isset($_POST['text_area_x'])) $data['text_area_x'] = intval($_POST['text_area_x']);
                if (isset($_POST['text_area_y'])) $data['text_area_y'] = intval($_POST['text_area_y']);
                if (isset($_POST['text_area_width'])) $data['text_area_width'] = intval($_POST['text_area_width']);
                if (isset($_POST['text_area_height'])) $data['text_area_height'] = intval($_POST['text_area_height']);
                
                $result = $this->automation_manager->update_automation($automation_id, $data);
                
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
    
    /**
     * Old modal code removed
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