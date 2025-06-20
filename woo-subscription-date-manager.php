<?php
/**
 * Plugin Name: WooCommerce Subscription Date Manager Pro
 * Plugin URI: https://designnairobi.agency
 * Description: Efficiently manage and bulk update WooCommerce subscription payment dates with advanced filtering options.
 * Version: 1.0.2
 * Author: Eric Mutema
 * Author URI: https://designnairobi.agency
 * Text Domain: woo-sub-date-manager
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
if (!defined('WCSM_VERSION')) {
    define('WCSM_VERSION', '1.0.2');
}
if (!defined('WCSM_PLUGIN_FILE')) {
    define('WCSM_PLUGIN_FILE', __FILE__);
}
if (!defined('WCSM_PLUGIN_DIR')) {
    define('WCSM_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('WCSM_PLUGIN_URL')) {
    define('WCSM_PLUGIN_URL', plugin_dir_url(__FILE__));
}

/**
 * Main plugin loader class
 */
final class WCSM_Plugin {
    /**
     * Single instance
     */
    protected static $_instance = null;

    /**
     * Core instance
     */
    public $core = null;

    /**
     * Admin instance
     */
    public $admin = null;

    /**
     * Main instance
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        $this->includes();
        $this->init_hooks();

        do_action('wcsm_loaded');
    }

    /**
     * Include required files
     */
    private function includes() {
        // Core includes
        require_once WCSM_PLUGIN_DIR . 'includes/class-wcsm-core.php';

        // Admin includes
        if ($this->is_request('admin')) {
            require_once WCSM_PLUGIN_DIR . 'includes/admin/class-wcsm-admin.php';
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(WCSM_PLUGIN_FILE, array($this, 'activate'));
        register_activation_hook(WCSM_PLUGIN_FILE, array($this, 'update_version'));
        
        add_action('init', array($this, 'init'), 0);
        add_action('init', array($this, 'load_textdomain'));
        add_action('plugins_loaded', array($this, 'update_version'));
        
        // Initialize admin
        if ($this->is_request('admin')) {
            add_action('init', array($this, 'init_admin'));
        }
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Before init action
        do_action('before_wcsm_init');

        // Initialize core
        $this->core = WCSM_Core::instance();

        // Init action
        do_action('wcsm_init');
    }

    /**
     * Initialize admin
     */
    public function init_admin() {
        $this->admin = WCSM_Admin();
    }

    /**
     * Activation hook
     */
    public function activate() {
        if (!get_option('wcsm_version')) {
            add_option('wcsm_version', WCSM_VERSION);
        }
        $this->update_version();
    }

    /**
     * Update version if needed
     */
    public function update_version() {
        $current_version = get_option('wcsm_version');
        
        if ($current_version !== WCSM_VERSION) {
            // Perform any upgrade routines here
            $this->perform_upgrade($current_version, WCSM_VERSION);
            
            // Update version in database
            update_option('wcsm_version', WCSM_VERSION);
            
            // Set upgrade notice
            set_transient('wcsm_upgraded', true, 30);
        }
    }

    /**
     * Perform upgrade routines
     */
    private function perform_upgrade($from_version, $to_version) {
        // Version-specific upgrade routines
        if (version_compare($from_version, '1.0.2', '<')) {
            // Clear any cached data that might be incompatible
            delete_transient('wcsm_subscription_cache');
            
            // Add any new default options
            $options = get_option('wcsm_options', array());
            if (!isset($options['batch_size'])) {
                $options['batch_size'] = 25;
                update_option('wcsm_options', $options);
            }
        }
    }

    /**
     * Load localization files
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'woo-sub-date-manager',
            false,
            dirname(plugin_basename(WCSM_PLUGIN_FILE)) . '/languages/'
        );
    }

    /**
     * What type of request is this?
     */
    private function is_request($type) {
        switch ($type) {
            case 'admin':
                return is_admin();
            case 'ajax':
                return defined('DOING_AJAX');
            case 'cron':
                return defined('DOING_CRON');
            case 'frontend':
                return (!is_admin() || defined('DOING_AJAX')) && !defined('DOING_CRON');
        }
    }

    /**
     * Check plugin requirements
     */
    public static function check_requirements() {
        if (
            !class_exists('WooCommerce') ||
            !class_exists('WC_Subscriptions')
        ) {
            add_action('admin_notices', function() {
                ?>
                <div class="notice notice-error">
                    <p>
                        <?php
                        _e('WooCommerce Subscription Date Manager Pro requires both WooCommerce and WooCommerce Subscriptions to be installed and activated.',
                            'woo-sub-date-manager'
                        );
                        ?>
                    </p>
                </div>
                <?php
            });
            return false;
        }
        return true;
    }
}

/**
 * Main instance of plugin
 */
function WCSM() {
    return WCSM_Plugin::instance();
}

// Global for backwards compatibility
$GLOBALS['wcsm'] = WCSM();

// Initialize plugin when plugins are loaded
add_action('plugins_loaded', function() {
    // Check requirements
    if (WCSM_Plugin::check_requirements()) {
        WCSM();
    }
});