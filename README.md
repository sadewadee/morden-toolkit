# Morden Toolkit

**Contributors:** sadewadee
**Tags:** debug, performance, monitoring, htaccess, php-config, developer-tools
**Requires at least:** 5.0
**Tested up to:** 6.8
**Requires PHP:** 7.4
**Stable tag:** 1.2.18
**License:** GPL v3 or later
**License URI:** https://www.gnu.org/licenses/gpl-3.0.html

Lightweight developer tools for WordPress: Debug Manager, Query Monitor, Htaccess Editor, PHP Config presets.

## Description

Morden Toolkit is a WordPress plugin that provides essential developer tools with a simple UI and safety-first approach. This plugin is designed to provide easy access to debugging, performance monitoring, configuration file editing, and PHP settings management with automatic backup systems.

### Features

#### 🔧 Debug Management
- **One-click debug mode toggle** - Enable/disable WP_DEBUG, WP_DEBUG_LOG, etc.
- **Smart debug log viewer** - Filter by level, time, search logs
- **Safe configuration** - Auto-backup wp-config.php before modifications
- **Clear debug logs** - Clean logs with one click

#### 📊 Query Monitor & Performance
- **Admin bar integration** - Performance metrics integrated with WordPress admin bar
- **Unified display** - Execution time, memory usage, and query count in one indicator
- **Query Monitor style** - Visual design consistent with popular developer tools
- **Click-to-expand details** - Detail panel appears from bottom of screen when clicked
- **Real-time monitoring** - Database queries, execution time, memory usage
- **Detailed metrics** - PHP version, WordPress version, peak memory
- **Mobile responsive** - Optimal display on all device sizes
- **SMTP Logging** - Log all outgoing emails sent via `wp_mail` for easy debugging.

#### 📝 File Editor
- **Safe .htaccess editing** - Built-in syntax validation
- **Auto-backup system** - Max 3 backups with timestamp
- **One-click restore** - Rollback to previous backup
- **Common snippets** - WordPress rewrite, caching, compression, security headers
- **Site accessibility test** - Auto-restore if site breaks
- **Duplicate prevention** - Prevents adding the same snippet twice
- **503 Error protection** - Enhanced validation to prevent service unavailable errors

#### ⚙️ PHP Configuration
- **Preset-based config** - Basic, Medium, High performance presets
- **Multiple methods** - .htaccess, wp-config.php, .user.ini support
- **Auto-detection** - Smart method selection based on server environment
- **Current config display** - View active PHP settings
- **Visual comparison** - Compare current vs preset values

### Security Features

- **Capability checking** - Requires `manage_options`
- **Nonce verification** - All AJAX requests protected
- **Content validation** - Block malicious code patterns
- **Advanced fail-safe mechanism** - Multi-layer protection for wp-config.php modifications
- **Atomic operations** - Safe file operations with rollback and emergency recovery
- **Comprehensive validation** - PHP syntax, site accessibility, and WordPress constants validation
- **Multiple backup points** - Enhanced backup system with pre-restore emergency backups
- **Post-rollback validation** - Ensures site accessibility after recovery operations
- **Site monitoring** - Test accessibility after .htaccess changes
- **Clean uninstall** - Remove all modifications on plugin deletion

## Installation

### Via WordPress Admin
1. Download plugin zip file
2. Upload via Plugins → Add New → Upload Plugin
3. Activate plugin
4. Access via Tools → Morden Toolkit

### Via FTP
1. Extract plugin files
2. Upload `morden-toolkit` folder to `/wp-content/plugins/`
3. Activate via WordPress admin

### Via WP-CLI
```bash
wp plugin install morden-toolkit.zip --activate
```

## Usage

### Debug Management
1. Go to **Tools → Morden Toolkit**
2. Click **Debug Management** tab
3. Toggle debug mode ON/OFF
4. View current debug settings status
5. Clear logs or view detailed logs

