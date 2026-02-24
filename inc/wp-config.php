<?php /* BEGIN KINSTA DEVELOPMENT ENVIRONMENT - DO NOT MODIFY THIS CODE BLOCK */ ?>
<?php if ( !defined('KINSTA_DEV_ENV') ) { define('KINSTA_DEV_ENV', true); /* Kinsta development - don't remove this line */ } ?>
<?php if ( !defined('JETPACK_STAGING_MODE') ) { define('JETPACK_STAGING_MODE', true); /* Kinsta development - don't remove this line */ } ?>
<?php /* END KINSTA DEVELOPMENT ENVIRONMENT - DO NOT MODIFY THIS CODE BLOCK */ ?>
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
define( 'DB_NAME', 'caeshub' );

/** Database username */
define( 'DB_USER', 'caeshub' );

/** Database password */
define( 'DB_PASSWORD', 'YNwauIA0vevWeSu' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

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
define( 'AUTH_KEY',          '6nX#&`H^%I{/|bhJP$T; *SXL?O$}l,H|pyXS=Hd(yR>#<*Dg|q5df|VABolB{$S' );
define( 'SECURE_AUTH_KEY',   '|2+y^bN&(Kf3h?s#gk%eOApGCCAp{=[3aePAHOhe{ND9o/zeS@v%5A.o#88_+!2r' );
define( 'LOGGED_IN_KEY',     '=f.4hN~|c0v0wB82h+4-}WF kjwyn kmpHbEX@JhLeo];TDN$_PrlneoI5STI(x?' );
define( 'NONCE_KEY',         'XQ|Xodu>1`Lv7~TdMz80T]ZL1{/-@vmm0I$Z(h^FA[;C|O%Pg{muu zrC ^=RAPz' );
define( 'AUTH_SALT',         '}l<XvM*Lz+n6PkrM)As&s5J;ZbRkF+&~>wS_k%<}7e4ctV+-|)i|;KLS3fDfs`{k' );
define( 'SECURE_AUTH_SALT',  'n;fSjN=X Oyw:FptoA$bZF5>(XcYez$X]X6K78J&i#:?e7r-he:oi5,SDdP.E9Lp' );
define( 'LOGGED_IN_SALT',    ')`x>#t z>3#yv-SS#j!n*xe-,;/*M<sGSe1ybT/aW$^Mib`m0$r=*C~Ix:B_~B^q' );
define( 'NONCE_SALT',        'DQ,$Ry^FRom4j!S%9&H[BY.(,.7s1i?8S;1AQA0o|<[Wit]j$Zi8deT1aZOsVd3,' );
define( 'WP_CACHE_KEY_SALT', 'sUW]qv<tUZMoo[Sfy^1r@BA+<M2`eZ`78Y$`v=hp,~HncFlS):pZ)S:*;0N})_zv' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */

// Symplectic Elements API Credentials
define( 'SYMPLECTIC_API_USERNAME', 'caes' );
define( 'SYMPLECTIC_API_PASSWORD', '8Ky4w5GLt!vtpb%z' );

// API key for call to ColdFusion API to fetch 810 numbers
define('CF_810_API_ENDPOINT_KEY', 'X6dSHykr3$ji9vNj');

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
	define( 'WP_DEBUG', false );
	define( 'WP_DEBUG_LOG', true );
}



define( 'WP_ALLOW_MULTISITE', true );
define( 'MULTISITE', true );
define( 'SUBDOMAIN_INSTALL', false );
$base = '/';
define( 'DOMAIN_CURRENT_SITE', 'stg-caeshub-staging.kinsta.cloud' );
define( 'PATH_CURRENT_SITE', '/' );
define( 'SITE_ID_CURRENT_SITE', 1 );
define( 'BLOG_ID_CURRENT_SITE', 1 );
define('SMTP_HOST','post.uga.edu');
define('SMTP_USER','s-agmail');
define('SMTP_PASS','Wuwu0@star');
define('SMTP_FROM','caesweb@uga.edu');
define('SMTP_NAME','CAESWEB');
define('SMTP_PORT',587);
define('SMTP_AUTH', true );
define('SMTP_SECURE','tls');
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
