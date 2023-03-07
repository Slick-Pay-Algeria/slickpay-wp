<?php

class slickpay extends WC_Payment_Gateway
{
    private $api_environment, $account_type, $bank_account, $api_module, $public_key;

    public function __construct()
    {
        $this->clear_cache();

        // global ID
        $this->id = "slickpay";

        $title = __("Slick-Pay", 'slickpay');
        $description = __("Slick-Pay.com Secured Payment Gateway", 'slickpay');

        // Show Title
        $this->method_title = $title;

        // Show Description
        $this->method_description = $description;

        // Vertical tab title
        $this->title = $title;
        $this->description = $description;

        $this->icon = null;

        $this->has_fields = true;

        // Support default form with credit card
        $this->supports = array(
            'products',
            // 'default_credit_card_form'
        );

        // Setting defines
        $this->init_form_fields();

        // Load time variable setting
        $this->init_settings();

        // Turn these settings into variables we can use
        foreach ($this->settings as $setting_key => $value) {
            $this->$setting_key = $value;
        }

        // Plugin actions
        add_action('admin_notices', array($this, 'do_check_settings'));

        add_action('woocommerce_thankyou', array($this, 'do_complete_payment'));

        // Save settings
        if (is_admin()) {

            add_action('admin_enqueue_scripts', array($this, 'slickpay_enqueue_scripts'));

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'slickpay_display_data'), 10, 1);
        } elseif ($this->account_type == 'user') {
            // add_action('woocommerce_after_order_notes', array($this, 'slickpay_checkout_fields'), 10);

            // add_action('woocommerce_after_checkout_validation', array($this, 'slickpay_checkout_validation'), 10, 2);
        }
    } // Here is the  End __construct()

    // Administration fields for specific Gateway
    public function init_form_fields()
    {
        $this->form_fields = array(
            'account_type' => array(
                'title'    => __('Account type', 'slickpay'),
                'type'     => 'select',
                'desc_tip' => __('Your Slick-pay.com account type.', 'slickpay'),
                'default'  => 'user',
                'options'  => array(
                    'user'     => __('User', 'slickpay'),
                    'merchant' => __('Merchant', 'slickpay')
                ) // array of options for select/multiselects only
            ),
            'bank_account' => array(
                'title'    => __('User bank account', 'slickpay'),
                'type'     => 'select',
                'desc_tip' => __('Select your bank account.', 'slickpay'),
                'options'  => $this->slickpay_user_accounts()
            ),
            'api_environment' => array(
                'title'    => __('API environment', 'slickpay'),
                'type'     => 'select',
                'desc_tip' => __('Slick-pay.com API environment.', 'slickpay'),
                'default'  => 'live',
                'options'  => array(
                    'live'    => __('Live', 'slickpay'),
                    'sandbox' => __('Sandbox', 'slickpay')
                ) // array of options for select/multiselects only
            ),
            'api_module' => array(
                'title'    => __('API module', 'slickpay'),
                'type'     => 'select',
                'desc_tip' => __('Your Slick-pay.com API module.', 'slickpay'),
                'default'  => 'invoices',
                'options'  => array(
                    'transfers' => __('Transfer', 'slickpay'),
                    'invoices'  => __('Invoice', 'slickpay')
                ) // array of options for select/multiselects only
            ),
            'public_key' => array(
                'title'    => __('Public Key', 'slickpay'),
                'type'     => 'text',
                'desc_tip' => __('Your Slick-pay.com account public key.', 'slickpay'),
                // 'default'  => __( '00012345678912345678', 'slickpay' ),
            )
        );
    }

    // Response handled for payment gateway
    public function process_payment($order_id)
    {
        // global $woocommerce;

        $customer_order = new WC_Order($order_id);

        try {

            if ($this->account_type == 'user') {

                if ($this->api_module == 'transfers') {
                    $response = $this->user_transfer_create($customer_order);
                } else {
                    $response = $this->user_invoice_create($customer_order);
                }

            } else {
                $response = $this->merchant_invoice_create($customer_order);
            }

            if ($response['status'] == 200 && $response['data']['success']) {
                $payment_url = $response['data']['url'];
                $payment_id = $response['data']['id'];
            } else {
                $errorMessage = !empty($response['data']['message']) ? $response['data']['message'] : __('Payment Gateway Error!', 'slickpay');

                throw new Exception($errorMessage);
            }

        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }

        $fh = fopen(plugin_dir_path(__FILE__) . 'redirect-' . $order_id . '.php', 'w+');
        fwrite($fh, '<?php header("Location: ' . $payment_url . '"); exit;');
        fclose($fh);
        $redirect_url = plugin_dir_url(__FILE__) . 'redirect-' . $order_id . '.php';

        $customer_order->update_meta_data('slickpay_payment_id', $payment_id);
        $customer_order->save();

        return array(
            'result'   => 'success',
            'redirect' => $redirect_url
        );
    }

    // Payment gateway callback
    public function do_complete_payment($order_id)
    {
        global $woocommerce;

        $customer_order = new WC_Order($order_id);

        $payment_id = $customer_order->get_meta('slickpay_payment_id');

        if (!$customer_order->is_paid() && !empty($payment_id)) {

            try {

                if ($this->account_type == 'user') {

                    if ($this->api_module == 'transfers') {
                        $response = $this->user_transfer_confirm($payment_id);
                    } else {
                        $response = $this->user_invoice_confirm($payment_id);
                    }

                } else {
                    $response = $this->merchant_invoice_confirm($payment_id);
                }

                if (
                    $response['status'] == 200 &&
                    $response['data']['data']['completed'] == 1
                ) {

                    // Payment successful
                    $customer_order->add_order_note(__("Slick-Pay.com payment completed.", 'slickpay'));

                    if ($redirect = realpath(plugin_dir_path(__FILE__) . 'redirect-' . $order_id . '.php')) @unlink($redirect);

                    $log =  is_string($response['data']['data']['transaction']['log']) ? json_decode($response['data']['data']['transaction']['log'], true) : $response['data']['data']['transaction']['log'];

                    $customer_order->update_meta_data('slickpay_payment_amount', $response['data']['data']['transaction']['amount']);
                    $customer_order->update_meta_data('slickpay_payment_serial', $response['data']['data']['serial']);
                    $customer_order->update_meta_data('slickpay_payment_status', $response['data']['data']['status']);
                    $customer_order->update_meta_data('slickpay_payment_url', $response['data']['data']['url']);
                    $customer_order->update_meta_data('slickpay_transaction_orderId', $log['orderId']);
                    $customer_order->update_meta_data('slickpay_transaction_orderNumber', $log['OrderNumber']);
                    $customer_order->update_meta_data('slickpay_transaction_approvalCode', $log['approvalCode']);
                    $customer_order->update_meta_data('slickpay_transaction_respCode', $log['respCode_desc']);

                    // paid order marked
                    $customer_order->payment_complete();

                    // this is important part for empty cart
                    $woocommerce->cart->empty_cart();
                } else {
                    $customer_order->add_order_note(__("Slick-Pay.com payment status error !", 'slickpay'));

                    wc_clear_notices();
                    wc_add_notice(__("An error has occured, please reload the page !", 'slickpay'), 'error');
                    wc_print_notices();
                }
            } catch (\Exception $e) {
                wc_clear_notices();
                wc_add_notice(__("An error has occured, please reload the page !", 'slickpay'), 'error');
                wc_print_notices();
            }
        }
    }

    // Check if the payment gateway plugin is configured
    public function do_check_settings()
    {
        if (
            empty($this->account_type) || (
                $this->account_type == 'user' &&
                empty($this->api_module)
            ) ||
            empty($this->public_key)
        ) {

            print "<div class=\"error\"><p>" . sprintf(__("Please ensure that <a href=\"%s\"><strong>%s</strong></a> is configured."), admin_url('admin.php?page=wc-settings&tab=checkout'), $this->method_title) . "</p></div>";
        }
    }

    public function slickpay_checkout_fields($checkout)
    {
        print '<div id="slickpay_checkout_fields"><h3>Slickpay</h3>';

        woocommerce_form_field('billing_rib', array(
                'type'          => 'text',
                'class'         => array('form-row-wide'),
                'label'         => __('RIB'),
                'required'      => true,
                'placeholder'   => __('Enter your RIB', 'slickpay'),
            ),
            $checkout->get_value('billing_rib')
        );

        print '</div>';
    }

    public function slickpay_checkout_validation($data, $errors)
    {
        if (
            !is_admin() &&
            $this->account_type == 'user' &&
            $_POST['payment_method'] === 'slickpay' && (
                empty($_POST['billing_rib']) ||
                !is_numeric($_POST['billing_rib']) ||
                strlen($_POST['billing_rib']) != 20
            )
        )  $errors->add('validation', __("Please enter a correct RIB."));
    }

    public function slickpay_display_data($order)
    {
        $payment_url = get_post_meta($order->id, 'slickpay_payment_url', true);
        $payment_serial = get_post_meta($order->id, 'slickpay_payment_serial', true);
        $satim_serial = get_post_meta($order->id, 'slickpay_transaction_orderId', true);
        $payment_status = get_post_meta($order->id, 'slickpay_payment_status', true);

        if (
            !empty($payment_url) &&
            !empty($payment_serial) &&
            !empty($payment_status) &&
            !empty($satim_serial)
        ) {
            print "<h3>Payment</h3>
            <ul>
                <li><strong>" . __("Slickpay serial", 'slickpay') . ":</strong> {$payment_serial}</li>
                <li><strong>" . __("SATIM serial", 'slickpay') . ":</strong> {$satim_serial}</li>
                <li><strong>" . __("Payment status", 'slickpay') . ":</strong> {$payment_status}</li>
                <li><strong>" . __("Details", 'slickpay') . ":</strong> <a href=\"$payment_url\" target=\"_blank\">" . __("Payment details page", 'slickpay') . "</a></li>
            </ul>";
        }
    }

    public function slickpay_enqueue_scripts($hook)
    {
        if (
            !empty($_GET['page']) && $_GET['page'] == 'wc-settings' &&
            !empty($_GET['tab']) && $_GET['tab'] == 'checkout' &&
            !empty($_GET['section']) && $_GET['section'] == 'slickpay'
        ) {
            wp_enqueue_script('slickpay_scripts', plugin_dir_url(__FILE__) . 'assets/js/script.js');
        }
    }

    private function slickpay_user_accounts()
    {
        $accounts = [];

        try {

            $cURL = curl_init();

            curl_setopt($cURL, CURLOPT_URL, $this->api_url() . "/accounts");
            curl_setopt($cURL, CURLOPT_HTTPHEADER, array(
                "Accept: application/json",
                "Authorization: Bearer {$this->public_key}",
            ));
            curl_setopt($cURL, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($cURL, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($cURL, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($cURL, CURLOPT_TIMEOUT, 20);

            $response = curl_exec($cURL);
            $status = curl_getinfo($cURL, CURLINFO_HTTP_CODE);
            $errors = curl_error($cURL);

            curl_close($cURL);

            $response = json_decode($response, true);

            if (
                $status == 200 &&
                !empty($response['data'])
            ) {
                foreach ($response['data'] as $account) {
                    $accounts[$account['uuid']] = "{$account['title']} (RIB: {$account['rib']})";
                }
            }

        } catch (\Exception $e) {
        }

        return $accounts;
    }

    private function user_transfer_create($order)
    {
        $cURL = curl_init();

        $data = [
            'url'    => $this->get_return_url($order),
            'amount' => $order->get_total(),
        ];

        if (!empty($this->bank_account)) $data['account'] = $this->bank_account;

        curl_setopt($cURL, CURLOPT_URL, $this->api_url() . "/transfers");
        curl_setopt($cURL, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($cURL, CURLOPT_HTTPHEADER, array(
            "Accept: application/json",
            "Authorization: Bearer {$this->public_key}",
        ));
        curl_setopt($cURL, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($cURL, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($cURL, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($cURL, CURLOPT_TIMEOUT, 20);

        $response = curl_exec($cURL);
        $status = curl_getinfo($cURL, CURLINFO_HTTP_CODE);
        $errors = curl_error($cURL);

        curl_close($cURL);

        return [
            'data'   => json_decode($response, true),
            'status' => $status,
            'errors' => $errors,
        ];
    }

    private function user_invoice_create($order)
    {
        $cURL = curl_init();

        $data = [
            'amount'    => $order->get_total(),
            'url'       => $this->get_return_url($order),
            'firstname' => ucfirst($order->get_billing_first_name()),
            'lastname'  => strtoupper($order->get_billing_last_name()),
            'phone'     => $order->get_billing_phone(),
            'email'     => $order->get_billing_email(),
            'address'   => $this->format_address($order),
            'items'     => $this->format_items($order->get_items()),
        ];

        if (!empty($this->bank_account)) $data['account'] = $this->bank_account;

        curl_setopt($cURL, CURLOPT_URL, $this->api_url() . "/invoices");
        curl_setopt($cURL, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($cURL, CURLOPT_HTTPHEADER, array(
            "Accept: application/json",
            "Authorization: Bearer {$this->public_key}",
        ));
        curl_setopt($cURL, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($cURL, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($cURL, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($cURL, CURLOPT_TIMEOUT, 20);

        $response = curl_exec($cURL);
        $status = curl_getinfo($cURL, CURLINFO_HTTP_CODE);
        $errors = curl_error($cURL);

        curl_close($cURL);

        return [
            'data'   => json_decode($response, true),
            'status' => $status,
            'errors' => $errors,
        ];
    }

    private function user_transfer_confirm($transfer_id)
    {
        $cURL = curl_init();

        curl_setopt($cURL, CURLOPT_URL, $this->api_url() . "/transfers/{$transfer_id}");
        curl_setopt($cURL, CURLOPT_HTTPHEADER, array(
            "Accept: application/json",
            "Authorization: Bearer {$this->public_key}",
        ));
        curl_setopt($cURL, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($cURL, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($cURL, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($cURL, CURLOPT_TIMEOUT, 20);

        $response = curl_exec($cURL);
        $status = curl_getinfo($cURL, CURLINFO_HTTP_CODE);
        $errors = curl_error($cURL);

        curl_close($cURL);

        return [
            'data'   => json_decode($response, true),
            'status' => $status,
            'errors' => $errors,
        ];
    }

    private function user_invoice_confirm($invoice_id)
    {
        $cURL = curl_init();

        curl_setopt($cURL, CURLOPT_URL, $this->api_url() . "/invoices/{$invoice_id}");
        curl_setopt($cURL, CURLOPT_HTTPHEADER, array(
            "Accept: application/json",
            "Authorization: Bearer {$this->public_key}",
        ));
        curl_setopt($cURL, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($cURL, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($cURL, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($cURL, CURLOPT_TIMEOUT, 20);

        $response = curl_exec($cURL);
        $status = curl_getinfo($cURL, CURLINFO_HTTP_CODE);
        $errors = curl_error($cURL);

        curl_close($cURL);

        return [
            'data'   => json_decode($response, true),
            'status' => $status,
            'errors' => $errors,
        ];
    }

    private function merchant_invoice_create($order)
    {
        $cURL = curl_init();

        curl_setopt($cURL, CURLOPT_URL, $this->api_url() . "/invoices");
        curl_setopt($cURL, CURLOPT_POSTFIELDS, http_build_query([
            'url'       => $this->get_return_url($order),
            'amount'    => $order->get_total(),
            'name'      => $this->format_name($order),
            'firstname' => ucfirst($order->get_billing_first_name()),
            'lastname'  => strtoupper($order->get_billing_last_name()),
            'phone'     => $order->get_billing_phone(),
            'email'     => $order->get_billing_email(),
            'address'   => $this->format_address($order),
            'items'     => $this->format_items($order->get_items()),
            'note'      => $order->get_customer_note(),
        ]));
        curl_setopt($cURL, CURLOPT_HTTPHEADER, array(
            "Accept: application/json",
            "Authorization: Bearer {$this->public_key}",
        ));
        curl_setopt($cURL, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($cURL, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($cURL, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($cURL, CURLOPT_TIMEOUT, 20);

        $response = curl_exec($cURL);
        $status = curl_getinfo($cURL, CURLINFO_HTTP_CODE);
        $errors = curl_error($cURL);

        curl_close($cURL);

        return [
            'data'   => json_decode($response, true),
            'status' => $status,
            'errors' => $errors,
        ];
    }

    private function merchant_invoice_confirm($invoice_id)
    {
        $cURL = curl_init();

        curl_setopt($cURL, CURLOPT_URL, $this->api_url() . "/invoices/{$invoice_id}");
        curl_setopt($cURL, CURLOPT_HTTPHEADER, array(
            "Accept: application/json",
            "Authorization: Bearer {$this->public_key}",
        ));
        curl_setopt($cURL, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($cURL, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($cURL, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($cURL, CURLOPT_TIMEOUT, 20);

        $response = curl_exec($cURL);
        $status = curl_getinfo($cURL, CURLINFO_HTTP_CODE);
        $errors = curl_error($cURL);

        curl_close($cURL);

        return [
            'data'   => json_decode($response, true),
            'status' => $status,
            'errors' => $errors,
        ];
    }

    private function api_url()
    {
        $user_type = 'users';
        if ($this->account_type == 'merchant') $user_type = 'merchants';

        if ($this->api_environment == 'live') return "https://prodapi.slick-pay.com/api/v2/{$user_type}";

        return "https://devapi.slick-pay.com/api/v2/{$user_type}";
    }

    private function format_name($order)
    {
        return implode(' ', [ucfirst($order->get_billing_first_name()), strtoupper($order->get_billing_last_name())]);
    }

    private function format_address($order)
    {
        return $order->get_billing_address_1() . (!empty($order->get_billing_address_2()) ? ' ' . $order->get_billing_address_2() : '') . ', ' . $order->get_billing_city() . ', ' . $order->get_billing_state() . ' - ' . $order->get_billing_postcode() . ', ' . $order->get_billing_country();
    }

    private function format_items($items)
    {
        $result = [];

        foreach ($items as $item) {
            array_push($result, [
                'name'     => $item->get_name(),
                'price'    => $item->get_total() / $item->get_quantity(),
                'quantity' => $item->get_quantity(),
            ]);
        }

        return $result;
    }

    private function clear_cache()
    {
        $files = glob(plugin_dir_path(__FILE__) . "redirect-*");
        $now   = time();

        foreach ($files as $file) {

            if (
                is_file($file)
                && $now - filemtime($file) >= 60 * 60 * 24 * 2 // 2 days
            ) @unlink($file);
        }
    }
}
