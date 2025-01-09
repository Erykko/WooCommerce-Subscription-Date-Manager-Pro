<?php
/**
 * Main plugin class
 *
 * @package WooCommerce Subscription Date Manager Pro
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Main plugin class
 */
class WC_Subscription_Date_Manager {
    /**
     * Plugin version
     */
    const VERSION = '1.0.0';

    /**
     * Single instance of the plugin
     *
     * @var WC_Subscription_Date_Manager|null
     */
    protected static $instance = null;

    /**
     * Main plugin instance
     *
     * @return WC_Subscription_Date_Manager
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        $this->define_constants();
        $this->init_hooks();
    }

    /**
     * Define plugin constants
     */
    private function define_constants() {
        $this->define('WCSM_VERSION', self::VERSION);
        $this->define('WCSM_PLUGIN_DIR', plugin_dir_path(__FILE__));
        $this->define('WCSM_PLUGIN_URL', plugin_dir_url(__FILE__));
    }

    /**
     * Define constant if not already defined
     *
     * @param string $name
     * @param string|bool $value
     */
    private function define($name, $value) {
        if (!defined($name)) {
            define($name, $value);
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Check prerequisites
        add_action('admin_init', array($this, 'check_requirements'));

        // Add menu
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Add settings
        add_action('admin_init', array($this, 'register_settings'));

        // Enqueue assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Add AJAX handlers
        add_action('wp_ajax_wcsm_update_dates', array($this, 'handle_ajax_update'));
    }

    /**
     * Check if requirements are met
     */
    public function check_requirements() {
        if (!class_exists('WooCommerce') || !class_exists('WC_Subscriptions')) {
            add_action('admin_notices', function() {
                ?>
                <div class="notice notice-error">
                    <p><?php _e('WooCommerce Subscription Date Manager Pro requires both WooCommerce and WooCommerce Subscriptions to be installed and activated.', 'woo-sub-date-manager'); ?></p>
                </div>
                <?php
            });
            return false;
        }
        return true;
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Subscription Date Manager', 'woo-sub-date-manager'),
            __('Date Manager', 'woo-sub-date-manager'),
            'manage_woocommerce',
            'subscription-date-manager',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('wcsm_settings', 'wcsm_excluded_emails');
        register_setting('wcsm_settings', 'wcsm_date_format');
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_assets($hook) {
        $screen = get_current_screen();
        
        // Only load on our plugin page
        if (!$screen || 'woocommerce_page_subscription-date-manager' !== $screen->id) {
            return;
        }

        // Plugin version for cache busting
        $version = WCSM_VERSION;

        // Enqueue styles
        wp_enqueue_style(
            'wcsm-admin-styles',
            WCSM_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            $version
        );

        // jQuery UI Datepicker styles
        wp_enqueue_style('jquery-ui-datepicker');

        // Enqueue scripts
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-datepicker');
        
        wp_enqueue_script(
            'wcsm-admin-script',
            WCSM_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'jquery-ui-datepicker'),
            $version,
            true
        );

        // Localize script
        wp_localize_script('wcsm-admin-script', 'wcsmData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcsm_nonce'),
            'i18n' => array(
                'confirmUpdate' => __('Are you sure you want to update subscription dates? This action cannot be undone.', 'woo-sub-date-manager'),
                'processing' => __('Processing...', 'woo-sub-date-manager'),
                'success' => __('Update completed successfully!', 'woo-sub-date-manager'),
                'error' => __('An error occurred while processing the update.', 'woo-sub-date-manager'),
            )
        ));
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        // Check user capabilities
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        // Get settings
        $excluded_emails = get_option('wcsm_excluded_emails', '');
        ?>
        <div class="wrap wcsm-wrap">
            <h1><?php _e('Subscription Date Manager', 'woo-sub-date-manager'); ?></h1>

            <div class="wcsm-card">
                <h2><?php _e('Bulk Update Next Payment Dates', 'woo-sub-date-manager'); ?></h2>
                
                <form id="wcsm-update-form" method="post">
                    <div class="wcsm-form-row">
                        <label for="wcsm-new-date">
                            <?php _e('New Payment Date', 'woo-sub-date-manager'); ?>
                        </label>
                        <input type="text" 
                               id="wcsm-new-date" 
                               name="new_date" 
                               class="wcsm-date-input" 
                               required />
                        <p class="description">
                            <?php _e('Select the new next payment date for subscriptions', 'woo-sub-date-manager'); ?>
                        </p>
                    </div>

                    <div class="wcsm-form-row">
                        <label for="wcsm-exclude-after">
                            <?php _e('Exclude After Date', 'woo-sub-date-manager'); ?>
                        </label>
                        <input type="text" 
                               id="wcsm-exclude-after" 
                               name="exclude_after" 
                               class="wcsm-date-input" 
                               required />
                        <p class="description">
                            <?php _e('Skip subscriptions with payments after this date', 'woo-sub-date-manager'); ?>
                        </p>
                    </div>

