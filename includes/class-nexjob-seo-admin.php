<?php
/**
 * Admin interface class for WordPress admin pages
 */

if (!defined('ABSPATH')) {
    exit;
}

class NexJob_SEO_Admin {
    
    /**
     * Dependencies
     */
    private $settings;
    private $logger;
    private $post_processor;
    private $cron_manager;
    
    /**
     * Constructor
     */
    public function __construct($settings, $logger, $post_processor, $cron_manager) {
        $this->settings = $settings;
        $this->logger = $logger;
        $this->post_processor = $post_processor;
        $this->cron_manager = $cron_manager;
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_admin_actions'));
        add_action('admin_notices', array($this, 'show_admin_notices'));
        
        // Add manual process buttons to post list pages
        $post_types = $this->settings->get('post_types');
        foreach ($post_types as $post_type) {
            add_filter("views_edit-{$post_type}", array($this, 'add_manual_process_buttons'));
        }
    }
    
    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        // Main menu page
        add_options_page(
            __('NexJob SEO Automation', 'nexjob-seo'),
            __('NexJob SEO', 'nexjob-seo'),
            'manage_options',
            'nexjob-seo',
            array($this, 'admin_page')
        );
        
        // Settings submenu
        add_submenu_page(
            'nexjob-seo',
            __('Settings', 'nexjob-seo'),
            __('Settings', 'nexjob-seo'),
            'manage_options',
            'nexjob-seo-settings',
            array($this, 'settings_page')
        );
        
