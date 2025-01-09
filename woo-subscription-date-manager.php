<?php
/**
 * Plugin Name: WooCommerce Subscription Date Manager Pro
 * Plugin URI: https://designnairobi.agency
 * Description: Efficiently manage and bulk update WooCommerce subscription payment dates with advanced filtering options.
 * Version: 1.0.0
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

class WC_Subscription_Date_Manager {
    /**
     * Plugin version
     */
    const VERSION = '1.0.0';

    /**
     * Single instance of the plugin
     */
    protected static $instance = null;

    /**
     * Main instance
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
        <div class="wrap">
            <h1><?php _e('Subscription Date Manager', 'woo-sub-date-manager'); ?></h1>

            <div class="card">
                <h2><?php _e('Bulk Update Next Payment Dates', 'woo-sub-date-manager'); ?></h2>
                
                <form id="wcsm-update-form" method="post">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="new_date"><?php _e('New Payment Date', 'woo-sub-date-manager'); ?></label>
                            </th>
                            <td>
                                <input type="date" id="new_date" name="new_date" required 
                                       class="regular-text" />
                                <p class="description">
                                    <?php _e('Select the new next payment date for subscriptions', 'woo-sub-date-manager'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="exclude_after"><?php _e('Exclude After Date', 'woo-sub-date-manager'); ?></label>
                            </th>
                            <td>
                                <input type="date" id="exclude_after" name="exclude_after" required 
                                       class="regular-text" />
                                <p class="description">
                                    <?php _e('Skip subscriptions with payments after this date', 'woo-sub-date-manager'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="excluded_emails"><?php _e('Excluded Emails', 'woo-sub-date-manager'); ?></label>
                            </th>
                            <td>
                                <textarea id="excluded_emails" name="excluded_emails" 
                                          class="large-text" rows="4"><?php echo esc_textarea($excluded_emails); ?></textarea>
                                <p class="description">
                                    <?php _e('Enter email addresses to exclude (one per line)', 'woo-sub-date-manager'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <div id="wcsm-progress" style="display: none;">
                        <progress class="wcsm-progress-bar" value="0" max="100"></progress>
                        <p class="description wcsm-progress-text"></p>
                    </div>

                    <div id="wcsm-results" style="display: none;" class="notice">
                        <p class="wcsm-results-text"></p>
                    </div>

                    <?php wp_nonce_field('wcsm_update_nonce', 'wcsm_nonce'); ?>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary" id="wcsm-update-button">
                            <?php _e('Update Subscription Dates', 'woo-sub-date-manager'); ?>
                        </button>
                    </p>
                </form>
            </div>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#wcsm-update-form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var $button = $('#wcsm-update-button');
                var $progress = $('#wcsm-progress');
                var $results = $('#wcsm-results');
                
                // Disable button and show progress
                $button.prop('disabled', true);
                $progress.show();
                $results.hide();
                
                // Prepare data
                var formData = {
                    action: 'wcsm_update_dates',
                    nonce: $('#wcsm_nonce').val(),
                    new_date: $('#new_date').val(),
                    exclude_after: $('#exclude_after').val(),
                    excluded_emails: $('#excluded_emails').val()
                };
                
                // Send AJAX request
                $.post(ajaxurl, formData, function(response) {
                    if (response.success) {
                        $results
                            .removeClass('notice-error')
                            .addClass('notice-success')
                            .show()
                            .find('.wcsm-results-text')
                            .html(response.data.message);
                    } else {
                        $results
                            .removeClass('notice-success')
                            .addClass('notice-error')
                            .show()
                            .find('.wcsm-results-text')
                            .html(response.data.message);
                    }
                })
                .fail(function() {
                    $results
                        .removeClass('notice-success')
                        .addClass('notice-error')
                        .show()
                        .find('.wcsm-results-text')
                        .html('Server error occurred. Please try again.');
                })
                .always(function() {
                    $button.prop('disabled', false);
                    $progress.hide();
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Handle AJAX update request
     */
    public function handle_ajax_update() {
        check_ajax_referer('wcsm_update_nonce', 'nonce');
        
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

        // Process subscriptions
        $results = $this->process_subscriptions($target_date, $exclude_timestamp, $excluded_emails);

        wp_send_json_success(array(
            'message' => sprintf(
                __('Process complete. Updated: %d, Skipped: %d, Errors: %d', 'woo-sub-date-manager'),
                $results['updated'],
                $results['skipped'],
                $results['errors']
            )
        ));
    }

    /**
     * Process subscription updates
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

// Initialize plugin
function WC_Subscription_Date_Manager() {
    return WC_Subscription_Date_Manager::instance();
}

add_action('plugins_loaded', 'WC_Subscription_Date_Manager');