### Query Monitor
1. Enable **Performance Bar** in Query Monitor tab
2. Performance bar will appear at bottom of page for logged-in users
3. Click info icon for detailed metrics
4. Monitor database queries, execution time, memory usage

### File Editor
1. Access **.htaccess Editor** tab
2. Edit file content with syntax highlighting
3. File is automatically backed up before save
4. Restore from backup if needed
5. Insert common snippets with one-click
6. Duplicate snippets are automatically prevented
7. Enhanced 503 error protection ensures site stability

### PHP Config
1. Select **PHP Config** tab
2. Choose preset: Basic, Medium, or High Performance
3. Preview setting changes
4. Apply configuration
5. System auto-detects best method (.htaccess, wp-config, .user.ini)

## Screenshots

1. **Main Dashboard** - Overview of all tools and current status
2. **Debug Management** - Toggle debug settings and view logs
3. **Query Monitor** - Performance metrics and database monitoring
4. **Htaccess Editor** - Safe file editing with backup system
5. **PHP Configuration** - Preset-based PHP settings management

## Frequently Asked Questions

### Is this plugin safe to use on production sites?

Yes, this plugin includes multiple safety features:
- Automatic backups before any file modifications
- Content validation to prevent malicious code
- Site accessibility testing after changes
- One-click restore functionality
- Capability checking and nonce verification

### What happens if I break my site?

The plugin includes comprehensive fail-safe mechanisms:
- Automatic backup creation before any changes
- Site accessibility testing after modifications
- One-click restore to previous working state
- Emergency recovery procedures

### Can I use this with other debugging plugins?

Yes, Morden Toolkit is designed to work alongside other debugging tools. It focuses on providing essential developer tools in one lightweight package.

### Does this plugin modify my theme files?

No, this plugin only modifies configuration files (wp-config.php, .htaccess) and creates its own database tables for logging. Your theme files remain untouched.

## System Requirements

- **WordPress:** 5.0 or higher
- **PHP:** 7.4 or higher
- **MySQL:** 5.6+ or MariaDB 10.1+
- **Server:** Apache with mod_rewrite or Nginx

## Upgrade Notice

### 1.2.18
- Code cleanup and optimization
- Removed excessive comments and non-English content
- Improved code formatting and maintainability
- Enhanced WordPress coding standards compliance
- Bug fixes and performance improvements

### 1.2.16
- Enhanced htaccess validation with duplicate prevention
- Added 503 error protection for better site stability
- Improved performance monitoring capabilities
- Bug fixes and security improvements

## Changelog

### [1.2.18] - 2025-08-30
#### Added
- Code cleanup and optimization
- Enhanced code maintainability
- Improved WordPress coding standards compliance

#### Fixed
- Removed excessive comments and redundant code
- Cleaned up non-English content
- Fixed code formatting and spacing issues
- Improved function organization

#### Improved
- Better code readability
- Enhanced performance through code optimization
- Cleaner codebase structure

### [1.2.16] - 2025-08-28
#### Added
- Enhanced htaccess validation with duplicate snippet prevention
- 503 error protection for site stability
- Improved performance monitoring with real-time updates
- Better error handling and user feedback

#### Fixed
- WordPress Coding Standards compliance improvements
- Security enhancements for environment data exposure
- Asset separation and proper enqueuing
- HTTP API compliance for external requests

#### Improved
- Centralized URL handling for better security
- Helper functions for code maintainability
- Internationalization support

### [1.0.0] - 2024-08-16
#### Added
- Initial release
- Debug management with wp-config.php integration
- Query monitor with performance bar
- Safe .htaccess editor with auto-backup
- PHP configuration presets with multi-method support
- Comprehensive admin interface with tabbed navigation
- Security features and permission checking
- Complete test suite
- Translation support

## License

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, see <https://www.gnu.org/licenses/>.

## Credits

Developed by [Mordenhost Team](https://mordenhost.com) with focus on simplicity, safety, and developer experience.

---

**This plugin is designed for developers who need essential WordPress tools in one lightweight and safe package.**
