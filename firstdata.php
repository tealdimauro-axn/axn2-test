<?php
/* Firstdata Payment Gateway Class */

include_once( 'includes/firstdata-utility.php' );

class WANDERLUST_Firstdata_Gateway extends WC_Payment_Gateway {

    /** @var bool Whether or not logging is enabled */
    public static $log_enabled = false;

    /** @var WC_Logger Logger instance */
    public static $log = false;

    // Setup our Gateway's id, description and other values
    function __construct() {

        global $woocommerce;

        define('FIRSTDATA_TEMPLATE_PATH', untrailingslashit(plugin_dir_path(__FILE__)) . '/templates/');
        // The global ID for this Payment method
        $this->id = "wanderlust_firstdata_gateway"; //wanderlust_firstdata_india

        define('WC_FIRSTDATA_PLUGIN_URL', untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__))));

        // The Title shown on the top of the Payment Gateways Page next to all the other Payment Gateways
        $this->method_title = $this->getPluginTitle();

        // The description for this Payment Gateway, shown on the actual Payment options page on the backend
        $this->method_description = $this->getDescription();

        // The title to be used for the vertical tabs that can be ordered top to bottom
        $this->title = $this->getPluginTitle();

        // If you want to show an image next to the gateway's name on the frontend, enter a URL to an image.
        $this->icon = $this->getIcon();


        $this->callback = str_replace('https:', 'http:', add_query_arg('/wc-api/fdsuccess', '/wc-api/fdfailed', '/wc-api/fdnotify'));
        add_action('woocommerce_api_fdsuccess', array($this, 'checkTransactionStatus'));
        add_action('woocommerce_api_fdfailed', array($this, 'checkTransactionStatus'));
        add_action('woocommerce_api_fdnotify', array($this, 'checkNotifyResponse'));

        add_action( 'woocommerce_api_firstdata', array( $this, 'webhook' ) );
          
        add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );


        add_action('woocommerce_after_checkout_validation', array($this, 'checkout_card_validation'));

        // Bool. Can be set to true if you want payment fields to show on the checkout 
        // if doing a direct integration, which we are doing in this case
        $this->has_fields = true;

        $this->debug = 'yes' === $this->get_option('debug', 'no');
        self::$log_enabled = $this->debug;

        // Define the supported features
        $this->supports = array(
            'products',
            'tokenization',
            'default_credit_card_form', //We can enable this when we have Hosted Option enabled in Admin Option
        );

        /*
         * Add Refund support only for allowed country based on localizatio setting
         */
        if ($this->getDetails('refunds') == 'yes') {
            $this->supports[] = 'refunds';
        }

        // This basically defines your settings which are then loaded with init_settings()
        $this->init_form_fields();

        // After init_settings() is called, you can get the settings and load them into variables, e.g:
        $this->init_settings();

        // Turn these settings into variables we can use
        foreach ($this->settings as $setting_key => $value) {
            $this->$setting_key = $value;
        }

        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

        // Instalment fields shown on the thanks page and in emails
        $this->instalment_details = get_option('woocommerce_multiple_paymet_gateway', array(
            array(
                'multipayment_id' => $this->get_option('multipayment_id'),
                'multipayment_description' => $this->get_option('multipayment_description'),
                'multipayment_store' => $this->get_option('multipayment_store'),
                'multipayment_minammount' => $this->get_option('multipayment_minammount'),
                'multipayment_maxamount' => $this->get_option('multipayment_maxamount'),
                'multipayment_count' => $this->get_option('multipayment_count'),
                'multipayment_period' => $this->get_option('multipayment_period'),
                'multipayment_quotas' => $this->get_option('multipayment_quotas'),
                'multipayment_interest' => $this->get_option('multipayment_interest')
            ),
                )
        );
        

        // Save settings
        if (is_admin()) {
            // Versions over 2.0
            // Save our administration options. Since we are not going to be doing anything special
            // we have not defined 'process_admin_options' in this class so the method in the parent
            // class will be used instead
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            /* Added for multy payment */
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'save_instalment_details'));

            // Receipt
        }
    }

    /**
     * Build the administration fields for First Data IPG Gateway
     */
    public function init_form_fields() {
        $this->form_fields = include( 'includes/firstdata-settings.php' );
    }

    /**
     * Installment for user checkout
     */
    public function showInstallment() {
        global $woocommerce;
        $totalcartamount = $woocommerce->cart->total;
        $installment_payment = $this->get_saved_cards();
        $applicable_installment_payment = $this->check_for_applicable_installment($totalcartamount, $installment_payment);
        if (count($applicable_installment_payment) > 0) {
            wc_get_template('multiple_payment_card.php', array('installment_payment' => $applicable_installment_payment, 'card_total' => $totalcartamount), 'firstdata-gateway/', FIRSTDATA_TEMPLATE_PATH);
        }
    }

    /**
     * Check the installment is applicable or not
     */
    private function check_for_applicable_installment($cart_total, $installment_payment) {
        $final_installment_payment = array();
        foreach ($installment_payment as $key => $installment) {
            if (($installment['multipayment_minammount'] <= $cart_total) && ($installment['multipayment_maxamount'] >= $cart_total)) {
                array_push($final_installment_payment, $installment_payment[$key]);
            }
        }
        return $final_installment_payment;
    }

    /**
     * get_saved_cards function.
     *
     * @access private
     * @return array
     */
    private function get_saved_cards() {
        $multiple_payment = get_option('woocommerce_multiple_paymet_gateway');
        return $multiple_payment;
    }

    /**
     * Generate the Firstdata Payment Gateway button link.
     *
     * @since 1.0.0
     */
    function generate_ipg_in_form($order_id) {
        global $woocommerce;
        $order = wc_get_order($order_id);
        $notifyUrl = get_site_url(null, '/wc-api/fdnotify');
        $successUrl = get_site_url(null, '/wc-api/fdsuccess');
        //$failedUrl = get_site_url(null, '/wc-api/fdfailed');
		    $failedUrl = "https://axnsport.com/falla-en-el-pago/";
		/*$timezone = "GMT-3";*/
		$timezone = "America/Buenos_Aires";
		date_default_timezone_set("America/Argentina/Buenos_Aires");

        $txndatetime = date('Y:m:d-H:i:s');
		
	

        $country = $this->get_option('country');
        $reseller = $this->get_option('reseller');
        $currency = $this->get_currency_code_by_country(get_woocommerce_currency());

        $language = "en_EN";
        /*if (defined('WPLANG')) {
            $language = WPLANG;
        }*/
		$language=get_locale();
        $customNotifyUrl = '';
        $customSuccessUrl = '';
        $customFailedUrl = '';
        if ($this->get_option('env') == "TEST") {
            $storename = $this->test_store_name;
            $customNotifyUrl = $this->test_notification_url;
            $customSuccessUrl = $this->test_success_url;
            $customFailedUrl = $this->test_fail_url;
        } else {
            $storename = $this->prod_store_name;
            $customNotifyUrl = $this->prod_notification_url;
            $customSuccessUrl = $this->prod_success_url;
            $customFailedUrl = $this->prod_fail_url;
        }
        $url = $this->getDetails('connecturl');

        $notifyUrl = $customNotifyUrl != '' ? $customNotifyUrl : $notifyUrl;
        $successUrl = $order->get_checkout_order_received_url();
 		 $failedUrl = $order->get_cancel_order_url();
		
 
          

        $ipg_args = array(
            'timezone' => $timezone,
            'txndatetime' => $txndatetime,
            'hash' => $this->createRequestHash($txndatetime, $order->order_total, $currency),
            'currency' => $currency,
            'mode' => $this->mode,
            'storename' => $storename,
            'chargetotal' => $order->order_total,
            'language' => $language,
            'responseSuccessURL' => $successUrl,
            'responseFailURL' => $failedUrl,
            'transactionNotificationURL' => $notifyUrl,
            'txntype' => $this->authorisation == 'yes' ? 'sale' : 'preauth',
            'checkoutoption' => $this->checkout,
            'dynamicMerchantName' => $this->get_option('dynamic_merchant_name'),
            'authenticateTransaction' =>  'true',
             'threeDSRequestorChallengeIndicator' => 1,
            'dccSkipOffer' => 'false',
            'oid' => $order_id,
        );

        $ipg_args['bname'] = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        $ipg_args['bcompany'] = $order->get_billing_company();
        $ipg_args['baddr1'] = $order->get_billing_address_1();
        $ipg_args['baddr2'] = $order->get_billing_address_2();
        $ipg_args['bcity'] = $order->get_billing_city();
        $ipg_args['bstate'] = $order->get_billing_state();
        $ipg_args['bcountry'] = $order->get_billing_country();
        $ipg_args['bzip'] = $order->get_billing_postcode();
        $ipg_args['phone'] = $order->get_billing_phone();
        $ipg_args['email'] = $order->get_billing_email();
        $ipg_args['fax'] = '';
        $ipg_args['sname'] = $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name();
        $ipg_args['saddr1'] = $order->get_shipping_address_1();
        $ipg_args['saddr2'] = $order->get_shipping_address_2();
        $ipg_args['scity'] = $order->get_shipping_city();
        $ipg_args['sstate'] = $order->get_shipping_state();
        $ipg_args['scountry'] = $order->get_shipping_country();
        $ipg_args['szip'] = $order->get_shipping_postcode();

        $token_id = wc_clean(WC()->session->get($this->id . '-token'));
        WC()->session->__unset($this->id . '-token');
        if ('yes' == $this->tokenisation && is_user_logged_in() == true) {
            if ($token_id != 'new' && !empty($token_id)) {
                $token = WC_Payment_Tokens::get($token_id);
                // Token user ID does not match the current user... bail out of payment processing.
                if ($token->get_user_id() !== wp_get_current_user()->ID) {
                    // Optionally display a notice with `wc_add_notice`
                    $this->log('malfunction ' . wp_get_current_user()->ID . ' user tried to access differnt user token ' . $token->get_user_id(), 'Error');
                    return;
                }
                if ($token) {
                    $ipg_args['hosteddataid'] = $token->get_token();
                    $ipg_args['hosteddatastoreid'] = $storename;
                }
            } else {
                $ipg_args['assignToken'] = 'true';
            }
        }

        /*
         * Handle Authorization option is enabled and and user provided the card information in checkout
         */
        if ($this->authorization == 'hidden' && ($token_id == 'new' || $token_id == '')) {
            $cardNumber = wc_clean(WC()->session->get($this->id . '-card-number'));
            $cardExpiry = str_replace(" ", "", wc_clean(WC()->session->get($this->id . '-card-expiry')));
            $getCardExp = explode("/", $cardExpiry);
            $cardcvc = wc_clean(WC()->session->get($this->id . '-card-cvc'));
            $bname = wc_clean(WC()->session->get($this->id . '-bname'));
            WC()->session->__unset($this->id . '-card-number');
            WC()->session->__unset($this->id . '-card-expiry');
            WC()->session->__unset($this->id . '-card-cvc');
            WC()->session->__unset($this->id . '-bname');
            $ipg_args['cardnumber'] = str_replace(" ", "", $cardNumber);
            $ipg_args['expmonth'] = trim($getCardExp[0]);
            $ipg_args['expyear'] = '20' . trim($getCardExp[1]);
            if ($cardExpiry != '' && (strlen($getCardExp[1]) == 2 || strlen($getCardExp[1]) == 4)) {
                if (strlen($getCardExp[1]) == 2)
                    $date = DateTime::createFromFormat('m/y', $cardExpiry);
                if (strlen($getCardExp[1]) == 4)
                    $date = DateTime::createFromFormat('m/Y', $cardExpiry);
                if (isset($date))
                    $ipg_args['expyear'] = $date->format('Y');
            }
            $cardtype = wc_clean(WC()->session->get($this->id . '-card-type'));
            WC()->session->__unset($this->id . '-card-type');
            if ($cardtype != '') {
                $ipg_args['cardFunction'] = $cardtype;
            }
            $ipg_args['cvm'] = $cardcvc;
            $ipg_args['bname'] = $bname;
            $ipg_args['full_bypass'] = 'true';
        }

        /*
         * Handle installment if it enabled and user seleted the installment in checkout
         */
        $enable_installment = wc_clean(WC()->session->get($this->id . '-enable_installment_payment'));
        WC()->session->__unset($this->id . '-enable_installment_payment');

       

        if ($enable_installment == "multiplepayment") {
            $installment_id = wc_clean(WC()->session->get($this->id . '-installment_id'));
            $installment_details = get_option('woocommerce_multiple_paymet_gateway');
            WC()->session->__unset($this->id . '-installment_id');
            foreach ($installment_details as $key => $installment) {
                if ($installment['multipayment_id'] == $installment_id) {

                    $new_total = $order->order_total * $installment['multipayment_interest'] ;
                    $new_total = number_format($new_total, 2, '.', '');
					
					 $recargo =   $new_total - $order->order_total ;
					
				 
                    $ipg_args['hash'] = $this->createRequestHash($txndatetime, $new_total, $currency);


                    $ipg_args['numberOfInstallments'] = $installment['multipayment_count'];
                    $ipg_args['chargetotal'] = $new_total;

                    $order->set_total( $new_total );
                    update_post_meta($order_id,'_order_total', $new_total);
                  
                    
				  $fee_data   = array(
						'name'       => __('Recargo Tarjeta'),
						'amount'     => wc_format_decimal($recargo),
						'tax_status' => 'none',
						'tax_class'  => ''
					);

 
    
        $item = new WC_Order_Item_Fee(); // Get an empty instance object

        $item->set_name( $fee_data['name'] );
        $item->set_amount( $fee_data['amount'] );
        $item->set_tax_class($fee_data['tax_class']);
        $item->set_tax_status($fee_data['tax_status']);
        $item->set_total($fee_data['amount']);

        $order->add_item( $item );
        $item->save(); // (optional) to be sure
        $mostrar_cuotas =  $installment['multipayment_quotas'];

        $intereses =           $installment['multipayment_interest'];
        $porcentajeint = ($installment['multipayment_interest'] -1 )*100;
        $total_intereses = $new_total;
        $valorcuota =  wc_format_decimal($new_total  / $mostrar_cuotas);
                  
                  
        if($porcentajeint < 1){
          $texto_mostrar='
							<p>Descuento por pago en cuotas <strong>'.$porcentajeint.'%</strong> <p>Total a pagar con descuento <strong>$'.round($total_intereses,2).'</strong> <p>Pagar&aacute;s <span style="color: #000000;"><strong>'.$mostrar_cuotas.'</strong></span> cuotas de <span style="color: #000000;"><strong>$'.$valorcuota.'</strong></span> cada una.</p>
							<p>&nbsp;</p><p>&nbsp;</p>';
        } else {
           $texto_mostrar='    <p>Total a pagar con intereses <strong>$'.round($total_intereses,2).'</strong>
        <p>Pagar&aacute;s <span style="color: #000000;"><strong>'.$mostrar_cuotas.'</strong></span> 
        cuotas de <span style="color: #000000;"><strong>$'.round($valorcuota,2).'</strong></span> cada una.</p>
							<p>&nbsp;</p><p>&nbsp;</p>';
        }
        
       
    


                    /**
                     * Send Delay Month only for Mexico 
                     */
                    if ($country == "mex") {
                        $ipg_args['installmentDelayMonths'] = $installment['multipayment_period'];
                    }
                }
            }
            /* for mexico retailer update the interest_instalment value */
            if ($country == "mex") {
                if ($reseller == "firstdatamexico" && $this->interest_instalment == "yes") {
                    $ipg_args['installmentsInterest'] = 'true';
                } else {
                    $ipg_args['installmentsInterest'] = 'false';
                }
            }
        }

 

        /*
         * Add local/alternative payment method
         */
        if ($this->local_payment_method == 'yes') {
            $payMethod = wc_clean(WC()->session->get('local_payment_user_selection'));

            if ($payMethod != '' && $this->validpaymentOptions($payMethod) == false) {
                $this->log('Received invalid value ' . $payMethod . ' Local Payment option from checkout', 'Error');
            } else if ($payMethod != '' && $this->validpaymentOptions($payMethod) == true) {
                $this->log('Received Local Payment option from checkout', 'info');
                $ipg_args['paymentMethod'] = $payMethod;
            }
            WC()->session->__unset('local_payment_user_selection');
        }

        // semail for India Netbanking
        if ($country == "ind" && isset($ipg_args['paymentMethod']) && $ipg_args['paymentMethod'] == 'netbanking') {
            $ipg_args['semail'] = $order->get_billing_email();
        }

		   
			  
		
        $ipgform = '';
		
       
        foreach ($ipg_args as $key => $value) {
            if ($value) {
                $ipgform .= '<input type="hidden" name="' . $key . '" value="' . $value . '" />' . "\n";
            }
        }

        return ''.$texto_mostrar.'<form action="' . $url . '" method="POST" name="payform" id="payform">
                ' . $ipgform . '
                <input type="submit" class="button" id="submit_ipg_payment_form" value="' . __('FINALIZAR PAGO', $this->id) . '" /> <a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __('Cancelar pedido &amp; vaciar el carrito', $this->id) . '</a>
                
        </form>';
    }

    /**
     * Process Payment Method to handled user checkout action hook
     * @param type $order_id
     * @return type 
     */
    public function process_payment($order_id) {
      
      
      if(empty($this->get_post_var('_multiplepayment'))){
        
        return array('result' => 'error'   );
      }
        $this->log('Enter in to Process Payment for order ID: ' . $order_id, 'info');
        $order = new WC_Order($order_id);
        $this->log('Post Data ' . print_r($_POST, TRUE), 'info'); //exit;
        WC()->session->__unset($this->id . '-token');
        WC()->session->set($this->id . '-token', $this->get_post_var('wc-' . $this->id . '-payment-token'));
        if ($this->authorization == 'hidden') {
            WC()->session->__unset($this->id . '-card-type');
            WC()->session->__unset($this->id . '-card-number');
            WC()->session->__unset($this->id . '-card-expiry');
            WC()->session->__unset($this->id . '-card-cvc');
            WC()->session->__unset($this->id . '-bname');
            WC()->session->set($this->id . '-card-type', $this->get_post_var($this->id . '-card-type'));
            WC()->session->set($this->id . '-card-number', $this->get_post_var($this->id . '-card-number'));
            WC()->session->set($this->id . '-card-expiry', $this->get_post_var($this->id . '-card-expiry'));
            WC()->session->set($this->id . '-card-cvc', $this->get_post_var($this->id . '-card-cvc'));
            WC()->session->set($this->id . '-bname', $order->billing_first_name . ' ' . $order->billing_last_name);
        }
        if ($this->local_payment_method == 'yes') {
            if ($this->get_post_var('local_payment_option')) {
                WC()->session->__unset('local_payment_user_selection');
                WC()->session->set('local_payment_user_selection', $this->get_post_var('local_payment_option'));
            }
        }

        WC()->session->__unset($this->id . '-installment_id');
        WC()->session->set($this->id . '-installment_id', $this->get_post_var('_multiplepayment'));
        WC()->session->__unset($this->id . '-enable_installment_payment');
        WC()->session->set($this->id . '-enable_installment_payment', $this->get_post_var('_fullpaymet'));
        $order = wc_get_order($order_id);

        return array('result' => 'success', 'redirect' => $order->get_checkout_payment_url(true));
    }

    /**
     * Receipt page.
     * Display text and a button to direct the user to the payment screen.
     * @since 1.0.0
     */
    function receipt_page($order) {
        echo '<p>' . __('Gracias por tu pedido, haz click en el botón a continuación para finalizar el pago en FirstData.', $this->id) . '</p>';

        echo $this->generate_ipg_in_form($order);
    }

    /**
     * Builds our payment fields area - including tokenization fields for logged
     * in users, and the actual payment fields.
     */
    public function payment_fields() {
        if ($this->description) {
            echo apply_filters('wc_' . $this->id . '_description', wpautop(wp_kses_post($this->description)));
        }
        if ('yes' == $this->rule_activation) {
            $this->showInstallment();
        }
        $tokens = WC_Payment_Tokens::get_customer_tokens(get_current_user_id(), $this->id);
        if ('yes' == $this->tokenisation && $tokens && is_user_logged_in()) {
            $this->tokenization_script();
            $this->saved_payment_methods();
        }
        if ('yes' == $this->local_payment_method) {
            $this->localPaymentMethod();
        }
        if ($this->authorization == 'hidden') {
            $this->form();
        }
    }

    /**
     * genarate interest option to be added in admin for Mexico reseller
     */
    public function generate_interest_instalment_html() {
        ob_start();
        ?>
        <tr valign="top" id="mexico_installment_interest_tr">
            <th scope="row" class="titledesc"><?php _e('Interest Instalment For Mexico', 'woocommerce'); ?>:</th>
        </tr>
        <?php
    }

    /**
     * Local Payment Checkout page ui handling 
     */
    public function localPaymentMethod() {
        $local_payment = $this->paymentOptionsValue();
        $local_method = $this->local_payment_method_options;
        $local_payment_method_options = array();
        foreach ($local_method as $local_options) {
            if (array_key_exists($local_options, $local_payment)) {
                $local_payment_method_options[$local_options] = $local_payment[$local_options];
            }
        }
        if ('yes' == $this->local_payment_method && $this->authorization == 'hidden') {
            wp_enqueue_script('firstdata_gateway_script', plugins_url('assets/js/firstdata-gateway.js', __FILE__), array('jquery'), WC()->version, true);

            $localSettingData = array('localPaySetting' => $this->paymentOptionsValue());
            wp_localize_script('firstdata_gateway_script', 'fd_setting', $localSettingData);
        }

        wc_get_template('local-payment-method.php', array('local_payment' => $local_payment_method_options), 'firstdata-gateway/', FIRSTDATA_TEMPLATE_PATH);
    }

    /**
     * Local Payment options supported in IPG configuration
     */
    public function paymentOptionsValue() {
        return array(
            'M' => array('name' => 'MasterCard', 'card_support' => true, 'shipping_support' => true),
            'V' => array('name' => 'Visa (Credit/Debit/Electron/Delta)', 'card_support' => true, 'shipping_support' => true),
            'A' => array('name' => 'American Express', 'card_support' => true, 'shipping_support' => true),
            'C' => array('name' => 'Diners', 'card_support' => true, 'shipping_support' => true),
            'J' => array('name' => 'JCB', 'card_support' => true, 'shipping_support' => true),
            'debitDE' => array('name' => 'SEPA Direct Debit', 'card_support' => false, 'shipping_support' => true),
            'CA' => array('name' => 'Cabal', 'card_support' => true, 'shipping_support' => true),
            'giropay' => array('name' => 'Giropay', 'card_support' => false, 'shipping_support' => true),
            'ideal' => array('name' => 'iDEAL', 'card_support' => false, 'shipping_support' => true),
            'klarna' => array('name' => 'Klarna', 'card_support' => false, 'shipping_support' => true),
            'indiawallet' => array('name' => 'Local Wallets India', 'card_support' => false, 'shipping_support' => false),
            'emi' => array('name' => 'Equated Monthly Instalments (EMI)', 'card_support' => true, 'shipping_support' => true),
            'MA' => array('name' => 'Maestro', 'card_support' => true, 'shipping_support' => true),
            'maestroUK' => array('name' => 'Maestro UK', 'card_support' => true, 'shipping_support' => true),
            'masterpass' => array('name' => 'MasterPass', 'card_support' => false, 'shipping_support' => true),
            'netbanking' => array('name' => 'Netbanking (India)', 'card_support' => false, 'shipping_support' => false),
            'paypal' => array('name' => 'PayPal', 'card_support' => false, 'shipping_support' => true),
            'RU' => array('name' => 'RuPay', 'card_support' => true, 'shipping_support' => true),
            'sofort' => array('name' => 'SOFORT Banking (Überweisung)', 'card_support' => false, 'shipping_support' => true),
            'SO' => array('name' => 'Sorocred', 'card_support' => true, 'shipping_support' => true),
            'BCMC' => array('name' => 'Bancontact', 'card_support' => true, 'shipping_support' => true),
			'EL' => array('name' => 'ELO', 'card_support' => true,'shipping_support' => true),
            'hipercard' => array('name' => 'HIPER/HIPERCARD', 'card_support' => true,'shipping_support' => true),
			'mexicoLocal' => array('name' => 'MEXICOLOCAL', 'card_support' => true,'shipping_support' => false),
			
        );
    }

    public function checkPaymentOptions($value) {
        $options = array();
        if (array_key_exists($value, $this->paymentOptionsValue())) {
            $options = $this->paymentOptionsValue();
            $options[$value];
        }
        return $options;
    }

    public function validpaymentOptions($value) {
        return array_key_exists($value, $this->paymentOptionsValue()) ? true : false;
    }

    /**
     * Generate Instalment Details html.
     *
     * @return string
     */
    public function generate_instalment_details_html() {
        ob_start();
        ?>
        <tr valign="top" id="multiple_payment_tr">
            <th scope="row" class="titledesc"><?php _e('Payment Option', 'woocommerce'); ?>:</th>
            <td class="forminp" id="fd_instalment">
                <table class="widefat wc_input_table sortable" cellspacing="0">
                    <thead>
                        <tr>
                            <th class="sort">&nbsp;</th>
                            <th><?php _e('Store ID', 'woocommerce'); ?></th>
                            <th><?php _e('Label', 'woocommerce'); ?></th>
                            <th><?php _e('Min Amount', 'woocommerce'); ?></th>
                            <th><?php _e('Max Amount', 'woocommerce'); ?></th>
                            <th><?php _e('API Quotas', 'woocommerce'); ?></th>
                            <th><?php _e('Real Quotas', 'woocommerce'); ?></th>
                            <th><?php _e('Interest Rate', 'woocommerce'); ?></th>
                            <th class="delay-month"><?php _e('Period', 'woocommerce'); ?></th>
                        </tr>
                    </thead>
                    <tbody class="instalments">
                        <?php
                        $i = -1;
                        if ($this->instalment_details) {
                            foreach ($this->instalment_details as $instalment) {
                                $i++;

                                echo '<tr class="instalment">
                                        <td class="sort"></td>
                                        <td><input type="text" value="' . esc_attr(wp_unslash($instalment['multipayment_store'])) . '" name="multipayment_store[' . $i . ']" size="10"/></td>
                                        <td><input type="text" value="' . esc_attr(wp_unslash($instalment['multipayment_description'])) . '" name="multipayment_description[' . $i . ']" size="10"/></td>
                                        <td><input type="text" value="' . esc_attr($instalment['multipayment_minammount']) . '" name="multipayment_minammount[' . $i . ']" /></td>
                                        <td><input type="text" value="' . esc_attr(wp_unslash($instalment['multipayment_maxamount'])) . '" name="multipayment_maxamount[' . $i . ']" /></td>
                                        <td><input type="text" value="' . esc_attr($instalment['multipayment_count']) . '" name="multipayment_count[' . $i . ']" /></td>
                                        <td><input type="text" value="' . esc_attr($instalment['multipayment_quotas']) . '" name="multipayment_quotas[' . $i . ']" /></td>
                                        <td><input type="text" value="' . esc_attr($instalment['multipayment_interest']) . '" name="multipayment_interest[' . $i . ']" /></td>
                                        <td class="delay-month"><input type="text" value="' . esc_attr($instalment['multipayment_period']) . '" name="multipayment_period[' . $i . ']" /></td>
                                    </tr>';
                            }
                        }
                        ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="8"><a href="#" class="add button" id='add_button'><?php _e('+ Add Instalment', 'woocommerce'); ?></a> 
                                <a href="#" class="remove_rows button" id="remove_button"><?php _e('Remove instalment(s)', 'woocommerce'); ?></a></th>
                        </tr>
                        <tr>
                            <td colspan='5' style='font-style: italic;font-size:80%;'>
                                Click on the "Add Instalment" button to configure one or more payment options.<br/>
                                <b>Store ID :</b> Add Store ID.<br/>
                                <b>Label :</b> The option label to display on the front end.<br/>
                                <b>Min Amount :</b> Minimum amount to enable the payment option.<br/>
                                <b>Max amount :</b> Maximum amount to enable the payment option.<br/>
                                <b>Count :</b> Total number of payments.<br/>
                                <span class="delay-month"><b>Period :</b> Delay (in days) between payment<br/></span>
                                <b>DO not forget to click on "Save" button to save your modifications.</b></td>
                        </tr>
                    </tfoot>
                </table>
                <script type="text/javascript">
                    jQuery(function() {
                        jQuery('#fd_instalment').on( 'click', 'a.add', function(){ 
                            var country = jQuery('select#woocommerce_wanderlust_firstdata_gateway_country').find('option:selected').val();
                            var size = jQuery('#fd_instalment').find('tbody .instalment').length;
                            /* Add Maximum 5 rows */
                            if(size < 60){
                                first = '<tr class="instalment">\
                                            <td class="sort"></td>\
                                            <td><input type="text" name="multipayment_store[' + size + ']" /></td>\
                                            <td><input type="text" name="multipayment_description[' + size + ']" /></td>\
                                            <td><input type="text" name="multipayment_minammount[' + size + ']" /></td>\
                                            <td><input type="text" name="multipayment_maxamount[' + size + ']" /></td>';
                                                            second = '<td><input type="text" name="multipayment_count[' + size + ']" /></td>\
                                                                      <td><input type="text" name="multipayment_quotas[' + size + ']" /></td>\
                                                                      <td><input type="text" name="multipayment_interest[' + size + ']" /></td>';
                                                            last = '<td class="delay-month"><input type="text" name="multipayment_period[' + size + ']" /></td>\
                                    </tr>';
                                                            var instalHtml = '';
                                                            if(country == 'mex'){
                                                                instalHtml =  first + second + last;
                                                            } else {
                                                                instalHtml =  first + second + last;
                                                            }
                                                                                
                                                            jQuery(instalHtml).appendTo('#fd_instalment table tbody');
                                                            if(size == 4){
                                                                jQuery('#add_button').hide();
                                                            }
                                                        } else {
                                                            jQuery('#add_button').hide();
                                                        }

                                                        return false;
                                                    });

                                                    jQuery('#remove_button').on( 'click', function(){
                                                        var size = jQuery('#fd_instalment').find('tbody .instalment').length;
                                                        if(size < 5){
                                                            jQuery('#add_button').show();															
                                                        }
                                                    });

                                                    if (jQuery('#woocommerce_wanderlust_firstdata_gateway_rule_activation').is(":checked")) {
                                                        jQuery("#multiple_payment_tr").fadeIn( "slow" );
                                                    } else {
                                                        jQuery("#multiple_payment_tr").fadeOut( "slow" );
                                                    }

                                                    if(jQuery('.woocommerce-save-button')){
                                                    }

                                                });
                </script>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    /**
     * Save account details table.
     */
    public function save_instalment_details() {

        $instalmentsDetails = array();


        if (isset($_POST['woocommerce_wanderlust_firstdata_gateway_rule_activation']) &&
                ( $_POST['woocommerce_wanderlust_firstdata_gateway_rule_activation'] == 1) &&
                isset($_POST['multipayment_description'])) {

            $multipayment_description = array_map('wc_clean', $_POST['multipayment_description']);
            $multipayment_store = array_map('wc_clean', $_POST['multipayment_store']);
            $multipayment_minammount = array_map('wc_clean', $_POST['multipayment_minammount']);
            $multipayment_maxamount = array_map('wc_clean', $_POST['multipayment_maxamount']);
            $multipayment_count = array_map('wc_clean', $_POST['multipayment_count']);
            $multipayment_period = array_map('wc_clean', $_POST['multipayment_period']);
            $multipayment_quotas = array_map('wc_clean', $_POST['multipayment_quotas']);
            $multipayment_interest = array_map('wc_clean', $_POST['multipayment_interest']);


            foreach ($multipayment_description as $i => $name) {
                if (!isset($multipayment_description[$i])) {
                    continue;
                }
                $multipayment_id = $i + 1;
                $instalmentsDetails[] = array(
                    'multipayment_id' => $multipayment_id,
                    'multipayment_description' => $multipayment_description[$i],
                    'multipayment_store' => $multipayment_store[$i],
                    'multipayment_minammount' => $multipayment_minammount[$i],
                    'multipayment_maxamount' => $multipayment_maxamount[$i],
                    'multipayment_count' => $multipayment_count[$i],
                    'multipayment_period' => $multipayment_period[$i],
                    'multipayment_quotas' => $multipayment_quotas[$i],
                    'multipayment_interest' => $multipayment_interest[$i],
                );
            }
        } else {
            $instalmentsDetails = array();
        }

        update_option('woocommerce_multiple_paymet_gateway', $instalmentsDetails);
    }

    /**
     * Outputs fields for entering credit card information.
     * @since 2.6.0
     */
    public function form() {
        wp_enqueue_script('wc-credit-card-form');

        $fields = array();

        $cvc_field = '<p class="form-row form-row-last">
			<label for="' . esc_attr($this->id) . '-card-cvc">' . esc_html__('Card code', 'woocommerce-firstdata') . ' <span class="required">*</span></label>
			<input id="' . esc_attr($this->id) . '-card-cvc" class="input-text wc-credit-card-form-card-cvc" inputmode="numeric" autocomplete="off" autocorrect="no" autocapitalize="no" spellcheck="no" type="password" maxlength="4" placeholder="' . esc_attr__('CVC', 'woocommerce') . '" ' . $this->field_name('card-cvc') . ' style="width:100px" />
                    </p>';
        $cardtypecheck = $this->getCardType();
        if ($cardtypecheck == 'yes') {
            $cardtype = '<p class="form-row form-row-wide">
				<label for="' . esc_attr($this->id) . '-card-type">' . esc_html__('Debit/Credit', 'woocommerce-firstdata') . ' <span class="required">*</span></label>
                                <select id="' . esc_attr($this->id) . '-card-type" class="input-text wc-credit-card-form-card-type" ' . $this->field_name('card-type') . ' >
                                <option value="credit"> ' . esc_html__('Credit Card', 'woocommerce-firstdata') . ' </option>         
                                <option value="debit">' . esc_html__('Debit Card', 'woocommerce-firstdata') . '</option>                                          
                                </select> 
                        </p>';
        } else {
            $cardtype = '';
        }

        $default_fields = array(
            'card-type' => $cardtype,
            'card-number-field' => '<p class="form-row form-row-wide">
				<label for="' . esc_attr($this->id) . '-card-number">' . esc_html__('Card number', 'woocommerce-firstdata') . ' <span class="required">*</span></label>
				<input id="' . esc_attr($this->id) . '-card-number" class="input-text wc-credit-card-form-card-number" inputmode="numeric" autocomplete="cc-number" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;" ' . $this->field_name('card-number') . ' />
			</p>',
            'card-expiry-field' => '<p class="form-row form-row-first">
				<label for="' . esc_attr($this->id) . '-card-expiry">' . esc_html__('Expiry (MM/YY)', 'woocommerce-firstdata') . ' <span class="required">*</span></label>
				<input id="' . esc_attr($this->id) . '-card-expiry" class="input-text wc-credit-card-form-card-expiry" inputmode="numeric" autocomplete="cc-exp" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="' . esc_attr__('MM / YY', 'woocommerce') . '" ' . $this->field_name('card-expiry') . ' />
			</p>',
        );

        $default_fields['card-cvc-field'] = $cvc_field;

        $fields = wp_parse_args($fields, apply_filters('woocommerce_credit_card_form_fields', $default_fields, $this->id));
        ?>

        <fieldset id="wc-<?php echo esc_attr($this->id); ?>-cc-form" class='wc-credit-card-form wc-payment-form'>
        <?php do_action('woocommerce_credit_card_form_start', $this->id); ?>
        <?php
        foreach ($fields as $field) {
            echo $field;
        }
        ?>
        <?php do_action('woocommerce_credit_card_form_end', $this->id); ?>
            <div class="clear"></div>
        </fieldset>
            <?php
        }

        /**
         * Enqueues our tokenization script to handle some of the new form options.
         * @since 2.6.0
         */
        public function tokenization_script() {
            wp_enqueue_script(
                    'woocommerce-tokenization-form', plugins_url('/assets/js/frontend/tokenization-form' . ( defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min' ) . '.js', WC_PLUGIN_FILE), array('jquery'), WC()->version
            );
        }

        /**
         * Check the validity of data received in $_POST and the status of transaction
         * @since 1.0.0
         */
        public function checkTransactionStatus() {
            $postData = array();

            $getPost = wp_unslash($_POST); // WPCS: CSRF ok, input var ok.

            if (!empty($getPost)) {
                foreach ($getPost as $key => $value) {
                    $postData[$key] = htmlentities($value, ENT_QUOTES);
                }
            } else {
                $this->log('No transaction data was passed', 'Error');
                die('No transaction data was passed!');
            }
			$this->log('Gateway Returns : Response  ' . print_r($postData, TRUE), 'info');
            $this->log('Payment Gateway Returns to Site for Order Id: ' . $postData['oid'], 'info');
		    
            $approvalcode = substr($postData['approval_code'], 0, 1);
			$this->log('approval: ' . $postData['approval_code'], 'info');
            if ($this->validateGatewayHash($postData, false) === false) {
                $postData['fail_reason'] = 'Response Hash not matched';
                $this->payment_failure($postData);
            } else {
                if ($approvalcode == 'Y') {
                    $this->payment_success($postData);
                }

                if ($approvalcode == 'N') {
                    $this->payment_failure($postData);
                }
            }
        }

        /**
         * @since 1.0.0
         */
        public function payment_success($responseData) {

            global $woocommerce;

            $order = wc_get_order($responseData['oid']);

            if (!$responseData || !$order || !$responseData['oid']) {
                $this->log('Gateway Return: Invalid Data or Order Id is missing', 'Error');
                die('No transaction data was passed for success method!');
            }

            $responseSuccessURL = $this->get_return_url($order);
            $this->log('Gateway Returns : Success for order Id: ' . $responseData['oid'] . ' IPG Ref No: ' . $responseData['ipgTransactionId'], 'info');

            // Maybe save card
            if (is_user_logged_in() && 'yes' == $this->get_option('tokenisation') && $responseData['hosteddataid'] != '') {
                $tokens = WC_Payment_Tokens::get_customer_tokens(get_current_user_id(), $this->id);
                $stored_tokens = array();
                foreach ($tokens as $token) {
                    $stored_tokens[] = $token->get_last4();
                }
                if (!in_array(substr($responseData['cardnumber'], -4), $stored_tokens)) {
                    $this->save_card($responseData);
                }
            }
            /*
             * Added for ICICI Merchant to store the Installment
             */
            if ('icici' == $this->get_option('reseller') && $responseData['number_of_installments'] != '') {
                $order->add_order_note('Transaction Successful: <br/>Txn ID: ' . $responseData['oid'] . ' <br/>Amount: ' . $responseData['chargetotal'] . '<br/>IPG Ref No: ' . $responseData['ipgTransactionId'] . '<br/>Number of installment : ' . $responseData['number_of_installments'] . '<br/>Installments amount per month : ' . $responseData['installments_amount_per_month']);
            } else {
                $order->add_order_note('Transaction Successful: <br/>Txn ID: ' . $responseData['oid'] . ' <br/>Amount: ' . $responseData['chargetotal'] . '<br/>IPG Ref No: ' . $responseData['ipgTransactionId']);
            }

            $woocommerce->cart->empty_cart();

            /*
             * Updated transaction detail in order to process Refund later
             */
            $ipgTransactionId = $this->get_post_var('ipgTransactionId');
            update_post_meta($order->get_id(), '_order_hash_id', $this->get_post_var('oid'));
            update_post_meta($order->get_id(), '_txn_type', $this->get_post_var('txntype'));
            $order->payment_complete($ipgTransactionId);

            wp_redirect($responseSuccessURL);
        }

        /**
         * @since 1.0.0
         */
        public function payment_failure($responseData) {

            global $woocommerce;

            $order = new WC_Order($responseData['oid']);

            $this->log('Gateway Returns : Failure for order Id: ' . $responseData['oid'] . 'Transaction Declined: ' . $responseData['fail_reason'] . ' IPG Ref No: ' . $responseData['ipgTransactionId'], 'info');

            $failURL = $this->get_return_url($order);

            $order->update_status('failed');
            $order->add_order_note('Transaction Declined: ' . $responseData['fail_reason'] . '<br/>Txn ID: ' . $responseData['oid'] . ' <br/>Amount: ' . $responseData['chargetotal']);
            $woocommerce->cart->empty_cart();

            wp_redirect($failURL);
        }

        public function checkNotifyResponse() {
            global $woocommerce;

            $postData = array();

            $getPost = wp_unslash($_POST); // WPCS: CSRF ok, input var ok.
            if (!empty($getPost)) {
                foreach ($getPost as $key => $value) {
                    $postData[$key] = htmlentities($value, ENT_QUOTES);
                }
            } else {
                $this->log('Payment Gateway Notify URL : No transaction data ', 'info');
                die('No transaction data was passed!');
            }
            if (!$postData['oid']) {
                $this->log('Gateway Return: Invalid Data or Order Id is missing', 'Error');
                die('Invalid Data is passed!');
            }


            $txnid = $postData['oid'];
            $this->log('Payment Gateway Return: Notified for Order ID:' . $txnid, 'info');
            $order = new WC_Order($txnid);

            if ($this->validateGatewayHash($postData, true) === false) {
                $this->log('Invalid Response Hash received from gateway on Notify URL', 'Error');
                die('Invalid Response Hash received for Order ID: ' . $txnid);
            }

            $approvalcode = substr($postData['approval_code'], 0, 1);

            if ($approvalcode == 'Y') {
                if (is_user_logged_in() && 'yes' == $this->get_option('tokenisation') && $postData['hosteddataid'] != '') {
                    $tokens = WC_Payment_Tokens::get_customer_tokens(get_current_user_id(), $this->id);
                    $stored_tokens = array();
                    foreach ($tokens as $token) {
                        $stored_tokens[] = $token->get_last4();
                    }
                    if (!in_array(substr($postData['cardnumber'], -4), $stored_tokens)) {
                        $this->save_card($postData);
                    }
                }
                $this->log('Gateway Returns to Notify URL : Payment Success for order Id: ' . $postData['order_id'], 'info');
                update_post_meta($order->get_id(), '_order_hash_id', $postData['oid']);
                update_post_meta($order->get_id(), '_txn_type', $postData['txntype']);
                $order->payment_complete($postData['ipgTransactionId']);
            }

            if ($approvalcode == 'N') {
                $this->log('Gateway Returns to Notify URL : Failure for order Id: ' . $postData['order_id'] . 'Transaction Declined: ' . $postData['fail_reason'] . ' IPG Ref No: ' . $postData['ipgTransactionId'], 'info');
                $order->add_order_note('Transaction Declined: ' . $postData['fail_reason'] . '<br/>Txn ID: ' . $postData['oid'] . ' <br/>Amount: ' . $postData['chargetotal'] . '<br/>IPG Ref No: ' . $postData['ipgTransactionId']);
                $order->update_status('failed');
            }
            $woocommerce->cart->empty_cart();
            return true;
        }

        private function validateGatewayHash($data, $isNotify) {
            if (!$data || !$data['oid']) {
                $this->log('Gateway Return: Invalid Data or Order Id is missing', 'Error');
                die('No transaction data was passed!');
            }
            $return = true;

            if ($this->get_option('env') == "TEST") {
                $storename = $this->get_option('test_store_name');
                $sharedsecret = $this->get_option('test_trans_key');
            } else {
                $storename = $this->get_option('prod_store_name');
                $sharedsecret = $this->get_option('prod_trans_key');
            }

            $order = new WC_Order($data['oid']);

            //$chargetotal = $order->order_total;
			 $chargetotal=$data['chargetotal'];
			
            $currency = $this->get_currency_code_by_country(get_woocommerce_currency());
			
			  
            $txndatetime = $data['txndatetime'];
            $approvalcode = $data['approval_code'];

            if ($isNotify == true) {
                $this->log('Hash Notify for Order Id: ' . $data['oid'], 'Info');
                $hashValue = sha1(bin2hex($chargetotal . $sharedsecret . $currency . $txndatetime . $storename . $approvalcode));
                if ($hashValue != $data['notification_hash']) {
                    $this->log('Invalid Notification Hash received from gateway Order Id: ' . $data['oid'], 'Error');
                    $return = false;
                }
            } else {
                $this->log('Hash Response for Order Id: ' . $data['oid'], 'Info');
                $hashValue = sha1(bin2hex($sharedsecret . $approvalcode . $chargetotal . $currency . $txndatetime . $storename));
                if ($hashValue != $data['response_hash']) {
                    $this->log('Invalid Response Hash received from gateway Order Id: ' . $data['oid'], 'Error');
                    $return = false;
                }
            }

            return $return;
        }

        /**
         * save_card function.
         *
         * @access public
         * @param Object $response
         * @return void
         */
        public function save_card($response) {
            $this->log('Token for Txn Id : ' . $response['oid'] . ' is created', 'info');
            $payment_token_from_gateway = $response['hosteddataid'];
            // Build the token
            $token = new WC_Payment_Token_CC();
            $token->set_token($payment_token_from_gateway); // Token comes from payment processor
            $token->set_gateway_id($this->id);
            $token->set_last4(substr($response['cardnumber'], -4));
            $token->set_expiry_year($response['expyear']);
            $token->set_expiry_month($response['expmonth']);
            $token->set_card_type($response['ccbrand']);
            $token->set_user_id(get_current_user_id());
            // Save the new token to the database
            $token->save();
            // Set this token as the users new default token
            WC_Payment_Tokens::set_users_default(get_current_user_id(), $token->get_id());
        }

        /**
         *  Get post data if set
         * @since 1.4
         */
        function get_post_var($name) {
            if (isset($_POST[$name])) {
                return wp_unslash($_POST[$name]);
            }
            return NULL;
        }

        // Validate fields
        public function validate_fields() {
            return true;
        }

        /**
         * Generate Localization Script html.
         * 
         * @return string
         * */
        public function generate_localization_html() {
            ob_start();
            ?>

        <script type="text/javascript">
            jQuery(function() {
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        
                var featuresSettings = eval( <?php echo json_encode(Firstdata_Utility::getFeatures()); ?> );
                var countrySelected = '';
                var resellerSelected = '';
                jQuery('.country').change('click',function() {
                    var country = jQuery( this ).val();
                    if(featuresSettings.hasOwnProperty(country)){
                        countrySelected = featuresSettings[country];
                        var sellerOptions = "";
                        for(var prop in countrySelected) {
                            var sellerDetails = countrySelected[prop];
                            sellerOptions += "<option value='" + prop + "'>" + sellerDetails.reseller_name + "</option>";
                        }
                        jQuery('select[name="woocommerce_wanderlust_firstdata_gateway_reseller"]').find('option').not(':first').remove();
                        jQuery('select[name="woocommerce_wanderlust_firstdata_gateway_reseller"]').append( sellerOptions );
                    }
                    if(country == 'mex') {
                        jQuery('.delay-month').show();
                    } else {
                        jQuery('.delay-month').hide();
                    }
                });
                jQuery('.reseller').change('click',function() {
                    resellerSelected = jQuery( this ).val();
                                                           
                    var sellerDetails = countrySelected[resellerSelected];
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    
                    jQuery('.customer-details').html(sellerDetails.customer_detail_title);
                    jQuery('#fd-contact-detail-desc').html(sellerDetails.customer_detail);
                    jQuery('#fd-contact-support-desc').html(sellerDetails.contact_support);
                    jQuery('.description').val(sellerDetails.description + " " + sellerDetails.reseller_name);
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            
                    jQuery("#fd-plugin-logo").attr("src",sellerDetails.logo);   
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    
                    if(sellerDetails.dynamic_merchant_name == 'yes'){
                        jQuery('.dynamic-descriptor').closest('tr').show();
                    } else {
                        jQuery('.dynamic-descriptor').closest('tr').hide();
                    }
                    if(sellerDetails.secure_pay == 'yes'){
                        jQuery('.secure-pay').closest('tr').show();
                    } else {
                        jQuery('.secure-pay').closest('tr').hide();
                    }
                    if(sellerDetails.dcc_skip_offer == 'yes'){
                        jQuery('.dcc-skip-offer').closest('tr').show();
                    } else {
                        jQuery('.dcc-skip-offer').closest('tr').hide();
                    }
                    if(sellerDetails.instalments == 'yes'){
                        jQuery('.instalment-config').show();
                        jQuery(".instalment-config").next("table").show();
                    } else {
                        jQuery('.instalment-config').hide();
                        jQuery(".instalment-config").next("table").hide();
                    }
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            
                    /*Mexico Condition
                     * Added by Mom
                     */
                    if(resellerSelected == "firstdatamexico"){
                        jQuery("#woocommerce_wanderlust_firstdata_gateway_interest_instalment").closest("tr").show();
                    }
                    else{
                        jQuery("#woocommerce_wanderlust_firstdata_gateway_interest_instalment").closest("tr").hide();
                        jQuery("#woocommerce_wanderlust_firstdata_gateway_interest_instalment").attr('checked', false);
                    }
                    /*
                     * End OF Mexico Condition
                     */
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            
                    var localPayment = sellerDetails.local_payment;
                    if(localPayment.length != 0) {
                        var localPaymentOptions = "";
                        for (var paykey in localPayment) {
                            if (localPayment.hasOwnProperty(paykey)) {
                                localPaymentOptions += "<option value='" + paykey + "'>" + localPayment[paykey] + "</option>";
                                jQuery('select[name="woocommerce_wanderlust_firstdata_gateway_local_payment_method_options[]"]').find('option').remove();
                                jQuery('select[name="woocommerce_wanderlust_firstdata_gateway_local_payment_method_options[]"]').append( localPaymentOptions );
                            }
                        } 
                        jQuery('.local-payment-method-title').show();
                        jQuery('.local-payment-method, .local-payment-method-options').closest('tr').show();
                    } else {

                        jQuery('select[name="woocommerce_wanderlust_firstdata_gateway_local_payment_method_options[]"]').find('option').remove();
                        jQuery('.local-payment-method-title').hide();
                        jQuery('.local-payment-method, .local-payment-method-options').closest('tr').hide();
                    }
                });
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                
                jQuery('.local-payment-method').change('click',function() {
                    if(jQuery(".local-payment-method").is(':checked')){ 
                        jQuery('.local-payment-method, .local-payment-method-options').closest('tr').show();
                    } else {
                        jQuery('.local-payment-method-options').closest('tr').hide();
                    }
                });
                jQuery('.ipg_rule_activation').on( 'click', function(){ 			
                    if (jQuery('#woocommerce_wanderlust_firstdata_gateway_rule_activation').is(":checked")) {
                        jQuery("#multiple_payment_tr").fadeIn( "slow" );
                    } else {
                        jQuery("#multiple_payment_tr").fadeOut( "slow" );
                    }		
                });
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       
                var prod_fields      = '.prod-trans-key, .prod-store-name, .prod-success-url, .prod-fail-url, .prod-notification-url'; 
                var test_fields      = '.test-trans-key, .test-store-name, .test-success-url, .test-fail-url, .test-notification-url';
                jQuery('.environment').change('click',function() {
                    if(jQuery('.environment').val()==='TEST') {
                        jQuery(prod_fields).closest('tr').hide();

                        jQuery(test_fields).closest('tr').show();

                    } else {
                        jQuery(prod_fields).closest('tr').show();

                        jQuery(test_fields).closest('tr').hide();

                    }
                });
        <?php
        /*
         * Display environment when page is loading =
         */
        if ($this->get_option('env') == "TEST" || $this->get_option('env') == '') {
            echo "jQuery(prod_fields).closest('tr').hide();";
            echo "jQuery(test_fields).closest('tr').show();";
        } else {
            echo "jQuery(prod_fields).closest('tr').show();";
            echo "jQuery(test_fields).closest('tr').hide();";
        }
        if ($this->get_option('checkout') == "combinedpage" || $this->get_option('checkout') == '') {
            echo "jQuery('.payment-mode').closest('tr').hide();";
            echo "jQuery('.tokenisation').closest('tr').hide();";
        }
        /*
         * Enable feature for reseller when loading page
         */
        $country = $this->get_option('country');
        $feature = Firstdata_Utility::getFeatures();
        $reseller = $this->get_option('reseller');


        if (array_key_exists($country, $feature) && array_key_exists($reseller, $feature[$country])) {
            $resellerFeatures = $feature[$country][$reseller];
            ?>
                        var countrySel = "<?php echo $this->get_option('country'); ?>";
                        var sellerSel = "<?php echo $this->get_option('reseller'); ?>";
                        var localPayment = '';
                        if(featuresSettings.hasOwnProperty(countrySel)){
                            countrySelected = featuresSettings[countrySel];
                            var sellerOptions = "";
                            for(var prop in countrySelected) {
                                var sellerDetails = countrySelected[prop];
                                if(prop === sellerSel){
                                    localPayment = sellerDetails.local_payment;
                                    jQuery("#fd-plugin-logo").attr("src",sellerDetails.logo);
                                }
                                sellerOptions += "<option value='" + prop + "'>" + sellerDetails.reseller_name + "</option>";
                            }

                            if(typeof localPayment !== "undefined" || localPayment.length != 0) {
                                var localPaymentOptions = "";
                                var selectedLocalPay = eval(<?php echo json_encode($this->get_option('local_payment_method_options')); ?>);
                                for (var paykey in localPayment) {
                                    if (localPayment.hasOwnProperty(paykey)) {
                                        var sel = "";
                                        if( selectedLocalPay.indexOf(paykey) !='-1' ) {
                                            sel = ' selected="selected"';
                                        }
                                        localPaymentOptions += "<option"+ sel +" value='" + paykey + "'>" + localPayment[paykey] + "</option>";
                                        jQuery('select[name="woocommerce_wanderlust_firstdata_gateway_local_payment_method_options[]"]').find('option').remove();
                                        jQuery('select[name="woocommerce_wanderlust_firstdata_gateway_local_payment_method_options[]"]').append( localPaymentOptions );
                                    }
                                }
                            }

                            jQuery('select[name="woocommerce_wanderlust_firstdata_gateway_reseller"]').find('option').not(':first').remove();
                            jQuery('select[name="woocommerce_wanderlust_firstdata_gateway_reseller"]').append( sellerOptions );
                            jQuery('select[name="woocommerce_wanderlust_firstdata_gateway_reseller"] option[value="'+sellerSel+'"]').attr("selected",true);

                        }
                                    
                        if(countrySel == 'mex') {
                            jQuery('.delay-month').show();
                        } else {
                            jQuery('.delay-month').hide();
                        }
            <?php
            if ($resellerFeatures['dynamic_merchant_name'] == 'yes') {
                echo "jQuery('.dynamic-descriptor').closest('tr').show();";
            } else {
                echo "jQuery('.dynamic-descriptor').closest('tr').hide();";
            }
            if ($resellerFeatures['instalments'] == 'yes') {
                echo "jQuery('.instalment-config').show();";
                echo "jQuery('.instalment-config').next('table').show();";
            } else {
                echo "jQuery('.instalment-config').hide();";
                echo "jQuery('.instalment-config').next('table').hide();";
            }
            if ($resellerFeatures['secure_pay'] == 'yes') {
                echo "jQuery('.secure-pay').closest('tr').show();";
            } else {
                echo "jQuery('.secure-pay').closest('tr').hide();";
            }
            if ($resellerFeatures['dcc_skip_offer'] == 'yes') {
                echo "jQuery('.dcc-skip-offer').closest('tr').show();";
            } else {
                echo "jQuery('.dcc-skip-offer').closest('tr').hide();";
            }
            if (!empty($resellerFeatures['local_payment'])) {
                echo "jQuery('.local-payment-method-title').show();";
                echo "jQuery('.local-payment-method, .local-payment-method-options').closest('tr').show();";
            } else {
                echo "jQuery('.local-payment-method-title').hide();";
                echo "jQuery('.local-payment-method, .local-payment-method-options').closest('tr').hide();";
            }
        }
        ?>
                jQuery('.checkout-option').change('click',function() {
                    if(jQuery('.checkout-option').val()==="combinedpage") {
                        jQuery('.payment-mode').closest('tr').hide();
                        jQuery('.tokenisation').closest('tr').hide();
                    } else {
                        jQuery('.payment-mode').closest('tr').show();
                        jQuery('.tokenisation').closest('tr').show();
                    }
                });
                /*
                 * Mexico reseller
                 *Added by mom*/
                var sellerSel = "<?php echo $this->get_option('reseller'); ?>";	
                if(sellerSel == "firstdatamexico"){
                    jQuery("#woocommerce_wanderlust_firstdata_gateway_interest_instalment").closest("tr").show();
                } else {
                    jQuery("#woocommerce_wanderlust_firstdata_gateway_interest_instalment").closest("tr").hide();
                    jQuery("#woocommerce_wanderlust_firstdata_gateway_interest_instalment").attr('checked', false);
                }

                var localMethod = "<?php echo $this->get_option('local_payment_method'); ?>";	
                if(localMethod == "yes"){
                    jQuery('.local-payment-method-options').closest('tr').show();
                } else {
                    jQuery('.local-payment-method-options').closest('tr').hide();
                }
            });
        </script>

        <?php
        return ob_get_clean();
    }

    /**
     * Do some additonal validation before saving options via the API.
     */
    public function process_admin_options() {
        // If a certificate has been uploaded, read the contents and save that string instead.
        if (array_key_exists('woocommerce_wanderlust_firstdata_gateway_server_trust_pem', $_FILES)
                && array_key_exists('tmp_name', $_FILES['woocommerce_wanderlust_firstdata_gateway_server_trust_pem'])
                && array_key_exists('size', $_FILES['woocommerce_wanderlust_firstdata_gateway_server_trust_pem'])
                && $_FILES['woocommerce_wanderlust_firstdata_gateway_server_trust_pem']['size'] && $this->file_validate($_FILES['woocommerce_wanderlust_firstdata_gateway_server_trust_pem']['tmp_name'], "text/plain")) {

            file_put_contents(dirname(__FILE__) . "/keys/ipg_online_trust.pem", file_get_contents($_FILES['woocommerce_wanderlust_firstdata_gateway_server_trust_pem']['tmp_name']));
            $_POST['woocommerce_wanderlust_firstdata_gateway_server_trust_pem'] = str_replace('\\', '/', realpath(dirname(__FILE__)) . "/keys/ipg_online_trust.pem");
            unlink($_FILES['woocommerce_wanderlust_firstdata_gateway_server_trust_pem']['tmp_name']);
            unset($_FILES['woocommerce_wanderlust_firstdata_gateway_server_trust_pem']);
        } else {
            $_POST['woocommerce_wanderlust_firstdata_gateway_server_trust_pem'] = $this->get_option('server_trust_pem');
        }

        if (array_key_exists('woocommerce_wanderlust_firstdata_gateway_client_certificate_pemfile', $_FILES)
                && array_key_exists('tmp_name', $_FILES['woocommerce_wanderlust_firstdata_gateway_client_certificate_pemfile'])
                && array_key_exists('size', $_FILES['woocommerce_wanderlust_firstdata_gateway_client_certificate_pemfile'])
                && $_FILES['woocommerce_wanderlust_firstdata_gateway_client_certificate_pemfile']['size'] && $this->file_validate($_FILES['woocommerce_wanderlust_firstdata_gateway_client_certificate_pemfile']['tmp_name'], "text/plain")) {

            file_put_contents(dirname(__FILE__) . "/keys/client_certificate.pem", file_get_contents($_FILES['woocommerce_wanderlust_firstdata_gateway_client_certificate_pemfile']['tmp_name']));
            $_POST['woocommerce_wanderlust_firstdata_gateway_client_certificate_pemfile'] = str_replace('\\', '/', realpath(dirname(__FILE__)) . "/keys/client_certificate.pem");
            unlink($_FILES['woocommerce_wanderlust_firstdata_gateway_client_certificate_pemfile']['tmp_name']);
            unset($_FILES['woocommerce_wanderlust_firstdata_gateway_client_certificate_pemfile']);
        } else {
            $_POST['woocommerce_wanderlust_firstdata_gateway_client_certificate_pemfile'] = $this->get_option('client_certificate_pemfile');
        }

        if (array_key_exists('woocommerce_wanderlust_firstdata_gateway_client_certificate_keyfile', $_FILES)
                && array_key_exists('tmp_name', $_FILES['woocommerce_wanderlust_firstdata_gateway_client_certificate_keyfile'])
                && array_key_exists('size', $_FILES['woocommerce_wanderlust_firstdata_gateway_client_certificate_keyfile'])
                && $_FILES['woocommerce_wanderlust_firstdata_gateway_client_certificate_keyfile']['size'] && $this->file_validate($_FILES['woocommerce_wanderlust_firstdata_gateway_client_certificate_keyfile']['tmp_name'], "text/plain")) {

            file_put_contents(dirname(__FILE__) . "/keys/client_certificate.key", file_get_contents($_FILES['woocommerce_wanderlust_firstdata_gateway_client_certificate_keyfile']['tmp_name']));
            $_POST['woocommerce_wanderlust_firstdata_gateway_client_certificate_keyfile'] = str_replace('\\', '/', realpath(dirname(__FILE__)) . "/keys/client_certificate.key");
            unlink($_FILES['woocommerce_wanderlust_firstdata_gateway_client_certificate_keyfile']['tmp_name']);
            unset($_FILES['woocommerce_wanderlust_firstdata_gateway_client_certificate_keyfile']);
        } else {
            $_POST['woocommerce_wanderlust_firstdata_gateway_client_certificate_keyfile'] = $this->get_option('client_certificate_keyfile');
        }

        parent::process_admin_options();

        // Validate credentials.
        //$this->validate_active_credentials();
    }

    public function file_validate($file_url = '', $mimetype = '') {
        $mime = "Notset";
        $isValid = false;
        if (file_exists($file_url)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file_url);
            finfo_close($finfo);
        }
        if ($mimetype == $mime)
            $isValid = true;

        return $isValid;
    }

    /**
     * Can the order be refunded via First Data Gateway?
     * @param  WC_Order $order
     * @return bool
     */
    public function can_refund_order($order) {
        return $order && $order->get_transaction_id();
    }

    /**
     * Init the API class and set the username/password etc.
     */
    protected function init_api() {
        include_once( dirname(__FILE__) . '/includes/firstdata-api-handler.php' );

        Firstdata_API_Handler::$api_url = $url = $this->getDetails('apiurl');
        Firstdata_API_Handler::$api_username = $this->get_option('api_username');
        Firstdata_API_Handler::$api_password = $this->get_option('api_password');
        Firstdata_API_Handler::$certificate_key_password = $this->get_option('certificate_key_password');
        Firstdata_API_Handler::$server_trust_pem = $this->get_option('server_trust_pem');
        Firstdata_API_Handler::$client_certificate_pemfile = $this->get_option('client_certificate_pemfile');
        Firstdata_API_Handler::$client_certificate_keyfile = $this->get_option('client_certificate_keyfile');
    }

    /**
     * Process a refund if supported.
     * @param  int    $order_id
     * @param  float  $amount
     * @param  string $reason
     * @return bool|WP_Error
     */
    public function process_refund($order_id, $amount = null, $reason = '') {
        $this->log('Refund Order Id : ' . $order_id);
        $order = wc_get_order($order_id);
        if (!$this->can_refund_order($order)) {
            $this->log('Refund Failed: No transaction ID', 'error');
            return new WP_Error('error', __('Refund failed: No transaction ID', 'woocommerce'));
        }

        $this->init_api();

        $total = number_format($order->get_total(), 2, '.', '');
        $txn_type = get_post_meta($order_id, '_txn_type', true);
        $amt = number_format($amount, 2, '.', '');
        $method = "";
        if ($txn_type == "preauth" && $amt == $total) {
            $method = "void";
        } else if ($txn_type == "sale") {
            $method = "return";
        } else {
            return new WP_Error('error', __('partial amount refund is not possible on preauth transaction', 'woocommerce'));
        }
        $request_param = array();
        $request_param['order_id'] = $order_id;
        $request_param['method'] = $method;
        $request_param['order_hash_id'] = get_post_meta($order_id, '_order_hash_id', true);
        $request_param['transaction_id'] = get_post_meta($order_id, '_transaction_id', true);
        $request_param['order_currency'] = get_post_meta($order_id, '_order_currency', true);
        $request_param['amount'] = $amount;
        $request_param['reason'] = $reason;

        $result = Firstdata_API_Handler::refund_transaction($request_param);
        if (isset($result->ipgapi_ApprovalCode) && isset($result->ipgapi_TransactionResult)) {
            $approvalcode = substr($result->ipgapi_ApprovalCode, 0, 1);

            if ($approvalcode == 'Y' && strtolower($result->ipgapi_TransactionResult) == "approved" && strtolower($result->method) == "return") {

                $order->add_order_note(__('Refunded on ' . date("Y-m-d h:i:s") . ' with Transaction ID = ' . $result->ipgapi_IpgTransactionId . ' using ' . strtoupper($result->method), 'woocommerce'));
                if (($order->get_total() - $order->get_total_refunded()) == $amount) {
                    $order->update_status('wc-refunded');
                }
                return true;
            }
            if ($approvalcode == 'Y' && strtolower($result->ipgapi_TransactionResult) == "approved" && strtolower($result->method) == "void") {
                $order->add_order_note(__('Refunded on ' . date("Y-m-d h:i:s") . ' with Transaction ID = ' . $result->ipgapi_IpgTransactionId . ' using ' . strtoupper($result->method), 'woocommerce'));
                $order->update_status('wc-cancelled');
                return true;
            }
            if ($approvalcode == 'N' && strtolower($result->ipgapi_TransactionResult) == "failed") {
                $this->log('Refund Failed: ' . $result->ipgapi_ErrorMessage, 'error');
                return new WP_Error('error', $result->ipgapi_ErrorMessage);
            }
        }
        return isset($result->ipgapi_ErrorMessage) ? new WP_Error('error', $result->ipgapi_ErrorMessage) : false;
    }

    public function getCardSupportLocalPay($method, $type = 'C') {
        $paymentMethodOption = $this->paymentOptionsValue();
        $checkoutValidation = true;
        if (array_key_exists($method, $paymentMethodOption)) {
            $selLocalPayOption = $paymentMethodOption[$method];
            if ($type == 'C') {
                if ($selLocalPayOption['card_support'] == false) {
                    $checkoutValidation = false;
                }
            } else {
                if ($selLocalPayOption['shipping_support'] == false) {
                    $checkoutValidation = false;
                }
            }
        }
        return $checkoutValidation;
    }

    /*
     * Valida checkout for hidden payment mode
     * Since 1.0
     * Return Validation Error
     */

    public function checkout_card_validation() {
    
    if($_POST['payment_method'] == 'wanderlust_firstdata_gateway' ){
    
            $token_id = $this->get_post_var('wc-' . $this->id . '-payment-token');
            $paymentMethod = $this->get_post_var('local_payment_option');
            if ($this->authorization == 'hidden' &&
                    ($token_id == 'new' || $token_id == '') &&
                    $this->getCardSupportLocalPay($paymentMethod) == true) {

                $cardNumber = str_replace(" ", "", $this->get_post_var($this->id . '-card-number'));
                $cardExpiry = str_replace(" ", "", $this->get_post_var($this->id . '-card-expiry'));
                $getCardExp = explode("/", $cardExpiry);
                $cardcvc = $this->get_post_var($this->id . '-card-cvc');
                $expiry_validation = true;
                if ($cardExpiry != '' && (strlen($getCardExp[1]) == 2 || strlen($getCardExp[1]) == 4)) {
                    if (strlen($getCardExp[1]) == 2)
                        $date = DateTime::createFromFormat('m/y', $cardExpiry);
                    if (strlen($getCardExp[1]) == 4)
                        $date = DateTime::createFromFormat('m/Y', $cardExpiry);
                    if (isset($date))
                        $expiry_validation = (strtotime($date->format('Y-m')) >= strtotime(date('Y-m'))) ? true : false;
                }

                if ($cardNumber == '') {
                    wc_add_notice(__('Card Number is a required field.', 'woocommerce-firstdata'), 'error');
                } else if (!preg_match('/^[0-9]{14,16}$/', $cardNumber)) {
                    wc_add_notice(__('Card Numberis is Invalid.', 'woocommerce-firstdata'), 'error');
                }


                if ($cardExpiry == '') {
                    wc_add_notice(__('Expiry Date is a required field.', 'woocommerce-firstdata'), 'error');
                } else if (!preg_match('/^([0-9]{1,2})\/([0-9]{2}|[0-9]{4})$/', $cardExpiry) || !$expiry_validation) {
                    wc_add_notice(__('Expiry Date is Invalid.', 'woocommerce-firstdata'), 'error');
                }

                if ($cardcvc == '') {
                    wc_add_notice(__('Card CVC is a required field.', 'woocommerce-firstdata'), 'error');
                } else if (!preg_match('/^[0-9]{3,4}$/', $cardcvc)) {
                    wc_add_notice(__('Card CVC is Invalid.', 'woocommerce-firstdata'), 'error');
                }
            }
         } 
    }

    public function get_currency_code_by_country($counryCode) {
        $allCountryCode = array(
            'MYR' => '458', 'PLN' => '985', 'NOK' => '578', 'RUB' => '643',
            'AED' => '784', 'CNY' => '156', 'KRW' => '410', 'ILS' => '376',
            'SAR' => '682', 'TRY' => '949', 'HKD' => '344', 'HUF' => '348',
            'KWD' => '414', 'INR' => '356', 'RON' => '946', 'SGD' => '702',
            'MXN' => '484', 'AUD' => '036', 'NZD' => '554', 'EEK' => '233',
            'LTL' => '440', 'USD' => '840', 'ZAR' => '710', 'BRL' => '986',
            'CAD' => '124', 'JPY' => '392', 'SEK' => '752', 'CZK' => '203',
            'DKK' => '208', 'NGN' => '566', 'EUR' => '978', 'GBP' => '826',
            'CHF' => '756', 'HRK' => '191', 'BHD' => '048', 'QAR' => '634',
            'THB' => '764', 'BND' => '096', 'MAD' => '504', 'TND' => '788',
            'BIF' => '108', 'BYN' => '933', 'ARS' => '032', 'CLP' => '152',
            'BDT' => '050', 'KES' => '404', 'MUR' => '480', 'NPR' => '524',
            'BYR' => '974', 'MDL' => '498', 'BOB' => '068', 'PYG' => '600',
            'NAD' => '516', 'DZD' => '012', 'BBD' => '052', 'OMR' => '512',
            'BSD' => '044', 'BZD' => '084', 'KYD' => '136', 'GTQ' => '320',
            'DOP' => '214', 'GYD' => '328', 'JMD' => '388', 'ANG' => '532',
            'AWG' => '533', 'XOF' => '952', 'TTD' => '780', 'XCD' => '951',
            'SRD' => '968', 'UGX' => '800', 'MMK' => '104', 'CRC' => '188',
            'UYU' => '858', 'COP' => '170', 'EGP' => '818', 'FJD' => '242',
            'IDR' => '360', 'AFN' => '971', 'IQD' => '368', 'IRR' => '364',
            'ISK' => '352', 'LAK' => '418', 'LKR' => '144', 'JOD' => '400',
            'MOP' => '446', 'PHP' => '608', 'PKR' => '586', 'SCR' => '690',
            'TWD' => '901', 'BMD' => '060', 'VND' => '704', 'PEN' => '604',
            'RSD' => '941', 'KZT' => '398', 'BWP' => '072', 'TZS' => '834',
            'BGN' => '975', 'AZN' => '944', 'UAH' => '980', 'LBP' => '422'
        );
        if (array_key_exists($counryCode, $allCountryCode)) {
            return $allCountryCode[$counryCode];
        } else {
            $this->log('Currency Code Not supported by Payment Gateway for  : ' . $counryCode);
            return false;
        }
    }

    /**
     * Logging method.
     *
     * @param string $message Log message.
     * @param string $level   Optional. Default 'info'.
     *     emergency|alert|critical|error|warning|notice|info|debug
     */
    public static function log($message, $level = 'info') {
        if (self::$log_enabled) {
            if (empty(self::$log)) {
                self::$log = wc_get_logger();
            }
            self::$log->log($level, $message, array('source' => 'firstdata'));
        }
    }

    /*
     * Util function to get particular selected reseller details
     * @return reseller detail
     */

    public function getDetails($type) {
        $return = '';

        $country = $this->get_option('country');
        $feature = Firstdata_Utility::getFeatures();
        $reseller = $this->get_option('reseller');
        if (array_key_exists($country, $feature) && array_key_exists($reseller, $feature[$country])) {
            $resellerFeatures = $feature[$country][$reseller];

            switch ($type) {
                case "customer":
                    $return = $resellerFeatures['customer_detail_title'];
                    break;
                case "customer_detail":
                    $return = $resellerFeatures['customer_detail'];
                    break;
                case "contact_support_title":
                    $return = $resellerFeatures['contact_support_title'];
                    break;
                case "contact_support":
                    $return = $resellerFeatures['contact_support'];
                    break;
                case "connecturl":
                    if ($this->get_option('env') == 'TEST') {
                        $return = $resellerFeatures['testurl'];
                    } else {
                        $return = $resellerFeatures['produrl'];
                    }
                    break;
                case "apiurl":
                    if ($this->get_option('env') == 'TEST') {
                        $return = $resellerFeatures['apiurl'];
                    } else {
                        $return = $resellerFeatures['prodapiurl'];
                    }
                    break;
                case "refunds":
                    $return = $resellerFeatures['refunds'];
                    break;
                default:
                    $return = '';
            }
        }
        return __($return, $this->id);
    }

    /*
     * Plugin Title for selected Reseller
     * @return Title
     */

    public function getPluginTitle() {
        $return = '';
        $country = $this->get_option('country');
        $feature = Firstdata_Utility::getFeatures();
        $reseller = $this->get_option('reseller');
        if (array_key_exists($country, $feature) && array_key_exists($reseller, $feature[$country])) {
            $resellerFeatures = $feature[$country][$reseller];
            $return = $resellerFeatures['plugin_name'];
        } else {
            $return = 'First Data';
        }
        return __($return, $this->id);
    }

    /*
     * Plugin Description for selected Reseller
     * @return Description
     */

    public function getDescription() {
        $return = $this->get_option('description') != '' ? $this->get_option('description') : 'First Data Gateway extension for WooCommerce';
        return __($return, $this->id);
    }

    /*
     * Plugin logo for selected Reseller
     * @return logo
     */
     
      public function thankyou_page($order_id) {
           
          $order = wc_get_order( $order_id );
          
        
           
          if($_POST['endpointTransactionId']){
            $order->add_order_note(
              'FirstData: ' .
              __( 'Payment approved.', 'wc-gateway-pagos360' )
            );
            $order->payment_complete();
          }
           
          
        }

    public function getIcon() {
        $return = null;
        if ($this->get_option('logo') == 'yes') {
            $country = $this->get_option('country');
            $feature = Firstdata_Utility::getFeatures();
            $reseller = $this->get_option('reseller');
            if (array_key_exists($country, $feature) && array_key_exists($reseller, $feature[$country])) {
                $return = $feature[$country][$reseller]['logo'];
            }
        }
        return __($return, $this->id);
    }

    public function getCardType() {
        $return = null;
        $country = $this->get_option('country');
        $feature = Firstdata_Utility::getFeatures();
        $reseller = $this->get_option('reseller');
        if (array_key_exists($country, $feature) && array_key_exists($reseller, $feature[$country])) {
            $return = $feature[$country][$reseller]['card_type'];
        }

        return __($return);
    }

    /*
     * Plugin Logo for checkout display
     * @return logo
     */

    public function checkLogo() {
        $return = null;
        $country = $this->get_option('country');
        $feature = Firstdata_Utility::getFeatures();
        $reseller = $this->get_option('reseller');
        if ($country != '' && $reseller) {
            if (array_key_exists($country, $feature) && array_key_exists($reseller, $feature[$country])) {
                $return = $feature[$country][$reseller]['logo'];
            } else {
                $return = "data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=";
            }
        } else {
            $return = "data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=";
        }
        return $return;
    }

    /**
     * Output field name HTML
     *
     * Gateways which support tokenization do not require names - we don't want the data to post to the server.
     *
     * @since  2.6.0
     * @param  string $name
     * @return string
     */
    public function field_name($name) {
        return ' name="' . esc_attr($this->id . '-' . $name) . '" ';
    }

    /**
     * RequestHash
     * @return Hashvalue
     * 
     */
    function createRequestHash($txndatetime, $chargetotal, $currency) {
        if ($this->env == "TEST") {
            $storename = $this->test_store_name;
            $sharedsecret = $this->test_trans_key;
        } else {
            $storename = $this->prod_store_name;
            $sharedsecret = $this->prod_trans_key;
        }
        $stringToHash = $storename . $txndatetime . $chargetotal . $currency . $sharedsecret;
        $ascii = bin2hex($stringToHash);
        return sha1($ascii);
    }

}
?>