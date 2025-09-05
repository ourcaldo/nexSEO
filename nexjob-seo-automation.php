<?php
/**
 * Plugin Name: NexJob SEO Automation
 * Plugin URI: https://nexjob.com
 * Description: Automated SEO meta title, description, and slug generation for job posts with RankMath integration
 * Version: 1.1.0
 * Author: NexJob Team
 * License: GPL v2 or later
 * Text Domain: nexjob-seo
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('NEXJOB_SEO_VERSION', '1.1.0');
define('NEXJOB_SEO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NEXJOB_SEO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('NEXJOB_SEO_PLUGIN_FILE', __FILE__);

// Autoload classes
require_once NEXJOB_SEO_PLUGIN_DIR . 'includes/class-nexjob-seo-plugin.php';
require_once NEXJOB_SEO_PLUGIN_DIR . 'includes/class-nexjob-seo-settings.php';
require_once NEXJOB_SEO_PLUGIN_DIR . 'includes/class-nexjob-seo-logger.php';
require_once NEXJOB_SEO_PLUGIN_DIR . 'includes/class-nexjob-seo-post-processor.php';
require_once NEXJOB_SEO_PLUGIN_DIR . 'includes/class-nexjob-seo-cron-manager.php';
require_once NEXJOB_SEO_PLUGIN_DIR . 'includes/class-nexjob-seo-admin.php';
require_once NEXJOB_SEO_PLUGIN_DIR . 'includes/class-nexjob-seo-ajax-handlers.php';

// Initialize the plugin
function nexjob_seo_init() {
    new NexJob_SEO_Plugin();
}

// Hook to plugins_loaded to ensure WordPress is fully loaded
add_action('plugins_loaded', 'nexjob_seo_init');

// Plugin activation hook
register_activation_hook(__FILE__, array('NexJob_SEO_Plugin', 'activate'));

// Plugin deactivation hook
register_deactivation_hook(__FILE__, array('NexJob_SEO_Plugin', 'deactivate'));