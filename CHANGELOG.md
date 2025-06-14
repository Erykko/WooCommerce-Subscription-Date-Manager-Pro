# Changelog
All notable changes to WooCommerce Subscription Date Manager Pro will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.2] - 2025-01-09
### Added
- Enhanced batch processing for better performance with large subscription lists
- Version tracking and automatic upgrade routines
- User information logging (username) in activity logs
- Processing delays to prevent server overload during bulk updates
- Better memory management for large datasets
- Upgrade notices for successful plugin updates

### Improved
- Enhanced error handling and logging with more detailed information
- Better input validation and sanitization
- Email filtering now case-insensitive for better matching
- Subscription notes now include plugin version information
- More robust batch processing with configurable batch sizes

### Fixed
- Memory issues when processing large numbers of subscriptions
- Empty email entries in exclusion lists
- Performance issues during bulk updates

### Security
- Enhanced input validation for all form fields
- Improved nonce verification
- Better error logging without exposing sensitive information

## [1.0.1] - 2025-01-09
### Added
- Complete admin interface implementation
- Enhanced AJAX security and error handling
- Improved form validation with real-time feedback
- Better user experience with loading states and progress indication

### Fixed
- Missing admin page rendering functionality
- AJAX security issues with nonce verification
- CSS file structure and asset enqueuing
- JavaScript form validation and error handling

### Security
- Added proper capability checks
- Improved input sanitization
- Enhanced nonce verification
- Added error logging for debugging

## [1.0.0] - 2025-01-09
### Added
- Initial release
- Bulk subscription date update functionality
- Email exclusion management
- Date-based filtering
- Progress tracking
- Error handling and logging
- Admin interface under WooCommerce menu
- Documentation and user guide
- Developer hooks and filters
- Security features
  - Nonce verification
  - Capability checking
  - Input sanitization
  - Error logging
  - Secure AJAX processing

### Security
- Implemented WordPress security best practices
- Added nonce verification for all forms
- Added user capability checks
- Sanitized all inputs
- Escaped all outputs
- Added error logging