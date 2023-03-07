<?php
/*
Plugin Name: Slick-Pay Payment Gateway
Plugin URI: https://slick-pay.com
Description: Slick-Pay.com Payment Gateway Plug-in for WooCommerce.
Author: Slick-Pay
Version: 2.0.0
*/
add_action('plugins_loaded', 'slickpay_init', 0);

function slickpay_init() {

    // if condition use to do nothin while WooCommerce is not installed
    if (!class_exists('WC_Payment_Gateway')) return;

    include_once('slickpay-wp-woocommerce.php');

    // class add it too WooCommerce
    add_filter('woocommerce_payment_gateways', 'slickpay_add_gateway');

    function slickpay_add_gateway($methods) {

        $methods[] = 'slickpay';

        return $methods;
    }
}

// Add custom action links
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'slickpay_action_links');

function slickpay_action_links( $links ) {

    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . __('Settings', 'slickpay') . '</a>',
    );

    return array_merge($plugin_links, $links);
}