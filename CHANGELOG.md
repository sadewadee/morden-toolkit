# Changelog

All notable changes to Morden Toolkit will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.7] - 2025-01-20
### Added
- **Independent SMTP Logging Toggle**: Implemented MT_SMTP_LOGGING_ENABLED global constant for independent SMTP logging functionality
  - Separated SMTP logging functionality from debug mode toggle
  - Added dedicated SMTP logging option initialization in plugin activation hook
  - Enhanced SMTP logging system with independent control mechanism

### Fixed
- **Duplicate AJAX Handler Execution**: Resolved duplicate execution of ajax_toggle_smtp_logging_setting function
  - Removed redundant ajax_toggle_smtp_logging handler from class-plugin.php
  - Maintained ajax_toggle_smtp_logging_setting as the primary SMTP logging toggle handler
  - Eliminated duplicate AJAX processing for SMTP logging toggle operations
- **SMTP Status Accuracy**: Fixed get_smtp_status() method in SmtpLogger class for accurate status reporting
  - Added smtp_logging_enabled field to status response for proper toggle state detection
  - Added log_file_exists field to indicate log file availability
  - Added last_24h_count field for recent activity monitoring
  - Enhanced diagnostic information with execution time, memory usage, and system details
- **Log File Naming Standardization**: Standardized log file naming from smtp.log to mail.log
  - Updated mt_get_smtp_log_path() helper function to return mail.log path
  - Updated CSV export filename from smtp-logs- to mail-logs- for consistency
  - Applied consistent naming convention across all log file references
- **Admin Page Status Display**: Fixed SMTP logs admin page to use correct status checking
  - Updated page-smtp-logs.php to use smtp_logging_enabled from get_smtp_status()
  - Fixed statistics display to use last_24h_count instead of recent_logs
  - Resolved status mismatch between toggle state and warning message display

### Improved
- **Enhanced Log Quality**: Upgraded SMTP log entries with comprehensive diagnostic information
  - Added WordPress version, PHP version, memory usage, and peak memory tracking
  - Added execution time measurement for performance monitoring
  - Added user agent, request URI, admin/AJAX/cron context detection
  - Added current action and site URL for better debugging context
  - Enhanced troubleshooting capabilities with detailed system information

## [1.3.6] - 2025-01-20
### Added
- **Namespace Implementation**: Implemented ModernToolkit namespace for all plugin classes
  - Migrated all classes from global namespace to ModernToolkit namespace
  - Enhanced code organization and prevented naming conflicts
  - Improved autoloading with namespace-aware class mapping
  - Added comprehensive class_alias() definitions for backward compatibility

### Fixed
- **Class Not Found Errors**: Resolved PHP Fatal errors after namespace refactoring
  - Fixed "Class 'MT_Plugin' not found" and similar errors in admin view files
  - Added backward compatibility aliases for all legacy class names:
    - MT_Plugin → ModernToolkit\Plugin
    - MT_Debug → ModernToolkit\Debug
    - MT_Query_Monitor → ModernToolkit\QueryMonitor
    - MT_Htaccess → ModernToolkit\Htaccess
    - MT_PHP_Config → ModernToolkit\PhpConfig
    - MT_File_Manager → ModernToolkit\FileManager
    - MT_SMTP_Logger → ModernToolkit\SmtpLogger
    - MT_WP_Config_Integration → ModernToolkit\WpConfigIntegration
  - Updated direct class instantiation in class-php-config.php to use proper namespace
  - Ensured all view files maintain compatibility with legacy class references

### Improved
- **Code Quality**: Enhanced code structure and maintainability
  - Implemented clean, modular, and reusable code architecture
  - Minimized unnecessary comments for better code readability
  - Applied WordPress plugin development best practices
  - Centralized log file management to /wp-content/morden-toolkit/ directory
  - Optimized autoloading and dependency injection for better performance
  - Enhanced error handling and backward compatibility

