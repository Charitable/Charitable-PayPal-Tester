<?php
/**
 * Plugin Name:       Charitable - PayPal Tester
 * Plugin URI:        https://github.com/Charitable/Charitable-PayPal-Tester
 * Description:       A utility plugin to test whether PayPal IPNs are working.
 * Version:           1.0.0
 * Author:            WP Charitable
 * Author URI:        https://www.wpcharitable.com
 * Requires at least: 4.2
 * Tested up to:      4.9
 *
 * Text Domain:       charitable-paypal-tester
 * Domain Path:       /languages/
 *
 * @package Charitable_PayPal_Tester
 * @author  WP Charitable
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Load plugin class, but only if Charitable is found and activated.
 *
 * @return false|Charitable_Stat_Shortcode Whether the class was loaded.
 */
function charitable_paypal_tester_load() { 
    if ( class_exists( 'Charitable' ) ) {
        require_once( 'class-charitable-stat-query.php' );
        require_once( 'class-charitable-stat-shortcode.php' );
    }
}

add_action( 'plugins_loaded', 'charitable_paypal_tester_load', 1 );
