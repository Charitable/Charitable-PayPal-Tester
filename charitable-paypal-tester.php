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
	if ( class_exists( 'Charitable-paypal-tester' ) ) {
		require_once( 'class-charitable-stat-query.php' );
		require_once( 'class-charitable-stat-shortcode.php' );
	}
}

add_action( 'plugins_loaded', 'charitable_paypal_tester_load', 1 );


/**
 * Receives the IPN from PayPal after the sandbox test and attempts to verify the result.
 *
 * @since  1.0.0
 *
 * @return void
 */
function charitable_paypal_tester_process_ipn() {
	$gateway = new Charitable_Gateway_Paypal();
	$data    = $gateway->get_encoded_ipn_data();

	/* If any of these checks fail, we conclude that this is not a proper IPN from PayPal. */
	if ( empty( $data ) || ! is_array( $data ) ) {
		die("empty data");
	}

	/* Compare the token with the one we generated. */
	$token = get_option( 'charitable_paypal_sandbox_test_token' );

	if ( ! array_key_exists( 'custom', $data ) || $token !== $data['custom'] ) {
		die("missing or mismatched custom data");
	}

	$remote_post_vars = array(
		'method'           => 'POST',
		'timeout'          => 45,
		'redirection'      => 5,
		'httpversion'      => '1.1',
		'blocking'         => true,
		'headers'          => array(
			'host'         => 'www.paypal.com',
			'connection'   => 'close',
			'content-type' => 'application/x-www-form-urlencoded',
			'post'         => '/cgi-bin/webscr HTTP/1.1',
		),
		'sslverify'        => false,
		'body'             => $data,
	);

	/* Call the PayPal API to verify the IPN. */
	$api_response = wp_remote_post( 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr', $remote_post_vars );
	$succeeded    = ! is_wp_error( $api_response );
	$message      = '';

	if ( $succeeded ) {

		$result  = 'succeeded';
		$subject = __( 'Your PayPal integration is working', 'charitable-paypal-tester' );
		$message = __( '<p>Good news! We successfuly received the Instant Payment Notification from PayPal and were able to verify it with them.</p>', 'charitable-paypal-tester' );
		$message .= __( '<p>This means that your website is all set to continue receiving donations through PayPal. You should not experience any issues when PayPal upgrades its SSL certificates.</p>', 'charitable-paypal-tester' );
		$message .= __( '<p>Cheers<br />Eric & Wes', 'charitable-paypal-tester' );

	} else {

		$result  = 'failed';
		$subject = __( 'Your PayPal test failed', 'charitable-paypal-tester' );
		$message .= __( '<p>We received the Instant Payment Notification from PayPal but were not able to verify its authenticity.', 'charitable-paypal-tester' );
		$message .= __( '<p>Our communicaton with PayPal failed with the following errors:</p>', 'charitable-paypal-tester' );
		$message .= '<ul>';

		foreach ( $api_response->get_error_messages() as $error ) {
			$message .= sprintf( '<li>%s</li>', $error );
		}

		$message .= '</ul>';
		$message .= __( '<p>Unfortunately, this means that you are likely to face problems with your PayPal donations from October 2016 onwards. Your donors will still be able to proceed to PayPal and make their donation, but their donations will not be automatically marked as Paid in your WordPress dashboard.</p>', 'charitable-paypal-tester' );
		$message .= __( '<h3>Short-term fix</h3>', 'charitable-paypal-tester' );
		$message .= __( '<p><strong>Disable IPN verification</strong>. This makes your donation verification process less secure, but it will allow your donations to continue getting marked as Paid. To set this up, log into your WordPress dashboard and go to <em>Charitable</em> > <em>Settings</em> > <em>Payment Gateways</em>, select your PayPal settings and enable the "Disable IPN Verification" setting.', 'charitable-paypal-tester' );
		$message .= __( '<h3>Long-term solution</h3>', 'charitable-paypal-tester' );
		$message .= __( '<p><strong>Get in touch with your web host</strong>. Please refer them to <a href="https://www.paypal-knowledge.com/infocenter/index?page=content&widgetview=true&id=FAQ1766&viewlocale=en_US">the upgrade information provided by PayPal</a>. You should also provide them with the error message you received from PayPal above.</p>', 'charitable-paypal-tester' );
		$message .= __( '<p>If your web host is unable to upgrade the software on your server, we strongly recommend switching to a hosting platform that provides a more modern, and secure service.</p>', 'charitable-paypal-tester' );
		$message .= __( '<p>Cheers<br />Eric & Wes', 'charitable-paypal-tester' );

	}

	/* Store the result. */
	update_option( 'charitable_paypal_sandbox_test', $result );

	/* Clear the token. */
	delete_option( 'charitable_paypal_sandbox_test_token' );

	/* Set a transient to display the success/failure of the test. */
	set_transient( 'charitable_paypal-sandbox-test_notice', 1 );

	/* Remove the transient about the PayPal upgrade. */
	delete_transient( 'charitable_release-143-paypal_notice' );

	/* Send an email to the site admin. */
	ob_start();

	charitable_template( 'emails/header.php', array( 'email' => null, 'headline' => $subject ) );

	echo $message;

	charitable_template( 'emails/footer.php' );

	$message = ob_get_clean();

	$headers  = "From: Charitable <support@wpcharitable.com>\r\n";
	$headers .= "Reply-To: support@wpcharitable.com\r\n";
	$headers .= "Content-Type: text/html; charset=utf-8\r\n";

	/* Send an email to the site administrator letting them know. */
	$sent = wp_mail(
		get_option( 'admin_email' ),
		$subject,
		$message,
		$headers
	);

}

add_action( 'charitable_process_ipn_paypal_sandbox_test', 'charitable_paypal_tester_process_ipn' );

/**
 * Display the PayPal sandbox testing tool at the end of the PayPal gateway settings page.
 *
 * @since  1.0.0
 *
 * @param  string $group The settings group.
 * @return void
 */
function charitable_paypal_tester_render_tool( $group ) {
	if ( 'gateways_paypal' == $group ) {
		require_once( 'admin-tool.php' );
	}
}

add_action( 'charitable_after_admin_settings', 'charitable_paypal_tester_render_tool' );

/**
 * Redirect the user to PayPal after they initiate the sandbox test.
 *
 * @since  1.0.0
 *
 * @return void
 */
function charitable_paypal_tester_redirect_to_paypal() {
	$protocol    = is_ssl() ? 'https://' : 'http://';
	$paypal_url  = $protocol . 'www.sandbox.paypal.com/cgi-bin/webscr/?';
	$paypal_args = $_POST;

	unset( $paypal_args['submit'] );

	/* Add a unique token so we can avoid spoofed requests. */
	$token = strtolower( md5( uniqid() ) );

	update_option( 'charitable_paypal_sandbox_test_token', $token );

	$paypal_args['custom'] = $token;

	$paypal_url .= http_build_query( $paypal_args );
	$paypal_url = str_replace( '&amp;', '&', $paypal_url );

	wp_redirect( $paypal_url );

	exit();
}

add_action( 'charitable_do_paypal_sandbox_test', 'charitable_paypal_tester_redirect_to_paypal' );

/**
 * Redirect the user to PayPal after they initiate the sandbox test.
 *
 * @since  1.4.3
 *
 * @return void
 */
function charitable_paypal_tester_redirect_after_return() {
	$return_url = add_query_arg( array(
		'page'         => 'charitable-settings',
		'tab'          => 'gateways',
		'group'        => 'gateways_paypal',
		'sandbox_test' => true,
	), admin_url( 'admin.php' ) );

	wp_safe_redirect( $return_url );

	exit();
}

add_action( 'charitable_paypal_sandbox_test_return', 'charitable_paypal_tester_redirect_after_return' );
