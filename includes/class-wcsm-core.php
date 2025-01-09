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
    const VERSION = '1.0.0';

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