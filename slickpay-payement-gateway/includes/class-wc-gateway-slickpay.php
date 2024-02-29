<?php

/**
 * Slick-Pay Payment Gateways for WooCommerce - Gateways Class
 *
 * @version 1.0.1
 * @since   1.0.0
 * @author  Slick-Pay <wordpress@slick-pay.com>
 * @package spg
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit();
}

add_action('plugins_loaded', 'init_wc_gateway_slickpay_class');

if (!function_exists('init_wc_gateway_slickpay_class')) {

	/**
	 * Load the class for creating custom gateway once plugins are loaded.
	 */
	function init_wc_gateway_slickpay_class()
    {
        if (class_exists('WC_Payment_Gateway')) {

            class WC_Gateway_Slickpay extends WC_Payment_Gateway
            {
                // Plugin settings vars
                public $api_environment, $account_type, $bank_account, $api_module, $public_key;

                public function __construct()
                {
                    //
                } // Here is the  End __construct()

				/**
				 * Initialise gateway settings form fields.
				 *
				 * @version 1.0.0
				 * @since   1.0.0
				 */
                public function init_form_fields()
                {
					// Form fields.
					$this->form_fields = array(
                        'account_type' => array(
                            'title'    => esc_html__('Account type', 'slickpay-payement-gateway'),
                            'type'     => 'select',
                            'desc_tip' => esc_html__('Your Slick-pay.com account type.', 'slickpay-payement-gateway'),
                            'default'  => 'user',
                            'options'  => array(
                                'user'     => esc_html__('User', 'slickpay-payement-gateway'),
                                'merchant' => esc_html__('Merchant', 'slickpay-payement-gateway'),
                            ) // array of options for select/multiselects only
                        ),
                        'public_key' => array(
                            'title'    => esc_html__('Public Key', 'slickpay-payement-gateway'),
                            'type'     => 'text',
                            'desc_tip' => esc_html__('Your Slick-pay.com account public key.', 'slickpay-payement-gateway'),
                        ),
                        'bank_account' => array(
                            'title'    => esc_html__('User bank account', 'slickpay-payement-gateway'),
                            'type'     => 'select',
                            'desc_tip' => esc_html__('Select your bank account.', 'slickpay-payement-gateway'),
                            'options'  => $this->_slickpay_user_accounts(),
                        ),
                        // 'deposit_status' => array(
                        //     'title'    => esc_html__('Enable Deposits', 'slickpay-payement-gateway'),
                        //     'type'     => 'select',
                        //     'desc_tip' => esc_html__('Once activated, a new field deposit appears on the product edit page at the general tab.', 'slickpay-payement-gateway'),
                        //     'default'  => 'off',
                        //     'options'  => [
                        //         'off' => esc_html__('Disable', 'slickpay-payement-gateway'),
                        //         'on'   => esc_html__('Enable', 'slickpay-payement-gateway'),
                        //     ]
                        // ),
                        'api_environment' => array(
                            'title'    => esc_html__('API environment', 'slickpay-payement-gateway'),
                            'type'     => 'select',
                            'desc_tip' => esc_html__('Slick-pay.com API environment.', 'slickpay-payement-gateway'),
                            'default'  => 'live',
                            'options'  => array(
                                'live'    => esc_html__('Live (Production)', 'slickpay-payement-gateway'),
                                'sandbox' => esc_html__('Sandbox (Test)', 'slickpay-payement-gateway'),
                            )
                        ),
                        'api_module' => array(
                            'title'    => esc_html__('API module', 'slickpay-payement-gateway'),
                            'type'     => 'select',
                            'desc_tip' => esc_html__('Your Slick-pay.com API module.', 'slickpay-payement-gateway'),
                            'default'  => 'invoices',
                            'options'  => array(
                                'transfers' => esc_html__('Transfer', 'slickpay-payement-gateway'),
                                'invoices'  => esc_html__('Invoice', 'slickpay-payement-gateway'),
                            )
                        )
                    );
                }

				/**
				 * Check if the gateway is available for use.
				 *
				 * @version 1.0.0
				 * @since   1.0.0
				 * @return  bool
				 */
				public function is_available()
                {
                    return get_woocommerce_currency() == 'DZD';
                }

                /**
                 * Maybe reset settings.
                 *
				 * @return  void
                 * @version 1.0.0
                 * @since   1.0.0
                 */
                public function admin_notice_settings()
                {
                    if (empty($this->account_type)
                        || ($this->account_type == 'user' && empty($this->api_module))
                        || empty($this->public_key)
                    ) {

                        print "<div class=\"error\"><p>" . sprintf(esc_html__("Please ensure that %s is configured.", 'slickpay-payement-gateway'), esc_html($this->method_title)) . "</p></div>";
                    }

                    if (get_woocommerce_currency() != 'DZD') {
                        print "<div class=\"error\"><p>" . sprintf(esc_html__("Please ensure that the currency is set to %s (%s).", 'slickpay-payement-gateway'), get_woocommerce_currencies()['DZD'], get_woocommerce_currency_symbol('DZD')) . "</p></div>";
                    }
                }

				/**
				 * Process the payment and return the result
				 *
				 * @param   int $order_id Order ID.
				 * @return  array
				 * @version 1.0.0
				 * @since   1.0.0
				 */
                public function process_payment($order_id)
                {
                    $customer_order = new WC_Order($order_id);

                    try {

                        if ($this->account_type == 'user') {

                            if ($this->api_module == 'transfers') {
                                $response = $this->_user_transfer_create($customer_order);
                            } else {
                                $response = $this->_user_invoice_create($customer_order);
                            }
                        } else {
                            $response = $this->_merchant_invoice_create($customer_order);
                        }

                        if ($response['status'] == 200 && $response['data']['success']) {
                            $payment_url = $response['data']['url'];
                            $payment_id = $response['data']['id'];
                        } else {
                            $errorMessage = !empty($response['data']['message']) ? $response['data']['message'] : esc_html__('Payment Gateway Error!', 'slickpay-payement-gateway');

                            $timezone = date_default_timezone_get();
                            $datetime = date('d-M-Y H:i:s');

                            error_log("[{$datetime} {$timezone}] Slick-pay Gateway error [{$this->account_type} {$this->api_module}]: " . json_encode($response) . PHP_EOL, 3, realpath(plugin_dir_path(__FILE__)) . DIRECTORY_SEPARATOR . 'log.txt');

                            throw new Exception($errorMessage);
                        }
                    } catch (\Exception $e) {
                        throw new Exception($e->getMessage());
                    }

                    // $customer_order->update_meta_data('slickpay_payment_url', $payment_url);

                    $customer_order->update_meta_data('slickpay_payment_id', $payment_id);
                    $customer_order->save();

                    return array(
                        'result'   => 'success',
                        'redirect' => $payment_url
                    );
                }

				/**
				 * Output for the order received page.
				 *
				 * @version 1.0.0
				 * @since   1.0.0
				 */
                public function thankyou_page($order_id)
                {
                    global $woocommerce;

                    $customer_order = new WC_Order($order_id);

                    $payment_id = $customer_order->get_meta('slickpay_payment_id', true);

                    if (!$customer_order->is_paid()
                        && !empty($payment_id)
                    ) {

                        try {

                            if ($this->account_type == 'user') {

                                if ($this->api_module == 'transfers') {
                                    $response = $this->_user_transfer_details($payment_id);
                                } else {
                                    $response = $this->_user_invoice_details($payment_id);
                                }
                            } else {
                                $response = $this->_merchant_invoice_details($payment_id);
                            }

                            if ($response['status'] == 200
                                && $response['data']['data']['completed'] == 1
                            ) {

                                // Payment successful
                                $customer_order->add_order_note(esc_html__("Slick-Pay.com payment completed.", 'slickpay-payement-gateway'));

                                if ($redirect = realpath(plugin_dir_path(__FILE__) . 'redirect-' . $order_id . '.php')) {
                                    @unlink($redirect);
                                }

                                $log =  is_string($response['data']['data']['transaction']['log']) ? json_decode($response['data']['data']['transaction']['log'], true) : $response['data']['data']['transaction']['log'];

                                $customer_order->update_meta_data('slickpay_payment_amount', $response['data']['data']['transaction']['amount']);
                                $customer_order->update_meta_data('slickpay_payment_serial', $response['data']['data']['serial']);
                                $customer_order->update_meta_data('slickpay_payment_status', $response['data']['data']['status']);
                                $customer_order->update_meta_data('slickpay_payment_url', $response['data']['data']['url']);
                                $customer_order->update_meta_data('slickpay_transaction_orderId', $log['orderId']);
                                $customer_order->update_meta_data('slickpay_transaction_orderNumber', $log['OrderNumber']);
                                // $customer_order->update_meta_data('slickpay_transaction_approvalCode', $log['approvalCode']);
                                // $customer_order->update_meta_data('slickpay_transaction_respCode', $log['respCode_desc']);

                                if (!$customer_order->get_meta('slickpay_deposit', true)) {
                                    $customer_order->payment_complete();
                                } else {
                                    $customer_order->add_order_note($this->_get_note($customer_order));
                                }

                                $customer_order->save();

                                // this is important part for empty cart
                                $woocommerce->cart->empty_cart();
                            } else {
                                $customer_order->add_order_note(esc_html__("Slick-Pay.com payment status error !", 'slickpay-payement-gateway'));

                                wc_clear_notices();
                                wc_add_notice(esc_html__("An error has occured, please reload the page !", 'slickpay-payement-gateway'), 'error');
                                wc_print_notices();
                            }
                        } catch (\Exception $e) {
                            wc_clear_notices();
                            wc_add_notice(esc_html__("An error has occured, please reload the page !", 'slickpay-payement-gateway'), 'error');
                            wc_print_notices();
                        }
                    }
                }

                /**
                 * Display payment details on order details page.
                 *
				 * @param   int   $order_id Order ID.
				 * @param   array $data
                 * @return  void
                 * @version 1.0.0
                 * @since   1.0.0
                 */
                public function do_update_order_meta($order_id, $data)
                {
                    $customer_order = wc_get_order($order_id);

                    $slickpay_deposit = boolval(get_transient('slickpay_deposit'));

                    $customer_order->update_meta_data('slickpay_deposit', $slickpay_deposit);

                    if ($slickpay_deposit) {
                        $customer_order->update_meta_data('slickpay_today', floatval(get_transient('slickpay_today')));
                        $customer_order->update_meta_data('slickpay_remaining', floatval(get_transient('slickpay_remaining')));
                    }

                    $customer_order->save();
                }

                /**
                 * Display payment details on order details page.
                 *
                 * @param  WC_Order $order WooCommerce order
                 * @return  void
                 * @version 1.0.0
                 * @since   1.0.0
                 */
                public function slickpay_display_data($order)
                {
                    $payment_url = $order->get_meta('slickpay_payment_url', true);
                    $payment_serial = $order->get_meta('slickpay_payment_serial', true);
                    $satim_serial = $order->get_meta('slickpay_transaction_orderId', true);
                    $payment_status = $order->get_meta('slickpay_payment_status', true);

                    if (!empty($payment_url)
                        && !empty($payment_serial)
                        && !empty($payment_status)
                        && !empty($satim_serial)
                    ) {
                        print "<h3>" . esc_html__("Payment", 'slickpay-payement-gateway') . "</h3>
                        <ul>
                            <li><strong>" . esc_html__("Slickpay serial", 'slickpay-payement-gateway') . ":</strong> " . esc_html($payment_serial) . "</li>
                            <li><strong>" . esc_html__("SATIM serial", 'slickpay-payement-gateway') . ":</strong> " . esc_html($satim_serial) . "</li>
                            <li><strong>" . esc_html__("Payment status", 'slickpay-payement-gateway') . ":</strong> " . esc_html($payment_status) . "</li>
                            <li><strong>" . esc_html__("Details", 'slickpay-payement-gateway') . ":</strong> <a href=\"" . esc_url($payment_url) . "\" target=\"_blank\">" . esc_html__("Payment details page", 'slickpay-payement-gateway') . "</a></li>
                        </ul>";
                    }
                }

                /**
                 * Enqueue a script in the WordPress admin on edit.php.
                 *
                 * @param   int $hook Hook suffix for the current admin page.
                 * @return  void
                 * @version 1.0.1
                 * @since   1.0.0
                 */
                public function slickpay_enqueue_scripts($hook)
                {
                    if (!empty($_GET['page']) && $_GET['page'] == 'wc-settings'
                        && !empty($_GET['tab']) && $_GET['tab'] == 'checkout'
                        && !empty($_GET['section']) && $_GET['section'] == 'wc_gateway_slickpay'
                    ) {
                        wp_enqueue_script('slickpay_scripts', WC_Slickpay_Payment_Gateways::plugin_url() . '/assets/js/script.js');
                        wp_add_inline_script('slickpay_scripts', 'var slickpayApiAccount = "' . $this->bank_account . '"; var slickpayApiUrl = {sandbox: {user: `' . $this->_api_url('sandbox', 'user') . '`, merchant: `' . $this->_api_url('sandbox', 'merchant') . '`}, live: {user: `' . $this->_api_url('live', 'user') . '`, merchant: `' . $this->_api_url('live', 'merchant') . '`}};', 'before');
                    }
                }

                /**
                 * Enqueue a style in the WordPress on frontend.
                 *
                 * @return  void
                 * @version 1.0.0
                 * @since   1.0.0
                 */
                public function slickpay_checkout_styles()
                {
                    wp_enqueue_style('slickpay_styles', WC_Slickpay_Payment_Gateways::plugin_url() . '/assets/css/style.css');
                    wp_add_inline_style('slickpay_styles', ':root { --slickpay-icon-url: url("' . WC_Slickpay_Payment_Gateways::plugin_url() . '/assets/cibeddahabia.png' . '"); }');
                }

                /**
                 * Listing user accounts from Slick-Pay API
                 *
                 * @return array
                 * @version 1.0.0
                 * @since   1.0.0
                 */
                private function _slickpay_user_accounts()
                {
                    $accounts = [];

                    try {
                        $args = array(
                            'headers' => array(
                                "Accept"        => "application/json",
                                "Authorization" => "Bearer " . esc_html($this->public_key),
                            )
                        );
                        $response = wp_remote_get($this->_api_url() . "/accounts", $args);
                        $body = wp_remote_retrieve_body($response);
                        $http_code = wp_remote_retrieve_response_code($response);

                        $body = json_decode($body, true);

                        if ($http_code == 200
                            && !empty($body['data'])
                        ) {
                            foreach ($body['data'] as $account) {
                                $accounts[$account['uuid']] = esc_html($account['title']) . " (RIB: " . esc_html($account['rib']) . ")";
                            }
                        }
                    } catch (\Exception $e) {
                    }

                    return $accounts;
                }

                /**
                 * Creating new transfer for standard user type from Slick-Pay API
                 *
                 * @param  WC_Order $order WooCommerce order
                 * @return array
                 * @version 1.0.0
                 * @since   1.0.0
                 */
                private function _user_transfer_create($order)
                {
                    $data = [
                        'url'    => $this->get_return_url($order),
                        'amount' => $this->_get_total_amount($order),
                    ];

                    if (!empty($this->bank_account)) {
                        $data['account'] = $this->bank_account;
                    }

                    $args = array(
                        'body'    => $data,
                        'headers' => array(
                            "Accept"        => "application/json",
                            "Authorization" => "Bearer " . esc_html($this->public_key),
                        )
                    );
                    $response = wp_remote_post($this->_api_url() . "/transfers", $args);
                    $body = wp_remote_retrieve_body($response);
                    $http_code = wp_remote_retrieve_response_code($response);
                    $errors = array();

                    return [
                        'data'   => json_decode($body, true),
                        'status' => $http_code,
                        'errors' => $errors,
                    ];
                }

                /**
                 * Getting transfer data for standard user type from Slick-Pay API
                 *
                 * @param  number $transfer_id Slick-Pay transfer ID
                 * @return array
                 * @version 1.0.0
                 * @since   1.0.0
                 */
                private function _user_transfer_details($transfer_id)
                {
                    $args = array(
                        'headers' => array(
                            "Accept"        => "application/json",
                            "Authorization" => "Bearer " . esc_html($this->public_key),
                        )
                    );
                    $response = wp_remote_get($this->_api_url() . "/transfers/{$transfer_id}", $args);
                    $body = wp_remote_retrieve_body($response);
                    $http_code = wp_remote_retrieve_response_code($response);
                    $errors = array();

                    return [
                        'data'   => json_decode($body, true),
                        'status' => $http_code,
                        'errors' => $errors,
                    ];
                }

                /**
                 * Creating new invoice for standard user type from Slick-Pay API
                 *
                 * @param  WC_Order $order WooCommerce order
                 * @return array
                 * @version 1.0.0
                 * @since   1.0.0
                 */
                private function _user_invoice_create($order)
                {
                    $data = [
                        'amount'    => $this->_get_total_amount($order),
                        'url'       => $this->get_return_url($order),
                        'firstname' => ucfirst($order->get_billing_first_name()),
                        'lastname'  => strtoupper($order->get_billing_last_name()),
                        'phone'     => $order->get_billing_phone(),
                        'email'     => $order->get_billing_email(),
                        'address'   => $this->_format_address($order),
                        'items'     => $this->_format_items($order->get_items()),
                        'note'      => $this->_get_note($order),
                    ];

                    if (!empty($this->bank_account)) {
                        $data['account'] = $this->bank_account;
                    }

                    $args = array(
                        'body'    => $data,
                        'headers' => array(
                            "Accept"        => "application/json",
                            "Authorization" => "Bearer " . esc_html($this->public_key),
                        )
                    );
                    $response = wp_remote_post($this->_api_url() . "/invoices", $args);
                    $body = wp_remote_retrieve_body($response);
                    $http_code = wp_remote_retrieve_response_code($response);
                    $errors = array();

                    return [
                        'data'   => json_decode($body, true),
                        'status' => $http_code,
                        'errors' => $errors,
                    ];
                }

                /**
                 * Getting invoice data for standard user type from Slick-Pay API
                 *
                 * @param  number $invoice_id Slick-Pay invoice ID
                 * @return array
                 * @version 1.0.0
                 * @since   1.0.0
                 */
                private function _user_invoice_details($invoice_id)
                {
                    $args = array(
                        'headers' => array(
                            "Accept"        => "application/json",
                            "Authorization" => "Bearer " . esc_html($this->public_key),
                        )
                    );
                    $response = wp_remote_get($this->_api_url() . "/invoices/{$invoice_id}", $args);
                    $body = wp_remote_retrieve_body($response);
                    $http_code = wp_remote_retrieve_response_code($response);
                    $errors = array();

                    return [
                        'data'   => json_decode($body, true),
                        'status' => $http_code,
                        'errors' => $errors,
                    ];
                }

                /**
                 * Creating new invoice for merchant user type from Slick-Pay API
                 *
                 * @param  WC_Order $order WooCommerce order
                 * @return array
                 * @version 1.0.0
                 * @since   1.0.0
                 */
                private function _merchant_invoice_create($order)
                {
                    $data = array(
                        'url'       => $this->get_return_url($order),
                        'amount'    => $this->_get_total_amount($order),
                        'name'      => $this->_format_name($order),
                        'firstname' => ucfirst($order->get_billing_first_name()),
                        'lastname'  => strtoupper($order->get_billing_last_name()),
                        'phone'     => $order->get_billing_phone(),
                        'email'     => $order->get_billing_email(),
                        'address'   => $this->_format_address($order),
                        'items'     => $this->_format_items($order->get_items()),
                        'note'      => $order->get_customer_note(),
                    );
                    $args = array(
                        'body'    => $data,
                        'headers' => array(
                            "Accept"        => "application/json",
                            "Authorization" => "Bearer " . esc_html($this->public_key),
                        )
                    );

                    $response = wp_remote_post($this->_api_url() . "/invoices", $args);
                    $body = wp_remote_retrieve_body($response);
                    $http_code = wp_remote_retrieve_response_code($response);
                    $errors = array();

                    return [
                        'data'   => json_decode($body, true),
                        'status' => $http_code,
                        'errors' => $errors,
                    ];
                }

                /**
                 * Getting invoice data for merchant user type from Slick-Pay API
                 *
                 * @param  number $invoice_id Slick-Pay invoice ID
                 * @return array
                 * @version 1.0.0
                 * @since   1.0.0
                 */
                private function _merchant_invoice_details($invoice_id)
                {
                    $args = array(
                        'headers' => array(
                            "Accept"        => "application/json",
                            "Authorization" => "Bearer " . esc_html($this->public_key),
                        )
                    );
                    $response = wp_remote_get($this->_api_url() . "/invoices/{$invoice_id}", $args);
                    $body = wp_remote_retrieve_body($response);
                    $http_code = wp_remote_retrieve_response_code($response);
                    $errors = array($args);

                    return [
                        'data'   => json_decode($body, true),
                        'status' => $http_code,
                        'errors' => $errors,
                    ];
                }

                /**
                 * Generate the appropriate API url depending on user account type
                 *
                 * @param  string $v Generate sandbox or API prod url
                 * @return string
                 * @version 1.0.0
                 * @since   1.0.0
                 */
                private function _api_url($env = null, $userType = null)
                {
                    $apiType = 'users';

                    if (($this->account_type == 'merchant' && is_null($userType))
                        || (!is_null($userType) && $userType == 'merchant')
                    ) {
                        $apiType = 'merchants';
                    }

                    if (($this->api_environment == 'live' && empty($env))
                        || (!empty($env) && $env == 'live')
                    ) {
                        return "https://prodapi.slick-pay.com/api/v2/{$apiType}";
                    }

                    return "https://devapi.slick-pay.com/api/v2/{$apiType}";
                }

                /**
                 * Formating order client full name
                 *
                 * @param  WC_Order $order WooCommerce order
                 * @return string
                 * @version 1.0.0
                 * @since   1.0.0
                 */
                private function _format_name($order)
                {
                    return implode(' ', [ucfirst($order->get_billing_first_name()), strtoupper($order->get_billing_last_name())]);
                }

                /**
                 * Formating order client address
                 *
                 * @param  WC_Order $order WooCommerce order
                 * @return string
                 * @version 1.0.0
                 * @since   1.0.0
                 */
                private function _format_address($order)
                {
                    return $order->get_billing_address_1() . (!empty($order->get_billing_address_2()) ? ' ' . $order->get_billing_address_2() : '') . ', ' . $order->get_billing_city() . ', ' . $order->get_billing_state() . ' - ' . $order->get_billing_postcode() . ', ' . $order->get_billing_country();
                }

                /**
                 * Formating order items
                 *
                 * @param  WC_Order_Item $items WooCommerce order items
                 * @return string
                 * @version 1.0.0
                 * @since   1.0.0
                 */
                private function _format_items($items)
                {
                    $result = [];

                    foreach ($items as $item) {
                        $row = [
                            'name'     => $item->get_name(),
                            'price'    => $item->get_total() / $item->get_quantity(),
                            'quantity' => $item->get_quantity(),
                        ];
                        array_push($result, $row);
                    }

                    return $result;
                }

                /**
                 * Get the appropriate order total depending on deposit usage
                 *
                 * @param  WC_Order $order WooCommerce order
                 * @return float
                 * @version 1.0.0
                 * @since   1.0.0
                 */
                private function _get_total_amount($order)
                {
                    return boolval($order->get_meta('slickpay_deposit', true))
                        ? floatval($order->get_meta('slickpay_today', true))
                        : $order->get_total();
                }

                /**
                 * Generate note for order containing deposit items
                 *
                 * @param  WC_Order $order WooCommerce order
                 * @return string|null
                 * @version 1.0.0
                 * @since   1.0.0
                 */
                private function _get_note($order)
                {
                    return boolval($order->get_meta('slickpay_deposit', true))
                        ? __("Paid:", 'slickpay-payement-gateway') . " " . wc_price($order->get_meta('slickpay_today', true)) . " / " . esc_html__("Remaining:", 'slickpay-payement-gateway') . " " . wc_price($order->get_meta('slickpay_remaining', true))
                        : null;
                }

				/**
				 * Init.
				 *
				 * @version 1.0.0
				 * @since   1.0.0
				 */
                public function init()
                {
                    $this->id = "wc_gateway_slickpay";
                    $this->icon = WC_Slickpay_Payment_Gateways::plugin_url() . '/assets/cibeddahabia.png';
                    $this->has_fields = false;
                    $this->supports = array(
                        'products',
                    );

                    $this->method_title = esc_html__("CIB/EDAHABIA (Slick-Pay)", 'slickpay-payement-gateway');
                    $this->method_description = esc_html__("Slick-Pay.com Secured Payment Gateway", 'slickpay-payement-gateway');

                    // Set plugin options (Title, Description are Locked).
                    $this->update_option('title', $this->method_title);
                    $this->update_option('description', $this->method_description);

                    // Load the settings.
                    $this->init_form_fields();
                    $this->init_settings();

                    // Turn these settings into variables we can use
                    foreach ($this->settings as $setting_key => $value) {
                        $this->{$setting_key} = $value;
                    }

                    // Define user set variables.
                    $this->title = $this->get_option('title');
                    $this->description = $this->get_option('description');

                    // Actions.
                    add_action('admin_notices', array($this, 'admin_notice_settings'));
                    add_action('woocommerce_checkout_update_order_meta', array($this, 'do_update_order_meta'), 10, 2);
                    add_action('woocommerce_thankyou', array($this, 'thankyou_page'));

                    // Save settings
                    if (is_admin()) {
                        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                        add_action('admin_enqueue_scripts', array($this, 'slickpay_enqueue_scripts'));
                        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'slickpay_display_data'), 10, 1);
                    } else {
                        add_action('wp_enqueue_scripts', array($this, 'slickpay_checkout_styles'));
                    }
                }
            }

			/**
			 * Add WC Slick-Pay Gateway Classes.
			 *
			 * @param array $methods Gateway Methods.
			 * @return array
			 * @version 1.0.0
			 * @since   1.0.0
			 */
			function add_wc_gateway_slickpay_classes( $methods )
            {
                $instance = new WC_Gateway_Slickpay();

                $instance->init();

                $methods[] = $instance;

                return $methods;
			}
			add_filter('woocommerce_payment_gateways', 'add_wc_gateway_slickpay_classes');

			/**
			 * Registers WooCommerce Blocks integration.
			 *
			 * @return void
			 * @version 1.0.0
			 * @since   1.0.0
			 */
            function register_wc_gateway_slickpay_woocommerce_block_support()
            {
                if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
                    require_once realpath(WC_Slickpay_Payment_Gateways::plugin_path() . '/includes/blocks/class-wc-gateway-slickpay-blocks.php');
                    add_action(
                        'woocommerce_blocks_payment_method_type_registration',
                        function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                            $payment_method_registry->register(new WC_Gateway_Slickpay_Blocks_Support());
                        }
                    );
                }
            }
            add_action('woocommerce_blocks_loaded', 'register_wc_gateway_slickpay_woocommerce_block_support');
        }
    }
}

