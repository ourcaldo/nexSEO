<?php
/**
 * Webhook Admin Interface class for configuration UI
 */

if (!defined('ABSPATH')) {
    exit;
}

class NexJob_SEO_Webhook_Admin {
    
    /**
     * Dependencies
     */
    private $logger;
    private $webhook_manager;
    private $webhook_data;
    private $field_mapper;
    private $webhook_processor;
    
    /**
     * Constructor
     */
    public function __construct($logger, $webhook_manager, $webhook_data, $field_mapper, $webhook_processor) {
        $this->logger = $logger;
        $this->webhook_manager = $webhook_manager;
        $this->webhook_data = $webhook_data;
        $this->field_mapper = $field_mapper;
        $this->webhook_processor = $webhook_processor;
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_webhook_admin_menu'));
        add_action('admin_init', array($this, 'handle_webhook_admin_actions'));
        add_action('wp_ajax_nexjob_fetch_webhook_data', array($this, 'ajax_fetch_webhook_data'));
        add_action('wp_ajax_nexjob_process_webhook_data', array($this, 'ajax_process_webhook_data'));
        add_action('wp_ajax_nexjob_get_webhook_fields', array($this, 'ajax_get_webhook_fields'));
        add_action('wp_ajax_nexjob_get_wp_fields', array($this, 'ajax_get_wp_fields'));
        add_action('wp_ajax_nexjob_suggest_field_mappings', array($this, 'ajax_suggest_field_mappings'));
    }
    
    /**
     * Add webhook admin menu
     */
    public function add_webhook_admin_menu() {
        // Main webhooks page
        add_submenu_page(
            'nexjob-seo',
            __('Webhooks', 'nexjob-seo'),
            __('Webhooks', 'nexjob-seo'),
            'manage_options',
            'nexjob-seo-webhooks',
            array($this, 'webhooks_list_page')
        );
        
        // Add webhook page
        add_submenu_page(
            null, // Hidden from menu
            __('Add Webhook', 'nexjob-seo'),
            __('Add Webhook', 'nexjob-seo'),
            'manage_options',
            'nexjob-seo-add-webhook',
            array($this, 'add_webhook_page')
        );
        
        // Configure webhook page
        add_submenu_page(
            null, // Hidden from menu
            __('Configure Webhook', 'nexjob-seo'),
            __('Configure Webhook', 'nexjob-seo'),
            'manage_options',
            'nexjob-seo-configure-webhook',
            array($this, 'configure_webhook_page')
        );
    }
    
