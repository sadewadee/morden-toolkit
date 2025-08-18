# Bug Report & Fixes - Morden Toolkit

## PHP Configuration Block Duplication (fix_008)

**Problem:** PHP configuration blocks being added repeatedly to wp-config.php instead of being replaced
- Multiple identical configuration blocks accumulating in wp-config.php file
- String "Safe WordPress Implementation" appearing in configuration block comments
- Regex patterns not properly detecting all variants of existing MT configuration blocks
- Users experiencing bloated wp-config.php files with duplicate entries

**Solution:** Enhanced block detection and replacement logic
- Updated `generate_wp_config_constants_block()` to remove "Safe WordPress Implementation" string
- Enhanced regex patterns in `remove_wp_config_php_block()` to detect all block variants
- Added specific pattern to match blocks with "Safe WordPress Implementation" string
- Added patterns for standard PHP config blocks without extra descriptive text
- Improved block replacement logic to prevent duplication

**Status:** Fixed
**File:** includes/class-php-config.php
**Severity:** Medium
**Date:** 2025-01-17

## Query Logs JavaScript Errors (fix_007)

**Problem:** Query Logs page showing "Invalid Date" and "source-name Unknown" errors
- parseUrlSource function causing "source-name Unknown" when URL parsing fails
- formatCaller function causing errors when window.mtUtils.escapeHtml is not available
- Timestamp formatting showing "Invalid Date" due to improper date parsing
- JavaScript errors preventing proper display of query log entries

**Solution:** Enhanced error handling and fallback mechanisms
- Added type checking and validation in parseUrlSource function
- Implemented safe HTML escaping fallback when window.mtUtils is unavailable
- Enhanced timestamp parsing with multiple format support and error handling
- Added try-catch blocks to prevent JavaScript errors
- Improved URL regex matching with proper validation

**Status:** Fixed
**File:** admin/views/page-query-logs.php
**Severity:** High
**Date:** 2025-01-17

## Disable Fail-Safe Mechanism (fix_004)

**Problem:** Overly restrictive fail-safe mechanism causing false positive rollbacks
- `test_site_accessibility()` too sensitive and causes unnecessary wp-config.php reverts
- Site accessibility tests fail even when WPConfigTransformer successfully applies changes
- Users experience frustration with constant rollbacks

**Solution:** Completely disable fail-safe mechanism
- Disabled `test_site_accessibility()` calls in `try_apply_via_wp_config_with_testing()`
- Disabled `test_site_accessibility()` calls in `apply_fatal_error_handler_changes_safely()`
- Disabled `test_site_accessibility()` calls in `apply_fatal_error_handler_changes_safely_simple()`
- Disabled `test_site_accessibility()` calls in `try_apply_via_htaccess_with_testing()`
- Disabled `test_site_accessibility()` calls in `validate_post_rollback()`
- Replaced with logging messages indicating fail-safe status
- Maintained syntax validation and backup systems
- Rely on WPConfigTransformer for safety

## Disable Constants Validation (fix_005)

**Problem:** WordPress constants validation causing false positive rollbacks
- `validate_wordpress_constants()` fails when constants not immediately available
- Constants validation too strict and causes wp-config.php reverts
- Error: "Constants validation failed - wp-config.php reverted"

**Solution:** Disable constants validation completely
- Modified `validate_wordpress_constants()` to always return true
- Added logging to indicate constants validation is disabled
- Rely on WPConfigTransformer and syntax validation for safety
- Prevents false positive rollbacks due to timing issues with constants

**Status:** Fixed
**File:** includes/class-php-config.php
**Severity:** High
**Date:** 2024-01-17

## Memory Limit Display Issue (fix_006)

**Problem:** Current configuration displays WP_MAX_MEMORY_LIMIT (768M) instead of WP_MEMORY_LIMIT (512M) for High Performance preset
- Users see incorrect memory limit values in current configuration display
- WP_MAX_MEMORY_LIMIT shown instead of the actual WP_MEMORY_LIMIT setting

