# WooCommerce Subscription Date Manager Pro - User Guide

## Overview

WooCommerce Subscription Date Manager Pro helps you manage subscription payment dates efficiently. This guide covers everything you need to know to get started.

## Getting Started

### Prerequisites
- WordPress 5.8+
- WooCommerce 5.0+
- WooCommerce Subscriptions plugin
- PHP 7.4+

### Installation
1. Download the plugin zip file
2. Go to WordPress Admin → Plugins → Add New → Upload Plugin
3. Upload and activate the plugin
4. Navigate to WooCommerce → Date Manager

## Features

### Bulk Update Dates
1. Set new payment date
2. Define exclusion date
3. Add excluded email addresses
4. Click "Update Subscription Dates"

### Email Exclusions
- Add one email per line
- Case-insensitive matching
- Persistent storage

### Date Filtering
- Select new payment date
- Choose cut-off date for exclusions
- Automatic timezone handling

## Best Practices

1. Always backup your database before bulk updates
2. Test with a small subset first
3. Schedule updates during low-traffic periods
4. Review results after completion

## Troubleshooting

### Common Issues

1. **Update fails to start**
   - Check user permissions
   - Verify WooCommerce is active
   - Confirm Subscriptions plugin is active

2. **Some subscriptions not updated**
   - Check exclusion settings
   - Verify subscription status
   - Review error log

3. **Error Messages**
   - Invalid date format: Use YYYY-MM-DD
   - Permission denied: Check user role
   - Database error: Contact hosting provider

### Support

For technical support:
1. Visit: designnairobi.agency
2. Email: support@designnairobi.agency

## Updates

### Version History
- 1.0.0: Initial release

### Updating the Plugin
1. Backup your WordPress site
2. Deactivate current version
3. Upload new version
4. Activate and test

## Security

The plugin includes several security measures:
- Nonce verification
- Capability checking
- Input sanitization
- Error logging
- Secure AJAX processing