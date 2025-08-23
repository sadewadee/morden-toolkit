# Bug Report & Fixes - Morden Toolkit

## WP_DEBUG Toggle Deleting wp-config.php Content (fix_016)

**Problem:** WP_DEBUG toggle causing deletion of wp-config.php content instead of changing value
- Regex pattern in `disable_debug_constants()` method too aggressive: `/if\s*\(\s*!\s*defined\s*\(\s*['"]WP_DEBUG['"]\s*\)\s*\)\s*{\s*define\s*\(\s*['"]WP_DEBUG['"]\s*,\s*[^}]+\s*}/i`
- Pattern using `[^}]+` matches everything until first `}` found, potentially spanning multiple code blocks
- Similar issue in `set_debug_constant()` method with same aggressive pattern
- Users experiencing complete loss of wp-config.php content when toggling WP_DEBUG from true to false
- Pattern deletes content between WP_DEBUG conditional and first closing brace, regardless of context

**Solution:** Fixed regex pattern to match only specific WP_DEBUG conditional block
- Updated pattern to `/if\s*\(\s*!\s*defined\s*\(\s*['"]WP_DEBUG['"]\s*\)\s*\)\s*{\s*define\s*\(\s*['"]WP_DEBUG['"]\s*,\s*[^;)]+\s*\)\s*;\s*}/i`
- Changed `[^}]+` to `[^;)]+\s*\)\s*;` to match only until the define statement ends
- Applied fix to both `disable_debug_constants()` and `set_debug_constant()` methods
- Pattern now specifically targets the define statement within conditional block
- Tested with simulated wp-config.php content to verify other content preservation

**Status:** Fixed
**File:** includes/class-debug.php
**Severity:** Critical
**Date:** 2025-01-20

## Class Not Found Errors After Namespace Refactoring (fix_017)

**Problem:** PHP Fatal errors "Class 'MT_Plugin' not found" and similar errors after namespace implementation
- Refactoring moved all classes to ModernToolkit namespace (e.g., MT_Plugin → ModernToolkit\Plugin)
- View files in admin/views/ still using old class names (MT_Plugin::get_instance())
- Direct class instantiation in includes/class-php-config.php using old names (new MT_Htaccess())
- Missing backward compatibility aliases for legacy class names
- Error occurs in page-toolkit.php, page-logs.php, page-query-logs.php, page-smtp-logs.php
- Specific error: "Class 'MT_Plugin' not found in /admin/views/page-toolkit.php:11"

**Root Cause:** Namespace refactoring broke backward compatibility with existing code
- Classes moved from global namespace to ModernToolkit namespace
- No class_alias() definitions for backward compatibility
- View files and some internal code still referencing old class names

**Solution:** Implemented comprehensive backward compatibility solution
- Added class_alias() definitions in includes/autoloader.php for all refactored classes:
  - MT_Plugin → ModernToolkit\Plugin
  - MT_Debug → ModernToolkit\Debug
  - MT_Query_Monitor → ModernToolkit\QueryMonitor
  - MT_Htaccess → ModernToolkit\Htaccess
  - MT_PHP_Config → ModernToolkit\PhpConfig
  - MT_File_Manager → ModernToolkit\FileManager
  - MT_SMTP_Logger → ModernToolkit\SmtpLogger
  - MT_WP_Config_Integration → ModernToolkit\WpConfigIntegration
- Fixed direct instantiation in class-php-config.php (new MT_Htaccess() → new \ModernToolkit\Htaccess())
- Aliases created after class loading to ensure proper namespace resolution

