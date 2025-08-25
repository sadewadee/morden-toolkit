# Morden Toolkit

Lightweight developer tools for WordPress: Debug Manager, Query Monitor, Htaccess Editor, PHP Config presets.

## Overview

Morden Toolkit is a WordPress plugin that provides essential developer tools with a simple UI and safety-first approach. This plugin is designed to provide easy access to debugging, performance monitoring, configuration file editing, and PHP settings management with automatic backup systems.

## Features

### 🔧 Debug Management
- **One-click debug mode toggle** - Enable/disable WP_DEBUG, WP_DEBUG_LOG, etc.
- **Smart debug log viewer** - Filter by level, time, search logs
- **Safe configuration** - Auto-backup wp-config.php before modifications
- **Clear debug logs** - Clean logs with one click

### Query Monitor & Performance
- **Admin bar integration** - Performance metrics integrated with WordPress admin bar
- **Unified display** - Execution time, memory usage, and query count in one indicator
- **Query Monitor style** - Visual design consistent with popular developer tools
- **Click-to-expand details** - Detail panel appears from bottom of screen when clicked
- **Real-time monitoring** - Database queries, execution time, memory usage
- **Detailed metrics** - PHP version, WordPress version, peak memory
- **Mobile responsive** - Optimal display on all device sizes
- **SMTP Logging** - Log all outgoing emails sent via `wp_mail` for easy debugging.

### File Editor
- **Safe .htaccess editing** - Built-in syntax validation
- **Auto-backup system** - Max 3 backups with timestamp
- **One-click restore** - Rollback to previous backup
- **Common snippets** - WordPress rewrite, caching, compression, security headers
- **Site accessibility test** - Auto-restore if site breaks

### ⚙️ PHP Configuration
- **Preset-based config** - Basic, Medium, High performance presets
- **Multiple methods** - .htaccess, wp-config.php, .user.ini support
- **Auto-detection** - Smart method selection based on server environment
- **Current config display** - View active PHP settings
- **Visual comparison** - Compare current vs preset values

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

### PHP Config
1. Select **PHP Config** tab
2. Choose preset: Basic, Medium, or High Performance
3. Preview setting changes
4. Apply configuration
5. System auto-detects best method (.htaccess, wp-config, .user.ini)

## Security Features

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

## System Requirements

- **WordPress:** 5.0 or higher
- **PHP:** 7.4 or higher
- **MySQL:** 5.6+ or MariaDB 10.1+
- **Server:** Apache with mod_rewrite or Nginx

## File Structure

```
morden-toolkit/
├── morden-toolkit.php          # Main plugin file
├── uninstall.php              # Cleanup on uninstall
├── includes/                  # Core classes
│   ├── class-plugin.php       # Service container
│   ├── class-debug.php        # Debug management
│   ├── class-query-monitor.php # Performance monitoring
│   ├── class-htaccess.php     # File editing
│   ├── class-php-config.php   # PHP configuration
│   ├── class-file-manager.php # Backup/restore
│   └── helpers.php           # Utility functions
├── admin/                    # Admin interface
│   ├── views/               # Template files
│   └── assets/             # CSS/JS files
├── public/                  # Frontend assets
│   └── assets/             # Performance bar CSS/JS
├── data/                   # Configuration data
│   └── presets/           # PHP config presets
├── docs/                  # Documentation
├── tests/                 # Unit & integration tests
└── languages/            # Translation files
```

## Development

### Local Setup
```bash
git clone https://github.com/morden-pro/morden-toolkit.git
cd morden-toolkit
composer install --dev
npm install --dev
```

### Running Tests
```bash
# Unit tests
vendor/bin/phpunit tests/unit/

# Integration tests
vendor/bin/phpunit tests/integration/

# All tests
vendor/bin/phpunit
```

### Code Standards
- WordPress Coding Standards
- PHP 7.4+ compatibility
- JSHint for JavaScript
- SCSS for styles

## Contributing

1. Fork repository
2. Create feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push branch (`git push origin feature/amazing-feature`)
5. Open Pull Request

## Changelog

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

GPL v2 or later - see [LICENSE](LICENSE) file

## Support

- **Documentation:** [docs/](docs/)
- **Issues:** [GitHub Issues](https://github.com/sadewadee/morden-toolkit/issues)
- **Support Forum:** [WordPress.org Support](https://wordpress.org/support/plugin/morden-toolkit/)

## Credits

Developed by [Mordenhost Team](https://mordenhost.com) with focus on simplicity, safety, and developer experience.

---

**This plugin is designed for developers who need essential WordPress tools in one lightweight and safe package.**
