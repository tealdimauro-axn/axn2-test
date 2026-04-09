<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings for Axn Fiserv Gateway.
 */
return array(
    'country' => array(
        'title' => __('Country', $this->id),
        'type' => 'select',
        'class' => 'wc-enhanced-select country',
        'description' => __('Choose the country.', $this->id),
        'desc_tip' => true,
        'options' => array(
            '' => __('Select the Country', $this->id),
            'arg' => __('Argentina', $this->id)
        ),
    ),
    'reseller' => array(
        'title' => __('Reseller', $this->id),
        'type' => 'select',
        'class' => 'wc-enhanced-select reseller',
        'description' => __('Choose the Reseller.', $this->id),
        'default' => '',
        'desc_tip' => true,
        'options' => array(
            '' => __('Select the Reseller', $this->id),
            'posnet' => __('Fiserv', $this->id),

        ),
    ),
    'customer_detail' => array(
        'title' => $this->getDetails('customer_detail_title'),
        'type' => 'title',
        'class' => 'customer-details',
        'description' => '<span id="fd-contact-detail-desc">' . $this->getDetails('customer_detail') . '</span>',
    ),
    'logo' => array(
        'title' => __('Plugin Logo', $this->id),
        'label' => __('Display Logo <img src="' . $this->checkLogo() . '" style="float: left; margin-top:-10px; margin-right:10px; max-height:52px;" id="fd-plugin-logo"/>', $this->id),
        'desc_tip' => __('Show this logo in checkout page', $this->id),
        'type' => 'checkbox',
        'default' => 'no',
    ),
    'description' => array(
        'title' => __('Description', $this->id),
        'type' => 'textarea',
        'class' => 'description',
        'desc_tip' => __('Payment description the customer will see during the checkout process.', $this->id),
        'default' => $this->getDescription(),
        'css' => 'max-width:350px;'
    ),
    'enabled' => array(
        'title' => __('Enable / Disable', $this->id),
        'label' => __('Enable this payment gateway.', $this->id),
        'type' => 'checkbox',
        'default' => 'no',
    ),
    'env' => array(
        'title' => __('Environment', $this->id),
        'type' => 'select',
        'class' => 'wc-enhanced-select environment',
        'description' => __('This allows you to choose the payment gateway environment. The integration environment allows you to process transactions in the test environment. The production environment will allow customers to process live transactions.', $this->id),
        'default' => 'TEST',
        'desc_tip' => true,
        'options' => array(
            'PROD' => __('Production', $this->id),
            'TEST' => __('Test', $this->id),
        ),
    ),
    'test_trans_key' => array(
        'title' => __('Shared Secret', $this->id),
        'type' => 'text',
        'class' => 'test-trans-key',
        'desc_tip' => __('This is a unique identifier provided by the payment gateway, which will be provided by the payment gateway support team.', $this->id),
        'default' => ''
    ),
    'test_store_name' => array(
        'title' => __('Store name', $this->id),
        'type' => 'text',
        'class' => 'test-store-name',
        'desc_tip' => __('This is your unique Store ID, which will be provided by the payment gateway support team.', $this->id),
        'default' => '',
    ),
    'test_success_url' => array(
        'title' => __('Response success URL', $this->id),
        'type' => 'text',
        'class' => 'test-success-url',
        'desc_tip' => __('The URL where you wish to direct customers after a successful transaction (your \'Thank You\' URL).', $this->id),
        'default' => '',
    ),
    'test_fail_url' => array(
        'title' => __('Response fail URL', $this->id),
        'type' => 'text',
        'class' => 'test-fail-url',
        'desc_tip' => __('The URL where you wish to direct customers after a declined or unsuccessful transaction (your \'Sorry\' URL).', $this->id),
        'default' => '',
    ),
    'test_notification_url' => array(
        'title' => __('Notification URL', $this->id),
        'type' => 'text',
        'class' => 'test-notification-url',
        'desc_tip' => __('In addition to the response you receive in hidden fields to your ‘responseSuccessURL’ or ‘responseFailURL’, the payment gateway can send server-to-server notifications with the above result parameters to a defined URL. This is useful to keep your systems in sync with the status of a transaction.', $this->id),
        'default' => '',
    ),
    'prod_trans_key' => array(
        'title' => __('Shared Secret', $this->id),
        'type' => 'text',
        'class' => 'prod-trans-key',
        'desc_tip' => __('This is a unique identifier provided by the payment gateway, which will be provided by the payment gateway support team.', $this->id),
    ),
    'prod_store_name' => array(
        'title' => __('Store name', $this->id),
        'type' => 'text',
        'class' => 'prod-store-name',
        'desc_tip' => __('This is your unique Store ID, which will be provided by the payment gateway support team.', $this->id),
    ),
    'prod_success_url' => array(
        'title' => __('Response success URL', $this->id),
        'type' => 'text',
        'class' => 'prod-success-url',
        'desc_tip' => __('The URL where you wish to direct customers after a successful transaction (your \'Thank You\' URL).', $this->id),
        'default' => '',
    ),
    'prod_fail_url' => array(
        'title' => __('Response fail URL', $this->id),
        'type' => 'text',
        'class' => 'prod-fail-url',
        'desc_tip' => __('The URL where you wish to direct customers after a declined or unsuccessful transaction (your \'Sorry\' URL).', $this->id),
        'default' => '',
    ),
    'prod_notification_url' => array(
        'title' => __('Notification URL', $this->id),
        'type' => 'text',
        'class' => 'prod-notification-url',
        'desc_tip' => __('In addition to the response you receive in hidden fields to your ‘responseSuccessURL’ or ‘responseFailURL’, the payment gateway can send server-to-server notifications with the above result parameters to a defined URL. This is useful to keep your systems in sync with the status of a transaction.', $this->id),
        'default' => '',
    ),
    'authorisation' => array(
        'title' => __('Authorisation', $this->id),
        'label' => __('Capture charge immediately', $this->id),
        'desc_tip' => __('This allows you to capture the charge immediately. If this box is not selected, a temporary hold will be placed on the funds in the customer\'s bank account, and you will need to manually settle the transaction to capture the charge. This can be done through your Virtual Terminal - please contact your gateway support team for your login details.', $this->id),
        'type' => 'checkbox',
        'default' => 'yes',
    ),
    'checkout' => array(
        'title' => __('Checkout Mode', $this->id),
        'type' => 'select',
        'class' => 'wc-enhanced-select checkout-option',
        'description' => __('The "classic" checkout option splits the payment process into multiple pages, whereby you can easily decide the information you wish to gather from the customer. The "combined" checkout option consolidates the process into a single page which automatically responds to the size of the screen being used.', $this->id),
        'default' => 'classic',
        'desc_tip' => true,
        'options' => array(
            'classic' => __('Classic', $this->id),
            'combinedpage' => __('Combined', $this->id),
        ),
    ),
    'mode' => array(
        'title' => __('Payment Mode', $this->id),
        'type' => 'select',
        'class' => 'wc-enhanced-select payment-mode',
        'description' => __('The "Pay Only" payment mode collects just the basic payment informaton. "Pay Plus" mode collects the payment information and the billing information. "Full Pay" mode collects the payment information, billing information and shipping information.', $this->id),
        'default' => 'payonly',
        'desc_tip' => true,
        'options' => array(
            'payonly' => __('Pay Only ', $this->id),
            'payplus' => __('Pay Plus ', $this->id),
            'fullpay' => __('Full Pay', $this->id),
        ),
    ),
    'tokenisation' => array(
        'title' => __('Tokenisation', $this->id),
        'class' => 'tokenisation',
        'label' => __('Allow customers to securely save their payment details for future checkout.', $this->id),
        'desc_tip' => __('Enabling tokenisation allows you to store card details for subsequent transactions in a secure encrypted database hosted by Payment Gateway. Please contact the gateway support team to ensure this is enabled on your account.', $this->id),
        'type' => 'checkbox',
        'default' => 'no',
    ),
    'dynamic_merchant_name' => array(
        'title' => __('Dynamic Descriptor', $this->id),
        'type' => 'text',
        'class' => 'dynamic-descriptor',
        'desc_tip' => __('This allows you to define the company name to appear on the cardholder\'s statement. The length of this field should not exceed 25 characters.', $this->id),
        'default' => '',
    ),
    'secure_pay' => array(
        'title' => __('3D Secure', $this->id),
        'type' => 'select',
        'class' => 'wc-enhanced-select secure-pay',
        'description' => __('Enable 3D Secure', $this->id),
        'default' => 'active',
        'desc_tip' => true,
        'options' => array(
            'active' => __('Active', $this->id),
            'deactive' => __('Deactive', $this->id),
        ),
    ),
    'debug' => array(
        'title' => __('Debug log', $this->id),
        'type' => 'checkbox',
        'label' => __('Enable Debug Logging', $this->id),
        'default' => 'yes',
        'desc_tip' => 'Enabling the error logs help you to determine the cause of any particular issue that may have occurred during a transaction.',
    ),
    'dcc_skip_offer' => array(
        'title' => __('Dynamic Currency conversion', $this->id),
        'label' => __('Currency Conversion Page', $this->id),
        'desc_tip' => __('Dynamic Currency Conversion offers customers the choice to either pay in your currency or their own currency at the checkout, based on a real-time currency conversion. Please contact the gateway support team to set this up.', $this->id),
        'type' => 'checkbox',
        'class' => 'dcc-skip-offer',
        'default' => 'no',
    ),
    'authorization' => array(
        'title' => __('Authorisation Method', $this->id),
        'type' => 'select',
        'class' => 'wc-enhanced-select',
        'description' => __('If "Hosted Page" is chosen, the customer will be redirected to a secure payment page hosted by Axn Fiserv Gateway to enter their payment details. If "Hidden Authorisation" is chosen, you will host the initial payment page on your site.', $this->id),
        'default' => 'hosted',
        'desc_tip' => true,
        'options' => array(
            'hosted' => __('Hosted Payment', $this->id),
            'hidden' => __('Hidden Authorisation', $this->id),
        ),
    ),

    'multiple_payment' => array(
        'title' => __('Instalment Payment Options', $this->id),
        'type' => 'title',
        'class' => 'instalment-config',
    ),
    'rule_activation' => array(
        'title' => __('Instalment', $this->id),
        'label' => __('Enable/Disable', $this->id),
        'desc_tip' => __('Enables / disables multiple payment ', $this->id),
        'description' => __('Enables / disables multiple payment'),
        'class' => 'ipg_rule_activation instalment-config',
        'type' => 'checkbox',
        'default' => 'no',
    ),
    'interest_instalment' => array(
        'title' => __('Instalments Interest For Mexico', $this->id),
        'label' => __('Enable/Disable', $this->id),
        'desc_tip' => __('Enables / disables multiple payment ', $this->id),
        'description' => __('Enables / disables multiple payment'),
        'class' => 'ipg_rule_activation instalment-config',
        'type' => 'checkbox',
        'default' => 'no',
    ),
    'instalment_details' => array(
        'type' => 'instalment_details',
        'class' => 'instalment-config',
    ),
    'local_payment_method_title' => array(
        'title' => __('Local Payment Method', $this->id),
        'type' => 'title',
        'class' => 'local-payment-method-title',
        'desc_tip' => __('Enable the Local Payment Method and select the payment method for checkout.', $this->id),
    ),
    'local_payment_method' => array(
        'title' => __('Enable Local Payment Method', $this->id),
        'label' => __('Enable/Disable Local Payment Method for checkout', $this->id),
        'desc_tip' => __('Enable the local payment method to checkout', $this->id),
        'type' => 'checkbox',
        'class' => 'local-payment-method',
        'default' => 'no',
    ),
    'local_payment_method_options' => array(
        'title' => __('Payment Method', $this->id),
        'type' => 'multiselect',
        'class' => 'local-payment-method-options chosen_select',
        'css' => 'width: 350px;',
        'desc_tip' => __('Select the Payment local/alternative payment method to checkout.', $this->id),
        'options' => $this->paymentOptionsValue(),
        'default' => array(),
    ),
    'localization' => array(
        'type' => 'localization',
    )
);
