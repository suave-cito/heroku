<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'wordpress');

/** MySQL database username */
define('DB_USER', 'root');

/** MySQL database password */
define('DB_PASSWORD', '');

/** MySQL hostname */
define('DB_HOST', 'localhost');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8mb4');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         '9oJO2u@@v$$eiMWR0BuxwV&7*LZm8WGMBY0Lm>:IEA7k2BYylTDAf0iF$/hq/W@I');
define('SECURE_AUTH_KEY',  '938kBj-@DR@`~Tpv1&2g-=$0LB{S;+YfmsqO:-B*Li&]6@4k>soE|t.%[Wi=a#+X');
define('LOGGED_IN_KEY',    'M+aS63Igr^G:PkxJ|=uP4Vy6eq,hA] t=* 0fRgzG;`ca(809lk1ptp1IA.w7,?D');
define('NONCE_KEY',        ';-=VHL2nmT?;#3`PL)7D}QEn_R@VV-jE!!x&x6mB277h*So9)_,h$r(,!^c&$7S>');
define('AUTH_SALT',        '{V,|x^t6N-*QOcR5$jEhnmFMdRI{W&@nL9:K&ghRDijp-;vt<cE{.NY+/n=|v-xi');
define('SECURE_AUTH_SALT', 'k^_T#j<)][(4~H4i|u,0S|pDX7TKfRUem]XNHj.AXave0:!^pT[/U/C=GJ@(!=9!');
define('LOGGED_IN_SALT',   'i]zy] WK%bMjI!t,jkBX1/Jy/]gM|U1.,f_%o6MPXMr-nckZgY/m$DZ$.6wTJ~6p');
define('NONCE_SALT',       '92s1qSLaL&LX&zR!)Pti7QwA)Q/#1r`sVYkJs! ,`pSo%?d9lkN:(nrLjA|t|CKa');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define('WP_DEBUG', false);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
