<?php
/**
 * Core functionality for WooCommerce Subscription Date Manager Pro
 *
 * @package WooCommerce Subscription Date Manager Pro
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WCSM_Core {
    /**
     * Plugin version
     */
    const VERSION = '1.0.2';

    /**
     * Single instance of the class
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
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Add AJAX handlers
        add_action('wp_ajax_wcsm_update_dates', array($this, 'handle_ajax_update'));
        
        // Add admin notices for upgrades
        add_action('admin_notices', array($this, 'upgrade_notice'));
    }

    /**
     * Show upgrade notice
     */
    public function upgrade_notice() {
        if (get_transient('wcsm_upgraded')) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <strong><?php _e('WooCommerce Subscription Date Manager Pro', 'woo-sub-date-manager'); ?></strong>
                    <?php printf(__('has been updated to version %s successfully!', 'woo-sub-date-manager'), WCSM_VERSION); ?>
                </p>
            </div>
            <?php
            delete_transient('wcsm_upgraded');
        }
    }

    /**
     * Handle AJAX update request
     */
    public function handle_ajax_update() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wcsm_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'woo-sub-date-manager')));
            return;
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'woo-sub-date-manager')));
            return;
        }

        // Validate input
        if (!isset($_POST['new_date']) || !isset($_POST['exclude_after'])) {
            wp_send_json_error(array('message' => __('Missing required fields.', 'woo-sub-date-manager')));
            return;
        }

        $new_date = sanitize_text_field($_POST['new_date']);
        $exclude_after = sanitize_text_field($_POST['exclude_after']);
        $excluded_emails = isset($_POST['excluded_emails']) ? 
            array_map('trim', explode("\n", sanitize_textarea_field($_POST['excluded_emails']))) : 
            array();
        
        // Remove empty emails
        $excluded_emails = array_filter($excluded_emails);
        
        // Validate dates
        if (!strtotime($new_date) || !strtotime($exclude_after)) {
            wp_send_json_error(array('message' => __('Invalid date format.', 'woo-sub-date-manager')));
            return;
        }

        // Check if new date is after exclude date
        if (strtotime($new_date) <= strtotime($exclude_after)) {
            wp_send_json_error(array('message' => __('New payment date must be after the exclusion date.', 'woo-sub-date-manager')));
            return;
        }

        // Format dates for WooCommerce
        $target_date = date('Y-m-d H:i:s', strtotime($new_date));
        $exclude_timestamp = strtotime($exclude_after);

        try {
            // Process subscriptions
            $results = $this->process_subscriptions($target_date, $exclude_timestamp, $excluded_emails);

            // Log the update
            $this->log_update($results, $target_date, $exclude_after, $excluded_emails);

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
            error_log('WCSM Update Error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => __('An error occurred while processing the update. Please check the error log.', 'woo-sub-date-manager')
            ));
        }
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

        // Get active subscriptions
        $subscriptions = wcs_get_subscriptions(array(
            'subscription_status' => 'active',
            'subscriptions_per_page' => -1
        ));

        if (empty($subscriptions)) {
            return $results;
        }

        // Get batch size from settings
        $options = get_option('wcsm_options', array());
        $batch_size = isset($options['batch_size']) ? absint($options['batch_size']) : 25;
        
        $processed = 0;
        foreach ($subscriptions as $subscription) {
            try {
                if (!$subscription || !is_a($subscription, 'WC_Subscription')) {
                    $results['errors']++;
                    continue;
                }

                // Check excluded emails (case-insensitive)
                $billing_email = $subscription->get_billing_email();
                if ($billing_email && in_array(strtolower($billing_email), array_map('strtolower', $excluded_emails))) {
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
                
                // Add note to subscription
                $subscription->add_order_note(
                    sprintf(
                        __('Next payment date updated to %s via Date Manager Pro v%s', 'woo-sub-date-manager'),
                        date('Y-m-d', strtotime($target_date)),
                        self::VERSION
                    )
                );

                $results['updated']++;

                // Fire action hook
                do_action('wcsm_subscription_updated', $subscription, $target_date);

                // Process in batches to avoid memory issues
                $processed++;
                if ($processed % $batch_size === 0) {
                    // Small delay to prevent overwhelming the server
                    usleep(100000); // 0.1 seconds
                }

            } catch (Exception $e) {
                error_log('WCSM Subscription Update Error (ID: ' . $subscription->get_id() . '): ' . $e->getMessage());
                $results['errors']++;
            }
        }

        return $results;
    }

    /**
     * Log update activity
     */
    private function log_update($results, $target_date, $exclude_after, $excluded_emails) {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'user_login' => wp_get_current_user()->user_login,
            'target_date' => $target_date,
            'exclude_after' => $exclude_after,
            'excluded_emails_count' => count($excluded_emails),
            'results' => $results,
            'version' => self::VERSION
        );

        // Store in option (keep last 10 entries)
        $logs = get_option('wcsm_update_logs', array());
        array_unshift($logs, $log_entry);
        $logs = array_slice($logs, 0, 10);
        update_option('wcsm_update_logs', $logs);
    }

    /**
     * Get update logs
     */
    public function get_update_logs() {
        return get_option('wcsm_update_logs', array());
    }

    /**
     * Clear update logs
     */
    public function clear_update_logs() {
        delete_option('wcsm_update_logs');
    }

    /**
     * Get plugin version
     */
    public function get_version() {
        return self::VERSION;
    }
}