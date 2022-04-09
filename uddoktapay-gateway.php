<?php
/*
Plugin Name:  UddoktaPay Gateway
Plugin URI:   https://uddoktapay.com
Description:  A payment gateway that allows your customers to pay with Bkash, Rocket, Nagad, Upay via UddoktaPay Gateway
Version:      1.0.8
Author:       Md Rasel Islam
Author URI:   https://facebook.com/rtraselbd
License:      GPL 2 or later
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  uddoktapay-gateway
*/

// If this file is called firectly, abort!!!
defined('ABSPATH') or die('Direct access is not allowed.');


add_action('plugins_loaded', 'uddoktapay_init_gateway');

// Init gateway function
function uddoktapay_init_gateway()
{

	/**
	 * WooCommerce Required Notice.
	 */
	function uddoktapay_woo_required_notice()
	{
		$message = sprintf(
			// translators: %1$s Plugin Name, %2$s wooCommerce.
			esc_html__(' %1$s requires %2$s to be installed and activated. Please activate %2$s to continue.', 'uddoktapay-gateway'),
			'<strong>' . esc_html__('UddoktaPay Gateway', 'uddoktapay-gateway') . '</strong>',
			'<strong>' . esc_html__('WooCommerce', 'uddoktapay-gateway') . '</strong>'
		);
		printf('<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message);
	}

	// Class not exist
	if (!class_exists('WC_Payment_Gateway')) {
		// oops!
		add_action('admin_notices', 'uddoktapay_woo_required_notice');
		return;
	}

	// WooCommerce
	function uddoktapay_wc_class($methods)
	{
		if (!in_array('WC_Gateway_UddoktaPay', $methods)) {
			$methods[] = 'WC_Gateway_UddoktaPay';
		}
		return $methods;
	}

	// Load WooCommerce Class
	add_filter('woocommerce_payment_gateways', 'uddoktapay_wc_class');

	if (is_admin()) {

		// Plugin Action Links.
		add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'uddoktapay_plugin_action_links');
	}

	// Require Woocommerce Class
	if (file_exists(dirname(__FILE__) . '/class-wc-gateway-uddoktapay-gateway.php')) {
		require_once dirname(__FILE__) . '/class-wc-gateway-uddoktapay-gateway.php';
	}


	/**
	 * Plugin Action Links
	 *
	 * @param array $links Links.
	 * @return array
	 */
	function uddoktapay_plugin_action_links($links)
	{

		$links[] = sprintf(
			'<a href="%s">%s</a>',
			admin_url('admin.php?page=wc-settings&tab=checkout&section=uddoktapay'),
			__('Gateway Settings', 'uddoktapay-gateway')
		);
		$links[] = sprintf(
			'<a href="%s">%s</a>',
			'https://uddoktapay.com',
			__('<b style="color: green">Purchase License</b>', 'uddoktapay-gateway')
		);

		return $links;
	}
}