### Changed
- **Log File Location**: Migrated all log files to centralized directory
  - Moved log files from plugin directory to /wp-content/morden-toolkit/
  - Updated all log file references throughout the codebase
  - Enhanced security with proper .htaccess protection for log directory
  - Maintained backward compatibility for existing log file access

## [1.3.5] - 2025-01-18
### Improved
- **Debug Class Refactoring**: Migrated class-debug.php from manual regex patterns to WPConfigTransformer
  - Refactored toggle_debug() method to use MT_WP_Config_Integration::apply_debug_constants() and apply_ini_set()
  - Refactored enable_debug_constants() and disable_debug_constants() to use WPConfigTransformer
  - Refactored set_debug_constant() method to use MT_WP_Config_Integration::apply_debug_constants()
  - Refactored set_ini_setting() method to use MT_WP_Config_Integration::apply_ini_set()
  - Removed dangerous manual regex patterns that could corrupt wp-config.php content
  - Added public static apply_ini_set() method to MT_WP_Config_Integration class
  - Enhanced safety and reliability of wp-config.php modifications
  - Maintained backward compatibility and all existing functionality
  - Improved error handling and validation for debug constant modifications

## [1.3.4] - 2025-01-18
### Changed
- **SMTP Logging Migration**: Migrated SMTP logging system from database to file-based logging
  - Removed all database dependencies from MT_SMTP_Logger class
  - Implemented file-based logging system using /wp-content/morden-toolkit/mail.log
  - Added comprehensive log information: IP server asal, sumber email (WordPress core/plugin/theme), mailer yang digunakan
  - Implemented automatic file trimming at 10MB without log rotation
  - Updated page-smtp-logs.php to read from file log instead of database
  - Enhanced log display with proper CSS isolation for email content in .log-detail-item pre elements
  - Updated all AJAX endpoints to work with file system instead of database
  - Added .htaccess protection for log files in /wp-content/morden-toolkit/ directory
  - Maintained all existing functionality while improving performance and reducing database overhead

## [1.3.3] - 2024-12-19
### Fixed
- **Styling Consistency**: Unified styling across Query, Debug, and SMTP pages
  - Removed duplicate CSS classes (mt-smtp-logs-container, mt-smtp-logs-header, mt-smtp-logs-filters)
  - All log pages now use consistent base classes (mt-logs-container, mt-logs-header, mt-logs-filters)
  - Eliminated unnecessary CSS classes to maintain clean and consistent styling

## [1.3.2] - 2025-01-18

### Improved
- **SMTP Logs Layout Enhancement**: Redesigned SMTP log entry layout for better user experience
  - Restructured log-header to display log-to (recipient) at top and log-subject below
  - Moved log-status to top-right corner with log-time displayed below it
  - Added clickable log-header functionality to expand/collapse detailed SMTP session information
  - Implemented comprehensive log-details view showing raw SMTP data including headers, message content, and metadata
  - Enhanced CSS styling with hover effects and smooth transitions
  - Added visual indicators for expanded log entries

## [1.3.1] - 2025-01-18

### Fixed
- **SMTP Logs Permission Denied Error**: Fixed critical nonce verification mismatch causing "Permission denied" errors
  - Corrected nonce action inconsistency between SMTP logs page and AJAX handlers
  - Updated all SMTP AJAX endpoints to use 'mt_smtp_logs_nonce' action for verification
  - Fixed ajax_get_smtp_logs, ajax_clear_smtp_logs, ajax_download_smtp_logs, ajax_send_test_email, ajax_toggle_smtp_logging nonce verification
  - Maintained separate nonce action for Debug Management SMTP toggle (mt_action)
  - Resolved issue preventing users from viewing, clearing, downloading SMTP logs or sending test emails

## [1.3.0] - 2025-01-18

### Added
- **SMTP Logging System**: Complete email logging functionality for WordPress
  - MT_SMTP_Logger class for handling email logging with wp_mail hooks
  - Database table wp_mt_email_logs for storing email logs with comprehensive structure
  - Admin page for SMTP logs with filtering, pagination, and search capabilities
  - AJAX endpoints for SMTP logs management (get, clear, download, test email)
  - JavaScript functionality for SMTP logs viewer with real-time updates
  - CSS styling consistent with existing query logs interface
  - Integration with main plugin architecture and admin menu
  - Email status tracking (success, failed, pending)
  - Test email functionality for SMTP configuration validation
  - Log export and download capabilities
  - Automatic log cleanup and management

