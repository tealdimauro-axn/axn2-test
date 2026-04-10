<?php
/*
  Plugin Name: Wanderlust First Data - Custom - WooCommerce Gateway
  Plugin URI: https://wanderlust-webdesign.com/
  Description: First Data ePostnet Argentina for WooCommerce.
  Version: 13.0
  WC tested up to: 10.2.1
  Author: wanderlust-webdesign.com
  Author URI: https://wanderlust-webdesign.com/

  Text Domain: woocommerce-firstdata
  Domaith: 
 */
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

add_action('plugins_loaded', 'firstdata_gateway_init', 0);

function firstdata_gateway_init()
{

    if (!class_exists('WC_Payment_Gateway'))
        return;

    include_once('firstdata.php');

    add_filter('woocommerce_payment_gateways', 'wanderlust_add_firstdata_gateway');

    function wanderlust_add_firstdata_gateway($methods)
    {
        $methods[] = 'Wanderlust_Firstdata_Gateway';
        return $methods;
    }

    load_plugin_textdomain('woocommerce-firstdata', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wanderlust_firstdata_gateway_action_links');

function wanderlust_firstdata_gateway_action_links($links)
{
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=wanderlust_firstdata_gateway') . '">' . __('Settings', 'wanderlust-firstdata-gateway') . '</a>',
    );

    return array_merge($plugin_links, $links);
}