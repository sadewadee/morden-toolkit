=== Morden Toolkit ===
Contributors: mordenhost
Tags: debug, developer, tools, query monitor, performance, htaccess, php config
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.2.16
Requires PHP: 7.4
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Lightweight developer tools for WordPress: Debug Manager, Query Monitor, Htaccess Editor, PHP Config presets.

== Description ==

Morden Toolkit is a WordPress plugin that provides essential developer tools with a simple UI and safety-first approach. This plugin is designed to provide easy access to debugging, performance monitoring, configuration file editing, and PHP settings management with automatic backup systems.

= Features =

**🔧 Debug Management**
* One-click debug mode toggle - Enable/disable WP_DEBUG, WP_DEBUG_LOG, etc.
* Smart debug log viewer - Filter by level, time, search logs
* Safe configuration - Auto-backup wp-config.php before modifications
* Clear debug logs - Clean logs with one click

**Query Monitor & Performance**
* Admin bar integration - Performance metrics integrated with WordPress admin bar
* Unified display - Execution time, memory usage, and query count in one indicator
* Query Monitor style - Visual design consistent with popular developer tools
* Click-to-expand details - Detail panel appears from bottom of screen when clicked
* Real-time monitoring - Database queries, execution time, memory usage
* Detailed metrics - PHP version, WordPress version, peak memory
* Mobile responsive - Optimal display on all device sizes
* SMTP Logging - Log all outgoing emails sent via wp_mail for easy debugging

**File Editor**
* Safe .htaccess editing - Built-in syntax validation
* Auto-backup system - Max 3 backups with timestamp
* One-click restore - Rollback to previous backup
* Common snippets - WordPress rewrite, caching, compression, security headers
* Site accessibility test - Auto-restore if site breaks

**⚙️ PHP Configuration**
* Preset-based config - Basic, Medium, High performance presets
* Multiple methods - .htaccess, wp-config.php, .user.ini support
* Auto-detection - Smart method selection based on server environment
* Current config display - View active PHP settings
* Visual comparison - Compare current vs preset values

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/morden-toolkit` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Tools → Morden Toolkit screen to configure the plugin

== Frequently Asked Questions ==

= Is this plugin safe to use on production sites? =

Yes, Morden Toolkit is designed with safety-first approach. All configuration changes are backed up automatically, and the plugin includes rollback functionality.

= Does this plugin affect site performance? =

No, the plugin is lightweight and only loads its functionality when needed. The performance monitoring features have minimal overhead.

= Can I use this with other debug plugins? =

Yes, Morden Toolkit is designed to work alongside other developer tools and plugins.

== Screenshots ==

1. Main dashboard with all tools overview
2. Debug management interface
3. Performance monitoring in admin bar
4. File editor with syntax highlighting
5. PHP configuration presets

== Changelog ==

= 1.2.16 =
* Code cleanup and improved readability
* Updated translation files
* Added GPL v3.0 license
* Improved documentation

= 1.2.15 =
* Enhanced performance monitoring
* Bug fixes and improvements
* Updated admin interface

= 1.2.14 =
* Added SMTP logging functionality
* Improved debug log viewer
* Security enhancements

== Upgrade Notice ==

= 1.2.16 =
This version includes code cleanup, updated translations, and GPL v3.0 license. Safe to upgrade.

== License ==

This plugin is licensed under the GNU General Public License v3.0 or later.

You should have received a copy of the GNU General Public License along with this program. If not, see https://www.gnu.org/licenses/gpl-3.0.html