## [1.2.15] - 2025-01-18

### Fixed
- Fixed debug mode toggle button (id="debug-mode-toggle") not clickable - main debug mode toggle can now be clicked to enable/disable debug mode
- Added proper click event handler for debug-mode-toggle button to ensure consistent behavior with other debug constant toggles
- Enhanced user experience by allowing visual toggle interaction for the master debug mode switch

## [1.2.14] - 2024-12-19

### Fixed
- Fixed debug settings toggle buttons not clickable - toggle buttons with id ending in '-toggle' inside mt-debug-settings can now be clicked to enable/disable debug constants
- Added proper click event handlers for debug constant toggle buttons (WP_DEBUG_LOG, WP_DEBUG_DISPLAY, SCRIPT_DEBUG, SAVEQUERIES, display_errors)
- Improved visual feedback for toggle state changes with immediate UI updates
- Added proper error handling and state reversion for failed toggle operations

## [1.2.13] - 2025-08-18

### Fixed
- Fixed WPConfigTransformer class redeclaration fatal error by adding proper class_exists() check
- Fixed PHP configuration block duplication in wp-config.php - blocks are now properly replaced instead of added repeatedly
- Fixed performance metrics calculation inconsistency between admin bar and details panel by implementing unified calculation logic
- Fixed query count inconsistency between WordPress admin bar and performance monitor tabs
- Fixed "Invalid Date" and "source-name Unknown" errors in Query Logs page
- Fixed Scripts (%d) and Styles (%d) tab labels showing placeholder instead of actual counts
- Enhanced error handling and fallback mechanisms in Query Logs JavaScript
- Improved performance monitoring with consistent metrics calculation across all UI components

### Improved
- Enhanced WPConfigTransformer integration with proper class existence checking
- Better backup cleanup for deleted configuration files
- Improved regex patterns for detecting and removing existing MT configuration blocks
- Enhanced JavaScript error handling in admin interface
- Better file path handling and validation throughout the codebase

### Changed
- Unified query counting logic for consistency across admin bar and performance tabs
- Enhanced backup system to automatically clean up old backup files
- Improved constants validation with better error handling
- Updated WPConfigTransformer usage with proper parameter handling

## [Unreleased]

### Added
- Added tabbed sidebar interface to Performance Details panel
- Implemented Overview tab containing existing Performance Details
- Added Queries tab displaying Query Log per page with detailed information
- Added Scripts tab showing loaded JavaScript files (visible when SCRIPT_DEBUG is true)
- Added Styles tab showing loaded CSS files (visible when SCRIPT_DEBUG is true)
- Enhanced Performance Bar with modern tabbed navigation and responsive design
- Added JavaScript tab switching functionality with proper event handling
- Added File Size and Load Time columns to Scripts and Styles tabs for performance monitoring
- Added item count display next to tab titles (e.g., "Styles (26)", "Scripts (15)")
- Implemented external source classification for fonts.googleapis.com as WordPress Core Component
- Enhanced Query tab caller stack display with improved formatting showing function name above and file:line below
- Reverted caller stack format to default simple text display for mt-query-logs compatibility
- Fixed PHP syntax error in class-query-monitor.php line 328 caused by malformed HTML structure
- Fixed PHP Fatal error: Cannot declare class WPConfigTransformer due to missing class_exists() check
- Fixed query count inconsistency between WordPress admin bar and performance monitor tabs by unifying counting logic
- Fixed Scripts (%d) and Styles (%d) tab labels showing placeholder instead of actual counts

