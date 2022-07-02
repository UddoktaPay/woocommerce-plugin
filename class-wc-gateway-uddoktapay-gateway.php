<?php

// If this file is called firectly, abort!!!
defined('ABSPATH') or die('Direct access is not allowed.');

/**
 * WC_Gateway_UddoktaPay_Gateway_Class.
 */
class WC_Gateway_UddoktaPay extends WC_Payment_Gateway
{

	/** @var bool Whether or not logging is enabled */
	public static $log_enabled = false;

	/** @var WC_Logger Logger instance */
	public static $log = false;

	// webhook url
	var $webhook_url;

	/**
	 * Constructor for the gateway.
	 */
	public function __construct()
	{
		$this->id                 = 'uddoktapay';
		$this->has_fields         = false;
		$this->order_button_text  = __('Proceed to Checkout', 'uddoktapay-gateway');
		$this->method_title       = __('UddoktaPay Gateway', 'uddoktapay-gateway');
		$this->webhook_url        = add_query_arg('wc-api', 'WC_Gateway_UddoktaPay', home_url('/'));
		$this->method_description = '<p>' .
			// translators: Introduction text at top of UddoktaPay Gateway settings page.
			__('A payment gateway that sends your customers to UddoktaPay Gateway to pay with bKash, Rocket, Nagad, Upay.', 'uddoktapay-gateway')
			. '</p><p>' .
			sprintf(
				// translators: Introduction text at top of UddoktaPay Gateway settings page. Includes external URL.
				__('If you do not currently have a UddoktaPay Gateway account, you can contact with me: %s', 'uddoktapay-gateway'),
				'<a target="_blank" href="https://facebook.com/rtraselbd">Facebook</a>'
			);

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->title       = $this->get_option('title');
		$this->description = $this->get_option('description');
		$this->debug       = 'yes' === $this->get_option('debug', 'no');

		self::$log_enabled = $this->debug;

		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		add_action('woocommerce_api_wc_gateway_' . $this->id, array($this, 'handle_webhook'));
		add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'admin_order_data'));
	}

	/**
	 * Logging method.
	 *
	 * @param string $message Log message.
	 * @param string $level   Optional. Default 'info'.
	 *     emergency|alert|critical|error|warning|notice|info|debug
	 */
	public static function log($message, $level = 'info')
	{
		if (self::$log_enabled) {
			if (empty(self::$log)) {
				self::$log = wc_get_logger();
			}
			self::$log->log($level, $message, array('source' => 'uddoktapay'));
		}
	}

	/**
	 * Get gateway icon.
	 * @return string
	 */
	public function get_icon()
	{
		if ($this->get_option('show_icons') === 'no') {
			return '';
		}

		$image_path = plugin_dir_path(__FILE__) . 'assets/images';
		$icon_html  = '';
		$methods    = ['bkash', 'rocket', 'nagad', 'upay'];

		// Load icon for each available payment method.
		foreach ($methods as $m) {
			$path = realpath($image_path . '/' . $m . '.png');
			if ($path && dirname($path) === $image_path && is_file($path)) {
				$url        = WC_HTTPS::force_https_url(plugins_url('/assets/images/' . $m . '.png', __FILE__));
				$icon_html .= '<img width="30px" height="30px" src="' . esc_attr($url) . '" alt="' . esc_attr__($m, 'uddoktapay') . '" />';
			}
		}

		return apply_filters('woocommerce_gateway_icon', $icon_html, $this->id);
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields()
	{
		$this->form_fields = array(
			'enabled'        => array(
				'title'   => __('Enable/Disable', 'uddoktapay-gateway'),
				'type'    => 'checkbox',
				'label'   => __('Enable UddoktaPay Gateway Payment', 'uddoktapay-gateway'),
				'default' => 'yes',
			),
			'title'          => array(
				'title'       => __('Title', 'uddoktapay-gateway'),
				'type'        => 'text',
				'description' => __('This controls the title which the user sees during checkout.', 'uddoktapay-gateway'),
				'default'     => __('bKash, Rocket, Nagad, Upay', 'uddoktapay-gateway'),
				'desc_tip'    => true,
			),
			'description'    => array(
				'title'       => __('Description', 'uddoktapay-gateway'),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => __('This controls the description which the user sees during checkout.', 'uddoktapay-gateway'),
				'default'     => __('Pay with uddoktapay gateway.', 'uddoktapay-gateway'),
			),
			'api_key'        => array(
				'title'       => __('API Key', 'uddoktapay-gateway'),
				'type'        => 'text',
				'default'     => '',
				'description' => sprintf(
					// translators: Description field for API on settings page. Includes external link.
					__(
						'You can manage your API keys within the UddoktaPay Gateway Panel API Settings Page.',
						'uddoktapay-gateway'
					)
				),
			),
			'api_url'        => array(
				'title'       => __('API URL', 'uddoktapay-gateway'),
				'type'        => 'text',
				'default'     => '',
				'description' => sprintf(
					// translators: Description field for API on settings page. Includes external link.
					__(
						'You will find your API URL in the UddoktaPay Gateway Panel API Settings Page.',
						'uddoktapay-gateway'
					)
				),
			),
			'fee'    => array(
				'title'       => __('Fee (%)', 'uddoktapay-gateway'),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => __('This fee will be added to the total amount of the cart', 'uddoktapay-gateway'),
				'default'     => 0,
			),
			'show_icons'     => array(
				'title'       => __('Show icons', 'uddoktapay-gateway'),
				'type'        => 'checkbox',
				'label'       => __('Display icons on checkout page.', 'uddoktapay-gateway'),
				'default'     => 'yes',
			),
			'digital_product'     => array(
				'title'       => __('Digital Product', 'uddoktapay-gateway'),
				'type'        => 'checkbox',
				'label'       => __('If you are providing digital product then you can use this option. It will mark order as complete as soon as user paid.', 'uddoktapay-gateway'),
				'default'     => 'no',
			),
			'debug'          => array(
				'title'       => __('Debug log', 'uddoktapay-gateway'),
				'type'        => 'checkbox',
				'label'       => __('Enable logging', 'uddoktapay-gateway'),
				'default'     => 'no',
				// translators: Description for 'Debug log' section of settings page.
				'description' => sprintf(__('Log UddoktaPay Gateway API events inside %s', 'uddoktapay-gateway'), '<code>' . WC_Log_Handler_File::get_log_file_path('uddoktapay') . '</code>'),
			),
		);
	}

	/**
	 * Process the payment and return the result.
	 * @param  int $order_id
	 * @return array
	 */

	public function process_payment($order_id)
	{
		global $woocommerce;
		$order = new WC_Order($order_id);

		$this->init_api();

		// Create a new charge.
		$metadata = array(
			'order_id'  		=> $order->get_id(),
			'order_key' 		=> $order->get_order_key(),
			'source' 			=> 'woocommerce'
		);
		$full_name = $order->get_billing_first_name() . " " . $order->get_billing_last_name();
		$email = $order->get_billing_email();

		$result = UddoktaPay_Gateway_API_Handler::create_payment(
			$order->get_total(),
			get_woocommerce_currency(),
			$full_name,
			$email,
			$metadata,
			$this->get_return_url($order),
			$this->get_cancel_url($order),
			$this->webhook_url
		);

		if (isset($result) && empty($result->payment_url)) {
			return array('result' => 'fail');
		}

		if ($order->get_status() != 'completed') {
			// Mark as pending
			$order->update_status('pending', __('Customer is being redirected to UddoktaPay Gateway', 'uddoktapay-gateway'));
		}

		// Reduce stock levels
		$order->reduce_order_stock();

		// Remove cart
		$woocommerce->cart->empty_cart();

		return array(
			'result'   => 'success',
			'redirect' => $result->payment_url,
		);
	}

	/**
	 * Get the cancel url.
	 *
	 * @param WC_Order $order Order object.
	 * @return string
	 */
	public function get_cancel_url($order)
	{
		$return_url = $order->get_cancel_order_url();

		if (is_ssl() || get_option('woocommerce_force_ssl_checkout') == 'yes') {
			$return_url = str_replace('http:', 'https:', $return_url);
		}

		return apply_filters('woocommerce_get_cancel_url', $return_url, $order);
	}


	/**
	 * Handle requests sent to webhook.
	 */
	public function handle_webhook()
	{
		$payload =  file_get_contents('php://input');
		if (!empty($payload) && $this->validate_webhook($payload)) {
			$data       = json_decode($payload);

			self::log('Webhook received event: ' . print_r($data, true));

			if (!isset($data->metadata->order_id)) {
				// Probably a charge not created by us.
				exit;
			}

			$order_id = $data->metadata->order_id;

			$this->_update_order_status(wc_get_order($order_id), $data);
		}
		echo 'OK';
		exit();
	}

	/**
	 * Check UddoktaPay Gateway webhook request is valid.
	 * @param  string $data
	 */
	public function validate_webhook($data)
	{
		self::log('Checking Webhook response is valid');

		$key = 'HTTP_' . strtoupper(str_replace('-', '_', 'RT-UDDOKTAPAY-API-KEY'));
		if (!isset($_SERVER[$key])) {
			return false;
		}

		$api = sanitize_text_field($_SERVER[$key]);

		$api_key = sanitize_text_field($this->get_option('api_key'));

		if ($api_key === $api) {
			return true;
		}

		return false;
	}

	/**
	 * Init the API class and set the API key etc.
	 */
	protected function init_api()
	{
		include_once dirname(__FILE__) . '/includes/class-uddoktapay-api-handler.php';

		UddoktaPay_Gateway_API_Handler::$log     = get_class($this) . '::log';
		UddoktaPay_Gateway_API_Handler::$api_url = sanitize_text_field($this->get_option('api_url'));
		UddoktaPay_Gateway_API_Handler::$api_key = sanitize_text_field($this->get_option('api_key'));
	}

	/**
	 * Update the status of an order from a given timeline.
	 * @param  WC_Order $order
	 * @param  array    $timeline
	 */
	public function _update_order_status($order, $data)
	{
		$order->update_meta_data('uddoktapay_payment_data', $data);

		if ($order->get_status() != 'completed') {
			if ($data->status === 'COMPLETED') {
				if ($this->get_option('digital_product') === 'yes') {
					$order->update_status('completed', __('UddoktaPay Gateway payment was successfully completed.', 'uddoktapay-gateway'));
					$order->payment_complete();
				} else {
					$order->update_status('processing', __('UddoktaPay Gateway payment was successfully processed.', 'uddoktapay-gateway'));
					$order->payment_complete();
				}
				return true;
			} else {
				$order->update_status('on-hold', __('UddoktaPay Gateway payment was successfully on-hold. Transaction id not found. Please check it manually.', 'uddoktapay-gateway'));
				return true;
			}
		}
	}


	/**
	 * Display bKash data in admin page.
	 *
	 * @param Object $order Order.
	 */
	public function admin_order_data($order)
	{
		if ('uddoktapay' !== $order->get_payment_method()) {
			return;
		}

		// payment data
		$data = (get_post_meta(sanitize_text_field($_GET['post']), 'uddoktapay_payment_data', true)) ? get_post_meta(sanitize_text_field($_GET['post']), 'uddoktapay_payment_data', true) : '';

		$image_path = plugin_dir_path(__FILE__) . 'assets/images';
		$logo    = sanitize_text_field(strtolower($data->payment_method));

		// Load icon for each available payment method.
		$path = realpath($image_path . '/' . $logo . '.png');
		if ($path && dirname($path) === $image_path && is_file($path)) {
			$img_url        = WC_HTTPS::force_https_url(plugins_url('/assets/images/' . $logo . '.png', __FILE__));
		}
?>
		<div class="form-field form-field-wide bdpg-admin-data">
			<img src="<?php echo esc_url($img_url); ?> " alt="<?php echo esc_attr($data->payment_method); ?>">
			<table class="wp-list-table widefat striped posts">
				<tbody>
					<tr>
						<th>
							<strong>
								<?php echo __('Payment Method', 'uddoktapay-gateway'); ?>
							</strong>
						</th>
						<td>
							<?php echo esc_attr(ucfirst($data->payment_method)); ?>
						</td>
					</tr>
					<tr>
						<th>
							<strong>
								<?php echo __('Sender Number', 'uddoktapay-gateway'); ?>
							</strong>
						</th>
						<td>
							<?php echo esc_attr($data->sender_number); ?>
						</td>
					</tr>
					<tr>
						<th>
							<strong>
								<?php echo __('Transaction ID', 'uddoktapay-gateway'); ?>
							</strong>
						</th>
						<td>
							<?php echo esc_attr($data->transaction_id); ?>
						</td>
					</tr>
					<tr>
						<th>
							<strong>
								<?php echo __('Amount', 'uddoktapay-gateway'); ?>
							</strong>
						</th>
						<td>
							<?php echo esc_attr($data->amount); ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
<?php
	}
}
