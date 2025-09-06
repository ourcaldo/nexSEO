<?php
/**
 * Plugin Name: nexSEO
 * Plugin URI: https://nexjob.com
 * Description: Automated SEO meta title, description, and slug generation for job posts with RankMath integration and advanced webhook system
 * Version: 1.3.0
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Author: NexJob Team
 * Author URI: https://nexjob.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: nexjob-seo
 * Domain Path: /languages
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('NEXJOB_SEO_VERSION', '1.3.0');
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

// Webhook classes
require_once NEXJOB_SEO_PLUGIN_DIR . 'includes/class-nexjob-seo-webhook-database.php';
require_once NEXJOB_SEO_PLUGIN_DIR . 'includes/class-nexjob-seo-webhook-data.php';
require_once NEXJOB_SEO_PLUGIN_DIR . 'includes/class-nexjob-seo-webhook-manager.php';
require_once NEXJOB_SEO_PLUGIN_DIR . 'includes/class-nexjob-seo-field-mapper.php';
require_once NEXJOB_SEO_PLUGIN_DIR . 'includes/class-nexjob-seo-webhook-processor.php';
require_once NEXJOB_SEO_PLUGIN_DIR . 'includes/class-nexjob-seo-webhook-admin.php';


// Global plugin instance for access in admin pages
global $nexjob_seo_plugin;

// Initialize the plugin
function nexjob_seo_init() {
    global $nexjob_seo_plugin;
    $nexjob_seo_plugin = new NexJob_SEO_Plugin();
}

// Hook to plugins_loaded to ensure WordPress is fully loaded
add_action('plugins_loaded', 'nexjob_seo_init');

// Plugin activation hook
register_activation_hook(__FILE__, array('NexJob_SEO_Plugin', 'activate'));

// Plugin deactivation hook
register_deactivation_hook(__FILE__, array('NexJob_SEO_Plugin', 'deactivate'));