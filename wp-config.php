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
define('DB_NAME', 'myportfolio');

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
define('AUTH_KEY',         '0d.aP$7M~!a,:^fzYCc?gX1zIBwIW)|z-!P$WH0<?e^.mzQhoF]5iv`A3V$#rE?]');
define('SECURE_AUTH_KEY',  'c{=kXrS6hcUbE^W{L8xk+urcZL~c;J%-KKca xoH_Pn l)g|5jd25@]*yz?8?kNt');
define('LOGGED_IN_KEY',    '7&#`p9RhrkC0u$MG{e2OhZH(L7L4,ED;{YfLB;Q?$ZZs`x2V-`:f&lT89.n?*ZZM');
define('NONCE_KEY',        'WH~.X>vEj)DI7UyO7&V$5{xipi6x!(xc|8SI!yza#o9oay@mq].`XC>5S/)IneYe');
define('AUTH_SALT',        'Ab4NTH9vC8TXr/:%g/zhwyF ikg&l!i5.?V,M(VBVKX-9/)Vb>ZqWCc<]lPvU1(k');
define('SECURE_AUTH_SALT', 'htWcEO9YP$NXa@.W(^*(=KeWCBb?xNkC*U}Jxx-#wZX`c12(5?dQK4:0L{(n.Hvv');
define('LOGGED_IN_SALT',   '5B(m#qnN6mUTgPW}e~V%%szx,so-2CBstuzA8]ge*T4lQ*i> -{as E}Vh[GIdWN');
define('NONCE_SALT',       'De<,U^;H0Bc~cm4]|]a%T6U&mOXB@V!9dFksau*g)9^L.:RO6&CyFS]xRh8W2;y8');

define('AUTOMATIC_UPDATER_DISABLED', true); /* Perfectdashboard modification */
/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'myPort_';

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

