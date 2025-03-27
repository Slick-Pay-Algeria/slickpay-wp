<?php

/**
 * Plugin Name: Slick-Pay Payment Gateway
 * Plugin URI: https://wordpress.org/plugins/slickpay-payement-gateway/
 * Description: Slick-Pay.com Payment Gateway Plug-in for WooCommerce.
 * Version: 1.0.3
 *
 * Author: Slick-Pay
 * Author URI: https://slick-pay.com
 *
 * Text Domain: slickpay-payement-gateway
 * Domain Path: /languages
 *
 * Requires PHP: 7.3
 *
 * Requires at least: 6.0
 * Tested up to: 6.4.1
 *
 * WC requires at least: 7.5
 * WC tested up to: 8.3.1
 *
 * License: MIT License
 * License URI: https://opensource.org/licenses/MIT
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit();
}

if (!class_exists('WC_Slickpay_Payment_Gateways')) :

    final class WC_Slickpay_Payment_Gateways
    {
		/**
		 * Plugin version.
		 *
		 * @var   string
		 * @since 1.0.1
		 */
		public $version = '1.0.1';

		/**
		 * The single instance of the class.
		 *
		 * @var   WC_Slickpay_Payment_Gateways The single instance of the class
		 * @since 1.0.0
		 */
		protected static $_instance = null;

		/**
		 * Main WC_Slickpay_Payment_Gateways Instance
		 *
		 * Ensures only one instance of WC_Slickpay_Payment_Gateways is loaded or can be loaded.
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 * @static
		 * @return  WC_Slickpay_Payment_Gateways - Main instance
		 */
		public static function instance()
        {
			if (is_null(self::$_instance)) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}

        /**
         * Plugin bootstrapping.
         */
        public function __construct()
        {
            // Check for active plugins.
			if (!$this->is_plugin_active('woocommerce/woocommerce.php')) {
                return;
            }

            // Set up localisation.
            load_plugin_textdomain('slickpay-payement-gateway', false, dirname(plugin_basename(__FILE__)) . '/languages/');

			// Include required files.
			$this->includes();

            // Admin.
            if (is_admin()) {
                $this->admin();
            }
        }

        /**
         * Admin.
         *
         * @version 1.0.0
         * @since   1.0.0
         */
        public function admin()
        {
            // Action links.
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'action_links'));

            // Version update.
            if (get_option('wc_slickpay_payment_gateways_version', '') !== $this->version) {
                add_action('admin_init', array($this, 'version_updated'));
            }

            // Declare compatibility.
            add_action('before_woocommerce_init', array($this, 'declare_woocommerce_compatibilities'));
        }

        /**
         * Plugin includes.
         */
        public static function includes()
        {
            // Make the WC_Gateway_Slickapy class available.
            require_once 'includes/class-wc-gateway-slickpay.php';
        }

        /**
         * Is plugin active.
         *
         * @param   string $plugin Plugin Name.
         * @return  bool
         * @version 1.0.0
         * @since   1.0.0
         */
        public function is_plugin_active( $plugin )
        {
            return (function_exists('is_plugin_active')
                ? is_plugin_active($plugin)
                : (in_array($plugin, apply_filters('active_plugins', (array) get_option('active_plugins', array())), true)
                    || (is_multisite() && array_key_exists($plugin, (array) get_site_option('active_sitewide_plugins', array())))
                )
            );
        }

        public function action_links($links)
        {
            $custom_links   = array();

            $custom_links[] = '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=wc_gateway_slickpay') . '">' . __('Settings', 'woocommerce') . '</a>';

            return array_merge( $custom_links, $links );
        }

        /**
         * HPOS (High-Performance Order Storage) (in)compatibility
         */
        public function declare_woocommerce_compatibilities()
        {
            if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
            }
        }

        /**
		 * Version updated.
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function version_updated()
        {
			update_option('wc_slickpay_payment_gateways_version', $this->version);
		}

		/**
		 * Get the plugin url.
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 * @return  string
		 */
		public static function plugin_url()
        {
			return untrailingslashit(plugin_dir_url(__FILE__));
		}

		/**
		 * Get the plugin path.
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 * @return  string
		 */
		public static function plugin_path()
        {
			return untrailingslashit(plugin_dir_path(__FILE__));
		}
    }

endif;

if (!function_exists('wc_slickpay_payment_gateways')) {

	/**
	 * Returns the main instance of WC_Slickpay_Payment_Gateways to prevent the need to use globals.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 * @return  WC_Slickpay_Payment_Gateways
	 */
	function wc_slickpay_payment_gateways()
    {
		return WC_Slickpay_Payment_Gateways::instance();
	}
}

wc_slickpay_payment_gateways();