                    <div class="wcsm-form-row">
                        <label for="wcsm-excluded-emails">
                            <?php _e('Excluded Emails', 'woo-sub-date-manager'); ?>
                        </label>
                        <textarea id="wcsm-excluded-emails" 
                                  name="excluded_emails" 
                                  class="wcsm-textarea"><?php echo esc_textarea($excluded_emails); ?></textarea>
                        <p class="description">
                            <?php _e('Enter email addresses to exclude (one per line)', 'woo-sub-date-manager'); ?>
                        </p>
                    </div>

                    <div class="wcsm-progress">
                        <div class="wcsm-progress-bar">
                            <div class="wcsm-progress-bar-inner"></div>
                        </div>
                        <p class="wcsm-progress-text"></p>
                    </div>

                    <div id="wcsm-results" style="display: none;"></div>

                    <div class="wcsm-stats">
                        <div class="wcsm-stat-card">
                            <div class="wcsm-stat-label"><?php _e('Updated', 'woo-sub-date-manager'); ?></div>
                            <div id="wcsm-stat-updated" class="wcsm-stat-value updated">0</div>
                        </div>
                        <div class="wcsm-stat-card">
                            <div class="wcsm-stat-label"><?php _e('Skipped', 'woo-sub-date-manager'); ?></div>
                            <div id="wcsm-stat-skipped" class="wcsm-stat-value skipped">0</div>
                        </div>
                        <div class="wcsm-stat-card">
                            <div class="wcsm-stat-label"><?php _e('Errors', 'woo-sub-date-manager'); ?></div>
                            <div id="wcsm-stat-errors" class="wcsm-stat-value errors">0</div>
                        </div>
                    </div>

                    <?php wp_nonce_field('wcsm_nonce', 'wcsm_nonce'); ?>
                    
                    <button type="submit" class="button button-primary" id="wcsm-update-button">
                        <?php _e('Update Subscription Dates', 'woo-sub-date-manager'); ?>
                    </button>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Handle AJAX update request
     */
    public function handle_ajax_update() {
        check_ajax_referer('wcsm_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'woo-sub-date-manager')));
            return;
        }

        // Validate input
        $new_date = sanitize_text_field($_POST['new_date']);
        $exclude_after = sanitize_text_field($_POST['exclude_after']);
        $excluded_emails = array_map('trim', explode("\n", sanitize_textarea_field($_POST['excluded_emails'])));
        
        // Validate dates
        if (!strtotime($new_date) || !strtotime($exclude_after)) {
            wp_send_json_error(array('message' => __('Invalid date format.', 'woo-sub-date-manager')));
            return;
        }

        // Format dates for WooCommerce
        $target_date = date('Y-m-d H:i:s', strtotime($new_date));
        $exclude_timestamp = strtotime($exclude_after);

        try {
            // Process subscriptions
            $results = $this->process_subscriptions($target_date, $exclude_timestamp, $excluded_emails);

            wp_send_json_success(array(
                'message' => sprintf(
                    __('Update complete! Updated: %d, Skipped: %d, Errors: %d', 'woo-sub-date-manager'),
                    $results['updated'],
                    $results['skipped'],
                    $results['errors']
                ),
                'stats' => $results
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Process subscription updates
     *
     * @param string $target_date
     * @param int $exclude_timestamp
     * @param array $excluded_emails
     * @return array
     */
    private function process_subscriptions($target_date, $exclude_timestamp, $excluded_emails) {
        $results = array(
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0
        );

        $args = array(
            'post_type' => 'shop_subscription',
            'post_status' => 'wc-active',
            'posts_per_page' => -1,
            'fields' => 'ids'
        );

        $subscription_ids = get_posts($args);

        foreach ($subscription_ids as $subscription_id) {
            try {
                $subscription = wcs_get_subscription($subscription_id);
                
                if (!$subscription || !is_a($subscription, 'WC_Subscription')) {
                    $results['errors']++;
                    continue;
                }

                // Check excluded emails
                if (in_array(strtolower($subscription->get_billing_email()), array_map('strtolower', $excluded_emails))) {
                    $results['skipped']++;
                    continue;
                }

                // Check recent payments
                $last_payment = $subscription->get_date('last_payment');
                if ($last_payment && strtotime($last_payment) > $exclude_timestamp) {
                    $results['skipped']++;
                    continue;
                }

                // Update next payment date
                $subscription->update_dates(array('next_payment' => $target_date));
                $results['updated']++;

            } catch (Exception $e) {
                $results['errors']++;
            }
        }

        return $results;
    }
}