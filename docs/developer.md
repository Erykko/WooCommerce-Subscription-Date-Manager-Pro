# Developer Documentation

## Architecture

The plugin follows WordPress plugin architecture best practices:

```
woo-subscription-date-manager/
├── assets/
│   ├── css/
│   └── js/
├── includes/
│   ├── class-wc-subscription-date-manager.php
│   └── admin/
├── languages/
├── docs/
├── index.php
├── readme.txt
├── LICENSE
└── woo-subscription-date-manager.php
```

## Hooks and Filters

### Actions

```php
/**
 * Fires before subscription update process starts
 *
 * @param string $target_date New target date
 * @param array $excluded_emails List of excluded emails
 */
do_action('wcsm_before_update', $target_date, $excluded_emails);

/**
 * Fires after subscription update process completes
 *
 * @param array $results Update results array
 */
do_action('wcsm_after_update', $results);

/**
 * Fires when a single subscription is updated
 *
 * @param WC_Subscription $subscription
 * @param string $new_date
 */
do_action('wcsm_subscription_updated', $subscription, $new_date);
```

### Filters

```php
/**
 * Filter subscription query arguments
 *
 * @param array $args WP_Query arguments
 * @return array Modified arguments
 */
apply_filters('wcsm_subscription_query_args', $args);

/**
 * Filter whether a subscription should be updated
 *
 * @param bool $update Whether to update
 * @param WC_Subscription $subscription
 * @return bool Whether to update
 */
apply_filters('wcsm_should_update_subscription', $update, $subscription);
```

## Database

The plugin uses the following WordPress options:
- `wcsm_excluded_emails`: Stored email exclusion list
- `wcsm_date_format`: Date format preference
- `wcsm_last_update`: Timestamp of last update

## API Integration

### REST API Endpoints

```php
/wp-json/wcsm/v1/update
Method: POST
Required capabilities: manage_woocommerce
Parameters:
- new_date (string): Target date
- exclude_after (string): Exclusion date
- excluded_emails (array): Emails to exclude
```

## Extending the Plugin

### Adding Custom Filters

```php
add_filter('wcsm_should_update_subscription', function($update, $subscription) {
    // Add custom logic
    return $update;
}, 10, 2);
```

### Custom Admin Pages

```php
add_action('wcsm_admin_pages', function($admin_pages) {
    // Add custom admin pages
    return $admin_pages;
});
```

## Testing

### PHPUnit Tests

1. Install dependencies:
```bash
composer install
```

2. Run tests:
```bash
./vendor/bin/phpunit
```

### WordPress Testing

1. Set up WordPress testing environment
2. Run:
```bash
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

## Build Process

1. Install Node dependencies:
```bash
npm install
```

2. Build assets:
```bash
npm run build
```

3. Create release:
```bash
npm run release
```

## Deployment

1. Ensure version numbers are updated:
   - Plugin header
   - readme.txt
   - CHANGELOG.md

2. Create release branch:
```bash
git checkout -b release/1.0.0
```

3. Build and test

4. Tag release:
```bash
git tag -a v1.0.0 -m "Release 1.0.0"
```