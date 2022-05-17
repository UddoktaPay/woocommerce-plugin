<?php
/*
Plugin Name:  UddoktaPay Gateway
Plugin URI:   https://uddoktapay.com
Description:  A payment gateway that allows your customers to pay with Bkash, Rocket, Nagad, Upay via UddoktaPay Gateway
Version:      1.1.3
Author:       Md Rasel Islam
Author URI:   https://rtrasel.com
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


	// Require Woocommerce Class
	if (file_exists(dirname(__FILE__) . '/class-wc-gateway-uddoktapay-gateway.php')) {
		require_once dirname(__FILE__) . '/class-wc-gateway-uddoktapay-gateway.php';
	}

	// Load WooCommerce Class
	add_filter('woocommerce_payment_gateways', 'uddoktapay_wc_class');
	// Add fee
	add_action('woocommerce_cart_calculate_fees', 'uddoktapay_add_checkout_fee_for_gateway');
	// Refresh form
	add_action('woocommerce_after_checkout_form', 'uddoktapay_refresh_checkout_on_payment_methods_change');

	if (is_admin()) {

		// Plugin Action Links.
		add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'uddoktapay_plugin_action_links');
	}


	// WooCommerce
	function uddoktapay_wc_class($methods)
	{
		if (!in_array('WC_Gateway_UddoktaPay', $methods)) {
			$methods[] = 'WC_Gateway_UddoktaPay';
		}
		return $methods;
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


	function uddoktapay_add_checkout_fee_for_gateway()
	{
		$chosen_gateway = WC()->session->get('chosen_payment_method');
		if ($chosen_gateway == 'uddoktapay') {
			$uddoktapay = new WC_Gateway_UddoktaPay();
			$fee = $uddoktapay->get_option('fee');
			$amount = round(WC()->cart->cart_contents_total * ($fee / 100));
			WC()->cart->add_fee('Fee', $amount);
		}
	}

	function uddoktapay_refresh_checkout_on_payment_methods_change()
	{
		wc_enqueue_js("
           $( 'form.checkout' ).on( 'change', 'input[name^=\'payment_method\']', function() {
               $('body').trigger('update_checkout');
            });
       ");
	}
}
