<?php
/**
 * PHPUnit bootstrap for Matrix Donations.
 */

$plugin_root = dirname( __DIR__ );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $plugin_root . '/' );
}

require_once $plugin_root . '/includes/class-validation.php';
