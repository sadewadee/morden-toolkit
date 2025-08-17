# Morden Toolkit

Developer tools lightweight untuk WordPress: Debug Manager, Query Monitor, Htaccess Editor, PHP Config presets.

## Overview

Morden Toolkit adalah plugin WordPress yang menyediakan tools essential untuk developer dengan UI yang sederhana dan pendekatan safety-first. Plugin ini dirancang untuk memberikan akses mudah ke debugging, monitoring performa, editing file konfigurasi, dan management setting PHP dengan sistem backup otomatis.

## Features

### 🔧 Debug Management
- **One-click debug mode toggle** - Enable/disable WP_DEBUG, WP_DEBUG_LOG, dll
- **Smart debug log viewer** - Filter by level, time, search logs
- **Safe configuration** - Auto-backup wp-config.php sebelum modifikasi
- **Clear debug logs** - Bersihkan log dengan satu klik

### 📊 Query Monitor & Performance
- **Admin bar integration** - Performance metrics terintegrasi dengan WordPress admin bar
- **Unified display** - Execution time, memory usage, dan query count dalam satu indicator
- **Query Monitor style** - Visual design konsisten dengan developer tools populer
- **Click-to-expand details** - Panel detail muncul dari bawah layar saat diklik
- **Real-time monitoring** - Database queries, execution time, memory usage
- **Detailed metrics** - PHP version, WordPress version, peak memory
- **Mobile responsive** - Optimal display di semua device sizes

### 📝 File Editor
- **Safe .htaccess editing** - Built-in syntax validation
- **Auto-backup system** - Max 3 backups dengan timestamp
- **One-click restore** - Rollback ke backup sebelumnya
- **Common snippets** - WordPress rewrite, caching, compression, security headers
- **Site accessibility test** - Auto-restore jika site broken

### ⚙️ PHP Configuration
- **Preset-based config** - Basic, Medium, High performance presets
- **Multiple methods** - .htaccess, wp-config.php, .user.ini support
- **Auto-detection** - Smart method selection berdasarkan server environment
- **Current config display** - Lihat setting PHP yang aktif
- **Visual comparison** - Compare current vs preset values

## Installation

### Via WordPress Admin
1. Download plugin zip file
2. Upload via Plugins → Add New → Upload Plugin
3. Activate plugin
4. Access via Tools → Morden Toolkit

### Via FTP
1. Extract plugin files
2. Upload `morden-toolkit` folder ke `/wp-content/plugins/`
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
5. Clear logs atau view detailed logs

### Query Monitor
1. Enable **Performance Bar** di Query Monitor tab
2. Performance bar akan muncul di bottom page untuk logged-in users
3. Click info icon untuk detailed metrics
4. Monitor database queries, execution time, memory usage

### File Editor
1. Access **.htaccess Editor** tab
2. Edit file content dengan syntax highlighting
3. File di-backup otomatis sebelum save
4. Restore dari backup jika needed
5. Insert common snippets dengan one-click

### PHP Config
1. Pilih **PHP Config** tab
2. Select preset: Basic, Medium, atau High Performance
3. Preview setting changes
4. Apply configuration
5. System auto-detect method terbaik (.htaccess, wp-config, .user.ini)

## Security Features

- **Capability checking** - Requires `manage_options`
- **Nonce verification** - All AJAX requests protected
- **Content validation** - Block malicious code patterns
- **Auto-backup** - Safe file operations dengan rollback
- **Site monitoring** - Test accessibility setelah .htaccess changes
- **Clean uninstall** - Remove all modifications pada plugin deletion

## System Requirements

- **WordPress:** 5.0 or higher
- **PHP:** 7.4 or higher
- **MySQL:** 5.6+ or MariaDB 10.1+
- **Server:** Apache with mod_rewrite atau Nginx

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
- Debug management dengan wp-config.php integration
- Query monitor dengan performance bar
- Safe .htaccess editor dengan auto-backup
- PHP configuration presets dengan multi-method support
- Comprehensive admin interface dengan tabbed navigation
- Security features dan permission checking
- Complete test suite
- Translation support

## License

GPL v2 or later - see [LICENSE](LICENSE) file

## Support

- **Documentation:** [docs/](docs/)
- **Issues:** [GitHub Issues](https://github.com/morden-pro/morden-toolkit/issues)
- **Support Forum:** [WordPress.org Support](https://wordpress.org/support/plugin/morden-toolkit/)

## Credits

Developed by [Morden Pro](https://morden.pro) dengan focus pada simplicity, safety, dan developer experience.

---

**Plugin ini dirancang untuk developer yang butuh tools essential WordPress dalam satu package yang lightweight dan aman.**