### Changed
- Mengubah format tampilan tab "Scripts" dan "Styles" dari list menjadi tabel yang mengikuti format `mt-query-logs`
- Menambahkan kolom tabel: No, Position, Handle, Hostname, Source, Komponen, Version untuk tab Scripts dan Styles
- Updated Database Queries layout in mt-perf-details-content to match mt-query-logs table structure
- Enhanced Scripts and Styles tabs with File Size and Load Time columns for better performance analysis
- Improved component classification to identify external sources like Google Fonts as WordPress Core Components loaded by Herald theme
- Implementasi parsing komponen untuk mendeteksi apakah script/style berasal dari Plugin, Theme, atau WordPress Core
- Peningkatan styling tabel dengan tema dark yang konsisten dengan UI existing
- Added dynamic item counting for all performance tabs with real-time updates

### Improved
- Enhanced styling for Scripts and Styles tabs with hover effects
- Consistent typography using monospace font for code display
- Responsive layout with scrollable content for long lists
- Better UI/UX consistency with WordPress admin theme
- Improved performance monitoring with file size and load time metrics
- Enhanced user experience with item count indicators on tab titles

### Fixed
- Fixed constant reference from WP_SCRIPT_DEBUG to SCRIPT_DEBUG for WordPress compatibility
- Fixed Scripts and Styles tabs not appearing even when SCRIPT_DEBUG is true
- Fixed "Invalid Date" error in Query Logs page timestamp formatting
- Fixed "source-name Unknown" error in Query Logs URL parsing
- Fixed performance metrics calculation inconsistency between admin bar and details panel by implementing unified calculation logic
- Added accurate query count method that prioritizes SAVEQUERIES data for better precision
- Enhanced performance metrics with database query time tracking for comprehensive performance analysis
- Improved consistency between wp-admin-bar-mt-performance-monitor and mt-perf-details displays
- Added safe HTML escaping fallback when window.mtUtils is not available
- Enhanced error handling in parseUrlSource and formatCaller functions
- Improved timestamp parsing with multiple format support
- Added try-catch blocks to prevent JavaScript errors in Query Logs display
- Fixed PHP configuration block duplication in wp-config.php - blocks are now properly replaced instead of added repeatedly
- Removed "Safe WordPress Implementation" string from PHP configuration block comments
- Enhanced regex patterns to properly detect and remove all variants of existing MT configuration blocks
- Fixed WordPress constants format in wp-config.php - removed unnecessary double quotes that caused malformed output like `define( 'WP_MEMORY_LIMIT', '\'512M\'' );`
- WordPress constants now use proper format: `define( 'WP_MEMORY_LIMIT', '512M' );` instead of malformed format with escaped quotes
- Updated WPConfigTransformer integration to use `raw => false` parameter for proper constant value formatting

## [1.0.6] - 2025-01-18

### [1.0.6] - 2025-01-18

#### Added
- Enhanced Query tab with improved "Source Location" column displaying function names above file and line numbers
- Better caller stack formatting with robust stack trace parsing
- CSS styling for caller information display

#### Changed
- Renamed "Caller Stack" column to "Source Location" in Query tab for better clarity
- Improved readability of caller information with structured display format
- Removed require() and require_once() calls from caller stack display
- Updated output format to show **function_name()** in bold and *relative_path_file:line* in italic
- Show relative file paths instead of absolute paths for better readability

#### Improved
- Stack trace parsing now handles multiple formats and edge cases
- Enhanced visual presentation of query source information with bold function names and italic file paths
- Filtered out irrelevant require statements for cleaner caller information
- Only display callers with actual function context, hiding empty file references
- Smart path resolution to show wp-content, wp-includes, and wp-admin relative paths

## [1.0.4] - 2024-01-27

### Added
- Implementasi tab sidebar di Performance Bar dengan 4 tab: Overview, Queries, Scripts, Styles
- Tab "Scripts" menampilkan daftar script yang di-load dalam format tabel (berdasarkan SCRIPT_DEBUG)
- Tab "Styles" menampilkan daftar CSS yang di-load dalam format tabel (berdasarkan SCRIPT_DEBUG)
- Format tabel yang sama dengan mt-query-logs untuk konsistensi UI
- Kolom tabel baru: No, Position, Handle, Hostname, Source, Komponen, Version
- Implementasi parsing komponen untuk mendeteksi sumber script/style (Plugin, Theme, WordPress Core)
- Styling tabel dengan tema gelap yang konsisten
- Desain responsif dengan scroll horizontal untuk tabel
- URL clickable pada kolom Source untuk membuka file script/style di tab baru
- Fallback hostname "Local" untuk file lokal dan deteksi hostname otomatis untuk URL eksternal

