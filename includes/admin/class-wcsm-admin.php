<?php
/**
 * Admin functionality for WooCommerce Subscription Date Manager Pro
 *
 * @package WooCommerce Subscription Date Manager Pro
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WCSM_Admin {
    /**
     * Single instance of the class
     *
     * @var WCSM_Admin|null
     */
    protected static $instance = null;

    /**
     * Main Admin instance
     *
     * @return WCSM_Admin
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
        // Settings
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'handle_settings_save'));

        // Menu and pages
        add_action('admin_menu', array($this, 'admin_menu'));

        // AJAX handlers
        add_action('wp_ajax_wcsm_get_subscriptions', array($this, 'ajax_get_subscriptions'));
        add_action('wp_ajax_wcsm_preview_changes', array($this, 'ajax_preview_changes'));
        
        // Notices
        add_action('admin_notices', array($this, 'admin_notices'));
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'wcsm_settings',
            'wcsm_options',
            array(
                'type' => 'array',
                'sanitize_callback' => array($this, 'sanitize_settings')
            )
        );

        add_settings_section(
            'wcsm_general_settings',
            __('General Settings', 'woo-sub-date-manager'),
            array($this, 'render_general_settings_section'),
            'wcsm_settings'
        );

        add_settings_field(
            'default_excluded_emails',
            __('Default Excluded Emails', 'woo-sub-date-manager'),
            array($this, 'render_excluded_emails_field'),
            'wcsm_settings',
            'wcsm_general_settings'
        );

        add_settings_field(
            'batch_size',
            __('Processing Batch Size', 'woo-sub-date-manager'),
            array($this, 'render_batch_size_field'),
            'wcsm_settings',
            'wcsm_general_settings'
        );
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();

        if (isset($input['default_excluded_emails'])) {
            $emails = explode("\n", $input['default_excluded_emails']);
            $sanitized_emails = array();
            
            foreach ($emails as $email) {
                $email = sanitize_email(trim($email));
                if ($email) {
                    $sanitized_emails[] = $email;
                }
            }
            
            $sanitized['default_excluded_emails'] = implode("\n", $sanitized_emails);
        }

        if (isset($input['batch_size'])) {
            $sanitized['batch_size'] = absint($input['batch_size']);
            if ($sanitized['batch_size'] < 10) {
                $sanitized['batch_size'] = 10;
            }
            if ($sanitized['batch_size'] > 100) {
                $sanitized['batch_size'] = 100;
            }
        }

        return $sanitized;
    }

    /**
     * Render settings section
     */
    public function render_general_settings_section() {
        ?>
        <p>
            <?php _e('Configure general settings for the subscription date manager.', 'woo-sub-date-manager'); ?>
        </p>
        <?php
    }

    /**
     * Handle preview request
     */
    public function ajax_preview_changes() {
        check_ajax_referer('wcsm_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'woo-sub-date-manager')));
            return;
        }

        $new_date = sanitize_text_field($_POST['new_date']);
        $exclude_after = sanitize_text_field($_POST['exclude_after']);
        $excluded_emails = array_map('trim', explode("\n", sanitize_textarea_field($_POST['excluded_emails'])));

        try {
            $preview = $this->get_preview_data($new_date, $exclude_after, $excluded_emails);
            wp_send_json_success($preview);
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Get subscription list for preview
     */
    private function get_preview_data($new_date, $exclude_after, $excluded_emails) {
        $subscriptions = wcs_get_subscriptions(array(
            'subscriptions_per_page' => 10,
            'subscription_status' => 'active'
        ));

        $preview_data = array(
            'total' => count($subscriptions),
            'will_update' => 0,
            'will_skip' => 0,
            'sample_list' => array()
        );

        foreach ($subscriptions as $subscription) {
            $status = $this->check_subscription_status(
                $subscription,
                $new_date,
                $exclude_after,
                $excluded_emails
            );

            if ($status['will_update']) {
                $preview_data['will_update']++;
            } else {
                $preview_data['will_skip']++;
            }

            // Add to sample list
            if (count($preview_data['sample_list']) < 5) {
                $preview_data['sample_list'][] = array(
                    'id' => $subscription->get_id(),
                    'email' => $subscription->get_billing_email(),
                    'current_date' => $subscription->get_date('next_payment'),
                    'will_update' => $status['will_update'],
                    'skip_reason' => $status['skip_reason']
                );
            }
        }

        return $preview_data;
    }

    /**
     * Check individual subscription status
     */
    private function check_subscription_status($subscription, $new_date, $exclude_after, $excluded_emails) {
        $status = array(
            'will_update' => true,
            'skip_reason' => ''
        );

        // Check excluded email
        if (in_array(strtolower($subscription->get_billing_email()), array_map('strtolower', $excluded_emails))) {
            $status['will_update'] = false;
            $status['skip_reason'] = __('Email excluded', 'woo-sub-date-manager');
            return $status;
        }

        // Check payment date
        $last_payment = $subscription->get_date('last_payment');
        if ($last_payment && strtotime($last_payment) > strtotime($exclude_after)) {
            $status['will_update'] = false;
            $status['skip_reason'] = __('Recent payment', 'woo-sub-date-manager');
            return $status;
        }

        return $status;
    }

    /**
     * Add admin menu
     */
    public function admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Subscription Date Manager', 'woo-sub-date-manager'),
            __('Date Manager', 'woo-sub-date-manager'),
            'manage_woocommerce',
            'subscription-date-manager',
            array($this, 'render_admin_page')
        );

        add_submenu_page(
            'woocommerce',
            __('Date Manager Settings', 'woo-sub-date-manager'),
            __('Date Manager Settings', 'woo-sub-date-manager'),
            'manage_woocommerce',
            'subscription-date-manager-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Subscription Date Manager Settings', 'woo-sub-date-manager'); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('wcsm_settings');
                do_settings_sections('wcsm_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Display admin notices
     */
    public function admin_notices() {
        if (isset($_GET['wcsm-updated'])) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('Settings saved successfully.', 'woo-sub-date-manager'); ?></p>
            </div>
            <?php
        }

        if (isset($_GET['wcsm-error'])) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p><?php _e('An error occurred while saving settings.', 'woo-sub-date-manager'); ?></p>
            </div>
            <?php
        }
    }

    /**
     * Handle settings save
     */
    public function handle_settings_save() {
        if (
            isset($_POST['wcsm_settings_nonce']) &&
            wp_verify_nonce($_POST['wcsm_settings_nonce'], 'wcsm_save_settings')
        ) {
            // Process settings save
            update_option('wcsm_settings', $this->sanitize_settings($_POST['wcsm_settings']));
            wp_redirect(add_query_arg('wcsm-updated', 'true'));
            exit;
        }
    }

    /**
     * Render excluded emails field
     */
    public function render_excluded_emails_field() {
        $options = get_option('wcsm_options', array());
        $excluded_emails = isset($options['default_excluded_emails']) ? $options['default_excluded_emails'] : '';
        ?>
        <textarea name="wcsm_options[default_excluded_emails]" 
                  rows="5" 
                  class="large-text code"><?php echo esc_textarea($excluded_emails); ?></textarea>
        <p class="description">
            <?php _e('Enter one email address per line. These emails will be excluded by default.', 'woo-sub-date-manager'); ?>
        </p>
        <?php
    }

    /**
     * Render batch size field
     */
    public function render_batch_size_field() {
        $options = get_option('wcsm_options', array());
        $batch_size = isset($options['batch_size']) ? absint($options['batch_size']) : 25;
        ?>
        <input type="number" 
               name="wcsm_options[batch_size]" 
               value="<?php echo esc_attr($batch_size); ?>" 
               min="10" 
               max="100" 
               step="5" />
        <p class="description">
            <?php _e('Number of subscriptions to process in each batch (10-100).', 'woo-sub-date-manager'); ?>
        </p>
        <?php
    }

    /**
     * Get settings
     *
     * @return array
     */
    public function get_settings() {
        return get_option('wcsm_options', array(
            'default_excluded_emails' => '',
            'batch_size' => 25
        ));
    }
}

// Initialize admin
function WCSM_Admin() {
    return WCSM_Admin::instance();
}

WCSM_Admin();