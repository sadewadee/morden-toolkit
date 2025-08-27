<?php


/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'wp_7yl4t' );

/** Database username */
define( 'DB_USER', 'wp_go9ck' );

/** Database password */
define( 'DB_PASSWORD', '3B%%6&$0FP4#g$Mb' );

/** Database hostname */
define( 'DB_HOST', 'localhost:3306' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY', '9eIHR&IrfXSG2**1RzP88M]R@I4b9cq&-s0Pv-e5@I6&8iuL294&88*3@jCxy*2m');
define('SECURE_AUTH_KEY', 'XmbjQd9Ljc~;4[@!%eB92N4;Eiw7wg&3Ydb355X0)-~XL)+5c(Vq1Cz!cG#7QpFG');
define('LOGGED_IN_KEY', 'X1a8T_a+8#]2wD!!dFcjJ9C6;Mjy6zMUDN)aZE[7UXwG6IlpJY1&])5-584BB&+]');
define('NONCE_KEY', 'c~6Y:7|]hc0GjZ)]1PS+798;0U%&@V%jZiH3H!Xq]&75#D217:hn5Hv8GAkc9Rq0');
define('AUTH_SALT', '!UA;8p+)]|_UZyG5W8ysF3S3%7&5ZoJ9P32i-8i/32;p[MVKz):]WP4Mi_I#30Q2');
define('SECURE_AUTH_SALT', 'nH[&j[IWi6-9Y)*S8@4Nn!0SC070AeE*(4:gs3Dm;:-iE6GnHyTd0[3x720Rz|v4');
define('LOGGED_IN_SALT', '105a&Qf%GL]09*40]S)mvo4&Qvw69j&B(hq[U!Gn10[hK:/G#5__iARS@t+Gov9H');
define('NONCE_SALT', 'nKYMa2e8X_/O6D_%Z;iQC2;3(FWdwGC_q[s4]i3KD[1I0TA2L6qvw!_P6wA|kbJn');


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'Q7cGFX_';


/* Add any custom values between this line and the "stop editing" line. */

define('WP_ALLOW_MULTISITE', true);
/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', true );
}

define( 'DISABLE_WP_CRON', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_DEBUG_LOG', '/home/sysuser_8/sadewa.my.id/wp-content/morden-toolkit/wp-errors-craDGXSI.log' );
define('DISALLOW_FILE_EDIT', true); // Added by Morden Security

define( 'SCRIPT_DEBUG', true );
@ini_set('display_errors', '0');
define( 'SAVEQUERIES', true );

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
define( 'WP_MEMORY_LIMIT', '2048M' );
define( 'WP_MAX_MEMORY_LIMIT', '3G' );
define( 'WP_MAX_EXECUTION_TIME', '600' );
require_once ABSPATH . 'wp-settings.php';

/* BEGIN Morden Toolkit PHP Configuration */
 ini_set('upload_max_filesize', '64M');
 ini_set('post_max_size', '128M');
 ini_set('max_input_vars', '10000');
 ini_set('max_input_time', '600');
 /* END Morden Toolkit PHP Configuration */