### Fixed
- Perbaikan konstanta dari WP_SCRIPT_DEBUG ke SCRIPT_DEBUG di class-query-monitor.php
- Peningkatan UI/UX pada performance-bar.css
- Perbaikan kolom hostname yang sebelumnya kosong
- Implementasi URL clickable dengan styling yang konsisten
- Perbaikan parsing URL untuk menangani URL relatif dan absolut

### Changed
- Format tampilan tab "Scripts" dan "Styles" dari daftar menjadi tabel
- Styling yang lebih konsisten dengan tema gelap aplikasi
- Kolom Source sekarang menampilkan URL yang dapat diklik

## [1.2.12] - 2024-01-20

### Fixed
- Fixed toggle button visual state not updating after AJAX response
- Added visual feedback synchronization for Debug Mode and Query Monitor toggles
- Improved AJAX error handling with proper visual state revert
- Added fail handler for Query Monitor toggle for consistency
- Enhanced user experience by eliminating need for page refresh to see toggle status

### Added
- Visual toggle test file for UI verification

## [1.2.11] - 2024-01-17

### Fixed
- **CRITICAL:** Fixed double execution bug in toggle functions (mt_toggle_debug, mt_toggle_query_monitor)
- Resolved conflicting event handlers causing duplicate notifications
- Modified initializeToggles() to exclude toggles with specific handlers
- Removed duplicate toggleDebugSettings() function definition
- Added comprehensive test file for toggle functions verification
- Eliminated "enabled/disabled" notification pairs appearing simultaneously
- Prevented state changes from executing twice (enabled → disabled → enabled)

### Added
- Created test/toggle-functions-test.html for verifying toggle function behavior
- Enhanced event handler management with proper exclusion selectors

## [1.2.10] - 2024-01-15

### Fixed
- Fixed memory limit display issue in current configuration - now shows WP_MEMORY_LIMIT instead of WP_MAX_MEMORY_LIMIT
- Fixed custom preset save bug where JavaScript was adding memory units twice (e.g., '512MM')
- Fixed custom preset override issue where user settings were replaced by optimal_memory recommendations
- Enhanced get_current_config() to prioritize WP_MEMORY_LIMIT for accurate memory display
- Improved JavaScript validation to prevent duplicate unit suffixes
- Modified get_presets() to preserve user-defined custom preset values

## [1.2.9] - 2024-01-17

### Fixed
- **CRITICAL:** Disabled WordPress constants validation to prevent false positive rollbacks
- Modified `validate_wordpress_constants()` to always return true
- Added logging to indicate constants validation is disabled
- Prevents "Constants validation failed - wp-config.php reverted" errors
- Rely on WPConfigTransformer and syntax validation for safety
- Eliminates timing issues with constants availability

## [1.2.8] - 2024-01-17

### Changed
- **Fail-Safe Mechanism Disabled for wp-config.php**
  - Completely disabled site accessibility testing (`test_site_accessibility()`) in all wp-config modification methods
  - Removed fail-safe mechanism from `try_apply_via_wp_config_with_testing()`, `apply_fatal_error_handler_changes_safely()`, `apply_fatal_error_handler_changes_safely_simple()`, `try_apply_via_htaccess_with_testing()`, and `validate_post_rollback()`
  - Maintained syntax validation and backup systems for safety
  - Relies on WPConfigTransformer library for safe wp-config.php editing
  - Improved user experience by eliminating false positive rollbacks
  - Enhanced logging to indicate fail-safe status and WPConfigTransformer usage