**Status:** Fixed
**File:** includes/autoloader.php, includes/class-php-config.php, admin/views/*.php
**Severity:** Critical
**Date:** 2025-01-20

## SMTP Logging Toggle Independence Issues (fix_018)

**Problem:** SMTP logging toggle was not functioning as independent feature from debug mode
- SMTP logging functionality was coupled with debug mode toggle system
- No dedicated global constant for SMTP logging control (MT_SMTP_LOGGING_ENABLED)
- SMTP logging state dependent on debug mode constants instead of independent control
- Users unable to enable SMTP logging without enabling full debug mode
- Lack of separation between debugging features and email logging functionality

**Root Cause:** Missing architectural separation between debug and SMTP logging systems
- No dedicated constant for SMTP logging control
- SMTP logging initialization tied to debug mode activation
- Shared control mechanisms between different logging systems

**Solution:** Implemented independent SMTP logging toggle system
- Added MT_SMTP_LOGGING_ENABLED global constant in morden-toolkit.php
- Added dedicated mt_smtp_logging_enabled option initialization in plugin activation hook
- Separated SMTP logging functionality from debug mode dependencies
- Enhanced SMTP logging system with independent control mechanism

**Status:** Fixed
**File:** morden-toolkit.php
**Severity:** High
**Date:** 2025-01-20

## Duplicate AJAX Handler Execution for SMTP Logging (fix_019)

**Problem:** SMTP logging toggle function experiencing duplicate execution
- Two similar AJAX handlers: ajax_toggle_smtp_logging and ajax_toggle_smtp_logging_setting
- Both handlers registered in class-plugin.php causing duplicate processing
- ajax_toggle_smtp_logging calls SmtpLogger::toggle_logging() method
- ajax_toggle_smtp_logging_setting directly updates mt_smtp_logging_enabled option
- Duplicate AJAX processing leading to inconsistent state and potential conflicts

**Root Cause:** Redundant AJAX handler implementation during development
- Both handlers performing similar SMTP logging toggle functionality
- No clear distinction between the two handler purposes
- Duplicate registration in WordPress AJAX system

**Solution:** Removed redundant AJAX handler and standardized implementation
- Removed ajax_toggle_smtp_logging handler and related function from class-plugin.php
- Maintained ajax_toggle_smtp_logging_setting as primary SMTP logging toggle handler
- Eliminated duplicate AJAX processing for SMTP logging toggle operations
- Ensured consistent toggle behavior with single handler implementation

**Status:** Fixed
**File:** includes/class-plugin.php
**Severity:** Medium
**Date:** 2025-01-20

## Inaccurate SMTP Status Reporting (fix_020)

**Problem:** get_smtp_status() method not returning accurate smtp_logging_enabled status
- Method only returned log statistics (total, sent, failed, success_rate, recent_logs)
- Missing smtp_logging_enabled field in status response
- Admin page unable to detect actual SMTP logging toggle state
- Status mismatch between toggle state and warning message display
- Users seeing "SMTP logging is not enabled" even when toggle was active

**Root Cause:** Incomplete status response from SmtpLogger class
- get_smtp_status() method missing essential status fields
- No indication of log file availability or recent activity
- Insufficient diagnostic information for troubleshooting

**Solution:** Enhanced get_smtp_status() method with comprehensive status information
- Added smtp_logging_enabled field using get_option('mt_smtp_logging_enabled')
- Added log_file_exists field to indicate log file availability
- Added last_24h_count field for recent activity monitoring (replacing recent_logs)
- Enhanced diagnostic information with execution time, memory usage, and system details
- Updated admin page to use accurate status fields for proper display

**Status:** Fixed
**File:** includes/class-smtp-logger.php, admin/views/page-smtp-logs.php
**Severity:** High
**Date:** 2025-01-20

## Inconsistent Log File Naming Convention (fix_021)

**Problem:** SMTP log files using inconsistent naming (smtp.log vs mail.log)
- Helper function mt_get_smtp_log_path() returning smtp.log filename
- CSV export using smtp-logs- prefix instead of mail-logs-
- Inconsistent naming convention across log file references
- Recommendation for standardization to mail.log for all email-related logging

**Root Cause:** Legacy naming convention not updated during development
- Original implementation used smtp.log filename
- Export functionality maintained old naming pattern
- No standardization applied across all log file references

**Solution:** Standardized all log file naming to mail.log convention
- Updated mt_get_smtp_log_path() helper function to return mail.log path
- Updated CSV export filename from smtp-logs- to mail-logs- in both class-plugin.php and class-smtp-logger.php
- Applied consistent naming convention across all log file references
- Maintained backward compatibility for existing log files

**Status:** Fixed
**File:** includes/helpers.php, includes/class-plugin.php, includes/class-smtp-logger.php
**Severity:** Low
**Date:** 2025-01-20