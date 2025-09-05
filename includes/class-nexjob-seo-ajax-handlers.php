<?php
/**
 * AJAX handlers class for admin AJAX requests
 */

if (!defined('ABSPATH')) {
    exit;
}

class NexJob_SEO_Ajax_Handlers {
    
    /**
     * Dependencies
     */
    private $settings;
    private $logger;
    
    /**
     * Constructor
     */
    public function __construct($settings, $logger) {
        $this->settings = $settings;
        $this->logger = $logger;
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('wp_ajax_nexjob_clear_logs', array($this, 'ajax_clear_logs'));
        add_action('wp_ajax_nexjob_get_logs', array($this, 'ajax_get_logs'));
    }
    
    /**
     * AJAX: Clear logs
     */
    public function ajax_clear_logs() {
        // Verify nonce and permissions
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nexjob_logs') || !current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Clear logs
        $result = $this->logger->clear_logs();
        
        if ($result !== false) {
            wp_send_json_success(array('message' => __('Logs cleared successfully.', 'nexjob-seo')));
        } else {
            wp_send_json_error(array('message' => __('Failed to clear logs.', 'nexjob-seo')));
        }
    }
    
    /**
     * AJAX: Get logs
     */
    public function ajax_get_logs() {
        // Verify nonce and permissions
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nexjob_logs') || !current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Sanitize input
        $level = sanitize_text_field($_POST['level'] ?? '');
        $post_type = sanitize_text_field($_POST['post_type'] ?? '');
        
        // Get logs
        $logs = $this->logger->get_logs($level, $post_type);
        
        // Generate output
        ob_start();
        $this->display_logs($logs);
        $output = ob_get_clean();
        
        echo $output;
        wp_die();
    }
    
    /**
     * Display logs for AJAX responses
     */
    private function display_logs($logs) {
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
            echo '<td>' . esc_html($log->timestamp) . '</td>';
            echo '<td><span class="log-level log-level-' . esc_attr($log->level) . '">' . esc_html(ucfirst($log->level)) . '</span></td>';
            echo '<td>' . esc_html($log->message) . '</td>';
            echo '<td>';
            if ($log->post_id && function_exists('get_edit_post_link')) {
                $edit_link = get_edit_post_link($log->post_id);
                if ($edit_link) {
                    echo '<a href="' . esc_url($edit_link) . '">' . esc_html($log->post_title) . ' (#' . $log->post_id . ')</a>';
                } else {
                    echo esc_html($log->post_title) . ' (#' . $log->post_id . ')';
                }
            } else {
                echo '-';
            }
            echo '</td>';
            echo '<td>' . esc_html($log->post_type ?: '-') . '</td>';
            echo '<td>';
            if ($log->context) {
                echo '<details><summary>' . __('View', 'nexjob-seo') . '</summary><pre>' . esc_html($log->context) . '</pre></details>';
            } else {
                echo '-';
            }
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        
        // Add CSS for log levels (inline for AJAX responses)
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
        details pre { 
            background: #f1f1f1; 
            padding: 10px; 
            margin: 5px 0; 
            border-radius: 3px; 
            font-size: 11px; 
            white-space: pre-wrap; 
            word-wrap: break-word; 
            max-width: 300px;
            max-height: 200px;
            overflow: auto;
        }
        </style>';
    }
    
    /**
     * AJAX: Process specific post (if needed in future)
     */
    public function ajax_process_post() {
        // Verify nonce and permissions
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nexjob_process_post') || !current_user_can('edit_posts')) {
            wp_die('Unauthorized');
        }
        
        $post_id = absint($_POST['post_id'] ?? 0);
        if (!$post_id) {
            wp_send_json_error(array('message' => __('Invalid post ID.', 'nexjob-seo')));
        }
        
        // This would require the post processor, so we'd need to inject it
        // For now, just return success
        wp_send_json_success(array('message' => sprintf(__('Post %d queued for processing.', 'nexjob-seo'), $post_id)));
    }
    
    /**
     * AJAX: Get processing statistics
     */
    public function ajax_get_stats() {
        // Verify nonce and permissions
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'nexjob_stats') || !current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Get log statistics
        $log_stats = $this->logger->get_log_stats();
        $recent_logs = $this->logger->get_recent_logs_count(24);
        
        wp_send_json_success(array(
            'log_stats' => $log_stats,
            'recent_logs_24h' => $recent_logs
        ));
    }
}