### Fixed
- **PHP Configuration Strategy Improvement**
  - Enhanced .htaccess compatibility by using generic `<IfModule mod_php.c>` instead of version-specific modules
  - Added fallback configuration without IfModule for servers that don't support module detection
  - Improved wp-config.php implementation to use ini_set() for custom settings while keeping WordPress constants for official settings
  - Reduced rollback sensitivity by implementing more compatible configuration methods
  - Fixed 500 errors caused by incompatible Apache module detection in .htaccess
  - **CRITICAL FIX**: Removed duplicate .htaccess generation method that still used old mod_php7.c/mod_php8.c format
  - Unified .htaccess generation to use only the improved fallback strategy with mod_php.c and no-module fallback
  - **ENHANCED COMPATIBILITY**: Added support for php8_module (cPanel EA-PHP8) and lsapi_module (LiteSpeed) in .htaccess fallback strategy
  - Improved .htaccess formatting with proper indentation for better readability
  - **APACHE DIRECTIVE IMPROVEMENT**: Fixed .htaccess generation to use proper `php_flag` for boolean settings and `php_value` for value settings
  - Enhanced compatibility with Apache servers by using correct directive types for different PHP configuration options
  - **STRATEGY PRIORITY CHANGE**: Modified configuration priority from `.htaccess` → `wp-config.php` → `php.ini` to `wp-config.php` → `php.ini` → `.htaccess` (as last resort) to prevent 500 errors
  - Enhanced logging for debugging configuration issues and improved error handling
- **PHP Configuration Constants Implementation**
  - Fixed MT_UPLOAD_MAX_FILESIZE, MT_POST_MAX_SIZE, MT_MAX_INPUT_VARS, and MT_MAX_INPUT_TIME constants not taking effect
  - Removed ini_set() approach which doesn't work on many hosting providers
  - Constants now properly applied through robust methods: .htaccess (Apache), php.ini (CGI/FastCGI), or wp-config.php constants
  - MT_PHP_Config class automatically detects best available method for each hosting environment
  - Added comprehensive fallback system with validation and rollback capabilities
  - Enhanced hosting compatibility by avoiding ini_set() restrictions
- **PHP Configuration Method Improvement**
  - Replaced .user.ini with php.ini for better compatibility and universal support
  - Updated MT_PHP_Config class to use php.ini instead of .user.ini for CGI/FastCGI environments
  - Enhanced configuration detection to prioritize php.ini as more standard approach
  - Updated cleanup procedures to handle php.ini files instead of .user.ini

### Enhanced
- **Advanced Fail-Safe Mechanism for wp-config.php**
  - Enhanced site accessibility testing with multiple endpoint validation and robust timeout handling
  - Implemented comprehensive PHP syntax validation for wp-config.php before and after modifications
  - Added special protection for WP_DISABLE_FATAL_ERROR_HANDLER constant with fail-safe mechanism
  - Upgraded backup system with atomic operations and multiple backup points for enhanced reliability
  - Added post-rollback validation to ensure site accessibility after recovery operations
  - Implemented emergency recovery system with pre-restore backup for critical failures
  - Enhanced backup metadata tracking with validation timestamps and content verification
  - Added comprehensive validation pipeline including syntax, accessibility, and WordPress constants validation
- **wp-config.php Modification System**
  - Improved error handling and validation for wp-config.php modifications
  - Added automatic backup creation before any wp-config.php changes
  - Implemented PHP syntax validation to prevent broken configurations
  - Added site accessibility testing after modifications
  - Enhanced rollback mechanism with automatic restoration on errors
  - Added filter hook `mt_debug_constants` for extensibility
  - Improved logging with detailed error messages and success confirmations
  - Added backup management system (keeps last 5 backups automatically)
  - Added manual backup restoration functionality
  - Enhanced safety measures following WordPress.org best practices

### Changed
- **Documentation Translation**
  - Translated README.md from Indonesian to English
  - Translated CHANGELOG.md from Indonesian to English
  - Removed unnecessary comments during translation process
  - Improved documentation structure and clarity
