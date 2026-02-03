# Changelog

All notable changes to the RequestDesk Connector plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.5.0] - 2026-02-03

### Added
- External Services section in readme.txt for WordPress.org compliance
- Privacy section documenting data handling practices
- PHP 7.4 minimum requirement specification

### Changed
- Prepared for WordPress.org plugin directory submission
- Removed external auto-updater (now uses WordPress.org updates)
- Made templates generic for wider use (removed company-specific references)
- Updated version compatibility (Tested up to WordPress 6.7)

### Removed
- External auto-updater system (class-requestdesk-plugin-updater.php)
- Company-specific content from templates

## [2.4.0] - 2025-12-09

### Added
- **AI-First Schema Markup System** - Automatic content type detection with confidence scoring
- Product/Review schema with WooCommerce integration
- LocalBusiness schema with address/hours detection
- Video schema with YouTube/Vimeo/HTML5 auto-detection
- Course schema with LearnDash/LifterLMS/Tutor LMS integration
- Breadcrumb schema (always recommended for AI)
- Schema Types settings section in AEO Settings
- Detection sensitivity control (40%/60%/80% confidence thresholds)

### Changed
- Enhanced Claude AI prompts for smarter schema suggestions
- Schema types can be individually enabled/disabled

## [2.3.22] - 2025-11-21

### Fixed
- **Critical:** "Generate Q&A Pairs" button not working in post editor
- Missing JavaScript file `assets/js/aeo-admin.js` that prevented AJAX functionality
- Claude model integration with real API model IDs

### Added
- Complete `aeo-admin.js` with proper AJAX handlers for Q&A generation
- Comprehensive debug logging for troubleshooting
- Error handling and user feedback for failed operations

## [2.3.16] - 2025-11-20

### Fixed
- "Enable auto-updates" toggle button not working
- Translation loading issues in auto-update action handlers

### Added
- Proper action handlers for enable/disable auto-update actions
- Success/error notices for auto-update toggle actions

## [2.3.15] - 2025-11-20

### Added
- **Frontend Q&A Display System** - Complete public-facing Q&A pairs display
- `[requestdesk_qa]` shortcode with customizable options
- Optional automatic Q&A display at end of posts/pages
- Full admin control panel for frontend Q&A configuration
- Template functions: `requestdesk_display_qa_pairs()`, `requestdesk_get_qa_pairs()`, `requestdesk_has_qa_pairs()`
- Responsive design with mobile-friendly, dark theme support
- Automatic FAQ schema markup for SEO
- Confidence filtering for Q&A pairs

## [2.3.14] - 2025-11-20

### Fixed
- **Critical:** "133 characters of unexpected output" activation error
- WordPress 6.7.0+ compatibility for translation loading
- Lazy-loaded plugin version data to prevent early translation loading

## [2.3.11] - 2025-11-20

### Fixed
- Auto-updater now safely initializes after activation completes
- Added activation completion flag to prevent early auto-updater initialization

## [2.3.6] - 2025-11-20

### Added
- Auto-update system for plugin updates

## [2.3.1] - 2025-11-13

### Fixed
- `Undefined property: RequestDesk_API::$namespace` error
- WordPress REST route registration compliance
- Empty namespace error for `/update-featured-image` endpoint

### Changed
- All REST routes now use consistent namespace management
- Standardized routes to use `$this->namespace` for maintainability

## [1.1.0] - 2025-10-xx

### Added
- Configurable API key authentication
- Admin interface for API key configuration
- API key validation with clear error messages

### Security
- Exact API key matching with `hash_equals()` for timing attack protection
- Enhanced security warnings for debug mode

### Changed
- **Breaking:** API keys must now be configured in WordPress admin

## [1.0.0] - 2025-10-xx

### Added
- Initial release
- Basic post creation via REST API
- Secure API key authentication
- Category and tag support
- Sync history tracking
- Debug mode for testing
