<?php
/*
 Plugin Name: Axn Axn Fiserv - Custom - WooCommerce Gateway
 Plugin URI: https://axn-webdesign.com/
 Description: Axn Fiserv ePostnet Argentina for WooCommerce.
 Version: 13.0
 WC tested up to: 10.2.1
 Author: axn-webdesign.com
 Author URI: https://axn-webdesign.com/
 Text Domain: woocommerce-axn_fiserv
 Domain Path: /languages/
 */
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

add_action('plugins_loaded', 'axn_fiserv_gateway_init', 0);

add_action('wp_head', function () {
    if (is_checkout()) {
        echo '<style>.payment_method_axn_fiserv_gateway img { max-height: 52px !important; width: auto !important; }</style>';
    }
});

function axn_fiserv_gateway_init()
{

    if (!class_exists('WC_Payment_Gateway'))
        return;

    include_once('axn-fiserv.php');

    add_filter('woocommerce_payment_gateways', 'axn_add_axn_fiserv_gateway');

    function axn_add_axn_fiserv_gateway($methods)
    {
        $methods[] = 'AXN_Axn_fiserv_Gateway';
        return $methods;
    }

    load_plugin_textdomain('woocommerce-axn_fiserv', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'axn_fiserv_gateway_action_links');

function axn_fiserv_gateway_action_links($links)
{
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=axn_fiserv_gateway') . '">' . __('Settings', 'axn-fiserv-gateway') . '</a>',
    );

    return array_merge($plugin_links, $links);
}