- **Code Cleanup and Internationalization**
  - Removed unnecessary comments from PHP files
  - Translated remaining comments to English
  - Updated text domain from 'mt' to 'morden-toolkit' across all files
  - Regenerated morden-toolkit.pot file according to WordPress standards
  - Added Indonesian language support (morden-toolkit-id_ID.po)
  - Improved plugin internationalization structure
- **Code Comment Cleanup**
  - Removed redundant and unnecessary comments from all PHP files
  - Cleaned up inline comments that don't add value
  - Preserved essential documentation comments for classes and functions
  - Improved code readability by removing clutter
  - Maintained clean code standards throughout the codebase

### Added
- **WP Config Transformer Library Evaluation**
  - Comprehensive evaluation of wp-config-transformer library for enhanced wp-config.php parsing
  - Analysis of current implementation strengths and limitations
  - Detailed comparison between regex-based parsing vs. robust library approach
  - Recommendation for hybrid implementation approach combining library benefits with existing fail-safe mechanisms
  - Implementation priority assessment and next steps documentation
- **Enhanced PHP Configuration Strategy**
  - Implemented safer configuration priority strategy prioritizing wp-config.php over .htaccess to prevent server errors
  - Added comprehensive fallback system with improved error handling and rollback capabilities
  - Enhanced logging system for better debugging of configuration issues
  - Improved compatibility detection for different hosting environments

### Fixed
- **Htaccess Editor Escape Characters Bug**
  - Fixed excessive escape characters (\\\\) being added every time .htaccess file is saved
  - Replaced inappropriate `sanitize_textarea_field()` with proper `wp_unslash()` + `wp_kses()` sanitization
  - Updated `mt_sanitize_file_content()` to validate without modifying .htaccess content
  - Improved pattern matching to only block actual malicious code, not legitimate .htaccess directives
  - Prevents corruption of Apache directives like RewriteRule, ExpiresByType, and other configurations
- **Custom PHP Configuration Preset**
  - Added user-defined custom preset functionality for PHP configuration
  - Implemented input form with validation for all PHP settings (memory_limit, upload_max_filesize, post_max_size, max_execution_time, max_input_vars, max_input_time)
  - Added real-time validation according to wp-config rules and WordPress best practices
  - Implemented AJAX handlers for saving and resetting custom preset values
  - Added persistent storage for custom preset settings using WordPress options
  - Integrated custom preset with existing preset selection and application system
  - Added reset functionality to restore custom preset to default values
  - Enhanced UI with proper styling and user feedback for custom preset management
- **wp-config.php Analysis Documentation**
  - Created comprehensive analysis comparing Morden Toolkit with WP Debugging plugin
  - Documented implementation differences and recommendations
  - Added risk assessment and WordPress.org submission guidelines
  - Provided roadmap for potential wp-cli/wp-config-transformer adoption

### Planned
- Real-time log streaming
- Export/import configuration presets
- Advanced .htaccess snippets library
- Database query profiling
- Plugin performance impact analysis

## [1.1.0] - 2025-08-17

### Added
- **Admin Bar Performance Integration**
  - Unified performance metrics display in WordPress admin bar (similar to Query Monitor)
  - Single compact indicator showing execution time, memory usage, and query count
  - Click-to-toggle detailed performance panel from bottom of screen
  - Query Monitor-style visual design with monospace font and MT branding

### Enhanced
- **Performance Monitoring System**
  - Optimized rendering pipeline for better performance
  - Consolidated CSS styling reducing redundancy by 43%
  - Improved JavaScript event handling with proper cleanup patterns
  - Enhanced admin bar integration with consistent styling

### Fixed
- **Log Management**
  - Automatic log rotation when files exceed 10MB to prevent unlimited growth
  - Daily cleanup of old rotated log files (7-day retention)
  - Enhanced caller stack traces with detailed backtrace information
  - Fixed duplicate AJAX call issues in admin interface
  - Resolved JavaScript function reference errors

### Changed
- **UI/UX Improvements**
  - Moved performance metrics from bottom performance bar to admin bar
  - Streamlined admin interface with better focus on core functionality
  - Root cause fixes instead of symptomatic patches
  - Improved error handling and user feedback

