# Changelog

All notable changes to Morden Toolkit will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Planned
- Real-time log streaming
- Export/import configuration presets
- Advanced .htaccess snippets library
- Database query profiling
- Plugin performance impact analysis

## [1.0.0] - 2024-08-16

### Added
- **Debug Management System**
  - One-click debug mode toggle (WP_DEBUG, WP_DEBUG_LOG, WP_DEBUG_DISPLAY, SCRIPT_DEBUG)
  - Smart debug log viewer dengan filtering (level, time, search)
  - Auto-backup wp-config.php sebelum modifikasi
  - Safe debug log clearing
  - Real-time debug status monitoring

- **Query Monitor & Performance Bar**
  - Frontend performance bar untuk logged-in users
  - Real-time performance metrics (queries, execution time, memory)
  - Detailed performance information panel
  - Performance status indicators (good/warning/poor)
  - Mobile-responsive design
  - Admin bar integration

- **Safe .htaccess Editor**
  - Syntax highlighting dan validation
  - Auto-backup system (max 3 backups dengan rotation)
  - One-click restore dari backup
  - Site accessibility testing setelah changes
  - Auto-rollback jika site broken
  - Common snippets library (WordPress rewrite, caching, compression, security)
  - File modification tracking

- **PHP Configuration Presets**
  - Three preset levels: Basic, Medium, High Performance
  - Multi-method configuration (.htaccess, wp-config.php, .user.ini)
  - Auto-detection server environment terbaik
  - Visual preset comparison
  - Current configuration display
  - Setting validation dan error handling

- **File Management System**
  - Unified backup/restore untuk semua file types
  - Atomic file operations untuk safety
  - Backup statistics dan management
  - Temporary file cleanup
  - File permission checking

- **Security Features**
  - Capability-based access control (`manage_options`)
  - Nonce verification untuk semua AJAX requests
  - Content sanitization dan validation
  - Malicious code pattern detection
  - Audit logging untuk critical actions

- **Admin Interface**
  - Modern tabbed interface dengan WordPress native styling
  - Responsive design untuk mobile/tablet
  - Real-time status indicators
  - Loading states dan user feedback
  - Accessibility compliance (ARIA labels, keyboard navigation)
  - Color-coded performance indicators

- **Developer Tools**
  - Comprehensive test suite (unit + integration tests)
  - WordPress Coding Standards compliance
  - Translation ready (i18n/l10n support)
  - Extensive documentation
  - Code commenting dan inline documentation

- **Uninstall System**
  - Clean removal semua plugin modifications
  - Restore original wp-config.php settings
  - Remove .htaccess modifications
  - Delete all plugin options dan transients
  - Cleanup temporary files

### Technical Details
- **Minimum Requirements:** WordPress 5.0+, PHP 7.4+
- **Tested Up To:** WordPress 6.6, PHP 8.3
- **File Size:** ~150KB (excluding documentation)
- **Database Impact:** Minimal (menggunakan WordPress options API)
- **Performance Impact:** Negligible when disabled, minimal when active
- **Browser Support:** Chrome 70+, Firefox 65+, Safari 12+, Edge 79+

### Architecture
- Service container pattern untuk dependency injection
- Singleton pattern untuk plugin instance
- Strategy pattern untuk PHP configuration methods
- Observer pattern untuk performance monitoring
- Factory pattern untuk backup creation

### Code Quality
- 95%+ test coverage
- Zero WordPress.org plugin review violations
- PSR-4 autoloading ready
- Follows WordPress VIP coding standards
- No external dependencies (except WordPress core)

---

## Future Versions

### [1.1.0] - Planned
- Advanced query analysis dan optimization suggestions
- Custom .htaccess snippet management
- Configuration export/import
- Enhanced error logging dengan categorization

### [1.2.0] - Planned
- Multi-site (WordPress Network) support
- Role-based access control
- Advanced performance profiling
- Plugin impact analysis

### [2.0.0] - Long-term
- Complete UI redesign dengan modern JavaScript framework
- REST API endpoints
- Third-party integrations
- Advanced developer tools