    /**
     * Webhooks list page
     */
    public function webhooks_list_page() {
        $webhooks = $this->webhook_manager->get_webhooks();
        ?>
        <div class="wrap">
            <h1>
                <?php _e('Webhooks', 'nexjob-seo'); ?>
                <a href="<?php echo admin_url('admin.php?page=nexjob-seo-add-webhook'); ?>" class="page-title-action">
                    <?php _e('Add New', 'nexjob-seo'); ?>
                </a>
            </h1>
            
            <?php if (empty($webhooks)): ?>
                <div class="notice notice-info">
                    <p><?php _e('No webhooks found. Create your first webhook to start receiving data.', 'nexjob-seo'); ?></p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Name', 'nexjob-seo'); ?></th>
                            <th><?php _e('URL', 'nexjob-seo'); ?></th>
                            <th><?php _e('Status', 'nexjob-seo'); ?></th>
                            <th><?php _e('Post Type', 'nexjob-seo'); ?></th>
                            <th><?php _e('Auto Create', 'nexjob-seo'); ?></th>
                            <th><?php _e('Statistics', 'nexjob-seo'); ?></th>
                            <th><?php _e('Actions', 'nexjob-seo'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($webhooks as $webhook): ?>
                            <?php $stats = $this->webhook_manager->get_webhook_stats($webhook->id); ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($webhook->name); ?></strong>
                                    <?php if (!empty($webhook->description)): ?>
                                        <br><small><?php echo esc_html($webhook->description); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <code style="font-size: 11px; word-break: break-all;">
                                        <?php echo esc_html($this->webhook_manager->get_webhook_url($webhook->webhook_token)); ?>
                                    </code>
                                    <br>
                                    <button type="button" class="button-link copy-webhook-url" data-url="<?php echo esc_attr($this->webhook_manager->get_webhook_url($webhook->webhook_token)); ?>">
                                        <?php _e('Copy URL', 'nexjob-seo'); ?>
                                    </button>
                                </td>
                                <td>
                                    <span class="webhook-status webhook-status-<?php echo esc_attr($webhook->status); ?>">
                                        <?php echo esc_html(ucfirst($webhook->status)); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($webhook->post_type ?: '-'); ?></td>
                                <td>
                                    <?php if ($webhook->auto_create): ?>
                                        <span style="color: green;">✓ <?php _e('Yes', 'nexjob-seo'); ?></span>
                                    <?php else: ?>
                                        <span style="color: #999;">— <?php _e('No', 'nexjob-seo'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small>
                                        <?php printf(__('Total: %d', 'nexjob-seo'), $stats['total_requests']); ?><br>
                                        <?php printf(__('Created: %d', 'nexjob-seo'), $stats['posts_created']); ?>
                                    </small>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=nexjob-seo-configure-webhook&webhook_id=' . $webhook->id); ?>" class="button button-small">
                                        <?php _e('Configure', 'nexjob-seo'); ?>
                                    </a>
                                    <br><br>
                                    <a href="<?php echo add_query_arg(array('action' => 'toggle_webhook_status', 'webhook_id' => $webhook->id, 'nonce' => wp_create_nonce('webhook_action'))); ?>" class="button button-small">
                                        <?php echo $webhook->status === 'active' ? __('Deactivate', 'nexjob-seo') : __('Activate', 'nexjob-seo'); ?>
                                    </a>
                                    <br><br>
                                    <a href="<?php echo add_query_arg(array('action' => 'delete_webhook', 'webhook_id' => $webhook->id, 'nonce' => wp_create_nonce('webhook_action'))); ?>" 
                                       class="button button-small button-link-delete" 
                                       onclick="return confirm('<?php _e('Are you sure you want to delete this webhook? This action cannot be undone.', 'nexjob-seo'); ?>')">
                                        <?php _e('Delete', 'nexjob-seo'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <style>
        .webhook-status {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        .webhook-status-active {
            background: #d4edda;
            color: #155724;
        }
        .webhook-status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('.copy-webhook-url').click(function() {
                var url = $(this).data('url');
                navigator.clipboard.writeText(url).then(function() {
                    alert('<?php _e('Webhook URL copied to clipboard!', 'nexjob-seo'); ?>');
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Add webhook page
     */
    public function add_webhook_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Add New Webhook', 'nexjob-seo'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('create_webhook', 'webhook_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="webhook_name"><?php _e('Webhook Name', 'nexjob-seo'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="webhook_name" name="webhook_name" class="regular-text" required>
                            <p class="description"><?php _e('A descriptive name for this webhook.', 'nexjob-seo'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="webhook_description"><?php _e('Description', 'nexjob-seo'); ?></label>
                        </th>
                        <td>
                            <textarea id="webhook_description" name="webhook_description" class="large-text" rows="3"></textarea>
                            <p class="description"><?php _e('Optional description of what this webhook is used for.', 'nexjob-seo'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Create Webhook', 'nexjob-seo')); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Configure webhook page
     */
    public function configure_webhook_page() {
        $webhook_id = intval($_GET['webhook_id'] ?? 0);
        $webhook = $this->webhook_manager->get_webhook($webhook_id);
        
        if (!$webhook) {
            wp_die(__('Webhook not found.', 'nexjob-seo'));
        }
        
        // Get latest webhook data for configuration
        $latest_data = $this->webhook_data->get_latest_unprocessed_data($webhook_id);
        $post_types = get_post_types(array('public' => true), 'objects');
        ?>
        <div class="wrap">
            <h1><?php printf(__('Configure Webhook: %s', 'nexjob-seo'), esc_html($webhook->name)); ?></h1>
            
            <div class="webhook-config-container">
                <!-- Step 1: Webhook URL -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Step 1: Webhook URL', 'nexjob-seo'); ?></h2>
                    <div class="inside">
                        <p><?php _e('Use this URL to send POST requests with your data:', 'nexjob-seo'); ?></p>
                        <code style="background: #f1f1f1; padding: 10px; display: block; word-break: break-all;">
                            <?php echo esc_html($this->webhook_manager->get_webhook_url($webhook->webhook_token)); ?>
                        </code>
                        <button type="button" class="button copy-webhook-url" data-url="<?php echo esc_attr($this->webhook_manager->get_webhook_url($webhook->webhook_token)); ?>">
                            <?php _e('Copy URL', 'nexjob-seo'); ?>
                        </button>
                        <a href="<?php echo add_query_arg(array('action' => 'regenerate_token', 'webhook_id' => $webhook_id, 'nonce' => wp_create_nonce('webhook_action'))); ?>" 
                           class="button" 
                           onclick="return confirm('<?php _e('This will generate a new URL. Update your systems with the new URL. Continue?', 'nexjob-seo'); ?>')">
                            <?php _e('Regenerate URL', 'nexjob-seo'); ?>
                        </a>
                    </div>
                </div>
                
                <!-- Step 2: Fetch Sample Data -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Step 2: Send Sample Data', 'nexjob-seo'); ?></h2>
                    <div class="inside">
                        <p><?php _e('Send a POST request to your webhook URL with sample data, then click the button below to fetch and analyze it.', 'nexjob-seo'); ?></p>
                        
                        <button type="button" id="fetch-webhook-data" class="button button-primary" data-webhook-id="<?php echo $webhook_id; ?>">
                            <?php _e('Fetch Latest Data', 'nexjob-seo'); ?>
                        </button>
                        
                        <div id="webhook-data-container" style="margin-top: 15px;">
                            <?php if ($latest_data): ?>
                                <div class="webhook-data-preview">
                                    <h4><?php _e('Latest Received Data:', 'nexjob-seo'); ?></h4>
                                    <pre style="background: #f9f9f9; padding: 10px; max-height: 300px; overflow: auto;"><?php echo esc_html($latest_data->data); ?></pre>
                                    <p><small><?php printf(__('Received: %s', 'nexjob-seo'), $latest_data->created_at); ?></small></p>
                                </div>
                            <?php else: ?>
                                <p><em><?php _e('No data received yet. Send a POST request to the webhook URL above.', 'nexjob-seo'); ?></em></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Step 3: Configure Post Type and Field Mapping -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Step 3: Configure Field Mapping', 'nexjob-seo'); ?></h2>
                    <div class="inside">
                        <form method="post" action="" id="webhook-config-form">
                            <?php wp_nonce_field('configure_webhook', 'webhook_config_nonce'); ?>
                            <input type="hidden" name="webhook_id" value="<?php echo $webhook_id; ?>">
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="post_type"><?php _e('Post Type', 'nexjob-seo'); ?></label>
                                    </th>
                                    <td>
                                        <select id="post_type" name="post_type" required>
                                            <option value=""><?php _e('Select Post Type', 'nexjob-seo'); ?></option>
                                            <?php foreach ($post_types as $post_type): ?>
                                                <option value="<?php echo esc_attr($post_type->name); ?>" 
                                                        <?php selected($webhook->post_type, $post_type->name); ?>>
                                                    <?php echo esc_html($post_type->labels->name); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="default_status"><?php _e('Default Post Status', 'nexjob-seo'); ?></label>
                                    </th>
                                    <td>
                                        <select id="default_status" name="default_status">
                                            <option value="draft" <?php selected($webhook->default_status, 'draft'); ?>><?php _e('Draft', 'nexjob-seo'); ?></option>
                                            <option value="publish" <?php selected($webhook->default_status, 'publish'); ?>><?php _e('Published', 'nexjob-seo'); ?></option>
                                            <option value="private" <?php selected($webhook->default_status, 'private'); ?>><?php _e('Private', 'nexjob-seo'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="auto_create"><?php _e('Auto Create Posts', 'nexjob-seo'); ?></label>
                                    </th>
                                    <td>
                                        <label>
                                            <input type="checkbox" id="auto_create" name="auto_create" value="1" <?php checked($webhook->auto_create); ?>>
                                            <?php _e('Automatically create posts when webhook data is received', 'nexjob-seo'); ?>
                                        </label>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Field Mappings -->
                            <div id="field-mappings-container">
                                <h3><?php _e('Field Mappings', 'nexjob-seo'); ?></h3>
                                <p><?php _e('Map webhook data fields to WordPress post fields. You can create unlimited mappings and map the same webhook field to multiple WordPress fields (e.g., use one description field for both excerpt and SEO description):', 'nexjob-seo'); ?></p>
                                
                                <div id="field-mappings">
                                    <?php
                                    $existing_mappings = $webhook->field_mappings ? json_decode($webhook->field_mappings, true) : array();
                                    if (!empty($existing_mappings)):
                                        foreach ($existing_mappings as $index => $mapping):
                                    ?>
                                        <div class="field-mapping-row">
                                            <select name="field_mappings[<?php echo $index; ?>][webhook_field]" class="webhook-field-select" data-preserved-value="<?php echo esc_attr($mapping['webhook_field']); ?>">
                                                <option value="<?php echo esc_attr($mapping['webhook_field']); ?>"><?php echo esc_html($mapping['webhook_field']); ?></option>
                                            </select>
                                            <span> → </span>
                                            <select name="field_mappings[<?php echo $index; ?>][wp_field]" class="wp-field-select" data-preserved-value="<?php echo esc_attr($mapping['wp_field']); ?>">
                                                <option value="<?php echo esc_attr($mapping['wp_field']); ?>"><?php echo esc_html($mapping['wp_field']); ?></option>
                                            </select>
                                            <input type="text" name="field_mappings[<?php echo $index; ?>][default_value]" 
                                                   placeholder="<?php _e('Default value', 'nexjob-seo'); ?>" 
                                                   value="<?php echo esc_attr($mapping['default_value'] ?? ''); ?>">
                                            <button type="button" class="button remove-mapping"><?php _e('Remove', 'nexjob-seo'); ?></button>
                                        </div>
                                    <?php
                                        endforeach;
                                    endif;
                                    ?>
                                </div>
                                
                                <button type="button" id="add-field-mapping" class="button"><?php _e('Add Field Mapping', 'nexjob-seo'); ?></button>
                                <button type="button" id="suggest-mappings" class="button"><?php _e('Auto-Suggest Mappings', 'nexjob-seo'); ?></button>
                            </div>
                            
                            <?php submit_button(__('Save Configuration', 'nexjob-seo')); ?>
                        </form>
                    </div>
                </div>
                
                <!-- Step 4: Test -->
                <div class="postbox">
                    <h2 class="hndle"><?php _e('Step 4: Test Configuration', 'nexjob-seo'); ?></h2>
                    <div class="inside">
                        <p><?php _e('Test your webhook configuration by processing the latest received data:', 'nexjob-seo'); ?></p>
                        <button type="button" id="test-webhook-config" class="button button-secondary" data-webhook-id="<?php echo $webhook_id; ?>">
                            <?php _e('Test Configuration', 'nexjob-seo'); ?>
                        </button>
                        <div id="test-results"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var mappingIndex = <?php echo count($existing_mappings ?? array()); ?>;
            var latestWebhookFields = [];
            var currentPostType = $('#post_type').val() || '';
            var wpFieldsData = null; // Store WP fields data once
            
            // Copy webhook URL
            $('.copy-webhook-url').click(function() {
                var url = $(this).data('url');
                navigator.clipboard.writeText(url).then(function() {
                    alert('<?php _e('Webhook URL copied to clipboard!', 'nexjob-seo'); ?>');
                });
            });
            
            // Fetch webhook data
            $('#fetch-webhook-data').click(function() {
                var webhookId = $(this).data('webhook-id');
                var button = $(this);
                
                if (!webhookId) {
                    alert('Invalid webhook ID');
                    return;
                }
                
                button.prop('disabled', true).text('<?php _e('Fetching...', 'nexjob-seo'); ?>');
                console.log('Fetching data for webhook ID:', webhookId);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'nexjob_fetch_webhook_data',
                        webhook_id: webhookId,
                        nonce: '<?php echo wp_create_nonce('webhook_ajax'); ?>'
                    },
                    timeout: 30000,
                    success: function(response) {
                        console.log('AJAX Success Response:', response);
                        
                        if (response && response.success) {
                            $('#webhook-data-container').html(response.data.html);
                            
                            // Update ALL webhook field dropdowns with fresh data
                            if (response.data.fields && response.data.fields.length > 0) {
                                console.log('Fresh webhook fields received:', response.data.fields);
                                latestWebhookFields = response.data.fields.slice(); // Create a copy to avoid reference issues
                                console.log('latestWebhookFields after assignment:', latestWebhookFields);
                                updateAllWebhookFieldDropdowns();
                            } else {
                                console.log('No webhook fields in response');
                                latestWebhookFields = [];
                                updateAllWebhookFieldDropdowns();
                            }
                            
                            if (response.data.debug_info) {
                                console.log('Debug info:', response.data.debug_info);
                            }
                        } else {
                            var errorMsg = response && response.data && response.data.message ? response.data.message : 'Unknown error occurred';
                            alert('Error: ' + errorMsg);
                            console.error('Fetch webhook data error:', response);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', {xhr: xhr, status: status, error: error});
                        console.error('Response Text:', xhr.responseText);
                        alert('Request failed: ' + error + ' (Status: ' + status + ')');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php _e('Fetch Latest Data', 'nexjob-seo'); ?>');
                        console.log('AJAX request completed');
                    }
                });
            });
            
            // Post type change
            $('#post_type').change(function() {
                var postType = $(this).val();
                currentPostType = postType;
                if (postType) {
                    loadWpFieldsData(postType);
                }
            });
            
            // Add field mapping - unlimited mappings supported, same webhook field can map to multiple WP fields
            $('#add-field-mapping').click(function() {
                var html = '<div class="field-mapping-row">' +
                    '<select name="field_mappings[' + mappingIndex + '][webhook_field]" class="webhook-field-select">' +
                    '<option value=""><?php _e('Select webhook field', 'nexjob-seo'); ?></option>' +
                    '</select>' +
                    '<span> → </span>' +
                    '<select name="field_mappings[' + mappingIndex + '][wp_field]" class="wp-field-select">' +
                    '<option value=""><?php _e('Select WordPress field', 'nexjob-seo'); ?></option>' +
                    '</select>' +
                    '<input type="text" name="field_mappings[' + mappingIndex + '][default_value]" placeholder="<?php _e('Default value', 'nexjob-seo'); ?>">' +
                    '<button type="button" class="button remove-mapping"><?php _e('Remove', 'nexjob-seo'); ?></button>' +
                    '</div>';
                
                var $newRow = $(html);
                $('#field-mappings').append($newRow);
                
                // Populate the new row with currently available data
                populateWebhookFieldSelect($newRow.find('.webhook-field-select'));
                populateWpFieldSelect($newRow.find('.wp-field-select'));
                
                mappingIndex++;
                console.log('Added field mapping row #' + mappingIndex + '. Total rows: ' + $('#field-mappings .field-mapping-row').length);
            });
            
            // Remove field mapping
            $(document).on('click', '.remove-mapping', function() {
                $(this).closest('.field-mapping-row').remove();
            });
            
            // Auto-suggest mappings
            $('#suggest-mappings').click(function() {
                var webhookId = $('#fetch-webhook-data').data('webhook-id');
                var postType = $('#post_type').val();
                
                if (!postType) {
                    alert('<?php _e('Please select a post type first.', 'nexjob-seo'); ?>');
                    return;
                }
                
                $.post(ajaxurl, {
                    action: 'nexjob_suggest_field_mappings',
                    webhook_id: webhookId,
                    post_type: postType,
                    nonce: '<?php echo wp_create_nonce('webhook_ajax'); ?>'
                }, function(response) {
                    if (response.success) {
                        // Clear existing mappings
                        $('#field-mappings').empty();
                        mappingIndex = 0;
                        
                        // Add suggested mappings
                        response.data.suggestions.forEach(function(suggestion) {
                            $('#add-field-mapping').click();
                            var lastRow = $('#field-mappings .field-mapping-row').last();
                            lastRow.find('.webhook-field-select').val(suggestion.webhook_field);
                            lastRow.find('.wp-field-select').val(suggestion.wp_field);
                        });
                    }
                });
            });
            
            // Update all webhook field dropdowns with latest fields
            function updateAllWebhookFieldDropdowns() {
                $('.webhook-field-select').each(function() {
                    populateWebhookFieldSelect($(this));
                });
                console.log('Updated all webhook dropdowns with', latestWebhookFields.length, 'fields');
            }
            
            // Populate a single webhook field dropdown - allow reusing same field multiple times
            function populateWebhookFieldSelect($select) {
                var currentValue = $select.val() || $select.data('preserved-value');
                console.log('Populating webhook field dropdown. Current value:', currentValue, 'Preserved value:', $select.data('preserved-value'), 'Available fields:', latestWebhookFields);
                
                $select.empty().append('<option value=""><?php _e('Select webhook field', 'nexjob-seo'); ?></option>');
                
                // Allow all fields to be selectable in every dropdown (no restrictions)
                if (latestWebhookFields && latestWebhookFields.length > 0) {
                    latestWebhookFields.forEach(function(field) {
                        $select.append('<option value="' + field + '">' + field + '</option>');
                    });
                }
                
                // Restore previous selection if it still exists
                if (currentValue && latestWebhookFields && latestWebhookFields.indexOf(currentValue) !== -1) {
                    $select.val(currentValue);
                    console.log('Restored previous selection:', currentValue);
                } else if (currentValue) {
                    console.log('Could not restore previous selection:', currentValue, 'not found in available fields:', latestWebhookFields);
                    // Keep the old value as an option even if not in new fields (backward compatibility)
                    $select.append('<option value="' + currentValue + '">' + currentValue + ' (legacy)</option>');
                    $select.val(currentValue);
                }
            }
            
            // Load WordPress fields data once for current post type
            function loadWpFieldsData(postType) {
                if (!postType) {
                    return;
                }
                
                $.post(ajaxurl, {
                    action: 'nexjob_get_wp_fields',
                    post_type: postType,
                    nonce: '<?php echo wp_create_nonce('webhook_ajax'); ?>'
                }, function(response) {
                    console.log('WP fields response:', response);
                    
                    if (response.success && response.data && response.data.fields) {
                        wpFieldsData = response.data.fields;
                        updateAllWpFieldDropdowns();
                        console.log('Loaded WP fields data for', postType);
                    } else {
                        console.error('Invalid WP fields response:', response);
                        wpFieldsData = null;
                    }
                }).fail(function(xhr, status, error) {
                    console.error('Failed to load WP fields:', error);
                    wpFieldsData = null;
                });
            }
            
            // Update all WordPress field dropdowns
            function updateAllWpFieldDropdowns() {
                $('.wp-field-select').each(function() {
                    populateWpFieldSelect($(this));
                });
                console.log('Updated all WP field dropdowns');
            }
            
            // Populate a single WordPress field dropdown
            function populateWpFieldSelect($select) {
                if (!wpFieldsData) {
                    return;
                }
                
                var currentValue = $select.val();
                $select.empty().append('<option value=""><?php _e('Select WordPress field', 'nexjob-seo'); ?></option>');
                
                $.each(wpFieldsData, function(key, field) {
                    if (field && field.label && typeof field.label === 'string') {
                        $select.append('<option value="' + key + '">' + field.label + '</option>');
                    } else if (typeof field === 'string') {
                        $select.append('<option value="' + key + '">' + field + '</option>');
                    }
                });
                
                if (currentValue && $select.find('option[value="' + currentValue + '"]').length > 0) {
                    $select.val(currentValue);
                }
            }
            
            // Initialize WordPress fields for current post type
            if (currentPostType) {
                loadWpFieldsData(currentPostType);
            }
        });
        </script>
        
        <style>
        .webhook-config-container .postbox {
            margin-bottom: 20px;
        }
        .field-mapping-row {
            margin: 10px 0;
            padding: 10px;
            background: #f9f9f9;
            border: 1px solid #e5e5e5;
        }
        .field-mapping-row select,
        .field-mapping-row input {
            margin: 0 5px;
        }
        .webhook-data-preview {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            background: #fff;
        }
        </style>
        <?php
    }
    
    /**
     * Handle webhook admin actions
     */
    public function handle_webhook_admin_actions() {
        if (!current_user_can('manage_options')) return;
        
        // Create webhook
        if (isset($_POST['webhook_nonce'])) {
            $webhook_nonce = $_POST['webhook_nonce'];
            if (wp_verify_nonce($webhook_nonce, 'create_webhook')) {
            $name = sanitize_text_field($_POST['webhook_name']);
            $description = sanitize_textarea_field($_POST['webhook_description']);
            
            $result = $this->webhook_manager->create_webhook($name, $description);
            
            if ($result['success']) {
                wp_redirect(admin_url('admin.php?page=nexjob-seo-configure-webhook&webhook_id=' . $result['webhook_id'] . '&message=created'));
                exit;
            } else {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error"><p>' . __('Failed to create webhook.', 'nexjob-seo') . '</p></div>';
                });
            }
            }
        }
        
        // Configure webhook
        if (isset($_POST['webhook_config_nonce'])) {
            $webhook_config_nonce = $_POST['webhook_config_nonce'];
            if (wp_verify_nonce($webhook_config_nonce, 'configure_webhook')) {
            $webhook_id = intval($_POST['webhook_id']);
            // Debug: Log how many field mappings were submitted
            error_log('NexJob Debug: Field mappings submitted: ' . count($_POST['field_mappings'] ?? array()));
            error_log('NexJob Debug: Raw field mappings data: ' . print_r($_POST['field_mappings'] ?? array(), true));
            
            // Debug each individual mapping for detailed analysis
            if (isset($_POST['field_mappings']) && is_array($_POST['field_mappings'])) {
                foreach ($_POST['field_mappings'] as $index => $mapping) {
                    error_log("NexJob Debug: Mapping[$index] = " . json_encode($mapping));
                }
            }
            
            // Sanitize field mappings - allow unlimited mappings including duplicates
            $field_mappings = array();
            if (isset($_POST['field_mappings']) && is_array($_POST['field_mappings'])) {
                $mapping_counter = 0; // Use counter instead of original index to avoid gaps
                foreach ($_POST['field_mappings'] as $index => $mapping) {
                    if (is_array($mapping) && !empty($mapping['webhook_field']) && !empty($mapping['wp_field'])) {
                        $field_mappings[$mapping_counter] = array(
                            'webhook_field' => sanitize_text_field($mapping['webhook_field'] ?? ''),
                            'wp_field' => sanitize_text_field($mapping['wp_field'] ?? ''),
                            'default_value' => sanitize_textarea_field($mapping['default_value'] ?? '')
                        );
                        $mapping_counter++;
                    }
                }
            }
            
            error_log('NexJob Debug: Field mappings processed: ' . count($field_mappings));
            error_log('NexJob Debug: Final processed mappings: ' . json_encode($field_mappings));
            
            $config = array(
                'post_type' => sanitize_text_field($_POST['post_type']),
                'default_status' => sanitize_text_field($_POST['default_status']),
                'auto_create' => isset($_POST['auto_create']),
                'field_mappings' => $field_mappings
            );
            
            $result = $this->webhook_manager->update_webhook_config($webhook_id, $config);
            
            if ($result) {
                wp_redirect(add_query_arg('message', 'configured'));
                exit;
            }
            }
        }
        
        // Handle GET actions
        if (isset($_GET['action']) && isset($_GET['nonce'])) {
            $get_nonce = $_GET['nonce'];
            if (wp_verify_nonce($get_nonce, 'webhook_action')) {
            switch ($_GET['action']) {
                case 'toggle_webhook_status':
                    $webhook_id = intval($_GET['webhook_id']);
                    $webhook = $this->webhook_manager->get_webhook($webhook_id);
                    if ($webhook) {
                        $new_status = $webhook->status === 'active' ? 'inactive' : 'active';
                        $this->webhook_manager->update_webhook_status($webhook_id, $new_status);
                    }
                    wp_redirect(remove_query_arg(array('action', 'webhook_id', 'nonce')));
                    exit;
                    
                case 'delete_webhook':
                    $webhook_id = intval($_GET['webhook_id']);
                    $this->webhook_manager->delete_webhook($webhook_id);
                    wp_redirect(admin_url('admin.php?page=nexjob-seo-webhooks&message=deleted'));
                    exit;
                    
                case 'regenerate_token':
                    $webhook_id = intval($_GET['webhook_id']);
                    $this->webhook_manager->regenerate_webhook_token($webhook_id);
                    wp_redirect(add_query_arg('message', 'token_regenerated'));
                    exit;
            }
            }
        }
    }
    
    /**
     * AJAX: Fetch webhook data
     */
    public function ajax_fetch_webhook_data() {
        if (!isset($_POST['nonce'])) wp_die('Missing nonce');
        $nonce = $_POST['nonce'];
        if (!wp_verify_nonce($nonce, 'webhook_ajax') || !current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $webhook_id = intval($_POST['webhook_id']);
        
        // Debug: Log the webhook ID we're searching for
        error_log("AJAX fetch webhook data - searching for webhook_id: " . $webhook_id);
        
        // Get latest data regardless of status (not just unprocessed)
        $latest_data_records = $this->webhook_data->get_webhook_data($webhook_id, 1, 0);
        
        error_log("Found webhook records: " . count($latest_data_records));
        
        if (empty($latest_data_records)) {
            wp_send_json_error(array('message' => __('No data received yet. Send a POST request to the webhook URL.', 'nexjob-seo') . " (Searched for webhook_id: $webhook_id)"));
            return;
        }
        
        $latest_data = $latest_data_records[0];
        $parsed_data = $this->webhook_data->parse_webhook_data($latest_data->data);
        
        // Check for parsing errors
        if (isset($parsed_data['error'])) {
            wp_send_json_error(array('message' => 'Error parsing webhook data: ' . $parsed_data['error']));
            return;
        }
        
        $html = '<div class="webhook-data-preview">';
        $html .= '<h4>' . __('Latest Received Data:', 'nexjob-seo') . '</h4>';
        $html .= '<pre style="background: #f9f9f9; padding: 10px; max-height: 300px; overflow: auto;">' . esc_html($latest_data->data) . '</pre>';
        $html .= '<p><small>' . sprintf(__('Received: %s | Status: %s', 'nexjob-seo'), $latest_data->created_at, $latest_data->status) . '</small></p>';
        
        // Show available fields for debugging
        if (!empty($parsed_data['available_fields'])) {
            $html .= '<p><strong>Available fields:</strong> ' . implode(', ', $parsed_data['available_fields']) . '</p>';
        }
        
        $html .= '</div>';
        
        wp_send_json_success(array(
            'html' => $html,
            'fields' => $parsed_data['available_fields'] ?? array(),
            'debug_info' => array(
                'data_id' => $latest_data->id,
                'webhook_id' => $webhook_id,
                'status' => $latest_data->status,
                'field_count' => count($parsed_data['available_fields'] ?? array())
            )
        ));
    }
    
    /**
     * AJAX: Get webhook fields from recent data
     */
    public function ajax_get_webhook_fields() {
        if (!isset($_POST['nonce'])) wp_die('Missing nonce');
        $nonce = $_POST['nonce'];
        if (!wp_verify_nonce($nonce, 'webhook_ajax') || !current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $webhook_id = intval($_POST['webhook_id']);
        $fields = $this->webhook_data->get_field_suggestions($webhook_id);
        
        wp_send_json_success(array('fields' => $fields));
    }
    
    /**
     * AJAX: Get WordPress fields for post type
     */
    public function ajax_get_wp_fields() {
        if (!isset($_POST['nonce'])) wp_die('Missing nonce');
        $nonce = $_POST['nonce'];
        if (!wp_verify_nonce($nonce, 'webhook_ajax') || !current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $post_type = sanitize_text_field($_POST['post_type']);
        $fields = $this->field_mapper->get_available_wp_fields($post_type);
        
        wp_send_json_success(array('fields' => $fields));
    }
    
    /**
     * AJAX: Suggest field mappings
     */
    public function ajax_suggest_field_mappings() {
        if (!isset($_POST['nonce'])) wp_die('Missing nonce');
        $nonce = $_POST['nonce'];
        if (!wp_verify_nonce($nonce, 'webhook_ajax') || !current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $webhook_id = intval($_POST['webhook_id']);
        $post_type = sanitize_text_field($_POST['post_type']);
        
        $field_suggestions = $this->webhook_data->get_field_suggestions($webhook_id);
        $suggestions = $this->field_mapper->suggest_field_mappings($field_suggestions, $post_type);
        
        wp_send_json_success(array('suggestions' => $suggestions));
    }
    
    /**
     * AJAX: Process webhook data manually
     */
    public function ajax_process_webhook_data() {
        if (!isset($_POST['nonce'])) wp_die('Missing nonce');
        $nonce = $_POST['nonce'];
        if (!wp_verify_nonce($nonce, 'webhook_ajax') || !current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $webhook_id = intval($_POST['webhook_id']);
        $data_id = intval($_POST['data_id']);
        
        $result = $this->webhook_processor->process_webhook_data_manually($webhook_id, $data_id);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
}