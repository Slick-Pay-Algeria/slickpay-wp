<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Slickpay Payments Blocks integration
 *
 * @version 1.0.0
 * @since 1.0.0
 */
final class WC_Gateway_Slickpay_Blocks_Support extends AbstractPaymentMethodType
{
	/**
	 * The gateway instance.
	 *
	 * @var WC_Gateway_Slickpay
	 */
	private $gateway;

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = 'wc_gateway_slickpay';

	/**
	 * Initializes the payment method type.
	 */
	public function initialize()
    {
		$this->settings = get_option('woocommerce_wc_gateway_slickpay_settings', []);
		$this->gateway  = new WC_Gateway_Slickpay();
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active()
    {
		return $this->gateway->is_available();
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles()
    {
		$script_url        = WC_Slickpay_Payment_Gateways::plugin_url() . '/assets/js/frontend/blocks.js';
		$script_asset_path = realpath(WC_Slickpay_Payment_Gateways::plugin_path() . '/assets/js/frontend/blocks.asset.php');
		$script_asset      = file_exists($script_asset_path)
			? require($script_asset_path)
			: array(
				'dependencies' => array(),
				'version'      => '1.0.0'
			);

		wp_register_script(
			'wc-gateway-slickpay-blocks',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		if (function_exists('wp_set_script_translations')) {
			wp_set_script_translations('wc-gateway-slickpay-blocks', 'woocommerce-gateway-slickpay', realpath(WC_Slickpay_Payment_Gateways::plugin_path() . "/languages/"));
		}

		return ['wc-gateway-slickpay-blocks'];
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data()
    {
		return [
			'title'       => $this->get_setting('title'),
			'description' => $this->get_setting('description'),
			'supports'    => array_filter($this->gateway->supports, [$this->gateway, 'supports'])
		];
	}
}