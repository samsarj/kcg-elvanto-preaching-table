<?php
/**
 * Plugin Name: KCG Elvanto Preaching Table
 * Description: Displays a table of Sunday services and their dates using the Elvanto API Provider.
 * Version: 1.0.1
 * Author: Sam Sarjudeen
 * Author URI: https://github.com/samsarj
 * Plugin URI: https://github.com/samsarj/kcg-elvanto-preaching-table
 * GitHub Plugin URI: https://github.com/samsarj/kcg-elvanto-preaching-table
 * Primary Branch: main
 * Text Domain: kcg-elvanto-preaching-table
 * Requires Plugins: kcg-elvanto-api-provider
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('KCG_ELVANTO_PREACHING_TABLE_VERSION', '1.0.0');
define('KCG_ELVANTO_PREACHING_TABLE_PATH', plugin_dir_path(__FILE__));
define('KCG_ELVANTO_PREACHING_TABLE_URL', plugin_dir_url(__FILE__));

// Include class files
if (!class_exists('KCG_Elvanto_Preaching_Display')) {
    require_once KCG_ELVANTO_PREACHING_TABLE_PATH . 'includes/class-kcg-elvanto-preaching-display.php';
}

if (!class_exists('KCG_Elvanto_Fetcher')) {
    require_once KCG_ELVANTO_PREACHING_TABLE_PATH . 'includes/class-kcg-elvanto-fetcher.php';
}

if (!class_exists('KCG_Elvanto_Preaching_Admin')) {
    require_once KCG_ELVANTO_PREACHING_TABLE_PATH . 'includes/class-kcg-elvanto-preaching-admin.php';
}

// Initialize the plugin
class KCG_Elvanto_Preaching_Table {
    
    private static $instance = null;
    
    public function __construct() {
        self::$instance = $this;
        
        // Initialize on plugins_loaded hook
        add_action('plugins_loaded', array($this, 'init'), 5);
    }

    /**
     * Register activation and deactivation hooks.
     */
    public static function bootstrap() {
        register_activation_hook(__FILE__, array('KCG_Elvanto_Preaching_Table', 'activate'));
        register_deactivation_hook(__FILE__, array('KCG_Elvanto_Preaching_Table', 'deactivate'));
    }
    
    public static function get_instance() {
        return self::$instance;
    }
    
    /**
     * Plugin activation
     */
    public static function activate() {
        // Ensure the provider is active before scheduling.
        if (!class_exists('KCG_Elvanto_API_Registry')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die('KCG Elvanto Preaching Table requires the KCG Elvanto API Provider plugin to be installed and active.');
        }

        if (!wp_next_scheduled('kcg_elvanto_fetch_hook')) {
            wp_schedule_event(time(), 'daily', 'kcg_elvanto_fetch_hook');
        }
    }
    
    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        // Clean up cron job
        $timestamp = wp_next_scheduled('kcg_elvanto_fetch_hook');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'kcg_elvanto_fetch_hook');
        }
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        // Check if required plugin is active
        if (!$this->is_api_provider_active()) {
            add_action('admin_notices', array($this, 'missing_api_provider_notice'));
            return;
        }
        
        // Initialize the display manager
        new KCG_Elvanto_Preaching_Display();
        
        // Initialize admin interface
        if (is_admin()) {
            new KCG_Elvanto_Preaching_Admin();
        }
        
        // Hook for cron job
        add_action('kcg_elvanto_fetch_hook', array('KCG_Elvanto_Fetcher', 'fetch_data'));
        
        // Enqueue styles and scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    /**
     * Check if the API Provider plugin is active
     */
    private function is_api_provider_active() {
        return class_exists('KCG_Elvanto_API_Registry');
    }
    
    /**
     * Display admin notice if API provider is missing
     */
    public function missing_api_provider_notice() {
        ?>
        <div class="notice notice-error is-dismissible">
            <p><strong>KCG Elvanto Preaching Table</strong> requires the <strong>KCG Elvanto API Provider</strong> plugin to be installed and activated.</p>
        </div>
        <?php
    }
    
    /**
     * Enqueue plugin styles and scripts
     */
    public function enqueue_assets() {
        wp_enqueue_style(
            'kcg-elvanto-preaching-table',
            KCG_ELVANTO_PREACHING_TABLE_URL . 'includes/assets/preaching-table.css',
            array(),
            KCG_ELVANTO_PREACHING_TABLE_VERSION
        );
        
        wp_enqueue_script(
            'kcg-elvanto-preaching-table',
            KCG_ELVANTO_PREACHING_TABLE_URL . 'includes/assets/preaching-table.js',
            array(),
            KCG_ELVANTO_PREACHING_TABLE_VERSION,
            true
        );
    }
}

// Register activation/deactivation handlers and instantiate the plugin
KCG_Elvanto_Preaching_Table::bootstrap();
new KCG_Elvanto_Preaching_Table();