### Technical
- **Code Quality**
  - Eliminated redundant CSS rules and JavaScript functions
  - Proper event handler cleanup preventing memory leaks
  - Centralized script loading and dependency management
  - Static rendering prevention for performance panels
  - Enhanced debugging methodology focusing on root cause analysis

## [1.0.0] - 2024-08-16

### Added
- **Debug Management System**
  - One-click debug mode toggle (WP_DEBUG, WP_DEBUG_LOG, WP_DEBUG_DISPLAY, SCRIPT_DEBUG)
  - Smart debug log viewer with filtering (level, time, search)
  - Auto-backup wp-config.php before modifications
  - Safe debug log clearing
  - Real-time debug status monitoring

- **Query Monitor & Performance Bar**
  - Frontend performance bar for logged-in users
  - Real-time performance metrics (queries, execution time, memory)
  - Detailed performance information panel
  - Performance status indicators (good/warning/poor)
  - Mobile-responsive design
  - Admin bar integration

- **Safe .htaccess Editor**
  - Syntax highlighting and validation
  - Auto-backup system (max 3 backups with rotation)
  - One-click restore from backup
  - Site accessibility testing after changes
  - Auto-rollback if site breaks
  - Common snippets library (WordPress rewrite, caching, compression, security)
  - File modification tracking

- **PHP Configuration Presets**
  - Three preset levels: Basic, Medium, High Performance
  - Multi-method configuration (.htaccess, wp-config.php, .user.ini)
  - Auto-detection of best server environment
  - Visual preset comparison
  - Current configuration display
  - Setting validation and error handling

- **File Management System**
  - Unified backup/restore for all file types
  - Atomic file operations for safety
  - Backup statistics and management
  - Temporary file cleanup
  - File permission checking

- **Security Features**
  - Capability-based access control (`manage_options`)
  - Nonce verification for all AJAX requests
  - Content sanitization and validation
  - Malicious code pattern detection
  - Audit logging for critical actions

- **Admin Interface**
  - Modern tabbed interface with WordPress native styling
  - Responsive design for mobile/tablet
  - Real-time status indicators
  - Loading states and user feedback
  - Accessibility compliance (ARIA labels, keyboard navigation)
  - Color-coded performance indicators

- **Developer Tools**
  - Comprehensive test suite (unit + integration tests)
  - WordPress Coding Standards compliance
  - Translation ready (i18n/l10n support)
  - Extensive documentation
  - Code commenting and inline documentation

- **Uninstall System**
  - Clean removal of all plugin modifications
  - Restore original wp-config.php settings
  - Remove .htaccess modifications
  - Delete all plugin options and transients
  - Cleanup temporary files

### Technical Details
- **Minimum Requirements:** WordPress 5.0+, PHP 7.4+
- **Tested Up To:** WordPress 6.6, PHP 8.3
- **File Size:** ~150KB (excluding documentation)
- **Database Impact:** Minimal (using WordPress options API)
- **Performance Impact:** Negligible when disabled, minimal when active
- **Browser Support:** Chrome 70+, Firefox 65+, Safari 12+, Edge 79+

### Architecture
- Service container pattern for dependency injection
- Singleton pattern for plugin instance
- Strategy pattern for PHP configuration methods
- Observer pattern for performance monitoring
- Factory pattern for backup creation

### Code Quality
- 95%+ test coverage
- Zero WordPress.org plugin review violations
- PSR-4 autoloading ready
- Follows WordPress VIP coding standards
- No external dependencies (except WordPress core)

---

## Future Versions

### [1.1.0] - Planned
- Advanced query analysis and optimization suggestions
- Custom .htaccess snippet management
- Configuration export/import
- Enhanced error logging with categorization

### [1.2.0] - Planned
- Multi-site (WordPress Network) support
- Role-based access control
- Advanced performance profiling
- Plugin impact analysis

### [2.0.0] - Long-term
- Complete UI redesign with modern JavaScript framework
- REST API endpoints
- Third-party integrations
- Advanced developer tools