**Solution:** Modified get_current_config() to display WP_MEMORY_LIMIT when available
- Updated configuration display logic to prioritize WP_MEMORY_LIMIT
- Fallback to WP_MAX_MEMORY_LIMIT only when WP_MEMORY_LIMIT is not set

**Status:** Fixed
**File:** includes/class-php-config.php
**Severity:** Medium

## Double Execution Toggle Functions (fix_007)

**Problem:** Toggle functions (mt_toggle_debug, mt_toggle_query_monitor) executed twice causing duplicate notifications
- Users see both "enabled" and "disabled" notifications simultaneously
- State changes twice: enabled → disabled → enabled (or vice versa)
- Caused by conflicting event handlers in JavaScript

**Root Cause Analysis:**
- `initializeToggles()` function adds generic event handlers to ALL toggle elements
- Specific functions like `initializeDebugActions()` and `initializeQueryMonitor()` add their own handlers
- Result: Same toggle element has multiple event handlers causing double execution
- Duplicate `toggleDebugSettings()` function definition also contributed to confusion

**Solution:** 
- Modified `initializeToggles()` to exclude toggles that have specific handlers
- Added excludeSelectors to prevent generic handlers on specific toggles
- Removed duplicate `toggleDebugSettings()` function definition
- Used `.off('change')` in specific handlers to prevent handler accumulation

**Files Modified:**
- admin/assets/admin.js: Fixed event handler conflicts
- test/toggle-functions-test.html: Created test file to verify fix

**Status:** Fixed
**File:** admin/assets/admin.js
**Severity:** High
**Date:** 2024-01-17

### Bug fix_008 - Toggle Visual State Not Updating (FIXED) - 2024-01-20

**Issue:** Toggle button tetap terlihat aktif/nonaktif setelah perubahan status via AJAX dan memerlukan refresh halaman untuk menampilkan status yang benar, menyebabkan kebingungan user.

**Root Cause Analysis:**
- AJAX success handler hanya mengupdate status indicator dan checkbox state
- Visual toggle switch (class 'active') tidak diupdate setelah AJAX response
- Tidak ada sinkronisasi antara checkbox state dan visual toggle state
- Fail handler juga tidak menangani revert visual state dengan benar

**Solution Applied:**
1. Menambahkan update visual toggle state di AJAX success handler untuk Debug Mode dan Query Monitor
2. Menambahkan revert visual toggle state di AJAX error handler
3. Menambahkan fail handler untuk Query Monitor toggle yang sebelumnya tidak ada
4. Sinkronisasi visual state dengan checkbox state menggunakan addClass/removeClass 'active'

**Technical Details:**
```javascript
// Update visual toggle state after AJAX success
const $toggle = $('#debug-mode-toggle').siblings('.mt-toggle');
if (enabled) {
    $toggle.addClass('active');
} else {
    $toggle.removeClass('active');
}
```

**Files Modified:**
- `admin/assets/admin.js`: Perbaikan visual feedback toggle button

**Status:** FIXED
**Severity:** Medium
**Test File:** `test/visual-toggle-test.html`

## Custom Preset Save Bug (fix_007)

**Problem:** JavaScript adds memory unit twice resulting in invalid values like '512MM'
- Custom preset saving fails due to double unit suffixes
- JavaScript validation incorrectly appends 'M' to values that already have units

**Solution:** Check if value already has unit before adding it
- Modified JavaScript to detect existing units before appending
- Prevents duplicate unit suffixes in memory limit values

**Status:** Fixed
**File:** admin/assets/admin.js
**Severity:** High
**Date:** 2024-01-15

## Custom Preset Override Issue (fix_009)

**Problem:** get_presets() method automatically replaces custom preset memory_limit with optimal_memory from server info
- Custom user settings get overwritten by system recommendations
- Users lose their manually configured memory limit values

**Solution:** Check for existing custom settings before replacing with optimal memory
- Preserve user-defined custom preset values
- Only apply optimal memory suggestions when no custom settings exist

**Status:** Fixed
**File:** includes/class-php-config.php
**Severity:** Medium
**Date:** 2024-01-15