        // Logs submenu
        add_submenu_page(
            'nexjob-seo',
            __('Logs', 'nexjob-seo'),
            __('Logs', 'nexjob-seo'),
            'manage_options',
            'nexjob-seo-logs',
            array($this, 'logs_page')
        );
    }
    
    /**
     * Main admin page
     */
    public function admin_page() {
        // Get processing statistics
        $stats = $this->get_processing_stats();
        $cron_info = $this->cron_manager->get_cron_info();
        ?>
        <div class="wrap">
            <h1><?php _e('NexJob SEO Automation', 'nexjob-seo'); ?></h1>
            
            <div class="dashboard-widgets-wrap">
                <div class="metabox-holder">
                    <!-- Status Widget -->
                    <div class="postbox">
                        <h2 class="hndle"><?php _e('Processing Status', 'nexjob-seo'); ?></h2>
                        <div class="inside">
                            <?php if (!empty($stats)): ?>
                                <table class="widefat">
                                    <thead>
                                        <tr>
                                            <th><?php _e('Post Type', 'nexjob-seo'); ?></th>
                                            <th><?php _e('Total Posts', 'nexjob-seo'); ?></th>
                                            <th><?php _e('Processed', 'nexjob-seo'); ?></th>
                                            <th><?php _e('Remaining', 'nexjob-seo'); ?></th>
                                            <th><?php _e('Progress', 'nexjob-seo'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stats as $post_type => $stat): ?>
                                            <tr>
                                                <td><?php echo esc_html($post_type); ?></td>
                                                <td><?php echo esc_html($stat['total']); ?></td>
                                                <td><?php echo esc_html($stat['processed']); ?></td>
                                                <td><?php echo esc_html($stat['remaining']); ?></td>
                                                <td>
                                                    <?php 
                                                    $percentage = $stat['total'] > 0 ? round(($stat['processed'] / $stat['total']) * 100, 1) : 0;
                                                    echo esc_html($percentage . '%');
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p><?php _e('No statistics available.', 'nexjob-seo'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Cron Job Widget -->
                    <div class="postbox">
                        <h2 class="hndle"><?php _e('Cron Job Status', 'nexjob-seo'); ?></h2>
                        <div class="inside">
                            <p><strong><?php _e('Next Run:', 'nexjob-seo'); ?></strong> <?php echo esc_html($cron_info['next_run']); ?></p>
                            <p><strong><?php _e('Interval:', 'nexjob-seo'); ?></strong> <?php echo esc_html($cron_info['interval']); ?></p>
                            <p><strong><?php _e('Status:', 'nexjob-seo'); ?></strong> 
                                <?php if ($cron_info['is_scheduled']): ?>
                                    <span style="color: green;"><?php _e('Scheduled', 'nexjob-seo'); ?></span>
                                <?php else: ?>
                                    <span style="color: red;"><?php _e('Not Scheduled', 'nexjob-seo'); ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Actions Widget -->
                    <div class="postbox">
                        <h2 class="hndle"><?php _e('Manual Actions', 'nexjob-seo'); ?></h2>
                        <div class="inside">
                            <p>
                                <a href="<?php echo esc_url(add_query_arg('action', 'nexjob_manual_process')); ?>" 
                                   class="button button-secondary">
                                    <?php _e('Process Posts Now', 'nexjob-seo'); ?>
                                </a>
                            </p>
                            <p>
                                <a href="<?php echo esc_url(add_query_arg('action', 'nexjob_force_reprocess')); ?>" 
                                   class="button button-secondary" 
                                   onclick="return confirm('<?php _e('This will reprocess ALL posts, including those already processed. Continue?', 'nexjob-seo'); ?>')">
                                    <?php _e('Force Regenerate All', 'nexjob-seo'); ?>
                                </a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('NexJob SEO Settings', 'nexjob-seo'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('nexjob_seo_settings_group');
                do_settings_sections('nexjob-seo-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Logs page
     */
    public function logs_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('NexJob SEO Logs', 'nexjob-seo'); ?></h1>
            
            <div class="tablenav top">
                <div class="alignleft actions">
                    <label for="log-level-filter"><?php _e('Filter by level:', 'nexjob-seo'); ?></label>
                    <select id="log-level-filter">
                        <option value=""><?php _e('All Levels', 'nexjob-seo'); ?></option>
                        <option value="error"><?php _e('Error', 'nexjob-seo'); ?></option>
                        <option value="warning"><?php _e('Warning', 'nexjob-seo'); ?></option>
                        <option value="info"><?php _e('Info', 'nexjob-seo'); ?></option>
                        <option value="success"><?php _e('Success', 'nexjob-seo'); ?></option>
                    </select>
                    
                    <label for="post-type-filter"><?php _e('Filter by post type:', 'nexjob-seo'); ?></label>
                    <select id="post-type-filter">
                        <option value=""><?php _e('All Post Types', 'nexjob-seo'); ?></option>
                        <?php
                        $post_types = $this->settings->get('post_types');
                        foreach ($post_types as $post_type) {
                            echo '<option value="' . esc_attr($post_type) . '">' . esc_html($post_type) . '</option>';
                        }
                        ?>
                    </select>
                    
                    <button type="button" id="refresh-logs" class="button"><?php _e('Refresh', 'nexjob-seo'); ?></button>
                    <button type="button" id="clear-logs" class="button"><?php _e('Clear All Logs', 'nexjob-seo'); ?></button>
                </div>
            </div>
            
            <div id="logs-container">
                <?php $this->display_logs(); ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#refresh-logs, #log-level-filter, #post-type-filter').on('change click', function() {
                var level = $('#log-level-filter').val();
                var post_type = $('#post-type-filter').val();
                
                $.post(ajaxurl, {
                    action: 'nexjob_get_logs',
                    level: level,
                    post_type: post_type,
                    nonce: '<?php echo wp_create_nonce('nexjob_logs'); ?>'
                }, function(response) {
                    $('#logs-container').html(response);
                });
            });
            
            $('#clear-logs').click(function() {
                if (confirm('<?php _e('Are you sure you want to clear all logs?', 'nexjob-seo'); ?>')) {
                    $.post(ajaxurl, {
                        action: 'nexjob_clear_logs',
                        nonce: '<?php echo wp_create_nonce('nexjob_logs'); ?>'
                    }, function(response) {
                        if (response.success) {
                            $('#logs-container').html('<p><?php _e('No logs found.', 'nexjob-seo'); ?></p>');
                        }
                    });
                }
            });
            
            // Auto refresh every 30 seconds
            setInterval(function() {
                $('#refresh-logs').click();
            }, 30000);
        });
        </script>
        <?php
    }
    
    /**
     * Display logs
     */
    private function display_logs($level = '', $post_type = '') {
        $logs = $this->logger->get_logs($level, $post_type);
        
        if (empty($logs)) {
            echo '<p>' . __('No logs found.', 'nexjob-seo') . '</p>';
            return;
        }
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . __('Timestamp', 'nexjob-seo') . '</th>';
        echo '<th>' . __('Level', 'nexjob-seo') . '</th>';
        echo '<th>' . __('Message', 'nexjob-seo') . '</th>';
        echo '<th>' . __('Post', 'nexjob-seo') . '</th>';
        echo '<th>' . __('Post Type', 'nexjob-seo') . '</th>';
        echo '<th>' . __('Context', 'nexjob-seo') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        
        foreach ($logs as $log) {
            $level_class = 'log-' . $log->level;
            echo "<tr class='{$level_class}'>";
            echo '<td>' . $log->timestamp . '</td>';
            echo '<td><span class="log-level log-level-' . $log->level . '">' . ucfirst($log->level) . '</span></td>';
            echo '<td>' . esc_html($log->message) . '</td>';
            echo '<td>';
            if ($log->post_id) {
                echo '<a href="' . get_edit_post_link($log->post_id) . '">' . esc_html($log->post_title) . ' (#' . $log->post_id . ')</a>';
            } else {
                echo '-';
            }
            echo '</td>';
            echo '<td>' . esc_html($log->post_type ?: '-') . '</td>';
            echo '<td>';
            if ($log->context) {
                echo '<details><summary>View</summary><pre>' . esc_html($log->context) . '</pre></details>';
            } else {
                echo '-';
            }
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        
        // Add CSS for log levels
        echo '<style>
        .log-level { padding: 2px 6px; border-radius: 3px; color: white; font-size: 11px; }
        .log-level-error { background: #dc3232; }
        .log-level-warning { background: #ffb900; }
        .log-level-info { background: #0073aa; }
        .log-level-success { background: #46b450; }
        .log-error { background-color: #ffeaea; }
        .log-warning { background-color: #fff8e5; }
        .log-success { background-color: #eafaf1; }
        details { cursor: pointer; }
        details pre { background: #f1f1f1; padding: 10px; margin: 5px 0; border-radius: 3px; font-size: 11px; white-space: pre-wrap; word-wrap: break-word; }
        </style>';
    }
    
    /**
     * Handle admin actions
     */
    public function handle_admin_actions() {
        if (!current_user_can('manage_options')) return;
        
        if (isset($_GET['action'])) {
            switch ($_GET['action']) {
                case 'nexjob_manual_process':
                    $this->cron_manager->process_posts_via_cron(false);
                    wp_redirect(add_query_arg('message', 'processed', remove_query_arg('action')));
                    exit;
                    
                case 'nexjob_force_reprocess':
                    $this->cron_manager->force_reprocess_all();
                    wp_redirect(add_query_arg('message', 'reprocessed', remove_query_arg('action')));
                    exit;
            }
        }
    }
    
    /**
     * Show admin notices
     */
    public function show_admin_notices() {
        if (isset($_GET['message'])) {
            switch ($_GET['message']) {
                case 'processed':
                    echo '<div class="notice notice-success is-dismissible"><p>' . __('Manual processing completed!', 'nexjob-seo') . '</p></div>';
                    break;
                case 'reprocessed':
                    echo '<div class="notice notice-success is-dismissible"><p>' . __('Force reprocessing completed! All posts will be reprocessed.', 'nexjob-seo') . '</p></div>';
                    break;
            }
        }
        
        // Show processing status on configured post type pages
        $screen = get_current_screen();
        $post_types = $this->settings->get('post_types');
        if ($screen && in_array($screen->post_type, $post_types)) {
            $stats = $this->get_processing_stats();
            
            if (isset($stats[$screen->post_type]) && $stats[$screen->post_type]['remaining'] > 0) {
                echo '<div class="notice notice-info"><p>';
                echo sprintf(
                    __('NexJob SEO Automation: %d of %d %s posts processed properly. %d remaining.', 'nexjob-seo'),
                    $stats[$screen->post_type]['processed'],
                    $stats[$screen->post_type]['total'],
                    $screen->post_type,
                    $stats[$screen->post_type]['remaining']
                );
                echo '</p></div>';
            }
        }
    }
    
    /**
     * Add manual process buttons to post list page
     */
    public function add_manual_process_buttons($views) {
        $manual_url = add_query_arg('action', 'nexjob_manual_process');
        $force_url = add_query_arg('action', 'nexjob_force_reprocess');
        
        $views['manual_process'] = '<a href="' . esc_url($manual_url) . '">' . __('Manual Process SEO', 'nexjob-seo') . '</a>';
        $views['force_reprocess'] = '<a href="' . esc_url($force_url) . '" onclick="return confirm(\'' . __('This will reprocess ALL posts, including those already processed. Continue?', 'nexjob-seo') . '\')">' . __('Force Regenerate All', 'nexjob-seo') . '</a>';
        
        return $views;
    }
    
    /**
     * Get processing statistics for all configured post types
     */
    public function get_processing_stats() {
        $stats = array();
        $post_types = $this->settings->get('post_types');
        
        foreach ($post_types as $post_type) {
            $total_posts = wp_count_posts($post_type);
            
            if (!isset($total_posts->publish)) {
                continue;
            }
            
            // Get posts for this type
            $posts_of_type = get_posts(array(
                'post_type'      => $post_type,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids'
            ));
            
            $properly_processed = 0;
            foreach ($posts_of_type as $post_id) {
                if (!$this->post_processor->needs_seo_processing($post_id, false)) {
                    $properly_processed++;
                }
            }
            
            $stats[$post_type] = array(
                'total' => $total_posts->publish,
                'processed' => $properly_processed,
                'remaining' => $total_posts->publish - $properly_processed
            );
        }
        
        return $stats;
    }
}